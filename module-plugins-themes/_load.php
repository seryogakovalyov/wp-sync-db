<?php
/*
Plugin Name: WP Sync DB Plugins & Themes
Description: Compare plugin and theme versions between sites before migration.
*/

function wp_sync_db_plugins_themes_loaded() {
	if ( ! class_exists( 'WPSDB_Base' ) ) return;

	require_once __DIR__ . '/class/wpsdb-plugins-themes.php';

	global $wpsdb_plugins_themes;
	$wpsdb_plugins_themes = new WPSDB_Plugins_Themes( __FILE__ );
}

add_action( 'plugins_loaded', 'wp_sync_db_plugins_themes_loaded', 20 );
