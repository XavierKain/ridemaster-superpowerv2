#!/bin/bash
# Script pour démarrer un serveur local pour tester le site RideMaster

echo "🚀 Démarrage du serveur local RideMaster..."
echo "📍 URL: http://localhost:8000"
echo "🛑 Pour arrêter le serveur, appuyez sur Ctrl+C"
echo ""

cd "$(dirname "$0")"
npx --yes http-server -p 8000
