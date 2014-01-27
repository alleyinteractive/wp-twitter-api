=== Twitter API for WordPress ===
Contributors: mboynes, alleyinteractive, narnold1
Tags: twitter, api, rest, oauth
Requires at least: 3.5
Tested up to: 3.6
Stable tag: 0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Access the Twitter API in a WordPress-friendly manner.

== Description ==

This plugins provides easy access to the Twitter API for WordPress developers. What sets it apart from other, similar plugins? It has a loop.

Imagine the WordPress classes and functions you know and love for interacting with posts, like WP_Query, get_posts(), and get_the_ID(). Now imagine you could access Twitter data using similar classes and functions. And imagine that you could get a list of tweets and process them in a loop the same way you do posts. Now imagine you weren't merely imagining it.

`
tapi_query_tweets( 'screen_name=senyob&count=10' );
if ( tapi_have_tweets() ) : ?>
	<ol class="tweets">
		<?php while ( tapi_have_tweets() ) : tapi_the_tweet(); ?>
			<li class="tweet">
				<p><?php echo tapi_get_text() ?></p>
				<a class="tweet-time" href="<?php echo tapi_get_permalink() ?>"><?php echo tapi_get_timestamp( 'age' ) ?> ago</a>
			</li>
		<?php endwhile ?>
	</ol>
<?php endif ?>
`

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create an app at https://dev.twitter.com/apps. *Be sure to populate the callback URL field* (the callback URL can simply be your website)
4. Go to **Settings &rarr; Twitter API**
5. Enter your app's Consumer Key and Consumer Secret and click "Save Changes"
6. Authorize the app with your Twitter account

Now you should be all set to use the API!


== Frequently Asked Questions ==

= How is this different from all the other Twitter plugins? =

If you're a WordPress developer, you should just try it out, it was built just for you. It provides very simple access to the REST API, caches the responses, and provides helper functions for looping through and outputting the data in a way that's familiar to any WordPress developer. It gets out of your way so you can output Tweets using your HTML.

= Where's can I read more about ________? =

Check out our wiki.

= Does this use the 1.1 API? =

Do not fear, this is built around the 1.1 API.

