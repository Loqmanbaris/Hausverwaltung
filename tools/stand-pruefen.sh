#!/usr/bin/env bash
# ------------------------------------------------------------------
# Prüft NUR LESEND, welchem Stand auf GitHub dieser Server entspricht.
# Aufruf im Ordner app:
#   git fetch -q origin && git show origin/stand-paket-47:tools/stand-pruefen.sh > /tmp/stand-pruefen.sh && bash /tmp/stand-pruefen.sh
# Standard-Zweig: origin/stand-paket-47
# Ändert nichts. Zeigt je Commit, wie viele Dateien abweichen.
# ------------------------------------------------------------------
cd "$(git rev-parse --show-toplevel 2>/dev/null)" || { echo "Bitte im Ordner app ausführen."; exit 1; }
ZIEL="${1:-origin/stand-paket-47}"
if ! git fetch -q origin 2>/dev/null; then echo "GitHub nicht erreichbar."; exit 1; fi
echo "Server: $(pwd)"
echo "Server-Stand (HEAD): $(git log -1 --format='%h %s')"
echo
GEFUNDEN=""
for c in $(git rev-list --reverse "origin/main..$ZIEL"); do
  ab=0
  while IFS=$'\t' read -r meta path; do
    sha=$(echo "$meta" | awk '{print $3}')
    if [ ! -f "$path" ]; then ab=$((ab+1)); continue; fi
    [ "$(git hash-object -- "$path")" = "$sha" ] || ab=$((ab+1))
  done < <(git ls-tree -r "$c")
  zus=$(comm -23 <(git ls-files -c -o --exclude-standard | sort -u | while IFS= read -r f; do [ -e "$f" ] && echo "$f"; done) <(git ls-tree -r --name-only "$c" | sort -u) | wc -l)
  printf '%s  abweichend: %4d  zusätzlich: %3d  %s\n' "$(git log -1 --format='%h' "$c")" "$ab" "$zus" "$(git log -1 --format='%s' "$c")"
  if [ "$ab" -eq 0 ] && [ "$zus" -eq 0 ]; then GEFUNDEN="$c"; fi
done
echo
if [ -n "$GEFUNDEN" ]; then
  echo "Dein Server entspricht genau: $(git log -1 --format='%h %s' "$GEFUNDEN")"
else
  echo "Kein Stand passt genau. Bitte diese Ausgabe an Claude schicken."
fi
