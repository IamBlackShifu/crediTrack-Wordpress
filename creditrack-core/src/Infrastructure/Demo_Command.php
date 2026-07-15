<?php
namespace CrediTrack\Infrastructure;

final class Demo_Command {
	public function __invoke( array $args, array $assoc_args ): void {
		if ( 'production' === wp_get_environment_type() ) { \WP_CLI::error( 'Demo data is disabled when WP_ENVIRONMENT_TYPE is production.' ); }
		if ( 'CREATE-DEMO-DATA' !== ( $assoc_args['confirm'] ?? '' ) ) { \WP_CLI::error( 'Pass --confirm=CREATE-DEMO-DATA. This command never runs automatically.' ); }
		$accounts = array( 'admin@creditrack.com'=>array( 'admin123','creditrack_administrator','Demo Administrator' ), 'officer@creditrack.com'=>array( 'officer123','creditrack_loan_officer','Demo Officer' ), 'viewer@creditrack.com'=>array( 'viewer123','creditrack_viewer','Demo Viewer' ) );
		foreach ( $accounts as $email => $values ) { $id = email_exists( $email ); if ( ! $id ) { $id = wp_insert_user( array( 'user_login'=>$email, 'user_email'=>$email, 'user_pass'=>$values[0], 'display_name'=>$values[2], 'role'=>$values[1] ) ); if ( is_wp_error( $id ) ) { \WP_CLI::warning( $id->get_error_message() ); continue; } update_user_meta( $id, 'creditrack_force_password_change', '1' ); update_user_meta( $id, 'creditrack_active', '1' ); } }
		global $wpdb; if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ct_clients" ) ) { $now = gmdate( 'Y-m-d H:i:s' ); foreach ( array( array( 'Tariro','Moyo','+263 77 000 1001','tariro@example.test' ),array( 'Kuda','Ncube','+263 77 000 1002','kuda@example.test' ),array( 'Rudo','Dube','+263 77 000 1003','rudo@example.test' ) ) as $index => $client ) { $wpdb->insert( $wpdb->prefix . 'ct_clients', array( 'first_name'=>$client[0], 'last_name'=>$client[1], 'email'=>$client[3], 'phone'=>$client[2], 'address'=>'Synthetic demo address ' . ( $index + 1 ), 'occupation'=>'Demo trader', 'monthly_income'=>'650.00', 'next_of_kin_name'=>'Demo Contact', 'next_of_kin_phone'=>'+263 77 999 0000', 'next_of_kin_address'=>'Synthetic demo address', 'is_active'=>1, 'notes'=>'Synthetic data — not a real person', 'created_at'=>$now, 'updated_at'=>$now ) ); } }
		update_option( 'creditrack_demo_mode', 1 ); \WP_CLI::success( 'Demo accounts and synthetic clients created. Existing users and passwords were not changed.' );
	}
}
