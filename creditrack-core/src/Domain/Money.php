<?php
namespace CrediTrack\Domain;

use InvalidArgumentException;

final class Money {
	public static function minor( string|int $amount, int $precision = 2 ): int {
		$value = trim( (string) $amount );
		if ( ! preg_match( '/^-?\d+(?:\.\d+)?$/', $value ) ) {
			throw new InvalidArgumentException( 'Invalid monetary amount.' );
		}
		$negative = str_starts_with( $value, '-' );
		$value = ltrim( $value, '-+' );
		$parts = array_pad( explode( '.', $value, 2 ), 2, '' );
		$fraction = str_pad( $parts[1], $precision + 1, '0' );
		$minor = ( (int) $parts[0] * ( 10 ** $precision ) ) + (int) substr( $fraction, 0, $precision );
		if ( (int) $fraction[ $precision ] >= 5 ) { ++$minor; }
		return $negative ? -$minor : $minor;
	}

	public static function decimal( int $minor, int $precision = 2 ): string {
		$negative = $minor < 0 ? '-' : '';
		$minor = abs( $minor );
		$factor = 10 ** $precision;
		return $negative . intdiv( $minor, $factor ) . '.' . str_pad( (string) ( $minor % $factor ), $precision, '0', STR_PAD_LEFT );
	}

	public static function percent( int $principal_minor, string $rate, string $multiplier = '1' ): int {
		$rate_scaled = self::scaled( $rate, 6 );
		$multiplier_scaled = self::scaled( $multiplier, 6 );
		return (int) round( $principal_minor * $rate_scaled * $multiplier_scaled / 100 / 1_000_000 / 1_000_000, 0, PHP_ROUND_HALF_UP );
	}

	private static function scaled( string $value, int $scale ): int {
		if ( ! preg_match( '/^\d+(?:\.\d+)?$/', trim( $value ) ) ) { throw new InvalidArgumentException( 'Invalid decimal.' ); }
		$parts = array_pad( explode( '.', trim( $value ), 2 ), 2, '' );
		return (int) $parts[0] * ( 10 ** $scale ) + (int) substr( str_pad( $parts[1], $scale, '0' ), 0, $scale );
	}
}
