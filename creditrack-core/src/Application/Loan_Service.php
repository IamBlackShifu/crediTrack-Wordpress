<?php
namespace CrediTrack\Application;

use CrediTrack\Domain\Loan_Calculator;
use CrediTrack\Infrastructure\Audit;
use WP_Error;

final class Loan_Service {
	public function create( array $input ): int|WP_Error {
		if ( ! current_user_can( 'creditrack_create_loans' ) ) { return new WP_Error( 'forbidden', 'You cannot create loans.' ); }
		global $wpdb; $client_id = absint( $input['client_id'] ?? 0 );
		$client = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ct_clients WHERE id=%d AND is_active=1", $client_id ) );
		if ( ! $client ) { return new WP_Error( 'validation', 'Selected client no longer exists or is inactive.', array( 'client_id' => 'Select an active client.' ) ); }
		$key = sanitize_text_field( $input['idempotency_key'] ?? '' ); if ( strlen( $key ) < 16 ) { return new WP_Error( 'validation', 'The form token is invalid. Reload and try again.' ); }
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ct_loans WHERE idempotency_key=%s", $key ) ); if ( $existing ) { return (int) $existing; }
		try { $calculation = ( new Loan_Calculator() )->calculate( (string) ( $input['principal'] ?? '' ), (string) ( $input['interest_rate'] ?? '' ), sanitize_text_field( $input['interest_type'] ?? '' ), absint( $input['term_months'] ?? 0 ) ); } catch ( \Throwable $e ) { return new WP_Error( 'validation', $e->getMessage() ); }
		$now = gmdate( 'Y-m-d H:i:s' ); $data = array_merge( $calculation, array( 'client_id' => $client_id, 'loan_number' => self::number(), 'interest_rate' => number_format( (float) $input['interest_rate'], 4, '.', '' ), 'interest_type' => ucfirst( strtolower( sanitize_text_field( $input['interest_type'] ) ) ), 'term_months' => absint( $input['term_months'] ), 'application_date' => gmdate( 'Y-m-d' ), 'status' => 'Pending', 'purpose' => sanitize_textarea_field( $input['purpose'] ?? '' ), 'collateral' => sanitize_textarea_field( $input['collateral'] ?? '' ), 'guarantor' => sanitize_text_field( $input['guarantor'] ?? '' ), 'guarantor_phone' => sanitize_text_field( $input['guarantor_phone'] ?? '' ), 'notes' => sanitize_textarea_field( $input['notes'] ?? '' ), 'created_by' => get_current_user_id(), 'total_paid' => '0.00', 'outstanding' => $calculation['total_repayable'], 'idempotency_key' => $key, 'created_at' => $now, 'updated_at' => $now ) );
		if ( ! $wpdb->insert( $wpdb->prefix . 'ct_loans', $data ) ) { return new WP_Error( 'database', 'The loan could not be created.' ); }
		$id = (int) $wpdb->insert_id; Audit::record( 'loan', $id, 'create', array(), $data, 'Loan created' ); return $id;
	}

	public function transition( int $id, string $action, array $input = array() ): true|WP_Error {
		global $wpdb; $table = $wpdb->prefix . 'ct_loans'; $loan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $id ), ARRAY_A );
		if ( ! $loan ) { return new WP_Error( 'not_found', 'Loan not found.' ); }
		$settings = get_option( 'creditrack_settings', array() ); $is_officer_approve = current_user_can( 'creditrack_create_loans' ) && ! empty( $settings['officer_can_approve'] ); $is_officer_disburse = current_user_can( 'creditrack_create_loans' ) && ! empty( $settings['officer_can_disburse'] );
		if ( 'approve' === $action && ! current_user_can( 'creditrack_approve_loans' ) && ! $is_officer_approve ) { return new WP_Error( 'forbidden', 'You cannot approve loans.' ); }
		if ( 'disburse' === $action && ! current_user_can( 'creditrack_disburse_loans' ) && ! $is_officer_disburse ) { return new WP_Error( 'forbidden', 'You cannot disburse loans.' ); }
		if ( in_array( $action, array( 'default', 'write_off' ), true ) && ! current_user_can( 'creditrack_approve_loans' ) ) { return new WP_Error( 'forbidden', 'You cannot change this loan status.' ); }
		$expected = array( 'approve' => array( 'Pending' ), 'disburse' => array( 'Approved' ), 'default' => array( 'Pending','Approved','Active' ), 'write_off' => array( 'Defaulted' ) );
		if ( ! isset( $expected[ $action ] ) || ! in_array( $loan['status'], $expected[ $action ], true ) ) { return new WP_Error( 'transition', 'That status transition is not permitted.' ); }
		$now = gmdate( 'Y-m-d H:i:s' ); $update = array( 'updated_at' => $now );
		if ( 'approve' === $action ) { $update += array( 'status' => 'Approved', 'approved_by' => get_current_user_id(), 'approved_at' => $now ); }
		if ( 'default' === $action || 'write_off' === $action ) { $reason = sanitize_textarea_field( $input['reason'] ?? '' ); if ( '' === $reason ) { return new WP_Error( 'validation', 'A reason is required.' ); } $update += array( 'status' => 'default' === $action ? 'Defaulted' : 'Written Off', 'status_reason' => $reason ); }
		if ( 'disburse' === $action ) {
			$date = sanitize_text_field( $input['disbursement_date'] ?? gmdate( 'Y-m-d' ) ); $calculator = new Loan_Calculator();
			try { $schedule = $calculator->schedule( $loan, $date ); } catch ( \Throwable $e ) { return new WP_Error( 'calculation', $e->getMessage() ); }
			$update += array( 'status' => 'Active', 'disbursement_date' => $date, 'maturity_date' => end( $schedule )['due_date'] );
			$wpdb->query( 'START TRANSACTION' );
			if ( false === $wpdb->update( $table, $update, array( 'id' => $id, 'status' => 'Approved' ) ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'database', 'Loan disbursement failed.' ); }
			foreach ( $schedule as $row ) { $row += array( 'loan_id' => $id, 'paid_amount' => '0.00', 'status' => 'Pending', 'overdue' => 0, 'days_past_due' => 0, 'created_at' => $now, 'updated_at' => $now ); if ( ! $wpdb->insert( $wpdb->prefix . 'ct_schedules', $row ) ) { $wpdb->query( 'ROLLBACK' ); return new WP_Error( 'database', 'Repayment schedule creation failed.' ); } }
			Audit::record( 'loan', $id, 'disburse', $loan, $update, 'Loan disbursed and schedule generated' ); $wpdb->query( 'COMMIT' ); return true;
		}
		$wpdb->update( $table, $update, array( 'id' => $id ) ); Audit::record( 'loan', $id, $action, $loan, $update, 'Loan status changed' ); return true;
	}
	private static function number(): string { return 'CT-' . gmdate( 'Ym' ) . '-' . strtoupper( wp_generate_password( 8, false, false ) ); }
}
