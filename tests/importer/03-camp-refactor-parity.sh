#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 03: RM_Camp refactor parity (JFB vs create_from_payload) ==="

response=$(curl -s -o /tmp/rm-parity.json -w "%{http_code}" \
    "${RM_AUTH[@]}" -X POST \
    "${RM_URL}/wp-json/ridemaster/v1/__test_camp_parity")

if [ "$response" != "200" ]; then
    echo "FAIL: HTTP $response"
    cat /tmp/rm-parity.json
    exit 1
fi

python3 <<'PY'
import json, sys
d = json.load(open('/tmp/rm-parity.json'))
jfb = d.get('jfb_meta', {})
pay = d.get('payload_meta', {})

if d.get('payload_err'):
    print(f"FAIL: payload path errored: {d['payload_err']}")
    sys.exit(1)

# Keys that MUST match exactly between the two creation paths.
# (Stock keys included to verify the shutdown hook fires the same way.)
expected_keys = [
    '_price', '_regular_price', '_stock', '_manage_stock', '_stock_status',
    'camp_start_date', 'camp_end_date', 'full_date', 'full_date__end_date',
    'full_date__config',
]

ok = True
for k in expected_keys:
    j = str(jfb.get(k, '<MISSING>'))
    p = str(pay.get(k, '<MISSING>'))
    if j != p:
        print(f"DIFF on {k}:")
        print(f"  jfb:     {j!r}")
        print(f"  payload: {p!r}")
        ok = False
    else:
        print(f"OK on {k}: {j}")

if ok:
    print("\nPASS: all canonical meta keys are byte-identical")
    sys.exit(0)
else:
    print("\nFAIL: meta diverged — refactor broke parity")
    sys.exit(1)
PY
