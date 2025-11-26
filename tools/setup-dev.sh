#!/usr/bin/env bash
#
# SATORI Development Setup Script
#

echo "🔧 Starting SATORI development setup..."

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

HOOK_SOURCE="$REPO_ROOT/tools/git-hooks/commit-msg"
HOOK_DEST="$REPO_ROOT/.git/hooks/commit-msg"

VSCODE_SOURCE="$REPO_ROOT/.vscode/commit-message.code-snippets"
VSCODE_DEST_DIR="$REPO_ROOT/.vscode"
VSCODE_DEST="$REPO_ROOT/.vscode/commit-message.code-snippets"

if [ ! -d "$REPO_ROOT/.git" ]; then
  echo "❌ Error: .git directory not found."
  exit 1
fi

echo "📁 Repo root: $REPO_ROOT"

echo "📌 Installing git commit-msg hook..."
mkdir -p "$REPO_ROOT/.git/hooks"
cp "$HOOK_SOURCE" "$HOOK_DEST"
chmod +x "$HOOK_DEST"
echo "✔ Git hook installed."

echo "📌 Installing VS Code commit message snippet..."
mkdir -p "$VSCODE_DEST_DIR"
cp "$VSCODE_SOURCE" "$VSCODE_DEST"
echo "✔ VS Code snippet installed."

echo ""
echo "🎉 Setup complete!"
echo "Restart VS Code to activate snippets."
