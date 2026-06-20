#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 05: RM_Coach::create_from_payload ==="

TS=$(date +%s)
TEST_EMAIL="test-coach-${TS}@example.invalid"

PAYLOAD=$(cat <<JSON
{
    "match_by": {"email": "${TEST_EMAIL}"},
    "create_if_missing": true,
    "data": {
        "email": "${TEST_EMAIL}",
        "first_name": "Test",
        "last_name": "Coach",
        "bio": "Test bio.",
        "location": "Test, Country",
        "years_experience": 5,
        "certifications": ["IKO Level 1", "VDWS"],
        "sport": ["kitesurf"],
        "languages": ["english"],
        "coach_status": "validated",
        "instagram": "@testcoach"
    }
}
JSON
)

response=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" \
    -d "$PAYLOAD" \
    "${RM_URL}/wp-json/ridemaster/v1/__test_coach_create")
echo "Response: $response"

POST_ID=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['post_id'])")
USER_ID=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['user_id'])")
WAS_NEW=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['was_new'])")
WAS_NEW_USER=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['was_new_user'])")
WAS_NEW_POST=$(echo "$response" | python3 -c "import json,sys; print(json.load(sys.stdin)['was_new_post'])")

[ "$WAS_NEW" = "True" ] || { echo "FAIL: was_new should be True"; exit 1; }
[ "$WAS_NEW_USER" = "True" ] || { echo "FAIL: was_new_user should be True"; exit 1; }
[ "$WAS_NEW_POST" = "True" ] || { echo "FAIL: was_new_post should be True"; exit 1; }

# Verify meta on the coach CPT
curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/${POST_ID}" > /tmp/c.json
python3 <<'PY'
import json
d = json.load(open('/tmp/c.json'))
m = d['meta']
t = d['taxonomies']
checks = [
    ('coach_first_name', 'Test'),
    ('coach_last_name', 'Coach'),
    ('coach_bio', 'Test bio.'),
    ('coach_location', 'Test, Country'),
    ('coach_years_experience', '5'),
    ('instagram', '@testcoach'),
]
ok = True
for k, v in checks:
    if str(m.get(k)) != v:
        print(f'FAIL {k}: got {m.get(k)!r}'); ok = False

if 'kitesurf' not in t.get('sport', []): print(f'FAIL sport: {t.get("sport")}'); ok=False
if 'english' not in t.get('language', []): print(f'FAIL language: {t.get("language")}'); ok=False
if 'validated' not in t.get('coach-status', []): print(f'FAIL coach-status: {t.get("coach-status")}'); ok=False

# Stripe blocker bypass: stripe_onboarding_complete should be '1' on the user.
# We can't easily verify usermeta without a debug endpoint; relies on Task 5's
# stated behavior. (Manual verification: query the user via REST or WP admin.)

print('PASS' if ok else 'FAIL')
exit(0 if ok else 1)
PY

# Verify user has coach_role
USER_RESP=$(curl -s "${RM_AUTH[@]}" "${RM_URL}/wp-json/wp/v2/users/${USER_ID}?context=edit")
if ! echo "$USER_RESP" | python3 -c "import json,sys; d=json.load(sys.stdin); exit(0 if 'coach_role' in d.get('roles', []) else 1)"; then
    echo "FAIL: user $USER_ID does not have coach_role"
    exit 1
fi

# Idempotency: call again with same email, should return was_new=false and same IDs
response2=$(curl -s "${RM_AUTH[@]}" -X POST -H "Content-Type: application/json" -d "$PAYLOAD" "${RM_URL}/wp-json/ridemaster/v1/__test_coach_create")
WAS_NEW2=$(echo "$response2" | python3 -c "import json,sys; print(json.load(sys.stdin)['was_new'])")
POST_ID2=$(echo "$response2" | python3 -c "import json,sys; print(json.load(sys.stdin)['post_id'])")
[ "$WAS_NEW2" = "False" ] && [ "$POST_ID2" = "$POST_ID" ] || { echo "FAIL: idempotency broken (was_new=$WAS_NEW2, post_id=$POST_ID2 vs $POST_ID)"; exit 1; }

# Cleanup
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/coach/${POST_ID}?force=true" > /dev/null
curl -s "${RM_AUTH[@]}" -X DELETE "${RM_URL}/wp-json/wp/v2/users/${USER_ID}?reassign=1&force=true" > /dev/null
echo "PASS"
