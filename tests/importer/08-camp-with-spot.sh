#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 08: import camp with inline spot creation ==="

TS=$(date +%s)
SPOT_NAME="[TEST] Spot ${TS}"
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
            "country": "Italy",
            "description": "Test spot for inline creation",
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
[ "$r" = "200" ] || { echo "FAIL HTTP $r"; cat /tmp/r.json; exit 1; }

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
SPOT=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['spot']['id'])")
WAS_NEW_SPOT=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['spot']['was_new'])")

[ "$WAS_NEW_SPOT" = "True" ] || { echo "FAIL: spot should be new (was_new=$WAS_NEW_SPOT)"; exit 1; }

# Verify spot -> camp relation (rel_id 18)
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/${SPOT}" > /tmp/rl.json
if ! python3 -c "import json; d=json.load(open('/tmp/rl.json')); rows=[r for r in d['as_parent'] if int(r['rel_id'])==18 and int(r['child_object_id'])==${CAMP}]; exit(0 if rows else 1)"; then
    echo "FAIL: spot->camp relation (rel_id 18) missing"
    exit 1
fi

# Verify the new spot has country meta + taxos as specified
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${SPOT}" > /tmp/sm.json
python3 <<'PY'
import json
d = json.load(open('/tmp/sm.json'))
m, t = d['meta'], d['taxonomies']
ok = True
if m.get('spot_country') != 'Italy':
    print(f'FAIL country: {m.get("spot_country")}'); ok = False
if 'kitesurf' not in t.get('sport', []):
    print(f'FAIL sport: {t.get("sport")}'); ok = False
if 'flat-water' not in t.get('water-type', []):
    print(f'FAIL water-type: {t.get("water-type")}'); ok = False
print('PASS' if ok else 'FAIL')
exit(0 if ok else 1)
PY

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/spot/${SPOT}?force=true" > /dev/null
echo "PASS"
