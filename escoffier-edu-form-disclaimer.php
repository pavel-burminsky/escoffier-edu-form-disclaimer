<?php

/**
 * Plugin Name: Escoffier.edu Form Disclaimer
 * Description: Syncs form disclaimer from a central WordPress site.
 * Plugin URI: https://tellmemore.co/
 * Version: 1.0
 * Author: Pavel Burminsky
 * Author URI: https://tellmemore.co/
 * Text Domain: escoffier-edu-form-disclaimer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EscoffierFormDisclaimer {

	const FORMS_API_ROUTE = 'https://www.escoffier.edu/wp-json/escoffier/v1/form/disclaimer';
	const PREFIX = 'escoffier';
	const FORM_DISCLAIMER_NAME = self::PREFIX . '_form_disclaimer';
	const API_URL_OPTION_NAME = self::PREFIX . '_api_url';
	const SHORTCODE = self::PREFIX . '_edu_form_disclaimer';


	public function __construct() {
		add_action( 'init', [$this, 'init'] );
		add_action( 'admin_menu', [$this, 'add_options_page'] );
		add_action( 'admin_init', [$this, 'register_settings'] );
	}


	public function init() {
		add_shortcode( self::SHORTCODE, [$this, 'get_disclaimer'] );
	}


	public function add_options_page() {
		add_options_page(
			'Escoffier Form Disclaimer Settings',
			'Escoffier Form Disclaimer',
			'manage_options',
			'escoffier-form-disclaimer',
			[$this, 'render_settings_page']
		);
	}


	public function register_settings() {
		register_setting( self::FORM_DISCLAIMER_NAME, self::API_URL_OPTION_NAME );

		add_settings_section(
			self::PREFIX . '_form_disclaimer_section',
			'API Settings',
			null,
			self::PREFIX . '-form-disclaimer'
		);

		add_settings_field(
			self::API_URL_OPTION_NAME,
			'API URL for Disclaimer',
			[$this, 'render_api_url_field'],
			'escoffier-form-disclaimer',
			'escoffier_form_disclaimer_section'
		);
	}


	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1>Escoffier Form Disclaimer Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PREFIX . '_form_disclaimer' );
				do_settings_sections( self::PREFIX . '-form-disclaimer' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}


	public function render_api_url_field() {
		$api_url = get_option( self::API_URL_OPTION_NAME, self::FORMS_API_ROUTE );
		?>
		<input type="text" name="<?php echo self::API_URL_OPTION_NAME; ?>" value="<?php echo esc_attr( $api_url ); ?>" class="regular-text">
		<?php
	}


	public function fetch_disclaimer() {
		$api_url = get_option( self::API_URL_OPTION_NAME, self::FORMS_API_ROUTE );

		$response = wp_remote_get( $api_url );

		if ( is_wp_error( $response ) ) {
			error_log( 'Failed to fetch disclaimer: ' . $response->get_error_message() );
			$disclaimer = get_option( self::FORM_DISCLAIMER_NAME );
		} else {
			$disclaimer = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $disclaimer ) {
				update_option( self::FORM_DISCLAIMER_NAME, $disclaimer );
			} else {
				$disclaimer = get_option( self::FORM_DISCLAIMER_NAME );
			}
		}

		set_transient( self::FORM_DISCLAIMER_NAME, $disclaimer, 12 * HOUR_IN_SECONDS );
	}


	public function get_disclaimer( $atts ) {
		$atts = shortcode_atts( [
			'submit' => 'Send Request',
		], $atts, self::SHORTCODE );

		$disclaimer = get_transient( self::FORM_DISCLAIMER_NAME );

		if ( false === $disclaimer ) {
			$this->fetch_disclaimer();
			$disclaimer = get_transient( self::FORM_DISCLAIMER_NAME );
		}

		if ( $disclaimer && strpos( $disclaimer, 'Send Request' ) !== false ) {
			$disclaimer = str_replace( 'Send Request', $atts['submit'], $disclaimer );
		}

		return $disclaimer ?: '';
	}
}

new EscoffierFormDisclaimer();