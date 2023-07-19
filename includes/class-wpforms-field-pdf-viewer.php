<?php
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WPForms_Field' ) ) {
	/**
	 * Dropdown field.
	 *
	 * @since 1.0.0
	 */
	class WPForms_Field_PDF_Viewer extends WPForms_Field {

		/**
		 * Choices JS version.
		 *
		 * @since 1.6.3
		 */
		const CHOICES_VERSION = '9.0.1';

		/**
		 * Classic (old) style.
		 *
		 * @since 1.6.1
		 *
		 * @var string
		 */
		const STYLE_CLASSIC = 'classic';

		/**
		 * Modern style.
		 *
		 * @since 1.6.1
		 *
		 * @var string
		 */
		const STYLE_MODERN = 'modern';

		const DEFAULT_SCALE_VALUE = '1';

		/**
		 * Primary class constructor.
		 *
		 * @since 1.0.0
		 */
		public function init() {

			// Define field type information.
			$this->name     = esc_html__( 'PDF Viewer', 'embed-pdf-wpforms' );
			$this->type     = 'pdf_viewer';
			$this->icon     = 'fa-caret-square-o-down';
			$this->order    = 200;
			// $this->defaults = array(
			// 	1 => array(
			// 		'label'   => esc_html__( 'First Choice', 'embed-pdf-wpforms' ),
			// 		'value'   => '',
			// 		'default' => '',
			// 	),
			// 	2 => array(
			// 		'label'   => esc_html__( 'Second Choice', 'embed-pdf-wpforms' ),
			// 		'value'   => '',
			// 		'default' => '',
			// 	),
			// 	3 => array(
			// 		'label'   => esc_html__( 'Third Choice', 'embed-pdf-wpforms' ),
			// 		'value'   => '',
			// 		'default' => '',
			// 	),
			// );

			// Define additional field properties.
			add_filter( 'wpforms_field_properties_' . $this->type, array( $this, 'field_properties' ), 5, 3 );

			// Form frontend CSS enqueues.
			add_action( 'wpforms_frontend_css', array( $this, 'enqueue_frontend_css' ) );

			// Form frontend JS enqueues.
			add_action( 'wpforms_frontend_js', array( $this, 'load_js' ) );

			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

			add_action( 'wpforms_builder_enqueues', array( $this, 'enqueue_css_builder' ) );
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

			// Set properties.
			// foreach ( $choices as $key => $choice ) {

			// 	// Used for dynamic choices.
			// 	$depth = 1;

			// 	$properties['inputs'][ $key ] = array(
			// 		'container' => array(
			// 			'attr'  => array(),
			// 			'class' => array( "choice-{$key}", "depth-{$depth}" ),
			// 			'data'  => array(),
			// 			'id'    => '',
			// 		),
			// 		'label'     => array(
			// 			'attr'  => array(
			// 				'for' => "wpforms-{$form_id}-field_{$field_id}_{$key}",
			// 			),
			// 			'class' => array( 'wpforms-field-label-inline' ),
			// 			'data'  => array(),
			// 			'id'    => '',
			// 			'text'  => $choice['label'],
			// 		),
			// 		'attr'      => array(
			// 			'name'  => "wpforms[fields][{$field_id}]",
			// 			'value' => isset( $field['show_values'] ) ? $choice['value'] : $choice['label'],
			// 		),
			// 		'class'     => array(),
			// 		'data'      => array(),
			// 		'id'        => "wpforms-{$form_id}-field_{$field_id}_{$key}",
			// 		'required'  => ! empty( $field['required'] ) ? 'required' : '',
			// 		'default'   => isset( $choice['default'] ),
			// 	);
			// }

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
					'content' => $lbl . '<button class="wpforms-btn wpforms-btn-sm wpforms-btn-blue">Choose PDF</button>' . $fld,
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
					'value' => ! empty( $field['initial_scale'] ) ? $field['initial_scale'] : '',
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

			// Multiple options selection.
			$fld = $this->field_element(
				'toggle',
				$field,
				array(
					'slug'    => 'multiple',
					'value'   => ! empty( $field['multiple'] ),
					'desc'    => esc_html__( 'Multiple Options Selection', 'embed-pdf-wpforms' ),
					'tooltip' => esc_html__( 'Allow users to select multiple choices in this field.', 'embed-pdf-wpforms' ) . '<br>' .
								sprintf(
									wp_kses( /* translators: %s - URL to WPForms.com doc article. */
										esc_html__( 'For details, including how this looks and works for your site\'s visitors, please check out <a href="%s" target="_blank" rel="noopener noreferrer">our doc</a>.', 'embed-pdf-wpforms' ),
										array(
											'a' => array(
												'href'   => array(),
												'target' => array(),
												'rel'    => array(),
											),
										)
									),
									esc_url( wpforms_utm_link( 'https://wpforms.com/docs/how-to-allow-multiple-selections-to-a-dropdown-field-in-wpforms/', 'Field Options', 'Multiple Options Selection Documentation' ) )
								),
				),
				false
			);

			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'multiple',
					'content' => $fld,
				)
			);

			// Style.
			$lbl = $this->field_element(
				'label',
				$field,
				array(
					'slug'    => 'style',
					'value'   => esc_html__( 'Style', 'embed-pdf-wpforms' ),
					'tooltip' => esc_html__( 'Classic style is the default one generated by your browser. Modern has a fresh look and displays all selected options in a single row.', 'embed-pdf-wpforms' ),
				),
				false
			);

			$fld = $this->field_element(
				'text', //'select',
				$field,
				array(
					'slug'    => 'style',
					'value'   => ! empty( $field['style'] ) ? $field['style'] : self::STYLE_CLASSIC,
					'options' => array(
						self::STYLE_CLASSIC => esc_html__( 'Classic', 'embed-pdf-wpforms' ),
						self::STYLE_MODERN  => esc_html__( 'Modern', 'embed-pdf-wpforms' ),
					),
				),
				false
			);

			$this->field_element(
				'row',
				$field,
				array(
					'slug'    => 'style',
					'content' => $lbl . $fld,
				)
			);

			// Size.
			$this->field_option( 'size', $field );

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

			$label = ! empty( $field['name'] ) ? $field['name'] : '';
			?>
			<label class="label-title">
				<div class="text"><?php echo esc_html( $label ); ?></div>
				<div class="grey"><i class="fa fa-code"></i> <?php esc_html_e( 'PDF Viewer', 'embed-pdf-wpforms' ); ?></div>
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

			// What is the PDF URL?
			// The user might have chosen a PDF and saved it with the form.
			//$url = $this->pdfUrl;
			//TODO: stop hard-coding the PDF URL
			$url = 'https://breakfastco.test/wp-content/uploads/vscode-keyboard-shortcuts-macos.pdf';
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$url = '';
			}
			//TODO allow dynamic population
			// // Do we have a PDF URL via Dynamic Population?
			// if ( ! empty( $value ) ) {
			// 	// Is the populated value a URL?
			// 	if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
			// 		// Yes.
			// 		$url = esc_url( $value );
			// 	}
			// }

			// // Do we even have a PDF?
			// if ( empty( $url ) ) {
			// 	// No.
			// 	// Are we on a feed settings page? This isn't a problem when configuring feeds in the pro version.
			// 	if ( 'form_settings_embedpdfviewerpro' !== GFForms::get_page() ) {
			// 		$this->log_error( sprintf( __( 'No PDF to load into field %1$s on form %2$s', 'embed-pdf-gravityforms' ), $this->id, $form['id'] ) );
			// 		return;
			// 	}
			// }

			// Define data.
			$primary       = $field['properties']['inputs']['primary'];
			$field_id      = $field['id'];
			$form_id       = $form_data['id'];
			$canvas_id     = $field_id . '_embed_pdf_wpforms';
			$initial_scale = '1';

			$canvas_controls = sprintf(
				'<div class="epgf-controls-container">'
					// Paging controls.
					. '<button class="wpforms-page-button button" onclick="return false" id="%1$s_prev" data-viewer-id="%7$s" title="%2$s">%2$s</button> <button class="wpforms-page-button button" onclick="return false" id="%1$s_next" data-viewer-id="%7$s" title="%3$s">%3$s</button> '
					. '<span class="paging">%4$s <span id="%1$s_page_num"></span> / <span id="%1$s_page_count"></span></span> '
					// Zoom controls.
					. '<span class="zoom"><button class="wpforms-page-button button" onclick="return false" id="%1$s_zoom_out" data-viewer-id="%7$s" title="%5$s">%5$s</button> <button class="wpforms-page-button button" onclick="return false" id="%1$s_zoom_in" data-viewer-id="%7$s" title="%6$s">%6$s</button></span>'
					. '</div>'
					. '<div class="epgf-container"><canvas id="%1$s" class="epgf"></canvas></div>',
				esc_attr( $canvas_id ),
				esc_html__( 'Previous', 'embed-pdf-gravityforms' ),
				esc_html__( 'Next', 'embed-pdf-gravityforms' ),
				esc_html__( 'Page:', 'embed-pdf-gravityforms' ),
				esc_html__( 'Zoom Out', 'embed-pdf-gravityforms' ),
				esc_html__( 'Zoom In', 'embed-pdf-gravityforms' ),
				esc_attr( $field_id )
			)
				. "<script type=\"text/javascript\">
			var epgf_{$field_id} = {
					canvas: document.getElementById('{$canvas_id}'),
					canvasId: '{$canvas_id}',
					initialScale: {$initial_scale} ?? epgf_pdfjs_strings.initialScale,
					pageNum: 1,
					pageNumPending: null,
					pageRendering: false,
					pdfDoc: null,
					urlPdf: '{$url}',
				};
				window.addEventListener( 'load', function () {
					document.getElementById('{$canvas_id}_prev').addEventListener('click', onPrevPage);
					document.getElementById('{$canvas_id}_next').addEventListener('click', onNextPage);
					document.getElementById('{$canvas_id}_zoom_in').addEventListener('click', onZoomIn);
					document.getElementById('{$canvas_id}_zoom_out').addEventListener('click', onZoomOut);
					loadPreview( {$field_id}, {$form_id} );
				});
			</script>";

			// Primary field.
			printf(
				'<div %s>%s</div>',
				wpforms_html_attributes( $primary['id'], $primary['class'], $primary['data'], $primary['attr'] ),
				$canvas_controls
			);
		}

		/**
		 * Format and sanitize field.
		 *
		 * @since 1.0.2
		 * @since 1.6.1 Added a support for multiple values.
		 *
		 * @param int          $field_id     Field ID.
		 * @param string|array $field_submit Submitted field value (selected option).
		 * @param array        $form_data    Form data and settings.
		 */
		public function format( $field_id, $field_submit, $form_data ) {

			$field    = $form_data['fields'][ $field_id ];
			$dynamic  = false;
			$multiple = ! empty( $field['multiple'] );
			$name     = sanitize_text_field( $field['label'] );
			$value    = array();

			// Convert submitted field value to array.
			if ( ! is_array( $field_submit ) ) {
				$field_submit = array( $field_submit );
			}

			$value_raw = wpforms_sanitize_array_combine( $field_submit );

			$data = array(
				'name'      => $name,
				'value'     => '',
				'value_raw' => $value_raw,
				'id'        => absint( $field_id ),
				'type'      => $this->type,
			);

			// Normal processing, dynamic population is off.

			// If show_values is true, that means values posted are the raw values
			// and not the labels. So we need to get the label values.
			if ( ! empty( $field['show_values'] ) && (int) $field['show_values'] === 1 ) {

				foreach ( $field_submit as $item ) {
					foreach ( $field['choices'] as $choice ) {
						if ( $item === $choice['value'] ) {
							$value[] = $choice['label'];

							break;
						}
					}
				}

				$data['value'] = ! empty( $value ) ? wpforms_sanitize_array_combine( $value ) : '';

			} else {
				$data['value'] = $value_raw;
			}

			// Backward compatibility: for single dropdown save a string, for multiple - array.
			if ( ! $multiple && is_array( $data ) && ( 1 === count( $data ) ) ) {
				$data = reset( $data );
			}

			// Push field details to be saved.
			wpforms()->process->fields[ $field_id ] = $data;
		}

		/**
		 * Enqueue assets for the builder.
		 *
		 * @param string|null $view Current view.
		 */
		public function enqueue_css_builder( $view = null ) {
			$min = wpforms_get_min_suffix();
			wp_enqueue_style(
				'wpforms-builder-embed-pdf',
				plugins_url( "css/editor{$min}.css", EMBED_PDF_WPFORMS_PATH ),
				array( 'wpforms-builder-fields' ),
				EMBED_PDF_WPFORMS_VERSION
			);
		}

		/**
		 * Form frontend CSS enqueues.
		 *
		 * @since 1.6.1
		 *
		 * @param array $forms Forms on the current page.
		 */
		public function enqueue_frontend_css( $forms ) {

			$has_modern_select = false;

			foreach ( $forms as $form ) {
				if ( $this->is_field_style( $form, self::STYLE_MODERN ) ) {
					$has_modern_select = true;

					break;
				}
			}

			if ( $has_modern_select || wpforms()->frontend->assets_global() ) {
				$min = wpforms_get_min_suffix();

				wp_enqueue_style(
					'wpforms-choicesjs',
					WPFORMS_PLUGIN_URL . "assets/css/choices{$min}.css",
					array(),
					self::CHOICES_VERSION
				);
			}
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
				$handle = 'epgf_pdfjs';
				wp_enqueue_script(
					$handle,
					plugins_url( 'js/pdfjs/pdf.min.js', EMBED_PDF_WPFORMS_PATH ), // No un-minimized version of this script included.
					array(),
					EMBED_PDF_WPFORMS_VERSION,
					true
				);
				wp_add_inline_script(
					$handle,
					'const epgf_pdfjs_strings = ' . json_encode(
						array(
							'url_worker'        => plugins_url( 'js/pdfjs/pdf.worker.min.js', EMBED_PDF_WPFORMS_PATH ), // No unminimized version of this script included.
							'initial_scale'     => self::DEFAULT_SCALE_VALUE,
							'is_user_logged_in' => is_user_logged_in(),
						)
					),
					'before'
				);

				$handle = 'epgf_pdf_viewer';
				$min    = wpforms_get_min_suffix();
				wp_enqueue_script(
					$handle,
					plugins_url( "js/field-pdf-viewer{$min}.js", EMBED_PDF_WPFORMS_PATH ),
					array( 'wp-i18n', 'epgf_pdfjs', 'jquery' ),
					EMBED_PDF_WPFORMS_VERSION,
					true
				);
				wp_add_inline_script(
					$handle,
					'const epgf_pdf_viewer_strings = ' . json_encode(
						array(
							'site_url' => site_url(),
						)
					),
					'before'
				);
			}
		}

		/**
		 * Load WPForms Gutenberg block scripts.
		 *
		 * @since 1.8.1
		 */
		public function enqueue_block_editor_assets() {

			$min = wpforms_get_min_suffix();

			wp_enqueue_style(
				'wpforms-choicesjs',
				WPFORMS_PLUGIN_URL . "assets/css/choices{$min}.css",
				array(),
				self::CHOICES_VERSION
			);

			$this->enqueue_choicesjs_once( array() );
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
