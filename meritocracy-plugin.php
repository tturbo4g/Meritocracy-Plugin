<?php
/*
**
** Plugin Name: WooCommerce Pay In Store Gateway
** Plugin URI: https://github.com/tturbo4g/Meritocracy-Plugin
** Description: A WooCommerce Plugin to have your customers pay in store. 
** Will be upgraded to using NEAR in the future.
** Version: 1.0.1
** Author: TTURBO4G
** Author URI: https://github.com/tturbo4g
** License: GNU General Public License v3.0
** Reference: https://github.com/apostolisem/woo-pay-in-store-gateway/blob/master/woo-pay-in-store-gateway.php
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

add_action('plugins_loaded', 'woocommerce_pis_init', 0);

function woocommerce_pis_init()
{
	if (!class_exists('WC_Payment_Gateway')) return;

	class WC_Gateway_PIS extends WC_Payment_Gateway
	{

		/**
		 * Constructor for the gateway.
		 */
		public function __construct()
		{
			$this->id                 = 'pis';
			$this->icon               = apply_filters('woocommerce_pis_icon', '');
			$this->method_title       = __('Payment in store', 'woocommerce');
			$this->method_description = __('Have your customers pay with cash in store.', 'woocommerce');
			$this->has_fields         = false;

			// Load the settings
			$this->init_form_fields();
			$this->init_settings();

			// Get settings
			$this->title              = $this->get_option('title');
			$this->description        = $this->get_option('description');
			$this->instructions       = $this->get_option('instructions');
			$this->enable_for_methods = $this->get_option('enable_for_methods', array());

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			add_action('woocommerce_thankyou_pis', array($this, 'thankyou_page'));
		}

		/**
		 * Initialise Gateway Settings Form Fields
		 */
		public function init_form_fields()
		{
			global $woocommerce;

			// $shipping_methods = array();

			// $shipping_methods = new WC_Tracker();
			// $shipping_methods = WC()->Tracker->get_active_shipping_methods();

			// nokill($shipping_methods);

			// return;

			// if ( is_admin() )
			// 	foreach ( WC_Tracker::get_active_shipping_methods() as $method ) {
			//     	$shipping_methods[ $method->id ] = $method->get_title();
			// 	}

			$this->form_fields = array(
				'enabled' => array(
					'title'       => __('Enable PIS', 'woocommerce'),
					'label'       => __('Enable Payment in store', 'woocommerce'),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => __('Title', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
					'default'     => __('Payment in store', 'woocommerce'),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __('Description', 'woocommerce'),
					'type'        => 'textarea',
					'description' => __('Payment method description that the customer will see on your website.', 'woocommerce'),
					'default'     => __('Pay with cash in store.', 'woocommerce'),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __('Instructions', 'woocommerce'),
					'type'        => 'textarea',
					'description' => __('Instructions that will be added to the thank you page.', 'woocommerce'),
					'default'     => __('Pay with cash in store.', 'woocommerce'),
					'desc_tip'    => true,
				),
				'enable_for_methods' => array(
					'title'             => __('Enable for shipping methods', 'woocommerce'),
					'type'              => 'multiselect',
					'class'             => 'chosen_select',
					'css'               => 'width: 450px;',
					'default'           => '',
					'description'       => __('If PIS is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
					'options'           => load_shipping_method_options(),
					'desc_tip'          => true,
					'custom_attributes' => array(
						'data-placeholder' => __('Select shipping methods', 'woocommerce')
					)
				)
			);
		}

		/*
	 * Check If The Gateway Is Available For Use
	 */
		public function is_available()
		{

			if (!empty($this->enable_for_methods)) {

				// Only apply if all packages are being shipped via local pickup
				$chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

				if (isset($chosen_shipping_methods_session)) {
					$chosen_shipping_methods = array_unique($chosen_shipping_methods_session);
				} else {
					$chosen_shipping_methods = array();
				}

				$check_method = false;

				if (is_page(wc_get_page_id('checkout')) && !empty($wp->query_vars['order-pay'])) {

					$order_id = absint($wp->query_vars['order-pay']);
					$order    = new WC_Order($order_id);

					if ($order->shipping_method)
						$check_method = $order->shipping_method;
				} elseif (empty($chosen_shipping_methods) || sizeof($chosen_shipping_methods) > 1) {
					$check_method = false;
				} elseif (sizeof($chosen_shipping_methods) == 1) {
					$check_method = $chosen_shipping_methods[0];
				}

				if (!$check_method)
					return false;

				$found = false;

				foreach ($this->enable_for_methods as $method_id) {
					if (strpos($check_method, $method_id) === 0) {
						$found = true;
						break;
					}
				}

				if (!$found)
					return false;
			}

			return parent::is_available();
		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment($order_id)
		{

			$order = new WC_Order($order_id);

			// Mark as processing (payment won't be taken until delivery)
			$order->update_status('processing', __('Payment to be made upon delivery.', 'woocommerce'));

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url($order)
			);
		}

		/**
		 * Output for the order received page.
		 */
		public function thankyou_page()
		{
			if ($this->instructions)
				echo wpautop(wptexturize($this->instructions));
		}
	}

	function add_your_gateway_class($methods)
	{
		$methods[] = 'WC_Gateway_PIS';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'add_your_gateway_class');
}


/**
 * Loads all of the shipping method options for the enable_for_methods field.
 *
 * @return array
 */
function load_shipping_method_options()
{
	// Since this is expensive, we only want to do it if we're actually on the settings page.
	if (!is_accessing_settings()) {
		return array();
	}

	$data_store = WC_Data_Store::load('shipping-zone');
	$raw_zones  = $data_store->get_zones();

	foreach ($raw_zones as $raw_zone) {
		$zones[] = new WC_Shipping_Zone($raw_zone);
	}

	$zones[] = new WC_Shipping_Zone(0);

	$options = array();
	foreach (WC()->shipping()->load_shipping_methods() as $method) {

		$options[$method->get_method_title()] = array();

		// Translators: %1$s shipping method name.
		$options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'woocommerce'), $method->get_method_title());

		foreach ($zones as $zone) {

			$shipping_method_instances = $zone->get_shipping_methods();

			foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

				if ($shipping_method_instance->id !== $method->id) {
					continue;
				}

				$option_id = $shipping_method_instance->get_rate_id();

				// Translators: %1$s shipping method title, %2$s shipping method id.
				$option_instance_title = sprintf(__('%1$s (#%2$s)', 'woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

				// Translators: %1$s zone name, %2$s shipping method instance name.
				$option_title = sprintf(__('%1$s &ndash; %2$s', 'woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'woocommerce'), $option_instance_title);

				$options[$method->get_method_title()][$option_id] = $option_title;
			}
		}
	}

	return $options;
}


function is_accessing_settings()
{
	if (is_admin()) {
		// phpcs:disable WordPress.Security.NonceVerification
		if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
			return false;
		}
		if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
			return false;
		}
		if (!isset($_REQUEST['section']) || 'pis' !== $_REQUEST['section']) {
			return false;
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return true;
	}

	return false;
}
