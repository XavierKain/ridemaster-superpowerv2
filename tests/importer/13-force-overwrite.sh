#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 13: force_overwrite=true deletes existing camp + recreates ==="

TS=$(date +%s)
URL="https://test.invalid/overwrite-${TS}"

P1=$(cat <<JSON
{
    "import_source_url": "${URL}",
    "coach": {"existing_post_id": 189},
    "spot":  {"existing_post_id": 195},
    "camp": {"title": "[TEST] v1", "price_eur": 100, "max_spots": 1, "start_date": "2027-02-01", "end_date": "2027-02-02", "sport": "kitesurf", "camp_status": "open"}
}
JSON
)
P2=$(cat <<JSON
{
    "import_source_url": "${URL}",
    "force_overwrite": true,
    "coach": {"existing_post_id": 189},
    "spot":  {"existing_post_id": 195},
    "camp": {"title": "[TEST] v2", "price_eur": 200, "max_spots": 2, "start_date": "2027-02-10", "end_date": "2027-02-12", "sport": "kitesurf", "camp_status": "open"}
}
JSON
)

ID1=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$P1" "${RM_URL}/wp-json/ridemaster/v1/import-camp" | python3 -c "import json,sys; print(json.load(sys.stdin)['camp_id'])")
echo "Created v1: camp_id=${ID1}"

# Without force_overwrite — should 409
R_DUPE=$(curl -s -o /dev/null -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$P1" "${RM_URL}/wp-json/ridemaster/v1/import-camp")
[ "$R_DUPE" = "409" ] || { echo "FAIL: expected 409 without force_overwrite, got $R_DUPE"; exit 1; }

# With force_overwrite — should create a NEW camp with different ID
ID2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$P2" "${RM_URL}/wp-json/ridemaster/v1/import-camp" | python3 -c "import json,sys; print(json.load(sys.stdin)['camp_id'])")
echo "Overwrote with v2: camp_id=${ID2}"

[ "$ID2" != "$ID1" ] || { echo "FAIL: overwrite produced same ID"; exit 1; }

# v1 should be gone
GONE=$(curl -s -o /dev/null -w "%{http_code}" "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/product/${ID1}")
[ "$GONE" = "404" ] || { echo "FAIL: v1 (id=${ID1}) still present (HTTP $GONE)"; exit 1; }

# v2 should have the new price (verify the overwrite actually applied)
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${ID2}" > /tmp/m.json
PRICE=$(python3 -c "import json; print(json.load(open('/tmp/m.json'))['meta'].get('_price'))")
[ "$PRICE" = "200" ] || { echo "FAIL: v2 _price = $PRICE (expected 200)"; exit 1; }

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${ID2}?force=true" > /dev/null
echo "PASS"
