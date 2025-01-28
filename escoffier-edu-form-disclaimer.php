<?php

/**
 * Plugin Name: Escoffier Forms API
 * Description: Syncs form data from a central WordPress site.
 * Plugin URI: https://tellmemore.co/
 * Version: 1.0
 * Author: Pavel Burminsky
 * Author URI: https://tellmemore.co/
 * Text Domain: escoffier-forms-api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EscoffierFormsAPI {

	const FORMS_API_ROUTE = 'https://www.escoffier.edu/wp-json/escoffier-forms/v1/';
	const DISCLAIMER_OPTION_NAME = 'escoffier_disclaimer';

	public function __construct() {
		add_action( 'init', [$this, 'init'] );
		add_action( 'escoffier_disclaimer_sync', [$this, 'fetch_disclaimer'] );
	}

	public function init() {
		add_shortcode( 'escoffier_disclaimer', [$this, 'get_disclaimer'] );

		if ( ! wp_next_scheduled( 'escoffier_disclaimer_sync' ) ) {
			wp_schedule_event( time(), 'daily', 'escoffier_disclaimer_sync' );
		}
	}

	public function fetch_disclaimer() {
		$api_url = self::FORMS_API_ROUTE . 'disclaimer';

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			error_log( 'Failed to fetch disclaimer: ' . $response->get_error_message() );
			return;
		}

		$disclaimer = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $disclaimer ) {
			update_option( self::DISCLAIMER_OPTION_NAME, $disclaimer );
		}
	}

	public function get_disclaimer( $atts ) {
		$atts = shortcode_atts( [
			'submit' => 'Send Request',
		], $atts, 'escoffier_disclaimer' );

		$disclaimer = get_option( self::DISCLAIMER_OPTION_NAME );

		if ( false === $disclaimer ) {
			$this->fetch_disclaimer();
			$disclaimer = get_option( self::DISCLAIMER_OPTION_NAME );
		}

		if ( $disclaimer && strpos( $disclaimer, 'Send Request' ) !== false ) {
			$disclaimer = str_replace( 'Send Request', $atts['submit'], $disclaimer );
		}

		return $disclaimer ?: '';
	}
}

new EscoffierFormsAPI();