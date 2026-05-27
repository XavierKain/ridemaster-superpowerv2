#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 06: import camp with inline coach (new coach + link to existing spot=195) ==="

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
WAS_NEW_USER=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['was_new_user'])")
WAS_NEW_POST=$(python3 -c "import json; print(json.load(open('/tmp/r.json'))['created']['coach']['was_new_post'])")

[ "$WAS_NEW" = "True" ] || { echo "FAIL: coach should be new (was_new=$WAS_NEW)"; exit 1; }
[ "$WAS_NEW_USER" = "True" ] || { echo "FAIL: was_new_user should be True"; exit 1; }
[ "$WAS_NEW_POST" = "True" ] || { echo "FAIL: was_new_post should be True"; exit 1; }

# Verify coach -> camp relation exists (rel_id 20)
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/${COACH_POST_ID}" > /tmp/rel.json
if ! python3 -c "import json; d=json.load(open('/tmp/rel.json')); rows=[r for r in d['as_parent'] if int(r['rel_id'])==20 and int(r['child_object_id'])==${CAMP_ID}]; exit(0 if rows else 1)"; then
    echo "FAIL: coach->camp relation (rel_id 20) missing"
    exit 1
fi

# Verify spot -> camp relation exists too (rel_id 18) — spot 195 was passed as existing_post_id
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/195" > /tmp/rel2.json
if ! python3 -c "import json; d=json.load(open('/tmp/rel2.json')); rows=[r for r in d['as_parent'] if int(r['rel_id'])==18 and int(r['child_object_id'])==${CAMP_ID}]; exit(0 if rows else 1)"; then
    echo "FAIL: spot->camp relation (rel_id 18) missing"
    exit 1
fi

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/product/${CAMP_ID}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/coach/${COACH_POST_ID}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/users/${COACH_USER_ID}?reassign=1&force=true" > /dev/null
echo "PASS"
