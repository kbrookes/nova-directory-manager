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
    print_error "Example: ./update-version.sh 1.0.5 'Enhanced user experience'"
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

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" nova-directory-manager.php | head -1 | sed 's/.*Version: \([0-9]\+\.[0-9]\+\.[0-9]\+\).*/\1/')
print_status "Current version: $CURRENT_VERSION"

# Check if version is already updated
if [ "$CURRENT_VERSION" = "$NEW_VERSION" ]; then
    print_warning "Version is already set to $NEW_VERSION"
    print_status "Proceeding with git operations..."
else
    # Update version in plugin header
    print_status "Updating version in plugin header..."
    sed -i '' "s/^ *\* Version: [0-9]\+\.[0-9]\+\.[0-9]\+/ * Version: $NEW_VERSION/" nova-directory-manager.php

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
fi

# Push to main branch
print_status "Pushing to main branch..."
git push origin main

# Remove existing tag if it exists
print_status "Checking for existing tag..."
if git tag -l | grep -q "v$NEW_VERSION"; then
    print_warning "Tag v$NEW_VERSION already exists. Removing it..."
    git tag -d "v$NEW_VERSION"
    git push origin ":refs/tags/v$NEW_VERSION" 2>/dev/null || true
fi

# Create and push tag
print_status "Creating git tag v$NEW_VERSION..."
git tag -a "v$NEW_VERSION" -m "Release version $NEW_VERSION - $COMMIT_MESSAGE"

print_status "Pushing tag to GitHub..."
git push origin "v$NEW_VERSION"

# Create GitHub Release using GitHub API
print_status "Creating GitHub Release..."

# Check if GitHub CLI is available
if command -v gh &> /dev/null; then
    print_status "Using GitHub CLI to create release..."
    gh release create "v$NEW_VERSION" --title "Version $NEW_VERSION" --notes "$COMMIT_MESSAGE" --repo kbrookes/nova-directory-manager
    if [ $? -eq 0 ]; then
        print_success "GitHub Release created successfully using GitHub CLI!"
    else
        print_warning "GitHub CLI failed. Please create release manually."
        print_manual_release_instructions
    fi
else
    print_warning "GitHub CLI not found. Please create release manually."
    print_manual_release_instructions
fi

print_success "Version $NEW_VERSION has been successfully released!"
print_status "GitHub release created at: https://github.com/kbrookes/nova-directory-manager/releases/tag/v$NEW_VERSION"
print_status "Git Updater should detect the new version within 2-3 minutes."

# Function to print manual release instructions
print_manual_release_instructions() {
    print_status ""
    print_warning "IMPORTANT: You must now create a GitHub Release manually:"
    print_status "1. Go to: https://github.com/kbrookes/nova-directory-manager/releases"
    print_status "2. Click 'Draft a new release'"
    print_status "3. Select tag 'v$NEW_VERSION'"
    print_status "4. Add title: 'Version $NEW_VERSION'"
    print_status "5. Add description: '$COMMIT_MESSAGE'"
    print_status "6. Click 'Publish release'"
    print_status ""
    print_status "After publishing the release, Git Updater will detect the update within 2-3 minutes."
} 