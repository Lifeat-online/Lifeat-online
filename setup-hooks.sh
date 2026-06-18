#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Install the project git hooks
# ---------------------------------------------------------------------------
# Idempotent. Re-runnable. Safe to commit alongside the hooks.
# ---------------------------------------------------------------------------
set -euo pipefail

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

if [[ ! -d .githooks ]]; then
    echo "No .githooks directory found at $REPO_ROOT/.githooks" >&2
    exit 1
fi

git config core.hooksPath .githooks
chmod +x .githooks/pre-push 2>/dev/null || true

echo "git hooks installed. Path: $(git config core.hooksPath)"
echo "Skip with: SKIP_PRE_PUSH=1 git push ..."
