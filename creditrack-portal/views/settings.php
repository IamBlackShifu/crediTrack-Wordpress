<?php
if ( ! current_user_can( 'creditrack_manage_settings' ) ) { wp_die( esc_html__( 'Access denied.', 'creditrack' ), '', array( 'response' => 403 ) ); }
$field = static fn( string $key, mixed $fallback = '' ): string => (string) ( $settings[ $key ] ?? $fallback );
?><div class="ct-titlebar"><div><h1>Settings</h1><div class="ct-muted">Configure company identity, lending defaults, reminders, and permissions.</div></div></div>
<form class="ct-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'creditrack_action' ); ?><input type="hidden" name="action" value="creditrack_save_settings">
	<section class="ct-card ct-field full"><h2>Company identity</h2><div class="ct-form">
		<div class="ct-field"><label for="company_name">Company name</label><input id="company_name" name="company_name" value="<?php echo esc_attr( $field( 'company_name', 'CrediTrack' ) ); ?>" required></div>
		<div class="ct-field"><label for="company_email">Company email</label><input id="company_email" name="company_email" type="email" value="<?php echo esc_attr( $field( 'company_email' ) ); ?>"></div>
		<div class="ct-field"><label for="company_phone">Company phone</label><input id="company_phone" name="company_phone" value="<?php echo esc_attr( $field( 'company_phone' ) ); ?>"></div>
		<div class="ct-field full"><label for="company_address">Company address</label><textarea id="company_address" name="company_address"><?php echo esc_textarea( $field( 'company_address' ) ); ?></textarea></div>
	</div></section>
	<section class="ct-card ct-field full"><h2>Currency and formatting</h2><div class="ct-form">
		<div class="ct-field"><label for="currency_code">Currency code</label><input id="currency_code" name="currency_code" maxlength="3" value="<?php echo esc_attr( $field( 'currency_code', 'USD' ) ); ?>" required></div>
		<div class="ct-field"><label for="currency_symbol">Currency symbol</label><input id="currency_symbol" name="currency_symbol" value="<?php echo esc_attr( $field( 'currency_symbol', '$' ) ); ?>" required></div>
		<div class="ct-field"><label for="timezone">Timezone</label><select id="timezone" name="timezone"><?php echo wp_timezone_choice( $field( 'timezone', 'UTC' ), get_user_locale() ); ?></select></div>
		<div class="ct-field"><label for="date_format">Date format</label><select id="date_format" name="date_format"><?php foreach ( array( 'Y-m-d','d/m/Y','m/d/Y','j M Y' ) as $format ) { ?><option value="<?php echo esc_attr( $format ); ?>" <?php selected( $field( 'date_format', 'Y-m-d' ), $format ); ?>><?php echo esc_html( wp_date( $format ) ); ?></option><?php } ?></select></div>
	</div></section>
	<section class="ct-card ct-field full"><h2>Lending defaults</h2><p class="ct-muted">Changes apply only to new loans and never recalculate historical loans.</p><div class="ct-form">
		<div class="ct-field"><label for="default_interest_rate">Default interest rate (%)</label><input id="default_interest_rate" name="default_interest_rate" type="number" min="0" max="1000" step="0.0001" value="<?php echo esc_attr( $field( 'default_interest_rate', '15' ) ); ?>" required></div>
		<div class="ct-field"><label for="default_term">Default term (months)</label><input id="default_term" name="default_term" type="number" min="1" max="600" value="<?php echo esc_attr( $field( 'default_term', 12 ) ); ?>" required></div>
		<div class="ct-field"><label for="default_interest_type">Calculation method / rate basis</label><select id="default_interest_type" name="default_interest_type"><?php foreach ( array( 'Amortized'=>'Declining balance — annual nominal rate','Flat'=>'Flat/precomputed — whole-term rate','Monthly'=>'Flat/precomputed — monthly rate','Quarterly'=>'Flat/precomputed — quarterly rate','Annual'=>'Flat/precomputed — annual simple rate' ) as $type => $label ) { ?><option value="<?php echo esc_attr( $type ); ?>" <?php selected( $field( 'default_interest_type', 'Amortized' ), $type ); ?>><?php echo esc_html( $label ); ?></option><?php } ?></select></div>
		<div class="ct-field"><label for="grace_days">Overdue grace period (days)</label><input id="grace_days" name="grace_days" type="number" min="0" max="365" value="<?php echo esc_attr( $field( 'grace_days', 0 ) ); ?>"></div>
	</div></section>
	<section class="ct-card ct-field full"><h2>Notifications and policy</h2><div class="ct-form">
		<div class="ct-field"><label><input type="checkbox" name="notifications_enabled" value="1" <?php checked( ! empty( $settings['notifications_enabled'] ) ); ?>> Enable due reminders</label></div>
		<div class="ct-field"><label for="reminder_days">Reminder window (days)</label><input id="reminder_days" name="reminder_days" type="number" min="1" max="365" value="<?php echo esc_attr( $field( 'reminder_days', 7 ) ); ?>"></div>
		<div class="ct-field"><label><input type="checkbox" name="officer_can_approve" value="1" <?php checked( ! empty( $settings['officer_can_approve'] ) ); ?>> Loan Officers may approve loans</label></div>
		<div class="ct-field"><label><input type="checkbox" name="officer_can_disburse" value="1" <?php checked( ! empty( $settings['officer_can_disburse'] ) ); ?>> Loan Officers may disburse loans</label></div>
		<div class="ct-field"><label for="max_upload_mb">Maximum document size (MB)</label><input id="max_upload_mb" name="max_upload_mb" type="number" min="1" max="50" value="<?php echo esc_attr( $field( 'max_upload_mb', 5 ) ); ?>"></div>
		<div class="ct-field"><label for="backup_retention_days">Backup retention (days)</label><input id="backup_retention_days" name="backup_retention_days" type="number" min="1" max="3650" value="<?php echo esc_attr( $field( 'backup_retention_days', 30 ) ); ?>"></div>
	</div></section>
	<div class="ct-field full"><button type="submit">Save settings</button></div>
</form>
