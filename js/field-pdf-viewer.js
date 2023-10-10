(function( epdfWf, undefined ) {
	
	// Loading, paging, & zooming pdf.js viewers
	window.addEventListener( 'load', function(e) {
		// The workerSrc property shall be specified.
		if ( 'undefined' !== typeof pdfjsLib ) {
			pdfjsLib.GlobalWorkerOptions.workerSrc = epdf_wf_pdfjs_strings.url_worker;
			// Dispatch an event after pdfjs is ready.
			const event = new CustomEvent( 'epdf_wf_pdfjs_worker_set' );
			window.dispatchEvent(event);
		}
	});

	// Spin up script. Initializes all the viewers.
	window.addEventListener( 'epdf_wf_pdfjs_worker_set', function(e) {
		document.querySelectorAll( '.epdf-container canvas.epdf' ).forEach( function( el ) {
			loadPreview( el.dataset.field, el.dataset.form );
		});
	});

	/**
	 * Private Members
	 */
	var pdfDocs = [];

	/**
	 * Private Methods
	 */

	function canvasElement( fieldId, formId ) {
		return document.querySelector( '.epdf-container canvas.epdf#wpforms-' + formId + '-canvas_' + fieldId );
	}

	function loadPreview( fieldId, formId ) {
		var epdfInstance = canvasElement( fieldId, formId );
		if ( 'undefined' === typeof epdfInstance ) {
			// Something is wrong, spin up data for this this preview is missing.
			if ( epdf_wf_pdf_viewer_strings.script_debug ) {
				console.error( '[Embed PDF for WPForms] loadPreview( ' + fieldId + ' ) failed, spin up data missing' );
			}
			return;
		}

		var urlEl = document.getElementById( 'wpforms-' + formId + '-field_' + fieldId );
		if ( null === urlEl || '' === urlEl.value ) {
			// There is no PDF to load.
			if ( epdf_wf_pdf_viewer_strings.script_debug ) {
				console.error( '[Embed PDF for WPForms] loadPreview( ' + fieldId + ' ) failed, no PDF URL' );
			}
			return;
		}

		const controls = {
			'prev': 'onPrevPage',
			'next': 'onNextPage',
			'zoom_in': 'onZoomIn',
			'zoom_out': 'onZoomOut'
		};
		Object.keys(controls).forEach(function(key, index){
			var el = document.getElementById( epdfInstance.id + '_' + key);
			if ( el ) {
				el.addEventListener('click', epdfWf[controls[key]]);
			}
		});

		/**
		 * Asynchronously downloads PDF.
		 */
		pdfjsLib.getDocument({ url: urlEl.value, verbosity: 0 }).promise.then(function(pdfDoc_) {
			if (pdfDocs[fieldId]) {
				pdfDocs[fieldId].destroy();
			}
			pdfDocs[fieldId] = pdfDoc_;
			document.getElementById( epdfInstance.id + '_page_count').textContent = pdfDocs[fieldId].numPages;
			pdfDocs[fieldId].currentScaleValue = epdfInstance.dataset.initialScale;

			// Blow up the canvas to 100% width before rendering
			epdfInstance.style.width = '100%';

			// Initial/first page rendering
			renderPage(epdfInstance, Number(epdfInstance.dataset.pageNum));

			// Disable the Previous or Next buttons depending on page count.
			togglePrevNextButtons(epdfInstance);
		}).catch(function(error){
			if ( epdf_wf_pdf_viewer_strings.script_debug ) {
				console.error( '[Embed PDF for WPForms] Preview failed.' );
				console.error( error );
			}
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

	/**
	 * If another page rendering in progress, waits until the rendering is
	 * finised. Otherwise, executes rendering immediately.
	 */
	function queueRenderPage(epdfInstance) {
		if ('true' === epdfInstance.dataset.pageRendering) {
			epdfInstance.dataset.pageNumPending = epdfInstance.dataset.pageNum;
		} else {
			renderPage(epdfInstance, Number(epdfInstance.dataset.pageNum));
		}
	}

	/**
	 * Get page info from document, resize canvas accordingly, and render page.
	 * @param num Page number.
	 */
	function renderPage( epdfInstance, pageNum ) {
		if ( isNaN( Number(pageNum) ) ) {
			pageNum = 1;
		}

		epdfInstance.dataset.pageRendering = true;
		// Using promise to fetch the page
		pdfDocs[epdfInstance.dataset.field].getPage(pageNum).then(function(page) {

			var viewport = page.getViewport({scale: pdfDocs[epdfInstance.dataset.field].currentScaleValue});
			epdfInstance.height = viewport.height;
			epdfInstance.width = viewport.width;

			// Render PDF page into canvas context
			var renderContext = {
				canvasContext: epdfInstance.getContext('2d'),
				viewport: viewport
			};
			var renderTask = page.render(renderContext);

			// Wait for rendering to finish
			renderTask.promise.then(function() {
				epdfInstance.dataset.pageRendering = false;
				if (epdfInstance.dataset.pageNumPending !== '') {
					// New page rendering is pending
					renderPage(epdfInstance, Number(epdfInstance.dataset.pageNumPending));
					epdfInstance.dataset.pageNumPending = '';
				}

				// Set the canvas width once or else zoom in and out break
				epdfInstance.style.width = '100%';
				epdfInstance.style.width = epdfInstance.width + 'px';

				// Dispatch an event after a page render.
				const event = new CustomEvent( 'epdf_wf_render_page', { detail: epdfInstance.dataset.pageNum });
				window.dispatchEvent(event);
			});
		});

		// Update page counters
		document.getElementById( epdfInstance.id + '_page_num').textContent = pageNum;
	}

	function scaleDeltaDefault() {
		return 1.1;
	}

	function scaleMax() {
		return 10.0;
	}

	function scaleMin() {
		return 0.25;
	}

	function togglePrevNextButtons( epdfInstance ) {
		document.getElementById( epdfInstance.id + '_prev').disabled = ( 1 == epdfInstance.dataset.pageNum );
		document.getElementById( epdfInstance.id + '_next').disabled = ( epdfInstance.dataset.pageNum == pdfDocs[epdfInstance.dataset.field].numPages );
	}

	/**
	 * Public Methods
	 */

	/**
	 * Handler for the Previous Page button.
	 * 
	 * @param {*} e 
	 * @returns 
	 */
	epdfWf.onPrevPage = function(e) {
		var epdfInstance = canvasElement( e.target.dataset.field, e.target.dataset.form );
		if (Number(epdfInstance.dataset.pageNum) <= 1) {
			return;
		}
		epdfInstance.dataset.pageNum--;
		queueRenderPage(epdfInstance);
		togglePrevNextButtons(epdfInstance);
	}

	/**
	 * Handler for the Next Page button.
	 * 
	 * @param {*} e 
	 * @returns 
	 */
	epdfWf.onNextPage = function(e) {
		var epdfInstance = canvasElement( e.target.dataset.field, e.target.dataset.form );
		if (Number(epdfInstance.dataset.pageNum) >= pdfDocs[e.target.dataset.field].numPages) {
			return;
		}
		epdfInstance.dataset.pageNum++;
		queueRenderPage(epdfInstance);
		togglePrevNextButtons(epdfInstance);
	}

	/**
	 * Handler for the Zoom In button.
	 * 
	 * @param {*} e 
	 * @returns 
	 */
	epdfWf.onZoomIn = function(e) {
		var epdfInstance = canvasElement( e.target.dataset.field, e.target.dataset.form );
		let newScale = pdfDocs[e.target.dataset.field].currentScaleValue;
		newScale = (newScale * scaleDeltaDefault()).toFixed(2);
		newScale = Math.ceil(newScale * 10) / 10;
		newScale = Math.min(scaleMax(), newScale);
		pdfDocs[e.target.dataset.field].currentScaleValue = newScale;
		renderPage(epdfInstance, Number(epdfInstance.dataset.pageNum));

		// Dispatch an event about the new scale value.
		const event = new CustomEvent( 'epdf_wf_scale_value', { detail: newScale });
		window.dispatchEvent(event);
	}

	/**
	 * Handler for the Zoom Out button.
	 * 
	 * @param {*} e 
	 * @returns 
	 */
	epdfWf.onZoomOut = function(e) {
		var epdfInstance = canvasElement( e.target.dataset.field, e.target.dataset.form );
		let newScale = pdfDocs[e.target.dataset.field].currentScaleValue;
		newScale = (newScale / scaleDeltaDefault()).toFixed(2);
		newScale = Math.floor(newScale * 10) / 10;
		newScale = Math.max(scaleMin(), newScale);
		pdfDocs[e.target.dataset.field].currentScaleValue = newScale;
		renderPage(epdfInstance, Number(epdfInstance.dataset.pageNum));

		// Dispatch an event about the new scale value.
		const event = new CustomEvent( 'epdf_scale_value', { detail: newScale });
		window.dispatchEvent(event);
	}

}( window.epdfWf = window.epdfWf || {} ));
