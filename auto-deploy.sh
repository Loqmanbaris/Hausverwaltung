#!/usr/bin/env bash
# ------------------------------------------------------------------
# Automatisches Einspielen (läuft per Cronjob alle 5 Minuten).
# Gibt es auf GitHub einen neuen Stand für diesen Zweig, wird er mit
# deploy.sh eingespielt. Das Protokoll wird in den Zweig
# "status-<zweig>" auf GitHub hochgeladen, damit Claude es lesen kann.
# Schlägt ein Stand fehl, wird er nicht noch einmal versucht.
# ------------------------------------------------------------------
set -uo pipefail
export PATH=/usr/local/bin:/usr/bin:/bin:$PATH
if [ -z "${AUTO_DIR:-}" ]; then        # als Kopie laufen (Skript kann sich beim Update ändern)
  AUTO_DIR=$(cd "$(dirname "$0")" && pwd) || exit 1
  KOPIE=$(mktemp) && cp "$0" "$KOPIE" || exit 1
  AUTO_DIR="$AUTO_DIR" exec bash "$KOPIE" "$@"
fi
trap 'rm -f "$0"' EXIT
cd "$AUTO_DIR" || exit 1
mkdir -p storage/logs

exec 9>storage/logs/auto-deploy.lock
flock -n 9 || exit 0

BRANCH=$(git rev-parse --abbrev-ref HEAD)
git fetch -q origin "$BRANCH" 2>/dev/null || exit 0
NEW=$(git rev-parse "origin/$BRANCH")
[ "$(git rev-parse HEAD)" = "$NEW" ] && exit 0
[ "$(cat storage/logs/auto-deploy.failed 2>/dev/null)" = "$NEW" ] && exit 0

LOG=$(mktemp)
{
  echo "Server:  $(pwd)"
  echo "Zweig:   $BRANCH"
  echo "Ziel:    $(git log -1 --format='%h %s' "$NEW")"
} > "$LOG"
bash deploy.sh </dev/null >>"$LOG" 2>&1
RC=$?

if [ $RC -eq 0 ]; then
  ERGEBNIS="OK"; rm -f storage/logs/auto-deploy.failed
else
  ERGEBNIS="FEHLER"; echo "$NEW" > storage/logs/auto-deploy.failed
fi
sed -i "1i Ergebnis: $ERGEBNIS ($(date '+%d.%m.%Y %H:%M'))" "$LOG"

# Protokoll als Commit in status-<zweig> hochladen (ändert keine Dateien hier)
export GIT_AUTHOR_NAME="Server" GIT_AUTHOR_EMAIL="info@baris-verwaltung.de"
export GIT_COMMITTER_NAME="Server" GIT_COMMITTER_EMAIL="info@baris-verwaltung.de"
BLOB=$(git hash-object -w "$LOG")
TREE=$(printf '100644 blob %s\tprotokoll.txt\n' "$BLOB" | git mktree)
PARENT=()
if git fetch -q origin "status-$BRANCH" 2>/dev/null; then PARENT=(-p FETCH_HEAD); fi
COMMIT=$(git commit-tree "$TREE" "${PARENT[@]}" -m "$ERGEBNIS: $(git log -1 --format='%s' "$NEW")")
git push -q origin "$COMMIT:refs/heads/status-$BRANCH" 2>/dev/null

if [ $RC -ne 0 ]; then
  echo "Automatisches Update auf $(pwd) ist fehlgeschlagen. Der alte Stand ist aktiv."
  echo
  cat "$LOG"
fi
rm -f "$LOG"
exit 0
