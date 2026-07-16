<?php
/**
 * Plugin Name: CrediTrack Core
 * Description: Transactional lending and credit-management services for CrediTrack.
 * Version: 0.3.5
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: Infinity Lines of Code (Pvt) Ltd
 * Text Domain: creditrack
 */

namespace CrediTrack;

defined( 'ABSPATH' ) || exit;

define( 'CREDITRACK_VERSION', '0.3.5' );
define( 'CREDITRACK_FILE', __FILE__ );
define( 'CREDITRACK_PATH', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
			return;
		}
		$file = CREDITRACK_PATH . 'src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( Infrastructure\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Infrastructure\Activator::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Infrastructure\Activator::maybe_upgrade();
		( new Plugin() )->boot();
	}
);
