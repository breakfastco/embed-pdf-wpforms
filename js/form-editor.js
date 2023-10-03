(function( epdfWf, undefined ) {
	jQuery( document ).ready( function(e){
		$builder = jQuery( '#wpforms-builder' );
		$builder.on( 'wpformsFieldAdd', function( event, id, type ) {
			if ( 'pdf_viewer' !== type ) {
				return;
			}
			addPdfViewerFieldHandlers( event );
		} );
		$builder.on( 'wpformsBuilderReady', addPdfViewerFieldHandlers );
	});

	const { __ } = wp.i18n;

	function abortPreviousFetch( e ) {
		controller.abort();
	}
	function addPdfViewerFieldHandlers( event ) {
		// Add a click handler to the Choose PDF buttons.
		var els = document.querySelectorAll( '.wpforms-field-option-row-pdf_url button' );
		if ( els ) {
			els.forEach( ( el ) => {
				el.removeEventListener( 'click', handleChooseClick );
				el.addEventListener( 'click', handleChooseClick );
			});
		}
		// Add input handlers to the URL fields.
		els = document.querySelectorAll( '.wpforms-field-option-row-pdf_url input.pdf-url');
		if ( els ) {
			els.forEach( ( el ) => {
				el.removeEventListener( 'input', abortPreviousFetch );
				el.addEventListener( 'input', abortPreviousFetch );
				el.removeEventListener( 'input', handleUrlInput );
				el.addEventListener( 'input', handleUrlInput );
				// Fire the events so errors show as soon as the field is selected.
				el.dispatchEvent(new Event('input'));
			})
		}
	}
	function wpformsSetFieldError( element, message ) {
		wpformsResetFieldError( element );
		// Add error CSS class to input so its border is red.
		element.classList.add( 'wpforms-error');
		// Add a message below the field telling the user about the problem.
		const error = document.createElement("p");
		error.classList.add( 'wpforms-alert-danger' );
		error.classList.add( 'wpforms-alert' );
		error.classList.add( 'wpforms-error-msg' );
		error.innerHTML = message;
		element.parentNode.insertBefore( error, element.nextSibling );
	}
	function wpformsResetFieldError( element ) {
		// Remove all errors for this field and the class making the fields border red.
		element.classList.remove( 'wpforms-error');
		document.querySelectorAll( '#' + element.parentNode.id + ' .wpforms-alert' ).forEach( ( el ) => el.remove() );
	}

	function handleUrlInput (e) {
		e.preventDefault();

		if ( '' === e.target.value ) {
			wpformsResetFieldError( e.target );
			return;
		}

		// Is it a valid URL?
		if ( ! isValidHttpUrl( e.target.value ) ) {
			// No.
			wpformsSetFieldError( e.target, __( 'Please enter a valid URL.', 'embed-pdf-wpforms' ) );

		// Is it a local URL?
		} else if ( epdf_wf_pdf_viewer_strings.site_url !== e.target.value.substring( 0, epdf_wf_pdf_viewer_strings.site_url.length ) ) {
			const msg = __( 'Only PDFs hosted by this website and other websites listing this website in a CORS header ‘Access-Control-Allow-Origin’ can load in the viewer.', 'embed-pdf-wpforms' )
				+ ' <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS">' + __( 'Learn about CORS →', 'embed-pdf-wpforms' ) + '</a>';
			wpformsSetFieldError( e.target, msg );
		}

		// Does the file exist?
		localFileExists( e.target.value ).then( exists => exists ? wpformsResetFieldError( e.target ) : wpformsSetFieldError( e.target, __( 'No file exists at the provided URL.', 'embed-pdf-wpforms' ) ) );
	}

	// Choose PDF button click handler in form editor
	function handleChooseClick (e) {
		e.preventDefault();
		var file_frame = wp.media.frames.file_frame = wp.media({
			title: __( 'Choose PDF', 'embed-pdf-wpforms' ),
			button: {
				text: __( 'Load', 'embed-pdf-wpforms' )
			},
			frame: 'select',
			multiple: false
		});

		// When an image is selected, run a callback.
		file_frame.on('select', function () {
			// Get one image from the uploader.
			var attachment = file_frame.state().get('selection').first().toJSON();
			var urlEl = document.getElementById( 'wpforms-field-option-' + e.target.dataset.fieldId + '-pdf_url' );
			if ( urlEl && attachment.url ) {
				urlEl.value = attachment.url;
				// Fire the input event so our listener runs.
				urlEl.dispatchEvent(new Event('input'));
			}
		});

		// Finally, open the modal
		file_frame.open();

		// Don't submit forms.
		return false;
	}

	function isValidHttpUrl(string) {
		let url;

		try {
			url = new URL(string);
		} catch (_) {
			return false;
		}

		return url.protocol === "http:" || url.protocol === "https:";
	}

	var controller = new AbortController();
	const localFileExists = file => {
		if ( epdf_wf_pdf_viewer_strings.site_url !== file.substring( 0, epdf_wf_pdf_viewer_strings.site_url.length ) ) {
			return Promise.resolve(false);
		}
		controller = new AbortController();
		const response = fetch(
			file,
			{
				method: 'HEAD',
				cache:'no-store',
				credentials: 'omit',
				signal: controller.signal,
			}
		).then(response => (
			200 === response.status && response.url === file
		))
		.catch( exception => false );
		return response;
	}
}( window.epdfWf = window.epdfWf || {} ));