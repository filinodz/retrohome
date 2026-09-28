#!/usr/bin/env sh
# RetroHome — démarre le serveur NetPlay (Linux / macOS)
cd "$(dirname "$0")/netplay-server" || exit 1
if ! command -v node >/dev/null 2>&1; then
  echo "[ERREUR] Node.js n'est pas installé : https://nodejs.org" >&2
  exit 1
fi
[ -d node_modules ] || npm install --no-audit --no-fund
exec node server.js
