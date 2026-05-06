<?php

declare( strict_types=1 );

$root        = dirname( __DIR__ );
$package     = 'ibantest-for-woocommerce';
$build_root  = $root . '/build';
$destination = $build_root . '/' . $package;
$distignore  = $root . '/.distignore';
$locales     = [ 'de_DE', 'it_IT', 'fr_FR', 'nl_NL', 'es_ES', 'pl_PL' ];

$patterns = is_file( $distignore )
	? array_values( array_filter( array_map( 'trim', file( $distignore ) ?: [] ) ) )
	: [];

$patterns[] = 'build';

remove_path( $destination );
if ( ! is_dir( $destination ) && ! mkdir( $destination, 0775, true ) && ! is_dir( $destination ) ) {
	fwrite( STDERR, "Could not create build directory.\n" );
	exit( 1 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ) use ( $root, $patterns ): bool {
			$relative = ltrim( str_replace( $root, '', $file->getPathname() ), DIRECTORY_SEPARATOR );
			return ! is_excluded( $relative, $patterns );
		}
	),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $file ) {
	$relative = ltrim( str_replace( $root, '', $file->getPathname() ), DIRECTORY_SEPARATOR );
	$target   = $destination . DIRECTORY_SEPARATOR . $relative;

	if ( $file->isDir() ) {
		if ( ! is_dir( $target ) && ! mkdir( $target, 0775, true ) && ! is_dir( $target ) ) {
			fwrite( STDERR, "Could not create directory: {$target}\n" );
			exit( 1 );
		}
		continue;
	}

	$target_dir = dirname( $target );
	if ( ! is_dir( $target_dir ) && ! mkdir( $target_dir, 0775, true ) && ! is_dir( $target_dir ) ) {
		fwrite( STDERR, "Could not create directory: {$target_dir}\n" );
		exit( 1 );
	}

	copy( $file->getPathname(), $target );
}

$command = 'cd ' . escapeshellarg( $destination ) . ' && composer install --no-dev --optimize-autoloader --no-interaction';
passthru( $command, $exit_code );
if ( 0 !== $exit_code ) {
	exit( $exit_code );
}

smoke_check_build( $destination, $locales );

echo "Release build ready: {$destination}\n";

function is_excluded( string $relative, array $patterns ): bool {
	$relative = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
	foreach ( $patterns as $pattern ) {
		$pattern = trim( str_replace( DIRECTORY_SEPARATOR, '/', $pattern ), '/' );
		if ( '' === $pattern ) {
			continue;
		}

		if ( $relative === $pattern || str_starts_with( $relative, $pattern . '/' ) ) {
			return true;
		}

		if ( fnmatch( $pattern, basename( $relative ) ) || fnmatch( $pattern, $relative ) ) {
			return true;
		}
	}

	return false;
}

function remove_path( string $path ): void {
	if ( ! file_exists( $path ) ) {
		return;
	}

	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $file ) {
		$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
	}

	rmdir( $path );
}

function smoke_check_build( string $destination, array $locales ): void {
	assert_exists( $destination . '/ibantest-for-woocommerce.php', 'Main plugin file is missing from release build.' );
	assert_exists( $destination . '/vendor/autoload.php', 'Composer production autoload is missing from release build.' );
	assert_exists( $destination . '/composer.lock', 'composer.lock is missing from release build.' );
	assert_missing( $destination . '/tests', 'tests directory must not be shipped in release build.' );
	assert_missing( $destination . '/bin', 'bin directory must not be shipped in release build.' );
	assert_missing( $destination . '/vendor/phpunit', 'Dev dependency phpunit must not be shipped in release build.' );
	assert_missing( $destination . '/vendor/sebastian', 'Dev dependency sebastian packages must not be shipped in release build.' );

	foreach ( $locales as $locale ) {
		assert_exists(
			$destination . "/languages/ibantest-for-woocommerce-{$locale}.po",
			"Missing {$locale} PO file in release build."
		);
		assert_exists(
			$destination . "/languages/ibantest-for-woocommerce-{$locale}.mo",
			"Missing {$locale} MO file in release build. Run composer i18n before building."
		);
		if ( [] === glob( $destination . "/languages/ibantest-for-woocommerce-{$locale}-*.json" ) ) {
			fail( "Missing {$locale} JavaScript translation JSON in release build. Run composer i18n before building." );
		}
	}
}

function assert_exists( string $path, string $message ): void {
	if ( ! file_exists( $path ) ) {
		fail( $message );
	}
}

function assert_missing( string $path, string $message ): void {
	if ( file_exists( $path ) ) {
		fail( $message );
	}
}

function fail( string $message ): void {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}
