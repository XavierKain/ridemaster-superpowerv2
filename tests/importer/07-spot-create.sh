#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 07: RM_Spot::create_from_payload ==="

TS=$(date +%s)
NAME="[TEST] Spot ${TS}"

PAYLOAD=$(cat <<JSON
{
    "create_if_missing": true,
    "data": {
        "name": "${NAME}",
        "country": "Greece",
        "description": "Test spot description",
        "sport": ["kitesurf"],
        "level": ["intermediate"],
        "water_type": ["flat-water","waves"]
    }
}
JSON
)

r=$(curl -s -o /tmp/s.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_spot_create")
[ "$r" = "200" ] || { echo "FAIL HTTP $r"; cat /tmp/s.json; exit 1; }

POST_ID=$(python3 -c "import json; print(json.load(open('/tmp/s.json'))['post_id'])")
WAS_NEW=$(python3 -c "import json; print(json.load(open('/tmp/s.json'))['was_new'])")
[ "$WAS_NEW" = "True" ] || { echo "FAIL was_new should be True (got $WAS_NEW)"; exit 1; }

# Verify country meta + taxonomies
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${POST_ID}" > /tmp/sm.json
python3 <<'PY'
import json
d = json.load(open('/tmp/sm.json'))
m, t = d['meta'], d['taxonomies']
ok = True
if m.get('spot_country') != 'Greece':
    print(f'FAIL country: {m.get("spot_country")}'); ok=False
if 'kitesurf' not in t.get('sport', []):
    print(f'FAIL sport: {t.get("sport")}'); ok=False
if 'intermediate' not in t.get('level', []):
    print(f'FAIL level: {t.get("level")}'); ok=False
if sorted(['flat-water','waves']) != sorted(t.get('water-type', [])):
    print(f'FAIL water-type: {t.get("water-type")}'); ok=False
print('PASS' if ok else 'FAIL')
exit(0 if ok else 1)
PY

# Idempotency: re-call with same name → was_new=False + same post_id
r2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_spot_create" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d['post_id'], d['was_new'])")
[ "$r2" = "${POST_ID} False" ] || { echo "FAIL idempotency: $r2 (expected ${POST_ID} False)"; exit 1; }

# Test SPOT_NOT_FOUND when create_if_missing=false and unknown name
PAYLOAD2='{"create_if_missing":false,"match_by":{"name":"[TEST] Definitely Does Not Exist 999"}}'
r3=$(curl -s -o /tmp/s3.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD2" "${RM_URL}/wp-json/ridemaster/v1/__test_spot_create")
[ "$r3" = "404" ] || { echo "FAIL: expected 404 SPOT_NOT_FOUND, got $r3"; cat /tmp/s3.json; exit 1; }

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/spot/${POST_ID}?force=true" > /dev/null
echo "PASS"
