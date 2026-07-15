<?php
namespace CrediTrack\Application;

final class Notification_Service {
	public static function refresh(): void {
		$settings = get_option( 'creditrack_settings', array() ); if ( empty( $settings['notifications_enabled'] ) ) { return; }
		global $wpdb; $days = min( 365, max( 1, (int) ( $settings['reminder_days'] ?? 7 ) ) );
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT s.id,s.due_date,s.remaining_amount,s.days_past_due,l.id loan_id,l.loan_number,c.first_name,c.last_name FROM {$wpdb->prefix}ct_schedules s JOIN {$wpdb->prefix}ct_loans l ON l.id=s.loan_id JOIN {$wpdb->prefix}ct_clients c ON c.id=l.client_id WHERE s.remaining_amount>0 AND s.due_date<=DATE_ADD(UTC_DATE(),INTERVAL %d DAY) ORDER BY s.due_date LIMIT 500", $days ) );
		$users = get_users( array( 'fields' => 'ID', 'capability' => 'creditrack_read' ) ); $now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $users as $user_id ) { foreach ( $items as $item ) { $key = 'schedule-' . $item->id . '-' . $item->due_date; $title = $item->days_past_due > 0 ? 'Repayment overdue' : 'Repayment due soon'; $message = sprintf( '%s · %s %s · %s due %s', $item->loan_number, $item->first_name, $item->last_name, $item->remaining_amount, $item->due_date ); $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->prefix}ct_notifications (user_id,notification_key,title,message,entity_type,entity_id,created_at) VALUES (%d,%s,%s,%s,'loan',%d,%s)", $user_id, $key, $title, $message, $item->loan_id, $now ) ); } }
	}
	public static function update( int $id, string $action ): bool {
		global $wpdb; if ( ! in_array( $action, array( 'read','dismiss' ), true ) ) { return false; } $column = 'read' === $action ? 'read_at' : 'dismissed_at'; return false !== $wpdb->update( $wpdb->prefix . 'ct_notifications', array( $column => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => $id, 'user_id' => get_current_user_id() ) );
	}
}
