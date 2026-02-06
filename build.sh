#!/bin/bash

# Build both Watchtower plugins
# Agent must be built first so it can be bundled with Manager

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo "Building Watchtower Agent..."
cd "$SCRIPT_DIR/agent"
wordsmith build --quiet

# Get the built agent ZIP
AGENT_ZIP=$(ls -t build/*.zip | head -1)
VERSION=$(basename "$AGENT_ZIP" .zip | sed 's/watchtower-agent-//')

echo "Building Watchtower Manager..."
cd "$SCRIPT_DIR/manager"

# Copy agent ZIP to manager assets for bundling
cp "$SCRIPT_DIR/agent/$AGENT_ZIP" assets/

wordsmith build --quiet

# Clean up the copied agent ZIP from source
rm -f assets/watchtower-agent-*.zip

echo ""
echo "Build complete!"
echo "  - agent/build/watchtower-agent-${VERSION}.zip"
echo "  - manager/build/watchtower-${VERSION}.zip"
