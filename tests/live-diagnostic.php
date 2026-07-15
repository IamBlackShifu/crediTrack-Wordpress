<?php
/** Read-only health diagnostic for a local CrediTrack WordPress installation. */
$root = $argv[1] ?? '';
if ( ! $root || ! is_readable( rtrim( $root, '/\\' ) . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tests/live-diagnostic.php /path/to/wordpress\n" ); exit( 1 );
}
require rtrim( $root, '/\\' ) . '/wp-load.php';
global $wpdb;
foreach ( get_users( array( 'role__in' => array( 'creditrack_administrator','creditrack_loan_officer','creditrack_viewer' ) ) ) as $user ) {
	printf( "USER %d %s force=%d active=%s\n", $user->ID, implode( ',', $user->roles ), get_user_meta( $user->ID, 'creditrack_force_password_change', true ) ? 1 : 0, get_user_meta( $user->ID, 'creditrack_active', true ) );
}
$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->prefix}ct_payments", 0 );
echo 'PAYMENT_COLUMNS ' . implode( ',', $columns ) . PHP_EOL;
$stats = $wpdb->get_row( "SELECT COUNT(*) total,SUM(due_date<UTC_DATE() AND remaining_amount>0) past_due,SUM(overdue=1) overdue,MAX(days_past_due) max_days FROM {$wpdb->prefix}ct_schedules", ARRAY_A );
echo 'SCHEDULES ' . wp_json_encode( $stats ) . PHP_EOL;
echo 'NOTIFICATIONS ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ct_notifications" ) . PHP_EOL;
echo 'SCHEMA ' . get_option( 'creditrack_schema_version' ) . PHP_EOL;
