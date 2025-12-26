#!/bin/bash

# Quick Fix for Frontend Docker Build
set -e

echo "🔧 Fixing frontend Docker build..."

# Method 1: Update Dockerfile to use npm install instead of npm ci
cp frontend/Dockerfile.fixed frontend/Dockerfile
echo "✅ Updated frontend Dockerfile"

# Method 2: Regenerate package-lock.json to match current state
cd frontend
rm -f package-lock.json
npm install --package-lock-only
cd ..
echo "✅ Regenerated package-lock.json"

echo ""
echo "Now run: docker-compose up -d --build"
