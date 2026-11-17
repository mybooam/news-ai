#!/usr/bin/env bash
set -e
cd "$(dirname "$0")/news-ai" 2>/dev/null || true

git init
git add .
git commit -m "Initial commit: News AI v1.1.0"
git branch -M main

if command -v gh >/dev/null; then
  gh auth status >/dev/null 2>&1 || gh auth login
  gh repo create mybooam/news-ai --public --source=. --remote=origin --push
else
  git remote add origin https://github.com/mybooam/news-ai.git
  git push -u origin main
fi
