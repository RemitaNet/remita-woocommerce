<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class WC_Gateway_Remita_Blocks_Support
 *
 * @since 2.3.2
 * @extends AbstractPaymentMethodType
 * @package Flutterwave
 */
final class WC_Gateway_Remita_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'remita';
	/**
	 * Settings from the WP options table
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( "woocommerce_{$this->name}_settings", array() );
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() : bool
	{
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();
		return $payment_gateways['remita']->is_available();
	}


	/**
	 * Returns an array of supported features.
	 *
	 * @return string[]
	 */
	public function get_supported_features(): array {
		return $this->gateway->supports;
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$asset_path   = plugin_dir_path( __DIR__ ) . 'build/index.asset.php';
		$version      = null;
		$dependencies = array();
		if( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = isset( $asset[ 'version' ] ) ? $asset[ 'version' ] : $version;
			$dependencies = isset( $asset[ 'dependencies' ] ) ? $asset[ 'dependencies' ] : $dependencies;
		}
	
		wp_register_script( 
			'wc-remita-blocks-integration', 
			plugin_dir_url( __DIR__ ) . 'assets/js/block/checkout.js', 
			$dependencies, 
			$version, 
			true 
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wc-remita-blocks-integration');
		}

		return array( 'wc-remita-blocks-integration' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways       = $payment_gateways_class->payment_gateways();
		$gateway                = $payment_gateways['remita'];

		return array(
			'title'             => $this->get_setting('remita_title'),
			'description'       => $this->get_setting('remita_description'),
			'icons'				=>	plugin_dir_url( __DIR__ ) . 'assets/images/remita-payment-options.png',
		);
	}

}
