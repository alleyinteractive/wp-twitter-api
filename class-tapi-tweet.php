<?php

/**
* Class for handling tweets, and essentially adding functionality to them
*/
class TAPI_Tweet {
	public $tweet = null;

	public $filtered_text = '';

	public $verified = false;

	function __construct( $tweet ) {
		$this->tweet = $tweet;
	}


	/**
	 * Magic method for property overloading. This allows us to access the tweet properties as usual
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->tweet->$name;
	}


	/**
	 * Magic method for property overloading. This allows us to set the tweet properties as usual
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	public function __set( $name, $value ) {
		$this->tweet->$name = $value;
	}


	/**
	 * Ensure that we have a tweet object available
	 *
	 * @return bool
	 */
	public function is_verified() {
		if ( !$this->verified && $this->tweet && is_object( $this->tweet ) )
			$this->verified = true;

		return $this->verified;
	}


	/**
	 * Get tweet text
	 *
	 * @param string $state Optional. If "filtered", adds links to tweet text for mentions, hashtags, etc.
	 * @return string|bool
	 */
	public function text( $state = 'filtered' ) {
		if ( ! $this->is_verified() )
			return false;

		if ( 'raw' == $state )
			return $this->tweet->text;
		else
			return $this->filtered_text();
	}


	/**
	 * We need to do substring replacements on the tweets, but the text is UTF-8 and multiple bytes cause issues
	 *
	 * @param string $string
	 * @param string $replacement
	 * @param int $start
	 * @param int $length
	 * @return string
	 */
	private function _mb_substr_replace( $string, $replacement, $start, $length ) {
		return mb_substr( $string, 0, $start ) . $replacement . mb_substr( $string, $start + $length );
	}


	/**
	 * Filter the Twitter api response to replace hashtags, urls, user mentions, and media links with
	 *
	 * @param array $media Required. Array of media entity objects in a tweet
	 * @return array $parsed_entities. Array of parsed media entity objects in tweet
	 */
	public function filtered_text() {
		if ( ! $this->filtered_text ) {

			# initialize an empty array to hold the parsed entities
			$entities = array();

			if ( ! empty( $this->tweet->entities->hashtags ) ) # parse the hashtags
				$entities = $entities + $this->parse_hashtag( $this->tweet->entities->hashtags );

			if ( ! empty( $this->tweet->entities->urls ) ) # parse the urls
				$entities = $entities + $this->parse_url_link( $this->tweet->entities->urls );

			if ( ! empty( $this->tweet->entities->user_mentions ) ) # parse the user mentions
				$entities = $entities + $this->parse_user_mention( $this->tweet->entities->user_mentions );

			if ( ! empty( $this->tweet->entities->media ) ) # parse the media links
				$entities = $entities + $this->parse_media_link( $this->tweet->entities->media );

			# because we're using the location index of the substring to begin the replacement, we must reverse the order and work backwards.
			krsort( $entities );

			# replace the entities in the tweet text with the parsed versions
			$this->filtered_text = $this->tweet->text;
			foreach ( $entities as $entity ) {
				$this->filtered_text = $this->_mb_substr_replace( $this->filtered_text, $entity->replace, $entity->start, $entity->length );
			}

		}

		# return the processed tweet text
		return $this->filtered_text;
	}


	/**
	 * Parse the tweet hashtags
	 *
	 * @param array $hashtags Required. Array of hashtag entity objects in a tweet
	 * @return array $parsed_entities. Array of parsed hashtag entity objects in tweet
	 */
	public function parse_hashtag( $hashtags ) {
		$parsed_entities = array();
		$hashtag_link_pattern = '<a href="http://twitter.com/search?q=%%23%s&src=hash" rel="nofollow" target="_blank">#%s</a>';
		foreach( $hashtags as $hashtag ) {
			$entity = new stdclass();
			$entity->start = $hashtag->indices[0];
			$entity->length = $hashtag->indices[1] - $hashtag->indices[0];
			$entity->replace = sprintf( $hashtag_link_pattern, esc_attr( strtolower( $hashtag->text ) ), esc_html( $hashtag->text ) );
			# use the start index as the array key for sorting purposes
			$parsed_entities[ $entity->start ] = $entity;
		}
		return $parsed_entities;
	}


	/**
	 * Parse the tweet url links
	 *
	 * @param array $urls Required. Array of url entity objects in a tweet
	 * @return array $parsed_entities. Array of parsed url entity objects in tweet
	 */
	public function parse_url_link( $urls ) {
		$parsed_entities = array();
		$url_link_pattern = '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>';
		foreach( $urls as $url ) {
			$entity = new stdclass();
			$entity->start = $url->indices[0];
			$entity->length = $url->indices[1] - $url->indices[0];
			$entity->replace = sprintf( $url_link_pattern, esc_url( $url->url ), esc_attr( $url->expanded_url ), esc_html( $url->display_url ) );
			# use the start index as the array key for sorting purposes
			$parsed_entities[ $entity->start ] = $entity;
		}
		return $parsed_entities;
	}


	/**
	 * Parse the tweet user mentions
	 *
	 * @param array $user_mentions Required. Array of user mention entity objects in a tweet
	 * @return array $parsed_entities. Array of parsed user mention entity objects in tweet
	 */
	public function parse_user_mention( $user_mentions ) {
		$parsed_entities = array();
		$user_mention_link_pattern = '<a href="http://twitter.com/%s" rel="nofollow" target="_blank" title="%s">@%s</a>';
		foreach( $user_mentions as $user_mention ) {
			$entity = new stdclass();
			$entity->start = $user_mention->indices[0];
			$entity->length = $user_mention->indices[1] - $user_mention->indices[0];
			$entity->replace = sprintf( $user_mention_link_pattern, esc_attr( strtolower( $user_mention->screen_name ) ), esc_attr( $user_mention->name ), esc_html( $user_mention->screen_name ) );
			# use the start index as the array key for sorting purposes
			$parsed_entities[ $entity->start ] = $entity;
		}
		return $parsed_entities;
	}


	/**
	 * Parse the tweet media links
	 *
	 * @param array $media Required. Array of media entity objects in a tweet
	 * @return array $parsed_entities. Array of parsed media entity objects in tweet
	 */
	public function parse_media_link( $media ) {
		$parsed_entities = array();
		$media_link_pattern = '<a href="%s" rel="nofollow" target="_blank" title="%s">%s</a>';
		foreach( $media as $mediaitem ) {
			$entity = new stdclass();
			$entity->start = $mediaitem->indices[0];
			$entity->length = $mediaitem->indices[1] - $mediaitem->indices[0];
			$entity->replace = sprintf( $media_link_pattern, esc_url( $mediaitem->url ), esc_attr( $mediaitem->expanded_url ), esc_html( $mediaitem->display_url ) );
			# use the start index as the array key for sorting purposes
			$parsed_entities[ $entity->start ] = $entity;
		}
		return $parsed_entities;
	}

	/**
	 * Twitter timestamp format
	 */
	public function age() {
		$time = strtotime( $this->created_at );
		$secs = time() - $time;

		if ( $secs < 60 )
			return round( $secs ) . 's';

		if ( $secs / 60 < 60 )
			return round( $secs / 60 ) . 'm';

		if ( $secs / 3600 < 24 )
			return round( $secs / 3600 ) . 'h';

		if ( $secs / DAY_IN_SECONDS < 365 )
			return date( 'j M', $time );

		return date( 'j M y', $time );
	}


	public static function get_instance( $tweet, $args = array() ) {
		$tapi_obj = null;

		if ( is_object( $tweet ) ) {
			if ( is_a( $tweet, 'TAPI_Tweet' ) )
				return $tweet;

			$tapi_obj = wp_cache_get( $tweet->id_str, 'tapi_tweets' );
		} elseif ( is_numeric( $tweet ) ) {
			$tapi_obj = wp_cache_get( $tweet, 'tapi_tweets' );

			if ( !$tapi_obj ) {
				$tweet = WP_Twitter_API()->get( 'statuses/show/' . $tweet, $args );
			}
		}

		if ( ! $tapi_obj && is_object( $tweet ) ) {
			$tapi_obj = new TAPI_Tweet( $tweet );
			if ( isset( $tweet->retweeted_status ) && is_object( $tweet->retweeted_status ) )
				$tapi_obj->retweeted_status = new TAPI_Tweet( $tweet->retweeted_status );
			wp_cache_set( $tweet->id_str, $tapi_obj, 'tapi_tweets', MINUTE_IN_SECONDS );
			return $tapi_obj;
		}

		return $tapi_obj;
	}

}
