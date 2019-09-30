/* global resourceImportData */

const resourceImport = {
	complete: {
		posts: 0,
		terms: 0,
	},

	/**
	 * Update the delta for a given type.
	 *
	 * @param {string} type
	 * @param {integer} delta
	 */
	updateDelta: function ( type, delta ) {
		this.complete[ type ] += delta;

		const self = this;
		requestAnimationFrame( function () {
			self.render();
		} );
	},

	/**
	 * Update the progress bar for a given type.
	 *
	 * @param {string} type
	 * @param {integer} complete
	 * @param {integer} total
	 */
	updateProgress: function ( type, complete, total ) {
		const text = `${complete  }/${  total}`;
		document.getElementById( `completed-${  type}` ).innerHTML = text;
		total = parseInt( total, 10 );
		if ( 0 === total || isNaN( total ) ) {
			total = 1;
		}
		const percent = parseInt( complete, 10 ) / total;
		document.getElementById( `progress-${  type}` ).innerHTML = `${Math.round( percent * 100 )  }%`;
		document.getElementById( `progressbar-${  type}` ).value = percent * 100;
	},

	/**
	 * Render the progress.
	 */
	render: function () {
		const types = Object.keys( this.complete );
		let complete = 0;
		let total = 0;

		for ( let i = types.length - 1; 0 <= i; i-- ) {
			const type = types[i];
			this.updateProgress( type, this.complete[ type ], this.data.count[ type ] );

			complete += this.complete[ type ];
			total += this.data.count[ type ];
		}

		this.updateProgress( 'total', complete, total );
	}
};
resourceImport.data = resourceImportData;
resourceImport.render();

const evtSource = new EventSource( resourceImport.data.url );
evtSource.onmessage = function ( message ) {
	const data = JSON.parse( message.data );
	const importStatusMsg = jQuery( '#import-status-message' );

	switch ( data.action ) {
			case 'updateDelta':
				resourceImport.updateDelta( data.type, data.delta );
				break;

			case 'complete':
				evtSource.close();
				importStatusMsg.text( resourceImport.data.strings.complete );
				importStatusMsg.removeClass( 'notice-info' );
				importStatusMsg.addClass( 'notice-success' );
				break;
	}
};
evtSource.addEventListener( 'log', function ( message ) {
	const data = JSON.parse( message.data );
	const row = document.createElement( 'tr' );
	const levelElement = document.createElement( 'td' );
	const icon = document.createElement( 'span' );
	const level = document.createElement( 'span' );
	level.setAttribute( 'data-level', data.level );
	level.classList.add( 'screen-reader-text' );
	level.appendChild( document.createTextNode( data.level ) );
	switch ( data.level ) {
			case 'info':
				icon.setAttribute( 'class', 'dashicons dashicons-yes-alt' );
				break;
			case 'warning':
				icon.setAttribute( 'class', 'dashicons dashicons-warning' );
				break;
			case 'error':
				icon.setAttribute( 'class', 'dashicons dashicons-dismiss' );
				break;
			default:
				icon.setAttribute( 'class', 'dashicons dashicons-info' );
				break;
	}
	levelElement.appendChild( icon );
	levelElement.appendChild( level );
	row.appendChild( levelElement );


	const messageElement = document.createElement( 'td' );
	messageElement.appendChild( document.createTextNode( data.message ) );
	row.appendChild( messageElement );

	jQuery( '#import-log' ).append( row );
} );
