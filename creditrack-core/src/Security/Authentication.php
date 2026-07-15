<?php
namespace CrediTrack\Security;

use CrediTrack\Infrastructure\Audit;
use WP_Error;
use WP_User;

final class Authentication {
	public static function throttle( $user, string $username, string $password ) { $state = get_transient( self::key( $username ) ); return is_array( $state ) && (int) $state['count'] >= 5 ? new WP_Error( 'invalid_login', __( 'The supplied credentials could not be accepted. Try again later.', 'creditrack' ) ) : $user; }
	public static function record_failure( string $username ): void { $key = self::key( $username ); $state = get_transient( $key ); set_transient( $key, array( 'count' => is_array( $state ) ? (int) $state['count'] + 1 : 1 ), 15 * MINUTE_IN_SECONDS ); }
	public static function block_inactive( $user, string $username, string $password ) {
		if ( $user instanceof WP_User && '0' === (string) get_user_meta( $user->ID, 'creditrack_active', true ) ) {
			return new WP_Error( 'invalid_login', __( 'The supplied credentials could not be accepted.', 'creditrack' ) );
		}
		return $user;
	}
	public static function record_login( string $login, WP_User $user ): void {
		delete_transient( self::key( $login ) );
		update_user_meta( $user->ID, 'creditrack_last_login', gmdate( 'Y-m-d H:i:s' ) );
		Audit::record( 'user', $user->ID, 'login', array(), array(), 'Successful login' );
	}
	public static function login_redirect( string $redirect, string $requested, $user ): string { if ( $user instanceof WP_User && user_can( $user, 'creditrack_access' ) ) { return get_user_meta( $user->ID, 'creditrack_force_password_change', true ) ? home_url( '/creditrack/profile/?password_change=required' ) : home_url( '/creditrack/dashboard/' ); } return $redirect; }
	public static function after_password_reset( WP_User $user ): void { if ( get_user_meta( $user->ID, 'creditrack_force_password_change', true ) ) { delete_user_meta( $user->ID, 'creditrack_force_password_change' ); Audit::record( 'user', $user->ID, 'password_reset_completed', array(), array(), 'User completed a secure password reset' ); } }
	public static function protect_wp_admin(): void {
		$pagenow = (string) ( $GLOBALS['pagenow'] ?? '' );
		if ( is_admin() && ! wp_doing_ajax() && 'admin-post.php' !== $pagenow && current_user_can( 'creditrack_access' ) && ! current_user_can( 'manage_options' ) ) { wp_safe_redirect( home_url( '/creditrack/' ) ); exit; }
	}
	public static function show_admin_bar( bool $show ): bool { return current_user_can( 'manage_options' ) ? $show : false; }
	private static function key( string $username ): string { return 'creditrack_login_' . hash( 'sha256', strtolower( trim( $username ) ) . '|' . sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) ); }
}
