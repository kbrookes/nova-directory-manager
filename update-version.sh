#!/bin/bash

# Nova Directory Manager - Version Update Script
# This script automates the process of updating version numbers and creating GitHub releases

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if version number is provided
if [ -z "$1" ]; then
    print_error "Usage: ./update-version.sh <new_version> [commit_message]"
    print_error "Example: ./update-version.sh 1.0.4 'Enhanced user experience'"
    exit 1
fi

NEW_VERSION=$1
COMMIT_MESSAGE=${2:-"Release: Version $NEW_VERSION"}

print_status "Starting version update process for version $NEW_VERSION..."

# Check if we're in a git repository
if ! git rev-parse --git-dir > /dev/null 2>&1; then
    print_error "Not in a git repository. Please run this script from the plugin directory."
    exit 1
fi

# Check if we have uncommitted changes
if ! git diff-index --quiet HEAD --; then
    print_warning "You have uncommitted changes. Please commit or stash them first."
    exit 1
fi

# Update version in plugin header
print_status "Updating version in plugin header..."
sed -i '' "s/^ \* Version: [0-9]\+\.[0-9]\+\.[0-9]\+/ * Version: $NEW_VERSION/" nova-directory-manager.php

# Update version constant
print_status "Updating version constant..."
sed -i '' "s/define( 'NDM_VERSION', '[0-9]\+\.[0-9]\+\.[0-9]\+' );/define( 'NDM_VERSION', '$NEW_VERSION' );/" nova-directory-manager.php

# Verify changes
print_status "Verifying version updates..."
if grep -q "Version: $NEW_VERSION" nova-directory-manager.php && grep -q "NDM_VERSION', '$NEW_VERSION'" nova-directory-manager.php; then
    print_success "Version numbers updated successfully"
else
    print_error "Failed to update version numbers"
    exit 1
fi

# Stage changes
print_status "Staging changes..."
git add nova-directory-manager.php

# Commit changes
print_status "Committing changes..."
git commit -m "$COMMIT_MESSAGE"

# Push to main branch
print_status "Pushing to main branch..."
git push origin main

# Create and push tag
print_status "Creating git tag v$NEW_VERSION..."
git tag -a "v$NEW_VERSION" -m "Release version $NEW_VERSION - $COMMIT_MESSAGE"

print_status "Pushing tag to GitHub..."
git push origin "v$NEW_VERSION"

print_success "Version $NEW_VERSION has been successfully released!"
print_status "GitHub release created at: https://github.com/kbrookes/nova-directory-manager/releases/tag/v$NEW_VERSION"
print_status "Git Updater should detect the new version within a few minutes." 