<?php
namespace CrediTrack\Domain;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

final class Loan_Calculator {
	public function calculate( string $principal, string $rate, string $type, int $months ): array {
		$principal_minor = Money::minor( $principal );
		if ( $principal_minor <= 0 ) { throw new DomainException( 'Principal must be greater than zero.' ); }
		if ( $months < 1 || $months > 600 ) { throw new DomainException( 'Term must be between 1 and 600 months.' ); }
		if ( 'amortized' === strtolower( $type ) ) { return $this->calculate_amortized( $principal_minor, $rate, $months ); }
		$multiplier = match ( strtolower( $type ) ) {
			'flat' => '1',
			'monthly' => (string) $months,
			'quarterly' => self::ratio( $months, 3 ),
			'annual' => self::ratio( $months, 12 ),
			default => throw new DomainException( 'Unknown interest type.' ),
		};
		$interest = Money::percent( $principal_minor, $rate, $multiplier );
		$total = $principal_minor + $interest;
		return array( 'principal' => Money::decimal( $principal_minor ), 'total_interest' => Money::decimal( $interest ), 'total_repayable' => Money::decimal( $total ), 'installment_amount' => Money::decimal( (int) round( $total / $months, 0, PHP_ROUND_HALF_UP ) ) );
	}

	public function schedule( array $loan, string $disbursement_date ): array {
		if ( 'amortized' === strtolower( (string) ( $loan['interest_type'] ?? '' ) ) ) { return $this->amortized_schedule( $loan, $disbursement_date ); }
		$months = (int) $loan['term_months'];
		$principal = Money::minor( $loan['principal'] );
		$interest = Money::minor( $loan['total_interest'] );
		$total = $principal + $interest;
		$base_principal = intdiv( $principal, $months );
		$base_interest = intdiv( $interest, $months );
		$date = new DateTimeImmutable( $disbursement_date, new DateTimeZone( 'UTC' ) );
		$anchor_day = (int) $date->format( 'd' );
		$rows = array();
		$allocated_principal = 0; $allocated_interest = 0;
		for ( $number = 1; $number <= $months; ++$number ) {
			$p = $number === $months ? $principal - $allocated_principal : $base_principal;
			$i = $number === $months ? $interest - $allocated_interest : $base_interest;
			$due = self::add_months_clamped( $date, $number, $anchor_day );
			$rows[] = array( 'installment_number' => $number, 'due_date' => $due->format( 'Y-m-d' ), 'principal_portion' => Money::decimal( $p ), 'interest_portion' => Money::decimal( $i ), 'total_installment' => Money::decimal( $p + $i ), 'remaining_amount' => Money::decimal( $p + $i ) );
			$allocated_principal += $p; $allocated_interest += $i;
		}
		$scheduled_total = array_sum( array_map( static fn( $row ) => Money::minor( $row['total_installment'] ), $rows ) );
		if ( $scheduled_total !== $total ) { throw new DomainException( 'Schedule reconciliation failed.' ); }
		return $rows;
	}

	private static function ratio( int $value, int $divisor ): string { return number_format( $value / $divisor, 6, '.', '' ); }
	private function calculate_amortized( int $principal, string $annual_rate, int $months ): array {
		$monthly_rate = (float) $annual_rate / 1200;
		$payment = 0.0 === $monthly_rate ? (int) round( $principal / $months ) : (int) round( $principal * $monthly_rate / ( 1 - ( 1 + $monthly_rate ) ** -$months ), 0, PHP_ROUND_HALF_UP );
		$balance = $principal; $interest_total = 0;
		for ( $number = 1; $number <= $months; ++$number ) { $interest = Money::percent( $balance, $annual_rate, self::ratio( 1, 12 ) ); $principal_part = $number === $months ? $balance : min( $balance, max( 0, $payment - $interest ) ); $balance -= $principal_part; $interest_total += $interest; }
		$total = $principal + $interest_total;
		return array( 'principal' => Money::decimal( $principal ), 'total_interest' => Money::decimal( $interest_total ), 'total_repayable' => Money::decimal( $total ), 'installment_amount' => Money::decimal( $payment ) );
	}
	private function amortized_schedule( array $loan, string $disbursement_date ): array {
		$months = (int) $loan['term_months']; $balance = Money::minor( $loan['principal'] ); $target_total = Money::minor( $loan['total_repayable'] ); $payment = Money::minor( $loan['installment_amount'] ); $date = new DateTimeImmutable( $disbursement_date, new DateTimeZone( 'UTC' ) ); $anchor = (int) $date->format( 'd' ); $rows = array(); $scheduled = 0;
		for ( $number = 1; $number <= $months; ++$number ) { $interest = Money::percent( $balance, (string) $loan['interest_rate'], self::ratio( 1, 12 ) ); $principal = $number === $months ? $balance : min( $balance, max( 0, $payment - $interest ) ); $installment = $number === $months ? $target_total - $scheduled : $principal + $interest; if ( $number === $months ) { $interest = $installment - $principal; } $balance -= $principal; $scheduled += $installment; $rows[] = array( 'installment_number'=>$number, 'due_date'=>self::add_months_clamped( $date, $number, $anchor )->format( 'Y-m-d' ), 'principal_portion'=>Money::decimal( $principal ), 'interest_portion'=>Money::decimal( $interest ), 'total_installment'=>Money::decimal( $installment ), 'remaining_amount'=>Money::decimal( $installment ) ); }
		if ( 0 !== $balance || $scheduled !== $target_total ) { throw new DomainException( 'Amortized schedule reconciliation failed.' ); }
		return $rows;
	}
	private static function add_months_clamped( DateTimeImmutable $date, int $months, int $day ): DateTimeImmutable {
		$first = $date->modify( 'first day of +' . $months . ' month' );
		$clamped = min( $day, (int) $first->format( 't' ) );
		return $first->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'm' ), $clamped );
	}
}
