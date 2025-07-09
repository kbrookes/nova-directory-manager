# Nova Directory Manager - Example Usage

This guide shows how to implement the frontend editing functionality for business owners.

## 1. Member Dashboard Page

Create a page called "Member Dashboard" and add this content:

```
<h1>Welcome to Your Business Dashboard</h1>

<p>Here you can manage all your business listings. Click "Edit Business" to update your information.</p>

[ndm_business_list posts_per_page="5" show_status="true"]
```

## 2. Business Edit Page

Create a page called "Edit Business" and add this content:

```
[ndm_business_edit_form]
```

Or to edit a specific business:

```
[ndm_business_edit_form post_id="123"]
```

## 3. Page Template Example

You can also create a custom page template for better control:

```php
<?php
/**
 * Template Name: Business Dashboard
 */

get_header(); ?>

<div class="business-dashboard">
    <div class="dashboard-header">
        <h1><?php the_title(); ?></h1>
        <?php if ( is_user_logged_in() && in_array( 'business_owner', wp_get_current_user()->roles ) ) : ?>
            <p>Welcome back, <?php echo esc_html( wp_get_current_user()->display_name ); ?>!</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-content">
        <?php if ( is_user_logged_in() && in_array( 'business_owner', wp_get_current_user()->roles ) ) : ?>
            
            <?php if ( isset( $_GET['business_id'] ) ) : ?>
                <!-- Show edit form -->
                <?php echo do_shortcode( '[ndm_business_edit_form post_id="' . intval( $_GET['business_id'] ) . '"]' ); ?>
            <?php else : ?>
                <!-- Show business list -->
                <?php echo do_shortcode( '[ndm_business_list posts_per_page="10" show_status="true"]' ); ?>
            <?php endif; ?>

        <?php else : ?>
            <div class="login-required">
                <h2>Login Required</h2>
                <p>Please log in to access your business dashboard.</p>
                <?php wp_login_form(); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php get_footer(); ?>
```

## 4. Custom Styling

Add custom CSS to your theme for better integration:

```css
/* Business Dashboard Styles */
.business-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #0073aa;
}

.dashboard-header h1 {
    color: #333;
    margin-bottom: 10px;
}

.login-required {
    text-align: center;
    padding: 40px;
    background: #f9f9f9;
    border-radius: 8px;
}

/* Customize NDM styles */
.ndm-business-list {
    margin-top: 30px;
}

.ndm-business-edit-form {
    margin-top: 20px;
}
```

## 5. URL Structure

The plugin supports these URL patterns:

- `/member-dashboard/` - Shows business list
- `/member-dashboard/?business_id=123` - Shows edit form for business 123
- `/edit-business/` - Shows edit form (finds first business automatically)

## 6. ACF Field Group Setup

Ensure your ACF field group is configured correctly:

1. **Field Group Key**: `group_683a78bc7efb6`
2. **Location Rules**: Post Type is equal to "business"
3. **Fields**: All your business fields (name, email, phone, etc.)

## 7. Security Features

The plugin includes several security measures:

- **User Authentication**: Only logged-in users can access
- **Role Verification**: Only `business_owner` role can edit
- **Ownership Check**: Users can only edit their own businesses
- **Nonce Verification**: CSRF protection on all forms
- **Data Sanitization**: All input is properly sanitized

## 8. Troubleshooting

If the edit form doesn't appear:

1. Check if ACF Pro is active
2. Verify the field group key matches
3. Ensure user has `business_owner` role
4. Check if user owns any businesses
5. Look for error messages in the page

## 9. Customization

You can customize the behavior by modifying the shortcode attributes:

```php
// Show more businesses
[ndm_business_list posts_per_page="20"]

// Hide status badges
[ndm_business_list show_status="false"]

// Edit specific business
[ndm_business_edit_form post_id="456"]
```

## 10. Integration with Existing Themes

The plugin is designed to work with any WordPress theme. The CSS classes are prefixed with `ndm-` to avoid conflicts with theme styles. 