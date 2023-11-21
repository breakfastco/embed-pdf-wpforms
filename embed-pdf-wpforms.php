<?php
/**
 * Plugin Name: Embed PDF for WPForms
 * Plugin URI: https://breakfastco.xyz/embed-pdf-wpforms/
 * Description: Add-on for WPForms. Provides a PDF Viewer field.
 * Author: Breakfast
 * Author URI: https://breakfastco.xyz
 * Version: 1.1.1
 * License: GPLv3
 * Text Domain: embed-pdf-wpforms
 *
 * @author Corey Salzano <csalzano@duck.com>
 * @package embed-pdf-wpforms
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EMBED_PDF_WPFORMS_PATH' ) ) {
	define( 'EMBED_PDF_WPFORMS_PATH', __FILE__ );
}
if ( ! defined( 'EMBED_PDF_WPFORMS_VERSION' ) ) {
	define( 'EMBED_PDF_WPFORMS_VERSION', '1.1.1' );
}

if ( ! function_exists( 'embed_pdf_wpforms_init' ) ) {
	add_action( 'wpforms_loaded', 'embed_pdf_wpforms_init', 5 );
	/**
	 * Loads the plugin files and features.
	 *
	 * @return void
	 */
	function embed_pdf_wpforms_init() {
		if ( ! class_exists( 'WPForms_Field' ) ) {
			return;
		}
		require_once dirname( EMBED_PDF_WPFORMS_PATH ) . '/includes/class-wpforms-field-pdf-viewer.php';
		new WPForms_Field_PDF_Viewer();

		// Add our field type to the list of allowed fields in wpforms_get_form_fields().
		add_filter( 'wpforms_get_form_fields_allowed', 'embed_pdf_wpforms_field_types' );
	}

	/**
	 * Adds our field type to the list of field types WPForms recognizes as
	 * valid.
	 *
	 * @param  array $allowed_form_fields Allow list of field types.
	 * @return array
	 */
	function embed_pdf_wpforms_field_types( $allowed_form_fields ) {
		$allowed_form_fields[] = 'pdf_viewer';
		return $allowed_form_fields;
	}
}
