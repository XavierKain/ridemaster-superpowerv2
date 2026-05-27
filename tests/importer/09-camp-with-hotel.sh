#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 09: import camp with inline hotel creation ==="

TS=$(date +%s)
HOTEL_NAME="[TEST] Hotel ${TS}"
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
[ "$r" = "200" ] || { echo "FAIL HTTP $r"; cat /tmp/r.json; exit 1; }

CAMP=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['camp_id'])")
HOTEL=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['hotel']['id'])")
WAS_NEW=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['hotel']['was_new'])")

[ "$WAS_NEW" = "True" ] || { echo "FAIL hotel was_new=$WAS_NEW"; exit 1; }

# Verify _hotel_id on camp
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${CAMP}" > /tmp/cm.json
HM=$(python3 -c "import json; print(json.load(open('/tmp/cm.json'))['meta'].get('_hotel_id'))")
[ "$HM" = "$HOTEL" ] || { echo "FAIL _hotel_id: got $HM, expected $HOTEL"; exit 1; }

# Idempotency: re-call hotel resolution alone should give same hotel id
HOTEL_PAYLOAD="{\"match_by\":{\"name\":\"${HOTEL_NAME}\"}}"
HOTEL2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$HOTEL_PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_hotel_create" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['post_id'], d['was_new'])")
[ "$HOTEL2" = "${HOTEL} False" ] || { echo "FAIL hotel idempotency: $HOTEL2"; exit 1; }

# Test HOTEL_NOT_FOUND on unknown name + create_if_missing=false
PAYLOAD2='{"create_if_missing":false,"match_by":{"name":"[TEST] Hotel Does Not Exist 999"}}'
r3=$(curl -s -o /tmp/h3.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD2" "${RM_URL}/wp-json/ridemaster/v1/__test_hotel_create")
[ "$r3" = "404" ] || { echo "FAIL HOTEL_NOT_FOUND: HTTP $r3"; cat /tmp/h3.json; exit 1; }

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/hotel/${HOTEL}?force=true" > /dev/null
echo "PASS"
