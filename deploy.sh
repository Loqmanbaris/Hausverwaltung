#!/usr/bin/env bash
# ------------------------------------------------------------------
# Neuen Stand von GitHub holen und einspielen.
# Aufruf auf dem Server, im Ordner app:   bash deploy.sh
#
# Ablauf: Prüfen -> Sicherung -> Wartungsmodus -> Code holen ->
#         Datenbank umstellen -> Caches leeren -> Selbsttests -> online
# Schlägt etwas fehl, wird der alte Code automatisch wiederhergestellt.
# Protokoll: storage/logs/deploy-JJJJ-MM-TT.log
# ------------------------------------------------------------------
set -uo pipefail
cd "$(dirname "$0")" || exit 1
mkdir -p storage/logs
exec > >(tee -a "storage/logs/deploy-$(date +%F).log") 2>&1
echo
echo "=================== $(date '+%d.%m.%Y %H:%M:%S') ==================="

# --- PHP für die Kommandozeile finden (php auf IONOS ist CGI) ---
PHP=""
for c in php8.4-cli /usr/bin/php8.4-cli php-cli; do
  if command -v "$c" >/dev/null 2>&1; then PHP="$c"; break; fi
done
if [ -z "$PHP" ]; then echo "FEHLER: php8.4-cli nicht gefunden. Nichts geändert."; exit 1; fi

BRANCH=$(git rev-parse --abbrev-ref HEAD)
echo "Zweig: $BRANCH"

# --- 1. Prüfen ---
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ABBRUCH: Auf dem Server wurden Programmdateien direkt geändert:"
  git status --short --untracked-files=no
  echo "Nichts geändert. Bitte diese Ausgabe an Claude schicken."
  exit 1
fi

echo "Hole Stand von GitHub ..."
if ! git fetch -q origin "$BRANCH"; then echo "FEHLER: GitHub nicht erreichbar. Nichts geändert."; exit 1; fi
OLD=$(git rev-parse HEAD)
NEW=$(git rev-parse "origin/$BRANCH")

if [ "$OLD" = "$NEW" ]; then echo "Schon aktuell – nichts zu tun."; exit 0; fi
if ! git merge-base --is-ancestor "$OLD" "$NEW"; then
  echo "ABBRUCH: Der Stand auf GitHub passt nicht zum Server. Nichts geändert."; exit 1
fi

echo
echo "Neu auf GitHub:"
git log --format='  - %s' "$OLD..$NEW"
CHANGED=$(git diff --name-only "$OLD" "$NEW")

COMPOSER=""
if echo "$CHANGED" | grep -qx 'composer.lock'; then
  COMPOSER=$(command -v composer || true)
  if [ -z "$COMPOSER" ]; then
    echo "ABBRUCH: Neue Programmbibliotheken nötig, aber composer fehlt. Nichts geändert."; exit 1
  fi
fi

# --- 2. Sicherung ---
echo
echo "Sicherung der Datenbank ..."
if ! "$PHP" artisan backup:run --ohne-aufraeumen; then
  echo
  echo "WARNUNG: Die Sicherung hat nicht geklappt (z. B. Sicherungsschlüssel fehlt)."
  read -r -p "Trotzdem weitermachen? (ja/nein) " ANTWORT
  if [ "$ANTWORT" != "ja" ]; then echo "Abgebrochen. Nichts geändert."; exit 1; fi
fi

# --- 3. Einspielen ---
clear_caches() {
  for c in view:clear route:clear config:clear; do "$PHP" artisan "$c" >/dev/null 2>&1; done
}
online() { "$PHP" artisan up >/dev/null 2>&1; }

zurueck() {
  echo
  echo "!!! $1"
  echo "Stelle den alten Programmstand wieder her ..."
  git reset -q --hard "$OLD"
  if [ -n "$COMPOSER" ]; then "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction -q; fi
  clear_caches
  online
  echo "Alter Stand ist wieder aktiv, die Seite ist online."
  echo "Datenbank-Änderungen wurden NICHT zurückgedreht (Sicherung liegt vor)."
  echo "Bitte die ganze Ausgabe an Claude schicken."
  exit 1
}

echo
echo "Wartungsmodus an ..."
"$PHP" artisan down --retry=30 >/dev/null 2>&1
trap 'online' EXIT

echo "Code übernehmen ..."
git merge -q --ff-only "$NEW" || zurueck "Code konnte nicht übernommen werden."

if [ -n "$COMPOSER" ]; then
  echo "Programmbibliotheken aktualisieren ..."
  "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction || zurueck "composer install fehlgeschlagen."
fi

echo "Datenbank umstellen ..."
"$PHP" artisan migrate --force || zurueck "Datenbank-Umstellung fehlgeschlagen."

clear_caches

# --- 4. Selbsttests ---
echo
echo "Selbsttests ..."
FEHLER=""
for t in $("$PHP" artisan list --raw | awk '{print $1}' | grep ':selbsttest$'); do
  if "$PHP" artisan "$t" >/tmp/selbsttest.$$ 2>&1; then
    echo "  OK      $t"
  else
    echo "  FEHLER  $t"; sed 's/^/          /' /tmp/selbsttest.$$
    FEHLER="$FEHLER $t"
  fi
done
rm -f /tmp/selbsttest.$$
[ -n "$FEHLER" ] && zurueck "Selbsttest fehlgeschlagen:$FEHLER"

online
echo
echo "FERTIG: Neuer Stand $(git log -1 --format='%h – %s') ist online."
