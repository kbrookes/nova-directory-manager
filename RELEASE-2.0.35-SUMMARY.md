# Nova Directory Manager v2.0.35 - Critical ACF Fields Restoration

## Release Summary

**Version:** 2.0.35  
**Release Date:** January 2025  
**Type:** Critical Bug Fix Release  
**Priority:** Emergency

## 🚨 What This Release Fixes

This is a **CRITICAL** fix that resolves an issue where ACF fields completely disappeared from business and offer post types after the previous update. This affected:

- **Business post type admin interface** - ACF fields were not visible
- **Frontend forms** - Fields were missing from user-facing forms
- **Frontend editor** - Fields were not available for editing
- **Plugin functionality** - Core features were broken

## 🔧 Root Cause

The previous fix (v2.0.34) was too aggressive in preventing duplicate field groups. It was completely skipping the registration of existing field groups with ACF, which made them disappear from the system entirely.

## ✅ What's Fixed

### Critical Fixes
- **ACF Fields Restored**: All business and offer ACF fields are now visible again
- **Field Group Registration**: Plugin field groups are always registered with ACF for functionality
- **Admin Interface**: Fields now appear correctly in WordPress admin
- **Frontend Forms**: All frontend forms and editors work properly
- **Plugin Functionality**: Core plugin features are fully restored

### Improvements
- **Better Logic**: Field groups are always registered with ACF, even if they exist in database
- **Clearer Admin Interface**: Updated descriptions to clarify what the settings control
- **Safeguards**: Added protection against field groups disappearing in future updates

## 🚀 Immediate Action Required

### For Users Affected by Missing Fields

1. **Update to v2.0.35 immediately**
2. **Clear any caching plugins**
3. **Test business and offer post types**

### If Fields Still Missing After Update

1. **Run the emergency restore script** (if provided)
2. **Clear ACF cache** in Custom Fields > Tools
3. **Clear WordPress cache** if using caching plugins

## 📋 Technical Details

### Modified Functions
- `register_field_groups_from_json()` - Now always registers with ACF
- `force_offer_field_groups()` - Always includes field groups
- `register_offer_acf_fields()` - Enhanced to ensure functionality

### Key Changes
- Field groups are always registered with ACF for functionality
- Database save setting only controls database operations, not ACF registration
- Added safeguards to prevent future field group disappearance

## 🔄 Upgrade Instructions

### Automatic Upgrade
1. Update the plugin through WordPress admin
2. Clear any caching plugins
3. Test business and offer post types immediately

### Manual Upgrade
1. Replace the plugin files with version 2.0.35
2. Clear all caches (WordPress, ACF, any caching plugins)
3. Test functionality immediately

## 🧪 Testing Checklist

- [ ] Business post type shows ACF fields in admin
- [ ] Offer post type shows ACF fields in admin
- [ ] Frontend forms display all fields correctly
- [ ] Frontend editor works properly
- [ ] All plugin functionality is restored
- [ ] Manual field group creation still works (if needed)

## 🐛 Known Issues

None in this release. This is a critical fix that resolves the field disappearance issue.

## 📞 Emergency Support

If you experience any issues after this update:

1. **Clear all caches** (WordPress, ACF, plugins)
2. **Test on a business/offer post** to verify fields are visible
3. **Contact support immediately** if fields are still missing

## 🔮 Future Plans

- Enhanced field group management
- Better conflict resolution
- Improved error handling and recovery

---

**Release Notes:** This is a CRITICAL fix that restores ACF fields that disappeared after v2.0.34. All users should update immediately to restore full functionality.

**⚠️ Important:** This release fixes a critical issue that broke core plugin functionality. Update immediately.
