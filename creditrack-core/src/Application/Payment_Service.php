<?php
namespace CrediTrack\Application;

use CrediTrack\Domain\Money;
use CrediTrack\Infrastructure\Audit;
use WP_Error;

final class Payment_Service {
	public function post( array $input ): int|WP_Error {
		if ( ! current_user_can( 'creditrack_record_payments' ) ) { return new WP_Error( 'forbidden', 'You cannot record payments.' ); }
		global $wpdb; $loan_id = absint( $input['loan_id'] ?? 0 ); $key = sanitize_text_field( $input['idempotency_key'] ?? '' );
		if ( strlen( $key ) < 16 ) { return new WP_Error( 'validation', 'The form token is invalid. Reload and try again.' ); }
		try { $amount = Money::minor( (string) ( $input['amount'] ?? '' ) ); } catch ( \Throwable ) { return new WP_Error( 'validation', 'Enter a valid payment amount.', array( 'amount' => 'Enter a valid amount.' ) ); }
		if ( $amount <= 0 ) { return new WP_Error( 'validation', 'Payment amount must be greater than zero.', array( 'amount' => 'Must be greater than zero.' ) ); }
		$method = sanitize_text_field( $input['method'] ?? '' ); if ( ! in_array( $method, array( 'Cash','Bank Transfer','Mobile Money','Check','Other' ), true ) ) { return new WP_Error( 'validation', 'Select a valid payment method.' ); }
		$wpdb->query( 'START TRANSACTION' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ct_payments WHERE idempotency_key=%s", $key ) ); if ( $existing ) { $wpdb->query( 'ROLLBACK' ); return (int) $existing; }
		$loan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ct_loans WHERE id=%d FOR UPDATE", $loan_id ), ARRAY_A );
		if ( ! $loan || ! in_array( $loan['status'], array( 'Active','Defaulted' ), true ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'validation', 'Payments are only accepted for active or defaulted loans.' ); }
		$outstanding = Money::minor( $loan['outstanding'] ); if ( $amount > $outstanding ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'validation', 'Payment exceeds the outstanding balance of ' . Money::decimal( $outstanding ) . '.', array( 'amount' => 'Reduce the payment to the outstanding balance or less.' ) ); }
		$now = gmdate( 'Y-m-d H:i:s' ); $payment = array( 'loan_id' => $loan_id, 'amount' => Money::decimal( $amount ), 'payment_date' => sanitize_text_field( $input['payment_date'] ?? gmdate( 'Y-m-d' ) ), 'method' => $method, 'reference_number' => sanitize_text_field( $input['reference_number'] ?? '' ) ?: null, 'notes' => sanitize_textarea_field( $input['notes'] ?? '' ), 'is_advance' => ! empty( $input['is_advance'] ) ? 1 : 0, 'recorded_by' => get_current_user_id(), 'idempotency_key' => $key, 'is_reversal' => 0, 'created_at' => $now );
		if ( ! $wpdb->insert( $wpdb->prefix . 'ct_payments', $payment ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'database', 'Payment could not be posted.' ); }
		$payment_id = (int) $wpdb->insert_id; $remaining = $amount;
		$schedules = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ct_schedules WHERE loan_id=%d AND remaining_amount>0 ORDER BY due_date,id FOR UPDATE", $loan_id ), ARRAY_A );
		foreach ( $schedules as $schedule ) {
			if ( 0 === $remaining ) { break; } $due = Money::minor( $schedule['remaining_amount'] ); $allocated = min( $remaining, $due ); $new_due = $due - $allocated; $new_paid = Money::minor( $schedule['paid_amount'] ) + $allocated;
			$status = 0 === $new_due ? 'Paid' : 'Partial';
			$wpdb->insert( $wpdb->prefix . 'ct_payment_allocations', array( 'payment_id' => $payment_id, 'schedule_id' => $schedule['id'], 'amount' => Money::decimal( $allocated ), 'created_at' => $now ) );
			$wpdb->update( $wpdb->prefix . 'ct_schedules', array( 'paid_amount' => Money::decimal( $new_paid ), 'remaining_amount' => Money::decimal( $new_due ), 'status' => $status, 'paid_at' => 'Paid' === $status ? $now : null, 'overdue' => 0 === $new_due ? 0 : $schedule['overdue'], 'days_past_due' => 0 === $new_due ? 0 : $schedule['days_past_due'], 'updated_at' => $now ), array( 'id' => $schedule['id'] ) ); $remaining -= $allocated;
		}
		if ( 0 !== $remaining ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'allocation', 'Payment could not be fully allocated.' ); }
		$total_paid = Money::minor( $loan['total_paid'] ) + $amount; $new_outstanding = max( 0, Money::minor( $loan['total_repayable'] ) - $total_paid ); $update = array( 'total_paid' => Money::decimal( $total_paid ), 'outstanding' => Money::decimal( $new_outstanding ), 'updated_at' => $now ); if ( 0 === $new_outstanding ) { $update['status'] = 'Completed'; }
		$wpdb->update( $wpdb->prefix . 'ct_loans', $update, array( 'id' => $loan_id ) ); Audit::record( 'payment', $payment_id, 'post', array(), $payment, 'Payment posted and allocated' ); $wpdb->query( 'COMMIT' ); return $payment_id;
	}

	public function reverse( int $payment_id, string $reason ): int|WP_Error {
		if ( ! current_user_can( 'creditrack_reverse_payments' ) ) { return new WP_Error( 'forbidden', 'You cannot reverse payments.' ); }
		if ( '' === trim( $reason ) ) { return new WP_Error( 'validation', 'A reversal reason is required.' ); }
		global $wpdb; $wpdb->query( 'START TRANSACTION' );
		$payment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ct_payments WHERE id=%d FOR UPDATE", $payment_id ), ARRAY_A );
		if ( ! $payment || $payment['is_reversal'] || $payment['reversed_at'] ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'validation', 'This payment cannot be reversed or has already been reversed.' ); }
		$loan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ct_loans WHERE id=%d FOR UPDATE", $payment['loan_id'] ), ARRAY_A );
		$allocations = $wpdb->get_results( $wpdb->prepare( "SELECT a.*,s.paid_amount,s.remaining_amount,s.total_installment,s.due_date FROM {$wpdb->prefix}ct_payment_allocations a JOIN {$wpdb->prefix}ct_schedules s ON s.id=a.schedule_id WHERE a.payment_id=%d ORDER BY a.id FOR UPDATE", $payment_id ), ARRAY_A );
		$now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $allocations as $allocation ) {
			$restored_paid = max( 0, Money::minor( $allocation['paid_amount'] ) - Money::minor( $allocation['amount'] ) ); $remaining = Money::minor( $allocation['total_installment'] ) - $restored_paid; $overdue = $allocation['due_date'] < gmdate( 'Y-m-d' );
			$wpdb->update( $wpdb->prefix . 'ct_schedules', array( 'paid_amount' => Money::decimal( $restored_paid ), 'remaining_amount' => Money::decimal( $remaining ), 'status' => $restored_paid > 0 ? 'Partial' : ( $overdue ? 'Overdue' : 'Pending' ), 'paid_at' => null, 'overdue' => $overdue ? 1 : 0, 'days_past_due' => $overdue ? (int) ( new \DateTimeImmutable( $allocation['due_date'] ) )->diff( new \DateTimeImmutable( 'today' ) )->days : 0, 'updated_at' => $now ), array( 'id' => $allocation['schedule_id'] ) );
		}
		$amount = Money::minor( $payment['amount'] ); $new_paid = max( 0, Money::minor( $loan['total_paid'] ) - $amount ); $new_outstanding = Money::minor( $loan['total_repayable'] ) - $new_paid;
		$key = wp_generate_uuid4(); $wpdb->insert( $wpdb->prefix . 'ct_payments', array( 'loan_id' => $loan['id'], 'amount' => Money::decimal( -$amount ), 'payment_date' => gmdate( 'Y-m-d' ), 'method' => $payment['method'], 'reference_number' => 'REV-' . $payment_id, 'notes' => $reason, 'is_advance' => 0, 'recorded_by' => get_current_user_id(), 'idempotency_key' => $key, 'is_reversal' => 1, 'reverses_payment_id' => $payment_id, 'created_at' => $now ) ); $reversal_id = (int) $wpdb->insert_id;
		if ( ! $reversal_id || false === $wpdb->update( $wpdb->prefix . 'ct_payments', array( 'reversed_at' => $now, 'reversed_by' => get_current_user_id(), 'reversal_reason' => $reason ), array( 'id' => $payment_id, 'reversed_at' => null ) ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'database', 'Payment reversal failed.' ); }
		$status = 'Completed' === $loan['status'] ? 'Active' : $loan['status']; $wpdb->update( $wpdb->prefix . 'ct_loans', array( 'total_paid' => Money::decimal( $new_paid ), 'outstanding' => Money::decimal( $new_outstanding ), 'status' => $status, 'updated_at' => $now ), array( 'id' => $loan['id'] ) );
		Audit::record( 'payment', $payment_id, 'reverse', array( 'amount' => $payment['amount'] ), array( 'reversal_id' => $reversal_id, 'reason' => $reason ), 'Payment reversed by compensating entry' ); $wpdb->query( 'COMMIT' ); return $reversal_id;
	}
}
