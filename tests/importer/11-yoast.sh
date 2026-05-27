#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 11: Yoast SEO meta (focus keyword + meta description) ==="

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
[ "$FK" = "tarifa kite camp january" ] || { echo "FAIL focuskw: '$FK'"; exit 1; }
[ "$MD" = "Join us for a week of kitesurf in Tarifa" ] || { echo "FAIL metadesc: '$MD'"; exit 1; }

curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
echo "PASS"
