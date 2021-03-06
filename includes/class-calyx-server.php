<?php
/**
 * Helper for server functions.
 */

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Class.
 */
class Calyx_Server {
	use Calyx_Singleton;

	/** @var null|Calyx_Server_Low_Traffic */
	protected $_low_traffic = null;

	/** @var array $_notices Array of notices for removed functionality. **/
	protected $_notices = array();

	/**
	 * Construct.
	 */
	protected function __construct() {
		$this->_low_traffic = Calyx_Server_Low_Traffic::create_instance();

		if ( $this->is_high_load() ) {
			add_action( 'admin_enqueue_scripts', array( &$this, '_enqueue_styles' ) );
			add_action( 'wp_enqueue_scripts', array( &$this, '_enqueue_styles' ) );
			add_action( 'admin_bar_menu', array( &$this, 'action__admin_bar_menu' ), 999 );
		}
	}

	public static function include_files() {
		include_once CALYX_ABSPATH . '/includes/class-calyx-server-low-traffic.php';
	}

	function low_traffic() {
		return $this->_low_traffic;
	}


	/*
	   ###     ######  ######## ####  #######  ##    ##  ######
	  ## ##   ##    ##    ##     ##  ##     ## ###   ## ##    ##
	 ##   ##  ##          ##     ##  ##     ## ####  ## ##
	##     ## ##          ##     ##  ##     ## ## ## ##  ######
	######### ##          ##     ##  ##     ## ##  ####       ##
	##     ## ##    ##    ##     ##  ##     ## ##   ### ##    ##
	##     ##  ######     ##    ####  #######  ##    ##  ######
	*/

	/**
	 * Hook: admin_bar_menu
	 *
	 * @param WP_Admin_bar $bar
	 */
	function action__admin_bar_menu( $bar ) {

		$bar->add_menu( array(
			'id'        => THEME_PREFIX . '-server-load',
			'parent'    => 'top-secondary',
			'title'     => '<span class="server-load-label"><span class="server-load-blink">SERVER LOAD: ' . ( $this->is_extreme_load() ? 'EXTREME' : 'HIGH' ) . '</span>' . ( count( $this->_notices ) ? ' <span style="font-size: 0.7em; opacity: 0.7;">(' . count( $this->_notices ) . ')</span>' : '' ) . '</span>',
		) );

		if ( !empty( $this->_notices ) )
			foreach ( $this->_notices as $i => $message )
				$bar->add_menu( array(
					'id'        => THEME_PREFIX . '-server-load--' . esc_attr( $i ),
					'parent'    => THEME_PREFIX . '-server-load',
					'title'     => wp_kses_post( $message ),
				) );
		else
			$bar->add_menu( array(
				'id'        => THEME_PREFIX . '-server-load--none',
				'parent'    => THEME_PREFIX . '-server-load',
				'title'     => '<em>No functionality disabled on this page</em>',
			) );

	}


	/*
	######## ##     ## ##    ##  ######  ######## ####  #######  ##    ##  ######
	##       ##     ## ###   ## ##    ##    ##     ##  ##     ## ###   ## ##    ##
	##       ##     ## ####  ## ##          ##     ##  ##     ## ####  ## ##
	######   ##     ## ## ## ## ##          ##     ##  ##     ## ## ## ##  ######
	##       ##     ## ##  #### ##          ##     ##  ##     ## ##  ####       ##
	##       ##     ## ##   ### ##    ##    ##     ##  ##     ## ##   ### ##    ##
	##        #######  ##    ##  ######     ##    ####  #######  ##    ##  ######
	*/

	/**
	 * Inline styles for admin bar menu item.
	 */
	protected function _inlineStyle_adminBar() {
		ob_start();
		?>

		#wp-admin-bar-<?php echo THEME_PREFIX ?>-server-load .server-load-label { color: red; }

			body.admin-color-sunrise #wp-admin-bar-calyx-server-load .server-load-label { color: #000; }

		#wp-admin-bar-<?php echo THEME_PREFIX ?>-server-load .server-load-blink {
			-webkit-animation: <?php echo THEME_PREFIX ?>-server-load-blink 1s steps(5, start) infinite;
			        animation: <?php echo THEME_PREFIX ?>-server-load-blink 1s steps(5, start) infinite;
		}

		@keyframes <?php echo THEME_PREFIX ?>-server-load-blink {
			to { visibility: hidden; }
		}

		@-webkit-keyframes <?php echo THEME_PREFIX ?>-server-load-blink {
			to { visibility: hidden; }
		}

		<?php
		return ob_get_clean();
	}

	/**
	 * Add inline styles for admin bar menu item.
	 *
	 * @uses $this::_inlineStyle_adminBar()
	 */
	function _enqueue_styles() {
		wp_add_inline_style( 'admin-bar', $this->_inlineStyle_adminBar() );
	}

	/**
	 * Get server info.
	 *
	 * 1 => Apache|nginx
	 * 2 => version
	 *
	 * @return array
	 */
	function get_info() {
		preg_match( '/^(nginx|Apache)\/([0-9\.]*).*$/', $_SERVER['SERVER_SOFTWARE'], $matches );
		return $matches;
	}

	/**
	 * Retrieve indication of server under normal load.
	 *
	 * @uses $this::is_high_load()
	 *
	 * @return bool
	 */
	function is_normal_load() {
		return !$this->is_high_load();
	}

	/**
	 * Retrieve indication of server under high load.
	 *
	 * @uses $this::is_extreme_load()
	 *
	 * @return bool
	 */
	function is_high_load() {
		return (
			(
				       defined( 'CALYX_HIGH_LOAD' )
				   && constant( 'CALYX_HIGH_LOAD' )
			)
			|| !!get_transient( 'CALYX_HIGH_LOAD' )
			|| !!get_option(    'CALYX_HIGH_LOAD' )
			|| $this->is_extreme_load()
		);
	}

	/**
	 * Retrieve indication of server under extreme load.
	 *
	 * @return bool
	 */
	function is_extreme_load() {
		 return (
			(
				       defined( 'CALYX_EXTREME_LOAD' )
				   && constant( 'CALYX_EXTREME_LOAD' )
			)
			|| !!get_transient( 'CALYX_EXTREME_LOAD' )
			|| !!get_option(    'CALYX_EXTREME_LOAD' )
		);
	}

	/**
	 * Add notice to indicate removed functionality (due to server load).
	 *
	 * @param array|string $messages Message(s) to add.
	 */
	function add_notices( $messages ) {
		if ( !is_array( $messages ) )
			$messages = array( $messages );

		$this->_notices = array_merge( $this->_notices, $messages );
	}

	/**
	 * Alias for add_notices().
	 *
	 * @param array|string $message Message to add.
	 *
	 * @uses $this::add_notices()
	 */
	function add_notice( $message ) {
		$this->add_notices( $message );
	}

	/**
	 * Check if production environment.
	 *
	 * If no indication, default true.
	 *
	 * @return bool
	 */
	function is_production() {
		static $_cache = null;

		if ( !is_null( $_cache ) )
			return $_cache;

		return $_cache = apply_filters( THEME_PREFIX . '/server/is_production', (
			!defined( 'CALYX_PRODUCTION_URL' )
			|| site_url() === CALYX_PRODUCTION_URL
		) );
	}

	/**
	 * Check if development environment.
	 *
	 * @uses $this::is_production()
	 *
	 * @return bool
	 */
	function is_development() {
		static $_cache = null;

		if ( !is_null( $_cache ) )
			return $_cache;

		return $_cache = apply_filters( THEME_PREFIX . '/server/is_development', (
			!$this->is_production()
			&& (
				(
					defined( 'WP_LOCAL_DEV' )
					&& WP_LOCAL_DEV
				)
				|| (
					defined( 'CALYX_DEVELOPMENT_URL' )
					&& site_url() === CALYX_DEVELOPMENT_URL
				)
			)
		) );
	}

}

add_action( THEME_PREFIX . '/include_files/after_core', array( 'Calyx_Server', 'include_files' ) );

?>
