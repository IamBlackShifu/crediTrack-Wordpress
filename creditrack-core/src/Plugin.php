<?php
namespace CrediTrack;

use CrediTrack\Http\Portal_Controller;
use CrediTrack\Infrastructure\Activator;

final class Plugin {
	public function boot(): void {
		add_action( 'init', array( Activator::class, 'register_roles' ) );
		add_filter( 'authenticate', array( Security\Authentication::class, 'block_inactive' ), 30, 3 );
		add_filter( 'authenticate', array( Security\Authentication::class, 'throttle' ), 5, 3 );
		add_action( 'wp_login_failed', array( Security\Authentication::class, 'record_failure' ) );
		add_action( 'wp_login', array( Security\Authentication::class, 'record_login' ), 10, 2 );
		add_filter( 'login_redirect', array( Security\Authentication::class, 'login_redirect' ), 10, 3 );
		add_action( 'after_password_reset', array( Security\Authentication::class, 'after_password_reset' ) );
		add_action( 'admin_init', array( Security\Authentication::class, 'protect_wp_admin' ) );
		add_filter( 'show_admin_bar', array( Security\Authentication::class, 'show_admin_bar' ) );
		add_action( 'init', array( Portal_Controller::class, 'register_actions' ) );
		add_action( 'init', array( Infrastructure\Overdue_Job::class, 'maybe_run' ), 20 );
		add_action( 'creditrack_daily', array( Infrastructure\Overdue_Job::class, 'run' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) { \WP_CLI::add_command( 'creditrack demo', Infrastructure\Demo_Command::class ); }
	}
}
