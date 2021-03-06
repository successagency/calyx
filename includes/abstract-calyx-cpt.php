<?php
/**
 * Abstract for CPT helpers.
 */

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Abstract class for custom post types.
 */
abstract class _Calyx_CPT {
	use Calyx_Singleton;

	/** @var string Post type name. */
	protected $_type;

	/** @var array Name and action for nonce. */
	protected $_nonce;

	/** @var string Plural name of post type. */
	protected $_plural;

	/** @var string Singular name of post type. */
	protected $_singular;

	/**
	 * @var string CSS 'content' code of Dashicon to represent post type.
	 * @link https://developer.wordpress.org/resource/dashicons/#book-alt
	 */
	protected $_dashicon_code;

	/** @var array Arguments for post type. */
	protected $_args = array(
		'labels'   => array(),
		'rewrites' => array(),
		'supports' => array(),
	);

	/**
	 * Construct.
	 *
	 * @param array $args Arguments to overwrite defaults.
	 */
	protected function __construct( $args = array() ) {
		do_action( 'qm/start', get_called_class() . '::' . __FUNCTION__ . '()' );

		if ( !empty( $args ) )
			foreach ( $args as $arg => $value )
				if ( property_exists( $this, $arg ) )
					$this->_{$arg} = $value;

		$this->_nonce = array(
			'action' => __FILE__ . '::' . __LINE__,
			'name'   => '_wpnonce_' . $this->get_type(),
		);

		add_action( 'init',                           array( &$this, 'action__init'       ) );
		add_action( 'admin_init',                     array( &$this, 'action__admin_init' ) );
		add_action( 'save_post_' . $this->get_type(), array( &$this, 'action__save_post'  ), 10, 2 );

		add_filter( 'dashboard_glance_items', array( &$this, 'filter__dashboard_glance_items' ) );
		add_filter( 'post_updated_messages',  array( &$this, 'filter__post_updated_messages'  ) );

		do_action( 'qm/stop', get_called_class() . '::' . __FUNCTION__ . '()' );
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
	 * Action: init
	 *
	 * @see register_post_type()
	 * @link https://codex.wordpress.org/Function_Reference/register_post_type#Arguments
	 */
	function action__init() {

		$labels = wp_parse_args(
			(
				array_key_exists( 'labels', $this->_args )
				? $this->_args['labels']
				: array()
			),
			array(
				'name'                  => $this->_plural,
				'singular_name'         => $this->_singular,
				'add_new'               => 'Add ' . $this->_singular,
				'add_new_item'          => 'Add New ' . $this->_singular,
				'edit_item'             => 'Edit ' . $this->_singular,
				'new_item'              => 'New ' . $this->_singular,
				'view_item'             => 'View ' . $this->_singular,
				'search_items'          => 'Search ' . $this->_plural,
				'not_found'             => 'No ' . strtolower( $this->_plural ) . ' found',
				'not_found_in_trash'    => 'No ' . strtolower( $this->_plural ) . ' found in Trash',
				'parent_item_colon'     => 'Parent ' . $this->_plural . ':',
				'all_items'             => 'All ' . $this->_plural,
				'archives'              => $this->_singular . ' Archives',
				'attributes'            => $this->_singular . ' Attributes',
				'insert_into_item'      => 'Insert into ' . strtolower( $this->_singular ),
				'uploaded_to_this_item' => 'Uploaded to this ' . strtolower( $this->_singular ),
				// 'featured_image'        => 'Featured Image',
				// 'set_featured_image'    => 'Set Featured Image',
				// 'remove_featured_image' => 'Remove featured image',
				// 'use_featured_image'    => 'Use as featured image',
				'menu_name'             => $this->_plural,
				// 'filter_items_list'     => $this->_plural,
				// 'items_list_navigation' => $this->_plural,
				'items_list'            => $this->_plural,
				'name_admin_bar'        => $this->_singular,
			)
		);

		$args = wp_parse_args( $this->_args, array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'has_archive'        => true,
			'hierarchical'       => false,
		) );

		$args['rewrite'] = wp_parse_args(
			(
				array_key_exists( 'rewrites', $this->_args )
				? $this->_args['rewrites']
				: array()
			),
			array(
				'slug'       => $this->get_type(),
				'with_front' => false,
			)
		);

		$args['supports'] = wp_parse_args(
			(
				array_key_exists( 'supports', $this->_args )
				? $this->_args['supports']
				: array()
			),
			array(
				'title',
				'author',
				'editor',
				'excerpt',
				'thumbnail',
			)
		);

		register_post_type( $this->get_type(), $args );

	}

	/**
	 * Action: admin_init
	 */
	function action__admin_init() {
		if ( !empty( $this->_dashicon_code ) )
			wp_add_inline_style( 'dashicons', '.icon-cpt-' . $this->get_type() . ':before { content: "' . $this->get_dashicon_code() . '" !important; }' );
	}

	/**
	 * Action: save_post
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	function action__save_post( $post_id, WP_Post $post ) {
		if (
			empty( $this->_postmeta )
			|| !isset( $_POST )
			|| !count( array_filter( $_POST ) )
			|| Calyx()->utils()->doing_ajax()
			|| Calyx()->utils()->doing_autosave()
			|| !wp_verify_nonce( $_POST[$this->get_nonce__name()], $this->get_nonce__action() )
		)
			return false;

		if ( count( array_filter( $this->_postmeta ) ) )
			foreach ( array_filter( $this->_postmeta ) as $postmeta_key => $field_name )
				!empty( $_POST[$field_name] )
					? update_post_meta( $post_id, $postmeta_key, $_POST[$field_name] )
					: delete_post_meta( $post_id, $postmeta_key );

		do_action( THEME_PREFIX . '_save_' . $this->get_type(), $post_id, $post );
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
	 * Add count of CPT to 'At a Glance' dashboard widget.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	function filter__dashboard_glance_items( $items ) {
		$count = wp_count_posts( $this->get_type() );

		$items['count_' . $this->get_type()] =
			'<a class="icon-cpt-' . $this->get_type() . '" href="' . admin_url( add_query_arg( 'post_type', $this->get_type(), 'edit.php' ) ) . '">' .
				$count->publish . _n( ' ' . $this->_singular, ' ' . $this->_plural, $count->publish ) .
			'</a>';

		return $items;
	}

	/**
	 * Add messages for updating the custom post type.
	 *
	 * @param array $notices
	 *
	 * @return array
	 */
	function filter__post_updated_messages( $notices ) {
		global $post_ID, $post;

		if ( get_post_type( $post ) !== $this->get_type() )
			return $notices;

		$object = get_post_type_object( get_post_type( $post ) );

		$notices[$this->get_type()] = array(
			 0 => '', // Unused. Messages start at index 1.
			 1 => sprintf( __( $this->_singular . ' updated.' . ( true === $object->public ? ' <a href="%s">View ' . $this->_singular . '</a>' : '' ) ), esc_url( get_permalink( $post_ID ) ) ),
			 2 => __( 'Custom field updated.' ),
			 3 => __( 'Custom field deleted.' ),
			 4 => __( $this->_singular . ' updated.' ),
			 5 => isset( $_GET['revision'] ) ? sprintf( __( $this->_singular . ' restored to revision from %s' ), wp_post_revision_title( ( int ) $_GET['revision'], false ) ) : false,
			 6 => sprintf( __( $this->_singular . ' published.' . ( true === $object->public ? ' <a href="%s">View ' . $this->_singular . '</a>' : '' ) ), esc_url( get_permalink( $post_ID ) ) ),
			 7 => __( $this->_singular . ' saved.'),
			 8 => sprintf( __( $this->_singular . ' submitted. <a target="_blank" href="%s">Preview ' . $this->_singular . '</a>' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
			 9 => sprintf( __( $this->_singular . ' scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview ' . $this->_singular . '</a>' ), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
			10 => sprintf( __( $this->_singular . ' draft updated.' . ( true === $object->public ? ' <a target="_blank" href="%s">Preview ' . $this->_singular . '</a>' : '' ) ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
		);

		return $notices;
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

	/** Get CPT name. */
	function get_type()  { return $this->_type; }

	/** Get nonce for CPT .*/
	function get_nonce() { return wp_create_nonce( $this->get_nonce__action() ); }

	/** Get nonce action for CPT. */
	function get_nonce__action() { return $this->_nonce['action']; }

	/** Get nonce name for CPT. */
	function get_nonce__name()   { return $this->_nonce['name']; }

	/** Get WordPress dashicon code for CPT. */
	function get_dashicon_code() { return $this->_dashicon_code;   }

}

?>