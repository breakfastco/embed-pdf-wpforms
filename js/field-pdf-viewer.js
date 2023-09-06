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
	// Is it a valid URL?
	if ( ! isValidHttpUrl( e.target.value ) ) {
		// No.
		const { __ } = wp.i18n;
		wpformsSetFieldError( e.target, __( 'Please enter a valid URL.', 'embed-pdf-wpforms' ) );

	// Is it a local URL?
	} else if ( epdf_wf_pdf_viewer_strings.site_url !== e.target.value.substring( 0, epdf_wf_pdf_viewer_strings.site_url.length ) ) {
		const { __ } = wp.i18n;
		const msg = __( 'Only PDFs hosted by this website and other websites listing this website in a CORS header ‘Access-Control-Allow-Origin’ can load in the viewer.', 'embed-pdf-wpforms' )
			+ ' <a href="https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS">' + __( 'Learn about CORS →', 'embed-pdf-wpforms' ) + '</a>';
		wpformsSetFieldError( e.target, msg );
	} else {
		wpformsResetFieldError( e.target );
	}
}//const event = WPFormsUtils.triggerEvent( $builder, 'wpformsBuilderReady' );

// Choose PDF button click handler in form editor & feed settings in pro
function handleChooseClick (e) {
	e.preventDefault();
	const { __ } = wp.i18n;
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

// Loading, paging, & zooming pdf.js viewers
window.addEventListener( 'load', function(e) {
	// The workerSrc property shall be specified.
	if ( 'undefined' !== typeof pdfjsLib ) {
		pdfjsLib.GlobalWorkerOptions.workerSrc = epdf_wf_pdfjs_strings.url_worker;
	}
});

/**
 * Get page info from document, resize canvas accordingly, and render page.
 * @param num Page number.
 */
function renderPage( epdfInstance, pageNum ) {
	epdfInstance.pageRendering = true;
	// Using promise to fetch the page
	epdfInstance.pdfDoc.getPage(pageNum).then(function(page) {

		var viewport = page.getViewport({scale: epdfInstance.pdfDoc.currentScaleValue});
		epdfInstance.canvas.height = viewport.height;
		epdfInstance.canvas.width = viewport.width;

		// Render PDF page into canvas context
		var renderContext = {
			canvasContext: epdfInstance.canvas.getContext('2d'),
			viewport: viewport
		};
		var renderTask = page.render(renderContext);

		// Wait for rendering to finish
		renderTask.promise.then(function() {
			epdfInstance.pageRendering = false;
			if (epdfInstance.pageNumPending !== null) {
				// New page rendering is pending
				renderPage(epdfInstance, epdfInstance.pageNumPending);
				epdfInstance.pageNumPending = null;
			}

			// Set the canvas width once or else zoom in and out break
			epdfInstance.canvas.style.width = '100%';
			epdfInstance.canvas.style.width = epdfInstance.canvas.width + 'px';

			// Dispatch an event after a page render.
			const event = new CustomEvent( 'epdf_render_page', { detail: epdfInstance.pageNum });
			window.dispatchEvent(event);
		});
	});

	// Update page counters
	document.getElementById( epdfInstance.canvasId + '_page_num').textContent = pageNum;
}
/**
 * If another page rendering in progress, waits until the rendering is
 * finised. Otherwise, executes rendering immediately.
 */
function queueRenderPage(epdfInstance) {
	if (epdfInstance.pageRendering) {
		epdfInstance.pageNumPending = epdfInstance.pageNum;
	} else {
		renderPage(epdfInstance,epdfInstance.pageNum);
	}
}
/**
 * Displays previous page.
 */
function onPrevPage(e) {
	var epdfInstance = window['epdf_' + e.target.dataset.viewerId];
	if (epdfInstance.pageNum <= 1) {
		return;
	}
	epdfInstance.pageNum--;
	queueRenderPage(epdfInstance);
	togglePrevNextButtons(epdfInstance);
}
/**
 * Displays next page.
 */
function onNextPage(e) {
	var epdfInstance = window['epdf_' + e.target.dataset.viewerId];
	if (epdfInstance.pageNum >= epdfInstance.pdfDoc.numPages) {
		return;
	}
	epdfInstance.pageNum++;
	queueRenderPage(epdfInstance);
	togglePrevNextButtons(epdfInstance);
}
function togglePrevNextButtons( epdfInstance ) {
	document.getElementById( epdfInstance.canvasId + '_prev').disabled = ( 1 == epdfInstance.pageNum );
	document.getElementById( epdfInstance.canvasId + '_next').disabled = ( epdfInstance.pageNum == epdfInstance.pdfDoc.numPages );
}
function scaleDeltaDefault() {
	return 1.1;
}
function scaleMin() {
	return 0.25;
}
function scaleMax() {
	return 10.0;
}

function onZoomIn(e) {
	var epdfInstance = window['epdf_' + e.target.dataset.viewerId];
	let newScale = epdfInstance.pdfDoc.currentScaleValue;
	newScale = (newScale * scaleDeltaDefault()).toFixed(2);
	newScale = Math.ceil(newScale * 10) / 10;
	newScale = Math.min(scaleMax(), newScale);
	epdfInstance.pdfDoc.currentScaleValue = newScale;
	renderPage(epdfInstance, epdfInstance.pageNum);

	// Dispatch an event about the new scale value.
	const event = new CustomEvent( 'epdf_scale_value', { detail: newScale });
	window.dispatchEvent(event);
}
function onZoomOut(e) {
	var epdfInstance = window['epdf_' + e.target.dataset.viewerId];
	let newScale = epdfInstance.pdfDoc.currentScaleValue;
	newScale = (newScale / scaleDeltaDefault()).toFixed(2);
	newScale = Math.floor(newScale * 10) / 10;
	newScale = Math.max(scaleMin(), newScale);
	epdfInstance.pdfDoc.currentScaleValue = newScale;
	renderPage(epdfInstance, epdfInstance.pageNum);

	// Dispatch an event about the new scale value.
	const event = new CustomEvent( 'epdf_scale_value', { detail: newScale });
	window.dispatchEvent(event);
}
function loadPreview( fieldId, formId ) {
	var epdfInstance = window['epdf_' + fieldId];
	var fieldElementId = 'field_' + formId + '_' + fieldId;
	if ( '' === epdfInstance.urlPdf ) {
		// There is no PDF to load.
		return;
	}
	/**
	 * Asynchronously downloads PDF.
	 */
	pdfjsLib.getDocument({ url: epdfInstance.urlPdf, verbosity: 0 }).promise.then(function(pdfDoc_) {
		if (epdfInstance.pdfDoc) {
			epdfInstance.pdfDoc.destroy();
		}
		epdfInstance.pdfDoc = pdfDoc_;
		document.getElementById( epdfInstance.canvasId + '_page_count').textContent = epdfInstance.pdfDoc.numPages;
		epdfInstance.pdfDoc.currentScaleValue = epdfInstance.initialScale;

		// Blow up the canvas to 100% width before rendering
		epdfInstance.canvas.style.width = '100%';

		// Initial/first page rendering
		renderPage(epdfInstance, epdfInstance.pageNum);

		// Disable the Previous or Next buttons depending on page count.
		togglePrevNextButtons(epdfInstance);
	}).catch(function(error){
		console.log(error);
		// Display an error on the front-end.

		const el = document.querySelector('#wpforms-' + formId + '-field_' + fieldId + '.wpforms-container-pdf-viewer');
		if ( el && error.message ) {
			const { __ } = wp.i18n;
			var msg = '<p><b>' + __( 'PDF Viewer Error:', 'embed-pdf-wpforms' ) + '</b> ' + error.message;
			if ( epdf_wf_pdfjs_strings.is_user_logged_in ) {
				msg += ' <a href="https://breakfastco.xyz/embed-pdf-for-wpforms/#troubleshooting">' + __( 'Troubleshooting →', 'embed-pdf-wpforms' ) + '</a>';
			}
			msg += '</p>';
			el.innerHTML += msg;
		}
		// Hide the broken controls.
		const controlEls = el.querySelectorAll( '.epdf-controls-container, .epdf-container' ).forEach( function( el ) { el.style.display ='none'; });
	});
}