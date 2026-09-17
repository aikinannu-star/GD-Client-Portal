#!/usr/bin/env bash
set -euo pipefail

# Fetch gradle-wrapper.jar and wrapper properties for specified Gradle version
GRADLE_VERSION=${1:-7.5.1}
WRAPPER_DIR="$(dirname "$0")/gradle/wrapper"
mkdir -p "$WRAPPER_DIR"

DIST_URL="https://services.gradle.org/distributions/gradle-${GRADLE_VERSION}-bin.zip"
echo "Downloading Gradle distribution (to compute wrapper)..."
TMPZIP=$(mktemp)
curl -sSL "$DIST_URL" -o "$TMPZIP"

# Extract gradle-wrapper.jar from distribution
echo "Extracting gradle-wrapper.jar..."
unzip -q "$TMPZIP" "gradle-${GRADLE_VERSION}/lib/gradle-wrapper.jar" -d /tmp
if [ -f "/tmp/gradle-${GRADLE_VERSION}/lib/gradle-wrapper.jar" ]; then
  mv "/tmp/gradle-${GRADLE_VERSION}/lib/gradle-wrapper.jar" "$WRAPPER_DIR/gradle-wrapper.jar"
  echo "Saved $WRAPPER_DIR/gradle-wrapper.jar"
else
  echo "Failed to extract gradle-wrapper.jar" >&2
  exit 1
fi
rm -f "$TMPZIP"

echo "Done. You can now run ./gradlew assembleDebug from the android folder."
