(function( $ ) {
    'use strict';
	
	$(function () {
		/**
		 * Single Accordion Custom Control
		 *
		 * @author Anthony Hortin <http://maddisondesigns.com>
		 * @license http://www.gnu.org/licenses/gpl-2.0.html
		 * @link https://github.com/maddisondesigns
		 */

		$('.single-accordion-toggle').on('click', function() {
			var $accordionToggle = $(this);
			$(this).parent().find('.single-accordion').slideToggle('slow', function() {
				$accordionToggle.toggleClass('single-accordion-toggle-rotate', $(this).is(':visible'));
			});
		});
	});
})( jQuery );