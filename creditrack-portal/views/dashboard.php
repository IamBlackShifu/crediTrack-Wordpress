<?php
$clients = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ct_clients WHERE is_active=1" );
$loan_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ct_loans" );
$totals = $wpdb->get_row( "SELECT COALESCE(SUM(CASE WHEN disbursement_date IS NOT NULL THEN principal ELSE 0 END),0) disbursed,COALESCE(SUM(total_paid),0) collections,COALESCE(SUM(CASE WHEN status IN ('Active','Defaulted') THEN outstanding ELSE 0 END),0) outstanding FROM {$wpdb->prefix}ct_loans", ARRAY_A );
$overdue = (float) $wpdb->get_var( "SELECT COALESCE(SUM(remaining_amount),0) FROM {$wpdb->prefix}ct_schedules WHERE overdue=1" );
$outstanding = (float) $totals['outstanding'];
$collections = (float) $totals['collections'];
$risk_rate = $outstanding > 0 ? min( 100, ( $overdue / $outstanding ) * 100 ) : 0;
$repayment_rate = ( $collections + $outstanding ) > 0 ? ( $collections / ( $collections + $outstanding ) ) * 100 : 0;
$health = max( 0, 100 - $risk_rate );

$month_keys = array();
$month_labels = array();
$monthly_values = array();
for ( $offset = 5; $offset >= 0; --$offset ) {
	$timestamp = strtotime( '-' . $offset . ' months', current_time( 'timestamp' ) );
	$key = wp_date( 'Y-m', $timestamp );
	$month_keys[] = $key;
	$month_labels[ $key ] = wp_date( 'M', $timestamp );
	$monthly_values[ $key ] = 0.0;
}
$monthly_rows = $wpdb->get_results( $wpdb->prepare( "SELECT DATE_FORMAT(disbursement_date,'%%Y-%%m') period,COALESCE(SUM(principal),0) amount FROM {$wpdb->prefix}ct_loans WHERE disbursement_date >= %s GROUP BY period", $month_keys[0] . '-01' ) );
foreach ( $monthly_rows as $monthly_row ) { if ( isset( $monthly_values[ $monthly_row->period ] ) ) { $monthly_values[ $monthly_row->period ] = (float) $monthly_row->amount; } }
$chart_max = max( 1, max( $monthly_values ) );
$chart_points = array();
foreach ( array_values( $monthly_values ) as $index => $value ) { $chart_points[] = round( 34 + ( $index * 123.2 ), 1 ) . ',' . round( 205 - ( $value / $chart_max * 155 ), 1 ); }

$recent_loans = $wpdb->get_results( "SELECT l.loan_number,l.principal,l.disbursement_date,l.maturity_date,l.status,c.first_name,c.last_name FROM {$wpdb->prefix}ct_loans l JOIN {$wpdb->prefix}ct_clients c ON c.id=l.client_id ORDER BY l.created_at DESC LIMIT 5" );
$notices = $wpdb->get_results( $wpdb->prepare( "SELECT title,message,read_at,created_at FROM {$wpdb->prefix}ct_notifications WHERE user_id=%d AND dismissed_at IS NULL ORDER BY read_at IS NULL DESC,created_at DESC LIMIT 5", get_current_user_id() ) );
$due = $wpdb->get_results( $wpdb->prepare( "SELECT s.due_date,s.remaining_amount,s.days_past_due,l.loan_number,c.first_name,c.last_name FROM {$wpdb->prefix}ct_schedules s JOIN {$wpdb->prefix}ct_loans l ON l.id=s.loan_id JOIN {$wpdb->prefix}ct_clients c ON c.id=l.client_id WHERE s.remaining_amount>0 AND s.due_date<=DATE_ADD(UTC_DATE(),INTERVAL %d DAY) ORDER BY s.due_date LIMIT 10", (int) ( $settings['reminder_days'] ?? 7 ) ) );
?>
<div class="ct-dashboard-heading"><div><p>Welcome back, <?php echo esc_html( $user->first_name ?: $user->display_name ); ?></p><h1>Dashboard</h1><span>Here is what is happening with your portfolio today.</span></div><time datetime="<?php echo esc_attr( wp_date( 'c' ) ); ?>"><?php echo esc_html( wp_date( 'D, j M Y' ) ); ?></time></div>

<div class="ct-dashboard-kpis">
	<article class="ct-dashboard-kpi"><span class="ct-kpi-icon blue" aria-hidden="true">◎</span><div><small>Total loan portfolio</small><strong><?php echo esc_html( number_format_i18n( $loan_count ) ); ?></strong><span><?php echo esc_html( number_format_i18n( $clients ) ); ?> active clients</span></div></article>
	<article class="ct-dashboard-kpi"><span class="ct-kpi-icon teal" aria-hidden="true">↗</span><div><small>Total disbursed</small><strong><?php echo esc_html( $currency . number_format_i18n( $totals['disbursed'], 2 ) ); ?></strong><span><?php echo esc_html( $currency . number_format_i18n( $outstanding, 2 ) ); ?> outstanding</span></div></article>
	<article class="ct-dashboard-kpi"><span class="ct-kpi-icon blue" aria-hidden="true">%</span><div><small>Repayment rate</small><strong><?php echo esc_html( number_format_i18n( $repayment_rate, 1 ) . '%' ); ?></strong><span><?php echo esc_html( $currency . number_format_i18n( $collections, 2 ) ); ?> collected</span></div></article>
	<article class="ct-dashboard-kpi"><span class="ct-kpi-icon amber" aria-hidden="true">!</span><div><small>Portfolio at risk</small><strong><?php echo esc_html( number_format_i18n( $risk_rate, 1 ) . '%' ); ?></strong><span><?php echo esc_html( $currency . number_format_i18n( $overdue, 2 ) ); ?> overdue</span></div></article>
</div>

<div class="ct-dashboard-columns">
	<div class="ct-dashboard-primary">
		<section class="ct-dashboard-panel ct-portfolio-chart"><header><div><h2>Portfolio performance</h2><p>Disbursements over the last six months</p></div><span>Last 6 months</span></header><div class="ct-line-chart"><svg viewBox="0 0 700 250" role="img" aria-labelledby="portfolio-chart-title"><title id="portfolio-chart-title">Monthly loan disbursements</title><defs><linearGradient id="ct-chart-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#1f6fff" stop-opacity=".24"/><stop offset="1" stop-color="#1f6fff" stop-opacity="0"/></linearGradient></defs><g class="ct-chart-grid"><path d="M34 50H650M34 102H650M34 154H650M34 205H650"/></g><polygon points="34,205 <?php echo esc_attr( implode( ' ', $chart_points ) ); ?> 650,205" fill="url(#ct-chart-fill)"/><polyline points="<?php echo esc_attr( implode( ' ', $chart_points ) ); ?>"/><g class="ct-chart-dots"><?php foreach ( $chart_points as $point ) { $coordinates = explode( ',', $point ); ?><circle cx="<?php echo esc_attr( $coordinates[0] ); ?>" cy="<?php echo esc_attr( $coordinates[1] ); ?>" r="4"/><?php } ?></g><g class="ct-chart-months"><?php foreach ( $month_keys as $index => $key ) { ?><text x="<?php echo esc_attr( 34 + ( $index * 123.2 ) ); ?>" y="232" text-anchor="middle"><?php echo esc_html( $month_labels[ $key ] ); ?></text><?php } ?></g></svg></div><div class="ct-chart-values"><?php foreach ( $month_keys as $key ) { ?><span><i></i><?php echo esc_html( $month_labels[ $key ] . ' ' . $currency . number_format_i18n( $monthly_values[ $key ], 0 ) ); ?></span><?php } ?></div></section>

		<section class="ct-dashboard-panel"><header><div><h2>Recent loans</h2><p>Latest applications and disbursements</p></div><a href="<?php echo esc_url( $base . 'loans/' ); ?>">View all loans</a></header><div class="ct-dashboard-table"><table><thead><tr><th>Loan</th><th>Client</th><th>Amount</th><th>Due date</th><th>Status</th></tr></thead><tbody><?php foreach ( $recent_loans as $loan ) { ?><tr><td><strong><?php echo esc_html( $loan->loan_number ); ?></strong></td><td><?php echo esc_html( "$loan->first_name $loan->last_name" ); ?></td><td><?php echo esc_html( $currency . number_format_i18n( $loan->principal, 2 ) ); ?></td><td><?php echo esc_html( $loan->maturity_date ?: '—' ); ?></td><td><span class="ct-badge <?php echo esc_attr( $loan->status ); ?>"><?php echo esc_html( $loan->status ); ?></span></td></tr><?php } if ( ! $recent_loans ) { ?><tr><td colspan="5">No loans have been created yet.</td></tr><?php } ?></tbody></table></div></section>
	</div>

	<aside class="ct-dashboard-secondary">
		<section class="ct-health-panel"><span>Portfolio health</span><strong><?php echo esc_html( number_format_i18n( $health, 0 ) . '%' ); ?></strong><b><?php echo $health >= 90 ? 'Good' : ( $health >= 75 ? 'Monitor' : 'Action required' ); ?></b><div class="ct-health-track"><i style="width:<?php echo esc_attr( $health ); ?>%"></i></div><small>Based on the current overdue balance</small></section>
		<section class="ct-dashboard-panel ct-dashboard-notifications"><header><div><h2>Notifications</h2><p><?php echo esc_html( $unread ); ?> unread</p></div><a href="<?php echo esc_url( $base . 'notifications/' ); ?>">View all</a></header><div><?php foreach ( $notices as $notice ) { ?><article class="<?php echo $notice->read_at ? '' : 'unread'; ?>"><span aria-hidden="true">!</span><div><strong><?php echo esc_html( $notice->title ); ?></strong><p><?php echo esc_html( wp_trim_words( $notice->message, 12 ) ); ?></p><time><?php echo esc_html( $notice->created_at ); ?></time></div></article><?php } if ( ! $notices ) { ?><p class="ct-dashboard-empty">You are all caught up.</p><?php } ?></div></section>
	</aside>
</div>

<section class="ct-dashboard-panel ct-due-panel"><header><div><h2>Due and overdue repayments</h2><p>Items inside the configured reminder window</p></div><a href="<?php echo esc_url( $base . 'payments/' ); ?>">Open payments</a></header><div class="ct-dashboard-table"><table><thead><tr><th>Loan</th><th>Borrower</th><th>Due date</th><th>Amount</th><th>Days past due</th></tr></thead><tbody><?php foreach ( $due as $row ) { ?><tr><td><strong><?php echo esc_html( $row->loan_number ); ?></strong></td><td><?php echo esc_html( "$row->first_name $row->last_name" ); ?></td><td><?php echo esc_html( $row->due_date ); ?></td><td><?php echo esc_html( $currency . number_format_i18n( $row->remaining_amount, 2 ) ); ?></td><td><span class="<?php echo $row->days_past_due > 0 ? 'ct-overdue-days' : ''; ?>"><?php echo esc_html( $row->days_past_due > 0 ? $row->days_past_due : 'Upcoming' ); ?></span></td></tr><?php } if ( ! $due ) { ?><tr><td colspan="5">No repayments are due in the reminder window.</td></tr><?php } ?></tbody></table></div></section>
