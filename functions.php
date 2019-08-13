<?php

/**
 * Helper functions
 *
 * @package Twitter API for WordPress
 */


/**
 * Helper function for returning the n latest items from a given user's timeline
 *
 * @param string $screen_name The Twitter handle
 * @param int $count Optional. The number of tweets to retrieve
 * @return array
 */
function tapi_get_user_timeline( $screen_name, $count = 20 ) {
	return WP_Twitter_API()->get_user_timeline( "screen_name={$screen_name}&count={$count}" );
}


/**
 * Return the latest $count tweets from any number of screen names, in reverse chronological order.
 *
 * @param array $screen_names An array of twitter handles.
 * @param int $count Optional. The number of tweets to retrieve.
 * @param int $cache_length Optional. Default is 10 minutes. Notes that this has no effect on WP_Twitter_API, which will cache each user's tweets individually.
 * @return array
 */
function tapi_get_merged_user_timelines( $screen_names = array(), $count = 20, $cache_length = -1 ) {
	if ( 0 > $cache_length )
		$cache_length = 10 * MINUTE_IN_SECONDS;
	$cache_key = 'tafwp_' . md5( $count . ',' . implode( ',', $screen_names ) );
	if ( false === ( $tweets = get_transient( $cache_key ) ) ) {
		$tweets = array();
		foreach ( $screen_names as $screen_name ) {
			$tweets = array_merge( $tweets, tapi_get_user_timeline( $screen_name, $count ) );
		}
		usort( $tweets, 'tapi_sort_merged_tweets' );
		$tweets = array_slice( $tweets, 0, $count );
		return $tweets;
		set_transient( $cache_key, $tweets, $cache_length );
	}
	return $tweets;
}


/**
 * usort function to sort the merged tweets by date
 *
 * @param object $a
 * @param object $b
 * @return int
 */
function tapi_sort_merged_tweets( $a, $b ) {
	if ( $a->created_at == $b->created_at )
		return 0;
	return strtotime( $a->created_at ) < strtotime( $b->created_at ) ? 1 : -1;
}


/**
 * Helper function for returning the n latest items from a given user's timeline
 *
 * @param int|string $list Either the list id or the list slug
 * @param string $owner Optional. The Twitter handle of the list owner, rquired if $list is the slug
 * @param int $count Optional. The number of tweets to retrieve
 * @return array
 */
function tapi_get_list_timeline( $list, $owner = false, $count = 20 ) {
	if ( is_numeric( $list ) )
		$list = 'list_id=' . $list;
	else
		$list = "slug=$list&owner_screen_name=$owner";
	return WP_Twitter_API()->get_list_timeline( "count={$count}&{$list}" );
}


function tapi_get_tweet( $tweet = false, $args = array() ) {
	if ( $tweet )
		return TAPI_Tweet::get_instance( $tweet, $args );
	else
		return $GLOBALS['tapi_tweet'];
}


function tapi_get_tweet_id( $tweet = false ) {
	if ( ! $tweet )
		return $GLOBALS['tapi_tweet_id'];
	return $tweet->id;
}


function tapi_get_author( $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	return $tweet->user;
}


function tapi_get_author_name( $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	return $tweet->user->name;
}


function tapi_get_author_screen_name( $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	return $tweet->user->screen_name;
}


function tapi_get_author_avatar_url( $protocol = 'http', $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	return 'http' == $protocol ? $tweet->user->profile_image_url : $tweet->user->profile_image_url_https;
}


function tapi_get_author_permalink( $tweet = false ) {
	return "https://twitter.com/" . tapi_get_author_screen_name( $tweet );
}


function tapi_get_text( $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	if ( ! empty( $tweet->full_text ) ) {
		preg_match_all('/https:\/\/t.co\/[^\s]+/', $tweet->full_text, $matches );

		if ( ! empty( $matches[0] ) ) {
			$new_string = $tweet->full_text;

			// Loop through an replace matches.
			foreach ( $matches[0] as $match ) {
				$new_string = str_replace( $match, wp_kses_post( '<a href="' . $match . '" rel=”nofollow”>' . $match . '</a>' ),  $new_string );
			}

			return nl2br( $new_string );
		}
	}

	return nl2br( $tweet->full_text );
}


function tapi_get_timestamp( $format = 'raw', $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	if ( 'raw' == $format )
		return $tweet->created_at;

	$timestamp = strtotime( $tweet->created_at );

	if ( 'short' == $format )
		return date( 'j M y', $timestamp );

	if ( 'int' == $format )
		return $timestamp;

	if ( 'age' == $format )
		return tapi_get_relative_time( $tweet->created_at );

	return date( $format, $timestamp );
}


function tapi_get_permalink( $tweet = false ) {
	return "https://twitter.com/" . tapi_get_author_screen_name( $tweet ) . '/status/' . tapi_get_tweet_id( $tweet );
}

/**
 * Return relative time string from unix timestamp.
 *
 * @param string || int timestamp .ex Fri Aug 09 00:59:06 +0000 2019
 */
function tapi_get_relative_time( $timestamp = '' ) {

	// Bail if no timestamp exists.
	if ( empty( $timestamp ) ) {
		return;
	}

	// Setup new time.
	$now  = new \DateTime();
	$ago  = new \DateTime( $timestamp );
	$diff = $now->diff( $ago );

	// Round week down to nearest int.
	$diff->w = intval( floor( $diff->d / 7 ) );

	// Subtract day from week and set to var.
	$diff->d -= $diff->w * 7;

	// Set potential string output to array.
	$time_string_array = array(
		'y' => 'year',
		'm' => 'month',
		'w' => 'week',
		'd' => 'day',
		'h' => 'hour',
		'i' => 'minute',
		's' => 'second',
	);

	// Loop through each time.
	foreach ( $time_string_array as $key => &$time ) {
		// Set $spacetime key as plural,
		// else remove key from array if key isn't valid.
		if ( $diff->$key ) {
			$time = $diff->$key . ' ' . $time . ( $diff->$key > 1 ? 's' : '' );
		} else {
			unset( $time_string_array[ $key ] );
		}
	}

	// Get first element from array.
	$time_space = array_slice( $time_string_array, 0, 1 );

	// If we have a value assume past, and attache 'ago', else just now.
	$time_string = $time_space ? implode( ', ', $time_space ) . esc_html__( ' ago', 'tapi_tweets' ) : esc_html__( 'just now', 'tapi_tweets' );

	// Output markup.
	return $time_string;
}

/**
 * Get media for tweet.
 *
 * @param object $tweet tweet object.
 * @return array array of media if it exists.
 */
function tapi_get_media_url( $tweet = false ) {
	if ( ! $tweet )
		$tweet = $GLOBALS['tapi_tweet'];

	// Set empty array.
	$media_array = [];

	if ( isset( $tweet->entities ) && isset( $tweet->entities->media ) ) {

		foreach ( $tweet->entities->media as $media ) {
			$media_array[] = $media->media_url_https
				? $media->media_url_https
				: $media->media_url;

			return $media_array;
		}
	}

	return $media_array;
}
