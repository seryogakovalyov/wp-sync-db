<?php
// Standalone regression tests: no WordPress installation or real media writes required.
class WPSDB_Addon {}
function apply_filters( $name, $value ) {
	return $name === 'wpsdb_exclude_resized_media' ? $GLOBALS['exclude_sizes'] : $value;
}
require_once dirname( __DIR__ ) . '/module-media-files/class/wpsdb-media-files.php';
class WPSDB_WebP_Test extends WPSDB_Media_Files {
	function __construct() {}
	function queue( $attachments, $destination, $source ) {
		$this->files_to_migrate = array();
		$this->media_diff( $attachments, $attachments, $destination, $source );
		return $this->files_to_migrate;
	}
}
function check_queue( $label, $actual, $expected ) {
	ksort( $actual );
	ksort( $expected );
	if ( $actual !== $expected ) {
		fwrite( STDERR, $label . ': ' . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
	echo "PASS: $label\n";
}
$GLOBALS['exclude_sizes'] = false;
$media = new WPSDB_WebP_Test();
$attachments = array( array( 'file' => '2026/photo.jpg', 'date' => '2026-10-30 12:00:00', 'sizes' => array( '2026/photo-300x200.jpg' ) ) );
$originals = array( '2026/photo.jpg' => 1000, '2026/photo-300x200.jpg' => 500 );
$webp = array( '2026/photo.jpg.webp' => 200, '2026/photo-300x200.jpg.webp' => 100 );
$source = $originals + $webp;
check_queue( 'Existing originals, missing WebP (push/pull shared diff)', $media->queue( $attachments, $originals, $source ), $webp );
check_queue( 'New originals and WebP', $media->queue( $attachments, array(), $source ), $source );
check_queue( 'Identical copies skipped', $media->queue( $attachments, $source, $source ), array() );
$changed = $source;
$changed['2026/photo.jpg.webp'] = 201;
$changed['2026/photo-300x200.jpg.webp'] = 101;
check_queue( 'Changed WebP sizes', $media->queue( $attachments, $source, $changed ), array_diff_assoc( $changed, $source ) );
check_queue( 'Absent source WebP skipped', $media->queue( $attachments, $source, $originals ), array() );
$unrelated = array( '2026/unrelated.jpg.webp' => 123, '2026/photo.jpg.webp.webp' => 123 );
check_queue( 'Unrelated files excluded', $media->queue( $attachments, $source, $source + $unrelated ), array() );
$GLOBALS['exclude_sizes'] = true;
check_queue( 'Resized media exclusion respected', $media->queue( $attachments, $originals, $source ), array( '2026/photo.jpg.webp' => 200 ) );
$GLOBALS['exclude_sizes'] = false;
foreach ( array( '../escape.jpg', '/absolute.jpg', 'x/../escape.jpg', 'x/./photo.jpg', 'C:/photo.jpg', 'x\\photo.jpg', "x/\nphoto.jpg", 'native.webp', 'document.pdf' ) as $file ) {
	$attachment = array( array( 'file' => $file, 'date' => '2026-10-30 12:00:00' ) );
	$base = array( $file => 100 );
	check_queue( 'Excluded path ' . json_encode( $file ), $media->queue( $attachment, $base, $base + array( $file . '.webp' => 50 ) ), array() );
}
foreach ( array( 'sites/2/photo.png', '2026/photo.JPEG' ) as $file ) {
	$attachment = array( array( 'file' => $file, 'date' => '2026-10-30 12:00:00' ) );
	$base = array( $file => 100 );
	$copy = array( $file . '.webp' => 50 );
	check_queue( 'Supported path ' . $file, $media->queue( $attachment, $base, $base + $copy ), $copy );
}
