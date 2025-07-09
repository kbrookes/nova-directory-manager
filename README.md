# Nova Directory Manager

A WordPress plugin for managing business directory registrations with Fluent Forms integration, custom user roles, and automatic post creation.

**Author:** Kelsey Brookes  
**Website:** https://novastrategic.co  
**Version:** 1.0.0

## Overview

Nova Directory Manager automates the business directory registration process by:

1. **Creating users** with custom roles when they submit a Fluent Forms registration
2. **Creating business posts** in draft status
3. **Assigning users as post authors** 
4. **Assigning selected categories** to business posts
5. **Handling timing issues** between different WordPress hooks

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Fluent Forms plugin (free or pro)
- Fluent Forms User Registration addon
- Fluent Forms Post Creation addon
- Advanced Custom Fields Pro (for frontend editing)

## Installation

1. Upload the plugin files to `/wp-content/plugins/nova-directory-manager/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure the plugin settings in 'Nova Directory' admin menu

## Configuration

### 1. User Role Settings
- **Role Name:** Internal name for the user role (e.g., `business_owner`)
- **Display Name:** User-friendly name for the role (e.g., `Business Owner`)
- **Capabilities:** Configure what the role can do (read, edit posts, upload files, etc.)

### 2. Fluent Forms Integration
- **Registration Form:** Select the Fluent Form that handles business registration
- **Post Type:** Choose the post type for business listings (e.g., `business`)
- **Category Field:** Specify the form field name that contains category selection

### 3. Form Requirements
Your Fluent Form must have:
- **User Registration enabled** - Creates user accounts
- **Post Creation enabled** - Creates business posts
- **Category field** - Select/dropdown field for business categories

### 4. Frontend Editing Setup
For business owners to edit their posts on the frontend:

1. **ACF Field Group**: Ensure your business post type has an ACF field group (e.g., `group_683a78bc7efb6`)
2. **Shortcodes**: Use the provided shortcodes on your pages:
   - `[ndm_business_list]` - Shows user's businesses with edit links
   - `[ndm_business_edit_form]` - Displays the ACF edit form
3. **Page Setup**: Create a member dashboard page with the business list shortcode

## Key Features & Solutions

### 1. Multi-Layer Role Assignment
**Problem:** Fluent Forms was overriding our role assignments, causing users to remain as 'editor' instead of 'business_owner'.

**Solution:** Implemented three layers of role assignment:
- **Immediate assignment** - When user is registered
- **Delayed assignment** - On `wp_loaded` hook (after Fluent Forms finishes)
- **Cron job assignment** - 30 seconds after registration

```php
// Immediate role assignment
add_action( 'user_register', array( $this, 'handle_user_registration' ), 10, 1 );

// Delayed role assignment
add_action( 'wp_loaded', array( $this, 'delayed_role_assignment' ) );

// Cron job role assignment
add_action( 'ndm_role_assignment_cron', array( $this, 'cron_role_assignment' ) );
```

### 2. Timing Issue Resolution
**Problem:** Posts were created before users, causing assignment failures.

**Solution:** Implemented post tracking and delayed processing:
- Store recent posts in transients
- Process posts when users are registered
- Link users to posts using multiple fallback methods

```php
// Store post for later processing
$recent_posts = get_transient( 'ndm_recent_posts' ) ?: array();
$recent_posts[] = array(
    'post_id' => $post_id,
    'timestamp' => time(),
);
set_transient( 'ndm_recent_posts', $recent_posts, 300 );

// Process posts when user is registered
$this->process_recent_posts( $user_id, $stored_data );
```

### 3. Form Data Parsing
**Problem:** Fluent Forms sends data in URL-encoded format, making category extraction difficult.

**Solution:** Multiple parsing methods for form data:
- Direct field access
- URL-encoded data parsing
- Multiple field name variations

```php
// Parse URL-encoded data
if ( isset( $form_data['data'] ) ) {
    parse_str( $form_data['data'], $parsed_data );
    if ( isset( $parsed_data[ $category_field ] ) ) {
        $category_id = intval( $parsed_data[ $category_field ] );
        wp_set_object_terms( $post_id, $category_id, 'category' );
    }
}
```

### 4. Multiple Hook Integration
**Problem:** Fluent Forms hooks weren't firing consistently.

**Solution:** Hook into multiple Fluent Forms events:
- `fluentform_after_submission_completed`
- `fluentform_submission_inserted`
- `fluentform_after_entry_processed`
- `wp_ajax_fluentform_submit`

### 5. Enhanced Debugging
**Problem:** Difficult to troubleshoot issues without proper logging.

**Solution:** Comprehensive logging system:
- Detailed error logs with `NDM:` prefix
- Form data inspection
- Role assignment verification
- Test functions for debugging

### 6. Frontend Business Editing
**Problem:** Business owners need to edit their posts on the frontend using ACF fields.

**Solution:** Complete frontend editing system:
- ACF Pro frontend forms integration
- Secure permission checking
- Business list dashboard
- AJAX form handling

```php
// Business edit form shortcode
[ndm_business_edit_form post_id="123"]

// Business list shortcode  
[ndm_business_list posts_per_page="10" show_status="true"]
```

**Features:**
- Automatic user permission verification
- ACF field group integration (`group_683a78bc7efb6`)
- Responsive design with custom styling
- Form validation and error handling
- Success/error notifications

## File Structure

```
nova-directory-manager/
├── nova-directory-manager.php    # Main plugin file
├── assets/
│   ├── css/
│   │   ├── admin.css           # Admin styles
│   │   └── frontend.css        # Frontend styles
│   └── js/
│       ├── admin.js            # Admin scripts
│       └── frontend.js         # Frontend scripts
├── languages/                   # Translation files
└── README.md                   # This file
```

## Development Notes

### Critical Issues Solved

1. **Role Assignment Timing**
   - Fluent Forms creates users with default roles
   - Our plugin needs to override these roles
   - Multiple assignment methods ensure success

2. **Post-User Linking**
   - Posts created before users due to hook timing
   - Transient storage bridges the timing gap
   - Fallback methods for data retrieval

3. **Form Data Structure**
   - Fluent Forms data is URL-encoded
   - Multiple parsing methods required
   - Field name variations must be handled

4. **Hook Reliability**
   - Fluent Forms hooks don't always fire
   - Multiple hook integration ensures capture
   - AJAX and standard submission handling

### Key Constants

```php
define( 'NDM_VERSION', '1.0.0' );
define( 'NDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NDM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
```

### Database Options

- `ndm_settings` - Plugin configuration
- `ndm_form_data_*` - Form data transients
- `ndm_user_data_*` - User data transients
- `ndm_recent_posts` - Recent posts for processing
- `ndm_pending_role_assignment` - Users needing role assignment

### Testing Functions

The plugin includes test functions accessible via admin:
- **Test Plugin** - Basic functionality test
- **Test Form Fields** - Form structure inspection

### Shortcodes

The plugin provides two main shortcodes for frontend functionality:

1. **`[ndm_business_list]`** - Displays user's businesses
   - `posts_per_page` - Number of businesses to show (default: 10)
   - `show_status` - Show post status badges (default: true)

2. **`[ndm_business_edit_form]`** - Displays ACF edit form
   - `post_id` - Specific business ID to edit (optional)
   - Automatically finds user's first business if no ID provided

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

### Version 1.0.0
- Initial release
- Fluent Forms integration
- Custom user role management
- Automatic post creation and assignment
- Multi-layer role assignment system
- Comprehensive debugging and testing tools
- Frontend business editing with ACF Pro
- Business dashboard shortcodes
- Responsive design and user experience

## License

GPL v2 or later

## Support

For support, visit https://novastrategic.co 