<?php

/**
 * Plugin Name: CFW Additional Headers
 * Plugin URI: https://github.com/ssnepenthe/clockwork-for-wp
 * Description: An example plugin for adding additional clockwork headers.
 * Version: 0.1.0
 * Author: Ryan McLaughlin
 * Author URI: https://github.com/ssnepenthe
 * License: MIT
 */

\add_action( 'cfw_config_init', function( $config ) {
	$config->set(
		'requests.except',
		\array_merge( [ 'sample-page' ], $config->get( 'requests.except', [] ) )
	);
	$config->set( 'headers', [
		'Apples' => 'Bananas',
		'Cats' => 'Dogs',
	] );
} );