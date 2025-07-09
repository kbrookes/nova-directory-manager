<?php
/**
 * Admin page template for Nova Directory Manager
 *
 * @package NovaDirectoryManager
 * @since 1.0.0
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'ndm_messages' ); ?>

	<div class="ndm-admin-container">
		<div class="ndm-admin-content">
			<form method="post" action="">
				<?php wp_nonce_field( 'ndm_settings_nonce', 'ndm_nonce' ); ?>
				
				<h2><?php esc_html_e( 'User Role Configuration', 'nova-directory-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="user_role_name">
									<?php esc_html_e( 'Role Name', 'nova-directory-manager' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="user_role_name" 
									name="user_role_name" 
									value="<?php echo esc_attr( $this->settings['user_role_name'] ?? 'business_owner' ); ?>" 
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'The internal name for the user role (e.g., business_owner).', 'nova-directory-manager' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="user_role_display_name">
									<?php esc_html_e( 'Display Name', 'nova-directory-manager' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="user_role_display_name" 
									name="user_role_display_name" 
									value="<?php echo esc_attr( $this->settings['user_role_display_name'] ?? 'Business Owner' ); ?>" 
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'The display name for the user role (e.g., Business Owner).', 'nova-directory-manager' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'Fluent Forms Integration', 'nova-directory-manager' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="fluent_form_id">
									<?php esc_html_e( 'Registration Form', 'nova-directory-manager' ); ?>
								</label>
							</th>
							<td>
								<select id="fluent_form_id" name="fluent_form_id">
									<option value=""><?php esc_html_e( 'Select a form', 'nova-directory-manager' ); ?></option>
									<?php foreach ( $fluent_forms as $form_id => $form_title ) : ?>
										<option value="<?php echo esc_attr( $form_id ); ?>" <?php selected( $this->settings['fluent_form_id'] ?? 0, $form_id ); ?>>
											<?php echo esc_html( $form_title ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the Fluent Form that handles business registration.', 'nova-directory-manager' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="post_type">
									<?php esc_html_e( 'Post Type', 'nova-directory-manager' ); ?>
								</label>
							</th>
							<td>
								<select id="post_type" name="post_type">
									<?php foreach ( $post_types as $post_type => $post_type_label ) : ?>
										<option value="<?php echo esc_attr( $post_type ); ?>" <?php selected( $this->settings['post_type'] ?? 'business', $post_type ); ?>>
											<?php echo esc_html( $post_type_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select the post type for business listings.', 'nova-directory-manager' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="category_field">
									<?php esc_html_e( 'Category Field', 'nova-directory-manager' ); ?>
								</label>
							</th>
							<td>
								<input 
									type="text" 
									id="category_field" 
									name="category_field" 
									value="<?php echo esc_attr( $this->settings['category_field'] ?? 'business_category' ); ?>" 
									class="regular-text"
								/>
								<p class="description">
									<?php esc_html_e( 'The field name in your Fluent Form that contains the category selection (e.g., business_category).', 'nova-directory-manager' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'User Role Capabilities', 'nova-directory-manager' ); ?></h2>
				<p><?php esc_html_e( 'Configure what the business owner role can do:', 'nova-directory-manager' ); ?></p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Capabilities', 'nova-directory-manager' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text">
										<?php esc_html_e( 'User Role Capabilities', 'nova-directory-manager' ); ?>
									</legend>
									<?php
									$capabilities = array(
										'read' => __( 'Read posts and pages', 'nova-directory-manager' ),
										'edit_posts' => __( 'Edit their own posts', 'nova-directory-manager' ),
										'delete_posts' => __( 'Delete their own posts', 'nova-directory-manager' ),
										'publish_posts' => __( 'Publish posts', 'nova-directory-manager' ),
										'upload_files' => __( 'Upload files', 'nova-directory-manager' ),
										'edit_published_posts' => __( 'Edit published posts', 'nova-directory-manager' ),
										'delete_published_posts' => __( 'Delete published posts', 'nova-directory-manager' ),
									);

									$current_capabilities = $this->settings['user_role_capabilities'] ?? array();

									foreach ( $capabilities as $capability => $label ) :
										$checked = isset( $current_capabilities[ $capability ] ) && $current_capabilities[ $capability ];
									?>
										<label>
											<input 
												type="checkbox" 
												name="user_role_capabilities[<?php echo esc_attr( $capability ); ?>]" 
												value="1" 
												<?php checked( $checked ); ?>
											/>
											<?php echo esc_html( $label ); ?>
										</label><br>
									<?php endforeach; ?>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'nova-directory-manager' ) ); ?>
			</form>
			
			<hr>
			<h3><?php esc_html_e( 'Debug & Testing', 'nova-directory-manager' ); ?></h3>
			<p><?php esc_html_e( 'Click the button below to test the plugin functionality and check error logs.', 'nova-directory-manager' ); ?></p>
			<a href="<?php echo admin_url( 'admin.php?action=test_ndm' ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Test Plugin', 'nova-directory-manager' ); ?>
			</a>
			<a href="<?php echo admin_url( 'admin.php?action=test_ndm_fields' ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Test Form Fields', 'nova-directory-manager' ); ?>
			</a>
		</div>

		<div class="ndm-admin-sidebar">
			<div class="ndm-info-box">
				<h3><?php esc_html_e( 'Plugin Information', 'nova-directory-manager' ); ?></h3>
				<p>
					<strong><?php esc_html_e( 'Version:', 'nova-directory-manager' ); ?></strong> 
					<?php echo esc_html( NDM_VERSION ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Status:', 'nova-directory-manager' ); ?></strong> 
					<?php 
					// Get the plugin instance to check Fluent Forms status
					$plugin = Nova_Directory_Manager::get_instance();
					$fluent_forms_active = $plugin->is_fluent_forms_active();
					?>
					<?php if ( $fluent_forms_active ) : ?>
						<span class="ndm-status-active"><?php esc_html_e( 'Fluent Forms Active', 'nova-directory-manager' ); ?></span>
					<?php else : ?>
						<span class="ndm-status-inactive"><?php esc_html_e( 'Fluent Forms Required', 'nova-directory-manager' ); ?></span>
					<?php endif; ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Available Forms:', 'nova-directory-manager' ); ?></strong> 
					<?php echo count( $fluent_forms ); ?>
				</p>
			</div>

			<div class="ndm-help-box">
				<h3><?php esc_html_e( 'How It Works', 'nova-directory-manager' ); ?></h3>
				<ol>
					<li><?php esc_html_e( 'Configure your user role and form settings above.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'When a user submits the registration form, a user account is created.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'A business listing post is created in draft status.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'The user is automatically assigned as the author of the post.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'The selected category is assigned to the post.', 'nova-directory-manager' ); ?></li>
				</ol>
			</div>

			<div class="ndm-help-box">
				<h3><?php esc_html_e( 'Requirements', 'nova-directory-manager' ); ?></h3>
				<ul>
					<li><?php esc_html_e( 'Fluent Forms plugin must be installed and activated.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'Your form must have User Registration enabled.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'Your form must have Post Creation enabled.', 'nova-directory-manager' ); ?></li>
					<li><?php esc_html_e( 'The category field must be a dynamic field or select field.', 'nova-directory-manager' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</div> 