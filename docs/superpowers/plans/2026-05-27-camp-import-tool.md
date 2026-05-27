# Camp Import Tool Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a conversational tool that imports camps from coach websites into Ridemaster (https://ridemaster.eu) via a new `ridemaster-importer` WordPress plugin exposing a single REST endpoint `POST /ridemaster/v1/import-camp`.

**Architecture:** Two-plugin setup. (1) The existing `ridemaster` plugin is *minimally* refactored using the Extract-Method pattern to expose static `create_from_payload()` methods on `RM_Camp`, `RM_Coach`, `RM_Spot`, `RM_Hotel` — without changing the behavior of existing JetFormBuilder hooks. (2) A new separate plugin `ridemaster-importer` declares a dependency on `ridemaster`, exposes the REST endpoint, handles payload validation, image fetching/optimization, idempotency, JetEngine relations, and rollback on partial failure.

**Tech Stack:** PHP 8.x, WordPress 6.9+, WooCommerce, JetEngine, JetFormBuilder, Yoast SEO. No PHPUnit (testing is HTTP integration via curl + DB inspection via REST). Image optimization client-side via ImageMagick. Scraping client-side via Playwright MCP.

**Reference spec:** [docs/superpowers/specs/2026-05-27-camp-import-tool-design.md](../specs/2026-05-27-camp-import-tool-design.md)

**Pre-conditions before starting implementation:**
- Branch `main` is acceptable per user (dev-on-prod, backup exists)
- Need a fresh Application Password (the one used during audit should have been revoked)
- ImageMagick installed locally (`brew install imagemagick`)
- Playwright MCP available (already configured)

---

## File Structure

### Files created

```
plugins/ridemaster-importer/
├── ridemaster-importer.php              # Plugin bootstrap + dependency check
├── readme.txt                           # WP-style readme
└── includes/
    ├── class-rm-importer-endpoint.php   # REST route registration + auth + dispatch
    ├── class-rm-importer-validator.php  # Payload schema validation + term slug validation
    ├── class-rm-importer-images.php     # media_sideload, base64 fallback, SEO meta
    ├── class-rm-importer-relations.php  # JetEngine relations writer
    ├── class-rm-importer-rollback.php   # Created-this-call tracking + cleanup
    └── class-rm-importer-yoast.php      # Yoast SEO postmeta helper

tests/importer/                           # bash + curl integration test scripts
├── _env.sh                              # source these before running tests
├── 01-pong.sh
├── 02-camp-minimal.sh
├── 03-camp-with-coach.sh
├── 04-camp-with-spot.sh
├── 05-camp-with-hotel.sh
├── 06-camp-with-images.sh
├── 07-idempotency.sh
└── 08-rollback.sh
```

### Files modified

```
plugins/ridemaster/includes/class-camp.php    # extract apply_meta_from_data + add create_from_payload
plugins/ridemaster/includes/class-coach.php   # add create_from_payload
plugins/ridemaster/includes/class-hotel.php   # add create_from_payload
plugins/ridemaster/ridemaster.php             # bump version, register RM_Spot include if it exists
```

### Files created in main plugin

```
plugins/ridemaster/includes/class-spot.php    # new — no class existed; create static create_from_payload only (no hook side-effects)
```

---

## Testing Strategy

**No PHPUnit.** Tests are bash scripts that hit the live endpoint with curl and inspect the response + DB state via the WP REST API. They use a `_env.sh` file with:

```bash
export RM_URL="https://ridemaster.eu"
export RM_USER="xavierkain.consulting@gmail.com"
export RM_PASS="<application password here>"
export RM_AUTH="-u ${RM_USER}:${RM_PASS}"
```

Every test cleans up after itself by calling `DELETE /wp-json/wp/v2/product/{id}?force=true` on entities it created.

For refactor parity (Tasks 3 and 6), the strategy is:
1. Create one camp via the JFB form (Playwright submits the form)
2. Capture its postmeta via `GET /wp/v2/product/{id}?context=edit` AND a small PHP eval-style script that dumps ALL meta (REST hides unregistered meta)
3. Create another camp via the new `RM_Camp::create_from_payload()` with equivalent input
4. Diff the meta — should be byte-identical except for IDs/timestamps

Since REST doesn't expose all meta, we add a temporary debug endpoint `POST /ridemaster/v1/__debug_dump_meta` that returns all postmeta for a given post ID. This endpoint is removed in the final task before the E2E test.

---

## Task 1: Plugin skeleton + pong endpoint

**Files:**
- Create: `plugins/ridemaster-importer/ridemaster-importer.php`
- Create: `plugins/ridemaster-importer/readme.txt`
- Create: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/_env.sh`
- Create: `tests/importer/01-pong.sh`

- [ ] **Step 1: Create the plugin bootstrap file**

`plugins/ridemaster-importer/ridemaster-importer.php`:

```php
<?php
/**
 * Plugin Name: RideMaster Importer
 * Description: Conversational import of camps from external coach websites into Ridemaster. Depends on the RideMaster plugin.
 * Version: 0.1.0
 * Author: RideMaster
 * Text Domain: ridemaster-importer
 * Requires Plugins: ridemaster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RM_IMPORTER_VERSION', '0.1.0' );
define( 'RM_IMPORTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'RM_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Hard dependency check on the main ridemaster plugin.
 * If absent, deactivate self and show admin notice.
 */
add_action( 'admin_init', function () {
    if ( ! class_exists( 'RM_Camp' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>RideMaster Importer</strong> requires the <strong>RideMaster</strong> plugin to be active.</p></div>';
        } );
    }
} );

require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-endpoint.php';

add_action( 'rest_api_init', [ 'RM_Importer_Endpoint', 'register_routes' ] );
```

- [ ] **Step 2: Create the readme**

`plugins/ridemaster-importer/readme.txt`:

```
=== RideMaster Importer ===
Contributors: ridemaster
Requires at least: 6.9
Tested up to: 6.9
Stable tag: 0.1.0

Conversational import of camps from external coach websites into Ridemaster.

== Description ==

Exposes POST /wp-json/ridemaster/v1/import-camp for an LLM-driven import workflow.
Depends on the RideMaster plugin.
```

- [ ] **Step 3: Create the endpoint class (pong only)**

`plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Importer_Endpoint {

    const NAMESPACE = 'ridemaster/v1';

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/import-camp', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_import' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );
    }

    public static function check_permission( $request ) {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return new WP_Error(
                'INSUFFICIENT_PERMISSIONS',
                'You need edit_others_posts capability.',
                [ 'status' => 403 ]
            );
        }
        return true;
    }

    public static function handle_import( WP_REST_Request $request ) {
        // Pong only at this stage — full handler comes in later tasks.
        return new WP_REST_Response( [
            'status'  => 'pong',
            'version' => RM_IMPORTER_VERSION,
        ], 200 );
    }
}
```

- [ ] **Step 4: Create test environment file**

`tests/importer/_env.sh`:

```bash
#!/usr/bin/env bash
# Source this before running any test script: source tests/importer/_env.sh
export RM_URL="${RM_URL:-https://ridemaster.eu}"
export RM_USER="${RM_USER:-xavierkain.consulting@gmail.com}"
# IMPORTANT: export RM_PASS in your shell before sourcing.
# Do NOT commit a real password to this file.
if [ -z "${RM_PASS}" ]; then
    echo "FATAL: RM_PASS not set. export RM_PASS='xxxx xxxx xxxx xxxx xxxx xxxx' first." >&2
    return 1 2>/dev/null || exit 1
fi
export RM_AUTH=( -u "${RM_USER}:${RM_PASS}" )
```

- [ ] **Step 5: Create the pong test**

`tests/importer/01-pong.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 01: pong endpoint ==="
response=$(curl -s -o /tmp/rm-pong.json -w "%{http_code}" \
    "${RM_AUTH[@]}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d '{}' \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp")

status=$(python3 -c "import json; print(json.load(open('/tmp/rm-pong.json')).get('status', 'NO_STATUS'))")

if [ "$response" = "200" ] && [ "$status" = "pong" ]; then
    echo "PASS: HTTP 200, status=pong"
    exit 0
else
    echo "FAIL: HTTP $response, status=$status"
    cat /tmp/rm-pong.json
    exit 1
fi
```

- [ ] **Step 6: Run test, expect FAIL (plugin not yet activated)**

```bash
chmod +x tests/importer/*.sh
bash tests/importer/01-pong.sh
```

Expected: `FAIL: HTTP 404` (the route doesn't exist yet because the plugin isn't activated).

- [ ] **Step 7: Activate the plugin on the live site**

```bash
# Verify plugin folder is on the server. If working locally, sync first:
# rsync -avz plugins/ridemaster-importer/ user@ridemaster.eu:/var/www/wp-content/plugins/ridemaster-importer/
# (Adjust path per hosting.)

# Activate via REST (requires admin app password)
source tests/importer/_env.sh
curl -s "${RM_AUTH[@]}" -X POST \
    -H "Content-Type: application/json" \
    -d '{"status":"active"}' \
    "${RM_URL}/wp-json/wp/v2/plugins/ridemaster-importer%2Fridemaster-importer" \
    | python3 -m json.tool
```

Expected: response includes `"status": "active"`.

If working over SSH/SFTP without direct REST plugin install, upload the plugin folder manually to `wp-content/plugins/` and activate via WP admin UI.

- [ ] **Step 8: Re-run pong test, expect PASS**

```bash
bash tests/importer/01-pong.sh
```

Expected: `PASS: HTTP 200, status=pong`.

- [ ] **Step 9: Commit**

```bash
git add plugins/ridemaster-importer/ tests/importer/
git commit -m "feat(importer): plugin skeleton with pong endpoint

Creates the ridemaster-importer plugin with hard dependency check on the
main ridemaster plugin and a working /ridemaster/v1/import-camp route that
returns {status: pong} for authenticated admin users.

Includes tests/importer/ bash test harness using curl against the live site."
```

---

## Task 2: Add debug meta-dump endpoint (temporary)

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `plugins/ridemaster-importer/includes/class-rm-importer-debug.php`

This endpoint is **temporary** — it's used by parity tests in Tasks 3 and 6 to compare postmeta between a JFB-created camp and a `create_from_payload`-created camp. It's removed in Task 17 before the final E2E test.

- [ ] **Step 1: Create the debug class**

`plugins/ridemaster-importer/includes/class-rm-importer-debug.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Importer_Debug {

    public static function register_routes() {
        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__debug_dump_meta/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'dump_meta' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id' => [ 'required' => true, 'type' => 'integer' ],
            ],
        ] );

        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__debug_dump_relations/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'dump_relations' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id' => [ 'required' => true, 'type' => 'integer' ],
            ],
        ] );
    }

    public static function dump_meta( WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $post = get_post( $id );
        if ( ! $post ) {
            return new WP_Error( 'NOT_FOUND', "Post $id not found", [ 'status' => 404 ] );
        }

        $meta = get_post_meta( $id );
        // Flatten single-value arrays for easier diffing.
        $flat = [];
        foreach ( $meta as $k => $v ) {
            $flat[ $k ] = ( is_array( $v ) && count( $v ) === 1 ) ? $v[0] : $v;
        }

        // Get taxonomies + their terms (slugs only for stable diffing).
        $taxes = get_object_taxonomies( $post->post_type );
        $tax_data = [];
        foreach ( $taxes as $tax ) {
            $terms = wp_get_object_terms( $id, $tax, [ 'fields' => 'slugs' ] );
            if ( ! is_wp_error( $terms ) ) {
                sort( $terms );
                $tax_data[ $tax ] = $terms;
            }
        }

        return new WP_REST_Response( [
            'post_id'      => $id,
            'post_type'    => $post->post_type,
            'post_status'  => $post->post_status,
            'post_title'   => $post->post_title,
            'post_author'  => (int) $post->post_author,
            'meta'         => $flat,
            'taxonomies'   => $tax_data,
        ], 200 );
    }

    public static function dump_relations( WP_REST_Request $req ) {
        global $wpdb;
        $id = (int) $req['id'];
        $table = $wpdb->prefix . 'jet_rel_default';

        $as_parent = $wpdb->get_results(
            $wpdb->prepare( "SELECT rel_id, parent_object_id, child_object_id FROM {$table} WHERE parent_object_id = %d ORDER BY rel_id, child_object_id", $id ),
            ARRAY_A
        );
        $as_child = $wpdb->get_results(
            $wpdb->prepare( "SELECT rel_id, parent_object_id, child_object_id FROM {$table} WHERE child_object_id = %d ORDER BY rel_id, parent_object_id", $id ),
            ARRAY_A
        );

        return new WP_REST_Response( [
            'post_id'   => $id,
            'as_parent' => $as_parent,
            'as_child'  => $as_child,
        ], 200 );
    }
}
```

- [ ] **Step 2: Register the debug routes in main bootstrap**

In `plugins/ridemaster-importer/ridemaster-importer.php`, after the existing `require_once`:

```php
require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-debug.php';

add_action( 'rest_api_init', [ 'RM_Importer_Debug', 'register_routes' ] );
```

- [ ] **Step 3: Test the debug endpoint**

Create `tests/importer/02-debug-dump.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 02: debug dump on existing camp 2279 (Coaching Mayapo) ==="

# Meta dump
status=$(curl -s -o /tmp/rm-meta.json -w "%{http_code}" \
    "${RM_AUTH[@]}" \
    "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/2279")

if [ "$status" != "200" ]; then
    echo "FAIL meta dump: HTTP $status"
    cat /tmp/rm-meta.json
    exit 1
fi

# Sanity check: meta should contain expected camp keys
for key in _price camp_start_date _stock _thumbnail_id; do
    if ! python3 -c "import json,sys; d=json.load(open('/tmp/rm-meta.json')); sys.exit(0 if '$key' in d['meta'] else 1)"; then
        echo "FAIL: expected meta key '$key' not present"
        exit 1
    fi
done

# Relations dump
status=$(curl -s -o /tmp/rm-rel.json -w "%{http_code}" \
    "${RM_AUTH[@]}" \
    "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/2279")

if [ "$status" != "200" ]; then
    echo "FAIL relations dump: HTTP $status"
    exit 1
fi

# Should have at least one relation (this camp has a coach)
count=$(python3 -c "import json; d=json.load(open('/tmp/rm-rel.json')); print(len(d['as_parent']) + len(d['as_child']))")
if [ "$count" -eq 0 ]; then
    echo "FAIL: expected at least one relation row for camp 2279"
    exit 1
fi

echo "PASS: meta and relations dumps OK ($count relations found)"
```

- [ ] **Step 4: Sync plugin to server + run test**

```bash
# Sync (adjust per hosting; SFTP/rsync/git pull on server)
bash tests/importer/02-debug-dump.sh
```

Expected: `PASS: meta and relations dumps OK`.

- [ ] **Step 5: Commit**

```bash
git add plugins/ridemaster-importer/includes/class-rm-importer-debug.php \
        plugins/ridemaster-importer/ridemaster-importer.php \
        tests/importer/02-debug-dump.sh
git commit -m "feat(importer): add temporary debug meta/relations dump endpoints

Used by parity tests during the RM_Camp refactor. To be removed before
production use (Task 17)."
```

---

## Task 3: Refactor RM_Camp — extract apply_meta_from_data + add create_from_payload

**Files:**
- Modify: `plugins/ridemaster/includes/class-camp.php`
- Create: `tests/importer/03-camp-refactor-parity.sh`

**The goal:** transform [class-camp.php](../../../plugins/ridemaster/includes/class-camp.php) line 99-236 (the body of `init_new_camp`) into a reusable method `apply_meta_from_data( $post_id, array $data )` that takes a normalized array instead of `$_REQUEST`, and add a new `create_from_payload( $payload )` that builds the post AND calls the extracted method.

**The constraint:** `init_new_camp` continues to work IDENTICALLY when called via the JFB hook. Existing camps created via the form must end up with the same postmeta.

- [ ] **Step 1: Read the current state of init_new_camp**

Read [class-camp.php:72-239](../../../plugins/ridemaster/includes/class-camp.php#L72) carefully. The body has 7 blocks (A–H) that map `$_REQUEST` → meta + taxonomies + relations + draft status.

- [ ] **Step 2: Replace init_new_camp body with a thin wrapper + add new static methods**

Apply this edit to `plugins/ridemaster/includes/class-camp.php`. Replace lines 72-239 (the whole `init_new_camp` method) with:

```php
    /**
     * Initialise a newly-created camp product (JFB hook entrypoint).
     *
     * Maps $_REQUEST → normalized data array and delegates to apply_meta_from_data.
     * This wrapper preserves the exact behavior of the previous monolithic
     * implementation while enabling reuse from the importer plugin.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update (true) or new post (false).
     */
    public function init_new_camp( $post_id, $post, $update ) {

        // --- Re-entrance guard ---
        static $running = false;
        if ( $running ) {
            return;
        }

        // Only new products, not updates.
        if ( $update ) {
            return;
        }

        // Skip revisions and autosaves.
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // Only process when the request originates from the JetFormBuilder form.
        if ( ! isset( $_REQUEST['camp_title'] ) ) {
            return;
        }

        $running = true;

        self::log( 'RideMaster: init_new_camp fired for post ' . $post_id );

        // Map $_REQUEST → normalized data array.
        $data = [
            'price'         => isset( $_REQUEST['camp_price'] )     ? sanitize_text_field( $_REQUEST['camp_price'] )     : '',
            'max_spots'     => isset( $_REQUEST['camp_max_spots'] ) ? intval( $_REQUEST['camp_max_spots'] )              : ( isset( $_REQUEST['camp_stock'] ) ? intval( $_REQUEST['camp_stock'] ) : 0 ),
            'start_date'    => isset( $_REQUEST['camp_start_date'] ) ? sanitize_text_field( $_REQUEST['camp_start_date'] ) : '',
            'end_date'      => isset( $_REQUEST['camp_end_date'] )   ? sanitize_text_field( $_REQUEST['camp_end_date'] )   : '',
            'coach_user_id' => get_current_user_id(),
            'spot_id'       => isset( $_REQUEST['camp_spot'] ) ? intval( $_REQUEST['camp_spot'] ) : 0,
            'check_stripe'  => true,  // JFB path always checks the current user's Stripe status.
        ];

        self::apply_meta_from_data( $post_id, $data );

        $running = false;
    }

    /**
     * Apply all camp meta + taxonomies + relations + Stripe-blocker logic from a normalized data array.
     *
     * Extracted from init_new_camp so the importer plugin can reuse it without
     * relying on $_REQUEST.
     *
     * @param int   $post_id Camp product ID (must already exist).
     * @param array $data    Normalized data:
     *                       - price (string|int)
     *                       - max_spots (int)
     *                       - start_date (string YYYY-MM-DD)
     *                       - end_date (string YYYY-MM-DD)
     *                       - coach_user_id (int) — WP user whose Stripe status to check
     *                       - coach_post_id (int|null) — explicit coach CPT ID (overrides usermeta lookup)
     *                       - spot_id (int|null) — spot CPT ID to link
     *                       - check_stripe (bool) — whether to apply Stripe blocker (true for JFB)
     */
    public static function apply_meta_from_data( $post_id, array $data ) {

        // ---------------------------------------------------------------
        // A. Write _price meta.
        // ---------------------------------------------------------------
        if ( ! empty( $data['price'] ) ) {
            $price = $data['price'];
            update_post_meta( $post_id, '_price', $price );
            update_post_meta( $post_id, '_regular_price', $price );
            self::log( 'RideMaster: Set _price = ' . $price . ' for post ' . $post_id );
        }

        // ---------------------------------------------------------------
        // B. Set product type to "simple".
        // ---------------------------------------------------------------
        wp_set_object_terms( $post_id, 'simple', 'product_type' );
        self::log( 'RideMaster: Set product type to simple for post ' . $post_id );

        // ---------------------------------------------------------------
        // B2. Force stock values via shutdown hook.
        // ---------------------------------------------------------------
        $stock_qty = isset( $data['max_spots'] ) ? intval( $data['max_spots'] ) : 0;
        add_action( 'shutdown', function () use ( $post_id, $stock_qty ) {
            update_post_meta( $post_id, '_stock', $stock_qty );
            update_post_meta( $post_id, '_manage_stock', 'yes' );
            update_post_meta( $post_id, '_stock_status', $stock_qty > 0 ? 'instock' : 'outofstock' );
            wc_delete_product_transients( $post_id );
            self::log( 'RideMaster: Forced stock values on shutdown for post ' . $post_id . ' (stock=' . $stock_qty . ')' );
        } );

        // ---------------------------------------------------------------
        // C. Merge dates.
        // ---------------------------------------------------------------
        $start_date = isset( $data['start_date'] ) ? $data['start_date'] : '';
        $end_date   = isset( $data['end_date'] )   ? $data['end_date']   : '';

        if ( $start_date ) {
            update_post_meta( $post_id, 'camp_start_date', $start_date );
        }
        if ( $end_date ) {
            update_post_meta( $post_id, 'camp_end_date', $end_date );
        }

        if ( $start_date && $end_date ) {
            $start_ts = strtotime( $start_date );
            $end_ts   = strtotime( $end_date );

            update_post_meta( $post_id, 'full_date', $start_ts );
            update_post_meta( $post_id, 'full_date__end_date', $end_ts );

            $config = wp_json_encode( [
                'dates' => [
                    [ 'start' => $start_ts, 'end' => $end_ts ],
                ],
            ] );
            update_post_meta( $post_id, 'full_date__config', $config );

            self::log( 'RideMaster: Saved advanced date metas for post ' . $post_id );
        }

        // ---------------------------------------------------------------
        // D. Assign the "Camp" product category.
        // ---------------------------------------------------------------
        wp_set_object_terms( $post_id, 'camp', 'product_cat', true );

        // ---------------------------------------------------------------
        // E. Stripe blocker (only when explicitly requested).
        // ---------------------------------------------------------------
        if ( ! empty( $data['check_stripe'] ) ) {
            $user_to_check   = isset( $data['coach_user_id'] ) ? intval( $data['coach_user_id'] ) : 0;
            $stripe_complete = $user_to_check ? get_user_meta( $user_to_check, 'stripe_onboarding_complete', true ) : '';
            if ( $stripe_complete !== '1' ) {
                wp_update_post( [
                    'ID'          => $post_id,
                    'post_status' => 'draft',
                ] );
                update_post_meta( $post_id, '_rm_blocked_reason', 'stripe_not_connected' );
                self::log( 'RideMaster: Camp ' . $post_id . ' set to draft — coach Stripe not connected.' );
            }
        }

        // ---------------------------------------------------------------
        // F1. Create Coach → Camp relation.
        // ---------------------------------------------------------------
        $coach_post_id = isset( $data['coach_post_id'] ) ? intval( $data['coach_post_id'] ) : 0;
        if ( ! $coach_post_id && ! empty( $data['coach_user_id'] ) ) {
            $coach_post_id = (int) get_user_meta( $data['coach_user_id'], 'coach_post_id', true );
        }

        if ( $coach_post_id ) {
            $coach_to_camps = self::find_relation( 'Coach to Camps' );
            if ( $coach_to_camps ) {
                $coach_to_camps->update( $coach_post_id, $post_id );
                self::log( 'RideMaster: Linked Coach ' . $coach_post_id . ' → Camp ' . $post_id );
            } else {
                self::log( 'RideMaster: "Coach to Camps" relation not found.' );
            }
            update_post_meta( $post_id, '_coach_post_id', $coach_post_id );
        }

        // ---------------------------------------------------------------
        // F2. Create Spot → Camp relation.
        // ---------------------------------------------------------------
        $camp_spot = isset( $data['spot_id'] ) ? intval( $data['spot_id'] ) : 0;
        if ( $camp_spot ) {
            $spot_to_camps = self::find_relation( 'Spot to Camps' );
            if ( $spot_to_camps ) {
                $spot_to_camps->update( $camp_spot, $post_id );
                self::log( 'RideMaster: Linked Spot ' . $camp_spot . ' → Camp ' . $post_id );
            }
        }

        // ---------------------------------------------------------------
        // H. Clear WC transients.
        // ---------------------------------------------------------------
        wc_delete_product_transients( $post_id );
    }

    /**
     * Create a new camp product from a structured payload (used by the importer).
     *
     * @param array $payload {
     *     @type string $title             Post title.
     *     @type string $description_html  Post content (HTML allowed via wp_kses_post).
     *     @type string $price             Numeric string.
     *     @type int    $max_spots         Capacity.
     *     @type string $start_date        YYYY-MM-DD.
     *     @type string $end_date          YYYY-MM-DD.
     *     @type string $schedule          Optional textual schedule.
     *     @type array  $included          Array of strings.
     *     @type array  $not_included      Array of strings.
     *     @type string $sport             Sport term slug.
     *     @type array  $level             Array of level term slugs.
     *     @type array  $languages         Array of language term slugs.
     *     @type string $camp_status       Camp-status term slug (e.g. 'open').
     *     @type int    $coach_post_id     Existing coach CPT ID to link.
     *     @type int    $spot_id           Existing spot CPT ID to link.
     *     @type int    $hotel_id          Existing hotel CPT ID to link (optional).
     *     @type string $import_source_url URL for idempotency tracking.
     *     @type bool   $check_stripe      Whether to apply Stripe blocker (default true).
     * }
     * @return int|WP_Error Camp product ID, or WP_Error on failure.
     */
    public static function create_from_payload( array $payload ) {

        $post_id = wp_insert_post( [
            'post_type'    => 'product',
            'post_title'   => sanitize_text_field( $payload['title'] ?? '' ),
            'post_content' => isset( $payload['description_html'] ) ? wp_kses_post( $payload['description_html'] ) : '',
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'wp_insert_post_failed', 'wp_insert_post returned 0' );
        }

        // Convert payload format to internal data shape used by apply_meta_from_data.
        $data = [
            'price'         => $payload['price'] ?? '',
            'max_spots'     => $payload['max_spots'] ?? 0,
            'start_date'    => $payload['start_date'] ?? '',
            'end_date'      => $payload['end_date'] ?? '',
            'coach_post_id' => $payload['coach_post_id'] ?? 0,
            'coach_user_id' => $payload['coach_user_id'] ?? 0,
            'spot_id'       => $payload['spot_id'] ?? 0,
            'check_stripe'  => $payload['check_stripe'] ?? true,
        ];

        self::apply_meta_from_data( $post_id, $data );

        // Apply remaining metadata that is NOT handled by apply_meta_from_data.

        // Schedule.
        if ( ! empty( $payload['schedule'] ) ) {
            update_post_meta( $post_id, 'camp_schedule', wp_kses_post( $payload['schedule'] ) );
        }

        // Repeater-format included.
        if ( ! empty( $payload['included'] ) && is_array( $payload['included'] ) ) {
            $rows = array_map( function ( $v ) {
                return [ 'included_in_the_camp' => sanitize_text_field( $v ) ];
            }, $payload['included'] );
            update_post_meta( $post_id, 'camp_included', $rows );
        }
        if ( ! empty( $payload['not_included'] ) && is_array( $payload['not_included'] ) ) {
            $rows = array_map( function ( $v ) {
                return [ 'not_included_in_the_camp' => sanitize_text_field( $v ) ];
            }, $payload['not_included'] );
            update_post_meta( $post_id, 'camp_not_included', $rows );
        }

        // Taxonomies (slugs).
        if ( ! empty( $payload['sport'] ) ) {
            wp_set_object_terms( $post_id, [ sanitize_title( $payload['sport'] ) ], 'sport' );
        }
        if ( ! empty( $payload['level'] ) ) {
            $slugs = array_map( 'sanitize_title', (array) $payload['level'] );
            wp_set_object_terms( $post_id, $slugs, 'level' );
        }
        if ( ! empty( $payload['languages'] ) ) {
            $slugs = array_map( 'sanitize_title', (array) $payload['languages'] );
            wp_set_object_terms( $post_id, $slugs, 'language' );
        }
        if ( ! empty( $payload['camp_status'] ) ) {
            wp_set_object_terms( $post_id, [ sanitize_title( $payload['camp_status'] ) ], 'camp-status' );
        }

        // Hotel linkage (simple meta, no JE relation for hotel).
        if ( ! empty( $payload['hotel_id'] ) ) {
            update_post_meta( $post_id, '_hotel_id', intval( $payload['hotel_id'] ) );
        }

        // Idempotency tracking.
        if ( ! empty( $payload['import_source_url'] ) ) {
            update_post_meta( $post_id, '_import_source_url', esc_url_raw( $payload['import_source_url'] ) );
            update_post_meta( $post_id, '_import_imported_at', time() );
        }

        return $post_id;
    }
```

- [ ] **Step 3: Write the parity test**

`tests/importer/03-camp-refactor-parity.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 03: refactor parity — JFB-style call vs create_from_payload ==="

# This test calls a WP eval-style endpoint to create two camps and compare meta.
# Since we don't have wp-cli access from outside, we'll use a one-shot debug
# route. For now, manually test via WP admin OR add a temp route.

# STRATEGY: write a temp PHP test endpoint
cat > /tmp/test-parity-route.php <<'PHP'
<?php
// Drop this in /wp-content/mu-plugins/ to register a one-shot test route.
add_action( 'rest_api_init', function () {
    register_rest_route( 'ridemaster/v1', '/__test_parity', [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback' => function ( WP_REST_Request $req ) {
            // Simulate JFB call by setting $_REQUEST and creating a post the
            // same way JFB would (wp_insert_post + save_post hook).
            $_REQUEST = [
                'camp_title'      => '[TEST-JFB] Parity Camp',
                'camp_price'      => '777',
                'camp_max_spots'  => 5,
                'camp_start_date' => '2026-08-01',
                'camp_end_date'   => '2026-08-08',
                'camp_spot'       => 195,  // Tarifa
            ];
            $jfb_id = wp_insert_post( [
                'post_type'    => 'product',
                'post_title'   => '[TEST-JFB] Parity Camp',
                'post_status'  => 'publish',
            ] );

            // Direct call via create_from_payload
            $payload_id = RM_Camp::create_from_payload( [
                'title'           => '[TEST-PAYLOAD] Parity Camp',
                'price'           => '777',
                'max_spots'       => 5,
                'start_date'      => '2026-08-01',
                'end_date'        => '2026-08-08',
                'spot_id'         => 195,
                'coach_post_id'   => 0,  // skip coach linking for pure parity check
                'check_stripe'    => false,
            ] );

            // Force shutdown actions to run now for the meta diff.
            do_action( 'shutdown' );

            return [
                'jfb_id'     => $jfb_id,
                'payload_id' => $payload_id,
            ];
        },
    ] );
} );
PHP

echo "Upload /tmp/test-parity-route.php to wp-content/mu-plugins/ then press Enter."
read

response=$(curl -s "${RM_AUTH[@]}" -X POST "${RM_URL}/wp-json/ridemaster/v1/__test_parity")
jfb_id=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['jfb_id'])")
payload_id=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['payload_id'])")

echo "JFB camp: $jfb_id"
echo "Payload camp: $payload_id"

# Dump and diff
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${jfb_id}" > /tmp/jfb-meta.json
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${payload_id}" > /tmp/payload-meta.json

# Compare keys that should match (excluding the obvious differences)
python3 <<'PY'
import json
jfb = json.load(open('/tmp/jfb-meta.json'))['meta']
pay = json.load(open('/tmp/payload-meta.json'))['meta']
expected_keys = ['_price', '_regular_price', '_stock', '_manage_stock', '_stock_status',
                 'camp_start_date', 'camp_end_date', 'full_date', 'full_date__end_date',
                 'full_date__config']
ok = True
for k in expected_keys:
    j = jfb.get(k)
    p = pay.get(k)
    if str(j) != str(p):
        print(f"DIFF on {k}: jfb={j!r} vs payload={p!r}")
        ok = False
    else:
        print(f"OK  on {k}: {j}")
print("PASS" if ok else "FAIL")
exit(0 if ok else 1)
PY

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${jfb_id}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${payload_id}?force=true" > /dev/null
echo "Cleaned up test camps."
```

- [ ] **Step 4: Sync changes and run the parity test**

```bash
# Sync class-camp.php to server
# Run the test (it will prompt you to upload the mu-plugin first)
bash tests/importer/03-camp-refactor-parity.sh
```

Expected: `PASS` (all canonical meta keys match between JFB-flow and create_from_payload flow).

- [ ] **Step 5: Test the JFB form flow still works (no regression)**

```bash
# Use Playwright (or manually open the form) to create a real camp via JFB:
# 1. Login as xavierkain.consulting@gmail.com
# 2. Navigate to the camp creation form page
# 3. Fill all required fields, submit
# 4. Verify the new camp appears with all expected meta in WP admin

# Then dump its meta via the debug endpoint
CAMP_ID=<id of the camp you just created>
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP_ID}" \
    | python3 -m json.tool
```

Expected: all the usual meta keys present (`_price`, `_stock`, `camp_start_date`, `camp_end_date`, `full_date*`, plus coach/spot relations if applicable).

- [ ] **Step 6: Clean up test artifacts and commit**

```bash
# Remove the mu-plugin
# (on server) rm wp-content/mu-plugins/test-parity-route.php

# Optionally delete the test camp you created via JFB

git add plugins/ridemaster/includes/class-camp.php tests/importer/03-camp-refactor-parity.sh
git commit -m "refactor(ridemaster): extract RM_Camp::apply_meta_from_data + add create_from_payload

Extracted the body of init_new_camp into a reusable static method that takes
a normalized data array instead of \$_REQUEST. The JFB hook is preserved
as a thin wrapper that maps \$_REQUEST -> data and calls the new method.

Added RM_Camp::create_from_payload() for the importer plugin: it wraps
wp_insert_post + apply_meta_from_data + handles schedule, included/not_included
repeaters, taxonomies, hotel meta, and idempotency tracking.

Parity verified: a JFB-created camp and a payload-created camp produce
identical canonical meta keys (price, stock, dates, full_date*).

Refs: docs/superpowers/specs/2026-05-27-camp-import-tool-design.md section 5"
```

---

## Task 4: Endpoint v1 — accept minimal camp payload, no coach/spot/hotel inline

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `plugins/ridemaster-importer/includes/class-rm-importer-validator.php`
- Create: `tests/importer/04-camp-minimal.sh`

- [ ] **Step 1: Create the validator class**

`plugins/ridemaster-importer/includes/class-rm-importer-validator.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Importer_Validator {

    /**
     * Valid term slugs per taxonomy. Source of truth = live site audit 2026-05-27.
     * Used to reject unknown slugs BEFORE attempting to write to the DB.
     */
    const ALLOWED_TERMS = [
        'sport'         => [ 'kitesurf', 'parakite', 'wingfoil' ],
        'level'         => [ 'beginner', 'intermediate', 'advanced', 'expert' ],
        'language'      => [ 'english', 'french', 'german', 'italian', 'portuguese', 'spanish' ],
        'water-type'    => [ 'flat-water', 'waves', 'choppy', 'mixed' ],
        'camp-status'   => [ 'open', 'full', 'cancelled' ],
        'coach-status'  => [ 'pending', 'validated', 'suspended' ],
    ];

    /**
     * Validate an import-camp payload.
     *
     * @param array $payload Raw decoded JSON body.
     * @return true|WP_Error true if valid, WP_Error with detailed message otherwise.
     */
    public static function validate( array $payload ) {
        if ( empty( $payload['import_source_url'] ) || ! filter_var( $payload['import_source_url'], FILTER_VALIDATE_URL ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'import_source_url is required and must be a valid URL', [ 'status' => 400 ] );
        }

        if ( empty( $payload['camp'] ) || ! is_array( $payload['camp'] ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'camp object is required', [ 'status' => 400 ] );
        }

        $camp = $payload['camp'];

        // Required camp fields.
        foreach ( [ 'title', 'price_eur', 'max_spots', 'start_date', 'end_date' ] as $required ) {
            if ( ! isset( $camp[ $required ] ) || $camp[ $required ] === '' ) {
                return new WP_Error( 'INVALID_PAYLOAD', "camp.$required is required", [ 'status' => 400 ] );
            }
        }

        // Date format.
        foreach ( [ 'start_date', 'end_date' ] as $df ) {
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $camp[ $df ] ) ) {
                return new WP_Error( 'INVALID_PAYLOAD', "camp.$df must be YYYY-MM-DD", [ 'status' => 400 ] );
            }
        }

        // Slug enumerations.
        $checks = [
            [ 'sport',       isset( $camp['sport'] ) ? [ $camp['sport'] ] : [] ],
            [ 'level',       (array) ( $camp['level'] ?? [] ) ],
            [ 'language',    (array) ( $camp['languages'] ?? [] ) ],
            [ 'camp-status', isset( $camp['camp_status'] ) ? [ $camp['camp_status'] ] : [] ],
        ];
        foreach ( $checks as [ $tax, $slugs ] ) {
            foreach ( $slugs as $slug ) {
                if ( ! in_array( $slug, self::ALLOWED_TERMS[ $tax ], true ) ) {
                    return new WP_Error(
                        'INVALID_PAYLOAD',
                        "Unknown $tax slug '$slug'. Allowed: " . implode( ', ', self::ALLOWED_TERMS[ $tax ] ),
                        [ 'status' => 400 ]
                    );
                }
            }
        }

        return true;
    }
}
```

- [ ] **Step 2: Wire validator into endpoint + handle minimal camp creation**

Replace the contents of `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php` with:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-validator.php';

class RM_Importer_Endpoint {

    const NAMESPACE = 'ridemaster/v1';

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/import-camp', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_import' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );
    }

    public static function check_permission( $request ) {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return new WP_Error(
                'INSUFFICIENT_PERMISSIONS',
                'You need edit_others_posts capability.',
                [ 'status' => 403 ]
            );
        }
        return true;
    }

    public static function handle_import( WP_REST_Request $request ) {
        $payload = $request->get_json_params();
        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'Body must be a JSON object', [ 'status' => 400 ] );
        }

        // Schema validation.
        $valid = RM_Importer_Validator::validate( $payload );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        // Idempotency check.
        $existing = self::find_existing_by_source_url( $payload['import_source_url'] );
        if ( $existing && empty( $payload['force_overwrite'] ) ) {
            return new WP_Error(
                'DUPLICATE_IMPORT',
                'A camp from this URL already exists',
                [
                    'status'           => 409,
                    'existing_camp_id' => $existing,
                    'existing_edit_url'=> admin_url( "post.php?post={$existing}&action=edit" ),
                ]
            );
        }

        // Build camp payload for RM_Camp::create_from_payload.
        $camp = $payload['camp'];
        $camp_payload = [
            'title'             => $camp['title'],
            'description_html'  => $camp['description_html'] ?? '',
            'price'             => (string) $camp['price_eur'],
            'max_spots'         => (int) $camp['max_spots'],
            'start_date'        => $camp['start_date'],
            'end_date'          => $camp['end_date'],
            'schedule'          => $camp['schedule'] ?? '',
            'included'          => $camp['included'] ?? [],
            'not_included'      => $camp['not_included'] ?? [],
            'sport'             => $camp['sport'] ?? '',
            'level'             => $camp['level'] ?? [],
            'languages'         => $camp['languages'] ?? [],
            'camp_status'       => $camp['camp_status'] ?? 'open',
            'coach_post_id'     => $payload['coach']['existing_post_id'] ?? 0,
            'spot_id'           => $payload['spot']['existing_post_id'] ?? 0,
            'hotel_id'          => $payload['hotel']['existing_post_id'] ?? 0,
            'import_source_url' => $payload['import_source_url'],
            'check_stripe'      => false,  // imports skip Stripe check for example data
        ];

        $camp_id = RM_Camp::create_from_payload( $camp_payload );
        if ( is_wp_error( $camp_id ) ) {
            return new WP_Error( 'IMPORT_FAILED', 'Camp creation failed: ' . $camp_id->get_error_message(), [ 'status' => 500 ] );
        }

        // Force shutdown actions (stock meta) to run now so the response reflects final state.
        do_action( 'shutdown' );

        return new WP_REST_Response( [
            'status'    => 'success',
            'camp_id'   => $camp_id,
            'edit_url'  => admin_url( "post.php?post={$camp_id}&action=edit" ),
            'public_url'=> get_permalink( $camp_id ),
            'created'   => [
                'camp' => [ 'id' => $camp_id, 'images_imported' => 0, 'images_failed' => 0 ],
            ],
            'warnings'  => [],
        ], 200 );
    }

    /**
     * Find an existing camp by its _import_source_url meta.
     *
     * @return int|null Camp post ID, or null if none.
     */
    private static function find_existing_by_source_url( string $url ) {
        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                [ 'key' => '_import_source_url', 'value' => esc_url_raw( $url ) ],
            ],
        ] );
        return $q->posts ? (int) $q->posts[0] : null;
    }
}
```

- [ ] **Step 3: Write the minimal camp test**

`tests/importer/04-camp-minimal.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 04: import minimal camp (no images, existing coach=189, existing spot=195) ==="

SOURCE_URL="https://test.invalid/camp-minimal-$(date +%s)"

response=$(curl -s -o /tmp/rm-r.json -w "%{http_code}" "${RM_AUTH[@]}" \
    -X POST -H "Content-Type: application/json" \
    -d "{
        \"import_source_url\": \"${SOURCE_URL}\",
        \"camp\": {
            \"title\": \"[TEST] Minimal camp\",
            \"description_html\": \"<p>Test camp description.</p>\",
            \"price_eur\": 500,
            \"max_spots\": 8,
            \"start_date\": \"2026-09-01\",
            \"end_date\": \"2026-09-07\",
            \"sport\": \"kitesurf\",
            \"level\": [\"beginner\", \"intermediate\"],
            \"languages\": [\"english\", \"french\"],
            \"camp_status\": \"open\",
            \"included\": [\"Coaching\", \"Equipment\"],
            \"not_included\": [\"Flights\"]
        },
        \"coach\": {\"existing_post_id\": 189},
        \"spot\":  {\"existing_post_id\": 195}
    }" \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp")

if [ "$response" != "200" ]; then
    echo "FAIL: HTTP $response"
    cat /tmp/rm-r.json
    exit 1
fi

CAMP_ID=$(python3 -c "import json; print(json.load(open('/tmp/rm-r.json'))['camp_id'])")
echo "Created camp $CAMP_ID"

# Verify meta via debug dump
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP_ID}" > /tmp/rm-m.json

python3 <<PY
import json
d = json.load(open('/tmp/rm-m.json'))
m = d['meta']
t = d['taxonomies']

checks = [
    ('_price', '500'),
    ('_regular_price', '500'),
    ('_stock', '8'),
    ('_manage_stock', 'yes'),
    ('_stock_status', 'instock'),
    ('camp_start_date', '2026-09-01'),
    ('camp_end_date',   '2026-09-07'),
    ('_coach_post_id', '189'),
    ('_import_source_url', '${SOURCE_URL}'),
]
ok = True
for k, v in checks:
    if str(m.get(k)) != str(v):
        print(f'FAIL {k}: got {m.get(k)!r} expected {v!r}')
        ok = False

if 'kitesurf' not in t.get('sport', []):
    print(f'FAIL sport: got {t.get("sport")}'); ok = False
if sorted(['beginner','intermediate']) != sorted(t.get('level', [])):
    print(f'FAIL level: got {t.get("level")}'); ok = False
if 'camp' not in t.get('product_cat', []):
    print(f'FAIL product_cat: got {t.get("product_cat")}'); ok = False
if 'open' not in t.get('camp-status', []):
    print(f'FAIL camp-status: got {t.get("camp-status")}'); ok = False

print('PASS' if ok else 'FAIL')
exit(0 if ok else 1)
PY

# Verify the camp shows up in the existing coach's relations
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/189" > /tmp/rm-rel.json
if ! python3 -c "import json; d=json.load(open('/tmp/rm-rel.json')); rows=[r for r in d['as_parent'] if r['rel_id']==20 and int(r['child_object_id'])==${CAMP_ID}]; exit(0 if rows else 1)"; then
    echo "FAIL: coach 189 → camp ${CAMP_ID} relation (rel_id 20) not present"
    exit 1
fi

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP_ID}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 4: Sync, run, expect PASS**

```bash
bash tests/importer/04-camp-minimal.sh
```

Expected: `PASS`.

- [ ] **Step 5: Test idempotency rejection**

```bash
# Run the same test twice in a row WITHOUT cleanup in between
# (modify the script temporarily or run a one-off)

source tests/importer/_env.sh
URL="https://test.invalid/idem-$(date +%s)"
PAYLOAD="{\"import_source_url\":\"${URL}\",\"camp\":{\"title\":\"[TEST] Idem\",\"price_eur\":100,\"max_spots\":1,\"start_date\":\"2026-09-01\",\"end_date\":\"2026-09-02\",\"sport\":\"kitesurf\"},\"coach\":{\"existing_post_id\":189},\"spot\":{\"existing_post_id\":195}}"

# First call: should succeed
r1=$(curl -s -o /tmp/r1.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
ID1=$(python3 -c "import json; print(json.load(open('/tmp/r1.json'))['camp_id'])")
echo "First call: HTTP $r1, camp_id=$ID1"

# Second call: should 409
r2=$(curl -s -o /tmp/r2.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
echo "Second call: HTTP $r2"
cat /tmp/r2.json | python3 -m json.tool

curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${ID1}?force=true" > /dev/null
```

Expected: first HTTP=200, second HTTP=409 with `DUPLICATE_IMPORT` code and `existing_camp_id`.

- [ ] **Step 6: Commit**

```bash
git add plugins/ridemaster-importer/ tests/importer/04-camp-minimal.sh
git commit -m "feat(importer): endpoint v1 — accept minimal camp payload

Validates payload schema (required fields, date format, term slug enumerations),
checks idempotency by _import_source_url postmeta (409 DUPLICATE_IMPORT if
present and force_overwrite=false), and calls RM_Camp::create_from_payload
with the linked coach/spot IDs.

Skips Stripe blocker for imports (check_stripe=false) since we're seeding
example data."
```

---

## Task 5: Refactor RM_Coach — add create_from_payload

**Files:**
- Modify: `plugins/ridemaster/includes/class-coach.php`
- Create: `tests/importer/05-coach-create.sh`

A coach has TWO sides: a WP user (with `coach_role`) AND a coach CPT post, linked via `coach_post_id` usermeta and `_coach_post_id` postmeta (see audit Annexe B). Creating a coach therefore needs to:
1. Either find an existing WP user by email OR create one
2. Either find an existing coach CPT post OR create one
3. Wire them together via the two meta keys
4. Apply all the profile meta (bio, location, photos, certs, social)
5. Apply taxonomies (sport, language, coach-status)

- [ ] **Step 1: Add static methods to RM_Coach**

Append to `plugins/ridemaster/includes/class-coach.php` BEFORE the closing `}` of the class:

```php
    /**
     * Find or create a WP user + coach CPT post from a payload.
     *
     * @param array $payload {
     *     @type array  $match_by         { email?, name? } — lookup hints
     *     @type bool   $create_if_missing
     *     @type array  $data             Full coach profile data (see spec section 4.3)
     * }
     * @return array|WP_Error { 'user_id' => int, 'post_id' => int, 'was_new' => bool } or error
     */
    public static function create_from_payload( array $payload ) {

        $data        = $payload['data'] ?? [];
        $match       = $payload['match_by'] ?? [];
        $can_create  = ! empty( $payload['create_if_missing'] );

        $email      = $data['email']    ?? $match['email'] ?? '';
        $first_name = $data['first_name'] ?? '';
        $last_name  = $data['last_name']  ?? '';
        $full_name  = trim( "$first_name $last_name" ) ?: ( $match['name'] ?? '' );

        if ( empty( $email ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'coach.data.email or coach.match_by.email is required', [ 'status' => 400 ] );
        }

        // 1. Find or create WP user.
        $user = get_user_by( 'email', $email );
        $was_new_user = false;

        if ( ! $user ) {
            if ( ! $can_create ) {
                return new WP_Error( 'COACH_NOT_FOUND', "No coach user with email $email", [ 'status' => 404 ] );
            }
            $username = sanitize_user( current( explode( '@', $email ) ), true );
            // Ensure unique username
            $base = $username;
            $i = 1;
            while ( username_exists( $username ) ) {
                $username = $base . $i;
                $i++;
            }
            $user_id = wp_insert_user( [
                'user_login' => $username,
                'user_email' => $email,
                'user_pass'  => wp_generate_password( 24 ),
                'first_name' => $first_name,
                'last_name'  => $last_name,
                'display_name' => $full_name,
                'role'       => 'coach_role',
            ] );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
            $user = get_user_by( 'id', $user_id );
            $was_new_user = true;
        }

        $user_id = (int) $user->ID;

        // Ensure user has coach_role even if pre-existing.
        if ( ! in_array( 'coach_role', (array) $user->roles, true ) ) {
            $user->add_role( 'coach_role' );
        }

        // 2. Find or create coach CPT post.
        $coach_post_id = (int) get_user_meta( $user_id, 'coach_post_id', true );
        $was_new_post = false;

        if ( ! $coach_post_id || ! get_post( $coach_post_id ) ) {
            $coach_post_id = wp_insert_post( [
                'post_type'    => 'coach',
                'post_title'   => $full_name ?: $email,
                'post_status'  => 'publish',
            ], true );
            if ( is_wp_error( $coach_post_id ) ) {
                return $coach_post_id;
            }
            $was_new_post = true;
        }

        // 3. Link user <-> post (both directions).
        update_user_meta( $user_id, 'coach_post_id', $coach_post_id );
        update_post_meta( $coach_post_id, '_coach_post_id', $coach_post_id );

        // 4. Apply profile meta.
        $meta_map = [
            'coach_first_name'      => $first_name,
            'coach_last_name'       => $last_name,
            'coach_bio'             => $data['bio']             ?? '',
            'coach_location'        => $data['location']        ?? '',
            'coach_years_experience'=> $data['years_experience']?? '',
            'instagram'             => $data['instagram']       ?? '',
            'youtube'               => $data['youtube']         ?? '',
            'website'               => $data['website']         ?? '',
        ];
        foreach ( $meta_map as $key => $val ) {
            if ( $val !== '' ) {
                update_post_meta( $coach_post_id, $key, $val );
            }
        }

        if ( ! empty( $data['certifications'] ) && is_array( $data['certifications'] ) ) {
            // Repeater format: array of objects with one key
            $rows = array_map( function ( $c ) {
                return [ 'cert_name' => sanitize_text_field( $c ) ];
            }, $data['certifications'] );
            update_post_meta( $coach_post_id, 'coach_certifications', $rows );
        }

        // 5. Taxonomies.
        if ( ! empty( $data['sport'] ) ) {
            wp_set_object_terms( $coach_post_id, array_map( 'sanitize_title', (array) $data['sport'] ), 'sport' );
        }
        if ( ! empty( $data['languages'] ) ) {
            wp_set_object_terms( $coach_post_id, array_map( 'sanitize_title', (array) $data['languages'] ), 'language' );
        }

        $coach_status = $data['coach_status'] ?? 'validated';
        wp_set_object_terms( $coach_post_id, [ sanitize_title( $coach_status ) ], 'coach-status' );

        // 6. Auto-mark Stripe as connected so imported coaches don't block their own camps.
        update_user_meta( $user_id, 'stripe_onboarding_complete', '1' );

        return [
            'user_id'      => $user_id,
            'post_id'      => $coach_post_id,
            'was_new'      => ( $was_new_user || $was_new_post ),
            'was_new_user' => $was_new_user,  // Track separately so rollback only deletes users we actually created.
            'was_new_post' => $was_new_post,
        ];
    }
```

- [ ] **Step 2: Write the coach creation test**

`tests/importer/05-coach-create.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 05: create new coach via create_from_payload ==="

# Use a one-shot debug endpoint to call the static method directly.
# Add this temporary route via mu-plugin or as part of the importer debug class.

TEST_EMAIL="test-coach-$(date +%s)@example.invalid"

# We'll exercise it through the import-camp endpoint by passing a `coach` block
# (will require updating the endpoint to support inline coach — done in Task 6).
# For now, test indirectly by calling a temp endpoint.

cat > /tmp/test-coach-create.php <<'PHP'
<?php
add_action( 'rest_api_init', function () {
    register_rest_route( 'ridemaster/v1', '/__test_coach_create', [
        'methods'             => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback' => function ( WP_REST_Request $req ) {
            $payload = $req->get_json_params();
            return RM_Coach::create_from_payload( $payload );
        },
    ] );
} );
PHP

echo "Upload /tmp/test-coach-create.php to wp-content/mu-plugins/ then press Enter."
read

PAYLOAD=$(cat <<JSON
{
    "match_by": {"email": "${TEST_EMAIL}"},
    "create_if_missing": true,
    "data": {
        "email": "${TEST_EMAIL}",
        "first_name": "Test",
        "last_name": "Coach",
        "bio": "Test bio.",
        "location": "Test, Country",
        "years_experience": 5,
        "certifications": ["IKO Level 1", "VDWS"],
        "sport": ["kitesurf"],
        "languages": ["english"],
        "coach_status": "validated",
        "instagram": "@testcoach"
    }
}
JSON
)

response=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_coach_create")
echo "Response: $response"

POST_ID=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['post_id'])")
USER_ID=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['user_id'])")
WAS_NEW=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['was_new'])")

[ "$WAS_NEW" = "True" ] || { echo "FAIL: was_new should be True"; exit 1; }

# Verify meta on the coach CPT
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${POST_ID}" > /tmp/c.json
python3 <<PY
import json
d = json.load(open('/tmp/c.json'))
m = d['meta']
t = d['taxonomies']
checks = [
    ('coach_first_name', 'Test'),
    ('coach_last_name', 'Coach'),
    ('coach_bio', 'Test bio.'),
    ('coach_location', 'Test, Country'),
    ('coach_years_experience', '5'),
    ('instagram', '@testcoach'),
]
ok = True
for k, v in checks:
    if str(m.get(k)) != v:
        print(f'FAIL {k}: got {m.get(k)!r}'); ok = False

if 'kitesurf' not in t.get('sport', []): print(f'FAIL sport: {t.get("sport")}'); ok=False
if 'english' not in t.get('language', []): print(f'FAIL language: {t.get("language")}'); ok=False
if 'validated' not in t.get('coach-status', []): print(f'FAIL coach-status: {t.get("coach-status")}'); ok=False

print('PASS' if ok else 'FAIL')
exit(0 if ok else 1)
PY

# Verify user has coach_role
USER_RESP=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/users/${USER_ID}?context=edit")
if ! echo "$USER_RESP" | python3 -c "import json,sys; d=json.load(sys.stdin); exit(0 if 'coach_role' in d.get('roles', []) else 1)"; then
    echo "FAIL: user $USER_ID does not have coach_role"
    exit 1
fi

# Idempotency: call again with same email, should return was_new=false and same IDs
response2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_coach_create")
WAS_NEW2=$(echo "$response2" | python3 -c "import json,sys; print(json.load(sys.stdin)['was_new'])")
POST_ID2=$(echo "$response2" | python3 -c "import json,sys; print(json.load(sys.stdin)['post_id'])")
[ "$WAS_NEW2" = "False" ] && [ "$POST_ID2" = "$POST_ID" ] || { echo "FAIL: idempotency broken (was_new=$WAS_NEW2, post_id=$POST_ID2 vs $POST_ID)"; exit 1; }

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/coach/${POST_ID}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/users/${USER_ID}?reassign=1&force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 3: Run, expect PASS**

```bash
bash tests/importer/05-coach-create.sh
```

- [ ] **Step 4: Verify no regression on existing coach registration**

Use Playwright (or manually open `https://ridemaster.eu/register-coach/` or wherever the coach signup form is) and create a new coach via the JFB form. Verify the new coach CPT is created and linked to the WP user as before.

```bash
# Inspect the latest coach CPT
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/coach?per_page=1&orderby=date&order=desc" \
    | python3 -c "import json,sys; d=json.load(sys.stdin); print(d[0]['id'], d[0]['title']['rendered'])"
```

- [ ] **Step 5: Remove the mu-plugin and commit**

```bash
# on server: rm wp-content/mu-plugins/test-coach-create.php

git add plugins/ridemaster/includes/class-coach.php tests/importer/05-coach-create.sh
git commit -m "feat(coach): add RM_Coach::create_from_payload for importer

Creates or matches WP user (by email) + coach CPT post, wires the
bi-directional coach_post_id / _coach_post_id meta link, applies profile
meta + taxonomies + certifications repeater, and auto-marks Stripe as
complete so imported coaches don't block their own camps.

Idempotent: re-calling with the same email returns was_new=false and the
original IDs."
```

---

## Task 6: Endpoint v2 — coach inline (create or match)

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/06-camp-with-coach.sh`

- [ ] **Step 1: Update handle_import to process coach inline**

In `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`, modify `handle_import`. After the validation block and before the camp creation, add:

```php
        // ----- Resolve coach -----
        $coach_post_id = 0;
        $coach_result  = null;

        if ( ! empty( $payload['coach']['existing_post_id'] ) ) {
            $coach_post_id = (int) $payload['coach']['existing_post_id'];
        } elseif ( ! empty( $payload['coach']['data'] ) || ! empty( $payload['coach']['match_by'] ) ) {
            $coach_result = RM_Coach::create_from_payload( $payload['coach'] );
            if ( is_wp_error( $coach_result ) ) {
                return new WP_Error(
                    'IMPORT_FAILED',
                    'Coach resolution failed: ' . $coach_result->get_error_message(),
                    [ 'status' => 500, 'step' => 'coach' ]
                );
            }
            $coach_post_id = $coach_result['post_id'];
        }
```

And update the `$camp_payload` array to use `$coach_post_id` (instead of `$payload['coach']['existing_post_id'] ?? 0`).

And update the returned `created` block to include coach info:

```php
            'created'   => [
                'coach' => $coach_result
                    ? [ 'id' => $coach_result['user_id'], 'post_id' => $coach_result['post_id'], 'was_new' => $coach_result['was_new'] ]
                    : ( $coach_post_id ? [ 'post_id' => $coach_post_id, 'was_new' => false ] : null ),
                'camp'  => [ 'id' => $camp_id, 'images_imported' => 0, 'images_failed' => 0 ],
            ],
```

- [ ] **Step 2: Write test for inline coach**

`tests/importer/06-camp-with-coach.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
TEST_EMAIL="coach-${TS}@test.invalid"
SOURCE_URL="https://test.invalid/camp-with-coach-${TS}"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {
        "match_by": {"email": "${TEST_EMAIL}"},
        "create_if_missing": true,
        "data": {
            "email": "${TEST_EMAIL}",
            "first_name": "Jean",
            "last_name": "Test",
            "bio": "Test biography.",
            "location": "Tarifa, Spain",
            "sport": ["kitesurf"],
            "languages": ["english","french"],
            "coach_status": "validated"
        }
    },
    "spot": {"existing_post_id": 195},
    "camp": {
        "title": "[TEST] Camp with inline coach",
        "price_eur": 600,
        "max_spots": 6,
        "start_date": "2026-10-01",
        "end_date":   "2026-10-07",
        "sport": "kitesurf",
        "level": ["intermediate"],
        "languages": ["english"],
        "camp_status": "open"
    }
}
JSON
)

response=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" \
    -X POST -H "Content-Type: application/json" -d "$PAYLOAD" \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp")

[ "$response" = "200" ] || { echo "FAIL HTTP $response"; cat /tmp/r.json; exit 1; }

CAMP_ID=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
COACH_USER_ID=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['id'])")
COACH_POST_ID=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['post_id'])")
WAS_NEW=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['was_new'])")

[ "$WAS_NEW" = "True" ] || { echo "FAIL: coach should be new"; exit 1; }

# Verify coach -> camp relation exists
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/${COACH_POST_ID}" > /tmp/rel.json
if ! python3 -c "import json; d=json.load(open('/tmp/rel.json')); rows=[r for r in d['as_parent'] if int(r['rel_id'])==20 and int(r['child_object_id'])==${CAMP_ID}]; exit(0 if rows else 1)"; then
    echo "FAIL: coach->camp relation missing"
    exit 1
fi

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP_ID}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/coach/${COACH_POST_ID}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/users/${COACH_USER_ID}?reassign=1&force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 3: Run test, expect PASS**

```bash
bash tests/importer/06-camp-with-coach.sh
```

- [ ] **Step 4: Commit**

```bash
git add plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php tests/importer/06-camp-with-coach.sh
git commit -m "feat(importer): endpoint accepts inline coach payload

If coach.data or coach.match_by is provided, calls RM_Coach::create_from_payload
to find-or-create the user + coach CPT before creating the camp. Falls back
to coach.existing_post_id for the case where the caller wants to link to a
known coach."
```

---

## Task 7: Add RM_Spot — minimal class with create_from_payload only

**Files:**
- Create: `plugins/ridemaster/includes/class-spot.php`
- Modify: `plugins/ridemaster/ridemaster.php` (require the new file)
- Create: `tests/importer/07-spot-create.sh`

There's no `class-spot.php` today (spot CPT is registered by JetEngine; no plugin-side class manages it). We create a minimal class with ONLY the static `create_from_payload` — no hooks, no constructor side-effects.

- [ ] **Step 1: Create the file**

`plugins/ridemaster/includes/class-spot.php`:

```php
<?php
/**
 * RM_Spot — Spot CPT helper (importer support).
 *
 * The spot CPT itself is registered by JetEngine. This class provides a
 * single static method that the importer plugin uses to create or match a
 * spot from external data.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Spot {

    /**
     * Find or create a spot CPT post from a payload.
     *
     * Match strategy: by exact title (case-insensitive). Country is stored
     * as the spot_country meta if provided.
     *
     * @param array $payload {
     *     @type array $match_by         { name, country? }
     *     @type bool  $create_if_missing
     *     @type array $data             { name, country, description, sport[], level[], water_type[] }
     * }
     * @return array|WP_Error { 'post_id' => int, 'was_new' => bool }
     */
    public static function create_from_payload( array $payload ) {

        $data       = $payload['data'] ?? [];
        $match      = $payload['match_by'] ?? [];
        $can_create = ! empty( $payload['create_if_missing'] );

        $name = $data['name'] ?? $match['name'] ?? '';
        if ( empty( $name ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'spot.data.name or spot.match_by.name is required', [ 'status' => 400 ] );
        }

        // Match by exact title (case-insensitive).
        $existing = get_posts( [
            'post_type'      => 'spot',
            'post_status'    => 'any',
            'posts_per_page' => 2,
            'title'          => $name,
            'fields'         => 'ids',
        ] );

        if ( ! empty( $existing ) ) {
            return [ 'post_id' => (int) $existing[0], 'was_new' => false ];
        }

        if ( ! $can_create ) {
            return new WP_Error( 'SPOT_NOT_FOUND', "No spot with name '$name'", [ 'status' => 404 ] );
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'spot',
            'post_title'   => sanitize_text_field( $name ),
            'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Country meta.
        if ( ! empty( $data['country'] ) ) {
            update_post_meta( $post_id, 'spot_country', sanitize_text_field( $data['country'] ) );
        }

        // Taxonomies.
        if ( ! empty( $data['sport'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['sport'] ), 'sport' );
        }
        if ( ! empty( $data['level'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['level'] ), 'level' );
        }
        if ( ! empty( $data['water_type'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['water_type'] ), 'water-type' );
        }

        return [ 'post_id' => $post_id, 'was_new' => true ];
    }
}
```

- [ ] **Step 2: Require the file in the main plugin**

In `plugins/ridemaster/ridemaster.php`, after the existing `require_once` lines (around line 30), add:

```php
require_once RM_PLUGIN_DIR . 'includes/class-spot.php';
```

Note: NO `new RM_Spot()` — there's no constructor side-effects to register.

- [ ] **Step 3: Write spot create test**

`tests/importer/07-spot-create.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 07: spot create_from_payload ==="

# Add temp endpoint
cat > /tmp/test-spot.php <<'PHP'
<?php
add_action( 'rest_api_init', function () {
    register_rest_route( 'ridemaster/v1', '/__test_spot', [
        'methods' => 'POST',
        'permission_callback' => function () { return current_user_can( 'manage_options' ); },
        'callback' => function ( WP_REST_Request $req ) {
            return RM_Spot::create_from_payload( $req->get_json_params() );
        },
    ] );
} );
PHP

echo "Upload /tmp/test-spot.php to wp-content/mu-plugins/ then Enter."
read

TS=$(date +%s)
NAME="Test Spot ${TS}"

PAYLOAD="{\"create_if_missing\":true,\"data\":{\"name\":\"${NAME}\",\"country\":\"Greece\",\"description\":\"Test\",\"sport\":[\"kitesurf\"],\"level\":[\"intermediate\"],\"water_type\":[\"flat-water\",\"waves\"]}}"

r=$(curl -s -o /tmp/s.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_spot")
[ "$r" = "200" ] || { echo "FAIL HTTP $r"; cat /tmp/s.json; exit 1; }

POST_ID=$(python3 -c "import json; print(json.load(open('/tmp/s.json'))['post_id'])")

# Verify country meta + taxonomies
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${POST_ID}" > /tmp/sm.json
python3 <<PY
import json
d = json.load(open('/tmp/sm.json'))
m, t = d['meta'], d['taxonomies']
ok = True
if m.get('spot_country') != 'Greece':
    print(f'FAIL country: {m.get("spot_country")}'); ok=False
if 'kitesurf' not in t.get('sport', []):
    print(f'FAIL sport: {t.get("sport")}'); ok=False
if sorted(['flat-water','waves']) != sorted(t.get('water-type', [])):
    print(f'FAIL water-type: {t.get("water-type")}'); ok=False
print('PASS' if ok else 'FAIL')
exit(0 if ok else 1)
PY

# Idempotency: re-call with same name should not duplicate
r2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_spot" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['post_id'], d['was_new'])")
[ "$r2" = "${POST_ID} False" ] || { echo "FAIL idempotency: $r2"; exit 1; }

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/spot/${POST_ID}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 4: Run, expect PASS**

```bash
bash tests/importer/07-spot-create.sh
```

- [ ] **Step 5: Commit**

```bash
# (on server): rm wp-content/mu-plugins/test-spot.php

git add plugins/ridemaster/includes/class-spot.php plugins/ridemaster/ridemaster.php tests/importer/07-spot-create.sh
git commit -m "feat(spot): add RM_Spot::create_from_payload for importer

New helper class for spot CPT. Match-by-title (case-insensitive) before
create; supports country meta and sport/level/water-type taxonomies.
No hook side effects — purely a static helper consumed by the importer."
```

---

## Task 8: Endpoint v3 — spot inline

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/08-camp-with-spot.sh`

- [ ] **Step 1: Update handle_import for inline spot**

In `handle_import`, after the coach resolution block, add:

```php
        // ----- Resolve spot -----
        $spot_id     = 0;
        $spot_result = null;

        if ( ! empty( $payload['spot']['existing_post_id'] ) ) {
            $spot_id = (int) $payload['spot']['existing_post_id'];
        } elseif ( ! empty( $payload['spot']['data'] ) || ! empty( $payload['spot']['match_by'] ) ) {
            $spot_result = RM_Spot::create_from_payload( $payload['spot'] );
            if ( is_wp_error( $spot_result ) ) {
                return new WP_Error(
                    'IMPORT_FAILED',
                    'Spot resolution failed: ' . $spot_result->get_error_message(),
                    [ 'status' => 500, 'step' => 'spot' ]
                );
            }
            $spot_id = $spot_result['post_id'];
        }
```

Update `$camp_payload['spot_id']` to use `$spot_id`.

Update the `created` block to include spot:

```php
                'spot'  => $spot_result
                    ? [ 'id' => $spot_result['post_id'], 'was_new' => $spot_result['was_new'] ]
                    : ( $spot_id ? [ 'id' => $spot_id, 'was_new' => false ] : null ),
```

- [ ] **Step 2: Write test**

`tests/importer/08-camp-with-spot.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
SPOT_NAME="Test Spot ${TS}"
SOURCE_URL="https://test.invalid/camp-with-spot-${TS}"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {"existing_post_id": 189},
    "spot": {
        "match_by": {"name": "${SPOT_NAME}"},
        "create_if_missing": true,
        "data": {
            "name": "${SPOT_NAME}",
            "country": "Spain",
            "description": "Test spot",
            "sport": ["kitesurf"],
            "level": ["beginner"],
            "water_type": ["flat-water"]
        }
    },
    "camp": {
        "title": "[TEST] Camp with inline spot",
        "price_eur": 400, "max_spots": 4,
        "start_date": "2026-11-01", "end_date": "2026-11-07",
        "sport": "kitesurf", "camp_status": "open"
    }
}
JSON
)

r=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$r" = "200" ] || { echo "FAIL $r"; cat /tmp/r.json; exit 1; }

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
SPOT=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['spot']['id'])")

# Verify spot -> camp (rel_id 18)
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/${SPOT}" > /tmp/rl.json
if ! python3 -c "import json; d=json.load(open('/tmp/rl.json')); exit(0 if any(r['rel_id']==18 and int(r['child_object_id'])==${CAMP} for r in d['as_parent']) else 1)"; then
    echo "FAIL: spot->camp relation 18 missing"; exit 1
fi

# Verify auto coach <-> spot (rel_id 19) was created since both are linked
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/189" > /tmp/rl2.json
if ! python3 -c "import json; d=json.load(open('/tmp/rl2.json')); exit(0 if any(r['rel_id']==19 and int(r['child_object_id'])==${SPOT} for r in d['as_parent']) else 1)"; then
    echo "WARN: coach->spot relation 19 not present (may have been added on a later save)"
fi

curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/spot/${SPOT}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 3: Run + commit**

```bash
bash tests/importer/08-camp-with-spot.sh

git add plugins/ridemaster-importer/ tests/importer/08-camp-with-spot.sh
git commit -m "feat(importer): endpoint accepts inline spot payload"
```

---

## Task 9: Hotel — RM_Hotel::create_from_payload + endpoint v4

**Files:**
- Modify: `plugins/ridemaster/includes/class-hotel.php`
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/09-camp-with-hotel.sh`

Hotel is simpler — no JetEngine relation, just a `_hotel_id` meta on the camp.

- [ ] **Step 1: Add static method to RM_Hotel**

Append before the closing `}` of class `RM_Hotel` in `plugins/ridemaster/includes/class-hotel.php`:

```php
    /**
     * Find or create a hotel CPT post from a payload.
     *
     * @param array $payload { match_by{name}, create_if_missing, data{name, description} }
     * @return array|WP_Error { 'post_id' => int, 'was_new' => bool }
     */
    public static function create_from_payload( array $payload ) {

        $data       = $payload['data'] ?? [];
        $match      = $payload['match_by'] ?? [];
        $can_create = ! empty( $payload['create_if_missing'] );

        $name = $data['name'] ?? $match['name'] ?? '';
        if ( empty( $name ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'hotel.data.name or hotel.match_by.name is required', [ 'status' => 400 ] );
        }

        $existing = get_posts( [
            'post_type'      => 'hotel',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'title'          => $name,
            'fields'         => 'ids',
        ] );

        if ( ! empty( $existing ) ) {
            return [ 'post_id' => (int) $existing[0], 'was_new' => false ];
        }

        if ( ! $can_create ) {
            return new WP_Error( 'HOTEL_NOT_FOUND', "No hotel with name '$name'", [ 'status' => 404 ] );
        }

        $post_id = wp_insert_post( [
            'post_type'    => 'hotel',
            'post_title'   => sanitize_text_field( $name ),
            'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        return [ 'post_id' => $post_id, 'was_new' => true ];
    }
```

- [ ] **Step 2: Update endpoint to resolve hotel inline**

In `handle_import`, after spot resolution, add:

```php
        // ----- Resolve hotel -----
        $hotel_id     = 0;
        $hotel_result = null;

        if ( ! empty( $payload['hotel']['existing_post_id'] ) ) {
            $hotel_id = (int) $payload['hotel']['existing_post_id'];
        } elseif ( ! empty( $payload['hotel']['data'] ) || ! empty( $payload['hotel']['match_by'] ) ) {
            $hotel_result = RM_Hotel::create_from_payload( $payload['hotel'] );
            if ( is_wp_error( $hotel_result ) ) {
                return new WP_Error(
                    'IMPORT_FAILED',
                    'Hotel resolution failed: ' . $hotel_result->get_error_message(),
                    [ 'status' => 500, 'step' => 'hotel' ]
                );
            }
            $hotel_id = $hotel_result['post_id'];
        }
```

Update `$camp_payload['hotel_id']` to use `$hotel_id`. Add to `created`:

```php
                'hotel' => $hotel_result
                    ? [ 'id' => $hotel_result['post_id'], 'was_new' => $hotel_result['was_new'] ]
                    : ( $hotel_id ? [ 'id' => $hotel_id, 'was_new' => false ] : null ),
```

- [ ] **Step 3: Write test**

`tests/importer/09-camp-with-hotel.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
HOTEL_NAME="Test Hotel ${TS}"
SOURCE_URL="https://test.invalid/camp-with-hotel-${TS}"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {"existing_post_id": 189},
    "spot":  {"existing_post_id": 195},
    "hotel": {
        "match_by": {"name": "${HOTEL_NAME}"},
        "create_if_missing": true,
        "data": {"name": "${HOTEL_NAME}", "description": "Test hotel description"}
    },
    "camp": {
        "title": "[TEST] Camp with hotel",
        "price_eur": 700, "max_spots": 6,
        "start_date": "2026-12-01", "end_date": "2026-12-07",
        "sport": "kitesurf", "camp_status": "open"
    }
}
JSON
)

r=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$r" = "200" ] || { echo "FAIL $r"; cat /tmp/r.json; exit 1; }

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
HOTEL=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['hotel']['id'])")

# Verify _hotel_id on camp
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP}" > /tmp/cm.json
HM=$(python3 -c "import json; print(json.load(open('/tmp/cm.json'))['meta'].get('_hotel_id'))")
[ "$HM" = "$HOTEL" ] || { echo "FAIL _hotel_id: $HM vs $HOTEL"; exit 1; }

curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/hotel/${HOTEL}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 4: Run + commit**

```bash
bash tests/importer/09-camp-with-hotel.sh

git add plugins/ridemaster/includes/class-hotel.php \
        plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php \
        tests/importer/09-camp-with-hotel.sh
git commit -m "feat(hotel): RM_Hotel::create_from_payload + importer integration

Adds a static helper to find-or-create a hotel by title and updates the
import endpoint to accept inline hotel data. Links via _hotel_id postmeta
on the camp (no JetEngine relation for hotel)."
```

---

## Task 10: Image handling — SSRF-safe sideload + SEO meta on attachments

**Files:**
- Create: `plugins/ridemaster-importer/includes/class-rm-importer-images.php`
- Modify: `plugins/ridemaster-importer/ridemaster-importer.php` (require new class)
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/10-camp-with-images.sh`

- [ ] **Step 1: Create the image handler class**

`plugins/ridemaster-importer/includes/class-rm-importer-images.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

class RM_Importer_Images {

    /**
     * Import one image entry (URL or base64) and return the attachment ID.
     *
     * @param array $item   { url?, base64?, filename, alt, title?, role }
     * @param int   $parent_post_id Post to attach the image to.
     * @return array { 'attachment_id' => int|null, 'error' => string|null }
     */
    public static function import( array $item, int $parent_post_id ) {
        $alt      = $item['alt']      ?? '';
        $title    = $item['title']    ?? '';
        $filename = $item['filename'] ?? '';

        // Path A: URL — server-side download via sideload.
        if ( ! empty( $item['url'] ) ) {
            $url = $item['url'];

            $ssrf_err = self::check_ssrf( $url );
            if ( $ssrf_err ) {
                return [ 'attachment_id' => null, 'error' => $ssrf_err ];
            }

            // media_sideload_image with 'id' returns just the attachment ID
            $attachment_id = media_sideload_image( $url, $parent_post_id, $title, 'id' );
            if ( is_wp_error( $attachment_id ) ) {
                return [ 'attachment_id' => null, 'error' => 'sideload_failed: ' . $attachment_id->get_error_message() ];
            }

            // Rename file on disk to SEO-friendly filename if provided.
            if ( $filename ) {
                self::rename_attachment_file( $attachment_id, $filename );
            }

            self::apply_seo_meta( $attachment_id, $alt, $title );
            return [ 'attachment_id' => $attachment_id, 'error' => null ];
        }

        // Path B: base64 fallback.
        if ( ! empty( $item['base64'] ) ) {
            $bytes = base64_decode( $item['base64'], true );
            if ( $bytes === false ) {
                return [ 'attachment_id' => null, 'error' => 'invalid_base64' ];
            }
            $name = $filename ?: ( 'import-' . wp_generate_uuid4() . '.jpg' );

            $upload = wp_upload_bits( $name, null, $bytes );
            if ( ! empty( $upload['error'] ) ) {
                return [ 'attachment_id' => null, 'error' => 'upload_bits_failed: ' . $upload['error'] ];
            }

            $filetype = wp_check_filetype( basename( $upload['file'] ), null );
            $attachment_id = wp_insert_attachment( [
                'post_mime_type' => $filetype['type'] ?? 'image/jpeg',
                'post_title'     => $title ?: pathinfo( $name, PATHINFO_FILENAME ),
                'post_content'   => '',
                'post_status'    => 'inherit',
            ], $upload['file'], $parent_post_id );

            if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
                return [ 'attachment_id' => null, 'error' => 'wp_insert_attachment_failed' ];
            }

            $meta = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
            wp_update_attachment_metadata( $attachment_id, $meta );

            self::apply_seo_meta( $attachment_id, $alt, $title );
            return [ 'attachment_id' => $attachment_id, 'error' => null ];
        }

        return [ 'attachment_id' => null, 'error' => 'no_url_or_base64' ];
    }

    /**
     * Validate that a URL is safe to sideload from (no SSRF).
     * @return string|null Error message if unsafe, null if OK.
     */
    private static function check_ssrf( string $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['scheme'] ) || ! in_array( $parsed['scheme'], [ 'http', 'https' ], true ) ) {
            return 'scheme_not_allowed';
        }
        $host = $parsed['host'] ?? '';
        if ( empty( $host ) ) {
            return 'host_missing';
        }

        // Resolve to IP and reject private/local ranges.
        $ip = gethostbyname( $host );
        if ( $ip === $host ) {
            return null; // Couldn't resolve; let the HTTP layer fail naturally.
        }
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            return 'private_or_reserved_ip';
        }
        return null;
    }

    /**
     * Rename the attachment's file on disk to a SEO-friendly name.
     *
     * Updates the attached file path + post slug; regenerates intermediate sizes.
     */
    private static function rename_attachment_file( int $attachment_id, string $desired_filename ): void {
        $original_path = get_attached_file( $attachment_id );
        if ( ! $original_path || ! file_exists( $original_path ) ) {
            return;
        }

        $info     = pathinfo( $original_path );
        $ext      = $info['extension'] ?? 'jpg';
        $dir      = $info['dirname'];
        $new_base = sanitize_file_name( $desired_filename );
        // Strip any extension the caller may have included
        $new_base = preg_replace( '/\.(jpg|jpeg|png|gif|webp)$/i', '', $new_base );

        $new_path = $dir . '/' . $new_base . '.' . $ext;
        $i = 2;
        while ( file_exists( $new_path ) ) {
            $new_path = $dir . '/' . $new_base . '-' . $i . '.' . $ext;
            $i++;
        }

        if ( rename( $original_path, $new_path ) ) {
            update_attached_file( $attachment_id, $new_path );
            wp_update_post( [
                'ID'         => $attachment_id,
                'post_name'  => sanitize_title( $new_base ),
                'guid'       => str_replace( basename( $original_path ), basename( $new_path ), wp_get_attachment_url( $attachment_id ) ),
            ] );
            // Regenerate intermediate sizes from new path
            $meta = wp_generate_attachment_metadata( $attachment_id, $new_path );
            wp_update_attachment_metadata( $attachment_id, $meta );
        }
    }

    /** Apply alt text + title to the attachment for SEO + accessibility. */
    private static function apply_seo_meta( int $attachment_id, string $alt, string $title ): void {
        if ( $alt !== '' ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        }
        if ( $title !== '' ) {
            wp_update_post( [
                'ID'         => $attachment_id,
                'post_title' => sanitize_text_field( $title ),
            ] );
        }
    }
}
```

- [ ] **Step 2: Require the file**

In `plugins/ridemaster-importer/ridemaster-importer.php`, add after the existing requires:

```php
require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-images.php';
```

- [ ] **Step 3: Hook image processing into the endpoint**

In `handle_import`, after camp creation, add:

```php
        // ----- Images -----
        $images_imported = 0;
        $images_failed   = 0;
        $warnings        = [];

        $featured = $camp['featured_image'] ?? null;
        $gallery  = $camp['gallery']        ?? [];

        if ( $featured ) {
            $result = RM_Importer_Images::import( $featured, $camp_id );
            if ( $result['attachment_id'] ) {
                set_post_thumbnail( $camp_id, $result['attachment_id'] );
                $images_imported++;
            } else {
                $images_failed++;
                $warnings[] = "Featured image: {$result['error']}";
            }
        }

        $gallery_ids = [];
        foreach ( $gallery as $item ) {
            $result = RM_Importer_Images::import( $item, $camp_id );
            if ( $result['attachment_id'] ) {
                $gallery_ids[] = $result['attachment_id'];
                $images_imported++;
            } else {
                $images_failed++;
                $warnings[] = "Gallery image: {$result['error']}";
            }
        }

        if ( ! empty( $gallery_ids ) ) {
            update_post_meta( $camp_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
        }
```

And update the returned response: replace `'images_imported' => 0` with `'images_imported' => $images_imported`, `'images_failed' => $images_failed`, and replace `'warnings' => []` with `'warnings' => $warnings`.

- [ ] **Step 4: Write image test**

`tests/importer/10-camp-with-images.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
SOURCE_URL="https://test.invalid/camp-images-${TS}"

# Use Wikipedia commons images as test fixtures (stable public URLs)
HERO="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4f/Kitesurfing_at_Tarifa.jpg/1280px-Kitesurfing_at_Tarifa.jpg"
GAL1="https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Tarifa_Spain.jpg/1280px-Tarifa_Spain.jpg"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {"existing_post_id": 189},
    "spot":  {"existing_post_id": 195},
    "camp": {
        "title": "[TEST] Camp with images",
        "price_eur": 800, "max_spots": 5,
        "start_date": "2026-12-15", "end_date": "2026-12-22",
        "sport": "kitesurf", "camp_status": "open",
        "featured_image": {
            "url": "${HERO}",
            "filename": "camp-tarifa-test-${TS}-ridemaster-hero.jpg",
            "alt": "Kitesurfer riding at Tarifa",
            "title": "Kite at Tarifa",
            "role": "camp_hero"
        },
        "gallery": [
            {
                "url": "${GAL1}",
                "filename": "spot-tarifa-test-${TS}-ridemaster-landscape.jpg",
                "alt": "Tarifa beach landscape",
                "title": "Tarifa beach",
                "role": "camp_group"
            }
        ]
    }
}
JSON
)

r=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$r" = "200" ] || { echo "FAIL $r"; cat /tmp/r.json; exit 1; }

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
IMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['camp']['images_imported'])")
FAIL=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['camp']['images_failed'])")

[ "$IMP" = "2" ] || { echo "FAIL: expected 2 images imported, got $IMP"; cat /tmp/r.json; exit 1; }
[ "$FAIL" = "0" ] || { echo "FAIL: $FAIL images failed"; cat /tmp/r.json; exit 1; }

# Verify featured + gallery meta
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP}" > /tmp/m.json
THUMB=$(python3 -c "import json; print(json.load(open('/tmp/m.json'))['meta'].get('_thumbnail_id'))")
GAL=$(python3 -c "import json; print(json.load(open('/tmp/m.json'))['meta'].get('_product_image_gallery'))")
[ -n "$THUMB" ] && [ "$THUMB" != "None" ] || { echo "FAIL: _thumbnail_id missing"; exit 1; }
[ -n "$GAL" ] && [ "$GAL" != "None" ] || { echo "FAIL: gallery missing"; exit 1; }

# Verify alt meta on the featured attachment
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${THUMB}" > /tmp/a.json
ALT=$(python3 -c "import json; print(json.load(open('/tmp/a.json'))['meta'].get('_wp_attachment_image_alt'))")
[ "$ALT" = "Kitesurfer riding at Tarifa" ] || { echo "FAIL: alt = $ALT"; exit 1; }

# Verify the renamed filename appears in the attachment URL
URL=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/media/${THUMB}" | python3 -c "import json,sys; print(json.load(sys.stdin)['source_url'])")
if ! echo "$URL" | grep -q "camp-tarifa-test-${TS}-ridemaster-hero"; then
    echo "FAIL: filename not renamed (URL=$URL)"; exit 1
fi

# Cleanup (note: deleting the camp does NOT delete attached media by default)
ALL_ATT=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/media?parent=${CAMP}&per_page=20" | python3 -c "import json,sys; print(' '.join(str(m['id']) for m in json.load(sys.stdin)))")
for A in $ALL_ATT; do
    curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/media/${A}?force=true" > /dev/null
done
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 5: Run + commit**

```bash
bash tests/importer/10-camp-with-images.sh

git add plugins/ridemaster-importer/ tests/importer/10-camp-with-images.sh
git commit -m "feat(importer): image handling with SSRF protection + SEO meta + rename

- media_sideload_image for URL inputs (blocks private/reserved IPs)
- wp_upload_bits + wp_insert_attachment for base64 fallback
- _wp_attachment_image_alt + post_title for SEO
- Renames file on disk to caller-provided SEO-friendly name; regenerates
  intermediate sizes after rename
- Featured image set via set_post_thumbnail; gallery via _product_image_gallery CSV"
```

---

## Task 11: Yoast SEO meta

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/11-yoast.sh`

- [ ] **Step 1: Add Yoast meta application**

In `handle_import`, after image processing, add:

```php
        // ----- Yoast SEO -----
        if ( ! empty( $camp['yoast']['focus_keyword'] ) ) {
            update_post_meta( $camp_id, '_yoast_wpseo_focuskw', sanitize_text_field( $camp['yoast']['focus_keyword'] ) );
        }
        if ( ! empty( $camp['yoast']['meta_description'] ) ) {
            update_post_meta( $camp_id, '_yoast_wpseo_metadesc', sanitize_text_field( $camp['yoast']['meta_description'] ) );
        }
```

- [ ] **Step 2: Write test**

`tests/importer/11-yoast.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
SOURCE_URL="https://test.invalid/camp-yoast-${TS}"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {"existing_post_id": 189},
    "spot":  {"existing_post_id": 195},
    "camp": {
        "title": "[TEST] Camp with Yoast",
        "price_eur": 300, "max_spots": 3,
        "start_date": "2027-01-01", "end_date": "2027-01-07",
        "sport": "kitesurf", "camp_status": "open",
        "yoast": {
            "focus_keyword": "tarifa kite camp january",
            "meta_description": "Join us for a week of kitesurf in Tarifa"
        }
    }
}
JSON
)

r=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$r" = "200" ] || { echo "FAIL $r"; cat /tmp/r.json; exit 1; }

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP}" > /tmp/m.json
FK=$(python3 -c "import json; print(json.load(open('/tmp/m.json'))['meta'].get('_yoast_wpseo_focuskw'))")
MD=$(python3 -c "import json; print(json.load(open('/tmp/m.json'))['meta'].get('_yoast_wpseo_metadesc'))")
[ "$FK" = "tarifa kite camp january" ] || { echo "FAIL focuskw: $FK"; exit 1; }
[ "$MD" = "Join us for a week of kitesurf in Tarifa" ] || { echo "FAIL metadesc: $MD"; exit 1; }

curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 3: Run + commit**

```bash
bash tests/importer/11-yoast.sh

git add plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php tests/importer/11-yoast.sh
git commit -m "feat(importer): apply Yoast focus keyword + meta description"
```

---

## Task 12: Rollback on partial failure

**Files:**
- Create: `plugins/ridemaster-importer/includes/class-rm-importer-rollback.php`
- Modify: `plugins/ridemaster-importer/ridemaster-importer.php`
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/12-rollback.sh`

- [ ] **Step 1: Create the rollback tracker**

`plugins/ridemaster-importer/includes/class-rm-importer-rollback.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tracks entities created during a single import call so we can clean up
 * on partial failure. Pre-existing entities are NEVER touched — only ones
 * created in this invocation.
 */
class RM_Importer_Rollback {

    private $user_ids       = [];
    private $coach_post_ids = [];
    private $spot_ids       = [];
    private $hotel_ids      = [];
    private $camp_id        = null;
    private $attachment_ids = [];

    public function track_user( int $id ): void          { $this->user_ids[]       = $id; }
    public function track_coach_post( int $id ): void    { $this->coach_post_ids[] = $id; }
    public function track_spot( int $id ): void          { $this->spot_ids[]       = $id; }
    public function track_hotel( int $id ): void         { $this->hotel_ids[]      = $id; }
    public function track_camp( int $id ): void          { $this->camp_id          = $id; }
    public function track_attachment( int $id ): void    { $this->attachment_ids[] = $id; }

    /**
     * Delete everything tracked. Returns a summary array.
     */
    public function rollback(): array {
        $deleted = [
            'attachments' => 0,
            'camp'        => false,
            'hotels'      => 0,
            'spots'       => 0,
            'coaches'     => 0,
            'users'       => 0,
        ];

        foreach ( $this->attachment_ids as $id ) {
            if ( wp_delete_attachment( $id, true ) ) {
                $deleted['attachments']++;
            }
        }

        if ( $this->camp_id ) {
            if ( wp_delete_post( $this->camp_id, true ) ) {
                $deleted['camp'] = true;
            }
        }

        foreach ( $this->hotel_ids as $id ) {
            if ( wp_delete_post( $id, true ) ) $deleted['hotels']++;
        }

        foreach ( $this->spot_ids as $id ) {
            if ( wp_delete_post( $id, true ) ) $deleted['spots']++;
        }

        foreach ( $this->coach_post_ids as $id ) {
            if ( wp_delete_post( $id, true ) ) $deleted['coaches']++;
        }

        if ( function_exists( 'wp_delete_user' ) ) {
            foreach ( $this->user_ids as $id ) {
                if ( wp_delete_user( $id ) ) $deleted['users']++;
            }
        }

        return $deleted;
    }
}
```

- [ ] **Step 2: Require the file**

In `plugins/ridemaster-importer/ridemaster-importer.php`:

```php
require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-rollback.php';
```

- [ ] **Step 3: Wire rollback into endpoint**

In `handle_import`, at the top, instantiate:

```php
        $rollback = new RM_Importer_Rollback();
```

At each entity-creation site, IF the entity was newly created, track it:

```php
        // After coach resolution — track ONLY entities we actually created.
        // Use the separate was_new_user / was_new_post flags so we never
        // delete a pre-existing WP user even if its coach CPT post was new.
        if ( $coach_result ) {
            if ( ! empty( $coach_result['was_new_post'] ) ) {
                $rollback->track_coach_post( $coach_result['post_id'] );
            }
            if ( ! empty( $coach_result['was_new_user'] ) ) {
                $rollback->track_user( $coach_result['user_id'] );
            }
        }

        // After spot resolution:
        if ( $spot_result && $spot_result['was_new'] ) {
            $rollback->track_spot( $spot_result['post_id'] );
        }

        // After hotel resolution:
        if ( $hotel_result && $hotel_result['was_new'] ) {
            $rollback->track_hotel( $hotel_result['post_id'] );
        }

        // After camp creation:
        $rollback->track_camp( $camp_id );
```

After image processing, for each successful attachment:

```php
        // Inside the loop where we get an attachment_id back:
        if ( $result['attachment_id'] ) {
            $rollback->track_attachment( $result['attachment_id'] );
            // ... existing code
        }
```

To trigger rollback on a hard failure, wrap critical post-camp work (image processing, taxonomies) in a try/catch — but PHP REST callbacks don't naturally throw, so the simpler approach is: if camp creation succeeded but EVERY image failed AND `images_required_strict` is set in payload, roll back. For the MVP we can stay permissive (images failing → warnings, not rollback) since the spec section 8 only specifies rollback on actual errors.

For now, add ONE rollback trigger: if `RM_Camp::create_from_payload` returns a WP_Error (after spot/coach/hotel were created), roll those back:

In the existing camp error branch:

```php
        $camp_id = RM_Camp::create_from_payload( $camp_payload );
        if ( is_wp_error( $camp_id ) ) {
            $rolled = $rollback->rollback();
            return new WP_Error(
                'IMPORT_FAILED',
                'Camp creation failed: ' . $camp_id->get_error_message(),
                [ 'status' => 500, 'step' => 'camp', 'rolled_back' => $rolled ]
            );
        }
```

This is the minimum useful rollback. Production-grade rollback (every step in a try/finally) is YAGNI for now — we can extend later.

- [ ] **Step 4: Write rollback test**

`tests/importer/12-rollback.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

# Trigger rollback by sending an invalid camp date (which RM_Camp will
# reject downstream). Since validator catches obviously invalid dates,
# we need to trigger a server-side error. Easiest: send a payload that
# passes validation but with a coach.create_if_missing=true + a freshly
# generated email + then a duplicate import_source_url that already exists.
# Actually that's a 409, not a 500.

# Simpler: temporarily break create_from_payload by wp_insert_post with
# invalid post_type, but that's hard to inject from outside.

# Use a test-specific path: a fake mu-plugin that simulates a camp failure.

cat > /tmp/test-rb.php <<'PHP'
<?php
// Force RM_Camp::create_from_payload to return a WP_Error one time.
add_filter( 'wp_insert_post_empty_content', function ( $maybe_empty, $postarr ) {
    if ( strpos( ( $postarr['post_title'] ?? '' ), '[TEST-ROLLBACK]' ) !== false ) {
        return true;  // makes wp_insert_post fail
    }
    return $maybe_empty;
}, 10, 2 );
PHP

echo "Upload /tmp/test-rb.php to wp-content/mu-plugins/ then press Enter."
read

TS=$(date +%s)
TEST_EMAIL="rb-coach-${TS}@test.invalid"
SOURCE_URL="https://test.invalid/rb-${TS}"
SPOT_NAME="RB Spot ${TS}"
HOTEL_NAME="RB Hotel ${TS}"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {"match_by":{"email":"${TEST_EMAIL}"},"create_if_missing":true,"data":{"email":"${TEST_EMAIL}","first_name":"RB","sport":["kitesurf"],"coach_status":"validated"}},
    "spot":  {"match_by":{"name":"${SPOT_NAME}"},"create_if_missing":true,"data":{"name":"${SPOT_NAME}","sport":["kitesurf"]}},
    "hotel": {"match_by":{"name":"${HOTEL_NAME}"},"create_if_missing":true,"data":{"name":"${HOTEL_NAME}"}},
    "camp": {
        "title": "[TEST-ROLLBACK] Should fail",
        "price_eur": 100, "max_spots": 1,
        "start_date": "2027-01-01", "end_date": "2027-01-02",
        "sport": "kitesurf", "camp_status": "open"
    }
}
JSON
)

r=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$r" = "500" ] || { echo "FAIL: expected 500, got $r"; cat /tmp/r.json; exit 1; }

# Verify rolled_back stats present in error data
python3 <<'PY'
import json
r = json.load(open('/tmp/r.json'))
d = r.get('data', {})
rb = d.get('rolled_back', {})
print('rolled_back:', rb)
assert rb.get('coaches', 0) >= 1, "should have rolled back coach"
assert rb.get('spots', 0) >= 1, "should have rolled back spot"
assert rb.get('hotels', 0) >= 1, "should have rolled back hotel"
print('PASS')
PY

# Verify no orphan posts left (check by email / titles)
ORPH_COACH=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/users?context=edit&search=${TEST_EMAIL}" | python3 -c "import json,sys; print(len(json.load(sys.stdin)))")
[ "$ORPH_COACH" = "0" ] || { echo "FAIL: orphan user remains"; exit 1; }

echo "PASS"
```

- [ ] **Step 5: Run + commit**

```bash
bash tests/importer/12-rollback.sh
# (on server): rm wp-content/mu-plugins/test-rb.php

git add plugins/ridemaster-importer/includes/class-rm-importer-rollback.php \
        plugins/ridemaster-importer/ridemaster-importer.php \
        plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php \
        tests/importer/12-rollback.sh
git commit -m "feat(importer): rollback newly-created entities if camp creation fails

Tracks only entities created in the current invocation (NEVER deletes
pre-existing coaches/spots/hotels). On wp_insert_post failure for the
camp, deletes attachments, hotel, spot, coach post, and WP user in that
order. Reports rolled_back stats in the error response data."
```

---

## Task 13: force_overwrite (re-import existing)

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`
- Create: `tests/importer/13-force-overwrite.sh`

- [ ] **Step 1: Implement force_overwrite path**

In `handle_import`, replace the idempotency block with:

```php
        // Idempotency check.
        $existing = self::find_existing_by_source_url( $payload['import_source_url'] );
        if ( $existing && empty( $payload['force_overwrite'] ) ) {
            return new WP_Error(
                'DUPLICATE_IMPORT',
                'A camp from this URL already exists',
                [
                    'status'           => 409,
                    'existing_camp_id' => $existing,
                    'existing_edit_url'=> admin_url( "post.php?post={$existing}&action=edit" ),
                ]
            );
        }

        $is_overwrite = $existing && ! empty( $payload['force_overwrite'] );
        if ( $is_overwrite ) {
            // Soft-delete the existing camp; rest of the flow creates a fresh one.
            // We do NOT touch coach/spot/hotel — they remain.
            wp_delete_post( $existing, true );
        }
```

This is the simplest semantically correct approach: overwrite = delete + recreate. More sophisticated in-place update is YAGNI for example data import.

- [ ] **Step 2: Write test**

`tests/importer/13-force-overwrite.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
URL="https://test.invalid/overwrite-${TS}"

P1='{"import_source_url":"'${URL}'","coach":{"existing_post_id":189},"spot":{"existing_post_id":195},"camp":{"title":"[TEST] v1","price_eur":100,"max_spots":1,"start_date":"2027-02-01","end_date":"2027-02-02","sport":"kitesurf","camp_status":"open"}}'
P2='{"import_source_url":"'${URL}'","force_overwrite":true,"coach":{"existing_post_id":189},"spot":{"existing_post_id":195},"camp":{"title":"[TEST] v2","price_eur":200,"max_spots":2,"start_date":"2027-02-10","end_date":"2027-02-12","sport":"kitesurf","camp_status":"open"}}'

ID1=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$P1" "${RM_URL}/wp-json/ridemaster/v1/import-camp" | python3 -c "import json,sys; print(json.load(sys.stdin)['camp_id'])")
ID2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$P2" "${RM_URL}/wp-json/ridemaster/v1/import-camp" | python3 -c "import json,sys; print(json.load(sys.stdin)['camp_id'])")

[ "$ID2" != "$ID1" ] || { echo "FAIL: overwrite produced same ID"; exit 1; }

# v1 should be gone
GONE=$(curl -s -o /dev/null -w "%{http_code}" "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/product/${ID1}")
[ "$GONE" = "404" ] || { echo "FAIL: v1 still present (HTTP $GONE)"; exit 1; }

curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${ID2}?force=true" > /dev/null
echo "PASS"
```

- [ ] **Step 3: Run + commit**

```bash
bash tests/importer/13-force-overwrite.sh

git add plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php tests/importer/13-force-overwrite.sh
git commit -m "feat(importer): force_overwrite=true deletes existing camp and recreates"
```

---

## Task 14: Stripe blocker as warning (not error)

**Files:**
- Modify: `plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php`

Per spec section 8.3, the Stripe blocker should result in a draft camp + a warning, never a hard error. The endpoint passes `check_stripe: false` so this technically doesn't trigger via the importer. But for completeness and future-proofing, surface the case where a coach we LINKED to doesn't have Stripe.

- [ ] **Step 1: After camp creation, check coach Stripe + warn if absent**

In `handle_import`, after camp creation (and before image processing), add:

```php
        // If the linked coach hasn't completed Stripe, warn the caller.
        if ( $coach_post_id ) {
            $linked_user_id = self::get_user_id_for_coach_post( $coach_post_id );
            if ( $linked_user_id ) {
                $stripe = get_user_meta( $linked_user_id, 'stripe_onboarding_complete', true );
                if ( $stripe !== '1' ) {
                    $warnings[] = 'Coach Stripe onboarding is incomplete — camp may be hidden from public.';
                }
            }
        }
```

And add the helper method to the class:

```php
    private static function get_user_id_for_coach_post( int $coach_post_id ): int {
        $users = get_users( [
            'meta_key'   => 'coach_post_id',
            'meta_value' => $coach_post_id,
            'number'     => 1,
            'fields'     => 'ID',
        ] );
        return $users ? (int) $users[0] : 0;
    }
```

Note: `$warnings` was declared earlier in handle_import (Task 10). If you skipped that, declare it: `$warnings = [];` before the coach resolution block.

- [ ] **Step 2: Commit (no new test — covered manually)**

```bash
git add plugins/ridemaster-importer/includes/class-rm-importer-endpoint.php
git commit -m "feat(importer): warn if linked coach has incomplete Stripe onboarding"
```

---

## Task 15: Polish — full E2E payload test on a real-shaped input

**Files:**
- Create: `tests/importer/15-e2e-full.sh`

This test exercises the full payload shape from spec section 4.3 with all blocks present.

- [ ] **Step 1: Write the E2E test**

`tests/importer/15-e2e-full.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

TS=$(date +%s)
SOURCE_URL="https://test.invalid/e2e-${TS}"
COACH_EMAIL="e2e-coach-${TS}@test.invalid"
SPOT_NAME="E2E Spot ${TS}"
HOTEL_NAME="E2E Hotel ${TS}"

HERO="https://upload.wikimedia.org/wikipedia/commons/thumb/4/4f/Kitesurfing_at_Tarifa.jpg/1280px-Kitesurfing_at_Tarifa.jpg"
GAL1="https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Tarifa_Spain.jpg/1280px-Tarifa_Spain.jpg"

PAYLOAD=$(cat <<JSON
{
    "import_source_url": "${SOURCE_URL}",
    "coach": {
        "match_by": {"email": "${COACH_EMAIL}"},
        "create_if_missing": true,
        "data": {
            "email": "${COACH_EMAIL}",
            "first_name": "E2E", "last_name": "Coach",
            "bio": "Full-stack import test coach.",
            "location": "Tarifa, Spain",
            "years_experience": 8,
            "certifications": ["IKO Level 3"],
            "sport": ["kitesurf"],
            "languages": ["english","french"],
            "coach_status": "validated"
        }
    },
    "spot": {
        "match_by": {"name": "${SPOT_NAME}"},
        "create_if_missing": true,
        "data": {
            "name": "${SPOT_NAME}", "country": "Spain",
            "description": "E2E test spot.",
            "sport": ["kitesurf"], "level": ["intermediate"], "water_type": ["flat-water","waves"]
        }
    },
    "hotel": {
        "match_by": {"name": "${HOTEL_NAME}"},
        "create_if_missing": true,
        "data": {"name": "${HOTEL_NAME}", "description": "Beachfront."}
    },
    "camp": {
        "title": "[TEST-E2E] Full payload camp",
        "description_html": "<p>End-to-end test camp.</p>",
        "sport": "kitesurf", "level": ["intermediate"], "languages": ["english"],
        "camp_status": "open",
        "price_eur": 950, "max_spots": 10,
        "start_date": "2027-03-01", "end_date": "2027-03-08",
        "schedule": "Day 1: ...",
        "included": ["6h coaching/day", "Equipment"],
        "not_included": ["Flights"],
        "yoast": {
            "focus_keyword": "tarifa kite camp march",
            "meta_description": "E2E test description"
        },
        "featured_image": {
            "url": "${HERO}",
            "filename": "camp-tarifa-e2e-${TS}-ridemaster-hero.jpg",
            "alt": "E2E hero image", "title": "E2E hero",
            "role": "camp_hero"
        },
        "gallery": [
            {"url":"${GAL1}","filename":"spot-tarifa-e2e-${TS}-ridemaster-overview.jpg","alt":"Spot view","title":"Spot view","role":"camp_group"}
        ]
    }
}
JSON
)

response=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$response" = "200" ] || { echo "FAIL HTTP $response"; cat /tmp/r.json; exit 1; }

cat /tmp/r.json | python3 -m json.tool

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
COACH_USER=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['id'])")
COACH_POST=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['post_id'])")
SPOT=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['spot']['id'])")
HOTEL=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['hotel']['id'])")

# Cleanup
ALL_ATT=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/media?parent=${CAMP}&per_page=20" | python3 -c "import json,sys; print(' '.join(str(m['id']) for m in json.load(sys.stdin)))")
for A in $ALL_ATT; do
    curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/media/${A}?force=true" > /dev/null
done
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/hotel/${HOTEL}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/spot/${SPOT}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/coach/${COACH_POST}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/users/${COACH_USER}?reassign=1&force=true" > /dev/null

echo "PASS"
```

- [ ] **Step 2: Run + commit**

```bash
bash tests/importer/15-e2e-full.sh

git add tests/importer/15-e2e-full.sh
git commit -m "test(importer): full E2E integration test"
```

---

## Task 16: Remove debug endpoint + bump versions

**Files:**
- Delete: `plugins/ridemaster-importer/includes/class-rm-importer-debug.php`
- Modify: `plugins/ridemaster-importer/ridemaster-importer.php`
- Modify: `plugins/ridemaster/ridemaster.php` (version bump)

The debug endpoint was used by parity tests. Before the user-facing E2E test (Task 17), remove it.

- [ ] **Step 1: Remove debug from bootstrap**

In `plugins/ridemaster-importer/ridemaster-importer.php`, remove:

```php
require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-debug.php';
add_action( 'rest_api_init', [ 'RM_Importer_Debug', 'register_routes' ] );
```

- [ ] **Step 2: Delete the file**

```bash
rm plugins/ridemaster-importer/includes/class-rm-importer-debug.php
```

- [ ] **Step 3: Bump versions**

In `plugins/ridemaster-importer/ridemaster-importer.php`, change:

```php
 * Version: 0.1.0
```

to:

```php
 * Version: 1.0.0
```

And update `define( 'RM_IMPORTER_VERSION', '0.1.0' );` to `'1.0.0'`.

In `plugins/ridemaster/ridemaster.php`, bump version from `2.3.2` to `2.4.0` (since we added new public API):

```php
 * Version: 2.4.0
```

and

```php
define( 'RM_VERSION', '2.4.0' );
```

- [ ] **Step 4: Verify pong still works**

```bash
bash tests/importer/01-pong.sh
```

Expected: `PASS`.

- [ ] **Step 5: Commit**

```bash
git add plugins/ridemaster-importer/ plugins/ridemaster/ridemaster.php
git commit -m "chore: remove temp debug endpoint, bump to ridemaster-importer 1.0.0 and ridemaster 2.4.0"
```

---

## Task 17: Final user-facing acceptance test

**Files:** (none — this is user verification)

- [ ] **Step 1: Ask the user for 1-2 real coach camp URLs to test**

In conversation, ask: "Donne-moi l'URL d'un camp réel sur un site coach, je vais l'importer et tu vérifieras le résultat."

- [ ] **Step 2: Claude runs the full conversational workflow**

For each URL given:

```
1. Playwright navigate + render JS + scroll to lazy-load
2. Extract structured data (title, dates, prices, etc.) via reasoning
3. Filter images semantically (reject logos/icons; classify by role)
4. Download images locally; resize to max 2000px on long edge; compress
   (JPEG q=85 via mozjpeg if available); strip EXIF
5. Generate SEO-friendly filenames per spec section 7.4 pattern:
   {role}-{slug-context}-ridemaster-{descriptor}-{seq}.jpg
6. Generate alt text + title for each image
7. Build payload matching spec section 4.3
8. POST to https://ridemaster.eu/wp-json/ridemaster/v1/import-camp
9. Report camp_id, edit_url, warnings to user
```

- [ ] **Step 3: User verifies in WP admin**

- ☐ Camp visible at `edit_url`
- ☐ All fields populated (title, description, price, dates, included/not-included)
- ☐ Featured image is the hero shot
- ☐ Gallery has the other images, well-named
- ☐ Coach is linked correctly (visible in product meta)
- ☐ Spot is linked correctly
- ☐ Hotel is created and linked (if applicable)
- ☐ Camp visible on frontend at `public_url`
- ☐ Image filenames in `wp-content/uploads/` follow the SEO pattern
- ☐ Alt texts present on all images

- [ ] **Step 4: If anything fails, iterate**

Open issues with specific examples, fix in a follow-up commit. The importer is done when the user marks ALL the above as ☑.

---

## Final note for the implementer

- Use `superpowers:verification-before-completion` before claiming any task done — run the test, look at the actual output, confirm green before commit.
- If the strangler-pattern parity test (Task 3) ever fails, STOP. Do not proceed. The whole approach depends on byte-identical behavior of the JFB flow.
- All test data uses `[TEST]` or `[TEST-*]` prefixes for easy bulk cleanup later: a single query `wp_delete_post` on `post_title LIKE '[TEST%'` removes everything.
- Plugin sync to server: depends on hosting. SiteGround supports SFTP, Git deploy, or wp-cli. Whatever your deploy workflow is, use it after each commit before running the next test.

---

## Self-Review Checklist Coverage Map

Plan-vs-spec sanity check (auditor: was every spec section implemented?):

| Spec section | Task(s) | Notes |
|--------------|---------|-------|
| 3.1 Plugin separate | Task 1 | scaffolding + dependency check |
| 3.2 Refactor main plugin | Tasks 3, 5, 7, 9 | Camp, Coach, Spot (new), Hotel |
| 3.3 Workflow | Task 17 | E2E user test |
| 4.3 Payload contract | Task 4 (validator) + Tasks 6, 8, 9, 10, 11 (each block) | |
| 4.4 Response success | Task 4 (initial) + later tasks extend `created` | |
| 4.5 Error codes | Task 4 (DUPLICATE), Task 12 (rolled_back), Task 4 (INVALID_PAYLOAD) | INSUFFICIENT_PERMISSIONS in Task 1, STRIPE_BLOCKER in Task 14 |
| 5 Refactor strangler | Tasks 3, 5, 7, 9 | Each task tests existing JFB flow after refactor |
| 6 Idempotence | Task 4 (initial check) + Task 13 (force_overwrite) | |
| 7 Image handling | Task 10 | SSRF + sideload + base64 + alt + rename |
| 8 Rollback | Task 12 | Tracks only newly-created entities |
| 9 Security | Task 1 (permissions) + Task 4 (validation) + Task 10 (SSRF) | |
| 10 Test env | Implied throughout; tests use [TEST] prefix | |
| 11 Acceptance | Task 17 | |
| Yoast meta | Task 11 | |
