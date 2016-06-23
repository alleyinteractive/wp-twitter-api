<?php

/**
 * A class for managing the plugin's settings and the twitter authentication
 *
 * @package Twitter API for WordPress
 */
class WP_Twitter_API_Settings {

	/** @type string The permission tier required for accessing the options page */
	public $options_capability = 'manage_options';


	/** @type array Variable for storing and updating the site options */
	public $options = array();


	/** @type bool|string If the plugin is authorized, stores the screen name of the authenticated user. Otherwise, false. */
	public $authorized_to = false;


	/** @type string The handle for the plugin. Used in storing the options, namespacing the language, etc. */
	const HANDLE = 'tafwp';


	/** @type object Singleton container */
	protected static $instance;

	/**
	 * Does nothing
	 *
	 * @return void
	 */
	protected function __construct() {
		/** Don't do anything **/
	}

	/**
	 * Return the singleton
	 *
	 * @return object
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WP_Twitter_API_Settings;
			self::$instance->setup_actions();
		}
		return self::$instance;
	}


	/**
	 * Hook into the necessary WP actions
	 *
	 * @return void
	 */
	protected function setup_actions() {
		# Standard settings/options routines
		add_action( 'admin_init', array( self::$instance, 'action_admin_init' ) );
		add_action( 'admin_menu', array( self::$instance, 'action_admin_menu' ) );

		# Handle the Authentication process if necessary
		add_action( 'admin_init', array( self::$instance, 'load_options' ), 4 );
		add_action( 'admin_init', array( self::$instance, 'authenticate' ), 5 );
	}

	/**
	 * Load the options on demand
	 *
	 * @return void
	 */
	public function load_options() {
		if ( !$this->options )
			$this->options = (array) get_option( self::HANDLE, array(
			'consumer_key' => '',
			'consumer_secret' => ''
		) );
	}


	/**
	 * Control the authentication process.
	 *
	 * @return void
	 */
	public function authenticate() {
		# 1. Initialize authentication
		# 2. Get temp access token
		# 3. Redirect user
		# 4. Receive back verifier
		# 5. Get new access token
		# 6. Test
		if ( isset( $_POST['tafwp_authenticate'] ) ) {
			$auth = WP_Twitter_API()->get_oauth_token();
			if ( !isset( $auth['request_token'], $auth['redirect_url'] ) )
				return;

			# save token values
			$this->options['temp_oauth_token'] = $auth['request_token']['oauth_token'];
			$this->options['temp_oauth_token_secret'] = $auth['request_token']['oauth_token_secret'];
			if ( false === update_option( self::HANDLE, $this->options ) )
				return;

			# Redirect to Twitter to let the user sign in and verify this app
			wp_redirect( $auth['redirect_url'] );
			exit;
		} elseif ( isset( $_GET['oauth_token'], $_GET['oauth_verifier'], $this->options['temp_oauth_token'] ) && $this->options['temp_oauth_token'] == $_GET['oauth_token'] ) {
			# Get access token
			$token_credentials = WP_Twitter_API()->get_access_token( $_GET['oauth_verifier'] );

			# Store new credentials and reconnect
			unset( $this->options['temp_oauth_token'], $this->options['temp_oauth_token_secret'] );
			$this->options['oauth_token'] = $token_credentials['oauth_token'];
			$this->options['oauth_token_secret'] = $token_credentials['oauth_token_secret'];
			update_option( self::HANDLE, $this->options );
			WP_Twitter_API()->reconnect();

			# Test that all is working
			$content = WP_Twitter_API()->verify_credentials();
			if ( is_object( $content ) && !empty( $content->screen_name ) ) {
				$this->authorized_to = $content->screen_name;
			}
		} elseif ( isset( $_GET['page'] ) && self::HANDLE == $_GET['page'] ) {
			# Test this this is working and load the authorized user
			$content = WP_Twitter_API()->verify_credentials();
			if ( is_object( $content ) && !empty( $content->screen_name ) ) {
				$this->authorized_to = $content->screen_name;
			}
		}
	}

	/**
	 * Leverage the Settings API for our settings page
	 *
	 * @return void
	 */
	public function action_admin_init() {
		register_setting( self::HANDLE, self::HANDLE, array( self::$instance, 'sanitize_options' ) );
		add_settings_section( 'general', false, '__return_false', self::HANDLE );
		add_settings_field( 'consumer_key', __( 'Consumer Key:', self::HANDLE ), array( self::$instance, 'field' ), self::HANDLE, 'general', array( 'label_for' => 'consumer_key' ) );
		add_settings_field( 'consumer_secret', __( 'Consumer Secret:', self::HANDLE ), array( self::$instance, 'field' ), self::HANDLE, 'general', array( 'label_for' => 'consumer_secret' ) );
	}


	/**
	 * Hook our options page into the WP Menu
	 *
	 * @return void
	 */
	public function action_admin_menu() {

		add_options_page( __( 'Twitter API for WordPress', self::HANDLE ), __( 'Twitter API', self::HANDLE ), $this->options_capability, self::HANDLE, array( self::$instance, 'view_settings_page' ) );
	}


	/**
	 * Output an input field via the Settings API
	 *
	 * @param array $args
	 * @return void
	 */
	public function field( $args ) {
		echo '<input type="text" name="' .  esc_attr( self::HANDLE . '[' . $args['label_for'] . ']' ) . '" id="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $this->options[ $args['label_for'] ] ) .'" size="60" style="font-family:monospace;font-size:120%;letter-spacing:0.1em" />';
	}


	/**
	 * Sanitize the data from the settings form
	 *
	 * @param array $in
	 * @return array
	 */
	public function sanitize_options( $in ) {
		$out = array(
			'consumer_key'    => preg_replace( '/[^a-z0-9]/i', '', $in['consumer_key'] ),
			'consumer_secret' => preg_replace( '/[^a-z0-9]/i', '', $in['consumer_secret'] )
		);
		return $out;
	}


	/**
	 * Display the settings page
	 *
	 * @return void
	 */
	public function view_settings_page() {
	?><div class="wrap">
		<h2><?php _e( 'Twitter API for WordPress', self::HANDLE ); ?></h2>
		<p><?php _e( 'Enter your consumer key and secret from <a href="https://dev.twitter.com/apps" target="_blank">https://dev.twitter.com/apps</a> (be sure to set a callback URL when adding your app):', self::HANDLE ); ?></p>
		<form action="options.php" method="post">
			<?php settings_fields( self::HANDLE ); ?>
			<?php do_settings_sections( self::HANDLE ); ?>
			<?php submit_button(); ?>
		</form>

		<?php if ( $this->options['consumer_key'] && $this->options['consumer_secret'] ) : ?>

			<?php if ( !$this->authorized_to ) : ?>

				<h2><?php _e( 'Authentication Necessary', self::HANDLE ); ?></h2>
				<p><?php _e( "To complete the Twitter setup, you'll need to authenticate this application to use your Twitter account to make API requests", self::HANDLE ); ?></p>
				<form method="post">
					<input type="submit" name="tafwp_authenticate" value="Authenticate with Twitter" class="button-secondary" />
				</form>

			<?php else : ?>

				<p><?php printf( __( "Looks like you're good to go! You're authorized as %s", self::HANDLE ), '<strong><a href="https://twitter.com/' . esc_attr( $this->authorized_to ) . '" target="_blank">@' . esc_html( $this->authorized_to ) . '</a></strong>' ) ?></p>

			<?php endif ?>

		<?php endif ?>
	</div>
	<?php
	}

}


/**
 * Get the class singleton
 *
 * @return object
 */
function WP_Twitter_API_Settings() {
	return WP_Twitter_API_Settings::instance();
}
add_action( 'after_setup_theme', 'WP_Twitter_API_Settings' );
