#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
failures=0
count=0
for test in "$ROOT"/tests/*regression.php; do
  [ -f "$test" ] || continue
  count=$((count+1))
  if php "$test" >/tmp/gdcp-regression.out 2>&1; then
    printf 'PASS %s\n' "$(basename "$test")"
  else
    printf 'FAIL %s\n' "$(basename "$test")"
    cat /tmp/gdcp-regression.out
    failures=$((failures+1))
  fi
done
printf 'RESULT %d regression contracts; failures=%d\n' "$count" "$failures"
exit "$failures"
