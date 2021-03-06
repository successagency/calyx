<?php
/**
 * Monitor WooCommerce webhooks.
 */

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Class to monitor WooCommerce webhooks, specifically, the automatic
 * disabling due to successive connection failures.
 */
class Calyx_WooCommerce_MonitorWebhooks {
	use Calyx_Singleton;

	/**
	 * Construct.
	 */
	function __construct() {
		do_action( 'qm/start', __METHOD__ . '()' );

		do_action( THEME_PREFIX . '/compatibility_monitor/__woocommerce', __CLASS__, '3.4.0' );

		add_action( 'woocommerce_webhook_delivery', array( &$this, 'action__woocommerce_webhook_delivery' ), 10, 5 );

		/**
		 * Steps for reporting webhook disabled.
		 */
		add_action( 'woocommerce_webhook_disabled_due_delivery_failures',      array( &$this, 'step_1' ) );
		add_action( 'woocommerce_webhook_delivery',                            array( &$this, 'step_2' ), 10, 5 );
		add_action( THEME_PREFIX . '/woocommerce/webhooks/disabled_triggered', array( &$this, 'step_3' ), 10, 3 );
		add_filter( 'woocommerce_webhook_should_deliver',                      array( &$this, 'step_4' ), 10, 3 );
		add_action( 'woocommerce_webhook_updated',                             array( &$this, 'step_5' ) );

		do_action( 'qm/stop', __METHOD__ . '()' );
	}

	/**
	 * Action: woocommerce_webhook_delivery
	 *
	 * @param array  $http_args
	 * @param array  $response
	 * @param int    $duration
	 * @param string $arg
	 * @param int    $webhook_id
	 */
	function action__woocommerce_webhook_delivery( $http_args, $response, $duration, $arg, $webhook_id ) {
		$webhook = wc_get_webhook( $webhook_id );

		if ( empty( $webhook ) )
			return;

		$failures = $webhook->get_failure_count();

		if ( empty( $failures ) )
			return;

		$max_failures = apply_filters( 'woocommerce_max_webhook_delivery_failures', 5 );
		$percentage = $failures / $max_failures;

		if ( $percentage >= 1 )
			return;

		if ( $percentage >= apply_filters( THEME_PREFIX . '/woocommerce/webhooks/warning_percentage', 0.8 ) )
			do_action( THEME_PREFIX . '/woocommerce/webhooks/failures_warning', $webhook, $failures / $max_failures, $failures );
	}


	/*
	 ######  ######## ######## ########   ######
	##    ##    ##    ##       ##     ## ##    ##
	##          ##    ##       ##     ## ##
	 ######     ##    ######   ########   ######
	      ##    ##    ##       ##              ##
	##    ##    ##    ##       ##        ##    ##
	 ######     ##    ######## ##         ######
	*/

	/**
	 * Step 1: on deactivation of webhook due to failures, create a log entry, and set transient
	 * to mark the time of deactivation.
	 *
	 * Fires on `woocommerce_webhook_disabled_due_delivery_failures` action.
	 *
	 * @param int $webhook_id
	 */
	function step_1( $webhook_id ) {
		wc_get_logger()->info( 'Disabled: ' . $webhook_id, array( 'source' => 'wc-webhook-disabled-due-delivery-failure' ) );
		$this->set_webhook_disabled_transient( $webhook_id );
	}

	/**
	 * Step 2: if webhook was deactivated due to failures, 'woocommerce_webhook_delivery' action
	 * still fires, so let's use it to log the object the webhook failed to process.
	 *
	 * Fires on `woocommerce_webhook_delivery` action.
	 *
	 * @param array  $http
	 * @param array  $response
	 * @param int    $duration
	 * @param string $arg
	 * @param int    $webhook_id
	 */
	function step_2( $http, $response, $duration, $arg, $webhook_id ) {
		if (
			!did_action( 'woocommerce_webhook_disabled_due_delivery_failures' )
			|| empty( $this->get_webhook_disabled_transient( $webhook_id ) )
		)
			return;

		wc_get_logger()->info( $arg, array( 'source' => 'wc-disabled-webhook-' . $webhook_id . '-triggered-' . $this->get_webhook_disabled_transient( $webhook_id ) ) );

		do_action( THEME_PREFIX . '/woocommerce/webhooks/disabled_triggered', $webhook_id, $this->get_webhook_disabled_transient( $webhook_id ), $arg );
	}

	/**
	 * Step 3: log first hook argument if webhook disabled due to delivery failure.
	 *
	 * Fires on `THEME_PREFIX . '/woocommerce/disabled_webhook_triggered'` action.
	 *
	 * @param int    $webhook_id
	 * @param int    $time
	 * @param string $arg
	 */
	function step_3( $webhook_id, $time, $arg ) {
		wc_get_logger()->info( $arg, array( 'source' => 'wc-disabled-webhook-' . $webhook_id . '-triggered-' . $this->get_webhook_disabled_transient( $webhook_id ) ) );
	}

	/**
	 * Step 4: if webhook is disabled, and transient is set, log first hook argument.
	 *
	 * Fires on `woocommerce_webhook_should_deliver` filter.
	 *
	 * @param bool       $bool
	 * @param WC_Webhook $webhook
	 * @param string     $arg
	 *
	 * @return bool
	 */
	function step_4( $bool, WC_Webhook $webhook, $arg ) {
		if (
			$bool
			|| 'disabled' !== $webhook->get_status()
		)
			return $bool;

		$time = $this->get_webhook_disabled_transient( $webhook->get_id() );

		if ( empty( $time ) )
			return $bool;

		do_action( THEME_PREFIX . '/woocommerce/disabled_webhook_triggered', $webhook->get_id(), $time, $arg );

		return $bool;
	}

	/**
	 * Step 5: if transient is set, and webhook is updated to be active, delete the transient.
	 *
	 * Fires on `woocommerce_webhook_updated` action.
	 *
	 * @param int $webhook_id
	 */
	function step_5( $webhook_id ) {
		if ( empty( $this->get_webhook_disabled_transient( $webhook_id ) ) )
			return;

		$webhook = wc_get_webhook( $webhook_id );

		if (
			!empty( $webhook )
			&& 'active' === $webhook->get_status()
		) {
			$this->delete_webhook_disabled_transient( $webhook_id );
			wc_get_logger()->info( 'Reactivated: ' . $webhook_id, array( 'source' => 'wc-webhook-disabled-due-delivery-failure' ) );
		}
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
	 * Get webhook deactivated status from transient.
	 * @param int $webhook_id
	 * @return bool
	 */
	protected function get_webhook_disabled_transient( $webhook_id ) {
		return get_transient( __CLASS__ . '__webhook_' . $webhook_id . '_deactivated' );
	}

	/**
	 * Set webhook deactivated transient to current time.
	 * @param int $webhook_id
	 * @return bool
	 */
	protected function set_webhook_disabled_transient( $webhook_id ) {
		return set_transient( __CLASS__ . '__webhook_' . $webhook_id . '_deactivated', time() );
	}

	/**
	 * Delete webhook deactivated transient.
	 * @param int $webhook_id
	 * @return bool
	 */
	protected function delete_webhook_disabled_transient( $webhook_id ) {
		return delete_transient( __CLASS__ . '__webhook_' . $webhook_id . '_deactivated' );
	}

}

?>
