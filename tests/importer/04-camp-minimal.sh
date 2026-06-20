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

python3 - "${SOURCE_URL}" <<'PY'
import json, sys
source_url = sys.argv[1]
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
    ('_import_source_url', source_url),
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
sys.exit(0 if ok else 1)
PY

# Verify the camp shows up in the existing coach's relations
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/189" > /tmp/rm-rel.json
if ! python3 -c "import json; d=json.load(open('/tmp/rm-rel.json')); rows=[r for r in d['as_parent'] if int(r['rel_id'])==20 and int(r['child_object_id'])==${CAMP_ID}]; exit(0 if rows else 1)"; then
    echo "FAIL: coach 189 -> camp ${CAMP_ID} relation (rel_id 20) not present"
    exit 1
fi

# Test idempotency: re-submitting the same source_url should 409
r2=$(curl -s -o /tmp/rm-r2.json -w "%{http_code}" "${RM_AUTH[@]}" \
    -X POST -H "Content-Type: application/json" \
    -d "{
        \"import_source_url\": \"${SOURCE_URL}\",
        \"camp\": {
            \"title\": \"[TEST] Idempotent retry\",
            \"price_eur\": 100, \"max_spots\": 1,
            \"start_date\": \"2026-09-01\", \"end_date\": \"2026-09-02\",
            \"sport\": \"kitesurf\"
        },
        \"coach\": {\"existing_post_id\": 189},
        \"spot\":  {\"existing_post_id\": 195}
    }" \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp")

if [ "$r2" != "409" ]; then
    echo "FAIL: expected idempotency to return 409, got $r2"
    cat /tmp/rm-r2.json
    exit 1
fi

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP_ID}?force=true" > /dev/null
echo "PASS"
