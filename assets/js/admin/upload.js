/* eslint-disable camelcase */

jQuery( document ).ready( function( $ ) {
	const options = importUploadSettings;
	let uploader, statusTemplate, errorTemplate;

	/**
	 * Progress and success handlers for media multi uploads
	 */
	const renderStatus = function ( attachment ) {
		const attr = attachment.attributes;
		const $status = $.parseHTML( statusTemplate( attr ).trim() );

		$( '.bar', $status ).width( ( 200 * attr.loaded ) / attr.size );
		$( '.percent', $status ).html( `${attr.percent  }%` );

		$( '.drag-drop-status' ).empty().append( $status );
	};

	/**
	 * Render an error.
	 *
	 * @param {string} message
	 */
	const renderError = function ( message ) {
		const data = {
			message: message,
		};

		const status = errorTemplate( data );
		const $status = $( '.drag-drop-status' );
		$status.html( status );
		$status.one( 'click', 'button', function () {
			$status.empty().hide();
			$( '.drag-drop-selector' ).show();
		} );
	};

	const actions = {
		/**
		 * Init function.
		 */
		init: function () {
			const uploaddiv = $( '#plupload-upload-ui' );

			if ( uploader.supports.dragdrop ) {
				uploaddiv.addClass( 'drag-drop' );
			} else {
				uploaddiv.removeClass( 'drag-drop' );
			}
		},

		/**
		 * Added function.
		 *
		 * @param {} attachment
		 */
		added: function ( attachment ) {
			$( '.drag-drop-selector' ).hide();
			$( '.drag-drop-status' ).show();

			renderStatus( attachment );
		},

		/**
		 * Progress.
		 *
		 * @param {} attachment
		 */
		progress: function ( attachment ) {
			renderStatus( attachment );
		},

		/**
		 * Success.
		 *
		 * @param {} attachment
		 */
		success: function ( attachment ) {
			$( '#import-selected-id' ).val( attachment.id );

			renderStatus( attachment );
		},

		/**
		 * Error.
		 *
		 * @param {} message
		 */
		error: function ( message ) {
			renderError( message );
		},
	};

	/**
	 * init and set the uploader
	 */
	const init = function() {
		const isIE = -1 != navigator.userAgent.indexOf( 'Trident/' ) || -1 != navigator.userAgent.indexOf( 'MSIE ' );

		// Make sure flash sends cookies (seems in IE it does whitout switching to urlstream mode)
		if ( ! isIE && 'flash' === plupload.predictRuntime( options ) &&
			( ! options.required_features || ! Object.prototype.hasOwnProperty.call( options.required_features, 'send_binary_string' ) ) ) {

			options.required_features = options.required_features || {};
			options.required_features.send_binary_string = true;
		}

		const instanceOptions = _.extend( {}, options, actions );
		instanceOptions.browser = $( '#plupload-browse-button' );
		instanceOptions.dropzone = $( '#plupload-upload-ui' );

		uploader = new wp.Uploader( instanceOptions );
	};

	$( document ).ready( function() {
		statusTemplate = wp.template( 'import-upload-status' );
		errorTemplate = wp.template( 'import-upload-error' );

		init();

		// Create the media frame.
		const frame = wp.media( {
			id: 'import-select',
			// Set the title of the modal.
			title: options.l10n.frameTitle,
			multiple: true,

			// Tell the modal to show only xml files.
			library: {
				type: '',
				status: 'private',
			},

			// Customize the submit button.
			button: {
				// Set the text of the button.
				text: options.l10n.buttonText,
				// Tell the button not to close the modal, since we're
				// going to refresh the page when the image is selected.
				close: false,
			},
		} );
		$( '.upload-select' ).on( 'click', function ( event ) {
			event.preventDefault();

			frame.open();
		} );
		frame.on( 'select', function () {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			const $input = $( '#import-selected-id' );
			$input.val( attachment.id );
			$input.parents( 'form' )[0].submit();
		} );
	} );
} );
