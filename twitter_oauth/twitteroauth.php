<?php

if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC. '/class-http.php' );

/*
 * Abraham Williams (abraham@abrah.am) http://abrah.am
 * WordPressified by Matt Boynes and Alexi Maschas, http://www.alleyinteractive.com
 */

/**
 * Twitter OAuth class
 */
class TwitterOAuth {

	/* Contains the last HTTP status code returned. */
	public $http_code;

	/* Contains the last API call. */
	public $url;

	/* Set up the API root URL. */
	public $host = "https://api.twitter.com/1/";

	/* Set timeout default. */
	public $timeout = 30;

	/* Set connect timeout. */
	public $connecttimeout = 30;

	/* Verify SSL Cert. */
	public $ssl_verifypeer = FALSE;

	/* Respons format. */
	public $format = 'json';

	/* Decode returned json data. */
	public $decode_json = TRUE;

	/* Contains the last HTTP headers returned. */
	public $http_header;

	/* Set the useragnet. */
	public $useragent = 'TwitterOAuth v0.2.0-beta2';


	/**
	 * Set API URLS
	 */
	function accessTokenURL()  { return 'https://api.twitter.com/oauth/access_token'; }
	function authenticateURL() { return 'https://twitter.com/oauth/authenticate'; }
	function authorizeURL()    { return 'https://twitter.com/oauth/authorize'; }
	function requestTokenURL() { return 'https://api.twitter.com/oauth/request_token'; }

	/**
	 * Debug helpers
	 */
	function lastStatusCode() { return $this->http_status; }
	function lastAPICall() { return $this->last_api_call; }

	/**
	 * construct TwitterOAuth object
	 */
	function __construct( $consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL ) {
		$this->sha1_method = new TAPI_OAuthSignatureMethod_HMAC_SHA1();
		$this->consumer = new TAPI_OAuthConsumer( $consumer_key, $consumer_secret );
		if ( !empty( $oauth_token ) && !empty( $oauth_token_secret ) ) {
			$this->token = new TAPI_OAuthConsumer( $oauth_token, $oauth_token_secret );
		} else {
			$this->token = NULL;
		}
	}


	/**
	 * Get a request_token from Twitter
	 *
	 * @returns a key/value array containing oauth_token and oauth_token_secret
	 */
	function getRequestToken( $oauth_callback = NULL ) {
		$parameters = array();
		if ( !empty( $oauth_callback ) ) {
			$parameters['oauth_callback'] = $oauth_callback;
		}
		$request = $this->oAuthRequest( $this->requestTokenURL(), 'GET', $parameters );
		$token = TAPI_OAuthUtil::parse_parameters( $request );
		$this->token = new TAPI_OAuthConsumer( $token['oauth_token'], $token['oauth_token_secret'] );
		return $token;
	}

	/**
	 * Get the authorize URL
	 *
	 * @returns a string
	 */
	function getAuthorizeURL( $token, $sign_in_with_twitter = TRUE ) {
		if ( is_array( $token ) ) {
			$token = $token['oauth_token'];
		}
		if ( empty( $sign_in_with_twitter ) ) {
			return $this->authorizeURL() . "?oauth_token={$token}";
		} else {
			 return $this->authenticateURL() . "?oauth_token={$token}";
		}
	}

	/**
	 * Exchange request token and secret for an access token and
	 * secret, to sign API calls.
	 *
	 * @returns array("oauth_token" => "the-access-token",
	 *                "oauth_token_secret" => "the-access-secret",
	 *                "user_id" => "9436992",
	 *                "screen_name" => "abraham")
	 */
	function getAccessToken( $oauth_verifier = FALSE ) {
		$parameters = array();
		if ( !empty( $oauth_verifier ) ) {
			$parameters['oauth_verifier'] = $oauth_verifier;
		}
		$request = $this->oAuthRequest( $this->accessTokenURL(), 'GET', $parameters );
		$token = TAPI_OAuthUtil::parse_parameters( $request );
		$this->token = new TAPI_OAuthConsumer( $token['oauth_token'], $token['oauth_token_secret'] );
		return $token;
	}

	/**
	 * One time exchange of username and password for access token and secret.
	 *
	 * @returns array("oauth_token" => "the-access-token",
	 *                "oauth_token_secret" => "the-access-secret",
	 *                "user_id" => "9436992",
	 *                "screen_name" => "abraham",
	 *                "x_auth_expires" => "0")
	 */
	function getXAuthToken( $username, $password ) {
		$parameters = array();
		$parameters['x_auth_username'] = $username;
		$parameters['x_auth_password'] = $password;
		$parameters['x_auth_mode'] = 'client_auth';
		$request = $this->oAuthRequest( $this->accessTokenURL(), 'POST', $parameters );
		$token = TAPI_OAuthUtil::parse_parameters( $request );
		$this->token = new TAPI_OAuthConsumer( $token['oauth_token'], $token['oauth_token_secret'] );
		return $token;
	}

	/**
	 * GET wrapper for oAuthRequest.
	 */
	function get( $url, $parameters = array() ) {
		$response = $this->oAuthRequest( $url, 'GET', $parameters );
		if ( 'json' === $this->format && $this->decode_json ) {
			return json_decode( $response );
		}
		return $response;
	}

	/**
	 * POST wrapper for oAuthRequest.
	 */
	function post( $url, $parameters = array() ) {
		$response = $this->oAuthRequest( $url, 'POST', $parameters );
		if ( 'json' === $this->format && $this->decode_json ) {
			return json_decode( $response );
		}
		return $response;
	}

	/**
	 * DELETE wrapper for oAuthReqeust.
	 */
	function delete( $url, $parameters = array() ) {
		$response = $this->oAuthRequest( $url, 'DELETE', $parameters );
		if ( 'json' === $this->format && $this->decode_json ) {
			return json_decode( $response );
		}
		return $response;
	}

	/**
	 * Format and sign an OAuth / API request
	 */
	function oAuthRequest( $url, $method, $parameters ) {
		if ( strrpos( $url, 'https://' ) !== 0 && strrpos( $url, 'http://' ) !== 0 ) {
			$url = "{$this->host}{$url}.{$this->format}";
		}
		$request = TAPI_OAuthRequest::from_consumer_and_token( $this->consumer, $this->token, $method, $url, $parameters );
		$request->sign_request( $this->sha1_method, $this->consumer, $this->token );
		switch ( $method ) {
			case 'GET':
				return $this->http( $request->to_url(), 'GET' );
			default:
				return $this->http( $request->get_normalized_http_url(), $method, $request->to_postdata() );
		}
	}

	/**
	 * Make an HTTP request
	 *
	 * @return API results
	 */
	function http( $url, $method, $postfields = NULL ) {
		$result = wp_remote_request( $url, array(
			'method'     => $method,
			'user-agent' => $this->useragent,
			'headers'    => array( 'Expect:' ),
			'body'       => $postfields,
			'sslverify'  => $this->ssl_verifypeer,
		) );
		if ( ! is_wp_error( $result ) ) {
			$this->http_code = $result['response']['code'];
			$this->http_header = $result['headers'];
			$this->url = $url;

			return $result['body'];
		}
		return '[]';
	}

}
