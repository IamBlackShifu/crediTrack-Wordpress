<?php
namespace CrediTrack\Infrastructure;

final class Activator {
	private const SCHEMA_VERSION = '1.2.0';

	public static function activate(): void {
		self::migrate();
		self::register_roles();
		if ( ! wp_next_scheduled( 'creditrack_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'creditrack_daily' );
		}
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'creditrack_daily' );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( self::SCHEMA_VERSION !== get_option( 'creditrack_schema_version' ) ) {
			self::migrate();
		}
	}

	public static function register_roles(): void {
		$read = array( 'read' => true, 'creditrack_access' => true, 'creditrack_read' => true );
		$officer = array_merge( $read, array_fill_keys( array( 'creditrack_manage_clients', 'creditrack_manage_documents', 'creditrack_create_loans', 'creditrack_record_payments', 'creditrack_view_reports' ), true ) );
		$admin = array_merge( $officer, array_fill_keys( array( 'creditrack_approve_loans', 'creditrack_disburse_loans', 'creditrack_reverse_payments', 'creditrack_manage_users', 'creditrack_manage_settings', 'creditrack_view_audit', 'creditrack_manage_backups' ), true ) );
		add_role( 'creditrack_administrator', __( 'CrediTrack Administrator', 'creditrack' ), $admin );
		add_role( 'creditrack_loan_officer', __( 'Loan Officer', 'creditrack' ), $officer );
		add_role( 'creditrack_viewer', __( 'Viewer', 'creditrack' ), $read );
		foreach ( array( 'administrator' => $admin, 'creditrack_administrator' => $admin, 'creditrack_loan_officer' => $officer, 'creditrack_viewer' => $read ) as $role_name => $caps ) {
			$role = get_role( $role_name );
			if ( $role ) {
				foreach ( $caps as $cap => $grant ) {
					$role->add_cap( $cap, $grant );
				}
			}
		}
	}

	private static function migrate(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$p = $wpdb->prefix . 'ct_';
		$sql = array();
		$sql[] = "CREATE TABLE {$p}clients (id bigint unsigned NOT NULL AUTO_INCREMENT, first_name varchar(100) NOT NULL, last_name varchar(100) NOT NULL, email varchar(190) NULL, phone varchar(50) NOT NULL, address text NOT NULL, date_of_birth date NULL, national_id varchar(100) NULL, occupation varchar(190) NOT NULL DEFAULT '', monthly_income decimal(18,2) NOT NULL DEFAULT 0, next_of_kin_name varchar(190) NOT NULL DEFAULT '', next_of_kin_phone varchar(50) NOT NULL DEFAULT '', next_of_kin_address text NOT NULL, is_active tinyint(1) NOT NULL DEFAULT 1, notes text NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY national_id (national_id), KEY name (last_name,first_name), KEY phone (phone), KEY active (is_active)) $c;";
		$sql[] = "CREATE TABLE {$p}client_documents (id bigint unsigned NOT NULL AUTO_INCREMENT, client_id bigint unsigned NOT NULL, stored_name varchar(190) NOT NULL, original_name varchar(255) NOT NULL, protected_path varchar(500) NOT NULL, file_size bigint unsigned NOT NULL, mime_type varchar(100) NOT NULL, document_type varchar(40) NOT NULL, uploaded_by bigint unsigned NOT NULL, uploaded_at datetime NOT NULL, PRIMARY KEY (id), KEY client (client_id)) $c;";
		$sql[] = "CREATE TABLE {$p}loans (id bigint unsigned NOT NULL AUTO_INCREMENT, client_id bigint unsigned NOT NULL, loan_number varchar(40) NOT NULL, principal decimal(18,2) NOT NULL, interest_rate decimal(9,4) NOT NULL, interest_type varchar(20) NOT NULL, term_months smallint unsigned NOT NULL, application_date date NOT NULL, approved_by bigint unsigned NULL, approved_at datetime NULL, disbursement_date date NULL, maturity_date date NULL, status varchar(20) NOT NULL, purpose text NOT NULL, collateral text NOT NULL, guarantor varchar(190) NOT NULL, guarantor_phone varchar(50) NOT NULL, notes text NOT NULL, created_by bigint unsigned NOT NULL, total_interest decimal(18,2) NOT NULL, total_repayable decimal(18,2) NOT NULL, installment_amount decimal(18,2) NOT NULL, total_paid decimal(18,2) NOT NULL DEFAULT 0, outstanding decimal(18,2) NOT NULL, status_reason text NULL, idempotency_key varchar(64) NOT NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY loan_number (loan_number), UNIQUE KEY idempotency (idempotency_key), KEY client (client_id), KEY status (status), KEY officer (created_by), KEY disbursement (disbursement_date)) $c;";
		$sql[] = "CREATE TABLE {$p}schedules (id bigint unsigned NOT NULL AUTO_INCREMENT, loan_id bigint unsigned NOT NULL, installment_number smallint unsigned NOT NULL, due_date date NOT NULL, principal_portion decimal(18,2) NOT NULL, interest_portion decimal(18,2) NOT NULL, total_installment decimal(18,2) NOT NULL, paid_amount decimal(18,2) NOT NULL DEFAULT 0, remaining_amount decimal(18,2) NOT NULL, status varchar(20) NOT NULL DEFAULT 'Pending', paid_at datetime NULL, overdue tinyint(1) NOT NULL DEFAULT 0, days_past_due int unsigned NOT NULL DEFAULT 0, created_at datetime NOT NULL, updated_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY installment (loan_id,installment_number), KEY due (due_date,status), KEY loan_status (loan_id,status)) $c;";
		$sql[] = "CREATE TABLE {$p}payments (id bigint unsigned NOT NULL AUTO_INCREMENT, loan_id bigint unsigned NOT NULL, amount decimal(18,2) NOT NULL, payment_date date NOT NULL, method varchar(30) NOT NULL, reference_number varchar(190) NULL, notes text NOT NULL, is_advance tinyint(1) NOT NULL DEFAULT 0, recorded_by bigint unsigned NOT NULL, idempotency_key varchar(64) NOT NULL, is_reversal tinyint(1) NOT NULL DEFAULT 0, reverses_payment_id bigint unsigned NULL, reversed_at datetime NULL, reversed_by bigint unsigned NULL, reversal_reason text NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY idempotency (idempotency_key), KEY loan_date (loan_id,payment_date), KEY payment_date (payment_date), KEY method (method)) $c;";
		$sql[] = "CREATE TABLE {$p}payment_allocations (id bigint unsigned NOT NULL AUTO_INCREMENT, payment_id bigint unsigned NOT NULL, schedule_id bigint unsigned NOT NULL, amount decimal(18,2) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY payment_schedule (payment_id,schedule_id), KEY schedule (schedule_id)) $c;";
		$sql[] = "CREATE TABLE {$p}audit_logs (id bigint unsigned NOT NULL AUTO_INCREMENT, actor_user_id bigint unsigned NULL, entity_type varchar(50) NOT NULL, entity_id bigint unsigned NULL, action varchar(80) NOT NULL, old_values longtext NULL, new_values longtext NULL, description varchar(500) NOT NULL, ip_address varchar(45) NOT NULL, user_agent varchar(500) NOT NULL, created_at datetime NOT NULL, PRIMARY KEY (id), KEY actor (actor_user_id), KEY entity (entity_type,entity_id), KEY created (created_at)) $c;";
		$sql[] = "CREATE TABLE {$p}jobs (id bigint unsigned NOT NULL AUTO_INCREMENT, job_key varchar(64) NOT NULL, type varchar(30) NOT NULL, status varchar(20) NOT NULL, protected_path varchar(500) NULL, file_size bigint unsigned NULL, created_by bigint unsigned NOT NULL, error_message varchar(500) NULL, created_at datetime NOT NULL, updated_at datetime NOT NULL, completed_at datetime NULL, PRIMARY KEY (id), UNIQUE KEY job_key (job_key), KEY type_status (type,status)) $c;";
		$sql[] = "CREATE TABLE {$p}notifications (id bigint unsigned NOT NULL AUTO_INCREMENT, user_id bigint unsigned NOT NULL, notification_key varchar(100) NOT NULL, title varchar(190) NOT NULL, message text NOT NULL, entity_type varchar(30) NULL, entity_id bigint unsigned NULL, read_at datetime NULL, dismissed_at datetime NULL, created_at datetime NOT NULL, PRIMARY KEY (id), UNIQUE KEY user_notice (user_id,notification_key), KEY unread (user_id,read_at,dismissed_at)) $c;";
		foreach ( $sql as $statement ) {
			if ( preg_match( '/^CREATE TABLE\s+([^\s(]+)/i', $statement, $matches ) ) {
				$table_name = $matches[1];
				$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );
				if ( $exists !== $table_name ) { dbDelta( str_replace( ', ', ",\n", $statement ) ); }
			}
		}
		self::ensure_payment_reversal_columns( $p . 'payments' );
		if ( false === get_option( 'creditrack_settings', false ) ) {
			add_option( 'creditrack_settings', array( 'company_name' => 'CrediTrack', 'company_product_credit' => 'A product of Infinity Lines of Code (Pvt) Ltd', 'currency_code' => 'USD', 'currency_symbol' => '$', 'currency_precision' => 2, 'timezone' => wp_timezone_string(), 'date_format' => 'Y-m-d', 'default_interest_rate' => '15.0000', 'default_term' => 12, 'default_interest_type' => 'Amortized', 'grace_days' => 0, 'notifications_enabled' => true, 'reminder_days' => 7, 'officer_can_approve' => false, 'officer_can_disburse' => false, 'max_upload_mb' => 5, 'backup_retention_days' => 30 ) );
		}
		update_option( 'creditrack_schema_version', self::SCHEMA_VERSION );
	}

	private static function ensure_payment_reversal_columns( string $table ): void {
		global $wpdb;
		$columns = array(
			'reversed_at' => 'datetime NULL',
			'reversed_by' => 'bigint unsigned NULL',
			'reversal_reason' => 'text NULL',
		);
		foreach ( $columns as $name => $definition ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM $table LIKE %s", $name ) );
			if ( ! $exists ) { $wpdb->query( "ALTER TABLE $table ADD COLUMN $name $definition" ); }
		}
	}
}
