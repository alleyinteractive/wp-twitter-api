<?php

/**
* Similar to WP_Query, but for tweets
*/
class TAPI_Query {

	public $query_args;

	public $defaults;

	public $path;

	public $tweets = array();

	public $tweet;

	public $current_tweet = -1;

	public $tweet_count = 0;

	public $in_the_loop = false;

	function __construct( $args = array() ) {
		$this->parse_query( $args );
		$this->query();
	}

	public function parse_query( $args ) {
		$this->query_args = wp_parse_args( $args, array(
			'type' => 'user_timeline'
		) );
		$this->_resolve_type();
	}

	private function _resolve_type() {
		switch ( $this->query_args['type'] ) {
			case 'retweets' :
			case 'show' :
				$this->path = "statuses/{$this->query_args['type']}/{$this->query_args['id']}";
				unset( $this->query_args['id'] );
				break;

			case 'search' :
				$this->path = 'search/tweets';
				break;

			case 'list_timeline' :
				$this->path = 'lists/statuses';
				break;

			default :
				$this->path = 'statuses/' . $this->query_args['type'];
				break;
		}
		unset( $this->query_args['type'] );
	}


	public function query() {
		$this->tweets = WP_Twitter_API()->get( $this->path, $this->query_args );
		if ( is_array( $this->tweets ) && ! empty( $this->tweets ) ) {
			foreach ( $this->tweets as &$tweet ) {
				$tweet = new TAPI_Tweet( $tweet );
			}
		}
		$this->tweet_count = count( $this->tweets );
	}


	/**
	 * Set up the next tweet and iterate current tweet index.
	 *
	 * @access public
	 *
	 * @return TAPI_Tweet Next tweet.
	 */
	public function next_tweet() {
		$this->current_tweet++;

		$this->tweet = $this->tweets[ $this->current_tweet ];
		return $this->tweet;
	}


	/**
	 * Sets up the current tweet.
	 *
	 * Retrieves the next tweet, sets up the tweet, sets the 'in the loop'
	 * property to true.
	 *
	 * @access public
	 * @uses $tweet
	 */
	public function the_tweet() {
		global $tapi_tweet;
		$this->in_the_loop = true;

		$tapi_tweet = $this->next_tweet();
		tapi_setup_tweetdata( $tapi_tweet );
	}


	/**
	 * Whether there are more tweets available in the loop.
	 *
	 * Calls action 'loop_end', when the loop is complete.
	 *
	 * @access public
	 *
	 * @return bool True if tweets are available, false if end of loop.
	 */
	public function have_tweets() {
		if ( $this->current_tweet + 1 < $this->tweet_count ) {
			return true;
		} elseif ( $this->current_tweet + 1 == $this->tweet_count && $this->tweet_count > 0 ) {
			// Do some cleaning up after the loop
			$this->rewind_tweets();
		}

		$this->in_the_loop = false;
		return false;
	}


	/**
	 * Rewind the tweets and reset tweet index.
	 *
	 * @access public
	 */
	public function rewind_tweets() {
		$this->current_tweet = -1;
		if ( $this->tweet_count > 0 ) {
			$this->tweet = $this->tweets[0];
		}
	}

}


/**
 * Simply get an array of tweets
 *
 * @param array $args
 * @return array
 */
function tapi_get_tweets( $args ) {
	$query = new TAPI_Query( $args );
	return $query->tweets;
}


/**
 * Simply get an array of tweets
 *
 * @param array $args
 * @return array
 */
function tapi_query_tweets( $args ) {
	$GLOBALS['TAPI_Query'] = new TAPI_Query( $args );
}


/*
 * The Loop. tweet loop control.
 */

/**
 * Whether current TAPI query has results to loop over.
 *
 * @see TAPI_Query::have_tweets()
 * @uses $TAPI_Query
 *
 * @return bool
 */
function tapi_have_tweets() {
	global $TAPI_Query;

	return $TAPI_Query->have_tweets();
}

/**
 * Whether the caller is in the Loop.
 *
 * @uses $TAPI_Query
 *
 * @return bool True if caller is within loop, false if loop hasn't started or ended.
 */
function tapi_in_the_loop() {
	global $TAPI_Query;

	return $TAPI_Query->in_the_loop;
}

/**
 * Rewind the loop tweets.
 *
 * @see TAPI_Query::rewind_tweets()
 * @uses $TAPI_Query
 *
 * @return null
 */
function tapi_rewind_tweets() {
	global $TAPI_Query;

	return $TAPI_Query->rewind_tweets();
}

/**
 * Iterate the tweet index in the loop.
 *
 * @see TAPI_Query::the_tweet()
 * @uses $TAPI_Query
 */
function tapi_the_tweet() {
	global $TAPI_Query;

	$TAPI_Query->the_tweet();
}


/**
 * Set up global tweet data.
 *
 * @param object $tweet tweet data.
 * @uses do_action_ref_array() Calls 'the_tweet'
 * @return bool True when finished.
 */
function tapi_setup_tweetdata( $tweet ) {
	global $tapi_tweet_id;

	$tapi_tweet_id = 2147483647 == PHP_INT_MAX ? $tweet->id_str : $tweet->id;

	return true;
}