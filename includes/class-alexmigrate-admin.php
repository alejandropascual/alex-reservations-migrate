<?php

class AlexMigrate_Admin {

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	public function admin_enqueue_scripts( $hook ) {

		// Load only on ?page=alexmigrate
		if ( 'settings_page_alexmigrate' !== $hook ) {
			return;
		}

		$js_url = ALEXMIGRATE_PLUGIN_URL . 'assets/program/';

		wp_enqueue_style( 'alexmigrate-program-css', $js_url . 'index.css', array(), ALEXMIGRATE_VERSION );
		wp_enqueue_script( 'alexmigrate-program-js', $js_url . 'main.js', array(), ALEXMIGRATE_VERSION, true );
	}

	public function admin_menu() {
		add_options_page( __( 'Migrate QRR bookings', 'alexmigrate' ), __( 'Migrate QRR bookings', 'alexmigrate' ), 'manage_options', 'alexmigrate', array($this, 'settings') );
	}

	public function settings() {
		echo '<div id="alexmigrate"></div>';
		/*
		Find a list of past bookings
		Find a list of future bookings
		Find a list of clients
		*/
	}
}

new AlexMigrate_Admin();
