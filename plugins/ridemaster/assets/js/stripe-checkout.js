/**
 * RideMaster Stripe Checkout — Stripe Elements integration.
 */
(function($) {
	'use strict';

	if ( typeof rmStripe === 'undefined' ) return;

	var stripe = Stripe( rmStripe.publishableKey );
	var elements = null;
	var cardElement = null;
	var processing = false;

	function init() {
		var container = document.getElementById('rm-stripe-card-element');
		if ( ! container ) return;

		// Destroy previous instances (WooCommerce AJAX replaces the HTML).
		if ( cardElement ) {
			try { cardElement.unmount(); } catch(e) {}
			try { cardElement.destroy(); } catch(e) {}
			cardElement = null;
		}

		// Recreate elements instance to avoid "Can only create one Element" error.
		elements = stripe.elements();

		cardElement = elements.create('card', {
			style: {
				base: {
					fontSize: '16px',
					color: '#1f2937',
					fontFamily: '"DM Sans", sans-serif',
					'::placeholder': { color: '#9ca3af' }
				},
				invalid: { color: '#dc2626' }
			}
		});
		cardElement.mount('#rm-stripe-card-element');

		cardElement.on('change', function(event) {
			var errEl = document.getElementById('rm-stripe-card-errors');
			if ( errEl ) {
				errEl.textContent = event.error ? event.error.message : '';
			}
		});

		// Intercept WooCommerce checkout form submission.
		var form = $('form.checkout, form#order_review');
		if ( form.length ) {
			form.on('checkout_place_order_ridemaster_stripe', function() {
				if ( processing ) return false;
				processing = true;

				// Create PaymentIntent via AJAX, then confirm with Stripe.js.
				var formData = form.serialize();

				$.ajax({
					url: rmStripe.ajaxUrl,
					type: 'POST',
					data: {
						action: 'rm_create_payment_intent',
						nonce: rmStripe.nonce,
						form_data: formData
					},
					success: function( resp ) {
						if ( ! resp.success ) {
							showError( resp.data || 'Failed to create payment.' );
							processing = false;
							return;
						}

						var clientSecret = resp.data.client_secret;

						stripe.confirmCardPayment( clientSecret, {
							payment_method: { card: cardElement }
						}).then(function( result ) {
							if ( result.error ) {
								showError( result.error.message );
								processing = false;
							} else if ( result.paymentIntent && result.paymentIntent.status === 'succeeded' ) {
								// Set the PI ID in the hidden field and submit.
								$('#rm-stripe-payment-intent-id').val( result.paymentIntent.id );
								form.off('checkout_place_order_ridemaster_stripe');
								form.submit();
							} else {
								showError( 'Unexpected payment status: ' + result.paymentIntent.status );
								processing = false;
							}
						});
					},
					error: function() {
						showError( 'Network error. Please try again.' );
						processing = false;
					}
				});

				return false; // Prevent default form submission.
			});
		}
	}

	function showError( message ) {
		var errEl = document.getElementById('rm-stripe-card-errors');
		if ( errEl ) {
			errEl.textContent = message;
		}
		// Also trigger WooCommerce's error display.
		$( document.body ).trigger( 'checkout_error' );
	}

	$(document).ready(init);
	$(document.body).on('updated_checkout', init);

})(jQuery);
