<?php
/*
Plugin Name: Contact Form to Agile
Plugin URI: https://wordpress.org/plugins/cf2agile
Description: Contact Form to Agile is a powerful Add-on for Contact Form 7 plugin which extends the standard features of the contact form by adding new fields to interact with Active Collab.
Author: UpQode
Author URI: https://upqode.com/
Text Domain: cf2agile
Version: 1.0.0
*/

define( 'CF2AGILE_DIR', dirname( __FILE__ ) );

class cf2agile {

	/**
	 * Register plugin actions and filters
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
	    add_action( 'plugins_loaded', array( $this, 'require_plugin_files' ) );
	    add_action( 'wpcf7_before_send_mail', array( $this, 'before_send_mail' ) );
	    add_action( 'wp_ajax_cf2agile--settings-activecollab', array( $this, 'ajax_save_activecollab_settings' ) );
	}

	/**
	 * Add plugin menu pages
	 */
	public function add_plugin_page() {
		add_options_page( __( 'Contact Form to Agile', 'cf2agile' ), __( 'Contact Form to Agile', 'cf2agile' ), 'manage_options', 'cf2agile', array( $this, 'plugin_page_callback' ) );
	}

	/**
	 * Render general settings page
	 */
	public function plugin_page_callback() {
		ob_start(); ?>

		<div class="wrap">
			<h1><?php _e( 'Contact Form to Agile', 'cf2agile' ); ?></h1>

			<?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'true' ): ?>
				<div class="notice updated">
					<p><strong><?php _e( 'Your settings have been updated!', 'cf2agile' ); ?></strong></p>
				</div>
			<?php endif; ?>
			<?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'false' ): ?>
				<div class="notice error">
					<p><strong><?php _e( 'Your settings have not been saved!', 'cf2agile' ); ?></strong></p>
				</div>
			<?php endif; ?>

			<h2><?php _e( 'ActiveCollab Access Settings', 'cf2agile' ); ?></h2>
			<form action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="cf2agile--settings-activecollab">
				<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'cf2agile' ); ?>">

				<table class="form-table">
					<tbody>
					<tr>
						<th>
							<label for="your-org-name"><?php _e( 'Your Org Name', 'cf2agile' ); ?></label>
						</th>
						<td>
							<input id="your-org-name" class="regular-text" type="text" name="your_org_name" placeholder="<?php _e( 'ACME Inc', 'cf2agile' ); ?>" value="<?php echo esc_attr( get_option( 'cf2agile__activecollab__org_name' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th>
							<label for="your-app-name"><?php _e( 'Your App Name', 'cf2agile' ); ?></label>
						</th>
						<td>
							<input id="your-app-name" class="regular-text" type="text" name="your_app_name" placeholder="<?php _e( 'My Awesome Application', 'cf2agile' ); ?>" value="<?php echo esc_attr( get_option( 'cf2agile__activecollab__app_name' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th>
							<label for="email-or-username"><?php _e( 'Email or Username', 'cf2agile' ); ?></label>
						</th>
						<td>
							<input id="email-or-username" class="regular-text" type="text" name="email_or_username" placeholder="<?php _e( 'you@acmeinc.com', 'cf2agile' ); ?>" value="<?php echo esc_attr( get_option( 'cf2agile__activecollab__username' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th>
							<label for="password"><?php _e( 'Password', 'cf2agile' ); ?></label>
						</th>
						<td>
							<input id="password" class="regular-text" type="password" name="password" placeholder="<?php _e( 'Enter password...', 'cf2agile' ); ?>" value="<?php echo esc_attr( get_option( 'cf2agile__activecollab__password' ) ); ?>">
						</td>
					</tr>
					<tr>
						<th>
							<label for="self-hosted-url"><?php _e( 'Self Hosted Url', 'cf2agile' ); ?></label>
						</th>
						<td>
							<input id="self-hosted-url" class="regular-text" type="text" name="self_hosted_url" placeholder="<?php _e( 'https://my.company.com/projects', 'cf2agile' ); ?>" value="<?php echo esc_attr( get_option( 'cf2agile__activecollab__self_url' ) ); ?>">
						</td>
					</tr>
					</tbody>
				</table>

				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'cf2agile' ); ?>">
				</p>
			</form>
		</div>

		<?php echo ob_get_clean();
	}

	/**
	 * Include need plugin files
	 */
	public function require_plugin_files() {
	    require_once CF2AGILE_DIR . '/api/cf2agile_API_ActiveCollab.php';

	    if ( class_exists( 'WPCF7' ) ) {
		    require_once CF2AGILE_DIR . '/fields/cf2agile_Field.php';
		    require_once CF2AGILE_DIR . '/fields/cf2agile_Field_Budget.php';
		    require_once CF2AGILE_DIR . '/fields/cf2agile_Field_ProjectName.php';
		    require_once CF2AGILE_DIR . '/fields/cf2agile_Field_CompanyName.php';
		    require_once CF2AGILE_DIR . '/fields/cf2agile_Field_CustomerEmail.php';
	    }
	}

	/**
     * Create project in Agile system before send form email
     *
	 * @param WPCF7_ContactForm $contact_form
     * @return WPCF7_ContactForm
	 */
	public function before_send_mail( $contact_form ) {
		/** @var WPCF7_FormTag[] $form_tags */
	    $form_tags = $contact_form->scan_form_tags();
	    $submission = WPCF7_Submission::get_instance();
		$project_name = $project_budget = $company_name = $customer_email = false;
		$note_body = '';

		foreach ( $form_tags as $form_tag ) {
	        if ( 'cf2agile__project_name' === $form_tag->basetype ) {
		        $project_name = sanitize_text_field( $submission->get_posted_data( $form_tag->name ) );
            } elseif ( 'cf2agile__project_budget' === $form_tag->basetype ) {
	            $project_budget = floatval( $submission->get_posted_data( $form_tag->name ) );
            } elseif ( 'cf2agile__company_name' === $form_tag->basetype ) {
		        $company_name = sanitize_text_field( $submission->get_posted_data( $form_tag->name ) );
            } elseif ( 'cf2agile__customer_email' === $form_tag->basetype ) {
		        $customer_email = is_email( $submission->get_posted_data( $form_tag->name ) )
			        ? sanitize_email( $submission->get_posted_data( $form_tag->name ) )
			        : sanitize_text_field( $submission->get_posted_data( $form_tag->name ) );
            } elseif ( $form_tag->name ) {
	            $key = ( ! empty( $form_tag->labels ) ) ? (string) reset( $form_tag->labels ) : $form_tag->name;
	            $value = sanitize_text_field( $submission->get_posted_data( $form_tag->name ) );

	            $note_body .= "<p><strong>{$key}</strong>: {$value}</p>";
            }
        }

        if ( $project_name ) {  // create project in ActiveCollab
	        $project_id = cf2agile_API_ActiveCollab::get_instance()->create_project( $project_name, $company_name, $project_budget, $customer_email );

	        if ( $project_id ) {
	            cf2agile_API_ActiveCollab::get_instance()->add_note_in_project( $project_id, $project_name, $note_body );
            }
        }

	    return $contact_form;
    }

	/**
	 * AJAX: save access to ActiveCollab
	 */
	public function ajax_save_activecollab_settings() {
		$query_args = array( 'page' => 'cf2agile', 'status' => 'false' );

		if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'], 'cf2agile' ) ) {
			$query_args['status'] = 'true';

			if ( ! empty( $_POST['your_org_name'] ) ) {
				update_option( 'cf2agile__activecollab__org_name', sanitize_text_field( $_POST['your_org_name'] ) );
			}
			if ( ! empty( $_POST['your_app_name'] ) ) {
				update_option( 'cf2agile__activecollab__app_name', sanitize_text_field( $_POST['your_app_name'] ) );
			}
			if ( ! empty( $_POST['email_or_username'] ) ) {
			    $username = is_email( $_POST['email_or_username'] ) ? sanitize_email( $_POST['email_or_username'] ) : sanitize_text_field( $_POST['email_or_username'] );
				update_option( 'cf2agile__activecollab__username', $username );
			}
			if ( ! empty( $_POST['password'] ) ) {
				update_option( 'cf2agile__activecollab__password', sanitize_text_field( $_POST['password'] ) );
			}
			if ( ! empty( $_POST['self_hosted_url'] ) ) {
				update_option( 'cf2agile__activecollab__self_url', esc_url_raw( $_POST['self_hosted_url'] ) );
			}
		}

		wp_safe_redirect( esc_url( add_query_arg( $query_args, admin_url( 'options-general.php' ) ) ) ); exit;
	}

}

new cf2agile();
