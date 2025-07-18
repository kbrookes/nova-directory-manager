<?php
/**
 * Plugin Name: Nova Directory Manager
 * Plugin URI: https://novastrategic.co
 * Description: Manages business directory registrations with Fluent Forms integration, custom user roles, and automatic post creation with frontend editing capabilities.
 * Version: 2.0.4
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Kelsey Brookes
 * Author URI: https://novastrategic.co
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nova-directory-manager
 * Domain Path: /languages
 * Network: false
 * GitHub Plugin URI: kbrookes/nova-directory-manager
 * GitHub Branch: main
 * Primary Branch: main
 *
 * @package NovaDirectoryManager
 * @since 1.0.0
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'NDM_VERSION', '2.0.4' );
define( 'NDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NDM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
class Nova_Directory_Manager {

	/**
	 * Plugin instance.
	 *
	 * @var Nova_Directory_Manager
	 */
	private static $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Get plugin instance.
	 *
	 * @return Nova_Directory_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
		$this->load_settings();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'admin_action_test_ndm', array( $this, 'test_plugin' ) );
		add_action( 'admin_action_test_ndm_fields', array( $this, 'test_form_fields' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		
		// Check for Fluent Forms after all plugins are loaded
		add_action( 'plugins_loaded', array( $this, 'check_fluent_forms' ), 20 );
		
		// Fluent Forms integration - use multiple hooks for better compatibility
		add_action( 'fluentform_after_submission_completed', array( $this, 'handle_form_submission' ), 10, 3 );
		add_action( 'fluentform_submission_inserted', array( $this, 'handle_form_submission' ), 10, 3 );
		add_action( 'fluentform_after_entry_processed', array( $this, 'handle_form_submission' ), 10, 3 );
		
		// Add a more general hook to catch all form submissions
		add_action( 'wp_ajax_fluentform_submit', array( $this, 'handle_ajax_submission' ), 1 );
		add_action( 'wp_ajax_nopriv_fluentform_submit', array( $this, 'handle_ajax_submission' ), 1 );
		
		// Add a hook to catch all Fluent Forms submissions regardless of form ID
		add_action( 'fluentform_after_submission_completed', array( $this, 'handle_all_form_submissions' ), 10, 3 );
		
		// Add a hook that fires after user registration
		add_action( 'user_register', array( $this, 'handle_user_registration' ), 10, 1 );
		
		// Add a hook that fires after post creation
		add_action( 'wp_insert_post', array( $this, 'handle_post_creation' ), 10, 3 );
		
		// Add a delayed hook to ensure role assignment happens after Fluent Forms
		add_action( 'wp_loaded', array( $this, 'delayed_role_assignment' ) );
		
		// Add a cron job for role assignment
		add_action( 'ndm_role_assignment_cron', array( $this, 'cron_role_assignment' ) );
		
		// Frontend editing functionality
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_shortcode( 'ndm_business_edit_form', array( $this, 'business_edit_form_shortcode' ) );
		add_shortcode( 'ndm_business_list', array( $this, 'business_list_shortcode' ) );
		add_action( 'wp_ajax_ndm_save_business', array( $this, 'ajax_save_business' ) );
		add_action( 'wp_ajax_nopriv_ndm_save_business', array( $this, 'ajax_save_business' ) );
		
		// ACF form processing
		add_action( 'acf/save_post', array( $this, 'handle_acf_form_save' ), 10, 1 );
		
		// Auto-update post title from business name field
		add_action( 'acf/save_post', array( $this, 'update_post_title_from_business_name' ), 20, 1 );
		
		// Activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Check for Fluent Forms after all plugins are loaded.
	 *
	 * @since 1.0.0
	 */
	public function check_fluent_forms() {
		// Test logging
		error_log( 'NDM: Plugin loaded and checking for Fluent Forms' );
		
		// Only show notice on admin pages
		if ( is_admin() && ! $this->is_fluent_forms_active() ) {
			add_action( 'admin_notices', array( $this, 'fluent_forms_missing_notice' ) );
		}
	}

	/**
	 * Initialize plugin.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// Plugin initialization code here
		$this->register_offers_post_type();
		$this->create_advertiser_role();
	}

	/**
	 * Check if Fluent Forms is active using multiple detection methods.
	 *
	 * @return bool
	 */
	public function is_fluent_forms_active() {
		// Method 1: Check if class exists
		if ( class_exists( 'FluentForm' ) ) {
			return true;
		}

		// Method 2: Check if function exists
		if ( function_exists( 'fluentForm' ) ) {
			return true;
		}

		// Method 3: Check if plugin is active
		if ( is_plugin_active( 'fluentform/fluentform.php' ) ) {
			return true;
		}

		// Method 4: Check if pro plugin is active
		if ( is_plugin_active( 'fluentformpro/fluentformpro.php' ) ) {
			return true;
		}

		// Method 5: Check database for forms
		global $wpdb;
		$table_name = $wpdb->prefix . 'fluentform_forms';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
			return true;
		}

		return false;
	}

	/**
	 * Load plugin settings.
	 *
	 * @since 1.0.0
	 */
	private function load_settings() {
		$this->settings = get_option( 'ndm_settings', array() );
	}

	/**
	 * Add admin menu page.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Nova Directory Manager', 'nova-directory-manager' ),
			__( 'Nova Directory', 'nova-directory-manager' ),
			'manage_options',
			'nova-directory-manager',
			array( $this, 'admin_page_callback' ),
			'dashicons-businessman',
			30
		);
	}

	/**
	 * Admin page callback.
	 *
	 * @since 1.0.0
	 */
	public function admin_page_callback() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle form submissions
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'ndm_settings_nonce', 'ndm_nonce' ) ) {
			$this->save_settings();
		}

		// Handle offers admin actions
		$this->handle_offers_admin_actions();

		// Get available forms and post types
		$fluent_forms = $this->get_fluent_forms();
		$post_types = $this->get_post_types();

		// Determine active tab
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'directory';

		// Display admin page
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors( 'ndm_messages' ); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=nova-directory-manager&tab=directory" class="nav-tab<?php if ( $active_tab === 'directory' ) echo ' nav-tab-active'; ?>">Directory</a>
				<a href="?page=nova-directory-manager&tab=offers" class="nav-tab<?php if ( $active_tab === 'offers' ) echo ' nav-tab-active'; ?>">Offers</a>
			</h2>

			<div class="ndm-admin-container">
				<?php if ( $active_tab === 'directory' ) : ?>
					<div class="ndm-admin-main">
						<form method="post" action="">
							<?php wp_nonce_field( 'ndm_settings_nonce', 'ndm_nonce' ); ?>
							<table class="form-table" role="presentation">
								<tbody>
									<tr>
										<th scope="row">
											<label for="user_role_name"><?php _e( 'User Role Name', 'nova-directory-manager' ); ?></label>
										</th>
										<td>
											<input type="text" id="user_role_name" name="user_role_name" value="<?php echo esc_attr( $this->settings['user_role_name'] ?? 'business_owner' ); ?>" class="regular-text" />
											<p class="description"><?php _e( 'Internal name for the user role (e.g., business_owner)', 'nova-directory-manager' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="user_role_display_name"><?php _e( 'User Role Display Name', 'nova-directory-manager' ); ?></label>
										</th>
										<td>
											<input type="text" id="user_role_display_name" name="user_role_display_name" value="<?php echo esc_attr( $this->settings['user_role_display_name'] ?? 'Business Owner' ); ?>" class="regular-text" />
											<p class="description"><?php _e( 'User-friendly name for the role (e.g., Business Owner)', 'nova-directory-manager' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="fluent_form_id"><?php _e( 'Fluent Form', 'nova-directory-manager' ); ?></label>
										</th>
										<td>
											<select id="fluent_form_id" name="fluent_form_id">
												<option value=""><?php _e( 'Select a form...', 'nova-directory-manager' ); ?></option>
												<?php foreach ( $fluent_forms as $form_id => $form_title ) : ?>
													<option value="<?php echo esc_attr( $form_id ); ?>" <?php selected( $this->settings['fluent_form_id'] ?? '', $form_id ); ?>>
														<?php echo esc_html( $form_title ); ?> (ID: <?php echo esc_html( $form_id ); ?>)
													</option>
												<?php endforeach; ?>
											</select>
											<p class="description"><?php _e( 'Select the Fluent Form that handles business registration', 'nova-directory-manager' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="post_type"><?php _e( 'Post Type', 'nova-directory-manager' ); ?></label>
										</th>
										<td>
											<select id="post_type" name="post_type">
												<?php foreach ( $post_types as $post_type => $post_type_label ) : ?>
													<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( $this->settings['post_type'] ?? 'business', $post_type ); ?>>
														<?php echo esc_html( $post_type_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<p class="description"><?php _e( 'Choose the post type for business listings', 'nova-directory-manager' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label for="category_field"><?php _e( 'Category Field', 'nova-directory-manager' ); ?></label>
										</th>
										<td>
											<input type="text" id="category_field" name="category_field" value="<?php echo esc_attr( $this->settings['category_field'] ?? 'business_category' ); ?>" class="regular-text" />
											<p class="description"><?php _e( 'Form field name that contains category selection', 'nova-directory-manager' ); ?></p>
										</td>
									</tr>
								</tbody>
							</table>
							<?php submit_button( __( 'Save Settings', 'nova-directory-manager' ) ); ?>
						</form>
					</div>

					<div class="ndm-admin-sidebar">
						<div class="ndm-admin-box">
							<h3><?php _e( 'Directory Setup', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Configure your business directory settings here. These settings control how business registrations are processed and what user roles are created.', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'User Role Configuration', 'nova-directory-manager' ); ?></h3>
							<p><strong><?php _e( 'Role Name:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Internal identifier used by WordPress (e.g., business_owner)', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Display Name:', 'nova-directory-manager' ); ?></strong> <?php _e( 'User-friendly name shown in the admin (e.g., Business Owner)', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Fluent Forms Integration', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Select the Fluent Form that handles business registration. This form should collect business information and user details.', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Required Fields:', 'nova-directory-manager' ); ?></strong></p>
							<ul>
								<li><?php _e( 'Business name', 'nova-directory-manager' ); ?></li>
								<li><?php _e( 'User email', 'nova-directory-manager' ); ?></li>
								<li><?php _e( 'Category selection', 'nova-directory-manager' ); ?></li>
							</ul>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Shortcodes', 'nova-directory-manager' ); ?></h3>
							<p><strong><?php _e( 'Business Edit Form:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_business_edit_form]</code>
							<p><strong><?php _e( 'Business List:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_business_list]</code>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Need Help?', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Check the documentation or contact support if you need assistance with the directory setup.', 'nova-directory-manager' ); ?></p>
						</div>
					</div>
				<?php elseif ( $active_tab === 'offers' ) : ?>
					<div class="ndm-admin-main">
						<h2><?php _e( 'Offers Management', 'nova-directory-manager' ); ?></h2>
						
						<!-- Currency & General Settings -->
						<div class="ndm-admin-section">
							<h3><?php _e( 'Currency & General Settings', 'nova-directory-manager' ); ?></h3>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_offers_settings_nonce', 'ndm_offers_nonce' ); ?>
								<input type="hidden" name="action" value="save_general_settings" />
								
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row">
												<label for="currency"><?php _e( 'Currency', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<select id="currency" name="currency">
													<option value="AUD" <?php selected( $this->get_offer_setting( 'currency', 'AUD' ), 'AUD' ); ?>><?php _e( 'Australian Dollar (AUD)', 'nova-directory-manager' ); ?></option>
													<option value="USD" <?php selected( $this->get_offer_setting( 'currency', 'AUD' ), 'USD' ); ?>><?php _e( 'US Dollar (USD)', 'nova-directory-manager' ); ?></option>
													<option value="EUR" <?php selected( $this->get_offer_setting( 'currency', 'AUD' ), 'EUR' ); ?>><?php _e( 'Euro (EUR)', 'nova-directory-manager' ); ?></option>
													<option value="GBP" <?php selected( $this->get_offer_setting( 'currency', 'AUD' ), 'GBP' ); ?>><?php _e( 'British Pound (GBP)', 'nova-directory-manager' ); ?></option>
													<option value="CAD" <?php selected( $this->get_offer_setting( 'currency', 'AUD' ), 'CAD' ); ?>><?php _e( 'Canadian Dollar (CAD)', 'nova-directory-manager' ); ?></option>
												</select>
												<p class="description"><?php _e( 'Select the currency for offer pricing', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="default_duration_days"><?php _e( 'Default Duration (Days)', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<input type="number" id="default_duration_days" name="default_duration_days" value="<?php echo esc_attr( $this->get_offer_setting( 'default_duration_days', 30 ) ); ?>" class="regular-text" min="1" />
												<p class="description"><?php _e( 'Default number of days offers are active', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
									</tbody>
								</table>
								<?php submit_button( __( 'Save General Settings', 'nova-directory-manager' ) ); ?>
							</form>
						</div>

						<!-- Advertiser Pricing -->
						<div class="ndm-admin-section">
							<h3><?php _e( 'Advertiser Pricing', 'nova-directory-manager' ); ?></h3>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_offers_settings_nonce', 'ndm_offers_nonce' ); ?>
								<input type="hidden" name="action" value="save_advertiser_pricing" />
								
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row">
												<label for="advertiser_base_price"><?php _e( 'Base Price per 30 Days', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<input type="number" id="advertiser_base_price" name="advertiser_base_price" value="<?php echo esc_attr( $this->get_offer_setting( 'advertiser_base_price', 49.99 ) ); ?>" class="regular-text" step="0.01" min="0" />
												<p class="description"><?php _e( 'Base price for advertisers per 30-day period', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="advertiser_volume_discounts"><?php _e( 'Volume Discounts', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<textarea id="advertiser_volume_discounts" name="advertiser_volume_discounts" rows="4" class="large-text"><?php echo esc_textarea( $this->get_offer_setting( 'advertiser_volume_discounts', "3:0.10\n5:0.15\n10:0.20" ) ); ?></textarea>
												<p class="description"><?php _e( 'Format: quantity:discount_percentage (one per line). Example: 3:0.10 means 10% off for 3+ offers', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
									</tbody>
								</table>
								<?php submit_button( __( 'Save Advertiser Pricing', 'nova-directory-manager' ) ); ?>
							</form>
						</div>

						<!-- Business Owner Pricing -->
						<div class="ndm-admin-section">
							<h3><?php _e( 'Business Owner Pricing', 'nova-directory-manager' ); ?></h3>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_offers_settings_nonce', 'ndm_offers_nonce' ); ?>
								<input type="hidden" name="action" value="save_business_owner_pricing" />
								
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row">
												<label for="business_owner_included_offers"><?php _e( 'Included Offers', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<input type="number" id="business_owner_included_offers" name="business_owner_included_offers" value="<?php echo esc_attr( $this->get_offer_setting( 'business_owner_included_offers', 2 ) ); ?>" class="regular-text" min="0" />
												<p class="description"><?php _e( 'Number of free offers included with business registration', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="business_owner_max_duration"><?php _e( 'Maximum Duration (Days)', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<input type="number" id="business_owner_max_duration" name="business_owner_max_duration" value="<?php echo esc_attr( $this->get_offer_setting( 'business_owner_max_duration', 60 ) ); ?>" class="regular-text" min="1" />
												<p class="description"><?php _e( 'Maximum duration allowed for business owner offers', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="business_owner_additional_price"><?php _e( 'Additional Offer Price (per 30 days)', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<input type="number" id="business_owner_additional_price" name="business_owner_additional_price" value="<?php echo esc_attr( $this->get_offer_setting( 'business_owner_additional_price', 29.99 ) ); ?>" class="regular-text" step="0.01" min="0" />
												<p class="description"><?php _e( 'Price for additional offers after included offers are used', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="business_owner_volume_discounts"><?php _e( 'Volume Discounts', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<textarea id="business_owner_volume_discounts" name="business_owner_volume_discounts" rows="4" class="large-text"><?php echo esc_textarea( $this->get_offer_setting( 'business_owner_volume_discounts', "3:0.15\n5:0.20\n10:0.25" ) ); ?></textarea>
												<p class="description"><?php _e( 'Format: quantity:discount_percentage (one per line). Separate from included offers.', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
									</tbody>
								</table>
								<?php submit_button( __( 'Save Business Owner Pricing', 'nova-directory-manager' ) ); ?>
							</form>
						</div>

						<!-- Approval Workflow -->
						<div class="ndm-admin-section">
							<h3><?php _e( 'Approval Workflow', 'nova-directory-manager' ); ?></h3>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_offers_settings_nonce', 'ndm_offers_nonce' ); ?>
								<input type="hidden" name="action" value="save_approval_settings" />
								
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row">
												<label for="require_approval"><?php _e( 'Require Admin Approval', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<label>
													<input type="checkbox" id="require_approval" name="require_approval" value="1" <?php checked( $this->get_offer_setting( 'require_approval', true ) ); ?> />
													<?php _e( 'All offers must be approved by an administrator before going live', 'nova-directory-manager' ); ?>
												</label>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="auto_expire"><?php _e( 'Auto-Expire Offers', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<label>
													<input type="checkbox" id="auto_expire" name="auto_expire" value="1" <?php checked( $this->get_offer_setting( 'auto_expire', true ) ); ?> />
													<?php _e( 'Automatically expire offers after their duration period', 'nova-directory-manager' ); ?>
												</label>
											</td>
										</tr>
										<tr>
											<th scope="row">
												<label for="expiry_notification_days"><?php _e( 'Expiry Notification (Days)', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<input type="number" id="expiry_notification_days" name="expiry_notification_days" value="<?php echo esc_attr( $this->get_offer_setting( 'expiry_notification_days', 3 ) ); ?>" class="regular-text" min="0" />
												<p class="description"><?php _e( 'Send notification to business owners this many days before offer expires', 'nova-directory-manager' ); ?></p>
											</td>
										</tr>
									</tbody>
								</table>
								<?php submit_button( __( 'Save Approval Settings', 'nova-directory-manager' ) ); ?>
							</form>
						</div>

						<!-- Bulk Actions -->
						<div class="ndm-admin-section">
							<h3><?php _e( 'Bulk Actions', 'nova-directory-manager' ); ?></h3>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_offers_bulk_nonce', 'ndm_offers_bulk_nonce' ); ?>
								<input type="hidden" name="action" value="bulk_offers_action" />
								
								<table class="form-table" role="presentation">
									<tbody>
										<tr>
											<th scope="row">
												<label for="bulk_action"><?php _e( 'Action', 'nova-directory-manager' ); ?></label>
											</th>
											<td>
												<select id="bulk_action" name="bulk_action">
													<option value=""><?php _e( 'Select an action...', 'nova-directory-manager' ); ?></option>
													<option value="approve_all"><?php _e( 'Approve All Pending Offers', 'nova-directory-manager' ); ?></option>
													<option value="expire_all"><?php _e( 'Expire All Active Offers', 'nova-directory-manager' ); ?></option>
													<option value="extend_all"><?php _e( 'Extend All Offers by 30 Days', 'nova-directory-manager' ); ?></option>
													<option value="delete_expired"><?php _e( 'Delete All Expired Offers', 'nova-directory-manager' ); ?></option>
												</select>
											</td>
										</tr>
									</tbody>
								</table>
								<?php submit_button( __( 'Execute Bulk Action', 'nova-directory-manager' ), 'secondary' ); ?>
							</form>
						</div>

						<!-- Statistics -->
						<div class="ndm-admin-section">
							<h3><?php _e( 'Offers Statistics', 'nova-directory-manager' ); ?></h3>
							<?php $stats = $this->get_offers_statistics(); ?>
							<table class="widefat">
								<tbody>
									<tr>
										<td><strong><?php _e( 'Total Offers:', 'nova-directory-manager' ); ?></strong></td>
										<td><?php echo esc_html( $stats['total'] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php _e( 'Active Offers:', 'nova-directory-manager' ); ?></strong></td>
										<td><?php echo esc_html( $stats['active'] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php _e( 'Pending Approval:', 'nova-directory-manager' ); ?></strong></td>
										<td><?php echo esc_html( $stats['pending'] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php _e( 'Expired Offers:', 'nova-directory-manager' ); ?></strong></td>
										<td><?php echo esc_html( $stats['expired'] ); ?></td>
									</tr>
									<tr>
										<td><strong><?php _e( 'Revenue Generated:', 'nova-directory-manager' ); ?></strong></td>
										<td><?php echo esc_html( $this->get_currency_symbol() . number_format( $stats['revenue'], 2 ) ); ?></td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>

					<div class="ndm-admin-sidebar">
						<div class="ndm-admin-box">
							<h3><?php _e( 'Offers Management', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Configure pricing, approval workflows, and manage offers from this central location. Business owners and advertisers will create offers through the frontend.', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Currency & Pricing', 'nova-directory-manager' ); ?></h3>
							<p><strong><?php _e( 'Currency:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Set the currency for all offer pricing (default: AUD)', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Default Duration:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Standard offer duration in days (default: 30)', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'User Type Pricing', 'nova-directory-manager' ); ?></h3>
							<p><strong><?php _e( 'Advertisers:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Pay per offer with volume discounts', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Business Owners:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Get included offers with registration, then pay for additional offers', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Approval Workflow', 'nova-directory-manager' ); ?></h3>
							<p><strong><?php _e( 'Admin Approval:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Require admin approval before offers go live', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Auto-Expire:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Automatically expire offers after their duration period', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Notifications:', 'nova-directory-manager' ); ?></strong> <?php _e( 'Send expiry notifications to business owners', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Bulk Actions', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Perform mass operations on offers:', 'nova-directory-manager' ); ?></p>
							<ul>
								<li><?php _e( 'Approve all pending offers', 'nova-directory-manager' ); ?></li>
								<li><?php _e( 'Expire all active offers', 'nova-directory-manager' ); ?></li>
								<li><?php _e( 'Extend all offers by 30 days', 'nova-directory-manager' ); ?></li>
								<li><?php _e( 'Delete all expired offers', 'nova-directory-manager' ); ?></li>
							</ul>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Statistics', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Monitor your offers performance with real-time statistics including total offers, active offers, pending approvals, and revenue generated.', 'nova-directory-manager' ); ?></p>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'Volume Discounts', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'Format: quantity:discount_percentage', 'nova-directory-manager' ); ?></p>
							<p><strong><?php _e( 'Example:', 'nova-directory-manager' ); ?></strong></p>
							<code>3:0.10<br>5:0.15<br>10:0.20</code>
							<p><?php _e( 'This means 10% off for 3+ offers, 15% off for 5+ offers, etc.', 'nova-directory-manager' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save plugin settings.
	 *
	 * @since 1.0.0
	 */
	private function save_settings() {
		$settings = array(
			'user_role_name' => sanitize_text_field( $_POST['user_role_name'] ?? 'business_owner' ),
			'user_role_display_name' => sanitize_text_field( $_POST['user_role_display_name'] ?? 'Business Owner' ),
			'fluent_form_id' => intval( $_POST['fluent_form_id'] ?? 0 ),
			'post_type' => sanitize_text_field( $_POST['post_type'] ?? 'business' ),
			'category_field' => sanitize_text_field( $_POST['category_field'] ?? 'business_category' ),
			'user_role_capabilities' => array(
				'read' => true,
				'edit_posts' => true,
				'delete_posts' => false,
				'publish_posts' => false,
				'upload_files' => true,
				'edit_published_posts' => true,
				'delete_published_posts' => false,
			),
		);

		update_option( 'ndm_settings', $settings );
		$this->settings = $settings;

		// Create or update the user role
		$this->create_user_role();

		add_settings_error(
			'ndm_messages',
			'ndm_message',
			__( 'Settings saved successfully! User role has been created/updated.', 'nova-directory-manager' ),
			'success'
		);
	}

	/**
	 * Create or update the custom user role.
	 *
	 * @since 1.0.0
	 */
	private function create_user_role() {
		$role_name = $this->settings['user_role_name'];
		$display_name = $this->settings['user_role_display_name'];
		$capabilities = $this->settings['user_role_capabilities'];

		// Remove existing role if it exists
		remove_role( $role_name );

		// Create new role
		$result = add_role( $role_name, $display_name, $capabilities );

		if ( null === $result ) {
			add_settings_error(
				'ndm_messages',
				'ndm_error',
				__( 'Error creating user role. Please try again.', 'nova-directory-manager' ),
				'error'
			);
		}
	}

	/**
	 * Get available Fluent Forms.
	 *
	 * @return array
	 */
	private function get_fluent_forms() {
		$forms = array();
		
		// Check if Fluent Forms is active
		if ( ! $this->is_fluent_forms_active() ) {
			return $forms;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'fluentform_forms';
		
		// Check if table exists
		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			return $forms;
		}

		// Get forms from database
		$forms_data = $wpdb->get_results( "SELECT id, title FROM {$table_name} WHERE status = 'published' ORDER BY title ASC" );
		
		if ( $forms_data ) {
			foreach ( $forms_data as $form ) {
				$forms[ $form->id ] = $form->title;
			}
		}

		return $forms;
	}

	/**
	 * Get available post types.
	 *
	 * @return array
	 */
	private function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$options = array();

		foreach ( $post_types as $post_type ) {
			$options[ $post_type->name ] = $post_type->labels->singular_name;
		}

		return $options;
	}

	/**
	 * Handle Fluent Forms submission.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $form_data Form data.
	 * @param array $entry Entry data.
	 */
	public function handle_form_submission( $form_id, $form_data, $entry ) {
		// Check if this is the configured form
		if ( $form_id != $this->settings['fluent_form_id'] ) {
			error_log( "NDM: Form ID mismatch - expected {$this->settings['fluent_form_id']}, got {$form_id}" );
			return;
		}

		error_log( 'NDM: Form submission detected for form ' . $form_id );
		error_log( 'NDM: Form data: ' . print_r( $form_data, true ) );
		error_log( 'NDM: Entry data: ' . print_r( $entry, true ) );

		// Get the user ID from the entry
		$user_id = $this->get_user_id_from_entry( $entry, $form_data );
		
		if ( ! $user_id ) {
			error_log( 'NDM: No user ID found in form entry' );
			return;
		}

		error_log( 'NDM: Found user ID: ' . $user_id );

		// Get the post ID from the entry
		$post_id = $this->get_post_id_from_entry( $entry, $form_data );
		
		if ( ! $post_id ) {
			error_log( 'NDM: No post ID found in form entry' );
			return;
		}

		error_log( 'NDM: Found post ID: ' . $post_id );

		// Assign user role
		$this->assign_user_role( $user_id );

		// Assign user as post author
		$this->assign_user_to_post( $post_id, $user_id );

		// Assign category to post
		$this->assign_category_to_post( $post_id, $form_data );

		error_log( 'NDM: Form submission processing completed' );
	}

	/**
	 * Handle all Fluent Forms submissions to catch any form ID.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $form_data Form data.
	 * @param array $entry Entry data.
	 */
	public function handle_all_form_submissions( $form_id, $form_data, $entry ) {
		error_log( 'NDM: All form submissions hook - Form ID: ' . $form_id . ', Expected: ' . $this->settings['fluent_form_id'] );
		
		// Check if this is the configured form
		if ( $form_id == $this->settings['fluent_form_id'] ) {
			error_log( 'NDM: Processing configured form submission' );
			$this->handle_form_submission( $form_id, $form_data, $entry );
		} else {
			error_log( 'NDM: Form ID ' . $form_id . ' does not match configured form ID ' . $this->settings['fluent_form_id'] );
			
			// Store data anyway in case we need to process it later
			$this->store_form_data( $form_data );
		}
	}

	/**
	 * Handle AJAX form submission.
	 */
	public function handle_ajax_submission() {
		error_log( 'NDM: AJAX submission detected' );
		
		// Get form ID from POST data
		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		
		error_log( 'NDM: AJAX form ID: ' . $form_id . ', configured form ID: ' . $this->settings['fluent_form_id'] );
		
		if ( $form_id == $this->settings['fluent_form_id'] ) {
			error_log( 'NDM: Matching form ID detected: ' . $form_id );
			
			// Store form data for later processing
			$this->store_form_data( $_POST );
		} else {
			error_log( 'NDM: Form ID mismatch in AJAX - expected ' . $this->settings['fluent_form_id'] . ', got ' . $form_id );
		}
	}

	/**
	 * Handle user registration.
	 *
	 * @param int $user_id User ID.
	 */
	public function handle_user_registration( $user_id ) {
		error_log( 'NDM: User registration detected for user: ' . $user_id );
		
		// Get user email to find stored data
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			error_log( 'NDM: Could not get user data for user: ' . $user_id );
			return;
		}

		// Check if this is from our form - try multiple keys
		$stored_data = get_transient( 'ndm_form_data_' . $user_id );
		if ( ! $stored_data ) {
			$stored_data = get_transient( 'ndm_form_data_' . $user->user_email );
		}
		if ( ! $stored_data ) {
			// Try to find any recent stored data
			global $wpdb;
			$transients = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_ndm_form_data_%' ORDER BY option_id DESC LIMIT 5" );
			
			foreach ( $transients as $transient ) {
				$stored_data = maybe_unserialize( $transient->option_value );
				if ( $stored_data && isset( $stored_data['email'] ) && $stored_data['email'] === $user->user_email ) {
					error_log( 'NDM: Found stored data via database search for user: ' . $user_id );
					break;
				}
			}
		}

		if ( $stored_data ) {
			error_log( 'NDM: Found stored form data for user: ' . $user_id );
			error_log( 'NDM: Stored data: ' . print_r( $stored_data, true ) );
			
			// Assign user role
			$this->assign_user_role( $user_id );
			
			// Add user to pending list for delayed role assignment
			$pending_users = get_transient( 'ndm_pending_role_assignment' ) ?: array();
			if ( ! in_array( $user_id, $pending_users ) ) {
				$pending_users[] = $user_id;
				set_transient( 'ndm_pending_role_assignment', $pending_users, 300 );
				error_log( 'NDM: Added user ' . $user_id . ' to pending role assignment list' );
			}
			
					// Schedule a cron job to ensure role assignment happens
		if ( ! wp_next_scheduled( 'ndm_role_assignment_cron' ) ) {
			wp_schedule_single_event( time() + 30, 'ndm_role_assignment_cron' );
			error_log( 'NDM: Scheduled cron job for role assignment in 30 seconds' );
		}
			
			// Store user ID in the data for later post processing
			$stored_data['user_id'] = $user_id;
			set_transient( 'ndm_user_data_' . $user_id, $stored_data, 300 );
			
			// Process any recent posts that need a user assigned
			$this->process_recent_posts( $user_id, $stored_data );
			
			// Clear the original transient
			delete_transient( 'ndm_form_data_' . $user_id );
			delete_transient( 'ndm_form_data_' . $user->user_email );
		} else {
			error_log( 'NDM: No stored form data found for user: ' . $user_id );
		}
	}

	/**
	 * Handle post creation.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $post Post object.
	 * @param bool  $update Whether this is an update.
	 */
	public function handle_post_creation( $post_id, $post, $update ) {
		// Only process new posts of the configured post type
		if ( $update || $post->post_type !== $this->settings['post_type'] ) {
			return;
		}

		error_log( 'NDM: Post creation detected for post: ' . $post_id . ' of type: ' . $post->post_type );
		
		// Store the post ID for later processing when user is registered
		$recent_posts = get_transient( 'ndm_recent_posts' ) ?: array();
		$recent_posts[] = array(
			'post_id' => $post_id,
			'timestamp' => time(),
		);
		
		// Keep only the last 5 posts
		if ( count( $recent_posts ) > 5 ) {
			$recent_posts = array_slice( $recent_posts, -5 );
		}
		
		set_transient( 'ndm_recent_posts', $recent_posts, 300 );
		error_log( 'NDM: Stored post ' . $post_id . ' for later processing' );
		
		// Try to find the user who created this post
		$user_id = $this->find_user_for_post( $post_id );
		
		if ( $user_id ) {
			error_log( 'NDM: Found user for post: ' . $user_id );
			
			// Assign user as post author
			$this->assign_user_to_post( $post_id, $user_id );
			
			// Try to assign category
			$this->assign_category_from_stored_data( $post_id, $user_id );
		} else {
			error_log( 'NDM: No user found for post: ' . $post_id . '. Will process when user is registered.' );
		}
	}

	/**
	 * Store form data for later processing.
	 *
	 * @param array $form_data Form data.
	 */
	private function store_form_data( $form_data ) {
		// Create a unique key based on email or timestamp
		$key = isset( $form_data['email'] ) ? sanitize_email( $form_data['email'] ) : time();
		$transient_key = 'ndm_form_data_' . $key;
		
		set_transient( $transient_key, $form_data, 300 ); // Store for 5 minutes
		
		error_log( 'NDM: Stored form data with key: ' . $transient_key );
	}

	/**
	 * Find user for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int|false User ID or false if not found.
	 */
	private function find_user_for_post( $post_id ) {
		// Method 1: Check post author
		$post = get_post( $post_id );
		if ( $post && $post->post_author ) {
			return $post->post_author;
		}

		// Method 2: Look for recent users
		$users = get_users( array(
			'number' => 5,
			'orderby' => 'ID',
			'order' => 'DESC',
		) );

		foreach ( $users as $user ) {
			$stored_data = get_transient( 'ndm_form_data_' . $user->user_email );
			if ( $stored_data ) {
				return $user->ID;
			}
		}

		return false;
	}

	/**
	 * Assign category from stored form data.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 */
	private function assign_category_from_stored_data( $post_id, $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			error_log( 'NDM: Could not get user data for category assignment: ' . $user_id );
			return;
		}

		// Try multiple sources for stored data
		$stored_data = get_transient( 'ndm_user_data_' . $user_id );
		if ( ! $stored_data ) {
			$stored_data = get_transient( 'ndm_form_data_' . $user->user_email );
		}
		if ( ! $stored_data ) {
			$stored_data = get_transient( 'ndm_form_data_' . $user_id );
		}

		if ( ! $stored_data ) {
			error_log( 'NDM: No stored data found for category assignment for user: ' . $user_id );
			return;
		}

		error_log( 'NDM: Found stored data for category assignment: ' . print_r( $stored_data, true ) );
		
		// Use the same category assignment logic
		$this->assign_category_to_post( $post_id, $stored_data );
		
		// Clear the transients
		delete_transient( 'ndm_user_data_' . $user_id );
		delete_transient( 'ndm_form_data_' . $user->user_email );
		delete_transient( 'ndm_form_data_' . $user_id );
	}

	/**
	 * Delayed role assignment to ensure it happens after Fluent Forms.
	 */
	public function delayed_role_assignment() {
		// Only run this once per request
		if ( did_action( 'wp_loaded' ) > 1 ) {
			return;
		}

		// Check for users that need role assignment
		$pending_users = get_transient( 'ndm_pending_role_assignment' );
		if ( ! $pending_users ) {
			return;
		}

		error_log( 'NDM: Running delayed role assignment for users: ' . print_r( $pending_users, true ) );

		foreach ( $pending_users as $user_id ) {
			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				continue;
			}

			$role_name = $this->settings['user_role_name'] ?? 'business_owner';
			
			// Check if user already has the correct role
			if ( in_array( $role_name, $user->roles ) ) {
				error_log( "NDM: User {$user_id} already has correct role {$role_name}" );
				continue;
			}

			error_log( "NDM: Delayed role assignment - User {$user_id} current roles: " . print_r( $user->roles, true ) );
			
			// Check if user has administrative capabilities - if so, don't change their role
			if ( $user->has_cap( 'manage_options' ) || $user->has_cap( 'administrator' ) ) {
				error_log( "NDM: Admin user {$user_id} would be changed to {$role_name} in delayed assignment - preserving admin role" );
				continue;
			}
			
			// Force role assignment
			$user->set_role( $role_name );
			
			// Verify the assignment
			$user_after = get_user_by( 'ID', $user_id );
			error_log( "NDM: Delayed role assignment - User {$user_id} roles after: " . print_r( $user_after->roles, true ) );
		}

		// Clear the pending list
		delete_transient( 'ndm_pending_role_assignment' );
	}

	/**
	 * Cron job for role assignment.
	 */
	public function cron_role_assignment() {
		error_log( 'NDM: Running cron role assignment' );
		
		// Get recent users (last 10 minutes)
		$users = get_users( array(
			'number' => 10,
			'orderby' => 'ID',
			'order' => 'DESC',
			'date_query' => array(
				array(
					'after' => '10 minutes ago',
				),
			),
		) );

		$role_name = $this->settings['user_role_name'] ?? 'business_owner';
		
		foreach ( $users as $user ) {
			// Check if user has the correct role
			if ( in_array( $role_name, $user->roles ) ) {
				continue;
			}

			// Check if this user was created by our form (by checking for stored data)
			$stored_data = get_transient( 'ndm_user_data_' . $user->ID );
			if ( ! $stored_data ) {
				continue;
			}

			error_log( "NDM: Cron role assignment - User {$user->ID} current roles: " . print_r( $user->roles, true ) );
			
			// Check if user has administrative capabilities - if so, don't change their role
			if ( $user->has_cap( 'manage_options' ) || $user->has_cap( 'administrator' ) ) {
				error_log( "NDM: Admin user {$user->ID} would be changed to {$role_name} in cron assignment - preserving admin role" );
				continue;
			}
			
			// Force role assignment
			$user->set_role( $role_name );
			
			// Verify the assignment
			$user_after = get_user_by( 'ID', $user->ID );
			error_log( "NDM: Cron role assignment - User {$user->ID} roles after: " . print_r( $user_after->roles, true ) );
		}
	}

	/**
	 * Process recent posts that need a user assigned.
	 *
	 * @param int   $user_id User ID.
	 * @param array $stored_data Stored form data.
	 */
	private function process_recent_posts( $user_id, $stored_data ) {
		$recent_posts = get_transient( 'ndm_recent_posts' );
		if ( ! $recent_posts ) {
			error_log( 'NDM: No recent posts found for user: ' . $user_id );
			return;
		}

		error_log( 'NDM: Processing ' . count( $recent_posts ) . ' recent posts for user: ' . $user_id );

		foreach ( $recent_posts as $index => $post_data ) {
			$post_id = $post_data['post_id'];
			$post = get_post( $post_id );

			// Check if post exists and is of the correct type
			if ( ! $post || $post->post_type !== $this->settings['post_type'] ) {
				continue;
			}

			// Check if post already has an author
			if ( $post->post_author && $post->post_author != 0 ) {
				error_log( 'NDM: Post ' . $post_id . ' already has author: ' . $post->post_author );
				continue;
			}

			error_log( 'NDM: Assigning user ' . $user_id . ' to post ' . $post_id );

			// Assign user as post author
			$this->assign_user_to_post( $post_id, $user_id );

			// Assign category to post
			$this->assign_category_to_post( $post_id, $stored_data );

			// Remove this post from the recent posts list
			unset( $recent_posts[ $index ] );
		}

		// Update the recent posts list
		if ( empty( $recent_posts ) ) {
			delete_transient( 'ndm_recent_posts' );
		} else {
			set_transient( 'ndm_recent_posts', array_values( $recent_posts ), 300 );
		}
	}

	/**
	 * Test plugin functionality.
	 */
	public function test_plugin() {
		error_log( 'NDM: Test function called' );
		
		// Test user role creation
		$this->create_user_role();
		
		// Test if role exists
		$role_name = $this->settings['user_role_name'];
		$role = get_role( $role_name );
		if ( $role ) {
			error_log( 'NDM: Role ' . $role_name . ' exists with capabilities: ' . print_r( $role->capabilities, true ) );
		} else {
			error_log( 'NDM: Role ' . $role_name . ' does NOT exist!' );
		}
		
		// Test form detection
		$forms = $this->get_fluent_forms();
		error_log( 'NDM: Found ' . count( $forms ) . ' Fluent Forms' );
		
		// Test settings
		error_log( 'NDM: Current settings: ' . print_r( $this->settings, true ) );
		
		wp_die( 'BDRM Test completed. Check error logs for details.' );
	}

	/**
	 * Test form field structure.
	 */
	public function test_form_fields() {
		error_log( 'NDM: Testing form field structure' );
		
		// Get the configured form ID
		$form_id = $this->settings['fluent_form_id'];
		if ( ! $form_id ) {
			error_log( 'NDM: No form ID configured' );
			return;
		}
		
		// Try to get form fields from Fluent Forms
		if ( class_exists( 'FluentForm\App\Services\FormBuilder\Components\Select' ) ) {
			error_log( 'NDM: Fluent Forms classes available' );
		}
		
		// Get form data from database
		global $wpdb;
		$table_name = $wpdb->prefix . 'fluentform_forms';
		$form_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $form_id ) );
		
		if ( $form_data ) {
			error_log( 'NDM: Form data: ' . print_r( $form_data, true ) );
			
			// Try to decode the form fields
			$form_fields = json_decode( $form_data->form_fields, true );
			if ( $form_fields ) {
				error_log( 'NDM: Form fields: ' . print_r( $form_fields, true ) );
				
				// Look for category fields
				foreach ( $form_fields as $field ) {
					if ( isset( $field['element'] ) && $field['element'] === 'select' ) {
						error_log( 'NDM: Found select field: ' . $field['attributes']['name'] . ' with options: ' . print_r( $field['options'], true ) );
					}
				}
			}
		}
		
		wp_die( 'BDRM Form Field Test completed. Check error logs for details.' );
	}

	/**
	 * Get user ID from Fluent Forms entry.
	 *
	 * @param array $entry Entry data.
	 * @param array $form_data Form data.
	 * @return int|false User ID or false if not found.
	 */
	private function get_user_id_from_entry( $entry, $form_data ) {
		error_log( 'NDM: Attempting to get user ID from entry' );

		// Method 1: Check if user registration was successful
		if ( isset( $entry->user_id ) && $entry->user_id ) {
			error_log( 'NDM: Found user_id in entry: ' . $entry->user_id );
			return $entry->user_id;
		}

		// Method 2: Check entry response for user_id
		if ( isset( $entry->response ) && isset( $entry->response['user_id'] ) && $entry->response['user_id'] ) {
			error_log( 'NDM: Found user_id in entry response: ' . $entry->response['user_id'] );
			return $entry->response['user_id'];
		}

		// Method 3: Try to find user by email from form data
		if ( isset( $form_data['email'] ) && ! empty( $form_data['email'] ) ) {
			$user = get_user_by( 'email', $form_data['email'] );
			if ( $user ) {
				error_log( 'NDM: Found user by email: ' . $user->ID );
				return $user->ID;
			}
		}

		// Method 4: Try to find user by email from entry response
		if ( isset( $entry->response ) && isset( $entry->response['email'] ) && ! empty( $entry->response['email'] ) ) {
			$user = get_user_by( 'email', $entry->response['email'] );
			if ( $user ) {
				error_log( 'NDM: Found user by email from response: ' . $user->ID );
				return $user->ID;
			}
		}

		// Method 5: Check if entry has user_id property
		if ( is_object( $entry ) && isset( $entry->user_id ) ) {
			error_log( 'NDM: Found user_id in entry object: ' . $entry->user_id );
			return $entry->user_id;
		}

		error_log( 'NDM: No user ID found in any method' );
		return false;
	}

	/**
	 * Get post ID from Fluent Forms entry.
	 *
	 * @param array $entry Entry data.
	 * @param array $form_data Form data.
	 * @return int|false Post ID or false if not found.
	 */
	private function get_post_id_from_entry( $entry, $form_data ) {
		error_log( 'NDM: Attempting to get post ID from entry' );

		// Method 1: Check if post was created and stored in response
		if ( isset( $entry->response ) && isset( $entry->response['post_id'] ) && $entry->response['post_id'] ) {
			error_log( 'NDM: Found post_id in entry response: ' . $entry->response['post_id'] );
			return $entry->response['post_id'];
		}

		// Method 2: Check if post was created and stored in form data
		if ( isset( $form_data['post_id'] ) && $form_data['post_id'] ) {
			error_log( 'NDM: Found post_id in form data: ' . $form_data['post_id'] );
			return $form_data['post_id'];
		}

		// Method 3: Try to find post by title from form data
		if ( isset( $form_data['post_title'] ) && ! empty( $form_data['post_title'] ) ) {
			$posts = get_posts( array(
				'post_type' => $this->settings['post_type'],
				'post_title' => $form_data['post_title'],
				'post_status' => 'draft',
				'numberposts' => 1,
				'orderby' => 'ID',
				'order' => 'DESC',
			) );

			if ( ! empty( $posts ) ) {
				error_log( 'NDM: Found post by title: ' . $posts[0]->ID );
				return $posts[0]->ID;
			}
		}

		// Method 4: Try to find post by title from entry response
		if ( isset( $entry->response ) && isset( $entry->response['post_title'] ) && ! empty( $entry->response['post_title'] ) ) {
			$posts = get_posts( array(
				'post_type' => $this->settings['post_type'],
				'post_title' => $entry->response['post_title'],
				'post_status' => 'draft',
				'numberposts' => 1,
				'orderby' => 'ID',
				'order' => 'DESC',
			) );

			if ( ! empty( $posts ) ) {
				error_log( 'NDM: Found post by title from response: ' . $posts[0]->ID );
				return $posts[0]->ID;
			}
		}

		// Method 5: Find the most recent post of the configured post type
		$posts = get_posts( array(
			'post_type' => $this->settings['post_type'],
			'post_status' => 'draft',
			'numberposts' => 1,
			'orderby' => 'ID',
			'order' => 'DESC',
		) );

		if ( ! empty( $posts ) ) {
			error_log( 'NDM: Found most recent post: ' . $posts[0]->ID );
			return $posts[0]->ID;
		}

		error_log( 'NDM: No post ID found in any method' );
		return false;
	}

	/**
	 * Assign user role to the user.
	 *
	 * @param int $user_id User ID.
	 */
	private function assign_user_role( $user_id ) {
		error_log( "NDM: Starting role assignment for user {$user_id}" );
		
		// Check if settings are loaded
		if ( empty( $this->settings ) ) {
			error_log( "NDM: Settings not loaded, loading them now" );
			$this->load_settings();
		}
		
		error_log( "NDM: Current settings: " . print_r( $this->settings, true ) );
		
		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			error_log( "NDM: User {$user_id} not found for role assignment" );
			return;
		}

		$role_name = $this->settings['user_role_name'] ?? 'business_owner';
		error_log( "NDM: Attempting to assign role '{$role_name}' to user {$user_id}" );
		error_log( "NDM: User current roles: " . print_r( $user->roles, true ) );
		
		// Check if the role exists
		$role = get_role( $role_name );
		if ( ! $role ) {
			error_log( "NDM: Role '{$role_name}' does not exist! Creating it..." );
			$this->create_user_role();
			$role = get_role( $role_name );
			if ( ! $role ) {
				error_log( "NDM: Failed to create role '{$role_name}'" );
				return;
			}
		}
		
		// Check if user already has the role
		if ( in_array( $role_name, $user->roles ) ) {
			error_log( "NDM: User {$user_id} already has role {$role_name}" );
			return;
		}

		// Check if user has a different role that needs to be changed
		if ( ! empty( $user->roles ) && ! in_array( $role_name, $user->roles ) ) {
			// Check if user has administrative capabilities - if so, don't change their role
			if ( $user->has_cap( 'manage_options' ) || $user->has_cap( 'administrator' ) ) {
				error_log( "NDM: Admin user {$user_id} would be changed to {$role_name} - preserving admin role" );
				return;
			}
			error_log( "NDM: User {$user_id} has different roles: " . print_r( $user->roles, true ) . ". Changing to {$role_name}" );
		}

		// Assign the role (this will replace any existing roles)
		$result = $user->set_role( $role_name );
		error_log( "NDM: set_role() result: " . print_r( $result, true ) );
		
		// Verify the role was assigned
		$user_after = get_user_by( 'ID', $user_id );
		error_log( "NDM: User roles after assignment: " . print_r( $user_after->roles, true ) );
		
		if ( in_array( $role_name, $user_after->roles ) ) {
			error_log( "NDM: Successfully assigned role {$role_name} to user {$user_id}" );
		} else {
			error_log( "NDM: FAILED to assign role {$role_name} to user {$user_id}" );
		}
	}

	/**
	 * Assign user as post author.
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID.
	 */
	private function assign_user_to_post( $post_id, $user_id ) {
		$post_data = array(
			'ID' => $post_id,
			'post_author' => $user_id,
		);

		$result = wp_update_post( $post_data );

		if ( $result ) {
			error_log( "NDM: Successfully assigned user {$user_id} as author of post {$post_id}" );
		} else {
			error_log( "NDM: Failed to assign user {$user_id} as author of post {$post_id}" );
		}
	}

	/**
	 * Assign category to post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $form_data Form data.
	 */
	private function assign_category_to_post( $post_id, $form_data ) {
		$category_field = $this->settings['category_field'];
		
		error_log( "NDM: Attempting to assign category from field: {$category_field}" );
		error_log( "NDM: Current settings: " . print_r( $this->settings, true ) );
		error_log( "NDM: Form data keys: " . print_r( array_keys( $form_data ), true ) );
		
		// Method 1: Check form_data directly
		if ( isset( $form_data[ $category_field ] ) && ! empty( $form_data[ $category_field ] ) ) {
			$category_id = intval( $form_data[ $category_field ] );
			error_log( "NDM: Found category ID in form_data: {$category_id}" );
			
			// Set the category
			$result = wp_set_object_terms( $post_id, $category_id, 'category' );
			
			if ( ! is_wp_error( $result ) ) {
				error_log( "NDM: Successfully assigned category {$category_id} to post {$post_id}" );
			} else {
				error_log( "NDM: Failed to assign category {$category_id} to post {$post_id}: " . $result->get_error_message() );
			}
			return;
		}

		// Method 2: Check for business_category field
		if ( isset( $form_data['business_category'] ) && ! empty( $form_data['business_category'] ) ) {
			$category_id = intval( $form_data['business_category'] );
			error_log( "NDM: Found category ID in business_category field: {$category_id}" );
			
			// Set the category
			$result = wp_set_object_terms( $post_id, $category_id, 'category' );
			
			if ( ! is_wp_error( $result ) ) {
				error_log( "NDM: Successfully assigned category {$category_id} to post {$post_id}" );
			} else {
				error_log( "NDM: Failed to assign category {$category_id} to post {$post_id}: " . $result->get_error_message() );
			}
			return;
		}

		// Method 3: Check for category field
		if ( isset( $form_data['category'] ) && ! empty( $form_data['category'] ) ) {
			$category_id = intval( $form_data['category'] );
			error_log( "NDM: Found category ID in category field: {$category_id}" );
			
			// Set the category
			$result = wp_set_object_terms( $post_id, $category_id, 'category' );
			
			if ( ! is_wp_error( $result ) ) {
				error_log( "NDM: Successfully assigned category {$category_id} to post {$post_id}" );
			} else {
				error_log( "NDM: Failed to assign category {$category_id} to post {$post_id}: " . $result->get_error_message() );
			}
			return;
		}

		// Method 4: Check for URL-encoded data field
		if ( isset( $form_data['data'] ) && ! empty( $form_data['data'] ) ) {
			error_log( "NDM: Found data field, attempting to parse URL-encoded data" );
			
			// Parse the URL-encoded data
			parse_str( $form_data['data'], $parsed_data );
			error_log( "NDM: Parsed data keys: " . print_r( array_keys( $parsed_data ), true ) );
			error_log( "NDM: Full parsed data: " . print_r( $parsed_data, true ) );
			
			// Check for category in parsed data
			if ( isset( $parsed_data[ $category_field ] ) && ! empty( $parsed_data[ $category_field ] ) ) {
				$category_id = intval( $parsed_data[ $category_field ] );
				error_log( "NDM: Found category ID in parsed data for field '{$category_field}': {$category_id}" );
				
				// Set the category
				$result = wp_set_object_terms( $post_id, $category_id, 'category' );
				
				if ( ! is_wp_error( $result ) ) {
					error_log( "NDM: Successfully assigned category {$category_id} to post {$post_id}" );
				} else {
					error_log( "NDM: Failed to assign category {$category_id} to post {$post_id}: " . $result->get_error_message() );
				}
				return;
			}

			// Check for business_category in parsed data
			if ( isset( $parsed_data['business_category'] ) && ! empty( $parsed_data['business_category'] ) ) {
				$category_id = intval( $parsed_data['business_category'] );
				error_log( "NDM: Found category ID in parsed business_category field: {$category_id}" );
				
				// Set the category
				$result = wp_set_object_terms( $post_id, $category_id, 'category' );
				
				if ( ! is_wp_error( $result ) ) {
					error_log( "NDM: Successfully assigned category {$category_id} to post {$post_id}" );
				} else {
					error_log( "NDM: Failed to assign category {$category_id} to post {$post_id}: " . $result->get_error_message() );
				}
				return;
			}
			
			// Log all potential category fields
			error_log( "NDM: Looking for category field '{$category_field}' in parsed data" );
			foreach ( $parsed_data as $key => $value ) {
				if ( strpos( $key, 'category' ) !== false || strpos( $key, 'cat' ) !== false ) {
					error_log( "NDM: Found potential category field '{$key}' with value: {$value}" );
				}
			}
		}

		error_log( "NDM: No category found in form data for field: {$category_field}" );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
		if ( 'toplevel_page_nova-directory-manager' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'ndm-admin-style',
			NDM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			NDM_VERSION
		);

		wp_enqueue_script(
			'ndm-admin-script',
			NDM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			NDM_VERSION,
			true
		);
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'nova-directory-manager',
			false,
			dirname( NDM_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Show notice if Fluent Forms is not active.
	 *
	 * @since 1.0.0
	 */
	public function fluent_forms_missing_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Nova Directory Manager:', 'nova-directory-manager' ); ?></strong>
				<?php esc_html_e( 'Fluent Forms is not detected. Please ensure Fluent Forms is installed and activated.', 'nova-directory-manager' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'Debug Info:', 'nova-directory-manager' ); ?>
				<br>
				<?php esc_html_e( '• FluentForm class exists:', 'nova-directory-manager' ); ?> <?php echo class_exists( 'FluentForm' ) ? 'Yes' : 'No'; ?>
				<br>
				<?php esc_html_e( '• fluentForm function exists:', 'nova-directory-manager' ); ?> <?php echo function_exists( 'fluentForm' ) ? 'Yes' : 'No'; ?>
				<br>
				<?php esc_html_e( '• Fluent Forms plugin active:', 'nova-directory-manager' ); ?> <?php echo is_plugin_active( 'fluentform/fluentform.php' ) ? 'Yes' : 'No'; ?>
				<br>
				<?php esc_html_e( '• Fluent Forms Pro active:', 'nova-directory-manager' ); ?> <?php echo is_plugin_active( 'fluentformpro/fluentformpro.php' ) ? 'Yes' : 'No'; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation hook.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Set default options
		$default_settings = array(
			'user_role_name' => 'business_owner',
			'user_role_display_name' => 'Business Owner',
			'fluent_form_id' => 0,
			'post_type' => 'business',
			'category_field' => 'business_category',
			'user_role_capabilities' => array(
				'read' => true,
				'edit_posts' => true,
				'delete_posts' => false,
				'publish_posts' => false,
				'upload_files' => true,
				'edit_published_posts' => true,
				'delete_published_posts' => false,
			),
		);

		add_option( 'ndm_settings', $default_settings );

		// Create the default user role
		$this->settings = $default_settings;
		$this->create_user_role();
	}

	/**
	 * Plugin deactivation hook.
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		// Optionally remove the custom role on deactivation
		// remove_role( 'business_owner' );
	}

	/**
	 * Enqueue frontend scripts and styles.
	 */
	public function enqueue_frontend_scripts() {
		// Only enqueue on pages that might use our shortcodes
		if ( is_page() || is_single() ) {
			wp_enqueue_script( 'ndm-frontend', NDM_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), NDM_VERSION, true );
			wp_enqueue_style( 'ndm-frontend', NDM_PLUGIN_URL . 'assets/css/frontend.css', array(), NDM_VERSION );
			
			// Localize script for AJAX
			wp_localize_script( 'ndm-frontend', 'ndm_ajax', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ndm_frontend_nonce' ),
				'strings' => array(
					'saving' => __( 'Saving...', 'nova-directory-manager' ),
					'saved' => __( 'Business updated successfully!', 'nova-directory-manager' ),
					'error' => __( 'Error saving business. Please try again.', 'nova-directory-manager' ),
					'confirm_delete' => __( 'Are you sure you want to delete this business?', 'nova-directory-manager' )
				)
			) );
		}
	}

	/**
	 * Business edit form shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function business_edit_form_shortcode( $atts ) {
		// Check if user is logged in and has business_owner role
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to edit your business.', 'nova-directory-manager' ) . '</p>';
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'business_owner', $current_user->roles ) ) {
			return '<p>' . __( 'You do not have permission to edit businesses.', 'nova-directory-manager' ) . '</p>';
		}

		// Parse shortcode attributes
		$atts = shortcode_atts( array(
			'post_id' => 0,
		), $atts, 'ndm_business_edit_form' );

		$post_id = intval( $atts['post_id'] );

		// If no post_id provided, try to get from URL parameter
		if ( ! $post_id && isset( $_GET['business_id'] ) ) {
			$post_id = intval( $_GET['business_id'] );
		}

		// Validate post exists and user owns it
		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== 'business' || $post->post_author != $current_user->ID ) {
				return '<p>' . __( 'Business not found or you do not have permission to edit it.', 'nova-directory-manager' ) . '</p>';
			}
		} else {
			// Get the first business owned by this user
			$user_businesses = get_posts( array(
				'post_type' => 'business',
				'author' => $current_user->ID,
				'posts_per_page' => 1,
				'post_status' => 'any'
			) );

			if ( empty( $user_businesses ) ) {
				return '<p>' . __( 'No businesses found for your account.', 'nova-directory-manager' ) . '</p>';
			}

			$post_id = $user_businesses[0]->ID;
		}

		// Check if ACF is active
		if ( ! function_exists( 'acf_form' ) ) {
			return '<p>' . __( 'Advanced Custom Fields Pro is required to edit businesses.', 'nova-directory-manager' ) . '</p>';
		}

		// Start output buffering
		ob_start();

		// Add ACF form head
		acf_form_head();

		// Get the post
		$post = get_post( $post_id );
		setup_postdata( $post );

		// Output the form
		?>
		<div class="ndm-business-edit-form">
			<?php
			acf_form( array(
				'post_id' => $post_id,
				'post_title' => false, // Hide the title field
				'post_content' => false, // We don't use post content, only ACF fields
				'field_groups' => array( 'group_683a78bc7efb6' ), // Business fields group
				'form_attributes' => array(
					'class' => 'ndm-acf-form'
				),
				'html_before_fields' => '<div class="ndm-form-notices"></div>',
				'html_after_fields' => '<div class="ndm-form-actions"></div>',
				'submit_value' => __( 'Update Business', 'nova-directory-manager' ),
				'updated_message' => __( 'Business updated successfully!', 'nova-directory-manager' ),
				'return' => add_query_arg( 'updated', '1', get_permalink() ),
			) );
			?>

			<div class="ndm-form-actions">
				<a href="<?php echo esc_url( home_url( '/membership/member-dashboard/' ) ); ?>" class="button button-secondary">
					<?php _e( 'Back to Dashboard', 'nova-directory-manager' ); ?>
				</a>
			</div>
		</div>
		<?php

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Business list shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Shortcode output.
	 */
	public function business_list_shortcode( $atts ) {
		// Check if user is logged in and has business_owner role
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to view your businesses.', 'nova-directory-manager' ) . '</p>';
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'business_owner', $current_user->roles ) ) {
			return '<p>' . __( 'You do not have permission to view businesses.', 'nova-directory-manager' ) . '</p>';
		}

		// Parse shortcode attributes
		$atts = shortcode_atts( array(
			'posts_per_page' => 10,
			'show_status' => true,
		), $atts, 'ndm_business_list' );

		// Get user's businesses
		$businesses = get_posts( array(
			'post_type' => 'business',
			'author' => $current_user->ID,
			'posts_per_page' => intval( $atts['posts_per_page'] ),
			'post_status' => 'any',
			'orderby' => 'title',
			'order' => 'ASC'
		) );

		if ( empty( $businesses ) ) {
			return '<p>' . __( 'No businesses found for your account.', 'nova-directory-manager' ) . '</p>';
		}

		// Start output buffering
		ob_start();
		?>

		<div class="ndm-business-list">
			<h2><?php _e( 'Your Businesses', 'nova-directory-manager' ); ?></h2>
			
			<div class="ndm-business-grid">
				<?php foreach ( $businesses as $business ) : ?>
					<div class="ndm-business-item">
						<div class="ndm-business-header">
							<h3><?php echo esc_html( $business->post_title ); ?></h3>
							<?php if ( $atts['show_status'] ) : ?>
								<span class="ndm-business-status ndm-status-<?php echo esc_attr( $business->post_status ); ?>">
									<?php echo esc_html( ucfirst( $business->post_status ) ); ?>
								</span>
							<?php endif; ?>
						</div>
						
						<div class="ndm-business-meta">
							<?php
							$business_name = get_field( 'business_name', $business->ID );
							$business_email = get_field( 'business_email', $business->ID );
							$business_phone = get_field( 'business_phone', $business->ID );
							?>
							
							<?php if ( $business_name ) : ?>
								<p><strong><?php _e( 'Business Name:', 'nova-directory-manager' ); ?></strong> <?php echo esc_html( $business_name ); ?></p>
							<?php endif; ?>
							
							<?php if ( $business_email ) : ?>
								<p><strong><?php _e( 'Email:', 'nova-directory-manager' ); ?></strong> <?php echo esc_html( $business_email ); ?></p>
							<?php endif; ?>
							
							<?php if ( $business_phone ) : ?>
								<p><strong><?php _e( 'Phone:', 'nova-directory-manager' ); ?></strong> <?php echo esc_html( $business_phone ); ?></p>
							<?php endif; ?>
						</div>
						
						<div class="ndm-business-actions">
							<a href="<?php echo esc_url( add_query_arg( 'business_id', $business->ID, get_permalink() ) ); ?>" class="button button-primary">
								<?php _e( 'Edit Business', 'nova-directory-manager' ); ?>
							</a>
							
							<a href="<?php echo esc_url( get_permalink( $business->ID ) ); ?>" class="button button-secondary" target="_blank">
								<?php _e( 'View Business', 'nova-directory-manager' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX save business.
	 */
	public function ajax_save_business() {
		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ndm_frontend_nonce' ) ) {
			wp_die( __( 'Security check failed.', 'nova-directory-manager' ) );
		}

		// Check if user is logged in and has business_owner role
		if ( ! is_user_logged_in() ) {
			wp_die( __( 'You must be logged in to save businesses.', 'nova-directory-manager' ) );
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'business_owner', $current_user->roles ) ) {
			wp_die( __( 'You do not have permission to save businesses.', 'nova-directory-manager' ) );
		}

		// Get post ID
		$post_id = intval( $_POST['post_id'] );
		if ( ! $post_id ) {
			wp_die( __( 'Invalid business ID.', 'nova-directory-manager' ) );
		}

		// Verify user owns this business
		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'business' || $post->post_author != $current_user->ID ) {
			wp_die( __( 'Business not found or you do not have permission to edit it.', 'nova-directory-manager' ) );
		}

		// Update post title if provided
		if ( ! empty( $_POST['post_title'] ) ) {
			wp_update_post( array(
				'ID' => $post_id,
				'post_title' => sanitize_text_field( $_POST['post_title'] )
			) );
		}

		// Update ACF fields
		if ( function_exists( 'update_field' ) && ! empty( $_POST['acf'] ) ) {
			foreach ( $_POST['acf'] as $field_key => $value ) {
				update_field( $field_key, $value, $post_id );
			}
		}

		// Send success response
		wp_send_json_success( array(
			'message' => __( 'Business updated successfully!', 'nova-directory-manager' ),
			'redirect_url' => add_query_arg( 'updated', '1', get_permalink( $post_id ) )
		) );
	}

	/**
	 * Handle ACF form save.
	 *
	 * @param int $post_id Post ID.
	 */
	public function handle_acf_form_save( $post_id ) {
		// Only process business post types
		if ( get_post_type( $post_id ) !== 'business' ) {
			return;
		}

		// Log the save for debugging
		error_log( 'NDM: ACF form save triggered for business post ID: ' . $post_id );

		// Get the current user
		$current_user = wp_get_current_user();
		
		// Verify the user owns this business
		$post = get_post( $post_id );
		if ( ! $post || $post->post_author != $current_user->ID ) {
			error_log( 'NDM: User ' . $current_user->ID . ' does not own business ' . $post_id );
			return;
		}

		// Update the post author if needed (in case it was changed)
		if ( $post->post_author != $current_user->ID ) {
			wp_update_post( array(
				'ID' => $post_id,
				'post_author' => $current_user->ID
			) );
			error_log( 'NDM: Updated post author for business ' . $post_id . ' to user ' . $current_user->ID );
		}

		// Ensure the user has the business_owner role, but protect admin users
		if ( ! in_array( 'business_owner', $current_user->roles ) ) {
			// Check if user has administrative capabilities - if so, don't change their role
			if ( $current_user->has_cap( 'manage_options' ) || $current_user->has_cap( 'administrator' ) ) {
				error_log( 'NDM: Admin user ' . $current_user->ID . ' saved business ' . $post_id . ' - preserving admin role' );
			} else {
				$this->assign_user_role( $current_user->ID );
				error_log( 'NDM: Assigned business_owner role to user ' . $current_user->ID . ' during ACF save' );
			}
		}

		// Log successful save
		error_log( 'NDM: ACF form save completed successfully for business ' . $post_id . ' by user ' . $current_user->ID );
	}

	/**
	 * Update post title from business name field.
	 *
	 * @param int $post_id Post ID.
	 */
	public function update_post_title_from_business_name( $post_id ) {
		// Only process business posts
		if ( get_post_type( $post_id ) !== 'business' ) {
			return;
		}

		// Get the business name field value
		$business_name = get_field( 'business_name', $post_id );
		
		if ( ! empty( $business_name ) ) {
			// Update the post title
			wp_update_post( array(
				'ID' => $post_id,
				'post_title' => sanitize_text_field( $business_name ),
				'post_name' => sanitize_title( $business_name ), // Also update the slug
			) );
			
			error_log( "NDM: Updated post title to '$business_name' for post ID: $post_id" );
		}
	}

	/**
	 * Register the offers post type.
	 */
	private function register_offers_post_type() {
		$labels = array(
			'name'                  => _x( 'Offers', 'Post type general name', 'nova-directory-manager' ),
			'singular_name'         => _x( 'Offer', 'Post type singular name', 'nova-directory-manager' ),
			'menu_name'             => _x( 'Offers', 'Admin Menu text', 'nova-directory-manager' ),
			'name_admin_bar'        => _x( 'Offer', 'Add New on Toolbar', 'nova-directory-manager' ),
			'add_new'               => __( 'Add New', 'nova-directory-manager' ),
			'add_new_item'          => __( 'Add New Offer', 'nova-directory-manager' ),
			'new_item'              => __( 'New Offer', 'nova-directory-manager' ),
			'edit_item'             => __( 'Edit Offer', 'nova-directory-manager' ),
			'view_item'             => __( 'View Offer', 'nova-directory-manager' ),
			'all_items'             => __( 'All Offers', 'nova-directory-manager' ),
			'search_items'          => __( 'Search Offers', 'nova-directory-manager' ),
			'parent_item_colon'     => __( 'Parent Offer:', 'nova-directory-manager' ),
			'not_found'             => __( 'No offers found.', 'nova-directory-manager' ),
			'not_found_in_trash'    => __( 'No offers found in Trash.', 'nova-directory-manager' ),
		);

		$args = array(
			'labels'                => $labels,
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'offers' ),
			'capability_type'       => 'post',
			'has_archive'           => true,
			'hierarchical'          => false,
			'menu_position'         => null,
			'menu_icon'             => 'dashicons-money-alt',
			'supports'              => array( 'title', 'thumbnail' ),
			'taxonomies'            => array( 'category' ),
			'show_in_rest'          => true,
		);

		register_post_type( 'offer', $args );
	}

	/**
	 * Create the advertiser user role.
	 */
	private function create_advertiser_role() {
		remove_role( 'advertiser' );
		$capabilities = array(
			'read'                   => true,
			'edit_posts'             => true,
			'delete_posts'           => false,
			'publish_posts'          => false,
			'upload_files'           => true,
			'edit_published_posts'   => true,
			'delete_published_posts' => false,
			// Custom capabilities for offers (future use)
			'edit_offers'            => true,
			'edit_published_offers'  => true,
			'publish_offers'         => false, // Requires admin approval
			'delete_offers'          => false,
			'delete_published_offers'=> false,
		);
		add_role( 'advertiser', __( 'Advertiser', 'nova-directory-manager' ), $capabilities );
	}

	/**
	 * Get offer setting with default fallback.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_offer_setting( $key, $default = null ) {
		$offer_settings = get_option( 'ndm_offer_settings', array() );
		return isset( $offer_settings[ $key ] ) ? $offer_settings[ $key ] : $default;
	}

	/**
	 * Save offer setting.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 */
	private function save_offer_setting( $key, $value ) {
		$offer_settings = get_option( 'ndm_offer_settings', array() );
		$offer_settings[ $key ] = $value;
		update_option( 'ndm_offer_settings', $offer_settings );
	}

	/**
	 * Get currency symbol.
	 *
	 * @return string
	 */
	private function get_currency_symbol() {
		$currency = $this->get_offer_setting( 'currency', 'AUD' );
		$symbols = array(
			'AUD' => 'A$',
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'CAD' => 'C$',
		);
		return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency;
	}

	/**
	 * Get offers statistics.
	 *
	 * @return array
	 */
	private function get_offers_statistics() {
		$stats = array(
			'total'    => 0,
			'active'   => 0,
			'pending'  => 0,
			'expired'  => 0,
			'revenue'  => 0.00,
		);

		// Get all offers
		$offers = get_posts( array(
			'post_type'      => 'offer',
			'post_status'    => 'any',
			'numberposts'    => -1,
			'fields'         => 'ids',
		) );

		$stats['total'] = count( $offers );

		foreach ( $offers as $offer_id ) {
			$status = get_post_status( $offer_id );
			$is_paid = get_field( 'is_paid_offer', $offer_id );
			$price = get_field( 'offer_price', $offer_id );
			$expiry_date = get_field( 'expiry_date', $offer_id );

			// Count by status
			if ( $status === 'publish' ) {
				$stats['active']++;
			} elseif ( $status === 'pending' ) {
				$stats['pending']++;
			} elseif ( $status === 'draft' ) {
				$stats['pending']++;
			}

			// Check if expired
			if ( $expiry_date && strtotime( $expiry_date ) < time() ) {
				$stats['expired']++;
			}

			// Calculate revenue (only for paid offers)
			if ( $is_paid && $price ) {
				$stats['revenue'] += floatval( $price );
			}
		}

		return $stats;
	}

	/**
	 * Handle offers admin form submissions.
	 */
	private function handle_offers_admin_actions() {
		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		$action = sanitize_text_field( $_POST['action'] );

		switch ( $action ) {
			case 'save_general_settings':
				if ( wp_verify_nonce( $_POST['ndm_offers_nonce'], 'ndm_offers_settings_nonce' ) ) {
					$this->save_general_settings();
				}
				break;

			case 'save_advertiser_pricing':
				if ( wp_verify_nonce( $_POST['ndm_offers_nonce'], 'ndm_offers_settings_nonce' ) ) {
					$this->save_advertiser_pricing();
				}
				break;

			case 'save_business_owner_pricing':
				if ( wp_verify_nonce( $_POST['ndm_offers_nonce'], 'ndm_offers_settings_nonce' ) ) {
					$this->save_business_owner_pricing();
				}
				break;

			case 'save_approval_settings':
				if ( wp_verify_nonce( $_POST['ndm_offers_nonce'], 'ndm_offers_settings_nonce' ) ) {
					$this->save_offers_approval_settings();
				}
				break;

			case 'bulk_offers_action':
				if ( wp_verify_nonce( $_POST['ndm_offers_bulk_nonce'], 'ndm_offers_bulk_nonce' ) ) {
					$this->execute_bulk_offers_action();
				}
				break;
		}
	}

	/**
	 * Save general settings.
	 */
	private function save_general_settings() {
		$this->save_offer_setting( 'currency', sanitize_text_field( $_POST['currency'] ?? 'AUD' ) );
		$this->save_offer_setting( 'default_duration_days', intval( $_POST['default_duration_days'] ?? 30 ) );

		add_settings_error(
			'ndm_messages',
			'ndm_general_settings_saved',
			__( 'General settings saved successfully!', 'nova-directory-manager' ),
			'success'
		);
	}

	/**
	 * Save advertiser pricing settings.
	 */
	private function save_advertiser_pricing() {
		$this->save_offer_setting( 'advertiser_base_price', floatval( $_POST['advertiser_base_price'] ?? 49.99 ) );
		$this->save_offer_setting( 'advertiser_volume_discounts', sanitize_textarea_field( $_POST['advertiser_volume_discounts'] ?? '' ) );

		add_settings_error(
			'ndm_messages',
			'ndm_advertiser_pricing_saved',
			__( 'Advertiser pricing settings saved successfully!', 'nova-directory-manager' ),
			'success'
		);
	}

	/**
	 * Save business owner pricing settings.
	 */
	private function save_business_owner_pricing() {
		$this->save_offer_setting( 'business_owner_included_offers', intval( $_POST['business_owner_included_offers'] ?? 2 ) );
		$this->save_offer_setting( 'business_owner_max_duration', intval( $_POST['business_owner_max_duration'] ?? 60 ) );
		$this->save_offer_setting( 'business_owner_additional_price', floatval( $_POST['business_owner_additional_price'] ?? 29.99 ) );
		$this->save_offer_setting( 'business_owner_volume_discounts', sanitize_textarea_field( $_POST['business_owner_volume_discounts'] ?? '' ) );

		add_settings_error(
			'ndm_messages',
			'ndm_business_owner_pricing_saved',
			__( 'Business owner pricing settings saved successfully!', 'nova-directory-manager' ),
			'success'
		);
	}

	/**
	 * Save offers approval settings.
	 */
	private function save_offers_approval_settings() {
		$this->save_offer_setting( 'require_approval', isset( $_POST['require_approval'] ) );
		$this->save_offer_setting( 'auto_expire', isset( $_POST['auto_expire'] ) );
		$this->save_offer_setting( 'expiry_notification_days', intval( $_POST['expiry_notification_days'] ?? 3 ) );

		add_settings_error(
			'ndm_messages',
			'ndm_offers_approval_saved',
			__( 'Approval settings saved successfully!', 'nova-directory-manager' ),
			'success'
		);
	}

	/**
	 * Execute bulk offers action.
	 */
	private function execute_bulk_offers_action() {
		$action = sanitize_text_field( $_POST['bulk_action'] ?? '' );
		$count = 0;

		switch ( $action ) {
			case 'approve_all':
				$count = $this->bulk_approve_offers();
				break;
			case 'expire_all':
				$count = $this->bulk_expire_offers();
				break;
			case 'extend_all':
				$count = $this->bulk_extend_offers();
				break;
			case 'delete_expired':
				$count = $this->bulk_delete_expired_offers();
				break;
		}

		if ( $count > 0 ) {
			add_settings_error(
				'ndm_messages',
				'ndm_bulk_action_completed',
				sprintf( __( 'Bulk action completed successfully! %d offers affected.', 'nova-directory-manager' ), $count ),
				'success'
			);
		} else {
			add_settings_error(
				'ndm_messages',
				'ndm_bulk_action_no_offers',
				__( 'No offers were affected by this action.', 'nova-directory-manager' ),
				'info'
			);
		}
	}

	/**
	 * Bulk approve all pending offers.
	 *
	 * @return int Number of offers approved.
	 */
	private function bulk_approve_offers() {
		$offers = get_posts( array(
			'post_type'   => 'offer',
			'post_status' => array( 'pending', 'draft' ),
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		$count = 0;
		foreach ( $offers as $offer_id ) {
			wp_update_post( array(
				'ID'          => $offer_id,
				'post_status' => 'publish',
			) );
			$count++;
		}

		return $count;
	}

	/**
	 * Bulk expire all active offers.
	 *
	 * @return int Number of offers expired.
	 */
	private function bulk_expire_offers() {
		$offers = get_posts( array(
			'post_type'   => 'offer',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		$count = 0;
		foreach ( $offers as $offer_id ) {
			wp_update_post( array(
				'ID'          => $offer_id,
				'post_status' => 'draft',
			) );
			$count++;
		}

		return $count;
	}

	/**
	 * Bulk extend all offers by 30 days.
	 *
	 * @return int Number of offers extended.
	 */
	private function bulk_extend_offers() {
		$offers = get_posts( array(
			'post_type'   => 'offer',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		$count = 0;
		foreach ( $offers as $offer_id ) {
			$expiry_date = get_field( 'expiry_date', $offer_id );
			if ( $expiry_date ) {
				$new_expiry = date( 'Y-m-d', strtotime( $expiry_date . ' +30 days' ) );
				update_field( 'expiry_date', $new_expiry, $offer_id );
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Bulk delete expired offers.
	 *
	 * @return int Number of offers deleted.
	 */
	private function bulk_delete_expired_offers() {
		$offers = get_posts( array(
			'post_type'   => 'offer',
			'post_status' => 'any',
			'numberposts' => -1,
			'fields'      => 'ids',
		) );

		$count = 0;
		foreach ( $offers as $offer_id ) {
			$expiry_date = get_field( 'expiry_date', $offer_id );
			if ( $expiry_date && strtotime( $expiry_date ) < time() ) {
				wp_delete_post( $offer_id, true );
				$count++;
			}
		}

		return $count;
	}
}

// Initialize the plugin.
Nova_Directory_Manager::get_instance(); 