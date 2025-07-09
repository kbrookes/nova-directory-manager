# Nova Directory Manager - Release Process

This document outlines the standardized process for releasing new versions of the Nova Directory Manager plugin.

## Automated Release Process

We've created an automated script that handles the entire release process for you.

### Quick Release (Recommended)

```bash
# Basic release with default commit message
./update-version.sh 1.0.4

# Release with custom commit message
./update-version.sh 1.0.4 "Enhanced user experience and bug fixes"
```

### What the Script Does

1. **Updates Version Numbers**
   - Plugin header: `Version: 1.0.4`
   - Version constant: `define( 'NDM_VERSION', '1.0.4' );`

2. **Git Operations**
   - Stages the changes
   - Commits with your message
   - Pushes to main branch

3. **GitHub Release**
   - Creates git tag `v1.0.4`
   - Pushes tag to GitHub
   - Automatically creates GitHub Release

4. **Verification**
   - Checks that version numbers were updated correctly
   - Provides feedback on each step

## Manual Release Process (If Needed)

If you prefer to do it manually or the script isn't working:

### 1. Update Version Numbers

```bash
# Update plugin header
sed -i '' "s/^ \* Version: [0-9]\+\.[0-9]\+\.[0-9]\+/ * Version: 1.0.4/" nova-directory-manager.php

# Update version constant
sed -i '' "s/define( 'NDM_VERSION', '[0-9]\+\.[0-9]\+\.[0-9]\+' );/define( 'NDM_VERSION', '1.0.4' );/" nova-directory-manager.php
```

### 2. Commit and Push

```bash
git add nova-directory-manager.php
git commit -m "Release: Version 1.0.4 - [description of changes]"
git push origin main
```

### 3. Create GitHub Release

```bash
git tag -a v1.0.4 -m "Release version 1.0.4 - [description]"
git push origin v1.0.4
```

## Version Numbering Convention

- **Major.Minor.Patch** (e.g., 1.0.4, 1.1.0, 2.0.0)
- **Patch** (1.0.4, 1.0.5) - Bug fixes and minor improvements
- **Minor** (1.1.0, 1.2.0) - New features, backward compatible
- **Major** (2.0.0) - Breaking changes

## Git Updater Integration

After a release is created:
1. Git Updater detects the new version within 2-3 minutes
2. WordPress admin shows update notification
3. Users can update with one click
4. No manual release assets needed

## Troubleshooting

### Script Errors

**"Not in a git repository"**
- Make sure you're in the plugin directory
- Run `git init` if needed

**"You have uncommitted changes"**
- Commit or stash your changes first
- Run `git status` to see what's changed

**"Failed to update version numbers"**
- Check that the version format matches exactly
- Verify the plugin file structure

### Git Updater Issues

**Update not showing**
- Wait 2-3 minutes for Git Updater to refresh
- Check GitHub releases page for the new version
- Verify the tag was pushed correctly

**"Automatic update is unavailable"**
- Ensure a GitHub Release was created
- Check that the tag format is correct (`v1.0.4`)

## Best Practices

1. **Always use the script** for consistency
2. **Test changes** before releasing
3. **Write descriptive commit messages**
4. **Update changelog** in README files
5. **Verify the release** on GitHub

## Example Workflow

```bash
# 1. Make your changes
# 2. Test everything works
# 3. Release new version
./update-version.sh 1.0.4 "Fixed admin page styling and improved form validation"

# 4. Wait for Git Updater to detect the update
# 5. Update on WordPress site
```

This standardized process ensures consistent releases and makes Git Updater work reliably! 🚀 