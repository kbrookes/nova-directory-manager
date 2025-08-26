# Nova Directory Manager v2.0.34 - ACF Field Group Creation Fix

## Release Summary

**Version:** 2.0.34  
**Release Date:** January 2025  
**Type:** Bug Fix Release  
**Priority:** High

## 🎯 What This Release Fixes

This release resolves a critical issue where the Nova Directory Manager plugin was interfering with manual ACF (Advanced Custom Fields) field group creation. Users were experiencing:

- **Duplicate field groups** being created automatically
- **Version conflicts** when trying to save field groups
- **Inability to create new field groups** without interference
- **Confusing admin experience** with multiple versions of the same field group

## 🔧 Key Changes

### Fixed Issues
- **ACF Field Group Creation Conflicts**: Plugin no longer interferes with manual field group creation
- **Duplicate Field Groups**: Automatic creation of duplicates is now prevented
- **Version Conflicts**: Fixed issue where saving created different versions instead of updating
- **Automatic Registration Interference**: Added control to disable automatic registration

### New Features
- **ACF Field Registration Control**: New admin interface to control automatic field group registration
- **Manual Field Group Support**: Plugin now respects existing field groups
- **Registration Toggle**: Option to disable automatic field group registration
- **Safety Checks**: Added checks to prevent interference with manual creation

### Performance Improvements
- **Reduced ACF Conflicts**: Eliminated interference with manual field group creation
- **Better Cache Management**: Improved ACF cache handling
- **Smarter Registration**: Only register field groups if they don't already exist

## 🚀 How to Use the New Features

### For Users Experiencing ACF Issues

1. **Immediate Fix**: Run the provided `fix-acf-field-groups.php` script
2. **Disable Automatic Registration**: Go to Nova Directory > Directory > ACF Field Registration Control
3. **Check the box**: "Disable automatic field group registration"
4. **Save Settings**: Click "Update Registration Settings"

### For New Field Group Creation

1. **Go to Custom Fields > Field Groups** in WordPress admin
2. **Create your field group** (e.g., "people" for "person" post type)
3. **Add your fields** and configure as needed
4. **Save the field group** - no more interference!

## 📋 Technical Details

### New Options
- `ndm_disable_auto_field_registration` - Controls automatic field group registration

### Modified Functions
- `register_field_groups_from_json()` - Now checks for existing field groups
- `force_offer_field_groups()` - Respects disable setting
- `force_acf_reload_on_offer_screens()` - Respects disable setting

### New Functions
- `field_group_exists_in_database()` - Helper function to check for existing field groups

## 🔄 Upgrade Instructions

### Automatic Upgrade
1. Update the plugin through WordPress admin
2. The new features will be available immediately
3. No data migration required

### Manual Upgrade
1. Replace the plugin files with version 2.0.34
2. Clear any caching plugins
3. Test ACF field group creation

## 🧪 Testing Checklist

- [ ] Manual ACF field group creation works without interference
- [ ] Existing field groups are not duplicated
- [ ] Plugin functionality remains intact
- [ ] Admin interface for registration control works
- [ ] Automatic registration can be disabled/enabled
- [ ] No conflicts with existing ACF field groups

## 🐛 Known Issues

None reported in this release.

## 📞 Support

If you experience any issues with this release:

1. **Check the admin interface** for registration control settings
2. **Run the fix script** if you have existing conflicts
3. **Contact support** with specific error messages

## 🔮 Future Plans

- Enhanced ACF integration with better conflict resolution
- Improved field group management interface
- Better integration with ACF Pro features

---

**Release Notes:** This is a critical bug fix that resolves ACF field group creation conflicts. All users experiencing issues with manual field group creation should upgrade to this version.
