#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 12: rollback cleans up only newly-created entities ==="

TS=$(date +%s)
EMAIL="rb-${TS}@test.invalid"
SPOT_NAME="[TEST] RB Spot ${TS}"
HOTEL_NAME="[TEST] RB Hotel ${TS}"

PAYLOAD=$(cat <<JSON
{
    "coach": {
        "match_by": {"email": "${EMAIL}"},
        "create_if_missing": true,
        "data": {"email": "${EMAIL}", "first_name": "RB", "sport": ["kitesurf"], "coach_status": "validated"}
    },
    "spot":  {"match_by": {"name": "${SPOT_NAME}"},  "create_if_missing": true, "data": {"name": "${SPOT_NAME}",  "sport": ["kitesurf"]}},
    "hotel": {"match_by": {"name": "${HOTEL_NAME}"}, "create_if_missing": true, "data": {"name": "${HOTEL_NAME}"}}
}
JSON
)

r=$(curl -s -o /tmp/rb.json -w "%{http_code}" "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_rollback")
[ "$r" = "200" ] || { echo "FAIL HTTP $r"; cat /tmp/rb.json; exit 1; }

cat /tmp/rb.json | python3 -m json.tool

# Verify rollback summary
python3 <<'PY'
import json, sys
d = json.load(open('/tmp/rb.json'))
rb = d['rolled_back']
ck = d['post_rollback_check']
ok = True

# Note: the user counter may be 0 because the main plugin's class-cleanup.php
# hooks into wp_delete_post for coach CPTs and cascade-deletes the linked WP
# user before our explicit wp_delete_user loop runs. The correct invariant
# to assert is the post-rollback existence check below, not this counter.
if rb['coaches'] < 1: print(f"FAIL: expected at least 1 coach rolled back, got {rb['coaches']}"); ok=False
if rb['spots']   < 1: print(f"FAIL: expected at least 1 spot rolled back, got {rb['spots']}"); ok=False
if rb['hotels']  < 1: print(f"FAIL: expected at least 1 hotel rolled back, got {rb['hotels']}"); ok=False

# Post-rollback checks: all should be False (entity no longer exists).
for k, v in ck.items():
    if v is True:
        print(f"FAIL: {k} should be deleted but still exists"); ok=False

print('PASS' if ok else 'FAIL')
sys.exit(0 if ok else 1)
PY
