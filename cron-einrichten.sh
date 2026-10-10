#!/usr/bin/env bash
# Richtet den Cronjob für auto-deploy.sh in diesem Ordner ein (einmalig).
# Aufruf im Ordner app:   bash cron-einrichten.sh
DIR=$(cd "$(dirname "$0")" && pwd)
case "$DIR" in
  *crm-test*) MIN="*/5" ;;      # Test: 0, 5, 10, ...
  *)          MIN="2-59/5" ;;   # Live: 2, 7, 12, ...
esac
LINE="$MIN * * * * bash $DIR/auto-deploy.sh"
if crontab -l 2>/dev/null | grep -qF "$DIR/auto-deploy.sh"; then
  echo "Cronjob ist schon eingerichtet:"
else
  { crontab -l 2>/dev/null; echo "$LINE"; } | crontab - || { echo "FEHLER beim Einrichten."; exit 1; }
  echo "Cronjob eingerichtet:"
fi
crontab -l | grep -F "$DIR/auto-deploy.sh"
