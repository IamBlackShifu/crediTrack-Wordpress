<?php
namespace CrediTrack\Infrastructure;

final class Admin {
	public static function register(): void {
		add_menu_page( 'CrediTrack', 'CrediTrack', 'manage_options', 'creditrack', array( self::class, 'overview' ), 'dashicons-chart-area', 26 );
		add_submenu_page( 'creditrack', 'CrediTrack Overview', 'Overview', 'manage_options', 'creditrack', array( self::class, 'overview' ) );
		add_submenu_page( 'creditrack', 'CrediTrack Settings', 'Settings', 'manage_options', 'creditrack-settings', array( self::class, 'settings' ) );
	}

	public static function plugin_links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=creditrack-settings' ) ) . '">' . esc_html__( 'Settings', 'creditrack' ) . '</a>' );
		return $links;
	}

	public static function overview(): void {
		global $wpdb;
		$clients = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ct_clients" );
		$loans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ct_loans" );
		$settings = get_option( 'creditrack_settings', array() );
		?>
		<div class="wrap"><h1>CrediTrack</h1><p>Credit operations by Infinity Lines of Code (Pvt) Ltd.</p>
		<div class="notice notice-info inline"><p><strong>Portal status:</strong> <?php echo esc_html( $settings['company_name'] ?? 'CrediTrack' ); ?> &mdash; <?php echo esc_html( number_format_i18n( $clients ) ); ?> clients, <?php echo esc_html( number_format_i18n( $loans ) ); ?> loans.</p></div>
		<p><a class="button button-primary" href="<?php echo esc_url( home_url( '/creditrack/dashboard/' ) ); ?>">Open CrediTrack Portal</a> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=creditrack-settings' ) ); ?>">Configure CrediTrack</a></p>
		<h2>Administration</h2><p>Use the native settings page for company, lending, notification, permission, document, and backup defaults. Operational records remain in the secured portal.</p></div>
		<?php
	}

	public static function settings(): void {
		$settings = get_option( 'creditrack_settings', array() );
		$field = static fn( string $key, mixed $fallback = '' ): string => (string) ( $settings[ $key ] ?? $fallback );
		?>
		<div class="wrap"><h1>CrediTrack Settings</h1><p>These values are shared with the CrediTrack Portal and audited when changed.</p>
		<?php if ( isset( $_GET['ct_success'] ) ) { ?><div class="notice notice-success is-dismissible"><p>CrediTrack settings saved.</p></div><?php } ?>
		<?php if ( isset( $_GET['ct_error'] ) ) { ?><div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['ct_error'] ) ) ); ?></p></div><?php } ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'creditrack_action' ); ?><input type="hidden" name="action" value="creditrack_save_settings"><input type="hidden" name="creditrack_return" value="admin">
		<h2 class="title">Company identity</h2><table class="form-table" role="presentation"><tr><th><label for="ct_company_name">Company name</label></th><td><input class="regular-text" id="ct_company_name" name="company_name" value="<?php echo esc_attr( $field( 'company_name', 'CrediTrack' ) ); ?>" required></td></tr><tr><th><label for="ct_company_email">Company email</label></th><td><input class="regular-text" id="ct_company_email" name="company_email" type="email" value="<?php echo esc_attr( $field( 'company_email' ) ); ?>"></td></tr><tr><th><label for="ct_company_phone">Company phone</label></th><td><input class="regular-text" id="ct_company_phone" name="company_phone" value="<?php echo esc_attr( $field( 'company_phone' ) ); ?>"></td></tr><tr><th><label for="ct_company_address">Company address</label></th><td><textarea class="large-text" rows="3" id="ct_company_address" name="company_address"><?php echo esc_textarea( $field( 'company_address' ) ); ?></textarea></td></tr></table>
		<h2 class="title">Currency and formatting</h2><table class="form-table" role="presentation"><tr><th><label for="ct_currency_code">Currency code</label></th><td><input class="small-text" id="ct_currency_code" name="currency_code" maxlength="3" value="<?php echo esc_attr( $field( 'currency_code', 'USD' ) ); ?>" required></td></tr><tr><th><label for="ct_currency_symbol">Currency symbol</label></th><td><input class="small-text" id="ct_currency_symbol" name="currency_symbol" value="<?php echo esc_attr( $field( 'currency_symbol', '$' ) ); ?>" required></td></tr><tr><th><label for="ct_timezone">Timezone</label></th><td><select id="ct_timezone" name="timezone"><?php echo wp_timezone_choice( $field( 'timezone', 'UTC' ), get_user_locale() ); ?></select></td></tr><tr><th><label for="ct_date_format">Date format</label></th><td><select id="ct_date_format" name="date_format"><?php foreach ( array( 'Y-m-d','d/m/Y','m/d/Y','j M Y' ) as $format ) { ?><option value="<?php echo esc_attr( $format ); ?>" <?php selected( $field( 'date_format', 'Y-m-d' ), $format ); ?>><?php echo esc_html( wp_date( $format ) ); ?></option><?php } ?></select></td></tr></table>
		<h2 class="title">Lending and operations</h2><table class="form-table" role="presentation"><tr><th><label for="ct_default_interest_rate">Default interest rate (%)</label></th><td><input id="ct_default_interest_rate" name="default_interest_rate" type="number" min="0" max="1000" step="0.0001" value="<?php echo esc_attr( $field( 'default_interest_rate', '15' ) ); ?>" required></td></tr><tr><th><label for="ct_default_term">Default term (months)</label></th><td><input id="ct_default_term" name="default_term" type="number" min="1" max="600" value="<?php echo esc_attr( $field( 'default_term', 12 ) ); ?>" required></td></tr><tr><th><label for="ct_default_interest_type">Calculation method</label></th><td><select id="ct_default_interest_type" name="default_interest_type"><?php foreach ( array( 'Amortized','Flat','Monthly','Quarterly','Annual' ) as $type ) { ?><option <?php selected( $field( 'default_interest_type', 'Amortized' ), $type ); ?>><?php echo esc_html( $type ); ?></option><?php } ?></select></td></tr><tr><th><label for="ct_grace_days">Grace period</label></th><td><input id="ct_grace_days" name="grace_days" type="number" min="0" max="365" value="<?php echo esc_attr( $field( 'grace_days', 0 ) ); ?>"> days</td></tr><tr><th>Notifications</th><td><label><input type="checkbox" name="notifications_enabled" value="1" <?php checked( ! empty( $settings['notifications_enabled'] ) ); ?>> Enable repayment reminders</label><br><label>Reminder window <input class="small-text" name="reminder_days" type="number" min="1" max="365" value="<?php echo esc_attr( $field( 'reminder_days', 7 ) ); ?>"> days</label></td></tr><tr><th>Loan Officer policy</th><td><label><input type="checkbox" name="officer_can_approve" value="1" <?php checked( ! empty( $settings['officer_can_approve'] ) ); ?>> May approve loans</label><br><label><input type="checkbox" name="officer_can_disburse" value="1" <?php checked( ! empty( $settings['officer_can_disburse'] ) ); ?>> May disburse loans</label></td></tr><tr><th><label for="ct_max_upload_mb">Document limit</label></th><td><input id="ct_max_upload_mb" name="max_upload_mb" type="number" min="1" max="50" value="<?php echo esc_attr( $field( 'max_upload_mb', 5 ) ); ?>"> MB</td></tr><tr><th><label for="ct_backup_retention_days">Backup retention</label></th><td><input id="ct_backup_retention_days" name="backup_retention_days" type="number" min="1" max="3650" value="<?php echo esc_attr( $field( 'backup_retention_days', 30 ) ); ?>"> days</td></tr></table>
		<?php submit_button( 'Save CrediTrack Settings' ); ?></form></div>
		<?php
	}
}
