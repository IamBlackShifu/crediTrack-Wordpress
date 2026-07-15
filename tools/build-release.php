<?php
/** Build WordPress-compatible release ZIPs with portable forward-slash paths. */

declare(strict_types=1);

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$root = dirname( __DIR__ );
$version = $argv[1] ?? '';
if ( ! preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.]+)?$/', $version ) ) {
	fwrite( STDERR, "Usage: php tools/build-release.php <version>\n" );
	exit( 1 );
}

if ( ! class_exists( ZipArchive::class ) ) {
	fwrite( STDERR, "PHP ZipArchive is required.\n" );
	exit( 1 );
}

foreach ( array( 'creditrack-core', 'creditrack-portal' ) as $package ) {
	$source = $root . DIRECTORY_SEPARATOR . $package;
	$destination = $root . DIRECTORY_SEPARATOR . $package . '-' . $version . '.zip';
	$zip = new ZipArchive();
	if ( true !== $zip->open( $destination, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		fwrite( STDERR, "Could not create {$destination}.\n" );
		exit( 1 );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$relative = substr( $file->getPathname(), strlen( $source ) + 1 );
		$entry = $package . '/' . str_replace( DIRECTORY_SEPARATOR, '/', $relative );
		if ( ! $zip->addFile( $file->getPathname(), $entry ) ) {
			$zip->close();
			fwrite( STDERR, "Could not add {$entry}.\n" );
			exit( 1 );
		}
	}
	$zip->close();
	echo $destination . PHP_EOL;
}
