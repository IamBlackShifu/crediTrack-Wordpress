<?php
namespace CrediTrack\Infrastructure;

final class Audit {
	public static function record( string $entity_type, ?int $entity_id, string $action, array $old, array $new, string $description ): void {
		global $wpdb;
		$redact = static function ( array $values ): array {
			foreach ( array( 'password', 'pass', 'token', 'secret', 'document_contents' ) as $key ) { if ( array_key_exists( $key, $values ) ) { $values[ $key ] = '[REDACTED]'; } }
			return $values;
		};
		$wpdb->insert( $wpdb->prefix . 'ct_audit_logs', array( 'actor_user_id' => get_current_user_id() ?: null, 'entity_type' => sanitize_key( $entity_type ), 'entity_id' => $entity_id, 'action' => sanitize_key( $action ), 'old_values' => wp_json_encode( $redact( $old ) ), 'new_values' => wp_json_encode( $redact( $new ) ), 'description' => sanitize_text_field( $description ), 'ip_address' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ), 'user_agent' => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 500 ), 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
	}
}
