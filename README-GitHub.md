# Nova Directory Manager

A WordPress plugin for managing business directory registrations with Fluent Forms integration, custom user roles, and frontend editing capabilities.

## Description

Nova Directory Manager automates the business directory registration process by:

1. **Creating users** with custom roles when they submit a Fluent Forms registration
2. **Creating business posts** in draft status
3. **Assigning users as post authors** 
4. **Assigning selected categories** to business posts
5. **Providing frontend editing** for business owners using ACF Pro
6. **Handling timing issues** between different WordPress hooks

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Fluent Forms plugin (free or pro)
- Fluent Forms User Registration addon
- Fluent Forms Post Creation addon
- Advanced Custom Fields Pro (for frontend editing)

## Installation

### Method 1: Git Updater (Recommended)
1. Install the [Git Updater](https://git-updater.com/) plugin
2. Go to Settings > Git Updater > Plugins
3. Add this repository: `kbrookes/nova-directory-manager`
4. Install and activate the plugin

### Method 2: Manual Installation
1. Download the plugin files
2. Upload to `/wp-content/plugins/nova-directory-manager/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure the plugin settings in 'Nova Directory' admin menu

## Configuration

### 1. User Role Settings
- **Role Name:** Internal name for the user role (e.g., `business_owner`)
- **Display Name:** User-friendly name for the role (e.g., `Business Owner`)
- **Capabilities:** Configure what the role can do (read, edit posts, upload files, etc.)

### 2. Fluent Forms Integration
- **Registration Form:** Select the Fluent Form that handles business registration
- **Post Type:** Choose the post type for business listings (e.g., `business`)
- **Category Field:** Specify the form field name that contains category selection

### 3. Frontend Editing Setup
For business owners to edit their posts on the frontend:

1. **ACF Field Group**: Ensure your business post type has an ACF field group (e.g., `group_683a78bc7efb6`)
2. **Shortcodes**: Use the provided shortcodes on your pages:
   - `[ndm_business_list]` - Shows user's businesses with edit links
   - `[ndm_business_edit_form]` - Displays the ACF edit form
3. **Page Setup**: Create a member dashboard page with the business list shortcode

## Shortcodes

### Business List
```
[ndm_business_list posts_per_page="10" show_status="true"]
```

**Attributes:**
- `posts_per_page` - Number of businesses to show (default: 10)
- `show_status` - Show post status badges (default: true)

### Business Edit Form
```
[ndm_business_edit_form post_id="123"]
```

**Attributes:**
- `post_id` - Specific business ID to edit (optional)
- Automatically finds user's first business if no ID provided

## Example Usage

### Member Dashboard Page
Create a page at `/membership/member-dashboard/` with:
```
[ndm_business_list posts_per_page="5" show_status="true"]
```

### Business Edit Page
Create a page with:
```
[ndm_business_edit_form]
```

### URL Structure
- `/membership/member-dashboard/` - Shows business list
- `/membership/member-dashboard/?business_id=123` - Shows edit form for business 123

## Features

- **Fluent Forms Integration**: Automatically processes form submissions
- **User Role Management**: Creates and assigns the `business_owner` role
- **Post Creation**: Creates business posts from form submissions
- **Category Assignment**: Automatically assigns categories based on form data
- **Frontend Editing**: Business owners can edit their posts using ACF Pro frontend forms
- **Business Dashboard**: Shortcode to display user's businesses with edit links
- **ACF Integration**: Full integration with Advanced Custom Fields Pro
- **Error Logging**: Comprehensive logging for debugging
- **Admin Interface**: Easy configuration through WordPress admin panel
- **Multiple Hook Support**: Uses various Fluent Forms hooks for maximum compatibility
- **Delayed Processing**: Handles timing issues between user registration and post creation
- **Cron Integration**: Background processing for role assignment

## Troubleshooting

### Common Issues

1. **Users not getting correct role**
   - Check if Fluent Forms is assigning a default role
   - Verify the role exists in WordPress
   - Check error logs for assignment failures

2. **Posts not assigned to users**
   - Verify form submission timing
   - Check transient storage
   - Ensure post type matches configuration

3. **Categories not assigned**
   - Verify category field name in settings
   - Check form data structure
   - Ensure categories exist in WordPress

4. **Frontend editing not working**
   - Ensure ACF Pro is installed and activated
   - Verify the ACF field group key matches (`group_683a78bc7efb6`)
   - Check user has `business_owner` role
   - Verify user owns the business they're trying to edit

### Debug Steps

1. Enable WordPress debug logging
2. Submit a test form
3. Check logs for `NDM:` entries
4. Use test functions in admin panel
5. Verify form field structure

## Changelog

### Version 1.0.1
- Added frontend business editing with ACF Pro
- Added business dashboard shortcodes
- Added responsive design and user experience improvements
- Updated plugin headers for Git Updater compatibility
- Fixed back to dashboard button URL

### Version 1.0.0
- Initial release
- Fluent Forms integration
- Custom user role management
- Automatic post creation and assignment
- Multi-layer role assignment system
- Comprehensive debugging and testing tools

## Support

For support, visit https://novastrategic.co

## License

GPL v2 or later 