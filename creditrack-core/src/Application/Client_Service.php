<?php
namespace CrediTrack\Application;

use CrediTrack\Infrastructure\Audit;
use WP_Error;

final class Client_Service {
	public function save( array $input, ?int $id = null ): int|WP_Error {
		if ( ! current_user_can( 'creditrack_manage_clients' ) ) { return new WP_Error( 'forbidden', 'You cannot modify clients.' ); }
		$data = array( 'first_name' => sanitize_text_field( $input['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $input['last_name'] ?? '' ), 'email' => sanitize_email( $input['email'] ?? '' ) ?: null, 'phone' => sanitize_text_field( $input['phone'] ?? '' ), 'address' => sanitize_textarea_field( $input['address'] ?? '' ), 'date_of_birth' => self::date_or_null( $input['date_of_birth'] ?? '' ), 'national_id' => sanitize_text_field( $input['national_id'] ?? '' ) ?: null, 'occupation' => sanitize_text_field( $input['occupation'] ?? '' ), 'monthly_income' => self::money( $input['monthly_income'] ?? '0' ), 'next_of_kin_name' => sanitize_text_field( $input['next_of_kin_name'] ?? '' ), 'next_of_kin_phone' => sanitize_text_field( $input['next_of_kin_phone'] ?? '' ), 'next_of_kin_address' => sanitize_textarea_field( $input['next_of_kin_address'] ?? '' ), 'notes' => sanitize_textarea_field( $input['notes'] ?? '' ), 'updated_at' => gmdate( 'Y-m-d H:i:s' ) );
		$errors = array();
		foreach ( array( 'first_name' => 'First name', 'last_name' => 'Last name', 'phone' => 'Phone', 'address' => 'Address' ) as $key => $label ) { if ( '' === $data[ $key ] ) { $errors[ $key ] = "$label is required."; } }
		if ( ! empty( $input['email'] ) && ! is_email( $input['email'] ) ) { $errors['email'] = 'Enter a valid email address.'; }
		if ( $errors ) { return new WP_Error( 'validation', 'Please correct the highlighted fields.', $errors ); }
		global $wpdb; $table = $wpdb->prefix . 'ct_clients';
		if ( $data['national_id'] ) {
			$duplicate = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE national_id=%s AND id<>%d", $data['national_id'], $id ?: 0 ) );
			if ( $duplicate ) { return new WP_Error( 'validation', 'National ID is already in use.', array( 'national_id' => 'National ID is already in use.' ) ); }
		}
		$old = $id ? (array) $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $id ), ARRAY_A ) : array();
		if ( $id ) { $ok = $wpdb->update( $table, $data, array( 'id' => $id ) ); } else { $data['is_active'] = 1; $data['created_at'] = $data['updated_at']; $ok = $wpdb->insert( $table, $data ); $id = (int) $wpdb->insert_id; }
		if ( false === $ok ) { return new WP_Error( 'database', 'The client could not be saved.' ); }
		Audit::record( 'client', $id, $old ? 'update' : 'create', $old, $data, $old ? 'Client updated' : 'Client created' );
		return $id;
	}

	public function deactivate( int $id ): true|WP_Error {
		if ( ! current_user_can( 'creditrack_manage_clients' ) ) { return new WP_Error( 'forbidden', 'You cannot deactivate clients.' ); }
		global $wpdb; $loans = $wpdb->get_results( $wpdb->prepare( "SELECT loan_number,status FROM {$wpdb->prefix}ct_loans WHERE client_id=%d AND status IN ('Pending','Approved','Active','Defaulted')", $id ), ARRAY_A );
		if ( $loans ) { return new WP_Error( 'open_loans', 'Resolve these open loans first: ' . implode( ', ', array_map( static fn( $l ) => $l['loan_number'] . ' (' . $l['status'] . ')', $loans ) ) ); }
		$wpdb->update( $wpdb->prefix . 'ct_clients', array( 'is_active' => 0, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id ) );
		Audit::record( 'client', $id, 'deactivate', array( 'is_active' => 1 ), array( 'is_active' => 0 ), 'Client deactivated' ); return true;
	}
	private static function date_or_null( string $value ): ?string { $date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ); return $date && $date->format( 'Y-m-d' ) === $value ? $value : null; }
	private static function money( string $value ): string { try { return \CrediTrack\Domain\Money::decimal( \CrediTrack\Domain\Money::minor( $value ) ); } catch ( \Throwable ) { return '0.00'; } }
}
