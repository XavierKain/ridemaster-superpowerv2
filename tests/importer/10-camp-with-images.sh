#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 10: camp with images (URL sideload) ==="

TS=$(date +%s)
SOURCE_URL="https://test.invalid/camp-images-${TS}"

# Use picsum.photos for stable test fixtures. They serve a random 1280x720 image
# at any URL ending in `/<width>/<height>.jpg`; the seed segment makes each test
# run deterministic-ish and avoids collisions in WP's filename de-dup logic.
HERO="https://picsum.photos/seed/kitehero${TS}/1280/720.jpg"
GAL1="https://picsum.photos/seed/kitegal${TS}/1280/720.jpg"

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

r=$(curl -s -o /tmp/r.json -w "%{http_code}" "${RM_AUTH[@]}" \
    -X POST -H "Content-Type: application/json" -d "$PAYLOAD" \
    "${RM_URL}/wp-json/ridemaster/v1/import-camp" --max-time 60)
[ "$r" = "200" ] || { echo "FAIL HTTP $r"; cat /tmp/r.json; exit 1; }

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

# Cleanup
ALL_ATT=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/media?parent=${CAMP}&per_page=20" | python3 -c "import json,sys; print(' '.join(str(m['id']) for m in json.load(sys.stdin)))")
for A in $ALL_ATT; do
    curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/media/${A}?force=true" > /dev/null
done
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP}?force=true" > /dev/null
echo "PASS"
