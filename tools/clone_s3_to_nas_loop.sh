#!/usr/bin/env bash
set -u

cd "$(dirname "$0")/.." || exit 1

LOG="storage/logs/storage-clone-s3-nas.log"
mkdir -p "$(dirname "$LOG")"

exec >> "$LOG" 2>&1

echo ""
echo "==== clone loop started $(date) ===="

while :; do
  echo ""
  echo "==== batch $(date) ===="

  php artisan storage:clone-disk \
    --source-disk=s3 \
    --target-disk=nas_sftp \
    --batch=5000 \
    --progress=1000 \
    --verify-attempts=5 \
    --verify-sleep-ms=500 \
    --stop-on-failure

  status=$?

  if [ "$status" -ne 0 ]; then
    echo "==== stopped status=$status $(date) ===="
    exit "$status"
  fi

  if tail -n 50 "$LOG" | grep -q "copied: 0 "; then
    echo "==== completed $(date) ===="
    exit 0
  fi
done
