<?php
namespace CrediTrack\Infrastructure;

final class Overdue_Job {
	public static function maybe_run(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'creditrack_read' ) || get_transient( 'creditrack_overdue_refresh_lock' ) ) { return; }
		set_transient( 'creditrack_overdue_refresh_lock', 1, 5 * MINUTE_IN_SECONDS ); self::run();
	}
	public static function run(): void {
		global $wpdb;
		$settings = get_option( 'creditrack_settings', array() );
		$grace = max( 0, (int) ( $settings['grace_days'] ?? 0 ) );
		$table = $wpdb->prefix . 'ct_schedules';
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET overdue = 1, status = IF(status='Pending','Overdue',status), days_past_due = GREATEST(DATEDIFF(UTC_DATE(), due_date),0), updated_at=UTC_TIMESTAMP() WHERE remaining_amount > 0 AND DATE_ADD(due_date, INTERVAL %d DAY) < UTC_DATE()", $grace ) );
		$wpdb->query( $wpdb->prepare( "UPDATE $table SET overdue = 0, days_past_due = 0, status = IF(status='Overdue','Pending',status), updated_at=UTC_TIMESTAMP() WHERE remaining_amount > 0 AND DATE_ADD(due_date, INTERVAL %d DAY) >= UTC_DATE()", $grace ) );
		\CrediTrack\Application\Notification_Service::refresh();
	}
}
