<?php
/**
 * Enhance WordPress enqueues.
 */

/**
 * Class to enhance WordPress enqueues.
 *
 * Possible properties: critical, noscript, preload, inline.
 */
class CSSLLC_EnhanceEnqueues {

	/**
	 * @var array $_enhancements Reference list of enhancements by asset type.
	 */
	private $_enhancements = array(
		'script' => array(
			'preload',
			'critical',
			'inline',
		),
		'style' => array(
			'preload',
			'critical',
			'noscript',
			'inline',
		),
	);

	/**
	 * Construct.
	 */
	function __construct() {

		add_filter(  'style_loader_tag', array( &$this, 'filter__style_loader_tag'  ), 999, 4 );
		add_filter( 'script_loader_tag', array( &$this, 'filter__script_loader_tag' ), 999, 3 );

		add_action( 'wp_head',            array( &$this, 'action__wp_head'                   ), 5 );
		// add_action( 'wp_enqueue_scripts', array( &$this, '_debug_action__wp_enqueue_scripts' ) );

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
	 * Action: wp_head
	 *
	 * - print rel="preload" link tags
	 */
	function action__wp_head() {

		# Preload stylesheets
		$stylesheets = array_filter( wp_styles()->registered, function( $stylesheet ) {
			return (
				array_key_exists( 'preload', $stylesheet->extra )
				&& !empty( $stylesheet->extra['preload'] )
			);
		} );

		if ( !empty( $stylesheets ) )
			foreach ( $stylesheets as $handle => $stylesheet ) {
				if ( null === $stylesheet->ver )
					$ver = '';
				else
					$ver = $stylesheet->ver ? $stylesheet->ver : wp_styles()->default_version;

				if ( isset( $stylesheet->args ) )
					$media = esc_attr( $stylesheet->args );
				else
					$media = 'all';

				$href = wp_styles()->_css_href( $stylesheet->src, $ver, $handle );

				echo '<link rel="preload" href="' . $href . '" as="style" media="' . $media . '" />' . "\n";
			}

		# Preload scripts
		$scripts = array_filter( wp_scripts()->registered, function( $script ) {
			return (
				array_key_exists( 'preload', $script->extra )
				&& !empty( $script->extra['preload'] )
			);
		} );

		if ( !empty( $scripts ) )
			foreach ( $scripts as $handle => $script ) {
				$src = $script->src;

				if ( null === $script->ver ) {
					$ver = '';
				} else {
					$ver = $script->ver ? $script->ver : wp_scripts()->default_version;
				}

				if ( isset( wp_scripts()->args[$handle] ) )
					$ver = $ver ? $ver . '&amp;' . wp_scripts()->args[$handle] : wp_scripts()->args[$handle];

				if ( ! preg_match( '|^(https?:)?//|', $src ) && ! ( wp_scripts()->content_url && 0 === strpos( $src, wp_scripts()->content_url ) ) ) {
					$src = wp_scripts()->base_url . $src;
				}

				if ( ! empty( $ver ) )
					$src = add_query_arg( 'ver', $ver, $src );

				/** This filter is documented in wp-includes/class.wp-scripts.php */
				$src = esc_url( apply_filters( 'script_loader_src', $src, $handle ) );

				echo '<link rel="preload" href="' . $src . '" as="script" />' . "\n";
			}
	}


	/*
	######## #### ##       ######## ######## ########   ######
	##        ##  ##          ##    ##       ##     ## ##    ##
	##        ##  ##          ##    ##       ##     ## ##
	######    ##  ##          ##    ######   ########   ######
	##        ##  ##          ##    ##       ##   ##         ##
	##        ##  ##          ##    ##       ##    ##  ##    ##
	##       #### ########    ##    ######## ##     ##  ######
	*/

	/**
	 * Filter: style_loader_tag
	 *
	 * @param string $tag
	 * @param string $handle`
	 * @param string $href
	 * @param string $media
	 *
	 * @uses $this::_maybe_enhance_asset_loader_tag()
	 *
	 * @return string
	 */
	function filter__style_loader_tag( $tag, $handle, $href, $media ) {
		return $this->_maybe_enhance_asset_loader_tag( $tag, $handle, $href, $media );
	}

	/**
	 * Filter: script_loader_tag
	 *
	 * @param string $tag
	 * @param string $handle`
	 * @param string $src
	 *
	 * @uses $this::_maybe_enhance_asset_loader_tag()
	 *
	 * @return string
	 */
	function filter__script_loader_tag( $tag, $handle, $src ) {
		return $this->_maybe_enhance_asset_loader_tag( $tag, $handle, $src );
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
	 * Maybe enhance asset loader tag.
	 *
	 * @param string $_tag
	 * @param string $handle
	 * @param string $href
	 * @param null|string $media
	 *
	 * @uses $this::enhancement__critical()
	 *
	 * @return string
	 */
	function _maybe_enhance_asset_loader_tag( $_tag, $handle, $href, $media = null ) {
		$is_script = 'script_loader_tag' === current_filter();
		$wp_function = $is_script ? 'wp_scripts' : 'wp_styles';

		if ( !empty( $wp_function()->get_data( $handle, 'critical' ) ) )
			$this->enhancement__critical( $handle );

		$enhancements = array(
			'inline',
		);

		if ( !$is_script )
			$enhancements[] = 'noscript';

		$enhancements = apply_filters( 'enhance-enqueues/enhancements', $enhancements, current_filter() );

		foreach ( $enhancements as $enhancement ) {
			$do_enhancement = !empty( $wp_function()->get_data( $handle, $enhancement ) );
			$do_enhancement = apply_filters( 'enhance-enqueues/do_enhanced', $do_enhancement, $handle, $enhancement );
			$do_enhancement = apply_filters( 'enhance-enqueues/do_enhanced/' . $handle, $do_enhancement, $enhancement );
			$do_enhancement = apply_filters( 'enhance-enqueues/do_enhanced/' . $handle . '/' . $enhancement, $do_enhancement );

			if ( !$do_enhancement )
				continue;

			$function = array( &$this, 'enhancement__' . $enhancement );
			$function = apply_filters( 'enhance-enqueues/enhancement', $function, $enhancement, $handle );

			if ( !is_callable( $function ) )
				return $_tag;

			$tag = call_user_func_array( $function, array( $_tag, $handle, $href, $media ) );

			if ( !empty( $tag ) )
				return $tag;
		}

		return $_tag;
	}

	/**
	 * Enhancement: critical
	 *
	 * @link https://github.com/filamentgroup/loadCSS#how-to-use-loadcss-recommended-example Use of rel="preload".
	 *
	 * @param string $handle
	 */
	function enhancement__critical( $handle ) {
		$function = 'style_loader_tag' === current_filter() ? 'wp_style_add_data' : 'wp_script_add_data';
		$function( $handle, 'inline', true );
	}

	/**
	 * Enhancement: noscript
	 *
	 * @param string $_tag
	 * @param null|string $handle
	 * @param null|string $href
	 * @param null|string $media
	 *
	 * @return string
	 */
	function enhancement__noscript( $_tag, $handle = null, $href = null, $media = null ) {
		return '<noscript>' . trim( $_tag ) . '</noscript>' . "\n";
	}

	/**
	 * Enhancement: inline
	 *
	 * @param string $_tag
	 * @param string $handle
	 * @param string $href
	 * @param string $media
	 *
	 * @uses $this::_get_asset_contents()
	 *
	 * @return string
	 */
	function enhancement__inline( $_tag, $handle, $href, $media ) {
		$contents = $this->_get_asset_contents( $href );

		if ( false === $contents )
			return $_tag;

		if ( 'style_loader_tag' === current_filter() )
			return '<style type="text/css" id="' . esc_attr( $handle ) . '-css">' . "\n" .
				(
					defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG
					? '/* Source: ' . $href . ' */' . "\n\n"
					: ''
				) .
				(
					'all' !== $media
					? '@media ' . $media . ' { ' . $contents . ' }'
					: $contents
				) .
				"\n" .
			'</style>' . "\n";

		else if ( 'script_loader_tag' === current_filter() )
			return str_replace(
				"<script type='text/javascript' src='$href'></script>",
				'<script type="text/javascript" id="' . esc_attr( $handle ) . '-js">' . "\n" .
					( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '/* Source: ' . $href . ' */' . "\n\n" : '' ) .
					$contents . "\n" .
				'</script>',
				$_tag
			);

		return $_tag;
	}

	/**
	 * Get theme asset's contents.
	 *
	 * @param string $href URI of the asset.
	 *
	 * @return bool|string
	 */
	protected function _get_asset_contents( $href ) {
		foreach ( array(
			get_stylesheet_directory_uri() => get_stylesheet_directory(),
			  get_template_directory_uri() => get_template_directory(),
		) as $uri => $directory )
			if ( false !== stripos( $href, $uri ) ) {
				$path = trailingslashit( $directory ) . str_replace( trailingslashit( $uri ), '', $href );
				break;
			}

		$path = preg_replace( "/^(.+?)\?.*$/", "$1", $path );

		if (
			          empty( $path )
			|| !file_exists( $path )
			||     !is_file( $path )
		)
			return false;

		return apply_filters( 'enhance-enqueues/asset/content', file_get_contents( $path ), $path, $href );
	}

	/**
	 * Check if server version supports http2 push.
	 * @todo Add server support detection.
	 * @return bool
	 */
	function server_supports_http2_push() {
		return false;
	}

	/**
	 * Tests for debugging purposes.
	 */
	static function _debug_action__wp_enqueue_scripts() {
		$handle = 'enhance-enqueues-test';

		wp_enqueue_style( $handle, get_theme_file_url( 'includes/_dev/samples/style.css' ) );

			wp_add_inline_style( $handle, '.' . $handle . '::after { content: "after"; }' );
			wp_style_add_data( $handle, 'preload', true );

		wp_enqueue_script( $handle, get_theme_file_url( 'includes/_dev/samples/scripts.js' ) );

			wp_add_inline_script( $handle, 'var before = 1;', 'before' );
			wp_add_inline_script( $handle, 'var  after = 1;' );
			wp_script_add_data( $handle, 'preload', true );
	}

}

new CSSLLC_EnhanceEnqueues;
?>