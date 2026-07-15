<?php
require dirname( __DIR__ ) . '/creditrack-core/src/Domain/Money.php';
require dirname( __DIR__ ) . '/creditrack-core/src/Domain/Loan_Calculator.php';

use CrediTrack\Domain\Loan_Calculator;
use CrediTrack\Domain\Money;

function expect_same( mixed $expected, mixed $actual, string $message ): void {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: $message\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); }
}

$calculator = new Loan_Calculator();
expect_same( 101, Money::minor( '1.005' ), 'Money rounds half up.' );
expect_same( '-1.01', Money::decimal( Money::minor( '-1.005' ) ), 'Negative money rounds symmetrically.' );
$flat = $calculator->calculate( '100.00', '15', 'Flat', 12 );
expect_same( '15.00', $flat['total_interest'], 'Flat interest regression.' );
expect_same( '115.00', $flat['total_repayable'], 'Total repayable includes interest.' );
expect_same( '15.00', Money::decimal( Money::minor( $flat['total_repayable'] ) - Money::minor( '100.00' ) ), 'USD 100 payment leaves USD 15.' );
expect_same( '180.00', $calculator->calculate( '100.00', '2', 'Monthly', 40 )['total_repayable'], 'Monthly simple interest.' );
expect_same( '110.00', $calculator->calculate( '100.00', '5', 'Quarterly', 6 )['total_repayable'], 'Quarterly simple interest.' );
expect_same( '115.00', $calculator->calculate( '100.00', '10', 'Annual', 18 )['total_repayable'], 'Annual simple interest.' );
$amortized = $calculator->calculate( '1000.00', '12', 'Amortized', 12 );
expect_same( '66.19', $amortized['total_interest'], 'Declining-balance amortized interest.' );
$loan = array_merge( $calculator->calculate( '100.00', '15', 'Flat', 3 ), array( 'term_months' => 3 ) );
$schedule = $calculator->schedule( $loan, '2026-01-31' );
expect_same( array( '2026-02-28','2026-03-31','2026-04-30' ), array_column( $schedule, 'due_date' ), 'Month-end dates clamp to the target month.' );
expect_same( 11500, array_sum( array_map( static fn( $row ) => Money::minor( $row['total_installment'] ), $schedule ) ), 'Schedule reconciles exactly.' );
fwrite( STDOUT, "All domain tests passed.\n" );
