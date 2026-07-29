/**
 * RideMaster — Replace native date picker with Flatpickr (teal theme)
 * Add this as a PHP snippet in Code Snippets plugin.
 * Only loads on the camp creation page.
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_page( 'create-camp' ) && strpos( $_SERVER['REQUEST_URI'], 'create-camp' ) === false ) {
        return;
    }

    // Load Flatpickr CSS + JS from CDN
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

    // Initialize on JFB date fields + teal theme override
    wp_add_inline_script( 'flatpickr', "
        document.addEventListener('DOMContentLoaded', function() {
            var dateFields = document.querySelectorAll('input.date-field');
            dateFields.forEach(function(input) {
                // Change type from date to text so the native picker doesn't interfere
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
