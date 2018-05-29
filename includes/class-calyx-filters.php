<?php
/**
 * Container for global filters.
 */

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Global filters.
 */
class Calyx_Filters {
	use Calyx_Singleton;

	/**
	 * Construct.
	 */
	protected function __construct() {
		do_action( 'qm/start', __METHOD__ . '()' );

		add_filter( 'http_request_args', array( &$this, 'http_request_args' ), 10, 2 );

		do_action( 'qm/stop', __METHOD__ . '()' );
	}

	/**
	 * Filter: http_request_args
	 *
	 * @param array  $args
	 * @param string $url
	 *
	 * @return array
	 */
	function http_request_args( $args, $url ) {
		if ( false !== strpos( $url, 'http://api.wordpress.org/themes/update-check' ) )
			return $args; // Not a theme update request. Bail immediately.

		if (
			is_array( $args )
			&& count( $args )
			&& array_key_exists( 'themes', $args )
			&& is_array( $args['themes'] )
			&& count( $args['themes'] )
			&& array_key_exists( 'themes', $args['body'] )
		) {
			$args['body']['themes'] = json_decode( $args['body']['themes'] );
			list( $template, $stylesheet ) = array( get_option( 'template' ), get_option( 'stylesheet' ) );
			unset( $args['body']['themes']->themes->$template, $args['body']['themes']->themes->$stylesheet );
			$args['body']['themes'] = json_encode( $args['body']['themes'] );
		}

		return $args;
	}

}

?>