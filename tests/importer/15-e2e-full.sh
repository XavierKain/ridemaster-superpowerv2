#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 15: full E2E payload (coach + spot + hotel + images + Yoast all inline) ==="

TS=$(date +%s)
SOURCE_URL="https://test.invalid/e2e-${TS}"
COACH_EMAIL="e2e-coach-${TS}@test.invalid"
SPOT_NAME="[TEST] E2E Spot ${TS}"
HOTEL_NAME="[TEST] E2E Hotel ${TS}"

HERO="https://picsum.photos/seed/e2ehero${TS}/1280/720.jpg"
GAL1="https://picsum.photos/seed/e2egal${TS}/1280/720.jpg"

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
            "coach_status": "validated",
            "instagram": "@e2etest"
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
        "schedule": "Day 1: Welcome dinner. Days 2-7: Sessions.",
        "included": ["6h coaching/day", "Equipment"],
        "not_included": ["Flights"],
        "yoast": {
            "focus_keyword": "tarifa kite camp march",
            "meta_description": "E2E test description for the full payload import"
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

response=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" \
    -X POST -H "Content-Type: application/json" -d "$PAYLOAD" \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp" --max-time 90)

[ "$response" = "200" ] || { echo "FAIL HTTP $response"; cat /tmp/r.json; exit 1; }

echo "--- Response ---"
python3 -m json.tool /tmp/r.json

# Extract IDs for verification + cleanup
CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
COACH_USER=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['id'])")
COACH_POST=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['post_id'])")
SPOT=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['spot']['id'])")
HOTEL=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['hotel']['id'])")
IMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['camp']['images_imported'])")

[ "$IMP" = "2" ] || { echo "FAIL: expected 2 images imported, got $IMP"; exit 1; }

# Verify every entity exists
for endpoint in "product/${CAMP}" "coach/${COACH_POST}" "spot/${SPOT}" "hotel/${HOTEL}" "users/${COACH_USER}?context=edit"; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/${endpoint}")
    [ "$code" = "200" ] || { echo "FAIL: ${endpoint} returned ${code}"; exit 1; }
done

# Verify camp meta has all the canonical fields
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP}" > /tmp/cm.json
python3 - "${SOURCE_URL}" <<'PY'
import json, sys
url = sys.argv[1]
d = json.load(open('/tmp/cm.json'))
m, t = d['meta'], d['taxonomies']
checks = [
    ('_price', '950'),
    ('_stock', '10'),
    ('camp_start_date', '2027-03-01'),
    ('camp_end_date',   '2027-03-08'),
    ('_yoast_wpseo_focuskw', 'tarifa kite camp march'),
    ('_import_source_url', url),
]
ok = True
for k, v in checks:
    if str(m.get(k)) != v:
        print(f'FAIL {k}: got {m.get(k)!r} expected {v!r}')
        ok = False
if 'open' not in t.get('camp-status', []):  print('FAIL camp-status'); ok=False
if 'kitesurf' not in t.get('sport', []):    print('FAIL sport'); ok=False
print('PASS' if ok else 'FAIL'); sys.exit(0 if ok else 1)
PY

# Cleanup: attachments → camp → hotel → spot → coach CPT → coach user
ALL_ATT=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/media?parent=${CAMP}&per_page=20" | python3 -c "import json,sys; print(' '.join(str(m['id']) for m in json.load(sys.stdin)))")
for A in $ALL_ATT; do
    curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/media/${A}?force=true" > /dev/null
done
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/hotel/${HOTEL}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/spot/${SPOT}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/coach/${COACH_POST}?force=true" > /dev/null
# Note: deleting the coach post may cascade-delete the WP user too via class-cleanup.php
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/users/${COACH_USER}?reassign=1&force=true" > /dev/null

echo "PASS — full E2E import + all entities verified + cleanup done"
