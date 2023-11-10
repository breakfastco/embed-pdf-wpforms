<?php
/**
 * WPForms_Field_PDF_Viewer
 *
 * @package embed-pdf-wpforms
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WPForms_Field' ) ) {
	/**
	 * Dropdown field.
	 *
	 * @since 1.0.0
	 */
	class WPForms_Field_PDF_Viewer extends WPForms_Field {

		/**
		 * Default value for the Initial Scale setting.
		 *
		 * @var string
		 */
		const DEFAULT_SCALE_VALUE = '1';

		/**
		 * Primary class constructor.
		 *
		 * @since 1.0.0
		 */
		public function init() {

			// Define field type information.
			$this->name  = esc_html__( 'PDF Viewer', 'embed-pdf-wpforms' );
			$this->type  = 'pdf_viewer';
			$this->icon  = 'fa-caret-square-o-down';
			$this->order = 200;

			// Define additional field properties.
			add_filter( 'wpforms_field_properties_' . $this->type, array( $this, 'field_properties' ), 5, 3 );

			// Register our JS & CSS assets.
			add_action( 'wp_enqueue_scripts', array( $this, 'assets_register' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'assets_register' ) );

			// Form frontend enqueues.
			add_action( 'wpforms_frontend_css', array( $this, 'enqueue_css_frontend' ) );
			add_action( 'wpforms_frontend_js', array( $this, 'load_js' ) );

			add_action( 'wpforms_builder_enqueues', array( $this, 'enqueue_assets_builder' ) );

			// AJAX handler for the Download PDF into Media Library button.
			add_action( 'wp_ajax_epdf_wf_download_pdf_media', array( $this, 'ajax_handler_download_pdf_media' ) );
		}

		/**
		 * AJAX handler for the Download PDF into Media Library button.
		 *
		 * @return void
		 */
		public function ajax_handler_download_pdf_media() {
			check_ajax_referer( 'epdf_wf_download_pdf_media' );

			if ( empty( $_POST['url'] ) ) {
				wp_send_json_error();
			}

			$url = sanitize_url( wp_unslash( $_POST['url'] ) );

			// Download the file.
			$tmp_file = download_url( $url );
			if ( is_wp_error( $tmp_file ) ) {
				wp_send_json_error(
					array(
						/* translators: 1. An error message. */
						'msg' => sprintf( __( 'The download failed with error "%s"', 'embed-pdf-wpforms' ), $tmp_file->get_error_message() ),
					)
				);
			}
			// Move from a temp file to the uploads directory.
			$upload_dir = wp_upload_dir();
			$file_name  = wp_unique_filename( $upload_dir['path'], basename( $url ) );
			$path       = $upload_dir['path'] . DIRECTORY_SEPARATOR . $file_name;
			global $wp_filesystem;
			if ( ! class_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
			}
			WP_Filesystem();
			$wp_filesystem->move( $tmp_file, $path );
			// Add to the database.
			$media_id = wp_insert_attachment(
				array(
					'post_author'    => wp_get_current_user()->ID,
					'post_title'     => $file_name,
					'post_status'    => 'publish',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
					'meta_input'     => array(
						/* translators: 1. An external URL. */
						'_source' => sprintf( __( 'Downloaded from %s by Embed PDF for WPForms', 'embed-pdf-wpforms' ), $url ),
					),
				),
				$path
			);
			wp_update_attachment_metadata( $media_id, wp_generate_attachment_metadata( $media_id, $path ) );
			wp_send_json_success(
				array(
					'url' => wp_get_attachment_url( $media_id ),
				)
			);
		}

		/**
		 * Registers our scripts and styles so they can be enqueued whenever
		 * they are needed.
		 *
		 * @return void
		 */
		public function assets_register() {
			// pdf.js.
			$handle = 'epdf_wf_pdfjs';
			wp_register_script(
				$handle,
				plugins_url( 'js/pdfjs/pdf.min.js', EMBED_PDF_WPFORMS_PATH ), // No un-minimized version of this script included.
				array(),
				EMBED_PDF_WPFORMS_VERSION,
				true
			);
			wp_add_inline_script(
				$handle,
				'const epdf_wf_pdfjs_strings = ' . wp_json_encode(
					array(
						'url_worker'        => plugins_url( 'js/pdfjs/pdf.worker.min.js', EMBED_PDF_WPFORMS_PATH ), // No unminimized version of this script included.
						'initial_scale'     => self::DEFAULT_SCALE_VALUE,
						'is_user_logged_in' => is_user_logged_in(),
					)
				),
				'before'
			);

			// Script for the front-end.
			$handle = 'epdf_wf_pdf_viewer';
			$min    = wpforms_get_min_suffix();
			wp_register_script(
				$handle,
				plugins_url( "js/field-pdf-viewer{$min}.js", EMBED_PDF_WPFORMS_PATH ),
				array( 'wp-i18n', 'epdf_wf_pdfjs' ),
				EMBED_PDF_WPFORMS_VERSION,
				true
			);
			wp_add_inline_script(
				$handle,
				'const epdf_wf_pdf_viewer_strings = ' . wp_json_encode(
					array(
						'site_url'         => site_url(),
						'script_debug'     => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
						'can_upload_files' => current_user_can( 'upload_files' ),
						'ajax_url'         => admin_url( 'admin-ajax.php' ),
						'nonce'            => wp_create_nonce( 'epdf_wf_download_pdf_media' ),
					)
				),
				'before'
			);

			// Script for the Builder.
			wp_register_script(
				'epdf_wf_form_editor',
				plugins_url( "js/form-editor{$min}.js", EMBED_PDF_WPFORMS_PATH ),
				array( 'wp-i18n', 'epdf_wf_pdfjs', 'jquery' ),
				EMBED_PDF_WPFORMS_VERSION,
				true
			);

			// CSS for the Builder.
			wp_register_style(
				'wpforms-builder-embed-pdf',
				plugins_url( "css/editor{$min}.css", EMBED_PDF_WPFORMS_PATH ),
				array( 'wpforms-builder-fields' ),
				EMBED_PDF_WPFORMS_VERSION
			);

			// CSS for the front-end.
			wp_register_style(
				'wpforms-embed-pdf',
				plugins_url( "css/viewer{$min}.css", EMBED_PDF_WPFORMS_PATH ),
				array(),
				EMBED_PDF_WPFORMS_VERSION
			);
		}

		/**
		 * Define additional field properties.
		 *
		 * @since 1.5.0
		 *
		 * @param array $properties Field properties.
		 * @param array $field      Field settings.
		 * @param array $form_data  Form data and settings.
		 *
		 * @return array
		 */
		public function field_properties( $properties, $field, $form_data ) {

			$form_id  = absint( $form_data['id'] );
			$field_id = absint( $field['id'] );

			// Set options container (<select>) properties.
			$properties['input_container'] = array(
				'class' => array(),
				'data'  => array(),
				'id'    => "wpforms-{$form_id}-field_{$field_id}",
				'attr'  => array(
					'name' => "wpforms[fields][{$field_id}]",
				),
			);

			// Add class that changes the field size.
			if ( ! empty( $field['size'] ) ) {
				$properties['input_container']['class'][] = 'wpforms-field-' . esc_attr( $field['size'] );
			}

			// Required class for pagebreak validation.
			if ( ! empty( $field['required'] ) ) {
				$properties['input_container']['class'][] = 'wpforms-field-required';
			}

			// Add additional class for container.
			if (
				! empty( $field['style'] ) &&
				in_array( $field['style'], array( self::STYLE_CLASSIC, self::STYLE_MODERN ), true )
			) {
				$properties['container']['class'][] = "wpforms-field-vehicle-style-{$field['style']}";
			}

			return $properties;
		}

		/**
		 * Field options panel inside the builder.
		 *
		 * @since 1.0.0
		 *
		 * @param array $field Field settings.
		 */
		public function field_options( $field ) {
			/*
			 * Basic field options.
			 */

			// Options open markup.
			$this->field_option(
				'basic-options',
				$field,
				array(
					'markup' => 'open',
				)
			);

			// Label.
			$this->field_option( 'label', $field );

			// Description.
			$this->field_option( 'description', $field );

			// PDF URL.
			// [ Choose PDF ] [ https://breakfastco.test/wp-cont].
			$lbl = $this->field_element(
				'label',
				$field,
				array(
					'slug'    => 'pdf_url',
					'value'   => esc_html__( 'PDF', 'embed-pdf-wpforms' ),
					'tooltip' => esc_html__( 'Enter the URL of the PDF to load into the viewer.', 'embed-pdf-wpforms' ),
				),
				false
			);
			$fld = $this->field_element(
				'text',
				$field,
				array(
					'slug'  => 'pdf_url',
					'value' => ! empty( $field['pdf_url'] ) ? $field['pdf_url'] : '',
					'class' => 'pdf-url',
				),
				false
			);
			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'pdf_url',
					'content' => $lbl
						. '<button onclick="return false" class="wpforms-btn wpforms-btn-sm wpforms-btn-blue" data-field="' . esc_attr( $field['id'] ) . '">'
						. esc_html__( 'Choose PDF', 'embed-pdf-wpforms' )
						. '</button>'
						. $fld,
				)
			);
			// PDF URL end.

			// Initial Scale.
			$lbl = $this->field_element(
				'label',
				$field,
				array(
					'slug'    => 'initial_scale',
					'value'   => esc_html__( 'Initial Scale', 'embed-pdf-wpforms' ),
					'tooltip' => esc_html__( 'Loading too small to read? Increase this value to zoom in.', 'embed-pdf-wpforms' ),
				),
				false
			);
			$fld = $this->field_element(
				'text',
				$field,
				array(
					'slug'  => 'initial_scale',
					'type'  => 'number',
					'value' => ! empty( $field['initial_scale'] ) ? $field['initial_scale'] : self::DEFAULT_SCALE_VALUE,
					'class' => 'initial-scale',
				),
				false
			);
			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'initial_scale',
					'content' => $lbl . $fld . '<small>' . esc_html__( 'Loading too small to read? Increase this value to zoom in.', 'embed-pdf-wpforms' ) . '</small>',
				)
			);
			// Initial Scale end.

			// Required toggle.
			$this->field_option( 'required', $field );

			// Options close markup.
			$this->field_option(
				'basic-options',
				$field,
				array(
					'markup' => 'close',
				)
			);

			/*
			* Advanced field options.
			*/

			// Options open markup.
			$this->field_option(
				'advanced-options',
				$field,
				array(
					'markup' => 'open',
				)
			);

			// Custom CSS classes.
			$this->field_option( 'css', $field );

			// Hide label.
			$this->field_option( 'label_hide', $field );

			// Options close markup.
			$this->field_option(
				'advanced-options',
				$field,
				array(
					'markup' => 'close',
				)
			);
		}

		/**
		 * Field preview inside the builder.
		 *
		 * @since 1.0.0
		 * @since 1.6.1 Added a `Modern` style select support.
		 *
		 * @param array $field Field settings.
		 */
		public function field_preview( $field ) {
			?>
			<label class="label-title">
				<div class="grey text"><?php

				if ( ! empty( $field['label'] ) && __( 'PDF Viewer', 'embed-pdf-wpforms' ) !== $field['label'] ) {
					echo esc_html( $field['label'] );
				} else {
					echo '<i class="fa fa-code"></i> ' . esc_html__( 'PDF Viewer', 'embed-pdf-wpforms' );
				}

				?></div>
			</label>
			<div class="description"><?php esc_html_e( 'Contents of this field are not displayed in the form builder preview.', 'embed-pdf-wpforms' ); ?></div>
			<?php
		}

		/**
		 * Field display on the form front-end.
		 *
		 * @since 1.0.0
		 * @since 1.5.0 Converted to a new format, where all the data are taken not from $deprecated, but field properties.
		 * @since 1.6.1 Added a multiple select support.
		 *
		 * @param array $field      Field data and settings.
		 * @param array $deprecated Deprecated array of field attributes.
		 * @param array $form_data  Form data and settings.
		 */
		public function field_display( $field, $deprecated, $form_data ) {

			// Define data.
			$field_id      = $field['id'];
			$form_id       = $form_data['id'];
			$canvas_id     = sprintf( 'wpforms-%s-canvas_%s', $form_id, $field_id );
			$initial_scale = empty( $field['initial_scale'] ) ? DEFAULT_SCALE_VALUE : $field['initial_scale'];
			$url           = $this->get_url( $field, $form_id );

			// Do we even have a PDF?
			if ( empty( $url ) ) {
				// No.
				if ( defined( 'WPFORMS_DEBUG' ) && WPFORMS_DEBUG ) {
					/* translators: 1: field ID, 2: form ID. */
					$message = sprintf( __( 'No PDF to load into field %1$s on form %2$s', 'embed-pdf-wpforms' ), $field_id, $form_id );
					wpforms_log(
						__( 'Embed PDF for WPForms', 'embed-pdf-wpforms' ),
						$message,
						array(
							'type'    => 'error',
							'form_id' => $form_id,
						)
					);
				}
				return;
			}

			// Primary input is a hidden field that saves the URL.
			$primary                  = $field['properties']['inputs']['primary'];
			$primary['atts']['type']  = 'hidden';
			$primary['atts']['name']  = sprintf( 'wpforms[fields][%s]', $field_id );
			$primary['atts']['value'] = $url;

			printf(
				'<div class="epdf-controls-container">'
					// Paging controls.
					. '<span class="page"><button class="wpforms-page-button button" onclick="return false" id="%1$s_prev" data-field="%7$s" data-form="%9$s" title="%2$s">%2$s</button> <button class="wpforms-page-button button" onclick="return false" id="%1$s_next" data-field="%7$s" data-form="%9$s" title="%3$s">%3$s</button></span> '
					. '<span class="paging">%4$s <span id="%1$s_page_num"></span> / <span id="%1$s_page_count"></span></span> '
					// Zoom controls.
					. '<span class="zoom"><button class="wpforms-page-button button" onclick="return false" id="%1$s_zoom_out" data-field="%7$s" data-form="%9$s" title="%5$s">%5$s</button> <button class="wpforms-page-button button" onclick="return false" id="%1$s_zoom_in" data-field="%7$s" data-form="%9$s" title="%6$s">%6$s</button></span>'
					. '</div>'
					. '<div class="epdf-container"><canvas id="%1$s" class="epdf" data-initial-scale="%8$s" data-page-num="1" data-page-pending="" data-rendering="false" data-field="%7$s" data-form="%9$s"></canvas></div>'
					. '<input %10$s />',
				/* 1 */ esc_attr( $canvas_id ),
				/* 2 */ esc_html__( 'Previous', 'embed-pdf-wpforms' ),
				/* 3 */ esc_html__( 'Next', 'embed-pdf-wpforms' ),
				/* 4 */ esc_html__( 'Page:', 'embed-pdf-wpforms' ),
				/* 5 */ esc_html__( 'Zoom Out', 'embed-pdf-wpforms' ),
				/* 6 */ esc_html__( 'Zoom In', 'embed-pdf-wpforms' ),
				/* 7 */ esc_attr( $field_id ),
				/* 8 */ esc_attr( $initial_scale ),
				/* 9 */ esc_attr( $form_id ),
				/*10 */ wpforms_html_attributes( $primary['id'], array(), array(), $primary['atts'] ),
			);
		}

		/**
		 * What is the PDF URL? The user might have chosen a PDF and saved it
		 * with the form. It may be populated via Dynamic Population.
		 *
		 * @param  mixed $field   Field data and settings.
		 * @param  mixed $form_id Form data and settings.
		 * @return string
		 */
		protected function get_url( $field, $form_id ) {
			$url = $field['pdf_url'];
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$url = '';
			}

			// Do we have a PDF URL via Dynamic Population?
			if ( ! empty( $field['properties']['inputs']['primary']['attr']['value'] ) ) {
				// Is the populated value a URL?
				if ( filter_var( $field['properties']['inputs']['primary']['attr']['value'], FILTER_VALIDATE_URL ) ) {
					// Yes.
					$url = esc_url( $field['properties']['inputs']['primary']['attr']['value'] ?? '' );
				}
			}

			return $url;
		}

		/**
		 * Enqueue CSS and JS for the builder.
		 *
		 * @param string|null $view Current view.
		 */
		public function enqueue_assets_builder( $view = null ) {
			wp_enqueue_style( 'wpforms-builder-embed-pdf' );
			wp_enqueue_script( 'epdf_wf_pdf_viewer' );
			wp_enqueue_script( 'epdf_wf_form_editor' );
		}

		/**
		 * Form frontend CSS enqueues.
		 *
		 * @since 1.6.1
		 *
		 * @param array $forms Forms on the current page.
		 */
		public function enqueue_css_frontend( $forms ) {
			wp_enqueue_style( 'wpforms-embed-pdf' );
		}

		/**
		 * Form frontend JS enqueues.
		 *
		 * @since 1.6.1
		 *
		 * @param array $forms Forms on the current page.
		 */
		public function load_js( $forms ) {

			if (
				wpforms_has_field_type( 'pdf_viewer', $forms, true ) ||
				wpforms()->get( 'frontend' )->assets_global()
			) {
				wp_enqueue_script( 'epdf_wf_pdfjs' );
				wp_enqueue_script( 'epdf_wf_pdf_viewer' );
			}
		}

		/**
		 * Whether the provided form has a dropdown field with a specified style.
		 *
		 * @since 1.6.1
		 *
		 * @param array  $form  Form data.
		 * @param string $style Desired field style.
		 *
		 * @return bool
		 */
		protected function is_field_style( $form, $style ) {

			$is_field_style = false;

			if ( empty( $form['fields'] ) ) {

				return $is_field_style;
			}

			foreach ( (array) $form['fields'] as $field ) {

				if (
					! empty( $field['type'] ) &&
					$field['type'] === $this->type &&
					! empty( $field['style'] ) &&
					sanitize_key( $style ) === $field['style']
				) {
					$is_field_style = true;
					break;
				}
			}

			return $is_field_style;
		}

		/**
		 * Get field name for ajax error message.
		 *
		 * @since 1.6.3
		 *
		 * @param string $name  Field name for error triggered.
		 * @param array  $field Field settings.
		 * @param array  $props List of properties.
		 * @param string $error Error message.
		 *
		 * @return string
		 */
		public function ajax_error_field_name( $name, $field, $props, $error ) {

			if ( ! isset( $field['type'] ) || 'vehicle' !== $field['type'] ) {
				return $name;
			}
			if ( ! empty( $field['multiple'] ) ) {
				$input = isset( $props['inputs'] ) ? end( $props['inputs'] ) : array();

				return isset( $input['attr']['name'] ) ? $input['attr']['name'] . '[]' : '';
			}

			return $name;
		}
	}
}
