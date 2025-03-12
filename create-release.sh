#!/bin/bash

# This script helps create a new release of the WooCommerce Reward Points plugin
# Usage: ./create-release.sh v1.0.0 "Release description"

# Exit immediately if a command exits with a non-zero status
set -e

# Check if version tag is provided
if [ -z "$1" ]; then
  echo "Error: Version tag not provided"
  echo "Usage: ./create-release.sh v1.0.0 \"Release description\""
  exit 1
fi

VERSION_TAG=$1
RELEASE_MESSAGE=${2:-"Release $VERSION_TAG"}

echo "=== WooCommerce Reward Points Release Script ==="
echo "Creating release: $VERSION_TAG"
echo "Release message: $RELEASE_MESSAGE"
echo ""

# Make sure we have latest changes
echo "Pulling latest changes from remote..."
git pull origin main

# Add all changes
echo "Adding changes to git..."
git add .

# Commit changes
echo "Committing changes..."
git commit -m "Prepare for release $VERSION_TAG" || echo "No changes to commit"

# Create tag
echo "Creating tag $VERSION_TAG..."
git tag -a "$VERSION_TAG" -m "$RELEASE_MESSAGE"

# Push changes
echo "Pushing changes to remote..."
git push origin main

# Push tag
echo "Pushing tag to remote..."
git push origin "$VERSION_TAG"

echo ""
echo "=== Release process started ==="
echo "GitHub Actions will now build and create the release"
echo "Check the status at: https://github.com/qcho1010/rewardplugin/actions"
echo "The release will be available at: https://github.com/qcho1010/rewardplugin/releases"
echo ""
echo "Note: This process may take a few minutes to complete" 