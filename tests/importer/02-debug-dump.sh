#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/_env.sh"

echo "=== Test 02: debug dump on existing camp 2279 (Coaching Mayapo) ==="

# Meta dump
status=$(curl -s -o /tmp/rm-meta.json -w "%{http_code}" \
    "${RM_AUTH[@]}" \
    "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_meta/2279")

if [ "$status" != "200" ]; then
    echo "FAIL meta dump: HTTP $status"
    cat /tmp/rm-meta.json
    exit 1
fi

# Sanity check: meta should contain expected camp keys
# (Use full_date instead of camp_start_date — pre-existing camps were created
# before the camp_start_date meta was added to the JFB save flow; full_date
# is the JetEngine canonical date field and is present on all camps.)
for key in _price full_date _stock _thumbnail_id; do
    if ! python3 -c "import json,sys; d=json.load(open('/tmp/rm-meta.json')); sys.exit(0 if '$key' in d['meta'] else 1)"; then
        echo "FAIL: expected meta key '$key' not present"
        exit 1
    fi
done

# Relations dump
status=$(curl -s -o /tmp/rm-rel.json -w "%{http_code}" \
    "${RM_AUTH[@]}" \
    "${RM_URL}/wp-json/ridemaster/v1/__debug_dump_relations/2279")

if [ "$status" != "200" ]; then
    echo "FAIL relations dump: HTTP $status"
    exit 1
fi

# Should have at least one relation (this camp has a coach)
count=$(python3 -c "import json; d=json.load(open('/tmp/rm-rel.json')); print(len(d['as_parent']) + len(d['as_child']))")
if [ "$count" -eq 0 ]; then
    echo "FAIL: expected at least one relation row for camp 2279"
    exit 1
fi

echo "PASS: meta and relations dumps OK ($count relations found)"
