<?php

/**
 * Interact with Twitter's API in a WordPress-centric manner
 *
 * @package Twitter API for WordPress
 */

if ( !class_exists( 'WP_Twitter_API' ) ) :

class WP_Twitter_API {

	/** @type string The  */
	const HANDLE = 'tafwp';

	/** @type object Store the class singleton */
	private static $instance;

	/** @type bool|object Store the twitteroauth object, or false if it hasn't been initialized yet */
	public $api = false;

	/** @type int How long to store transients */
	public $cache_length;

	/** @type array Twitter authentication keys */
	private $options;

	private function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() { wp_die( "Please don't __clone WP_Twitter_API" ); }

	public function __wakeup() { wp_die( "Please don't __wakeup WP_Twitter_API" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new WP_Twitter_API;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/**
	 * Setup class defaults
	 *
	 * @return void
	 */
	public function setup() {
		$this->cache_length = 10 * MINUTE_IN_SECONDS;
	}

	/**
	 * Connect to Twitter via Oauth and initialize the $api variable
	 *
	 * @return object
	 */
	public function connect() {
		if ( !$this->api ) {
			$this->options = (array) get_option( self::HANDLE, array() );
			if ( !class_exists( 'TAPI_OAuthRequest' ) ) {
				require_once( 'twitter_oauth/OAuth.php' );
			}
			if ( !class_exists( 'TwitterOAuth' ) ) {
				require_once( 'twitter_oauth/twitteroauth.php' );
			}
			if ( isset( $this->options['temp_oauth_token'], $this->options['temp_oauth_token_secret'] ) )
				$this->api = new TwitterOAuth( $this->options['consumer_key'], $this->options['consumer_secret'], $this->options['temp_oauth_token'], $this->options['temp_oauth_token_secret'] );
			elseif ( isset( $this->options['oauth_token'], $this->options['oauth_token_secret'] ) )
				$this->api = new TwitterOAuth( $this->options['consumer_key'], $this->options['consumer_secret'], $this->options['oauth_token'], $this->options['oauth_token_secret'] );
			else
				$this->api = new TwitterOAuth( $this->options['consumer_key'], $this->options['consumer_secret'] );
			$this->api->host = "https://api.twitter.com/1.1/";
			$this->api->connecttimeout = 5;
			$this->api->timeout = 5;
		}
		return $this->api;
	}


	/**
	 * If necessary (e.g. during the authentication setup), drop the existing connection and start a new one
	 *
	 * @return object
	 */
	public function reconnect() {
		$this->api = false;
		return $this->connect();
	}


	/**
	 * Get an oauth token and redirect URL to use during the authentication process
	 *
	 * @return array
	 */
	public function get_oauth_token() {
		$this->connect();
		$ret = array();
		$ret['request_token'] = $this->api->getRequestToken( admin_url( 'options-general.php?page=tafwp' ) );
		$ret['redirect_url'] = $this->api->getAuthorizeURL( $ret['request_token'], false );
		return $ret;
	}


	/**
	 * Verify that we have an authenticated user
	 *
	 * @return object
	 */
	public function verify_credentials() {
		$this->connect();
		return $this->api->get( 'account/verify_credentials' );
	}


	/**
	 * Get an access token from an oauth verifier
	 *
	 * @param string $verifier OAuth verifier token
	 * @return array
	 */
	public function get_access_token( $verifier ) {
		$this->connect();
		return $this->api->getAccessToken( $verifier );
	}


	/**
	 * Run a GET request on the Twitter API and cache results in a transient.
	 * See {@link https://dev.twitter.com/docs/api/1.1} for all possible GET requests.
	 *
	 * @param string $url The API endpoint, e.g. statuses/user_timeline
	 * @param string|array $params Optional. The GET params to send. Can either be an associative array or GET string
	 * @param string|array $defaults Optional. A set of defaults which $params with override
	 * @return mixed
	 */
	public function get( $url, $params = array(), $defaults = array() ) {
		$params = wp_parse_args( $params, $defaults );
		$cache_key = 'tafwp_' . md5( $url . serialize( $params ) );
		if ( false === ( $response = get_transient( $cache_key ) ) ) {
			$this->connect();
			$response = $this->api->get( $url, $params );
			set_transient( $cache_key, $response, $this->cache_length );
		}
		return $response;
	}


	/**
	 * Get a user timeline
	 *
	 * @param array|string $params An array (or args string) of params to send with the GET request. See {@link https://dev.twitter.com/docs/api/1.1/get/statuses/user_timeline}.
	 * @return array
	 */
	public function get_user_timeline( $params = array() ) {
		return $this->get( 'statuses/user_timeline', $params, array(
			'count'       => 20,
			'include_rts' => 1
		) );
	}


	/**
	 * Get a timeline for a list
	 *
	 * @param array|string $params An array (or args string) of params to send with the GET request. See {@link https://dev.twitter.com/docs/api/1.1/get/lists/statuses}.
	 * @return array
	 */
	public function get_list_timeline( $params = array() ) {
		return $this->get( 'lists/statuses', $params, array(
			'count'       => 20,
			'include_rts' => 1
		) );
	}

}


/**
 * Get the class singleton
 *
 * @return object
 */
function WP_Twitter_API() {
	return WP_Twitter_API::instance();
}
add_action( 'after_setup_theme', 'WP_Twitter_API' );

endif;