#!/usr/bin/env bash
# Verify every file in a local directory reached the server, and repair the ones
# that did not by walking a ladder of FTP transports.
#
# WHY THIS EXISTS: Beget drops some data connections mid-transfer, deterministic
# per file and independent of size, type and directory. lftp reports only
# "Fatal error: max-retries exceeded"; the server, asked with debug 3, says:
#
#   <--- 426 Failure reading network stream.
#
# og-image.jpg has failed this way since the first deploy and is the reason the
# production mirror still excludes it. So a mirror finishing is not evidence the
# files arrived — that has to be measured, and anything missing retried a
# different way.
#
# Usage:  ftp-repair.sh <local-dir> <remote-dir>
# Env:    FTP_HOST FTP_USER FTP_PASSWORD
# Exit:   0 if everything is present (or was repaired), 1 otherwise.
#
# CAUTION: each rung deletes the remote file before writing it, because a
# half-written remote file defeats every retry. Never point this at a directory
# whose only copy of something lives on the server.
set -uo pipefail

LOCAL_DIR=${1:?local dir required}
REMOTE_DIR=${2:?remote dir required}
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASSWORD:?}"

SUMMARY=${GITHUB_STEP_SUMMARY:-/dev/null}

# Common lftp preamble. Kept in one place so a rung differs ONLY by the setting
# it is testing — otherwise a "working" rung might be working for another reason.
base_settings() {
  echo "set ftp:ssl-allow true; set ftp:ssl-force true;"
  echo "set ssl:verify-certificate no;"
  echo "set net:max-retries 2; set net:timeout 25;"
  echo "set cmd:fail-exit false;"
}

remote_has() {
  local path=$1
  lftp -c "
    $(base_settings)
    open -u '$FTP_USER','$FTP_PASSWORD' '$FTP_HOST';
    cls -1 '$path';
  " </dev/null 2>/dev/null | grep -q .
}

attempt() {
  local mode=$1 settings=$2 rel=$3
  local dir base remote
  dir=$(dirname "$rel"); base=$(basename "$rel")
  if [ "$dir" = "." ]; then remote="$REMOTE_DIR"; else remote="$REMOTE_DIR/$dir"; fi
  lftp -c "
    $(base_settings)
    $settings
    open -u '$FTP_USER','$FTP_PASSWORD' '$FTP_HOST';
    mkdir -p '$remote';
    rm -f '$remote/$base';
    put -O '$remote' '$LOCAL_DIR/$rel';
  " </dev/null >/dev/null 2>&1
  # Presence on the server is the only verdict. lftp's exit code has been wrong
  # on this host since the first deploy and is never consulted.
  if remote_has "$remote/$base"; then
    echo "    $mode: OK"
    echo "- \`$rel\` repaired via **$mode**" >> "$SUMMARY"
    return 0
  fi
  echo "    $mode: failed"
  return 1
}

missing=()
while IFS= read -r rel; do
  dir=$(dirname "$rel"); base=$(basename "$rel")
  if [ "$dir" = "." ]; then remote="$REMOTE_DIR/$base"; else remote="$REMOTE_DIR/$dir/$base"; fi
  remote_has "$remote" || missing+=("$rel")
done < <(cd "$LOCAL_DIR" && find . -type f | sed 's|^\./||' | sort)

if [ ${#missing[@]} -eq 0 ]; then
  echo "all $(cd "$LOCAL_DIR" && find . -type f | wc -l | tr -d ' ') files present in $REMOTE_DIR"
  exit 0
fi

echo "missing from the server:"
printf '  %s\n' "${missing[@]}"
echo "### FTP repair fired" >> "$SUMMARY"

failed=0
for rel in "${missing[@]}"; do
  echo "  repairing $rel ($(wc -c < "$LOCAL_DIR/$rel" | tr -d ' ') bytes)"
  # Rung one deliberately matches what the mirror itself uses. That is what
  # made the useful observation possible: when rung one succeeds, the setting
  # was never the problem — a single put on a fresh connection lands a file the
  # long-lived mirror session lost. Keep it first for that reason.
  attempt "plaintext data channel (the mirror's setting)" "set ftp:ssl-protect-data false;" "$rel" && continue
  attempt "encrypted data channel" "set ftp:ssl-protect-data true;" "$rel" && continue
  attempt "active mode"             "set ftp:passive-mode false;"     "$rel" && continue
  attempt "passive, no EPSV"        "set ftp:prefer-epsv false;"      "$rel" && continue
  echo "    every transport failed"
  echo "- \`$rel\` — **every transport failed**" >> "$SUMMARY"
  failed=1
done

if [ "$failed" -ne 0 ]; then
  echo "FAIL: some files could not be transferred by any transport"
  exit 1
fi
echo "" >> "$SUMMARY"
echo "If a rung other than the first won, put that setting in the mirror step so new files stop needing repair." >> "$SUMMARY"
echo "repaired everything that was missing"
