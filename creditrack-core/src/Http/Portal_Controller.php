<?php
namespace CrediTrack\Http;

use CrediTrack\Application\Client_Service;
use CrediTrack\Application\Loan_Service;
use CrediTrack\Application\Payment_Service;

final class Portal_Controller {
	public static function register_actions(): void {
		foreach ( array( 'save_client','deactivate_client','create_loan','loan_action','post_payment','reverse_payment','upload_document','delete_document','download_document','notification_action','export_report','create_backup','download_backup','restore_backup','save_user','send_password_reset','save_profile','save_settings','lock_session' ) as $action ) { add_action( 'admin_post_creditrack_' . $action, array( self::class, $action ) ); }
	}
	public static function save_client(): void { self::guard( 'creditrack_manage_clients' ); $result = ( new Client_Service() )->save( wp_unslash( $_POST ), absint( $_POST['client_id'] ?? 0 ) ?: null ); self::finish( $result, 'clients' ); }
	public static function deactivate_client(): void { self::guard( 'creditrack_manage_clients' ); $result = ( new Client_Service() )->deactivate( absint( $_POST['client_id'] ?? 0 ) ); self::finish( $result, 'clients' ); }
	public static function create_loan(): void { self::guard( 'creditrack_create_loans' ); $result = ( new Loan_Service() )->create( wp_unslash( $_POST ) ); self::finish( $result, 'loans' ); }
	public static function loan_action(): void { self::guard( 'creditrack_access' ); $result = ( new Loan_Service() )->transition( absint( $_POST['loan_id'] ?? 0 ), sanitize_key( $_POST['loan_action'] ?? '' ), wp_unslash( $_POST ) ); self::finish( $result, 'loans' ); }
	public static function post_payment(): void { self::guard( 'creditrack_record_payments' ); $result = ( new Payment_Service() )->post( wp_unslash( $_POST ) ); self::finish( $result, 'payments' ); }
	public static function reverse_payment(): void { self::guard( 'creditrack_reverse_payments' ); $result = ( new Payment_Service() )->reverse( absint( $_POST['payment_id'] ?? 0 ), sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ) ); self::finish( $result, 'payments' ); }
	public static function upload_document(): void { self::guard( 'creditrack_manage_documents' ); $result = ( new \CrediTrack\Application\Document_Service() )->upload( absint( $_POST['client_id'] ?? 0 ), $_FILES['document'] ?? array(), sanitize_text_field( wp_unslash( $_POST['document_type'] ?? '' ) ) ); self::finish( $result, 'clients?client_id=' . absint( $_POST['client_id'] ?? 0 ) ); }
	public static function delete_document(): void { self::guard( 'creditrack_manage_documents' ); $client_id = absint( $_POST['client_id'] ?? 0 ); $result = ( new \CrediTrack\Application\Document_Service() )->delete( absint( $_POST['document_id'] ?? 0 ) ); self::finish( $result, 'clients?client_id=' . $client_id ); }
	public static function download_document(): void { if ( ! is_user_logged_in() || ! current_user_can( 'creditrack_read' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); } check_admin_referer( 'creditrack_download_document' ); ( new \CrediTrack\Application\Document_Service() )->stream( absint( $_GET['document_id'] ?? 0 ) ); }
	public static function notification_action(): void { self::guard( 'creditrack_read' ); $result = \CrediTrack\Application\Notification_Service::update( absint( $_POST['notification_id'] ?? 0 ), sanitize_key( $_POST['notification_action'] ?? '' ) ); self::finish( $result ? true : new \WP_Error( 'notification', 'Notification could not be updated.' ), 'notifications' ); }
	public static function export_report(): void { self::guard( 'creditrack_view_reports' ); \CrediTrack\Infrastructure\Audit::record( 'report', null, 'export', array(), array( 'type' => sanitize_key( $_POST['report_type'] ?? '' ) ), 'Report exported to CSV' ); ( new \CrediTrack\Application\Report_Service() )->stream_csv( sanitize_key( $_POST['report_type'] ?? '' ), wp_unslash( $_POST ) ); }
	public static function create_backup(): void { self::guard( 'creditrack_manage_backups' ); self::finish( ( new \CrediTrack\Application\Backup_Service() )->create(), 'backups' ); }
	public static function download_backup(): void { if ( ! is_user_logged_in() || ! current_user_can( 'creditrack_manage_backups' ) ) { wp_die( 'Access denied.', '', array( 'response' => 403 ) ); } check_admin_referer( 'creditrack_download_backup' ); ( new \CrediTrack\Application\Backup_Service() )->stream( absint( $_GET['backup_id'] ?? 0 ) ); }
	public static function restore_backup(): void { self::guard( 'creditrack_manage_backups' ); $result = ( new \CrediTrack\Application\Backup_Service() )->restore( $_FILES['backup'] ?? array(), (string) ( $_POST['current_password'] ?? '' ), sanitize_text_field( wp_unslash( $_POST['confirmation'] ?? '' ) ) ); self::finish( $result, 'backups' ); }
	public static function save_user(): void {
		self::guard( 'creditrack_manage_users' ); $input = wp_unslash( $_POST ); $id = absint( $input['user_id'] ?? 0 ); $role = sanitize_key( $input['role'] ?? '' ); $roles = array( 'creditrack_administrator','creditrack_loan_officer','creditrack_viewer' );
		if ( ! in_array( $role, $roles, true ) ) { self::finish( new \WP_Error( 'role', 'Select a valid CrediTrack role.' ), 'users' ); }
		$email = sanitize_email( $input['email'] ?? '' ); if ( ! is_email( $email ) ) { self::finish( new \WP_Error( 'email', 'Enter a valid email address.' ), 'users' ); }
		$active = ! empty( $input['is_active'] ); $old = $id ? get_userdata( $id ) : null;
		if ( $old && ! array_intersect( $roles, $old->roles ) ) { self::finish( new \WP_Error( 'scope', 'This WordPress account is not managed by CrediTrack.' ), 'users' ); }
		if ( $old && in_array( 'creditrack_administrator', $old->roles, true ) && ( ! $active || 'creditrack_administrator' !== $role ) && self::active_admin_count() <= 1 ) { self::finish( new \WP_Error( 'final_admin', 'The final active CrediTrack Administrator cannot be deactivated or demoted.' ), 'users' ); }
		if ( $id === get_current_user_id() && ! $active ) { self::finish( new \WP_Error( 'self_lockout', 'You cannot deactivate your own signed-in account.' ), 'users' ); }
		$data = array( 'first_name' => sanitize_text_field( $input['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $input['last_name'] ?? '' ), 'user_email' => $email, 'role' => $role );
		if ( $id ) { $data['ID'] = $id; $result = wp_update_user( $data ); } else { $password = wp_generate_password( 24, true, true ); $data += array( 'user_login' => $email, 'user_pass' => $password, 'display_name' => trim( $data['first_name'] . ' ' . $data['last_name'] ) ?: $email ); $result = wp_insert_user( $data ); }
		if ( is_wp_error( $result ) ) { self::finish( $result, 'users' ); } $id = (int) $result; update_user_meta( $id, 'creditrack_active', $active ? '1' : '0' );
		if ( ! $old ) { update_user_meta( $id, 'creditrack_force_password_change', '1' ); } if ( ! $active ) { \WP_Session_Tokens::get_instance( $id )->destroy_all(); }
		\CrediTrack\Infrastructure\Audit::record( 'user', $id, $old ? 'update' : 'create', $old ? array( 'email' => $old->user_email, 'roles' => $old->roles ) : array(), array( 'email' => $email, 'role' => $role, 'active' => $active ), $old ? 'User updated' : 'User created' ); self::finish( true, 'users' );
	}
	public static function send_password_reset(): void {
		self::guard( 'creditrack_manage_users' ); $id = absint( $_POST['user_id'] ?? 0 ); $user = get_userdata( $id ); if ( ! $user ) { self::finish( new \WP_Error( 'user', 'User not found.' ), 'users' ); }
		$key = get_password_reset_key( $user ); if ( is_wp_error( $key ) ) { self::finish( $key, 'users' ); } $url = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ); $sent = wp_mail( $user->user_email, 'CrediTrack password reset', "A CrediTrack administrator requested a password reset for your account.\n\n$url" );
		\CrediTrack\Infrastructure\Audit::record( 'user', $id, 'password_reset_requested', array(), array(), 'Password reset link requested' ); self::finish( $sent ? true : new \WP_Error( 'email', 'The reset email could not be sent. Check WordPress mail configuration.' ), 'users' );
	}
	private static function active_admin_count(): int { $count = 0; foreach ( get_users( array( 'role' => 'creditrack_administrator', 'fields' => 'ID' ) ) as $id ) { if ( '0' !== (string) get_user_meta( $id, 'creditrack_active', true ) ) { ++$count; } } return $count; }
	public static function save_profile(): void {
		self::guard( 'creditrack_access' ); $user = wp_get_current_user(); $data = array( 'ID' => $user->ID, 'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ), 'last_name' => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ), 'user_email' => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		if ( ! is_email( $data['user_email'] ) ) { self::finish( new \WP_Error( 'email', 'Enter a valid email address.' ), 'profile' ); }
		$new_password = (string) wp_unslash( $_POST['new_password'] ?? '' ); $forced = (bool) get_user_meta( $user->ID, 'creditrack_force_password_change', true );
		if ( $forced && '' === $new_password ) { self::finish( new \WP_Error( 'password', 'You must choose a new password before continuing.' ), 'profile?password_change=required' ); }
		if ( '' !== $new_password ) {
			if ( ! wp_check_password( (string) wp_unslash( $_POST['current_password'] ?? '' ), $user->user_pass, $user->ID ) ) { self::finish( new \WP_Error( 'password', 'Current password is incorrect.' ), 'profile?password_change=required' ); }
			if ( strlen( $new_password ) < 12 ) { self::finish( new \WP_Error( 'password', 'New passwords must contain at least 12 characters.' ), 'profile?password_change=required' ); }
			if ( ! hash_equals( $new_password, (string) wp_unslash( $_POST['confirm_password'] ?? '' ) ) ) { self::finish( new \WP_Error( 'password', 'New password and confirmation do not match.' ), 'profile?password_change=required' ); }
			if ( wp_check_password( $new_password, $user->user_pass, $user->ID ) ) { self::finish( new \WP_Error( 'password', 'Choose a password different from your current password.' ), 'profile?password_change=required' ); }
		}
		if ( '' !== $new_password ) {
			$data['user_pass'] = $new_password;
		}
		$result = wp_update_user( $data ); if ( is_wp_error( $result ) ) { self::finish( $result, $forced ? 'profile?password_change=required' : 'profile' ); }
		if ( '' !== $new_password ) {
			$updated_user = get_userdata( $user->ID );
			if ( ! $updated_user || ! wp_check_password( $new_password, $updated_user->user_pass, $user->ID ) ) {
				self::finish( new \WP_Error( 'password_update', 'WordPress could not verify the new password. Please try again.' ), 'profile?password_change=required' );
			}
			delete_user_meta( $user->ID, 'creditrack_force_password_change' );
			wp_clear_auth_cookie(); wp_set_current_user( $user->ID ); wp_set_auth_cookie( $user->ID, false, is_ssl() );
			\CrediTrack\Infrastructure\Audit::record( 'user', $user->ID, 'password_change', array(), array(), 'User changed their own password' );
		}
		self::finish( $result, $forced && '' !== $new_password ? 'dashboard?password_changed=1' : 'profile' );
	}
	public static function lock_session(): void {
		self::guard( 'creditrack_access' ); $user_id = get_current_user_id(); \CrediTrack\Infrastructure\Audit::record( 'user', $user_id, 'session_lock', array(), array(), 'User locked their session' ); wp_destroy_current_session(); wp_clear_auth_cookie(); wp_safe_redirect( wp_login_url( home_url( '/creditrack/dashboard/' ), true ) ); exit;
	}
	public static function save_settings(): void {
		self::guard( 'creditrack_manage_settings' );
		$input = wp_unslash( $_POST );
		$required = array( 'company_name', 'currency_code', 'currency_symbol', 'date_format' );
		foreach ( $required as $field ) {
			if ( '' === trim( (string) ( $input[ $field ] ?? '' ) ) ) {
				self::finish( new \WP_Error( 'validation', ucfirst( str_replace( '_', ' ', $field ) ) . ' is required.' ), 'settings' );
			}
		}
		$interest_type = sanitize_text_field( $input['default_interest_type'] ?? 'Flat' );
		if ( ! in_array( $interest_type, array( 'Flat', 'Monthly', 'Quarterly', 'Annual', 'Amortized' ), true ) ) {
			self::finish( new \WP_Error( 'validation', 'Select a valid default interest type.' ), 'settings' );
		}
		$rate = (string) ( $input['default_interest_rate'] ?? '' );
		if ( ! is_numeric( $rate ) || (float) $rate < 0 || (float) $rate > 1000 ) {
			self::finish( new \WP_Error( 'validation', 'Default interest rate must be between 0 and 1000.' ), 'settings' );
		}
		$timezone = sanitize_text_field( $input['timezone'] ?? 'UTC' );
		if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
			self::finish( new \WP_Error( 'validation', 'Select a valid timezone.' ), 'settings' );
		}
		$old = get_option( 'creditrack_settings', array() );
		$new = array(
			'company_name' => sanitize_text_field( $input['company_name'] ),
			'company_address' => sanitize_textarea_field( $input['company_address'] ?? '' ),
			'company_phone' => sanitize_text_field( $input['company_phone'] ?? '' ),
			'company_email' => sanitize_email( $input['company_email'] ?? '' ),
			'currency_code' => strtoupper( substr( sanitize_text_field( $input['currency_code'] ), 0, 3 ) ),
			'currency_symbol' => sanitize_text_field( $input['currency_symbol'] ),
			'currency_precision' => 2,
			'timezone' => $timezone,
			'date_format' => sanitize_text_field( $input['date_format'] ),
			'default_interest_rate' => number_format( (float) $rate, 4, '.', '' ),
			'default_term' => min( 600, max( 1, absint( $input['default_term'] ?? 12 ) ) ),
			'default_interest_type' => $interest_type,
			'grace_days' => min( 365, absint( $input['grace_days'] ?? 0 ) ),
			'notifications_enabled' => ! empty( $input['notifications_enabled'] ),
			'reminder_days' => min( 365, max( 1, absint( $input['reminder_days'] ?? 7 ) ) ),
			'officer_can_approve' => ! empty( $input['officer_can_approve'] ),
			'officer_can_disburse' => ! empty( $input['officer_can_disburse'] ),
			'max_upload_mb' => min( 50, max( 1, absint( $input['max_upload_mb'] ?? 5 ) ) ),
			'backup_retention_days' => min( 3650, max( 1, absint( $input['backup_retention_days'] ?? 30 ) ) ),
			'updated_by' => get_current_user_id(),
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( 'creditrack_settings', $new, false );
		\CrediTrack\Infrastructure\Audit::record( 'settings', null, 'update', $old, $new, 'CrediTrack settings updated' );
		self::finish( true, 'settings' );
	}
	private static function guard( string $capability ): void { if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) { wp_die( esc_html__( 'Access denied.', 'creditrack' ), '', array( 'response' => 403 ) ); } check_admin_referer( 'creditrack_action' ); }
	private static function finish( mixed $result, string $page ): never { $args = is_wp_error( $result ) ? array( 'ct_error' => $result->get_error_message() ) : array( 'ct_success' => 1 ); $parts = explode( '?', $page, 2 ); $url = home_url( '/creditrack/' . $parts[0] . '/' ); if ( isset( $parts[1] ) ) { parse_str( $parts[1], $query ); $url = add_query_arg( $query, $url ); } wp_safe_redirect( add_query_arg( $args, $url ) ); exit; }
}
