<?php
/**
 * Plugin Name: Alex Reservations MIGRATE
 * Plugin URI:  https://alexreservations.com
 * Description: Restaurant reservations manager
 * Version:     1.0.0
 * Requires PHP: 8.1
 * Author:      AlexReservations
 * Author URI:  http://alexreservations.com
 * Donate link: https://alexreservations.com
 * License:     GPLv2
 * Text Domain: alexmigrate
 * Domain Path: /languages
 *
 * @link    https://alexreservations.com
 *
 * @package ALEXMIGRATE
 * @version 1.0.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Alex_Reservations_Migrate
{
	private static $singleton;

	public static function singleton()
	{
		if ( !isset( self::$singleton ) && !( self::$singleton instanceof Alex_Reservations_Migrate ) )
		{
			self::$singleton = new Alex_Reservations_Migrate;
			self::$singleton->setup_constants();

			add_action( 'plugins_loaded', array(self::$singleton, 'load_textdomain' ) );

			self::$singleton->includes();
		}

		return self::$singleton;
	}

	private function setup_constants()
	{
		// Version
		if ( ! defined( 'ALEXMIGRATE_VERSION' ) ) {
			define( 'ALEXMIGRATE_VERSION', '1.0.6' );
		}

		// Folder Path
		if ( ! defined( 'ALEXMIGRATE_PLUGIN_DIR' ) ) {
			define( 'ALEXMIGRATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Folder URL
		if ( ! defined( 'ALEXMIGRATE_PLUGIN_URL' ) ) {
			define( 'ALEXMIGRATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Root File
		if ( ! defined( 'ALEXMIGRATE_PLUGIN_FILE' ) ) {
			define( 'ALEXMIGRATE_PLUGIN_FILE', __FILE__ );
		}

		// Name for Setting
		if ( ! defined( 'ALEXMIGRATE_SETTINGS' ) ) {
			define( 'ALEXMIGRATE_SETTINGS', 'srr_settings' );
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'alexmigrate', false, plugin_basename( dirname( __FILE__ ) ) . "/languages/" );
	}

	private function includes() {

		require_once ALEXMIGRATE_PLUGIN_DIR.'includes/class-alexmigrate-admin.php';
		require_once ALEXMIGRATE_PLUGIN_DIR . 'includes/ajax-actions.php';
	}
}

function WP_ALEXMIGRATE() {
	return Alex_Reservations_Migrate::singleton();
}

WP_ALEXMIGRATE();
