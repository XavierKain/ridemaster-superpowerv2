# Stripe Connect Onboarding — Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable coaches to connect their Stripe account (Express) and set up admin settings for Stripe API keys, so the platform is ready for payment processing.

**Architecture:** New `class-payments.php` module handles Stripe SDK, coach onboarding flow (redirect to Stripe → webhook callback → store account ID), and admin settings page. Follows existing plugin patterns (class loaded in `ridemaster.php`, hooks in constructor).

**Tech Stack:** Stripe PHP SDK (via Composer), Stripe Connect Express, WordPress Settings API, WP AJAX.

**Spec:** `docs/plans/2026-03-29-payment-system-design.md`

---

## File Structure

| File | Action | Responsibility |
|------|--------|---------------|
| `composer.json` | Create | Stripe PHP SDK dependency |
| `vendor/` | Generated | Composer autoload |
| `ridemaster.php` | Modify | Add composer autoload + require class-payments + instantiate |
| `includes/class-payments.php` | Create | Stripe SDK init, admin settings page, coach onboarding AJAX, webhooks |
| `includes/class-camp.php` | Modify | Block camp publication if coach Stripe not connected |
| `.gitignore` | Create/Modify | Exclude vendor/ (or include it — see Task 1) |

---

## Task 1: Install Stripe PHP SDK via Composer

**Files:**
- Create: `plugins/ridemaster/composer.json`
- Generated: `plugins/ridemaster/vendor/`

- [ ] **Step 1: Initialize Composer in the plugin directory**

```bash
cd plugins/ridemaster
composer init --name="ridemaster/ridemaster" --type="wordpress-plugin" --no-interaction
```

- [ ] **Step 2: Require Stripe SDK**

```bash
composer require stripe/stripe-php
```

- [ ] **Step 3: Verify installation**

```bash
ls vendor/stripe/stripe-php/lib/Stripe.php
```

Expected: File exists.

- [ ] **Step 4: Add autoload to ridemaster.php**

In `plugins/ridemaster/ridemaster.php`, add after the `ABSPATH` check, before constants:

```php
// Composer autoload (Stripe SDK).
$autoload = RM_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}
```

- [ ] **Step 5: Add vendor/ to the ZIP build process**

The `vendor/` directory must be included in the plugin ZIP for deployment. Verify the build command includes it.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock vendor/ ridemaster.php
git commit -m "feat: add Stripe PHP SDK via Composer"
```

> **Note:** We commit `vendor/` because the production server doesn't run Composer. This is standard for WordPress plugins distributed as ZIPs.

---

## Task 2: Create class-payments.php — Stripe Init & Admin Settings Page

**Files:**
- Create: `plugins/ridemaster/includes/class-payments.php`
- Modify: `plugins/ridemaster/ridemaster.php` (add require + instantiate)

- [ ] **Step 1: Create the class skeleton with constructor hooks**

Create `plugins/ridemaster/includes/class-payments.php`:

```php
<?php
/**
 * RM_Payments — Stripe Connect integration, payment orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Payments {

    /** Stripe API mode: 'test' or 'live'. */
    private $mode;

    /** Stripe Secret Key (resolved from mode). */
    private $secret_key;

    public function __construct() {
        // Admin settings page.
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Initialize Stripe SDK with the correct key.
        $this->mode       = get_option( 'rm_stripe_mode', 'test' );
        $this->secret_key = $this->mode === 'live'
            ? get_option( 'rm_stripe_live_secret_key', '' )
            : get_option( 'rm_stripe_test_secret_key', '' );

        if ( $this->secret_key && class_exists( '\Stripe\Stripe' ) ) {
            \Stripe\Stripe::setApiKey( $this->secret_key );
        }
    }

    /**
     * Get the current Stripe mode.
     */
    public function get_mode() {
        return $this->mode;
    }

    /**
     * Check if Stripe is configured (has API keys).
     */
    public function is_configured() {
        return ! empty( $this->secret_key );
    }
}
```

- [ ] **Step 2: Register in ridemaster.php**

Add to the requires section:
```php
require_once RM_PLUGIN_DIR . 'includes/class-payments.php';
```

Add to the instantiation section:
```php
new RM_Payments();
```

- [ ] **Step 3: Add the admin menu registration method**

Add to `class-payments.php`:

```php
/**
 * Register the RideMaster settings menu.
 */
public function register_admin_menu() {
    add_menu_page(
        'RideMaster',
        'RideMaster',
        'manage_options',
        'ridemaster',
        [ $this, 'render_settings_page' ],
        'dashicons-palmtree',
        30
    );

    add_submenu_page(
        'ridemaster',
        'Settings',
        'Settings',
        'manage_options',
        'ridemaster',
        [ $this, 'render_settings_page' ]
    );

    add_submenu_page(
        'ridemaster',
        'Payments',
        'Payments',
        'manage_options',
        'ridemaster-payments',
        [ $this, 'render_payments_page' ]
    );
}
```

- [ ] **Step 4: Register the settings fields**

Add to `class-payments.php`:

```php
/**
 * Register all Stripe settings with the WordPress Settings API.
 */
public function register_settings() {
    // Stripe section.
    add_settings_section( 'rm_stripe_section', 'Stripe Connect', null, 'ridemaster' );

    // Mode toggle.
    register_setting( 'ridemaster_settings', 'rm_stripe_mode', [
        'type'              => 'string',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ 'test', 'live' ], true ) ? $val : 'test';
        },
        'default'           => 'test',
    ] );
    add_settings_field( 'rm_stripe_mode', 'Mode', [ $this, 'render_mode_field' ], 'ridemaster', 'rm_stripe_section' );

    // API keys.
    $keys = [
        'rm_stripe_test_publishable_key' => 'Test Publishable Key',
        'rm_stripe_test_secret_key'      => 'Test Secret Key',
        'rm_stripe_live_publishable_key' => 'Live Publishable Key',
        'rm_stripe_live_secret_key'      => 'Live Secret Key',
        'rm_stripe_webhook_secret'       => 'Webhook Secret',
    ];

    foreach ( $keys as $option_name => $label ) {
        register_setting( 'ridemaster_settings', $option_name, [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        add_settings_field( $option_name, $label, function() use ( $option_name ) {
            $value = get_option( $option_name, '' );
            $type  = strpos( $option_name, 'secret' ) !== false ? 'password' : 'text';
            printf(
                '<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off" />',
                esc_attr( $type ),
                esc_attr( $option_name ),
                esc_attr( $value )
            );
        }, 'ridemaster', 'rm_stripe_section' );
    }

    // Commission section.
    add_settings_section( 'rm_commission_section', 'Commission', null, 'ridemaster' );

    register_setting( 'ridemaster_settings', 'rm_commission_rate', [
        'type'              => 'number',
        'sanitize_callback' => function( $val ) {
            $val = floatval( $val );
            return max( 0, min( 100, $val ) );
        },
        'default'           => 0,
    ] );
    add_settings_field( 'rm_commission_rate', 'Commission Rate (%)', function() {
        $value = get_option( 'rm_commission_rate', 0 );
        printf(
            '<input type="number" name="rm_commission_rate" value="%s" min="0" max="100" step="0.1" class="small-text" /> %%',
            esc_attr( $value )
        );
        echo '<p class="description">Applied to new orders only. Set to 0 for launch.</p>';
    }, 'ridemaster', 'rm_commission_section' );

    // Payout section.
    add_settings_section( 'rm_payout_section', 'Payouts', null, 'ridemaster' );

    register_setting( 'ridemaster_settings', 'rm_payout_delay_days', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 15,
    ] );
    add_settings_field( 'rm_payout_delay_days', 'Payout Delay (days before camp)', function() {
        $value = get_option( 'rm_payout_delay_days', 15 );
        printf(
            '<input type="number" name="rm_payout_delay_days" value="%s" min="1" max="60" class="small-text" /> days',
            esc_attr( $value )
        );
        echo '<p class="description">Transfers are triggered this many days before the camp start date.</p>';
    }, 'ridemaster', 'rm_payout_section' );

    // Insurance section.
    add_settings_section( 'rm_insurance_section', 'Insurance & Compliance', null, 'ridemaster' );

    register_setting( 'ridemaster_settings', 'rm_insurance_pdf_id', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ] );
    add_settings_field( 'rm_insurance_pdf_id', 'Insurance Notice PDF', [ $this, 'render_pdf_upload_field' ], 'ridemaster', 'rm_insurance_section' );

    register_setting( 'ridemaster_settings', 'rm_insurance_label', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => 'Individual Accident Insurance included',
    ] );
    add_settings_field( 'rm_insurance_label', 'Insurance Label', function() {
        $value = get_option( 'rm_insurance_label', 'Individual Accident Insurance included' );
        printf(
            '<input type="text" name="rm_insurance_label" value="%s" class="regular-text" />',
            esc_attr( $value )
        );
    }, 'ridemaster', 'rm_insurance_section' );

    register_setting( 'ridemaster_settings', 'rm_cgv_page_id', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    ] );
    add_settings_field( 'rm_cgv_page_id', 'Terms & Conditions Page', function() {
        $value = get_option( 'rm_cgv_page_id', 0 );
        wp_dropdown_pages( [
            'name'             => 'rm_cgv_page_id',
            'selected'         => $value,
            'show_option_none' => '— Select a page —',
        ] );
    }, 'ridemaster', 'rm_insurance_section' );
}
```

- [ ] **Step 5: Add the settings page render methods**

Add to `class-payments.php`:

```php
/**
 * Render the main settings page.
 */
public function render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>RideMaster Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'ridemaster_settings' );
            do_settings_sections( 'ridemaster' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Render the mode toggle field.
 */
public function render_mode_field() {
    $mode = get_option( 'rm_stripe_mode', 'test' );
    ?>
    <label>
        <input type="radio" name="rm_stripe_mode" value="test" <?php checked( $mode, 'test' ); ?> />
        Test
    </label>
    &nbsp;&nbsp;
    <label>
        <input type="radio" name="rm_stripe_mode" value="live" <?php checked( $mode, 'live' ); ?> />
        Live
    </label>
    <p class="description">Use Test mode during development. Switch to Live when ready for real payments.</p>
    <?php
}

/**
 * Render PDF upload field for insurance notice.
 */
public function render_pdf_upload_field() {
    $pdf_id = get_option( 'rm_insurance_pdf_id', 0 );
    $pdf_url = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
    $pdf_name = $pdf_id ? basename( get_attached_file( $pdf_id ) ) : '';
    ?>
    <div id="rm-insurance-pdf-wrap">
        <?php if ( $pdf_url ) : ?>
            <p><a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank"><?php echo esc_html( $pdf_name ); ?></a></p>
        <?php endif; ?>
        <input type="hidden" name="rm_insurance_pdf_id" id="rm_insurance_pdf_id" value="<?php echo esc_attr( $pdf_id ); ?>" />
        <button type="button" class="button" id="rm-upload-insurance-pdf">
            <?php echo $pdf_id ? 'Change PDF' : 'Upload PDF'; ?>
        </button>
        <?php if ( $pdf_id ) : ?>
            <button type="button" class="button" id="rm-remove-insurance-pdf">Remove</button>
        <?php endif; ?>
    </div>
    <script>
    jQuery(function($) {
        $('#rm-upload-insurance-pdf').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({ title: 'Select Insurance PDF', library: { type: 'application/pdf' }, multiple: false });
            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#rm_insurance_pdf_id').val(attachment.id);
                $(e.target).text('Change PDF');
            });
            frame.open();
        });
        $('#rm-remove-insurance-pdf').on('click', function() {
            $('#rm_insurance_pdf_id').val('0');
            $(this).remove();
        });
    });
    </script>
    <?php
    wp_enqueue_media();
}

/**
 * Render the Payments admin page (placeholder for now).
 */
public function render_payments_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>RideMaster Payments</h1>
        <p>Payment dashboard will be available once the checkout system is implemented.</p>
    </div>
    <?php
}
```

- [ ] **Step 6: Commit**

```bash
git add includes/class-payments.php ridemaster.php
git commit -m "feat: add RideMaster settings page with Stripe, commission, payout, and insurance config"
```

---

## Task 3: Coach Stripe Onboarding — Connect Flow

**Files:**
- Modify: `plugins/ridemaster/includes/class-payments.php`

- [ ] **Step 1: Add AJAX actions for onboarding in the constructor**

Add to `__construct()`:

```php
// Coach Stripe onboarding.
add_action( 'wp_ajax_rm_stripe_connect', [ $this, 'ajax_stripe_connect' ] );
add_action( 'wp_ajax_rm_stripe_disconnect', [ $this, 'ajax_stripe_disconnect' ] );

// Stripe OAuth return handler.
add_action( 'template_redirect', [ $this, 'handle_stripe_return' ] );
```

- [ ] **Step 2: Implement the connect redirect (coach clicks "Connect with Stripe")**

Add to `class-payments.php`:

```php
/**
 * AJAX: Generate Stripe Express onboarding link and return it.
 * Coach clicks "Connect with Stripe" → JS calls this → redirected to Stripe.
 */
public function ajax_stripe_connect() {
    check_ajax_referer( 'rm_stripe_connect', 'nonce' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( 'Not logged in.' );
    }

    $user = get_userdata( $user_id );
    if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
        wp_send_json_error( 'Not authorized.' );
    }

    if ( ! $this->is_configured() ) {
        wp_send_json_error( 'Stripe is not configured. Please contact the administrator.' );
    }

    try {
        // Check if coach already has a Stripe account (reconnect scenario).
        $existing_account_id = get_user_meta( $user_id, 'stripe_account_id', true );

        if ( ! $existing_account_id ) {
            // Create a new Express account.
            $account = \Stripe\Account::create( [
                'type'         => 'express',
                'country'      => 'FR', // Default, Stripe will let user change.
                'email'        => $user->user_email,
                'capabilities' => [
                    'transfers' => [ 'requested' => true ],
                ],
                'business_type' => 'individual',
                'metadata'      => [
                    'rm_user_id'  => $user_id,
                    'rm_coach_id' => get_user_meta( $user_id, 'coach_post_id', true ),
                ],
            ] );
            $existing_account_id = $account->id;
            update_user_meta( $user_id, 'stripe_account_id', $existing_account_id );
        }

        // Create an Account Link for onboarding.
        $return_url  = add_query_arg( 'rm_stripe_return', '1', home_url( '/coach-dashboard/' ) );
        $refresh_url = add_query_arg( 'rm_stripe_refresh', '1', home_url( '/coach-dashboard/' ) );

        $account_link = \Stripe\AccountLink::create( [
            'account'     => $existing_account_id,
            'refresh_url' => $refresh_url,
            'return_url'  => $return_url,
            'type'        => 'account_onboarding',
        ] );

        wp_send_json_success( [ 'url' => $account_link->url ] );

    } catch ( \Stripe\Exception\ApiErrorException $e ) {
        error_log( '[RM Payments] Stripe Connect error: ' . $e->getMessage() );
        wp_send_json_error( 'Stripe error: ' . $e->getMessage() );
    }
}
```

- [ ] **Step 3: Implement the return handler (coach comes back from Stripe)**

Add to `class-payments.php`:

```php
/**
 * Handle the return from Stripe onboarding.
 * Checks account status and updates user meta.
 */
public function handle_stripe_return() {
    if ( ! isset( $_GET['rm_stripe_return'] ) && ! isset( $_GET['rm_stripe_refresh'] ) ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    $account_id = get_user_meta( $user_id, 'stripe_account_id', true );
    if ( ! $account_id || ! $this->is_configured() ) {
        return;
    }

    // If refresh URL was hit, redirect back to start onboarding again.
    if ( isset( $_GET['rm_stripe_refresh'] ) ) {
        try {
            $account_link = \Stripe\AccountLink::create( [
                'account'     => $account_id,
                'refresh_url' => add_query_arg( 'rm_stripe_refresh', '1', home_url( '/coach-dashboard/' ) ),
                'return_url'  => add_query_arg( 'rm_stripe_return', '1', home_url( '/coach-dashboard/' ) ),
                'type'        => 'account_onboarding',
            ] );
            wp_redirect( $account_link->url );
            exit;
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( '[RM Payments] Stripe refresh error: ' . $e->getMessage() );
        }
    }

    // Return URL: check account status.
    try {
        $account = \Stripe\Account::retrieve( $account_id );
        $complete = $account->charges_enabled && $account->payouts_enabled;
        update_user_meta( $user_id, 'stripe_onboarding_complete', $complete ? '1' : '0' );
        update_user_meta( $user_id, 'stripe_account_status', $complete ? 'active' : 'pending' );
    } catch ( \Stripe\Exception\ApiErrorException $e ) {
        error_log( '[RM Payments] Stripe account check error: ' . $e->getMessage() );
    }

    // Redirect to clean dashboard URL (remove query params).
    wp_redirect( home_url( '/coach-dashboard/' ) );
    exit;
}
```

- [ ] **Step 4: Implement the disconnect handler**

Add to `class-payments.php`:

```php
/**
 * AJAX: Disconnect coach from Stripe.
 */
public function ajax_stripe_disconnect() {
    check_ajax_referer( 'rm_stripe_connect', 'nonce' );

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        wp_send_json_error( 'Not logged in.' );
    }

    delete_user_meta( $user_id, 'stripe_account_id' );
    delete_user_meta( $user_id, 'stripe_onboarding_complete' );
    delete_user_meta( $user_id, 'stripe_account_status' );

    wp_send_json_success( [ 'message' => 'Stripe account disconnected.' ] );
}
```

- [ ] **Step 5: Commit**

```bash
git add includes/class-payments.php
git commit -m "feat: add Stripe Connect Express onboarding flow for coaches"
```

---

## Task 4: Stripe Webhook Handler

**Files:**
- Modify: `plugins/ridemaster/includes/class-payments.php`

- [ ] **Step 1: Register the webhook REST endpoint in the constructor**

Add to `__construct()`:

```php
// Stripe webhook endpoint.
add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );
```

- [ ] **Step 2: Implement the webhook endpoint**

Add to `class-payments.php`:

```php
/**
 * Register the Stripe webhook REST endpoint.
 * URL: /wp-json/ridemaster/v1/stripe-webhook
 */
public function register_webhook_endpoint() {
    register_rest_route( 'ridemaster/v1', '/stripe-webhook', [
        'methods'             => 'POST',
        'callback'            => [ $this, 'handle_webhook' ],
        'permission_callback' => '__return_true', // Stripe signs the request; we verify the signature.
    ] );
}

/**
 * Handle incoming Stripe webhook events.
 */
public function handle_webhook( \WP_REST_Request $request ) {
    $payload   = $request->get_body();
    $sig       = $request->get_header( 'Stripe-Signature' );
    $secret    = get_option( 'rm_stripe_webhook_secret', '' );

    if ( ! $secret ) {
        return new \WP_REST_Response( [ 'error' => 'Webhook secret not configured.' ], 400 );
    }

    try {
        $event = \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
    } catch ( \Stripe\Exception\SignatureVerificationException $e ) {
        error_log( '[RM Payments] Webhook signature verification failed: ' . $e->getMessage() );
        return new \WP_REST_Response( [ 'error' => 'Invalid signature.' ], 400 );
    } catch ( \Exception $e ) {
        error_log( '[RM Payments] Webhook error: ' . $e->getMessage() );
        return new \WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
    }

    // Dispatch event to handler.
    switch ( $event->type ) {
        case 'account.updated':
            $this->on_account_updated( $event->data->object );
            break;

        // Future events: payment_intent.succeeded, charge.refunded, etc.
        default:
            error_log( '[RM Payments] Unhandled webhook event: ' . $event->type );
            break;
    }

    return new \WP_REST_Response( [ 'received' => true ], 200 );
}

/**
 * Handle account.updated webhook — update coach Stripe status.
 */
private function on_account_updated( $account ) {
    // Find the WordPress user linked to this Stripe account.
    $users = get_users( [
        'meta_key'   => 'stripe_account_id',
        'meta_value' => $account->id,
        'number'     => 1,
    ] );

    if ( empty( $users ) ) {
        error_log( '[RM Payments] account.updated: no WP user found for Stripe account ' . $account->id );
        return;
    }

    $user_id  = $users[0]->ID;
    $complete = $account->charges_enabled && $account->payouts_enabled;

    update_user_meta( $user_id, 'stripe_onboarding_complete', $complete ? '1' : '0' );
    update_user_meta( $user_id, 'stripe_account_status', $complete ? 'active' : 'pending' );

    error_log( '[RM Payments] account.updated: user ' . $user_id . ' → ' . ( $complete ? 'active' : 'pending' ) );
}
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-payments.php
git commit -m "feat: add Stripe webhook endpoint for account.updated events"
```

---

## Task 5: Coach Dashboard — Stripe Connect Button

**Files:**
- Modify: `plugins/ridemaster/includes/ui-tweaks.php` (dashboard footer JS section)

- [ ] **Step 1: Inject the Stripe connect widget on the coach dashboard**

Add a new section in `ui-tweaks.php` — a `wp_footer` action that runs on the coach dashboard homepage. It injects a "Stripe Status" widget showing:
- If not connected: "Connect with Stripe" button
- If connected but pending: "Complete Stripe Setup" button
- If connected and active: green badge "Stripe Connected" + "Disconnect" link

```php
// =========================================================================
// 10. COACH DASHBOARD — STRIPE CONNECT WIDGET
// =========================================================================

add_action( 'wp_footer', function() {
    // Only on coach dashboard homepage (not sub-pages).
    if ( strpos( $_SERVER['REQUEST_URI'], '/coach-dashboard/' ) === false ) {
        return;
    }
    if ( strpos( $_SERVER['REQUEST_URI'], '/profile' ) !== false
        || strpos( $_SERVER['REQUEST_URI'], '/create-' ) !== false ) {
        return;
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }
    $user = get_userdata( $user_id );
    if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
        return;
    }

    $stripe_account_id = get_user_meta( $user_id, 'stripe_account_id', true );
    $stripe_complete   = get_user_meta( $user_id, 'stripe_onboarding_complete', true ) === '1';
    $nonce             = wp_create_nonce( 'rm_stripe_connect' );
    $ajax_url          = admin_url( 'admin-ajax.php' );
    ?>
    <style>
    .rm-stripe-widget {
        background: #fff;
        border-radius: 12px;
        padding: 20px 24px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
    }
    .rm-stripe-widget h3 {
        margin: 0 0 12px;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
    }
    .rm-stripe-status {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }
    .rm-stripe-status--active {
        background: #d1fae5;
        color: #065f46;
    }
    .rm-stripe-status--pending {
        background: #fef3c7;
        color: #92400e;
    }
    .rm-stripe-status--disconnected {
        background: #f3f4f6;
        color: #6b7280;
    }
    .rm-stripe-connect-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-top: 12px;
        padding: 10px 20px;
        background: #635bff;
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .rm-stripe-connect-btn:hover {
        background: #5147e5;
    }
    .rm-stripe-disconnect {
        display: inline-block;
        margin-top: 8px;
        font-size: 12px;
        color: #9ca3af;
        cursor: pointer;
        border: none;
        background: none;
        text-decoration: underline;
    }
    .rm-stripe-disconnect:hover {
        color: #ef4444;
    }
    </style>
    <script>
    (function() {
        var nonce = <?php echo wp_json_encode( $nonce ); ?>;
        var ajaxUrl = <?php echo wp_json_encode( $ajax_url ); ?>;
        var isConnected = <?php echo $stripe_account_id ? 'true' : 'false'; ?>;
        var isComplete = <?php echo $stripe_complete ? 'true' : 'false'; ?>;

        function init() {
            // Find the dashboard content area to prepend the widget.
            // Look for a common container on the dashboard page.
            var container = document.querySelector('.elementor-widget-wrap') ||
                            document.querySelector('.e-con-inner') ||
                            document.querySelector('.coach-dashboard-content') ||
                            document.querySelector('main') ||
                            document.querySelector('.site-main');
            if ( ! container ) return;

            // Check if widget already exists.
            if ( document.getElementById('rm-stripe-widget') ) return;

            var widget = document.createElement('div');
            widget.id = 'rm-stripe-widget';
            widget.className = 'rm-stripe-widget';

            if ( isConnected && isComplete ) {
                widget.innerHTML =
                    '<h3>Stripe Payments</h3>' +
                    '<span class="rm-stripe-status rm-stripe-status--active">&#10003; Stripe Connected</span>' +
                    '<br><button type="button" class="rm-stripe-disconnect" id="rm-stripe-disconnect">Disconnect</button>';
            } else if ( isConnected && ! isComplete ) {
                widget.innerHTML =
                    '<h3>Stripe Payments</h3>' +
                    '<span class="rm-stripe-status rm-stripe-status--pending">&#9888; Setup incomplete</span>' +
                    '<br><button type="button" class="rm-stripe-connect-btn" id="rm-stripe-connect-btn">Complete Stripe Setup</button>';
            } else {
                widget.innerHTML =
                    '<h3>Stripe Payments</h3>' +
                    '<span class="rm-stripe-status rm-stripe-status--disconnected">Not connected</span>' +
                    '<p style="margin:8px 0 0;font-size:13px;color:#6b7280;">Connect your Stripe account to receive payments from your camps.</p>' +
                    '<button type="button" class="rm-stripe-connect-btn" id="rm-stripe-connect-btn">Connect with Stripe</button>';
            }

            container.prepend(widget);

            // Connect button handler.
            var connectBtn = document.getElementById('rm-stripe-connect-btn');
            if ( connectBtn ) {
                connectBtn.addEventListener('click', function() {
                    connectBtn.disabled = true;
                    connectBtn.textContent = 'Redirecting...';
                    var fd = new FormData();
                    fd.append('action', 'rm_stripe_connect');
                    fd.append('nonce', nonce);
                    fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(resp) {
                            if ( resp.success && resp.data.url ) {
                                window.location.href = resp.data.url;
                            } else {
                                var msg = resp.data || 'Error connecting to Stripe.';
                                alert(msg);
                                connectBtn.disabled = false;
                                connectBtn.textContent = 'Connect with Stripe';
                            }
                        })
                        .catch(function() {
                            alert('Network error. Please try again.');
                            connectBtn.disabled = false;
                            connectBtn.textContent = 'Connect with Stripe';
                        });
                });
            }

            // Disconnect handler.
            var disconnectBtn = document.getElementById('rm-stripe-disconnect');
            if ( disconnectBtn ) {
                disconnectBtn.addEventListener('click', function() {
                    if ( ! confirm('Are you sure you want to disconnect your Stripe account?') ) return;
                    var fd = new FormData();
                    fd.append('action', 'rm_stripe_disconnect');
                    fd.append('nonce', nonce);
                    fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function() { window.location.reload(); });
                });
            }
        }

        if ( document.readyState === 'complete' ) {
            setTimeout( init, 300 );
        } else {
            window.addEventListener('load', function() { setTimeout( init, 300 ); });
        }
    })();
    </script>
    <?php
} );
```

- [ ] **Step 2: Commit**

```bash
git add includes/ui-tweaks.php
git commit -m "feat: add Stripe Connect widget on coach dashboard"
```

---

## Task 6: Block Camp Publication Without Stripe

**Files:**
- Modify: `plugins/ridemaster/includes/class-camp.php`

- [ ] **Step 1: Add Stripe check in init_new_camp**

In `class-camp.php`, inside `init_new_camp()`, after the coach_post_id check (around line 177), add:

```php
// Block camp creation if coach hasn't connected Stripe.
$stripe_complete = get_user_meta( $current_user_id, 'stripe_onboarding_complete', true );
if ( $stripe_complete !== '1' ) {
    // Set the product to draft so it's not publicly visible.
    wp_update_post( [
        'ID'          => $post_id,
        'post_status' => 'draft',
    ] );
    update_post_meta( $post_id, '_rm_blocked_reason', 'stripe_not_connected' );
    self::log( 'RideMaster: Camp ' . $post_id . ' set to draft — coach Stripe not connected.' );
}
```

- [ ] **Step 2: Add a notice on the create-camp page if Stripe is not connected**

In `ui-tweaks.php`, in the create-camp page section (section 5), add a check:

```php
// Show warning if coach hasn't connected Stripe.
$user_id = get_current_user_id();
if ( $user_id ) {
    $stripe_complete = get_user_meta( $user_id, 'stripe_onboarding_complete', true );
    if ( $stripe_complete !== '1' ) {
        ?>
        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:16px;margin-bottom:20px;font-size:14px;color:#92400e;">
            <strong>&#9888; Stripe not connected</strong> — You need to connect your Stripe account before you can publish a camp.
            <a href="/coach-dashboard/" style="color:#92400e;font-weight:600;">Go to Dashboard to connect Stripe</a>
        </div>
        <?php
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-camp.php includes/ui-tweaks.php
git commit -m "feat: block camp publication if coach Stripe not connected"
```

---

## Task 7: Admin — Coaches Stripe Status Column

**Files:**
- Modify: `plugins/ridemaster/includes/class-admin.php`

- [ ] **Step 1: Add a "Stripe" column to the coaches list**

In `class-admin.php`, add new hooks in the constructor (or existing hook pattern):

```php
add_filter( 'manage_coach_posts_columns', [ $this, 'add_stripe_column' ], 20 );
add_action( 'manage_coach_posts_custom_column', [ $this, 'render_stripe_column' ], 10, 2 );
```

Implement the methods:

```php
/**
 * Add Stripe status column to coaches list.
 */
public function add_stripe_column( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'rm_coach_status' || $key === 'title' ) {
            $new['rm_stripe'] = 'Stripe';
        }
    }
    if ( ! isset( $new['rm_stripe'] ) ) {
        $new['rm_stripe'] = 'Stripe';
    }
    return $new;
}

/**
 * Render the Stripe status column content.
 */
public function render_stripe_column( $column, $post_id ) {
    if ( $column !== 'rm_stripe' ) {
        return;
    }

    $post = get_post( $post_id );
    if ( ! $post ) {
        echo '—';
        return;
    }

    // Find the user linked to this coach post.
    $user_id = $post->post_author;
    if ( ! $user_id ) {
        echo '—';
        return;
    }

    $stripe_id   = get_user_meta( $user_id, 'stripe_account_id', true );
    $stripe_done = get_user_meta( $user_id, 'stripe_onboarding_complete', true ) === '1';

    if ( ! $stripe_id ) {
        echo '<span style="color:#9ca3af;">Not connected</span>';
    } elseif ( $stripe_done ) {
        echo '<span style="color:#065f46;font-weight:600;">&#10003; Connected</span>';
    } else {
        echo '<span style="color:#92400e;">&#9888; Pending</span>';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/class-admin.php
git commit -m "feat: add Stripe status column to admin coaches list"
```

---

## Task 8: Bump Version & Build ZIP

**Files:**
- Modify: `plugins/ridemaster/ridemaster.php` (version bump)

- [ ] **Step 1: Bump version to 2.1.0**

In `ridemaster.php`, update both version references from `2.0.8` to `2.1.0`.

- [ ] **Step 2: Build the ZIP**

```bash
cd plugins && rm -f ridemaster-2.1.0.zip && \
mkdir -p /tmp/rm-zip && rm -rf /tmp/rm-zip/ridemaster && \
cp -R ridemaster /tmp/rm-zip/ridemaster && \
find /tmp/rm-zip -name ".DS_Store" -delete && \
cd /tmp/rm-zip && \
zip -r "/Users/xavier/VSCode3/Ridemaster superpowerv2/plugins/ridemaster-2.1.0.zip" ridemaster/ && \
rm -rf /tmp/rm-zip
```

- [ ] **Step 3: Final commit**

```bash
git add ridemaster.php
git commit -m "chore: bump RideMaster to v2.1.0 — Stripe Connect onboarding"
```

---

## Post-implementation: Stripe Dashboard Setup

After deploying, you need to configure Stripe:

1. **Stripe Dashboard → Connect Settings** : Enable Express accounts
2. **Stripe Dashboard → Developers → Webhooks** : Add endpoint `https://ridemaster.eu/wp-json/ridemaster/v1/stripe-webhook` with events:
   - `account.updated`
   - (Later: `payment_intent.succeeded`, `charge.refunded`, `transfer.created`)
3. Copy the Webhook Signing Secret → paste in RideMaster Settings
4. Copy API keys (test + live) → paste in RideMaster Settings
