( function () {
	'use strict';

	function setValue( id, value ) {
		var field = document.getElementById( id );

		if ( field ) {
			field.value = value || '';
		}
	}

	function getDetails( option ) {
		return [
			option.dataset.trimVersion ? 'Trim: ' + option.dataset.trimVersion : '',
			option.dataset.bodyType ? 'Body: ' + option.dataset.bodyType : '',
			option.dataset.fuelType ? 'Fuel: ' + option.dataset.fuelType : '',
			option.dataset.transmission ? 'Transmission: ' + option.dataset.transmission : '',
			option.dataset.engine ? 'Engine: ' + option.dataset.engine : '',
		].filter( Boolean );
	}

	function updateCatalogPreview( option ) {
		var preview = document.getElementById( 'sm-vehicle-catalog-preview' );
		var details;

		if ( ! preview ) {
			return;
		}

		details = option ? getDetails( option ) : [];

		if ( ! details.length ) {
			preview.hidden = true;
			preview.textContent = '';
			return;
		}

		preview.hidden = false;
		preview.textContent = details.join( ' | ' );
	}

	function applyCatalogSelection( select ) {
		var option = select.options[ select.selectedIndex ];

		if ( ! option || '0' === option.value ) {
			updateCatalogPreview( null );
			return;
		}

		setValue( 'brand', option.dataset.brand );
		setValue( 'model', option.dataset.model );
		setValue( 'year', option.dataset.year );
		setValue( 'trim_version', option.dataset.trimVersion );
		setValue( 'body_type', option.dataset.bodyType );
		setValue( 'fuel_type', option.dataset.fuelType );
		setValue( 'transmission', option.dataset.transmission );
		setValue( 'engine', option.dataset.engine );
		updateCatalogPreview( option );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var select = document.querySelector( '[data-sm-vehicle-catalog]' );

		if ( ! select ) {
			return;
		}

		select.addEventListener( 'change', function () {
			applyCatalogSelection( select );
		} );

		updateCatalogPreview( select.options[ select.selectedIndex ] );
	} );
}() );
