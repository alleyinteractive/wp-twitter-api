<?php

/*
	Plugin Name: Twitter API for WordPress
	Plugin URI: http://www.alleyinteractive.com/
	Description: A plugin to interact with the Twitter API. For developers, by developers.
	Version: 0.1
	Author: Matthew Boynes, Alley Interactive
	Author URI: http://www.alleyinteractive.com/
*/
/*  This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


# Setup site options and authentication

define( 'TAPI_PATH', dirname( __FILE__ ) );
define( 'TAPI_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

require_once( TAPI_PATH . '/settings.php' );

# Our meat and potatoes
require_once( TAPI_PATH . '/class-wp-twitter-api.php' );

# A class for adding functionality to tweets
require_once( TAPI_PATH . '/class-tapi-tweet.php' );

# Like WP_Query, but for tweets
require_once( TAPI_PATH . '/class-tapi-query.php' );

# Helper functions
require_once( TAPI_PATH . '/functions.php' );

