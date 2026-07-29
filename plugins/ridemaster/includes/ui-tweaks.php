<?php
/**
 * RideMaster UI Tweaks
 * WooCommerce UI customizations, JetFormBuilder form styling, and camp card menus.
 * Merged into main RideMaster plugin as of v2.0.0.
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
	$qty_label = apply_filters( 'wpml_current_language', null ) === 'fr' ? 'personnes' : 'Guests';
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
				label.textContent = <?php echo wp_json_encode( $qty_label ); ?>;

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
	return apply_filters( 'wpml_current_language', null ) === 'fr' ? '⚡ Réserver' : '⚡ Book Now';
} );

add_filter( 'woocommerce_product_add_to_cart_text', function( $text ) {
	return apply_filters( 'wpml_current_language', null ) === 'fr' ? '⚡ Réserver' : '⚡ Book Now';
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
// 3b. ACCOMMODATION SECTION VISIBILITY (global — applies to all visitors)
// Lives here (not in inline-edit.css) because the inline-edit asset is only
// enqueued for the authenticated camp owner.
// =========================================================================

add_action( 'wp_head', function() {
	?>
	<style>
	/* Empty placeholder is hidden by default everywhere. Shown only when coach
	   is editing AND no hotel is linked (see override below). */
	.rm-accommodation-empty {
		display: none !important;
	}

	/* Show the empty placeholder ONLY for the camp owner in edit mode, when no hotel is linked. */
	body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-empty {
		display: block !important;
	}

	/* Public view, no hotel linked: hide the entire accommodation section. */
	body.rm-camp-no-hotel:not(.rm-edit-mode) .rm-accommodation-section {
		display: none !important;
	}

	/* No hotel linked AND in edit mode: hide the "filled" card (listing has nothing). */
	body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-filled {
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
	#certifications-documents.jet-form-builder-file-upload__input,
	#camp_thumbnail.jet-form-builder-file-upload__input,
	#camp_gallery.jet-form-builder-file-upload__input,
	#spot_image.jet-form-builder-file-upload__input,
	#spot_gallery.jet-form-builder-file-upload__input,
	#accommodation_photos.jet-form-builder-file-upload__input {
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

	/* --- LABEL ALIGNMENT --- */
	.jet-form-builder-row {
		align-items: center !important;
	}

	.jet-form-builder-row .jet-form-builder__label {
		align-self: center !important;
	}

	/* For textarea/wysiwyg rows, keep label at top */
	.jet-form-builder-row:has(textarea),
	.jet-form-builder-row:has(.jet-form-builder-file-upload),
	.jet-form-builder-row:has(.mce-tinymce),
	.jet-form-builder-row.field-type-repeater-field,
	.jet-form-builder-row:has(.jet-form-builder__field-wrap--map) {
		align-items: flex-start !important;
	}

	.jet-form-builder-row:has(textarea) .jet-form-builder__label,
	.jet-form-builder-row:has(.jet-form-builder-file-upload) .jet-form-builder__label,
	.jet-form-builder-row:has(.mce-tinymce) .jet-form-builder__label,
	.jet-form-builder-row.field-type-repeater-field .jet-form-builder__label,
	.jet-form-builder-row:has(.jet-form-builder__field-wrap--map) .jet-form-builder__label {
		align-self: flex-start !important;
		padding-top: 10px !important;
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

	/* --- DOCUMENT FIELD: hide JFB broken image preview, show our preview instead --- */
	.jet-form-builder-file-upload[data-rm-doc-field] .jet-form-builder-file-upload__file,
	.jet-form-builder-file-upload[data-rm-doc-field] .jet-form-builder-file-upload__file img,
	.jet-form-builder-file-upload[data-rm-doc-field] .jet-form-builder-file-upload__content .jet-form-builder-file-upload__file {
		display: none !important;
		width: 0 !important;
		height: 0 !important;
		overflow: hidden !important;
	}

	.rm-doc-preview {
		display: flex !important;
		align-items: center !important;
		gap: 10px !important;
		padding: 10px 14px !important;
		background: #f8fafc !important;
		border: 1px solid #e2e8f0 !important;
		border-radius: 8px !important;
		margin-bottom: 8px !important;
	}

	.rm-doc-preview__icon {
		flex-shrink: 0;
	}

	.rm-doc-preview__info {
		flex: 1;
		min-width: 0;
	}

	.rm-doc-preview__name {
		font-size: 13px !important;
		font-weight: 500 !important;
		color: #334155 !important;
		white-space: nowrap !important;
		overflow: hidden !important;
		text-overflow: ellipsis !important;
	}

	.rm-doc-preview__badge {
		display: inline-block !important;
		font-size: 10px !important;
		font-weight: 600 !important;
		color: #0D9488 !important;
		background: #CCFBF1 !important;
		padding: 1px 6px !important;
		border-radius: 4px !important;
		margin-top: 2px !important;
	}

	.rm-doc-preview__link {
		font-size: 12px !important;
		font-weight: 600 !important;
		color: #0D9488 !important;
		text-decoration: none !important;
		padding: 4px 10px !important;
		border: 1px solid #0D9488 !important;
		border-radius: 6px !important;
		white-space: nowrap !important;
		transition: all 0.2s ease !important;
	}

	.rm-doc-preview__link:hover {
		background: #0D9488 !important;
		color: #fff !important;
	}

	.rm-doc-preview__delete {
		background: none !important;
		border: 1px solid #EF4444 !important;
		color: #EF4444 !important;
		border-radius: 4px !important;
		padding: 2px 8px !important;
		cursor: pointer !important;
		font-size: 16px !important;
		line-height: 1 !important;
		flex-shrink: 0 !important;
	}

	.rm-doc-preview__delete:hover {
		background: #EF4444 !important;
		color: #fff !important;
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

	/* --- PRICE FONT FIX (coach dashboard / My Camps) --- */
	.woocommerce-Price-amount,
	.woocommerce-Price-amount .woocommerce-Price-currencySymbol,
	.amount {
		font-family: 'DM Sans', sans-serif !important;
		font-weight: 700 !important;
	}
	</style>
	<?php
} );

// =========================================================================
// 5. FLATPICKR ON CREATE-CAMP PAGE + STRIPE WARNING
// =========================================================================

// Show warning if coach hasn't connected Stripe on create-camp page.
add_action( 'wp_footer', function() {
	if ( ! is_page( 'create-camp' ) && strpos( $_SERVER['REQUEST_URI'], 'create-camp' ) === false ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}
	$stripe_complete = get_user_meta( $user_id, 'stripe_onboarding_complete', true );
	if ( $stripe_complete === '1' ) {
		return;
	}
	$is_fr           = apply_filters( 'wpml_current_language', null ) === 'fr';
	$dashboard_href  = $is_fr ? '/fr/coach-dashboard/' : '/coach-dashboard/';
	$title           = $is_fr ? 'Stripe non connecté' : 'Stripe not connected';
	$body            = $is_fr
		? "Vous pouvez publier votre camp, mais vous ne pourrez pas recevoir de paiements tant que votre compte Stripe n'est pas connecté."
		: "You can still publish your camp, but you won't be able to receive payments until your Stripe account is connected.";
	$cta             = $is_fr ? 'Connecter mon compte Stripe' : 'Connect my Stripe account';
	?>
	<script>
	(function() {
		function init() {
			var form = document.querySelector('.jet-form-builder');
			if ( ! form ) return;
			var warning = document.createElement('div');
			warning.style.cssText = 'background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:16px;margin-bottom:20px;font-size:14px;color:#92400e;';
			warning.innerHTML = '<strong>&#9888; <?php echo esc_js( $title ); ?></strong> — <?php echo esc_js( $body ); ?> <a href="<?php echo esc_js( $dashboard_href ); ?>" style="color:#92400e;font-weight:600;text-decoration:underline;"><?php echo esc_js( $cta ); ?></a>';
			form.parentNode.insertBefore(warning, form);
		}
		if ( document.readyState === 'complete' ) { setTimeout(init, 300); }
		else { window.addEventListener('load', function() { setTimeout(init, 300); }); }
	})();
	</script>
	<?php
} );

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
// 6. JFB MEDIA FIELD CRASH FIX
// =========================================================================

// =========================================================================
// 6b. JFB CHECKBOX REQUIRED FIX
// =========================================================================

/**
 * JFB sets required on EACH checkbox in a group. HTML spec says each required
 * checkbox must be individually checked, so unchecked ones fail validation
 * even when others in the same group ARE checked.
 *
 * Fix: before form submission, remove required from unchecked checkboxes
 * when at least one in the same group is checked.
 */
add_action( 'wp_footer', function() {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	$on_jfb = is_page( 'coach-register' )
		|| strpos( $uri, '/coach-dashboard/' ) !== false
		|| strpos( $uri, '/coach-register' ) !== false
		|| strpos( $uri, '/inscription-coach' ) !== false;
	if ( ! $on_jfb ) {
		return;
	}
	?>
	<script>
	(function() {
		function fixCheckboxRequired() {
			var groups = {};
			/* Group checkboxes by name */
			document.querySelectorAll('input[type="checkbox"][required]').forEach(function(cb) {
				var name = cb.name || cb.getAttribute('name');
				if (!name) return;
				if (!groups[name]) groups[name] = [];
				groups[name].push(cb);
			});
			/* For each group: if any is checked, remove required from unchecked ones */
			Object.keys(groups).forEach(function(name) {
				var cbs = groups[name];
				var anyChecked = cbs.some(function(cb) { return cb.checked; });
				if (anyChecked) {
					cbs.forEach(function(cb) {
						if (!cb.checked) {
							cb.removeAttribute('required');
						}
					});
				}
			});
		}

		/* Run on every checkbox change and before submit */
		document.addEventListener('change', function(e) {
			if (e.target && e.target.type === 'checkbox') {
				fixCheckboxRequired();
			}
		});

		/* Also fix right before any form submission */
		document.addEventListener('submit', function() {
			fixCheckboxRequired();
		}, true);

		/* Fix on page load too */
		setTimeout(fixCheckboxRequired, 2000);
	})();
	</script>
	<?php
} );

// =========================================================================
// 6c. JFB MEDIA FIELD CRASH FIX
// =========================================================================

/**
 * JFB's media.field.js crashes with "Cannot read properties of null (reading 'addEventListener')"
 * when rendering a non-image file (PDF/DOC). This breaks the form's save mechanism.
 * We patch the error by adding a safety wrapper that catches the crash.
 */
add_action( 'wp_head', function() {
	$uri = $_SERVER['REQUEST_URI'] ?? '';
	$is_jfb_page = strpos( $uri, '/coach-dashboard/' ) !== false
		|| strpos( $uri, '/coach-register' ) !== false
		|| strpos( $uri, '/inscription-coach' ) !== false;
	if ( ! $is_jfb_page ) {
		return;
	}
	?>
	<script>
	/*
	 * FIX: JFB media.field.js crashes on non-image files (PDF/DOC) in
	 * addRemoveHandler(). MutationObserver can't prevent it (too late).
	 *
	 * Solution: On DOMContentLoaded (which fires BEFORE JFB's setTimeout-deferred
	 * initialization), REMOVE the .jet-form-builder-file-upload element from the
	 * certifications row. JFB never sees a media field → no crash → Save works.
	 *
	 * Our footer code (fixCertificationDocPreview) shows the document preview.
	 */
	/*
	 * ROOT CAUSE FIX: JFB's media.field.js calls addRemoveHandler() which does
	 * querySelector(".jet-form-builder-file-upload__file-remove") on file preview
	 * elements. This returns null (the remove button doesn't exist in the preset
	 * rendering) → crash on addEventListener(null).
	 *
	 * The crash happens in the PROFILE PHOTO field (not certifications!).
	 *
	 * Fix: Patch querySelector to auto-create the missing remove button when JFB
	 * looks for it. This is synchronous — works even inside JFB's init chain.
	 */
	(function() {
		var origQS = Element.prototype.querySelector;
		Element.prototype.querySelector = function(selector) {
			var result = origQS.call(this, selector);
			if (!result && selector === '.jet-form-builder-file-upload__file-remove' &&
				this.classList && this.classList.contains('jet-form-builder-file-upload__file')) {
				/* Create the missing remove button on the fly */
				var dummy = document.createElement('div');
				dummy.className = 'jet-form-builder-file-upload__file-remove';
				dummy.style.display = 'none';
				this.appendChild(dummy);
				return dummy;
			}
			return result;
		};
		/* Restore original after JFB has fully initialized (10s safety) */
		setTimeout(function() { Element.prototype.querySelector = origQS; }, 10000);
	})();

	/*
	 * Hide the certifications upload widget — we manage docs independently.
	 *
	 * ONLY on the profile page, where fixCertificationDocPreview() builds the
	 * .rm-doc-list replacement UI. That function bails out when rmCertNonce is
	 * undefined (see the same guard in the wp_footer block), and the nonce is
	 * only injected on /coach-dashboard/profile by RM_Coach::inject_certification_doc_info().
	 *
	 * On /coach-register (and /inscription-coach), no replacement exists: the
	 * native widget is what RM_Auth::guest_upload_js() binds its uploader to.
	 * Hiding it there left the label and the "Max 10 MB" hint with no button
	 * at all — coaches could not attach any certification document.
	 *
	 * Gate on the same condition the replacement UI uses, so the two can never
	 * drift apart again. rmCertNonce is printed in wp_footer, which the browser
	 * has already parsed by the time DOMContentLoaded fires.
	 */
	document.addEventListener('DOMContentLoaded', function() {
		if (typeof window.rmCertNonce === 'undefined') return;
		var rows = document.querySelectorAll('.jet-form-builder-row');
		for (var i = 0; i < rows.length; i++) {
			var upload = rows[i].querySelector('.jet-form-builder-file-upload');
			if (!upload) continue;
			var lbl = rows[i].querySelector('.jet-form-builder__label');
			if (!lbl) continue;
			var txt = lbl.textContent.toLowerCase();
			if (txt.indexOf('certif') !== -1 && (txt.indexOf('upload') !== -1 || txt.indexOf('document') !== -1)) {
				upload.style.display = 'none';
				break;
			}
		}
	});

	/* Safety net: catch any remaining JFB errors */
	window.addEventListener('error', function(e) {
		if (e && e.filename && (
			e.filename.indexOf('media.field.js') !== -1 ||
			e.filename.indexOf('wysiwyg.js') !== -1
		)) {
			console.warn('[RM] Caught JFB error:', e.message);
			e.preventDefault();
			return true;
		}
	});
	window.addEventListener('unhandledrejection', function(e) {
		var msg = e && e.reason ? (e.reason.message || String(e.reason)) : '';
		if (msg.indexOf('validityState') !== -1 ||
			msg.indexOf('lock') !== -1 ||
			msg.indexOf('addEventListener') !== -1) {
			console.warn('[RM] Caught JFB promise rejection:', msg);
			e.preventDefault();
		}
	});
	</script>
	<?php
} );

// =========================================================================
// 7. JFB FILE UPLOAD PREVIEW FIX
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
		 * Detect the field name of a .jet-form-builder-file-upload wrapper.
		 */
		function getUploadFieldName(wrapper) {
			var fn = wrapper.getAttribute('data-field-name');
			if (fn) return fn;

			var hidden = wrapper.querySelector('input[type="hidden"]');
			if (hidden && hidden.name) return hidden.name;

			var fileInput = wrapper.querySelector('input[type="file"]');
			if (fileInput && fileInput.id) return fileInput.id;

			var row = wrapper.closest('.jet-form-builder-row');
			if (row) {
				var lbl = row.querySelector('.jet-form-builder__label');
				if (lbl) {
					var t = lbl.textContent.toLowerCase();
					if (t.indexOf('profile') !== -1) return 'coach_profile_photo';
					if (t.indexOf('cover') !== -1) return 'coach_cover_photo';
					if (t.indexOf('certif') !== -1) return 'certifications-documents';
				}
			}
			return '';
		}

		var DOC_FIELDS = ['certifications-documents', 'certifications_documents'];

		/**
		 * Fix 2: Handle file upload previews.
		 *
		 * - For IMAGE fields: replace generic JFB icons with real thumbnails.
		 * - For DOCUMENT fields: hide JFB's broken image, show a styled document
		 *   preview OUTSIDE the JFB wrapper (to avoid breaking form save).
		 */
		function fixGenericIcons() {
			var uploaders = document.querySelectorAll('.jet-form-builder-file-upload');

			uploaders.forEach(function(wrapper, idx) {
				if (wrapper.dataset.rmFixed) return;

				var fieldName = getUploadFieldName(wrapper);
				var fileEls = wrapper.querySelectorAll('.jet-form-builder-file-upload__file');
				var hiddenInput = wrapper.querySelector('input[type="hidden"]');
				var hiddenVal = hiddenInput ? hiddenInput.value : '';

				/* --- DOCUMENT FIELDS --- */
				if (DOC_FIELDS.indexOf(fieldName) !== -1) {
					wrapper.dataset.rmFixed = '1';
					/* Mark wrapper so CSS hides JFB's broken image preview */
					wrapper.setAttribute('data-rm-doc-field', '1');

					/* Use server-injected data (no REST fetch needed) */
					var docInfo = window.rmCertificationDoc;
					if (docInfo && docInfo.url) {
						addDocPreviewElement(wrapper, docInfo);
					}
					/* If no doc info, just leave the clean upload area */
					return;
				}

				/* --- IMAGE FIELDS --- */

				var imageUrl = null;

				/* Profile photo: use server-injected URL */
				if (fieldName === 'coach_profile_photo' && !hiddenVal && fileEls.length === 0 && window.rmCoachProfilePhotoUrl) {
					imageUrl = window.rmCoachProfilePhotoUrl;

					var content = wrapper.querySelector('.jet-form-builder-file-upload__content');
					if (!content) content = wrapper.querySelector('.jet-form-builder-file-upload__fields');
					if (content) {
						wrapper.dataset.rmFixed = '1';
						var fileDiv = document.createElement('div');
						fileDiv.className = 'jet-form-builder-file-upload__file';
						var img = document.createElement('img');
						img.src = imageUrl;
						img.style.cssText = 'width:100%;height:80px;object-fit:cover;border-radius:6px;display:block;';
						fileDiv.appendChild(img);
						content.insertBefore(fileDiv, content.firstChild);
					}
					return;
				}

				if (hiddenVal) {
					try {
						var parsed = JSON.parse(hiddenVal);
						if (parsed) {
							if (typeof parsed.id === 'string' && parsed.id.indexOf('http') === 0) imageUrl = parsed.id;
							else if (typeof parsed.url === 'string' && parsed.url.indexOf('http') === 0) imageUrl = parsed.url;
						}
					} catch(e) {
						var val = hiddenVal.trim();
						var numId = parseInt(val);
						if (numId && !isNaN(numId)) {
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
										if (thumbUrl) replaceWithImage(w, fEls, thumbUrl);
									})
									.catch(function() {});
							})(wrapper, fileEls, numId);
							return;
						} else if (val.indexOf('http') === 0) {
							imageUrl = val;
						}
					}
				}

				if (!imageUrl) return;
				replaceWithImage(wrapper, fileEls, imageUrl);
			});
		}

		function replaceWithImage(wrapper, fileEls, imageUrl) {
			if (wrapper.dataset.rmFixed) return;
			wrapper.dataset.rmFixed = '1';
			fileEls.forEach(function(fileEl) {
				var existingImg = fileEl.querySelector('img');
				if (existingImg && existingImg.naturalWidth > 50) return;
				fileEl.innerHTML = '';
				var img = document.createElement('img');
				img.src = imageUrl;
				img.style.cssText = 'width:100%;height:80px;object-fit:cover;border-radius:6px;display:block;';
				fileEl.appendChild(img);
			});
		}

		/**
		 * Add a document preview element BEFORE the wrapper (not inside it)
		 * so we don't break JFB's internal DOM structure or save mechanism.
		 */
		function addDocPreviewElement(wrapper, docInfo) {
			/* Remove any existing preview */
			var existing = wrapper.parentNode.querySelector('.rm-doc-preview');
			if (existing) existing.remove();

			var preview = document.createElement('div');
			preview.className = 'rm-doc-preview';

			/* SVG document icon */
			var iconHtml = '<svg class="rm-doc-preview__icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0D9488" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>';

			var isImage = docInfo.is_image;
			var thumbHtml = '';

			if (isImage && (docInfo.thumb_url || docInfo.url)) {
				thumbHtml = '<img src="' + (docInfo.thumb_url || docInfo.url) + '" style="width:60px;height:60px;object-fit:cover;border-radius:6px;flex-shrink:0;" />';
			}

			preview.innerHTML =
				(isImage ? thumbHtml : iconHtml) +
				'<div class="rm-doc-preview__info">' +
					'<div class="rm-doc-preview__name">' + docInfo.name + '</div>' +
					(docInfo.ext ? '<span class="rm-doc-preview__badge">' + docInfo.ext + '</span>' : '') +
				'</div>' +
				'<a class="rm-doc-preview__link" href="' + docInfo.url + '" target="_blank" rel="noopener">View</a>';

			/* Insert BEFORE the wrapper, not inside it */
			wrapper.parentNode.insertBefore(preview, wrapper);
		}

		/**
		 * Fix 3: Certification document preview — independent from JFB widget state.
		 *
		 * JFB's media.field.js crashes when trying to render a non-image file preview,
		 * leaving a broken image. Instead of relying on JFB's DOM, we find the
		 * certification row by its label text and add our preview + hide the broken element.
		 */
		function fixCertificationDocPreview() {
			if (document.querySelector('.rm-doc-list')) return;
			/* Only run on profile page where we injected the data */
			if (typeof window.rmCertNonce === 'undefined') return;

			/* Find the certifications upload row (upload widget hidden in wp_head) */
			var rows = document.querySelectorAll('.jet-form-builder-row');
			var certRow = null;
			for (var i = 0; i < rows.length; i++) {
				var lbl = rows[i].querySelector('.jet-form-builder__label');
				if (!lbl) continue;
				var txt = lbl.textContent.toLowerCase();
				if (txt.indexOf('certif') !== -1 && (txt.indexOf('upload') !== -1 || txt.indexOf('document') !== -1)) {
					certRow = rows[i];
					break;
				}
			}

			var docs = window.rmCertificationDocs || [];
			console.log('[RM Debug] fixCertificationDocPreview: docs=', docs.length, 'certRow=', !!certRow);

			if (!certRow) return;

			/* Track current doc IDs for AJAX save */
			var currentIds = docs.map(function(d) { return String(d.id); });

			/* Build the container — append AFTER the field-wrap, not inside it (upload widget is hidden) */
			var fieldWrap = certRow.querySelector('.jet-form-builder__field-wrap') || certRow;
			var listEl = document.createElement('div');
			listEl.className = 'rm-doc-list';

			function addDocEntry(doc) {
				var entry = document.createElement('div');
				entry.className = 'rm-doc-preview';
				entry.dataset.rmDocId = doc.id;

				var iconHtml = '<svg class="rm-doc-preview__icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0D9488" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>';

				entry.innerHTML =
					iconHtml +
					'<div class="rm-doc-preview__info">' +
						'<div class="rm-doc-preview__name">' + doc.name + '</div>' +
						'<span class="rm-doc-preview__badge">' + doc.ext + '</span>' +
					'</div>' +
					'<a class="rm-doc-preview__link" href="' + doc.url + '" target="_blank" rel="noopener">View</a>' +
					'<button type="button" class="rm-doc-preview__delete" title="Remove">&times;</button>';

				entry.querySelector('.rm-doc-preview__delete').addEventListener('click', function() {
					var docId = String(doc.id);
					currentIds = currentIds.filter(function(id) { return id !== docId; });
					entry.remove();
					saveDocIds();
				});

				return entry;
			}

			docs.forEach(function(doc) {
				listEl.appendChild(addDocEntry(doc));
			});

			/* Upload new document via REST (same endpoint as registration) */
			var addArea = document.createElement('div');
			addArea.style.cssText = 'margin-top:8px;';
			addArea.innerHTML =
				'<label style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border:1px dashed #0D9488;color:#0D9488;border-radius:6px;cursor:pointer;font-size:13px;font-weight:500;">' +
					'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>' +
					'Add document' +
					'<input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" multiple style="display:none;" />' +
				'</label>' +
				'<span class="rm-doc-upload-status" style="display:block;margin-top:4px;font-size:13px;"></span>';

			var addInput = addArea.querySelector('input[type="file"]');
			var addStatus = addArea.querySelector('.rm-doc-upload-status');

			addInput.addEventListener('change', function() {
				if (!addInput.files || !addInput.files.length) return;
				for (var fi = 0; fi < addInput.files.length; fi++) {
					uploadDocFile(addInput.files[fi]);
				}
				addInput.value = '';
			});

			function uploadDocFile(file) {
				var msg = document.createElement('div');
				msg.textContent = 'Uploading ' + file.name + '...';
				msg.style.color = '#64748b';
				addStatus.appendChild(msg);

				var formData = new FormData();
				formData.append('file', file);
				formData.append('field_type', 'document');

				fetch(window.rmRestUploadUrl || '/wp-json/ridemaster/v1/guest-upload', {
					method: 'POST',
					headers: { 'X-WP-Nonce': window.rmRestNonce || '' },
					body: formData
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					msg.remove();
					if (data && data.success) {
						var newId = String(data.attachment_id);
						currentIds.push(newId);
						var ext = file.name.split('.').pop().toUpperCase();
						var newDoc = { id: data.attachment_id, url: data.url, name: file.name, ext: ext, is_image: false };
						listEl.insertBefore(addDocEntry(newDoc), addArea);
						saveDocIds();
					} else {
						msg.textContent = 'Failed: ' + (data && data.message ? data.message : 'Unknown error');
						msg.style.color = '#EF4444';
						addStatus.appendChild(msg);
					}
				})
				.catch(function() {
					msg.textContent = 'Upload failed for ' + file.name;
					msg.style.color = '#EF4444';
				});
			}

			listEl.appendChild(addArea);
			/* Insert AFTER fieldWrap as a sibling (not inside — upload widget may be hidden) */
			if (fieldWrap.nextSibling) {
				fieldWrap.parentNode.insertBefore(listEl, fieldWrap.nextSibling);
			} else {
				fieldWrap.parentNode.appendChild(listEl);
			}

			/* Save doc IDs to post meta via AJAX (independent of JFB) */
			function saveDocIds() {
				var coachPostId = '';
				var cpInput = document.querySelector('input[name="coach_post_id"]');
				if (cpInput) coachPostId = cpInput.value;
				if (!coachPostId) return;

				var formData = new FormData();
				formData.append('action', 'rm_save_cert_docs');
				formData.append('post_id', coachPostId);
				formData.append('doc_ids', currentIds.join(','));
				formData.append('nonce', (window.rmCertNonce || ''));

				fetch((window.ajaxurl || '/wp-admin/admin-ajax.php'), {
					method: 'POST',
					body: formData
				}).then(function(r) { return r.json(); }).then(function(res) {
					console.log('[RM] Cert docs saved:', res);
				});
			}
		}

		function runAllFixes() {
			log('runAllFixes called');
			initCancelProtection();
			fixGenericIcons();
			fixCertificationDocPreview();
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

		/* ============================================================
		 * DIAGNOSTIC LOGS — helps debug Save My Profile issues.
		 * Open browser console (F12) to see these logs.
		 * ============================================================ */
		setTimeout(function() {
			console.log('=== [RM Debug] Form Diagnostics ===');

			var form = document.querySelector('form.jet-form-builder');
			if (!form) {
				var allForms = document.querySelectorAll('form');
				allForms.forEach(function(f) {
					if (f.querySelector('.jet-form-builder__submit') || f.querySelector('.jet-form-builder-row')) {
						form = f;
					}
				});
			}
			console.log('[RM Debug] JFB form:', form ? 'FOUND (' + form.id + ')' : 'NOT FOUND');

			var submitBtn = document.querySelector('.jet-form-builder__submit, button[type="submit"]');
			console.log('[RM Debug] Submit button:', submitBtn ? '"' + submitBtn.textContent.trim() + '"' : 'NOT FOUND');

			var uploads = document.querySelectorAll('.jet-form-builder-file-upload');
			console.log('[RM Debug] Upload fields: ' + uploads.length);
			uploads.forEach(function(u, idx) {
				var hidden = u.querySelector('input[type="hidden"]');
				var files = u.querySelectorAll('.jet-form-builder-file-upload__file');
				var row = u.closest('.jet-form-builder-row');
				var lbl = row ? row.querySelector('.jet-form-builder__label') : null;
				console.log('[RM Debug]   #' + idx + ': "' + (lbl ? lbl.textContent.trim() : '?') + '"',
					'name=' + (hidden ? hidden.name : '-'),
					'val=' + (hidden ? hidden.value.substring(0, 40) : '-'),
					'files=' + files.length,
					'rmDoc=' + (u.getAttribute('data-rm-doc-field') || 'no'));
			});

			console.log('[RM Debug] rmCertificationDocs:', window.rmCertificationDocs ? window.rmCertificationDocs.length + ' docs' : 'NOT SET');

			/* Intercept submit button click to log what happens */
			if (submitBtn) {
				submitBtn.addEventListener('click', function() {
					console.log('[RM Debug] >>> Submit button clicked <<<');

					/* Log all hidden inputs in the form */
					if (form) {
						var hiddens = form.querySelectorAll('input[type="hidden"]');
						hiddens.forEach(function(h) {
							if (h.name && h.value) {
								console.log('[RM Debug] Hidden: ' + h.name + ' = ' + h.value.substring(0, 60));
							}
						});
					}

					/* Check for required fields with validation issues */
					var allFields = document.querySelectorAll('input, select, textarea');
					var invalidCount = 0;
					allFields.forEach(function(f) {
						if (f.validity && !f.validity.valid && f.name) {
							console.warn('[RM Debug] INVALID field: "' + f.name + '" - ' + f.validationMessage);
							invalidCount++;
						}
					});
					console.log('[RM Debug] Invalid fields: ' + invalidCount);
				}, true);
			}

			console.log('=== [RM Debug] End Diagnostics ===');
		}, 4000);
	})();
	</script>
	<?php
} );

// =========================================================================
// 9. CAMP CARD "..." MENU WITH DELETE (My Camps on coach dashboard)
// =========================================================================

add_action( 'wp_footer', function() {
	// Only on the coach dashboard (not on individual camp pages).
	if ( strpos( $_SERVER['REQUEST_URI'], '/coach-dashboard/' ) === false ) {
		return;
	}
	// Don't show on profile sub-page (that's the edit form).
	if ( strpos( $_SERVER['REQUEST_URI'], '/profile' ) !== false ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	// Build a map of camp IDs → has_orders for the current coach's camps.
	$coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
	if ( ! $coach_post_id ) {
		return;
	}

	// --- Camp data ---
	$camp_ids = get_posts( [
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_query'     => [
			[ 'key' => '_coach_post_id', 'value' => $coach_post_id ],
		],
	] );

	$camp_data = [];
	if ( ! empty( $camp_ids ) ) {
		global $wpdb;
		$oim_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$oi_table  = $wpdb->prefix . 'woocommerce_order_items';

		$placeholders = implode( ',', array_fill( 0, count( $camp_ids ), '%d' ) );
		$camps_with_orders = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT oim.meta_value FROM {$oim_table} oim
			 INNER JOIN {$oi_table} oi ON oi.order_item_id = oim.order_item_id
			 WHERE oim.meta_key = '_product_id' AND oim.meta_value IN ({$placeholders})",
			...$camp_ids
		) );
		$camps_with_orders = array_map( 'intval', $camps_with_orders );

		foreach ( $camp_ids as $cid ) {
			$camp_data[ $cid ] = ! in_array( intval( $cid ), $camps_with_orders, true );
		}
	}

	// --- Spot data ---
	$spot_ids = get_posts( [
		'post_type'      => 'spot',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_query'     => [
			[ 'key' => '_coach_post_id', 'value' => $coach_post_id ],
		],
	] );

	$spot_data = [];
	if ( ! empty( $spot_ids ) ) {
		$inline_edit = new RM_Inline_Edit();
		foreach ( $spot_ids as $sid ) {
			$spot_data[ $sid ] = ! $inline_edit->spot_has_camps( $sid );
		}
	}

	// --- Hotel data ---
	$hotel_ids = get_posts( [
		'post_type'      => 'hotel',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_query'     => [
			[ 'key' => '_coach_post_id', 'value' => $coach_post_id ],
		],
	] );

	$hotel_data = [];
	foreach ( $hotel_ids as $hid ) {
		// Hotels can always be deleted (no dependency check for now).
		$hotel_data[ $hid ] = true;
	}

	if ( empty( $camp_data ) && empty( $spot_data ) && empty( $hotel_data ) ) {
		return;
	}

	$nonce = wp_create_nonce( 'rm_inline_edit' );
	?>
	<style>
	.rm-camp-menu-wrap {
		position: absolute;
		top: 20px;
		right: 20px;
		z-index: 10;
	}
	.rm-camp-menu-btn {
		background: rgba(255,255,255,0.95);
		border: 2px solid #0d9488;
		border-radius: 50%;
		width: 36px;
		height: 36px;
		cursor: pointer;
		font-size: 18px;
		line-height: 30px;
		text-align: center;
		color: #0d9488;
		box-shadow: 0 2px 8px rgba(0,0,0,0.12);
		transition: all 0.2s;
		padding: 0;
		font-weight: bold;
	}
	.rm-camp-menu-btn:hover {
		background: #0d9488;
		color: #fff;
	}
	.rm-camp-menu-dropdown {
		display: none;
		position: absolute;
		top: 36px;
		right: 0;
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 4px 12px rgba(0,0,0,0.15);
		min-width: 150px;
		overflow: hidden;
		z-index: 20;
	}
	.rm-camp-menu-dropdown.rm-open {
		display: block;
	}
	.rm-camp-menu-item {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 10px 16px;
		border: none;
		background: none;
		width: 100%;
		text-align: left;
		cursor: pointer;
		font-size: 14px;
		color: #dc3545;
		transition: background 0.15s;
	}
	.rm-camp-menu-item:hover {
		background: #fef2f2;
	}
	.rm-camp-menu-item svg {
		flex-shrink: 0;
	}
	</style>
	<script>
	(function() {
		var campData = <?php echo wp_json_encode( $camp_data ); ?>;
		var spotData = <?php echo wp_json_encode( $spot_data ); ?>;
		var hotelData = <?php echo wp_json_encode( $hotel_data ); ?>;
		var nonce = <?php echo wp_json_encode( $nonce ); ?>;
		var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

		function init() {
			var items = document.querySelectorAll('.jet-listing-grid__item');
			if ( ! items.length ) return;

			items.forEach(function(item) {
				var id = item.getAttribute('data-post-id') || item.getAttribute('data-object-id');
				if ( ! id ) {
					var postIdEl = item.querySelector('[data-post-id]');
					if ( postIdEl ) id = postIdEl.dataset.postId;
				}
				if ( id ) {
					var numId = parseInt( id, 10 );
					if ( campData.hasOwnProperty( numId ) ) {
						addMenu( item, numId, 'camp' );
					} else if ( spotData.hasOwnProperty( numId ) ) {
						addMenu( item, numId, 'spot' );
					} else if ( hotelData.hasOwnProperty( numId ) ) {
						addMenu( item, numId, 'hotel' );
					}
				}
			});
		}

		function addMenu( item, postId, type ) {
			var data = type === 'spot' ? spotData : type === 'hotel' ? hotelData : campData;
			if ( ! data[ postId ] ) return; // Not deletable.
			if ( item.querySelector('.rm-camp-menu-wrap') ) return;

			var posParent = item.querySelector('.jet-listing-grid__item-inner') || item;
			if ( getComputedStyle( posParent ).position === 'static' ) {
				posParent.style.position = 'relative';
			}

			var labels = { camp: 'Delete Camp', spot: 'Delete Spot', hotel: 'Delete Hotel' };
			var actions = { camp: 'rm_delete_camp', spot: 'rm_delete_spot', hotel: 'rm_delete_hotel' };
			var confirms = {
				camp: 'Are you sure you want to delete this camp? This action cannot be undone.',
				spot: 'Are you sure you want to delete this spot? This action cannot be undone.',
				hotel: 'Are you sure you want to delete this hotel? This action cannot be undone.'
			};
			var label = labels[type] || 'Delete';
			var action = actions[type] || 'rm_delete_camp';
			var confirmMsg = confirms[type] || 'Are you sure?';

			var wrap = document.createElement('div');
			wrap.className = 'rm-camp-menu-wrap';
			wrap.innerHTML =
				'<button type="button" class="rm-camp-menu-btn" title="Options">&#8942;</button>' +
				'<div class="rm-camp-menu-dropdown">' +
					'<button type="button" class="rm-camp-menu-item" data-post-id="' + postId + '" data-action="' + action + '" data-confirm="' + confirmMsg + '">' +
						'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>' +
						label +
					'</button>' +
				'</div>';

			posParent.appendChild( wrap );

			var btn = wrap.querySelector('.rm-camp-menu-btn');
			var dropdown = wrap.querySelector('.rm-camp-menu-dropdown');
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				document.querySelectorAll('.rm-camp-menu-dropdown.rm-open').forEach(function(d) {
					if ( d !== dropdown ) d.classList.remove('rm-open');
				});
				dropdown.classList.toggle('rm-open');
			});

			var delItem = wrap.querySelector('.rm-camp-menu-item');
			delItem.addEventListener('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				if ( ! confirm( this.dataset.confirm ) ) return;

				var fd = new FormData();
				fd.append('action', this.dataset.action);
				fd.append('nonce', nonce);
				fd.append('post_id', this.dataset.postId);

				fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if ( resp.success ) {
							item.style.transition = 'opacity 0.3s';
							item.style.opacity = '0';
							setTimeout(function() { item.remove(); }, 350);
						} else {
							var msg = (resp.data && typeof resp.data === 'string') ? resp.data : 'Failed to delete.';
							alert(msg);
						}
					})
					.catch(function() {
						alert('Network error. Please try again.');
					});
			});
		}

		// Close menus on outside click.
		document.addEventListener('click', function() {
			document.querySelectorAll('.rm-camp-menu-dropdown.rm-open').forEach(function(d) {
				d.classList.remove('rm-open');
			});
		});

		// Init after page load (JetEngine listing may load late).
		if ( document.readyState === 'complete' ) {
			setTimeout( init, 500 );
		} else {
			window.addEventListener('load', function() {
				setTimeout( init, 500 );
			});
		}
	})();
	</script>
	<?php
} );

// =========================================================================
// 10. SHORTCODE: [rm_stripe_connect] — Stripe Connect widget for coach dashboard
// =========================================================================

add_shortcode( 'rm_stripe_connect', function() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
		return '';
	}

	$stripe_account_id = get_user_meta( $user_id, 'stripe_account_id', true );
	$stripe_complete   = get_user_meta( $user_id, 'stripe_onboarding_complete', true ) === '1';
	$nonce             = wp_create_nonce( 'rm_stripe_connect' );
	$ajax_url          = admin_url( 'admin-ajax.php' );

	ob_start();
	?>
	<style>
	.rm-stripe-widget,
	.rm-earnings-widget,
	.rm-payments-wrap {
		font-family: 'DM Sans', sans-serif;
	}
	.rm-stripe-widget { background:#fff; border-radius:16px; padding:24px 28px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border:1px solid #e5e7eb; }
	.rm-stripe-widget h3 { margin:0 0 14px; font-size:18px; font-weight:700; color:#1f2937; font-family:'DM Sans',sans-serif; }
	.rm-stripe-widget p { font-family:'DM Sans',sans-serif; }
	.rm-stripe-status { display:inline-flex; align-items:center; gap:8px; padding:6px 16px; border-radius:20px; font-size:14px; font-weight:600; font-family:'DM Sans',sans-serif; }
	.rm-stripe-status--active { background:#d1fae5; color:#065f46; }
	.rm-stripe-status--pending { background:#fef3c7; color:#92400e; }
	.rm-stripe-status--disconnected { background:#f3f4f6; color:#6b7280; }
	.rm-stripe-connect-btn { display:inline-flex; align-items:center; gap:8px; margin-top:14px; padding:12px 24px; background:#635bff; color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:600; font-family:'DM Sans',sans-serif; cursor:pointer; transition:all 0.2s; }
	.rm-stripe-connect-btn:hover { background:#5147e5; transform:translateY(-1px); box-shadow:0 4px 12px rgba(99,91,255,0.3); }
	.rm-stripe-disconnect { display:inline-block; margin-top:10px; font-size:13px; color:#9ca3af; cursor:pointer; border:none; background:none; text-decoration:underline; font-family:'DM Sans',sans-serif; }
	.rm-stripe-disconnect:hover { color:#ef4444; }
	</style>
	<?php
	$rm_is_fr = apply_filters( 'wpml_current_language', null ) === 'fr';
	$rm_t = function( $en, $fr ) use ( $rm_is_fr ) { return $rm_is_fr ? $fr : $en; };
	?>
	<div class="rm-stripe-widget" id="rm-stripe-widget">
		<h3><?php echo $rm_t( 'Stripe Payments', 'Paiements Stripe' ); ?></h3>
		<?php if ( $stripe_account_id && $stripe_complete ) : ?>
			<span class="rm-stripe-status rm-stripe-status--active">&#10003; <?php echo $rm_t( 'Stripe Connected', 'Stripe connecté' ); ?></span>
			<br><button type="button" class="rm-stripe-disconnect" id="rm-stripe-disconnect"><?php echo $rm_t( 'Disconnect', 'Se déconnecter' ); ?></button>
		<?php elseif ( $stripe_account_id ) : ?>
			<span class="rm-stripe-status rm-stripe-status--pending">&#9888; <?php echo $rm_t( 'Setup incomplete', 'Configuration incomplète' ); ?></span>
			<br><button type="button" class="rm-stripe-connect-btn" id="rm-stripe-connect-btn"><?php echo $rm_t( 'Complete Stripe Setup', 'Terminer la configuration Stripe' ); ?></button>
		<?php else : ?>
			<span class="rm-stripe-status rm-stripe-status--disconnected"><?php echo $rm_t( 'Not connected', 'Non connecté' ); ?></span>
			<p style="margin:8px 0 0;font-size:13px;color:#6b7280;"><?php echo $rm_t( 'Connect your Stripe account to receive payments from your camps.', 'Connectez votre compte Stripe pour recevoir les paiements de vos camps.' ); ?></p>
			<button type="button" class="rm-stripe-connect-btn" id="rm-stripe-connect-btn"><?php echo $rm_t( 'Connect with Stripe', 'Se connecter avec Stripe' ); ?></button>
		<?php endif; ?>
	</div>
	<script>
	(function() {
		var nonce = <?php echo wp_json_encode( $nonce ); ?>;
		var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;

		var t = {
			redirecting:     <?php echo wp_json_encode( $rm_t( 'Redirecting...', 'Redirection...' ) ); ?>,
			errorConnect:    <?php echo wp_json_encode( $rm_t( 'Error connecting to Stripe.', 'Erreur de connexion à Stripe.' ) ); ?>,
			connectLabel:    <?php echo wp_json_encode( $rm_t( 'Connect with Stripe', 'Se connecter avec Stripe' ) ); ?>,
			networkError:    <?php echo wp_json_encode( $rm_t( 'Network error. Please try again.', 'Erreur réseau. Veuillez réessayer.' ) ); ?>,
			confirmDisco:    <?php echo wp_json_encode( $rm_t( 'Are you sure you want to disconnect your Stripe account?', 'Voulez-vous vraiment déconnecter votre compte Stripe ?' ) ); ?>,
		};
		var connectBtn = document.getElementById('rm-stripe-connect-btn');
		if ( connectBtn ) {
			connectBtn.addEventListener('click', function() {
				connectBtn.disabled = true;
				connectBtn.textContent = t.redirecting;
				var fd = new FormData();
				fd.append('action', 'rm_stripe_connect');
				fd.append('nonce', nonce);
				fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if ( resp.success && resp.data.url ) {
							window.location.href = resp.data.url;
						} else {
							alert(resp.data || t.errorConnect);
							connectBtn.disabled = false;
							connectBtn.textContent = t.connectLabel;
						}
					})
					.catch(function() {
						alert(t.networkError);
						connectBtn.disabled = false;
						connectBtn.textContent = t.connectLabel;
					});
			});
		}

		var disconnectBtn = document.getElementById('rm-stripe-disconnect');
		if ( disconnectBtn ) {
			disconnectBtn.addEventListener('click', function() {
				if ( ! confirm(t.confirmDisco) ) return;
				var fd = new FormData();
				fd.append('action', 'rm_stripe_disconnect');
				fd.append('nonce', nonce);
				fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function() { window.location.reload(); });
			});
		}
	})();
	</script>
	<?php
	return ob_get_clean();
} );

// =========================================================================
// 11. SHORTCODE: [rm_my_earnings] — Earnings summary widget
// =========================================================================

/**
 * Helper: gather earnings data for the current coach.
 */
function rm_get_coach_earnings() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return null;
	}

	$coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
	if ( ! $coach_post_id ) {
		return null;
	}

	// Find coach's camps (3-level fallback: meta, JetEngine relation, post_author).
	$camp_ids = get_posts( [
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'meta_query'     => [
			[ 'key' => '_coach_post_id', 'value' => strval( $coach_post_id ) ],
		],
	] );

	if ( empty( $camp_ids ) && function_exists( 'jet_engine' ) && class_exists( 'RM_Camp' ) ) {
		$relation = RM_Camp::find_relation( 'Coach to Camps' );
		if ( $relation && method_exists( $relation, 'get_children' ) ) {
			$children = $relation->get_children( $coach_post_id, 'ids' );
			if ( is_array( $children ) ) {
				$camp_ids = array_map( 'intval', $children );
			}
		}
	}

	if ( empty( $camp_ids ) ) {
		$camp_ids = get_posts( [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'author'         => $user_id,
			'tax_query'      => [
				[ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'camp' ],
			],
		] );
	}

	$earnings = [ 'available' => 0, 'escrow' => 0, 'total' => 0, 'next_payout' => null, 'transactions' => [] ];

	if ( empty( $camp_ids ) || ! function_exists( 'wc_get_orders' ) ) {
		return $earnings;
	}

	$orders = wc_get_orders( [
		'limit'      => 100,
		'orderby'    => 'date',
		'order'      => 'DESC',
		'status'     => [ 'processing', 'completed', 'refunded', 'cancelled' ],
		'meta_query' => [
			[ 'key' => '_camp_id', 'value' => $camp_ids, 'compare' => 'IN' ],
		],
	] );

	foreach ( $orders as $order ) {
		$payout_status = $order->get_meta( '_payout_status' );
		$coach_amount  = floatval( $order->get_meta( '_amount_coach' ) );
		$hotel_amount  = floatval( $order->get_meta( '_amount_hotel' ) );
		$total_amount  = floatval( $order->get_total() );
		$camp_id       = $order->get_meta( '_camp_id' );
		$payout_date   = $order->get_meta( '_payout_date' );

		$display_status = 'escrow';
		if ( $payout_status === 'paid' ) {
			$display_status = 'paid';
			$earnings['available'] += $coach_amount;
		} elseif ( $payout_status === 'cancelled' ) {
			$display_status = 'cancelled';
		} elseif ( $payout_status === 'failed' ) {
			$display_status = 'error';
		} else {
			$earnings['escrow'] += $coach_amount;
			if ( $payout_date && ( ! $earnings['next_payout'] || $payout_date < $earnings['next_payout']['date'] ) ) {
				$earnings['next_payout'] = [ 'date' => $payout_date, 'amount' => $coach_amount ];
			}
		}
		$earnings['total'] += $coach_amount;

		$rider_name = $order->get_billing_first_name() . ' ' . substr( $order->get_billing_last_name(), 0, 1 ) . '.';
		$earnings['transactions'][] = [
			'order_id'     => $order->get_id(),
			'order_number' => $order->get_order_number(),
			'date'         => $order->get_date_created() ? $order->get_date_created()->format( 'd/m/Y' ) : '',
			'camp'         => $camp_id ? get_the_title( $camp_id ) : '—',
			'camp_id'      => $camp_id,
			'rider'        => $rider_name,
			'rider_full'   => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'rider_email'  => $order->get_billing_email(),
			'total'        => $total_amount,
			'coach_amount' => $coach_amount,
			'hotel_amount' => $hotel_amount,
			'status'       => $display_status,
			'payout_date'  => $order->get_meta( '_payout_date_actual' ) ?: $payout_date,
			'refund_pct'   => $order->get_meta( '_cancellation_tier' ),
			'order_date'   => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i' ) : '',
			'participants' => $order->get_meta( '_participants_count' ) ?: '1',
		];
	}

	return $earnings;
}

add_shortcode( 'rm_my_earnings', function() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
		return '';
	}

	$data = rm_get_coach_earnings();
	if ( ! $data ) {
		return '';
	}

	$rm_is_fr = apply_filters( 'wpml_current_language', null ) === 'fr';
	$rm_t = function( $en, $fr ) use ( $rm_is_fr ) { return $rm_is_fr ? $fr : $en; };
	$fmt = function( $v ) { return number_format( $v, 0, ',', ' ' ) . '&nbsp;&euro;'; };
	$next = $data['next_payout']
		? $fmt( $data['next_payout']['amount'] ) . ' ' . $rm_t( 'on', 'le' ) . ' ' . esc_html( $data['next_payout']['date'] )
		: $rm_t( 'None scheduled', 'Aucun programmé' );

	ob_start();
	?>
	<style>
	.rm-earnings-widget { background:#fff; border-radius:16px; padding:24px 28px; box-shadow:0 2px 8px rgba(0,0,0,0.06); border:1px solid #e5e7eb; }
	.rm-earnings-widget h3 { margin:0 0 18px; font-size:18px; font-weight:700; color:#1f2937; font-family:'DM Sans',sans-serif; }
	.rm-earnings-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:20px; }
	.rm-earnings-card { text-align:center; padding:8px 0; }
	.rm-earnings-card .rm-amount { font-size:24px; font-weight:700; color:#1f2937; font-family:'DM Sans',sans-serif; }
	.rm-earnings-card .rm-label { font-size:13px; color:#6b7280; margin-top:6px; font-family:'DM Sans',sans-serif; }
	.rm-earnings-card .rm-amount--teal { color:#0d9488; }
	</style>
	<div class="rm-earnings-widget">
		<h3><?php echo $rm_t( 'My Earnings', 'Mes Revenus' ); ?></h3>
		<div class="rm-earnings-grid">
			<div class="rm-earnings-card"><div class="rm-amount rm-amount--teal"><?php echo $fmt( $data['available'] ); ?></div><div class="rm-label"><?php echo $rm_t( 'Available (paid out)', 'Disponible (versé)' ); ?></div></div>
			<div class="rm-earnings-card"><div class="rm-amount"><?php echo $fmt( $data['escrow'] ); ?></div><div class="rm-label"><?php echo $rm_t( 'In escrow', 'En séquestre' ); ?></div></div>
			<div class="rm-earnings-card"><div class="rm-amount"><?php echo $fmt( $data['total'] ); ?></div><div class="rm-label"><?php echo $rm_t( 'Total earned', 'Total gagné' ); ?></div></div>
			<div class="rm-earnings-card"><div class="rm-amount" style="font-size:14px;"><?php echo $next; ?></div><div class="rm-label"><?php echo $rm_t( 'Next payout', 'Prochain versement' ); ?></div></div>
		</div>
	</div>
	<?php
	return ob_get_clean();
} );

// =========================================================================
// 12. SHORTCODE: [rm_my_payments] — Full transaction table
// =========================================================================

add_shortcode( 'rm_my_payments', function() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return '';
	}
	$user = get_userdata( $user_id );
	if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
		return '';
	}

	$data = rm_get_coach_earnings();
	if ( ! $data ) {
		return '';
	}

	$fmt = function( $v ) { return number_format( $v, 0, ',', ' ' ) . '&nbsp;&euro;'; };

	ob_start();
	?>
	<style>
	.rm-payments-table { width:100%; border-collapse:collapse; font-size:14px; font-family:'DM Sans',sans-serif; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,0.06); border:1px solid #e5e7eb; }
	.rm-payments-table th { text-align:left; padding:14px 16px; border-bottom:2px solid #e5e7eb; font-weight:700; color:#374151; font-size:13px; background:#f9fafb; white-space:nowrap; }
	.rm-payments-table td { padding:14px 16px; border-bottom:1px solid #f3f4f6; white-space:nowrap; }
	.rm-payments-table .rm-tx-row { cursor:pointer; transition:background 0.15s ease; }
	.rm-payments-table .rm-tx-row:hover td { background:#f0fdfa; }
	.rm-payments-table .rm-tx-row td:nth-child(2) { white-space:normal; }
	.rm-pay-status { display:inline-block; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600; font-family:'DM Sans',sans-serif; white-space:nowrap; }
	.rm-pay-status--escrow { background:#fef3c7; color:#92400e; }
	.rm-pay-status--paid { background:#d1fae5; color:#065f46; }
	.rm-pay-status--cancelled { background:#fee2e2; color:#991b1b; }
	.rm-pay-status--error { background:#fef3c7; color:#dc2626; }
	.rm-tx-detail { display:none; }
	.rm-tx-detail.rm-open { display:table-row; }
	.rm-tx-detail td { padding:0; border-bottom:1px solid #e5e7eb; background:#f9fafb; }
	.rm-tx-detail-inner { padding:16px 24px; display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px 24px; font-family:'DM Sans',sans-serif; }
	.rm-tx-detail-item { font-size:13px; }
	.rm-tx-detail-item .rm-detail-label { color:#6b7280; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px; }
	.rm-tx-detail-item .rm-detail-value { color:#1f2937; font-weight:600; }
	.rm-tx-chevron { display:inline-block; width:16px; text-align:center; color:#9ca3af; transition:transform 0.2s ease; font-size:12px; margin-right:8px; }
	.rm-tx-row.rm-expanded .rm-tx-chevron { transform:rotate(90deg); color:#0d9488; }
	</style>
	<?php
	$rm_is_fr = apply_filters( 'wpml_current_language', null ) === 'fr';
	$rm_t = function( $en, $fr ) use ( $rm_is_fr ) { return $rm_is_fr ? $fr : $en; };
	?>
	<?php if ( empty( $data['transactions'] ) ) : ?>
		<p style="color:#6b7280;font-family:'DM Sans',sans-serif;font-size:14px;"><?php echo $rm_t( 'No transactions yet. Your payments will appear here when riders book your camps.', 'Aucune transaction pour le moment. Vos paiements apparaîtront ici quand des riders réserveront vos camps.' ); ?></p>
	<?php else : ?>
		<table class="rm-payments-table">
			<thead><tr><th><?php echo $rm_t( 'Date', 'Date' ); ?></th><th><?php echo $rm_t( 'Camp', 'Camp' ); ?></th><th><?php echo $rm_t( 'Rider', 'Rider' ); ?></th><th><?php echo $rm_t( 'Total', 'Total' ); ?></th><th><?php echo $rm_t( 'My Share', 'Ma part' ); ?></th><th><?php echo $rm_t( 'Hotel', 'Hôtel' ); ?></th><th><?php echo $rm_t( 'Status', 'Statut' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $data['transactions'] as $i => $tx ) :
				$status_label = $tx['status'] === 'escrow' ? $rm_t( 'In escrow', 'En séquestre' ) :
								( $tx['status'] === 'paid' ? $rm_t( 'Paid', 'Versé' ) . ( $tx['payout_date'] ? ' ' . esc_html( $tx['payout_date'] ) : '' ) :
								( $tx['status'] === 'cancelled' ? $rm_t( 'Cancelled', 'Annulé' ) . ( $tx['refund_pct'] ? ' (' . $tx['refund_pct'] . '%)' : '' ) : $rm_t( 'Error', 'Erreur' ) ) );
				$camp_url = $tx['camp_id'] ? get_permalink( $tx['camp_id'] ) : '';
			?>
				<tr class="rm-tx-row" data-tx="<?php echo $i; ?>">
					<td><span class="rm-tx-chevron">&#9654;</span><?php echo esc_html( $tx['date'] ); ?></td>
					<td><?php echo esc_html( $tx['camp'] ); ?></td>
					<td><?php echo esc_html( $tx['rider'] ); ?></td>
					<td><?php echo $fmt( $tx['total'] ); ?></td>
					<td><strong><?php echo $fmt( $tx['coach_amount'] ); ?></strong></td>
					<td><?php echo $tx['hotel_amount'] > 0 ? $fmt( $tx['hotel_amount'] ) : '—'; ?></td>
					<td><span class="rm-pay-status rm-pay-status--<?php echo esc_attr( $tx['status'] ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
				</tr>
				<tr class="rm-tx-detail" data-tx-detail="<?php echo $i; ?>">
					<td colspan="7">
						<div class="rm-tx-detail-inner">
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Order', 'Commande' ); ?></div>
								<div class="rm-detail-value">#<?php echo esc_html( $tx['order_number'] ); ?></div>
							</div>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Rider', 'Rider' ); ?></div>
								<div class="rm-detail-value"><?php echo esc_html( $tx['rider_full'] ); ?></div>
							</div>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Email', 'Email' ); ?></div>
								<div class="rm-detail-value"><?php echo esc_html( $tx['rider_email'] ); ?></div>
							</div>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Participants', 'Participants' ); ?></div>
								<div class="rm-detail-value"><?php echo esc_html( $tx['participants'] ); ?></div>
							</div>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Total order', 'Total commande' ); ?></div>
								<div class="rm-detail-value"><?php echo $fmt( $tx['total'] ); ?></div>
							</div>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'My share', 'Ma part' ); ?></div>
								<div class="rm-detail-value" style="color:#0d9488;"><?php echo $fmt( $tx['coach_amount'] ); ?></div>
							</div>
							<?php if ( $tx['hotel_amount'] > 0 ) : ?>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Hotel share', 'Part hôtel' ); ?></div>
								<div class="rm-detail-value"><?php echo $fmt( $tx['hotel_amount'] ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( $tx['payout_date'] ) : ?>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Payout date', 'Date versement' ); ?></div>
								<div class="rm-detail-value"><?php echo esc_html( $tx['payout_date'] ); ?></div>
							</div>
							<?php endif; ?>
							<?php if ( $camp_url ) : ?>
							<div class="rm-tx-detail-item">
								<div class="rm-detail-label"><?php echo $rm_t( 'Camp', 'Camp' ); ?></div>
								<div class="rm-detail-value"><a href="<?php echo esc_url( $camp_url ); ?>" style="color:#0d9488;text-decoration:none;" target="_blank"><?php echo $rm_t( 'View camp', 'Voir le camp' ); ?> &rarr;</a></div>
							</div>
							<?php endif; ?>
						</div>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<script>
		(function(){
			document.querySelectorAll('.rm-tx-row').forEach(function(row){
				row.addEventListener('click', function(){
					var idx = this.getAttribute('data-tx');
					var detail = document.querySelector('.rm-tx-detail[data-tx-detail="'+idx+'"]');
					if(detail){
						var isOpen = detail.classList.contains('rm-open');
						// Close all others
						document.querySelectorAll('.rm-tx-detail.rm-open').forEach(function(d){ d.classList.remove('rm-open'); });
						document.querySelectorAll('.rm-tx-row.rm-expanded').forEach(function(r){ r.classList.remove('rm-expanded'); });
						if(!isOpen){
							detail.classList.add('rm-open');
							this.classList.add('rm-expanded');
						}
					}
				});
			});
		})();
		</script>
	<?php endif; ?>
	<?php
	return ob_get_clean();
} );
