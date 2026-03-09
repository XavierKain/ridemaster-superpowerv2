<?php
/**
 * Plugin Name: RideMaster UI Tweaks
 * Description: WooCommerce UI customizations and JetFormBuilder form styling for RideMaster.
 * Version: 1.0.6
 * Author: RideMaster
 * Text Domain: ridemaster-ui-tweaks
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =========================================================================
// 1. CUSTOM QUANTITY SELECTOR +/- (Single Product + Cart)
// =========================================================================

add_action( 'wp_head', function() {
	?>
	<style>
	/* ========================================
	   STYLES POUR SINGLE PRODUCT (complet)
	   ======================================== */

	.single-product .elementor-widget-wc-add-to-cart .quantity,
	.single-product .e-atc-qty-button-holder .quantity,
	.single-product div.quantity {
		display: flex !important;
		align-items: center !important;
		justify-content: space-between !important;
		background: #F8FAFC !important;
		border: solid 1px #e2e8f0;
		border-radius: 12px !important;
		padding: 12px 16px !important;
		width: 100% !important;
		max-width: 100% !important;
		margin: 0 0 16px 0 !important;
		overflow: visible !important;
	}

	.single-product .quantity .qty-label {
		font-family: 'DM Sans';
		font-size: 14px !important;
		font-weight: 400 !important;
		color: #475569 !important;
		flex: 1 1 auto !important;
		margin: 0 !important;
		padding: 0 !important;
	}

	.single-product .quantity .qty-controls {
		display: flex !important;
		align-items: center !important;
		gap: 8px !important;
		flex: 0 0 auto !important;
	}

	p.stock.in-stock {
		display: none;
	}

	.single-product .quantity input[type="number"]::-webkit-outer-spin-button,
	.single-product .quantity input[type="number"]::-webkit-inner-spin-button {
		-webkit-appearance: none !important;
		margin: 0 !important;
	}
	.single-product .quantity .screen-reader-text,
	.single-product .quantity label.screen-reader-text {
		display: none !important;
	}

	.single-product .elementor-widget-wc-add-to-cart .quantity input.qty,
	.single-product .e-atc-qty-button-holder .quantity input.qty,
	.single-product .quantity input.qty,
	.single-product .quantity input[type="number"] {
		-moz-appearance: textfield !important;
		width: 40px !important;
		min-width: 40px !important;
		max-width: 40px !important;
		flex: 0 0 40px !important;
		text-align: center !important;
		border: none !important;
		font-size: 18px !important;
		font-weight: 600 !important;
		padding: 0 !important;
		background: transparent !important;
		margin: 0 !important;
		height: auto !important;
		color: #333 !important;
		box-shadow: none !important;
	}

	.single-product .quantity input[type="number"]:focus {
		outline: none !important;
		box-shadow: none !important;
	}

	.single-product .elementor-widget-wc-add-to-cart .quantity .qty-btn,
	.single-product .e-atc-qty-button-holder .quantity .qty-btn,
	.single-product .quantity .qty-btn,
	.single-product .quantity button.qty-btn {
		width: 30px !important;
		height: 30px !important;
		min-width: 30px !important;
		min-height: 30px !important;
		max-width: 30px !important;
		max-height: 30px !important;
		flex: 0 0 30px !important;
		background: #fff !important;
		border: 1px solid #d0d0d0 !important;
		border-radius: 50% !important;
		font-size: 20px !important;
		font-weight: 400 !important;
		cursor: pointer !important;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		color: #333 !important;
		transition: all 0.2s !important;
		padding: 0 !important;
		margin: 0 !important;
		line-height: 1 !important;
		box-shadow: none !important;
	}

	.single-product .quantity .qty-btn:hover {
		background: #f0f0f0 !important;
		border-color: #bbb !important;
	}

	.single-product .quantity .qty-btn.minus {
		padding-bottom: 2px !important;
	}

	.single-product .quantity .qty-btn.plus {
		padding-bottom: 1px !important;
	}

	/* ========================================
	   STYLES POUR CART (simplifie)
	   ======================================== */

	.woocommerce-cart .quantity {
		display: flex !important;
		align-items: center !important;
		gap: 8px !important;
		background: transparent !important;
		border: none !important;
		padding: 0 !important;
		margin: 0 !important;
	}

	.woocommerce-cart .quantity input[type="number"]::-webkit-outer-spin-button,
	.woocommerce-cart .quantity input[type="number"]::-webkit-inner-spin-button {
		-webkit-appearance: none !important;
		margin: 0 !important;
	}

	.woocommerce-cart .quantity input.qty,
	.woocommerce-cart .quantity input[type="number"] {
		-moz-appearance: textfield !important;
		width: 20px !important;
		min-width: 20px !important;
		max-width: 40px !important;
		text-align: center !important;
		border: none !important;
		font-size: 16px !important;
		font-weight: 600 !important;
		padding: 0 !important;
		background: transparent !important;
		margin: 0 !important;
		height: auto !important;
		color: #333 !important;
		box-shadow: none !important;
	}

	.woocommerce-cart .quantity input[type="number"]:focus {
		outline: none !important;
		box-shadow: none !important;
	}

	.woocommerce-cart .quantity .qty-btn,
	.woocommerce-cart .quantity button.qty-btn {
		width: 30px !important;
		height: 30px !important;
		min-width: 30px !important;
		min-height: 30px !important;
		max-width: 30px !important;
		max-height: 30px !important;
		background: #fff !important;
		border: 1px solid #d0d0d0 !important;
		border-radius: 50% !important;
		font-size: 18px !important;
		font-weight: 400 !important;
		cursor: pointer !important;
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		color: #333 !important;
		transition: all 0.2s !important;
		padding: 0 !important;
		margin: 0 !important;
		line-height: 1 !important;
		box-shadow: none !important;
	}

	.woocommerce-cart .quantity .qty-btn:hover {
		background: #f0f0f0 !important;
		border-color: #bbb !important;
	}

	.woocommerce-cart .quantity .qty-btn.minus {
		padding-bottom: 2px !important;
	}

	.woocommerce-cart .quantity .qty-btn.plus {
		padding-bottom: 1px !important;
	}
	</style>
	<?php
} );

add_action( 'wp_footer', function() {
	?>
	<script>
	(function() {
		function initQuantityButtonsSingleProduct() {
			if (!document.body.classList.contains('single-product')) return;

			document.querySelectorAll('.quantity').forEach(function(quantityDiv) {
				if (quantityDiv.querySelector('.qty-label')) return;

				var input = quantityDiv.querySelector('input[type="number"], input.qty');
				if (!input) return;

				var inputValue = input.value;
				var inputMin = input.min || 1;
				var inputMax = input.max || 99;
				var inputName = input.name;
				var inputId = input.id;

				quantityDiv.innerHTML = '';

				var label = document.createElement('span');
				label.className = 'qty-label';
				label.textContent = 'Guests';

				var controls = document.createElement('div');
				controls.className = 'qty-controls';

				var minusBtn = document.createElement('button');
				minusBtn.type = 'button';
				minusBtn.className = 'qty-btn minus';
				minusBtn.innerHTML = "\u2212";

				var newInput = document.createElement('input');
				newInput.type = 'number';
				newInput.className = 'input-text qty text';
				newInput.name = inputName;
				newInput.id = inputId;
				newInput.value = inputValue;
				newInput.min = inputMin;
				newInput.max = inputMax;
				newInput.step = 1;

				var plusBtn = document.createElement('button');
				plusBtn.type = 'button';
				plusBtn.className = 'qty-btn plus';
				plusBtn.innerHTML = '+';

				controls.appendChild(minusBtn);
				controls.appendChild(newInput);
				controls.appendChild(plusBtn);

				quantityDiv.appendChild(label);
				quantityDiv.appendChild(controls);

				minusBtn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var val = parseInt(newInput.value) || parseInt(inputMin);
					if (val > parseInt(inputMin)) {
						newInput.value = val - 1;
						newInput.dispatchEvent(new Event('change', { bubbles: true }));
						newInput.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});

				plusBtn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var val = parseInt(newInput.value) || parseInt(inputMin);
					if (val < parseInt(inputMax)) {
						newInput.value = val + 1;
						newInput.dispatchEvent(new Event('change', { bubbles: true }));
						newInput.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});
			});
		}

		function initQuantityButtonsCart() {
			if (!document.body.classList.contains('woocommerce-cart')) return;

			document.querySelectorAll('.quantity').forEach(function(quantityDiv) {
				if (quantityDiv.querySelector('.qty-btn')) return;

				var input = quantityDiv.querySelector('input[type="number"], input.qty');
				if (!input) return;

				var inputMin = input.min || 1;
				var inputMax = input.max || 99;

				var minusBtn = document.createElement('button');
				minusBtn.type = 'button';
				minusBtn.className = 'qty-btn minus';
				minusBtn.innerHTML = "\u2212";

				var plusBtn = document.createElement('button');
				plusBtn.type = 'button';
				plusBtn.className = 'qty-btn plus';
				plusBtn.innerHTML = '+';

				quantityDiv.insertBefore(minusBtn, input);
				quantityDiv.appendChild(plusBtn);

				minusBtn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var val = parseInt(input.value) || parseInt(inputMin);
					if (val > parseInt(inputMin)) {
						input.value = val - 1;
						input.dispatchEvent(new Event('change', { bubbles: true }));
						input.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});

				plusBtn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var val = parseInt(input.value) || parseInt(inputMin);
					if (val < parseInt(inputMax)) {
						input.value = val + 1;
						input.dispatchEvent(new Event('change', { bubbles: true }));
						input.dispatchEvent(new Event('input', { bubbles: true }));
					}
				});
			});
		}

		function init() {
			initQuantityButtonsSingleProduct();
			initQuantityButtonsCart();
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', init);
		} else {
			init();
		}

		if (typeof jQuery !== 'undefined') {
			jQuery(document).on('updated_cart_totals', initQuantityButtonsCart);
			jQuery(document.body).on('updated_wc_div', initQuantityButtonsCart);
		}
	})();
	</script>
	<?php
} );

// =========================================================================
// 2. "BOOK NOW" BUTTON TEXT
// =========================================================================

add_filter( 'woocommerce_product_single_add_to_cart_text', function( $text ) {
	return '⚡ Book Now';
} );

add_filter( 'woocommerce_product_add_to_cart_text', function( $text ) {
	return '⚡ Book Now';
} );

// =========================================================================
// 3. HIDE WOOCOMMERCE INFO MESSAGES (cart + checkout)
// =========================================================================

add_action( 'wp_head', function() {
	?>
	<style>
	.woocommerce-cart .woocommerce-message,
	.woocommerce-cart .woocommerce-info,
	.woocommerce-checkout .woocommerce-message,
	.woocommerce-checkout .woocommerce-info {
		display: none !important;
	}
	</style>
	<?php
} );

// =========================================================================
// 4. CAMP CREATION FORM CSS (JetFormBuilder styling)
// =========================================================================

add_action( 'wp_head', function() {
	?>
	<style>
	/* --- FILE UPLOAD --- */
	#coach_cover_photo.jet-form-builder-file-upload__input,
	#coach_profile_photo.jet-form-builder-file-upload__input,
	#camp_thumbnail.jet-form-builder-file-upload__input,
	#camp_gallery.jet-form-builder-file-upload__input {
		position: absolute !important;
		width: 100% !important;
		height: 100% !important;
		top: 0 !important;
		left: 0 !important;
		opacity: 0 !important;
		cursor: pointer !important;
		z-index: 10 !important;
	}

	.jet-form-builder-file-upload__fields {
		position: relative !important;
		display: flex !important;
		flex-direction: column !important;
		align-items: center !important;
		justify-content: center !important;
		min-height: 140px !important;
		border: 2px dashed #d1d5db !important;
		border-radius: 12px !important;
		background: #f9fafb !important;
		padding: 24px !important;
		transition: all 0.25s ease !important;
		cursor: pointer !important;
	}

	.jet-form-builder-file-upload__fields:hover {
		border-color: #0D9488 !important;
		background: #f0fdfa !important;
	}

	.jet-form-builder-file-upload__fields::before {
		content: '' !important;
		display: block !important;
		width: 48px !important;
		height: 48px !important;
		margin-bottom: 12px !important;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='%230D9488'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5'/%3E%3C/svg%3E") !important;
		background-size: contain !important;
		background-repeat: no-repeat !important;
		pointer-events: none !important;
	}

	.jet-form-builder-file-upload__fields::after {
		content: 'Click or drag files here to upload' !important;
		display: block !important;
		font-size: 14px !important;
		color: #6b7280 !important;
		font-weight: 500 !important;
		pointer-events: none !important;
	}

	.jet-form-builder-file-upload__message {
		font-size: 12px !important;
		color: #9ca3af !important;
		margin-top: 8px !important;
		text-align: center !important;
	}

	.jet-form-builder-file-upload {
		position: relative !important;
	}

	/* --- REPEATER --- */
	.jet-form-builder-repeater__row {
		display: flex !important;
		align-items: flex-start !important;
		gap: 8px !important;
		margin-bottom: 8px !important;
		padding: 12px !important;
		background: #f9fafb !important;
		border: 1px solid #e5e7eb !important;
		border-radius: 10px !important;
		transition: border-color 0.2s ease !important;
	}

	.jet-form-builder-repeater__row:hover {
		border-color: #0D9488 !important;
	}

	.jet-form-builder-repeater__row-fields {
		flex: 1 !important;
		min-width: 0 !important;
	}

	.jet-form-builder-repeater__row-fields .jet-form-builder__label {
		display: none !important;
	}

	.jet-form-builder-repeater__row-fields .jet-form-builder__field-wrap {
		width: 100% !important;
	}

	.jet-form-builder-repeater__row-fields input[type="text"] {
		width: 100% !important;
		border: 1px solid #d1d5db !important;
		border-radius: 8px !important;
		padding: 10px 14px !important;
		font-size: 14px !important;
		transition: border-color 0.2s ease !important;
		background: #fff !important;
	}

	.jet-form-builder-repeater__row-fields input[type="text"]:focus {
		border-color: #0D9488 !important;
		outline: none !important;
		box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1) !important;
	}

	.jet-form-builder-repeater__remove {
		display: flex !important;
		align-items: center !important;
		justify-content: center !important;
		width: 34px !important;
		height: 34px !important;
		min-width: 34px !important;
		border: none !important;
		background: #fee2e2 !important;
		color: #dc2626 !important;
		border-radius: 50% !important;
		font-size: 18px !important;
		font-weight: 600 !important;
		cursor: pointer !important;
		transition: all 0.2s ease !important;
		padding: 0 !important;
		line-height: 1 !important;
		margin-top: 2px !important;
	}

	.jet-form-builder-repeater__remove:hover {
		background: #dc2626 !important;
		color: #fff !important;
		transform: scale(1.1) !important;
	}

	.jet-form-builder-repeater__row-remove {
		display: flex !important;
		align-items: flex-start !important;
		padding-top: 0 !important;
	}

	.jet-form-builder-repeater__new {
		display: inline-flex !important;
		align-items: center !important;
		gap: 6px !important;
		padding: 8px 18px !important;
		background: transparent !important;
		border: 2px dashed #0D9488 !important;
		color: #0D9488 !important;
		border-radius: 8px !important;
		font-size: 13px !important;
		font-weight: 600 !important;
		cursor: pointer !important;
		transition: all 0.2s ease !important;
		margin-top: 4px !important;
	}

	.jet-form-builder-repeater__new:hover {
		background: #f0fdfa !important;
		border-style: solid !important;
	}

	.jet-form-builder-repeater__new::before {
		content: '+' !important;
		font-size: 16px !important;
		font-weight: 700 !important;
		line-height: 1 !important;
	}

	.jet-form-builder-row.field-type-repeater-field {
		margin-bottom: 0 !important;
		align-items: center !important;
	}

	.jet-form-builder-row.field-type-repeater-field:has(.jet-form-builder-repeater__row) {
		align-items: flex-start !important;
	}

	.jet-form-builder-repeater__row .jet-form-builder-row {
		margin-bottom: 0 !important;
		padding: 0 !important;
	}

	.jet-form-builder-repeater__items {
		display: flex !important;
		flex-direction: column !important;
		gap: 0 !important;
	}

	/* --- CHECKBOXES --- */
	.jet-form-builder__field.checkboxes-field.checkradio-field {
		appearance: none !important;
		-webkit-appearance: none !important;
		width: 20px !important;
		height: 20px !important;
		min-width: 20px !important;
		border: 2px solid #d1d5db !important;
		border-radius: 5px !important;
		background: #fff !important;
		cursor: pointer !important;
		transition: all 0.2s ease !important;
		position: relative !important;
		margin: 0 !important;
		vertical-align: middle !important;
	}

	.jet-form-builder__field.checkboxes-field.checkradio-field:hover {
		border-color: #0D9488 !important;
	}

	.jet-form-builder__field.checkboxes-field.checkradio-field:checked {
		background: #0D9488 !important;
		border-color: #0D9488 !important;
	}

	.jet-form-builder__field.checkboxes-field.checkradio-field:checked::after {
		content: '' !important;
		position: absolute !important;
		left: 5px !important;
		top: 1px !important;
		width: 6px !important;
		height: 11px !important;
		border: solid #fff !important;
		border-width: 0 2.5px 2.5px 0 !important;
		transform: rotate(45deg) !important;
	}

	.jet-form-builder__field-label.for-checkbox {
		display: flex !important;
		align-items: center !important;
		gap: 8px !important;
		cursor: pointer !important;
		padding: 6px 12px !important;
		border-radius: 8px !important;
		transition: background 0.15s ease !important;
	}

	.jet-form-builder__field-label.for-checkbox:hover {
		background: #f0fdfa !important;
	}

	.jet-form-builder__fields-group.checkradio-wrap {
		display: grid !important;
		grid-template-columns: repeat(4, 1fr) !important;
		gap: 4px 0 !important;
	}

	/* --- DATE PICKER --- */
	.jet-form-builder,
	.jet-form-builder * {
		accent-color: #0D9488 !important;
	}

	input.date-field,
	input[type="date"].jet-form-builder__field {
		color-scheme: light !important;
	}

	input.date-field::-webkit-calendar-picker-indicator,
	input[type="date"].jet-form-builder__field::-webkit-calendar-picker-indicator {
		cursor: pointer !important;
		opacity: 0.7 !important;
		transition: opacity 0.2s ease !important;
		filter: invert(43%) sepia(72%) saturate(500%) hue-rotate(140deg) brightness(92%) contrast(92%) !important;
	}

	input.date-field::-webkit-calendar-picker-indicator:hover,
	input[type="date"].jet-form-builder__field::-webkit-calendar-picker-indicator:hover {
		opacity: 1 !important;
	}

	input.date-field:focus,
	input[type="date"].jet-form-builder__field:focus {
		border-color: #0D9488 !important;
		outline: none !important;
		box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.1) !important;
	}

	/* --- SELECT DROPDOWN --- */
	select.jet-form-builder__field.select-field {
		padding-right: 36px !important;
		-webkit-appearance: none !important;
		-moz-appearance: none !important;
		appearance: none !important;
		background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='%230D9488'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E") !important;
		background-repeat: no-repeat !important;
		background-position: right 14px center !important;
		background-size: 16px !important;
	}

	/* --- FLATPICKR CALENDAR --- */
	.flatpickr-calendar {
		border-radius: 12px !important;
		box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12) !important;
		border: 1px solid #e5e7eb !important;
		font-family: inherit !important;
	}

	.flatpickr-months .flatpickr-month {
		background: #0D9488 !important;
		color: #fff !important;
		border-radius: 12px 12px 0 0 !important;
	}

	.flatpickr-current-month .flatpickr-monthDropdown-months,
	.flatpickr-current-month input.cur-year {
		color: #fff !important;
		font-weight: 600 !important;
	}

	.flatpickr-current-month .flatpickr-monthDropdown-months:hover {
		background: rgba(255, 255, 255, 0.15) !important;
	}

	.flatpickr-months .flatpickr-prev-month,
	.flatpickr-months .flatpickr-next-month {
		color: #fff !important;
		fill: #fff !important;
	}

	.flatpickr-months .flatpickr-prev-month:hover,
	.flatpickr-months .flatpickr-next-month:hover {
		color: #ccfbf1 !important;
	}

	.flatpickr-months .flatpickr-prev-month svg,
	.flatpickr-months .flatpickr-next-month svg {
		fill: #fff !important;
	}

	.flatpickr-weekdays {
		background: #0D9488 !important;
	}

	span.flatpickr-weekday {
		color: rgba(255, 255, 255, 0.85) !important;
		font-weight: 600 !important;
		font-size: 12px !important;
		background: transparent !important;
	}

	.flatpickr-day {
		border-radius: 8px !important;
		color: #374151 !important;
		font-weight: 500 !important;
		transition: all 0.15s ease !important;
	}

	.flatpickr-day:hover {
		background: #f0fdfa !important;
		border-color: #0D9488 !important;
		color: #0D9488 !important;
	}

	.flatpickr-day.selected,
	.flatpickr-day.startRange,
	.flatpickr-day.endRange,
	.flatpickr-day.selected.inRange,
	.flatpickr-day.startRange.inRange,
	.flatpickr-day.endRange.inRange,
	.flatpickr-day.selected:focus,
	.flatpickr-day.startRange:focus,
	.flatpickr-day.endRange:focus,
	.flatpickr-day.selected:hover,
	.flatpickr-day.startRange:hover,
	.flatpickr-day.endRange:hover,
	.flatpickr-day.selected.prevMonthDay,
	.flatpickr-day.startRange.prevMonthDay,
	.flatpickr-day.endRange.prevMonthDay,
	.flatpickr-day.selected.nextMonthDay,
	.flatpickr-day.startRange.nextMonthDay,
	.flatpickr-day.endRange.nextMonthDay {
		background: #0D9488 !important;
		border-color: #0D9488 !important;
		color: #fff !important;
	}

	.flatpickr-day.today {
		border-color: #0D9488 !important;
		color: #0D9488 !important;
		font-weight: 700 !important;
	}

	.flatpickr-day.today:hover {
		background: #0D9488 !important;
		color: #fff !important;
	}

	.flatpickr-day.flatpickr-disabled,
	.flatpickr-day.prevMonthDay,
	.flatpickr-day.nextMonthDay {
		color: #d1d5db !important;
	}

	/* --- DESCRIPTIONS --- */
	.jet-form-builder__desc {
		font-size: 13px !important;
		color: #9ca3af !important;
		margin-top: 4px !important;
		line-height: 1.4 !important;
	}

	/* --- HIDE JFB PAPERCLIP ICON WHEN OUR IMAGE FIX IS ACTIVE --- */
	.jet-form-builder-file-upload[data-rm-fixed] .jet-form-builder-file-upload__file {
		background: none !important;
		border: none !important;
		padding: 0 !important;
	}

	.jet-form-builder-file-upload[data-rm-fixed] .jet-form-builder-file-upload__file::before,
	.jet-form-builder-file-upload[data-rm-fixed] .jet-form-builder-file-upload__file::after,
	.jet-form-builder-file-upload[data-rm-fixed] .jet-form-builder-file-upload__file svg {
		display: none !important;
	}

	/* --- FILE UPLOAD EMPTY STATE --- */
	.jet-form-builder-file-upload__content {
		min-height: 0 !important;
		min-width: 0 !important;
	}

	.jet-form-builder-file-upload__files:empty {
		display: none !important;
		height: 0 !important;
		overflow: hidden !important;
	}

	/* --- WYSIWYG --- */
	.mce-tinymce.mce-container {
		background: #F8FAFC !important;
		border: 1px solid #94A3B8 !important;
		border-radius: 8px !important;
		overflow: hidden !important;
	}

	.mce-edit-area.mce-container {
		background: #F8FAFC !important;
	}

	.mce-toolbar-grp {
		background: #F8FAFC !important;
		border-bottom: 1px solid #94A3B8 !important;
	}

	.mce-statusbar {
		background: #F8FAFC !important;
		border-top: 1px solid #94A3B8 !important;
	}

	.wp-editor-container {
		border: 0 !important;
	}
	</style>
	<?php
} );

// =========================================================================
// 5. FLATPICKR ON CREATE-CAMP PAGE
// =========================================================================

add_action( 'wp_enqueue_scripts', function() {
	if ( ! is_page( 'create-camp' ) && strpos( $_SERVER['REQUEST_URI'], 'create-camp' ) === false ) {
		return;
	}

	wp_enqueue_style(
		'flatpickr',
		'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
		[],
		'4.6.13'
	);
	wp_enqueue_script(
		'flatpickr',
		'https://cdn.jsdelivr.net/npm/flatpickr',
		[],
		'4.6.13',
		true
	);

	wp_add_inline_script( 'flatpickr', "
		document.addEventListener('DOMContentLoaded', function() {
			var dateFields = document.querySelectorAll('input.date-field');
			dateFields.forEach(function(input) {
				input.type = 'text';
				input.placeholder = 'yyyy-mm-dd';
				flatpickr(input, {
					dateFormat: 'Y-m-d',
					allowInput: true,
					disableMobile: true
				});
			});
		});
	" );
} );

// =========================================================================
// 6. JFB FILE UPLOAD PREVIEW FIX
// =========================================================================

add_action( 'wp_footer', function() {
	if ( ! is_page( 'coach-register' )
		&& strpos( $_SERVER['REQUEST_URI'], '/coach-dashboard/' ) === false ) {
		return;
	}
	?>
	<script>
	(function() {
		function log() {}

		/* --- Fix 1: Preserve preview when user cancels file dialog --- */
		function initCancelProtection() {
			document.querySelectorAll('.jet-form-builder-file-upload').forEach(function(wrapper) {
				if (wrapper.dataset.rmCancelFix) return;
				wrapper.dataset.rmCancelFix = '1';

				var input = wrapper.querySelector('input[type="file"]');
				if (!input) return;

				var previewContainer = wrapper.querySelector('.jet-form-builder-file-upload__content');
				var savedPreviewHTML = '';

				input.addEventListener('click', function() {
					if (previewContainer) {
						savedPreviewHTML = previewContainer.innerHTML;
					}
				});

				input.addEventListener('change', function() {
					if (!input.files || input.files.length === 0) {
						if (previewContainer && savedPreviewHTML) {
							previewContainer.innerHTML = savedPreviewHTML;
							previewContainer.style.display = '';
						}
					}
				});
			});
		}

		/**
		 * Fix 2: Replace generic file icons (SVG paperclip) with real image thumbnails.
		 *
		 * JFB file upload preset stores value as JSON: {"url":false,"id":"https://...jpg"}
		 * and renders an SVG icon instead of an <img>. We parse the JSON, extract the
		 * image URL, and create a real <img> element to replace the icon.
		 */
		function fixGenericIcons() {
			var uploaders = document.querySelectorAll('.jet-form-builder-file-upload');
			log('Found', uploaders.length, 'file upload wrappers');

			uploaders.forEach(function(wrapper, idx) {
				if (wrapper.dataset.rmFixed) return;

				var fileEls = wrapper.querySelectorAll('.jet-form-builder-file-upload__file');
				var hiddenInput = wrapper.querySelector('input[type="hidden"]');
				var hiddenVal = hiddenInput ? hiddenInput.value : '';

				log('Wrapper #' + idx + ': fileElements=' + fileEls.length + ', hidden=' + hiddenVal.substring(0, 120));

				/* Extract image URL from hidden input value */
				var imageUrl = null;

				/*
				 * Profile photo fix: JFB doesn't preset the file upload field for
				 * Post Thumbnail mapping. The RideMaster plugin injects the URL as
				 * window.rmCoachProfilePhotoUrl. Use it for the first empty wrapper.
				 */
				if (!hiddenVal && fileEls.length === 0 && window.rmCoachProfilePhotoUrl && idx === 0) {
					imageUrl = window.rmCoachProfilePhotoUrl;
					log('  Using injected profile photo URL:', imageUrl);

					/* Create the file element structure that JFB would normally render */
					var content = wrapper.querySelector('.jet-form-builder-file-upload__content');
					if (!content) {
						content = wrapper.querySelector('.jet-form-builder-file-upload__fields');
					}
					if (content) {
						wrapper.dataset.rmFixed = '1';
						var fileDiv = document.createElement('div');
						fileDiv.className = 'jet-form-builder-file-upload__file';
						var img = document.createElement('img');
						img.src = imageUrl;
						img.style.width = '100%';
						img.style.height = '80px';
						img.style.objectFit = 'cover';
						img.style.borderRadius = '6px';
						img.style.display = 'block';
						fileDiv.appendChild(img);
						content.insertBefore(fileDiv, content.firstChild);
						log('  Injected profile photo image into wrapper #' + idx);
					}
					return;
				}

				if (hiddenVal) {
					/* Try parsing as JSON first (JFB format: {"url":...,"id":"https://..."}) */
					try {
						var parsed = JSON.parse(hiddenVal);
						log('  Parsed JSON:', parsed);
						if (parsed) {
							/* JFB stores URL in "id" field (confusing but true) */
							if (typeof parsed.id === 'string' && parsed.id.indexOf('http') === 0) {
								imageUrl = parsed.id;
							} else if (typeof parsed.url === 'string' && parsed.url.indexOf('http') === 0) {
								imageUrl = parsed.url;
							} else if (typeof parsed === 'string' && parsed.indexOf('http') === 0) {
								imageUrl = parsed;
							}
						}
					} catch(e) {
						/* Not JSON — try as plain value */
						var val = hiddenVal.trim();
						var numId = parseInt(val);
						if (numId && !isNaN(numId)) {
							/* Numeric attachment ID — fetch from REST */
							log('  Numeric ID detected:', numId);
							(function(w, fEls, aId) {
								fetch('/wp-json/wp/v2/media/' + aId)
									.then(function(r) { return r.json(); })
									.then(function(data) {
										if (!data) return;
										var thumbUrl = data.source_url || '';
										if (data.media_details && data.media_details.sizes) {
											var sz = data.media_details.sizes;
											var pick = sz.medium || sz.thumbnail || sz.full;
											if (pick) thumbUrl = pick.source_url;
										}
										if (thumbUrl) {
											log('  REST resolved URL:', thumbUrl);
											replaceWithImage(w, fEls, thumbUrl);
										}
									})
									.catch(function(err) { log('  Fetch error:', err); });
							})(wrapper, fileEls, numId);
							return;
						} else if (val.indexOf('http') === 0) {
							imageUrl = val;
						}
					}
				}

				if (!imageUrl) {
					log('  No image URL found for wrapper #' + idx);
					return;
				}

				log('  Image URL:', imageUrl);
				replaceWithImage(wrapper, fileEls, imageUrl);
			});
		}

		/**
		 * Replace the file element content (SVG icon) with a real <img>.
		 */
		function replaceWithImage(wrapper, fileEls, imageUrl) {
			if (wrapper.dataset.rmFixed) return;
			wrapper.dataset.rmFixed = '1';

			fileEls.forEach(function(fileEl) {
				/* Check if there's already a working <img> */
				var existingImg = fileEl.querySelector('img');
				if (existingImg && existingImg.naturalWidth > 50) return;

				log('  Replacing file element content with image');

				/* Clear the file element (remove SVG/paperclip icon) */
				fileEl.innerHTML = '';

				/* Create and insert a real <img> */
				var img = document.createElement('img');
				img.src = imageUrl;
				img.style.width = '100%';
				img.style.height = '80px';
				img.style.objectFit = 'cover';
				img.style.borderRadius = '6px';
				img.style.display = 'block';
				fileEl.appendChild(img);
			});
		}

		function runAllFixes() {
			log('runAllFixes called');
			initCancelProtection();
			fixGenericIcons();
		}

		/* Run immediately */
		runAllFixes();

		/* Run again on DOMContentLoaded */
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function() {
				log('DOMContentLoaded fired');
				runAllFixes();
			});
		}

		/* Run with delays for late-rendering JS widgets */
		setTimeout(function() { log('setTimeout 500ms'); runAllFixes(); }, 500);
		setTimeout(function() { log('setTimeout 1500ms'); runAllFixes(); }, 1500);
		setTimeout(function() { log('setTimeout 3000ms'); runAllFixes(); }, 3000);

		/* MutationObserver */
		var debounceTimer = null;
		var observer = new MutationObserver(function() {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(runAllFixes, 200);
		});
		var target = document.body || document.documentElement;
		if (target) {
			observer.observe(target, { childList: true, subtree: true });
		}
	})();
	</script>
	<?php
} );
