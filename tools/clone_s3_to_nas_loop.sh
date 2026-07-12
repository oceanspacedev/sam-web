#!/usr/bin/env bash
set -u

cd "$(dirname "$0")/.." || exit 1

LOG="storage/logs/storage-clone-s3-nas.log"
RETRY_SLEEP_SECONDS="${STORAGE_CLONE_RETRY_SLEEP_SECONDS:-60}"
mkdir -p "$(dirname "$LOG")"

exec >> "$LOG" 2>&1

echo ""
echo "==== clone loop started $(date) ===="
echo "==== retry_sleep=${RETRY_SLEEP_SECONDS}s ===="

while :; do
  echo ""
  echo "==== batch $(date) ===="
  batch_start_line="$(wc -l < "$LOG" | tr -d ' ')"

  php artisan storage:clone-disk \
    --source-disk=s3 \
    --target-disk=nas_sftp \
    --batch=5000 \
    --progress=1000 \
    --verify-attempts=5 \
    --verify-sleep-ms=500

  status=$?
  summary="$(tail -n +"$((batch_start_line + 1))" "$LOG" | grep "Storage clone selesai." | tail -n 1 || true)"
  target_files="$(tail -n +"$((batch_start_line + 1))" "$LOG" | grep "Target index selesai:" | tail -n 1 | sed -n 's/.*Target index selesai: \([0-9][0-9]*\) files\..*/\1/p')"

  if [ -z "$summary" ]; then
    echo "==== retrying after missing batch summary status=$status sleep=${RETRY_SLEEP_SECONDS}s $(date) ===="
    sleep "$RETRY_SLEEP_SECONDS"
    continue
  fi

  stats="$(printf "%s\n" "$summary" | sed -n 's/.*scanned: \([0-9][0-9]*\), copied: \([0-9][0-9]*\) .* failed: \([0-9][0-9]*\)\..*/\1 \2 \3/p')"
  scanned="$(printf "%s\n" "$stats" | awk '{print $1}')"
  copied="$(printf "%s\n" "$stats" | awk '{print $2}')"
  failed="$(printf "%s\n" "$stats" | awk '{print $3}')"

  if [ -z "$scanned" ] || [ -z "$copied" ] || [ -z "$failed" ]; then
    echo "==== retrying after unparsed batch summary status=$status sleep=${RETRY_SLEEP_SECONDS}s $(date) ===="
    sleep "$RETRY_SLEEP_SECONDS"
    continue
  fi

  if [ "$copied" = "0" ] && [ "$failed" = "0" ] && [ -n "$target_files" ] && [ "$target_files" -ge "$scanned" ]; then
    echo "==== completed scanned=${scanned} target=${target_files} $(date) ===="
    exit 0
  fi

  if [ "$copied" = "0" ] && [ "$failed" = "0" ]; then
    echo "==== retrying count mismatch scanned=${scanned:-unknown} target=${target_files:-unknown} sleep=${RETRY_SLEEP_SECONDS}s $(date) ===="
    sleep "$RETRY_SLEEP_SECONDS"
    continue
  fi

  if [ "$status" -ne 0 ]; then
    if [ "$copied" = "0" ]; then
      echo "==== retrying after no-progress failure status=$status sleep=${RETRY_SLEEP_SECONDS}s $(date) ===="
      sleep "$RETRY_SLEEP_SECONDS"
    else
      echo "==== continuing after partial failure status=$status $(date) ===="
    fi
  fi
done
