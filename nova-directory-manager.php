<?php
/**
 * Plugin Name: Nova Directory Manager
 * Plugin URI: https://novastrategic.co
 * Description: Manages business directory registrations with Fluent Forms integration, custom user roles, and automatic post creation with frontend editing capabilities.
 * Version: 2.0.25
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
define( 'NDM_VERSION', '2.0.25' );
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
		add_shortcode( 'ndm_offer_form', array( $this, 'offer_form_shortcode' ) );
		add_shortcode( 'ndm_blog_post_form', array( $this, 'blog_post_form_shortcode' ) );
		add_action( 'wp_ajax_ndm_save_business', array( $this, 'ajax_save_business' ) );
		add_action( 'wp_ajax_nopriv_ndm_save_business', array( $this, 'ajax_save_business' ) );
		
		// ACF form processing
		add_action( 'acf/save_post', array( $this, 'handle_acf_form_save' ), 10, 1 );
		
		// Auto-update post title from business name field
		add_action( 'acf/save_post', array( $this, 'update_post_title_from_business_name' ), 20, 1 );
		
		// Activation and deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_filter( 'acf/prepare_field/name=offer_business', array( $this, 'maybe_hide_offer_business_field' ) );
		add_action( 'acf/save_post', array( $this, 'sync_offer_category_from_business' ), 20, 1 );
		add_action( 'acf/save_post', array( $this, 'ensure_offer_author' ), 10, 1 );
		
		// Ensure ACF fields are always registered for offers (reduced to prevent duplicates)
		add_action( 'acf/init', array( $this, 'ensure_offer_acf_fields' ) );
		add_action( 'init', array( $this, 'ensure_offer_acf_fields' ), 20 );
		
		// Hook into ACF field group loading to ensure our fields are included
		add_filter( 'acf/load_field_groups', array( $this, 'force_offer_field_groups' ) );
		
		// Force ACF to reload field groups on offer post screens
		add_action( 'admin_head', array( $this, 'force_acf_reload_on_offer_screens' ) );

		// Register the Advertiser Type taxonomy for offers
		add_action('init', function() {
			register_taxonomy('advertiser_type', 'offer', array(
				'labels' => array(
					'name' => 'Advertiser Types',
					'singular_name' => 'Advertiser Type',
				),
				'public' => false,
				'show_ui' => true,
				'show_in_menu' => true,
				'show_admin_column' => true,
				'hierarchical' => false,
				'rewrite' => false,
			));
			// Ensure default terms exist
			if (!term_exists('Advertiser', 'advertiser_type')) {
				wp_insert_term('Advertiser', 'advertiser_type');
			}
			if (!term_exists('YBA Member', 'advertiser_type')) {
				wp_insert_term('YBA Member', 'advertiser_type');
			}
		}, 11);

		// Add custom columns to business post type admin
		add_filter('manage_business_posts_columns', array( $this, 'add_business_admin_columns' ) );
		add_action('manage_business_posts_custom_column', array( $this, 'display_business_admin_columns' ), 10, 2 );

		// Assign advertiser_type term on offer save
		add_action('acf/save_post', function($post_id) {
			if (get_post_type($post_id) !== 'offer') return;
			if (!is_user_logged_in()) return;
			$user = wp_get_current_user();
			if (in_array('advertiser', $user->roles, true)) {
				wp_set_object_terms($post_id, 'Advertiser', 'advertiser_type', false);
			} elseif (in_array('business_owner', $user->roles, true)) {
				wp_set_object_terms($post_id, 'YBA Member', 'advertiser_type', false);
			}
		}, 15);
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

		// Handle manual ACF field registration
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'register_acf_fields' && check_admin_referer( 'ndm_acf_fields_nonce', 'ndm_acf_nonce' ) ) {
			$this->register_offer_acf_fields();
			echo '<div class="notice notice-success"><p>ACF fields registered successfully!</p></div>';
		}
		
		// Handle ACF field cleanup
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'cleanup_acf_fields' && check_admin_referer( 'ndm_acf_fields_nonce', 'ndm_acf_nonce' ) ) {
			$cleaned = $this->cleanup_duplicate_acf_field_groups();
			echo '<div class="notice notice-success"><p>ACF field cleanup completed. Removed ' . $cleaned . ' duplicate field groups.</p></div>';
		}

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
				<a href="?page=nova-directory-manager&tab=settings" class="nav-tab<?php if ( $active_tab === 'settings' ) echo ' nav-tab-active'; ?>">Settings</a>
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
							<h3><?php _e( 'ACF Field Registration', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'If ACF fields are not appearing in the admin, click the button below to manually register them.', 'nova-directory-manager' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_acf_fields_nonce', 'ndm_acf_nonce' ); ?>
								<input type="hidden" name="action" value="register_acf_fields" />
								<?php submit_button( __( 'Register ACF Fields', 'nova-directory-manager' ), 'secondary', 'register_acf_fields' ); ?>
							</form>
						</div>

						<div class="ndm-admin-box">
							<h3><?php _e( 'ACF Field Cleanup', 'nova-directory-manager' ); ?></h3>
							<p><?php _e( 'If you have duplicate ACF field groups, click the button below to clean them up.', 'nova-directory-manager' ); ?></p>
							<form method="post" action="">
								<?php wp_nonce_field( 'ndm_acf_fields_nonce', 'ndm_acf_nonce' ); ?>
								<input type="hidden" name="action" value="cleanup_acf_fields" />
								<?php submit_button( __( 'Cleanup Duplicate ACF Fields', 'nova-directory-manager' ), 'secondary', 'cleanup_acf_fields' ); ?>
							</form>
						</div>

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
							<p><strong><?php _e( 'Offer Form:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_offer_form]</code>
							<p><strong><?php _e( 'Blog Post Form:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_blog_post_form]</code>
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

						<div class="ndm-admin-box">
							<h3><?php _e( 'Advertiser Shortcodes', 'nova-directory-manager' ); ?></h3>
							<p><strong><?php _e( 'Offer Creation Form:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_offer_form]</code>
							<p><strong><?php _e( 'Advertiser Dashboard:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_advertiser_dashboard]</code>
							<p><strong><?php _e( 'Offer Management:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_offer_management]</code>
							<p><strong><?php _e( 'Payment History:', 'nova-directory-manager' ); ?></strong></p>
							<code>[ndm_payment_history]</code>
							<p class="description"><?php _e( 'These shortcodes provide frontend interfaces for advertisers to manage their offers and payments.', 'nova-directory-manager' ); ?></p>
						</div>
					</div>
				<?php elseif ( $active_tab === 'settings' ) :
					// Settings tab should be a top-level form, not inside any other form
					echo '<div class="wrap">';
					echo '<h2>' . __( 'NDM Settings', 'nova-directory-manager' ) . '</h2>';
					echo '<h3>' . __( 'Admin Notification Emails', 'nova-directory-manager' ) . '</h3>';
					// Handle add/remove email actions
					if ( isset( $_POST['ndm_add_admin_email'] ) && check_admin_referer( 'ndm_admin_emails_nonce', 'ndm_admin_emails_nonce_field' ) ) {
						$emails = get_option( 'ndm_admin_emails', array() );
						$new_email = sanitize_email( $_POST['ndm_new_admin_email'] ?? '' );
						if ( $new_email && ! in_array( $new_email, $emails ) ) {
							$emails[] = $new_email;
							update_option( 'ndm_admin_emails', $emails );
							echo '<div class="notice notice-success"><p>Admin email added.</p></div>';
						}
					}
					if ( isset( $_POST['ndm_remove_admin_email'] ) && isset( $_POST['ndm_email_to_remove'] ) && check_admin_referer( 'ndm_admin_emails_nonce', 'ndm_admin_emails_nonce_field' ) ) {
						$emails = get_option( 'ndm_admin_emails', array() );
						$remove = sanitize_email( $_POST['ndm_email_to_remove'] );
						$emails = array_filter( $emails, function($e) use ($remove) { return $e !== $remove; });
						update_option( 'ndm_admin_emails', $emails );
						echo '<div class="notice notice-success"><p>Admin email removed.</p></div>';
					}
					$emails = get_option( 'ndm_admin_emails', array() );
					?>
					<form method="post">
						<?php wp_nonce_field( 'ndm_admin_emails_nonce', 'ndm_admin_emails_nonce_field' ); ?>
						<table class="form-table">
							<tbody>
								<?php foreach ( $emails as $email ) : ?>
									<tr>
										<td><?php echo esc_html( $email ); ?></td>
										<td>
											<button type="submit" name="ndm_remove_admin_email" value="1" class="button">Remove</button>
											<input type="hidden" name="ndm_email_to_remove" value="<?php echo esc_attr( $email ); ?>" />
										</td>
									</tr>
								<?php endforeach; ?>
								<tr>
									<td>
										<input type="email" name="ndm_new_admin_email" placeholder="Add new admin email" class="regular-text" />
									</td>
									<td>
										<button type="submit" name="ndm_add_admin_email" value="1" class="button button-primary">Add Email</button>
									</td>
								</tr>
							</tbody>
						</table>
					</form>
					<?php
					echo '</div>';
				endif; ?>
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
		
		// Register offers post type and ACF fields
		$this->register_offers_post_type();
		
		// Flush rewrite rules
		flush_rewrite_rules();
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
		// Import the offers post type from the JSON file
		$json_file = NDM_PLUGIN_DIR . 'docs/acf-export-offers-post-type.json';
		if ( file_exists( $json_file ) ) {
			$json_content = file_get_contents( $json_file );
			$post_types = json_decode( $json_content, true );
			
			if ( is_array( $post_types ) && ! empty( $post_types ) ) {
				$post_type_data = $post_types[0]; // Get the first (and only) post type
				
				// Convert ACF post type format to WordPress format
				$supports = $post_type_data['supports'];
				
				// Ensure author support is included for offers
				if ( $post_type_data['post_type'] === 'offer' && ! in_array( 'author', $supports ) ) {
					$supports[] = 'author';
				}
				
				$args = array(
					'labels' => $post_type_data['labels'],
					'public' => $post_type_data['public'],
					'publicly_queryable' => $post_type_data['publicly_queryable'],
					'show_ui' => $post_type_data['show_ui'],
					'show_in_menu' => $post_type_data['show_in_menu'],
					'show_in_admin_bar' => $post_type_data['show_in_admin_bar'],
					'show_in_nav_menus' => $post_type_data['show_in_nav_menus'],
					'show_in_rest' => $post_type_data['show_in_rest'],
					'rest_base' => $post_type_data['rest_base'],
					'rest_namespace' => $post_type_data['rest_namespace'],
					'rest_controller_class' => $post_type_data['rest_controller_class'],
					'menu_position' => $post_type_data['menu_position'],
					'menu_icon' => $post_type_data['menu_icon']['value'],
					'capability_type' => 'post',
					'capabilities' => array(),
					'map_meta_cap' => true,
					'hierarchical' => $post_type_data['hierarchical'],
					'supports' => $supports,
					'taxonomies' => $post_type_data['taxonomies'],
					'has_archive' => $post_type_data['has_archive'],
					'rewrite' => array(
						'slug' => $post_type_data['rewrite']['permalink_rewrite'],
						'with_front' => $post_type_data['rewrite']['with_front'] === '1',
						'feeds' => $post_type_data['rewrite']['feeds'] === '1',
						'pages' => $post_type_data['rewrite']['pages'] === '1',
					),
					'query_var' => $post_type_data['query_var'],
					'can_export' => $post_type_data['can_export'],
					'delete_with_user' => $post_type_data['delete_with_user'],
					'exclude_from_search' => $post_type_data['exclude_from_search'],
				);

				register_post_type( $post_type_data['post_type'], $args );
			}
		} else {
			// Fallback to hardcoded registration if JSON file doesn't exist
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
				'supports'              => array( 'title', 'thumbnail', 'author' ),
				'taxonomies'            => array( 'category' ),
				'show_in_rest'          => true,
			);

			register_post_type( 'offer', $args );
		}
		
		// Register ACF field groups for offers
		$this->register_offer_acf_fields();
	}

	/**
	 * Register ACF field groups for offers and businesses.
	 */
	private function register_offer_acf_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		// Register offers field groups
		$this->register_field_groups_from_json( 'docs/acf-export-2025-07-17.json', 'offer' );
		
		// Register business field groups
		$this->register_field_groups_from_json( 'docs/acf-export-2025-07-08.json', 'business' );
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

	/**
	 * Shortcode handler for [ndm_offer_form].
	 *
	 * Usage: [ndm_offer_form post_id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function offer_form_shortcode( $atts ) {
		if ( ! function_exists( 'acf_form' ) ) {
			return __( 'ACF Pro is required for this form.', 'nova-directory-manager' );
		}

		if ( ! is_user_logged_in() ) {
			return __( 'You must be logged in to create or edit offers.', 'nova-directory-manager' );
		}

		$current_user = wp_get_current_user();
		$allowed_roles = array( 'advertiser', 'business_owner' );
		if ( ! array_intersect( $allowed_roles, $current_user->roles ) ) {
			return __( 'You do not have permission to create or edit offers.', 'nova-directory-manager' );
		}

		$atts = shortcode_atts( array(
			'post_id' => '',
		), $atts );

		$post_id = absint( $atts['post_id'] );
		$editing = false;

		// If no post_id provided, try to get from URL parameter
		if ( ! $post_id && isset( $_GET['offer_id'] ) ) {
			$post_id = intval( $_GET['offer_id'] );
		}

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== 'offer' ) {
				return __( 'Offer not found.', 'nova-directory-manager' );
			}
			// Only allow editing if user is author or has manage_options
			if ( $post->post_author != $current_user->ID && ! current_user_can( 'manage_options' ) ) {
				return __( 'You do not have permission to edit this offer.', 'nova-directory-manager' );
			}
			$editing = true;
		} else {
			$post_id = 'new_post';
		}

		// Start output buffering
		ob_start();

		// Add ACF form head (for conditionals, tabs, etc.)
		acf_form_head();

		// Use the offer field group
		$field_groups = array( 'group_687447b887b7e' );

		if ( isset( $_GET['offer_submitted'] ) ) {
			echo '<div class="ndm-offer-success">' . esc_html__( 'Offer saved successfully!', 'nova-directory-manager' ) . '</div>';
		}

		// Output the form
		?>
		<div class="ndm-offer-form">
			<?php
			acf_form( array(
				'post_id' => $post_id,
				'post_title' => true,
				'post_content' => false,
				'field_groups' => $field_groups,
				'form_attributes' => array(
					'class' => 'ndm-acf-form'
				),
				'html_before_fields' => '<div class="ndm-form-notices"></div>',
				'html_after_fields' => '<div class="ndm-form-actions"></div>',
				'submit_value' => $editing ? __( 'Update Offer', 'nova-directory-manager' ) : __( 'Create Offer', 'nova-directory-manager' ),
				'updated_message' => __( 'Offer saved successfully!', 'nova-directory-manager' ),
				'new_post' => array(
					'post_type' => 'offer',
					'post_status' => 'pending',
					'post_author' => $current_user->ID,
				),
				'return' => add_query_arg( 'offer_submitted', '1', get_permalink() ),
			) );
			?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Conditionally hide the offer_business field for advertisers.
	 *
	 * @param array $field The ACF field array.
	 * @return array|null
	 */
	public function maybe_hide_offer_business_field( $field ) {
		if ( ! is_user_logged_in() ) {
			return $field;
		}
		$current_user = wp_get_current_user();
		if ( in_array( 'advertiser', $current_user->roles, true ) ) {
			// Hide the field for advertisers
			return null;
		}
		return $field;
	}

	/**
	 * Ensure ACF fields are always registered for offers, even if field group is disabled.
	 */
	public function ensure_offer_acf_fields() {
		// Prevent multiple calls to this method
		static $ensured = false;
		if ( $ensured ) {
			return;
		}
		$ensured = true;
		
		// Register the offer ACF fields
		$this->register_offer_acf_fields();
		
		// Also ensure fields are loaded on post edit screens
		if ( is_admin() && isset( $_GET['post'] ) && get_post_type( $_GET['post'] ) === 'offer' ) {
			// Force ACF to reload field groups
			acf_get_field_groups();
			error_log( 'NDM: Ensuring ACF fields for offer post: ' . $_GET['post'] );
		}
		
		// Debug: Check if we're on an offer post edit screen
		if ( is_admin() && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'offer' ) {
			error_log( 'NDM: On offer post type admin screen' );
		}
	}

	/**
	 * Register field groups from JSON file for a specific post type.
	 *
	 * @param string $json_file_path
	 * @param string $post_type
	 */
	private function register_field_groups_from_json( $json_file_path, $post_type ) {
		$json_file = NDM_PLUGIN_DIR . $json_file_path;
		if ( file_exists( $json_file ) ) {
			$json_content = file_get_contents( $json_file );
			$field_groups = json_decode( $json_content, true );
			
			if ( is_array( $field_groups ) ) {
				foreach ( $field_groups as &$field_group ) {
					// Ensure the field group is always active for our plugin
					$field_group['active'] = true;
					$field_group['local'] = 'json';
					$field_group['modified'] = time();
					// Ensure the location rule is correct for the specified post type
					if ( isset( $field_group['location'] ) && is_array( $field_group['location'] ) ) {
						foreach ( $field_group['location'] as &$location_group ) {
							foreach ( $location_group as &$rule ) {
								if ( isset( $rule['param'] ) && $rule['param'] === 'post_type' ) {
									$rule['value'] = $post_type;
								}
							}
						}
					}
					// --- Inject category selector for offers ---
					if ($post_type === 'offer') {
						$category_field = array(
							'key' => 'field_ndm_offer_category',
							'label' => 'Categories',
							'name' => 'ndm_offer_category',
							'type' => 'taxonomy',
							'taxonomy' => 'category',
							'field_type' => 'multi_select',
							'add_term' => 0,
							'save_terms' => 1,
							'load_terms' => 1,
							'return_format' => 'id',
							'multiple' => 1,
							'required' => 1,
							'instructions' => 'Select one or more categories for this offer.',
							'wrapper' => array('width' => '', 'class' => '', 'id' => ''),
						);
						// Insert after the business field (first field)
						array_splice($field_group['fields'], 1, 0, array($category_field));
					}
					// --- End inject ---
					acf_remove_local_field_group( $field_group['key'] );
					acf_add_local_field_group( $field_group );
					$this->save_field_group_to_database( $field_group );
					error_log( 'NDM: Registered ACF field group: ' . $field_group['key'] . ' for post type: ' . $post_type );
				}
			}
		} else {
			error_log( 'NDM: ACF JSON file not found: ' . $json_file );
		}
	}

	/**
	 * Save field group to database to ensure it's available in admin.
	 *
	 * @param array $field_group
	 */
	private function save_field_group_to_database( $field_group ) {
		global $wpdb;
		
		// Check if this field group already exists and is up to date
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acf-field-group' AND post_name = %s",
			$field_group['key']
		) );
		
		if ( $existing ) {
			// Check if the existing field group is identical to what we want to save
			$existing_meta = get_post_meta( $existing, '_acf_field_group', true );
			if ( $existing_meta && $existing_meta === $field_group ) {
				error_log( 'NDM: Field group already exists and is identical: ' . $field_group['key'] . ' (ID: ' . $existing . ')' );
				return $existing; // Return existing ID without creating duplicate
			}
			
			// Update existing field group instead of deleting and recreating
			$post_data = array(
				'ID'          => $existing,
				'post_title'  => $field_group['title'],
				'post_name'   => $field_group['key'],
				'post_type'   => 'acf-field-group',
				'post_status' => 'publish',
				'post_content' => '',
			);
			
			$post_id = wp_update_post( $post_data );
			error_log( 'NDM: Updated existing field group: ' . $field_group['key'] . ' (ID: ' . $existing . ')' );
		} else {
			// Create new field group post
			$post_data = array(
				'post_title'  => $field_group['title'],
				'post_name'   => $field_group['key'],
				'post_type'   => 'acf-field-group',
				'post_status' => 'publish',
				'post_content' => '',
			);
			
			$post_id = wp_insert_post( $post_data );
			error_log( 'NDM: Created new field group: ' . $field_group['key'] . ' (ID: ' . $post_id . ')' );
		}
		
		if ( $post_id && !is_wp_error( $post_id ) ) {
			// Save field group data as post meta
			update_post_meta( $post_id, '_acf_field_group', $field_group );
			
			// Save individual field data
			if ( isset( $field_group['fields'] ) && is_array( $field_group['fields'] ) ) {
				foreach ( $field_group['fields'] as $field ) {
					update_post_meta( $post_id, $field['key'], $field );
				}
			}
			
			// Clear ACF cache more thoroughly
			if ( function_exists( 'acf_get_cache' ) ) {
				$cache = acf_get_cache( 'acf_get_field_groups' );
				if ( $cache ) {
					$cache->flush();
				}
			}
			
			// Clear WordPress object cache for this post
			clean_post_cache( $post_id );
			
			return $post_id;
		}
		
		return false;
	}

	/**
	 * Clean up duplicate ACF field groups.
	 *
	 * @return int Number of duplicates removed
	 */
	private function cleanup_duplicate_acf_field_groups() {
		global $wpdb;
		
		$removed_count = 0;
		
		// Get all ACF field groups
		$field_groups = $wpdb->get_results(
			"SELECT ID, post_name, post_title FROM {$wpdb->posts} 
			WHERE post_type = 'acf-field-group' 
			ORDER BY post_name, ID"
		);
		
		$seen_keys = array();
		
		foreach ( $field_groups as $field_group ) {
			$key = $field_group->post_name;
			
			if ( in_array( $key, $seen_keys ) ) {
				// This is a duplicate, remove it
				wp_delete_post( $field_group->ID, true );
				$removed_count++;
				error_log( 'NDM: Removed duplicate field group: ' . $key . ' (ID: ' . $field_group->ID . ')' );
			} else {
				$seen_keys[] = $key;
			}
		}
		
		// Clear ACF cache after cleanup
		if ( function_exists( 'acf_get_cache' ) ) {
			$cache = acf_get_cache( 'acf_get_field_groups' );
			if ( $cache ) {
				$cache->flush();
			}
		}
		
		return $removed_count;
	}

	/**
	 * Add custom columns to business post type admin.
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_business_admin_columns( $columns ) {
		// Insert logo column after title
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( $key === 'title' ) {
				$new_columns['business_logo'] = __( 'Logo', 'nova-directory-manager' );
			}
		}
		return $new_columns;
	}

	/**
	 * Display custom columns in business post type admin.
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	public function display_business_admin_columns( $column, $post_id ) {
		if ( $column === 'business_logo' ) {
			$logo_id = get_field( 'business_logo', $post_id );
			
			if ( $logo_id ) {
				$logo_url = wp_get_attachment_image_url( $logo_id, 'thumbnail' );
				$logo_alt = get_post_meta( $logo_id, '_wp_attachment_image_alt', true );
				
				if ( $logo_url ) {
					echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $logo_alt ) . '" style="max-width: 50px; height: auto; border-radius: 4px;" />';
				} else {
					echo '<span style="color: #999; font-style: italic;">' . __( 'Image not found', 'nova-directory-manager' ) . '</span>';
				}
			} else {
				echo '<span style="color: #999; font-style: italic;">' . __( 'No logo', 'nova-directory-manager' ) . '</span>';
			}
		}
	}

	/**
	 * Blog post form shortcode for business owners.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function blog_post_form_shortcode( $atts ) {
		// Check if user is logged in and has business_owner role
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'You must be logged in to create blog posts.', 'nova-directory-manager' ) . '</p>';
		}

		$current_user = wp_get_current_user();
		if ( ! in_array( 'business_owner', $current_user->roles, true ) ) {
			return '<p>' . __( 'Only business owners can create blog posts.', 'nova-directory-manager' ) . '</p>';
		}

		// Get user's business to auto-assign category
		$user_businesses = get_posts( array(
			'post_type' => 'business',
			'author' => $current_user->ID,
			'posts_per_page' => 1,
			'post_status' => 'publish'
		) );

		if ( empty( $user_businesses ) ) {
			return '<p>' . __( 'You must have a published business to create blog posts.', 'nova-directory-manager' ) . '</p>';
		}

		$business = $user_businesses[0];
		$business_categories = wp_get_post_terms( $business->ID, 'category', array( 'fields' => 'ids' ) );

		// Enqueue WordPress editor scripts
		wp_enqueue_editor();
		wp_enqueue_media();

		// Handle form submission
		if ( isset( $_POST['ndm_blog_post_submit'] ) && wp_verify_nonce( $_POST['ndm_blog_post_nonce'], 'ndm_blog_post_nonce' ) ) {
			$post_title = sanitize_text_field( $_POST['ndm_blog_post_title'] ?? '' );
			$post_content = wp_kses_post( $_POST['ndm_blog_post_content'] ?? '' );
			$hero_image_id = intval( $_POST['ndm_blog_post_hero_image'] ?? 0 );

			if ( ! empty( $post_title ) && ! empty( $post_content ) ) {
				$post_data = array(
					'post_title' => $post_title,
					'post_content' => $post_content,
					'post_status' => 'draft',
					'post_type' => 'post',
					'post_author' => $current_user->ID,
					'post_category' => $business_categories
				);

				$post_id = wp_insert_post( $post_data );

				if ( $post_id && ! is_wp_error( $post_id ) ) {
					// Set featured image if provided
					if ( $hero_image_id > 0 ) {
						set_post_thumbnail( $post_id, $hero_image_id );
					}

					// Send notification to admin emails
					$this->send_blog_post_notification( $post_id, $current_user, $business );

					return '<div class="ndm-success"><p>' . __( 'Blog post created successfully! It has been saved as a draft and will be reviewed by an administrator.', 'nova-directory-manager' ) . '</p></div>';
				} else {
					return '<div class="ndm-error"><p>' . __( 'Error creating blog post. Please try again.', 'nova-directory-manager' ) . '</p></div>';
				}
			} else {
				return '<div class="ndm-error"><p>' . __( 'Please fill in all required fields.', 'nova-directory-manager' ) . '</p></div>';
			}
		}

		// Output the form
		ob_start();
		?>
		<div class="ndm-blog-post-form">
			<h3><?php _e( 'Create Blog Post', 'nova-directory-manager' ); ?></h3>
			<p><?php _e( 'Create a blog post that will be automatically assigned to your business category.', 'nova-directory-manager' ); ?></p>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'ndm_blog_post_nonce', 'ndm_blog_post_nonce' ); ?>
				
				<div class="ndm-form-field">
					<label for="ndm_blog_post_title"><?php _e( 'Post Title *', 'nova-directory-manager' ); ?></label>
					<input type="text" id="ndm_blog_post_title" name="ndm_blog_post_title" value="<?php echo esc_attr( $_POST['ndm_blog_post_title'] ?? '' ); ?>" required class="ndm-input" />
				</div>

				<div class="ndm-form-field">
					<label for="ndm_blog_post_hero_image"><?php _e( 'Hero Image', 'nova-directory-manager' ); ?></label>
					<div class="ndm-media-upload-field">
						<input type="hidden" id="ndm_blog_post_hero_image" name="ndm_blog_post_hero_image" value="<?php echo esc_attr( $_POST['ndm_blog_post_hero_image'] ?? '' ); ?>" />
						<div class="ndm-media-preview" id="ndm-media-preview">
							<?php if ( ! empty( $_POST['ndm_blog_post_hero_image'] ) ) : ?>
								<?php echo wp_get_attachment_image( $_POST['ndm_blog_post_hero_image'], 'medium' ); ?>
							<?php endif; ?>
						</div>
						<button type="button" class="ndm-button ndm-media-upload-btn" id="ndm-media-upload-btn">
							<?php _e( 'Select Image', 'nova-directory-manager' ); ?>
						</button>
						<button type="button" class="ndm-button ndm-media-remove-btn" id="ndm-media-remove-btn" style="display: none;">
							<?php _e( 'Remove Image', 'nova-directory-manager' ); ?>
						</button>
					</div>
				</div>

				<div class="ndm-form-field">
					<label for="ndm_blog_post_content"><?php _e( 'Post Content *', 'nova-directory-manager' ); ?></label>
					<div class="ndm-editor-container">
						<?php
						$editor_content = $_POST['ndm_blog_post_content'] ?? '';
						wp_editor( $editor_content, 'ndm_blog_post_content', array(
							'textarea_name' => 'ndm_blog_post_content',
							'media_buttons' => true,
							'textarea_rows' => 15,
							'editor_height' => 400,
							'teeny' => false,
							'tinymce' => array(
								'toolbar1' => 'formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
								'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
								'toolbar3' => '',
								'toolbar4' => ''
							),
							'quicktags' => true
						) );
						?>
					</div>
				</div>

				<div class="ndm-form-field">
					<p class="ndm-info">
						<?php _e( 'Your blog post will be automatically assigned to your business category and saved as a draft for admin review.', 'nova-directory-manager' ); ?>
					</p>
				</div>

				<div class="ndm-form-field">
					<input type="submit" name="ndm_blog_post_submit" value="<?php _e( 'Create Blog Post', 'nova-directory-manager' ); ?>" class="ndm-button ndm-button-primary" />
				</div>
			</form>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			var mediaUploader;
			
			$('#ndm-media-upload-btn').on('click', function(e) {
				e.preventDefault();
				
				if (mediaUploader) {
					mediaUploader.open();
					return;
				}
				
				mediaUploader = wp.media({
					title: '<?php _e( 'Select Hero Image', 'nova-directory-manager' ); ?>',
					button: {
						text: '<?php _e( 'Use this image', 'nova-directory-manager' ); ?>'
					},
					multiple: false
				});
				
				mediaUploader.on('select', function() {
					var attachment = mediaUploader.state().get('selection').first().toJSON();
					$('#ndm_blog_post_hero_image').val(attachment.id);
					$('#ndm-media-preview').html('<img src="' + attachment.sizes.medium.url + '" alt="' + attachment.title + '" style="max-width: 100%; height: auto;" />');
					$('#ndm-media-remove-btn').show();
				});
				
				mediaUploader.open();
			});
			
			$('#ndm-media-remove-btn').on('click', function(e) {
				e.preventDefault();
				$('#ndm_blog_post_hero_image').val('');
				$('#ndm-media-preview').empty();
				$(this).hide();
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Send notification email to admin emails when a blog post is created.
	 *
	 * @param int $post_id
	 * @param WP_User $user
	 * @param WP_Post $business
	 */
	private function send_blog_post_notification( $post_id, $user, $business ) {
		$admin_emails = get_option( 'ndm_admin_emails', array() );
		
		if ( empty( $admin_emails ) ) {
			return;
		}

		$post = get_post( $post_id );
		$subject = sprintf( __( 'New Blog Post Created: %s', 'nova-directory-manager' ), $post->post_title );
		
		$message = sprintf(
			__( 'A new blog post has been created by a business owner:

Post Title: %s
Author: %s (%s)
Business: %s
Post ID: %d

View the post: %s
Edit the post: %s

The post is currently in draft status and requires admin review before publication.', 'nova-directory-manager' ),
			$post->post_title,
			$user->display_name,
			$user->user_email,
			$business->post_title,
			$post_id,
			get_edit_post_link( $post_id ),
			get_edit_post_link( $post_id )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		foreach ( $admin_emails as $email ) {
			wp_mail( $email, $subject, $message, $headers );
		}
	}

	/**
	 * Force ACF to reload field groups on offer and business post screens.
	 */
	public function force_acf_reload_on_offer_screens() {
		$current_screen = get_current_screen();
		if ( $current_screen && in_array( $current_screen->post_type, array( 'offer', 'business' ) ) ) {
			// Only register fields once per screen load to prevent duplicates
			static $registered = false;
			if ( ! $registered ) {
				$this->register_offer_acf_fields();
				$registered = true;
			}
			
			// Clear ACF cache
			if ( function_exists( 'acf_get_cache' ) ) {
				$cache = acf_get_cache( 'acf_get_field_groups' );
				if ( $cache ) {
					$cache->flush();
				}
			}
			
			// Force ACF to reload field groups
			acf_get_field_groups();
			
			error_log( 'NDM: Forced ACF reload on ' . $current_screen->post_type . ' screen: ' . $current_screen->id );
		}
	}

	/**
	 * Force field groups to be included when ACF loads field groups.
	 *
	 * @param array $field_groups
	 * @return array
	 */
	public function force_offer_field_groups( $field_groups ) {
		// Check if we're on an offer or business post type
		$current_screen = get_current_screen();
		if ( $current_screen && in_array( $current_screen->post_type, array( 'offer', 'business' ) ) ) {
			// Ensure our field groups are included
			$this->register_offer_acf_fields();
			
			// Get the appropriate field group from our JSON
			$json_file = '';
			if ( $current_screen->post_type === 'offer' ) {
				$json_file = NDM_PLUGIN_DIR . 'docs/acf-export-2025-07-17.json';
			} elseif ( $current_screen->post_type === 'business' ) {
				$json_file = NDM_PLUGIN_DIR . 'docs/acf-export-2025-07-08.json';
			}
			
			if ( file_exists( $json_file ) ) {
				$json_content = file_get_contents( $json_file );
				$our_field_groups = json_decode( $json_content, true );
				
				if ( is_array( $our_field_groups ) ) {
					foreach ( $our_field_groups as $field_group ) {
						$field_group['active'] = true;
						$field_group['local'] = 'json';
						
						// Add our field group to the list
						$field_groups[] = $field_group;
					}
				}
			}
		}
		
		return $field_groups;
	}

	/**
	 * Ensure offer has the correct author assigned.
	 *
	 * @param int $post_id
	 */
	public function ensure_offer_author( $post_id ) {
		if ( get_post_type( $post_id ) !== 'offer' ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		
		$current_user = wp_get_current_user();
		$post = get_post( $post_id );
		
		// Only set author if it's not already set or if the current user is the author
		if ( ! $post->post_author || $post->post_author == $current_user->ID ) {
			// Update the post author
			wp_update_post( array(
				'ID' => $post_id,
				'post_author' => $current_user->ID,
			) );
			
			error_log( 'NDM: Set offer author: ' . $post_id . ' -> ' . $current_user->ID );
		}
	}

	/**
	 * On offer save, if business owner, copy category terms from selected business to offer.
	 *
	 * @param int $post_id
	 */
	public function sync_offer_category_from_business( $post_id ) {
		if ( get_post_type( $post_id ) !== 'offer' ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}
		$current_user = wp_get_current_user();
		if ( in_array( 'business_owner', $current_user->roles, true ) ) {
			$business_id = get_field( 'offer_business', $post_id );
			if ( $business_id ) {
				$business_terms = wp_get_post_terms( $business_id, 'category', array( 'fields' => 'ids' ) );
				if ( ! empty( $business_terms ) ) {
					wp_set_post_terms( $post_id, $business_terms, 'category', false );
				}
			}
		}
	}
}

// Initialize the plugin.
Nova_Directory_Manager::get_instance(); 