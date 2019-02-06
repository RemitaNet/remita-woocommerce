<?php
/**
  Plugin Name: Remita WooCommerce Payment Gateway
  Plugin URI:  https://www.remita.net
  Description: Remita Woocommerce Payment gateway allows you to accept payment on your Woocommerce store.
  Author:      SystemSpecs Limited
  Author URI:  https://systemspecs.com.ng
  Version:     2.0
  
 */

define('WC_REMITA_MAIN_FILE', __FILE__);

define('WC_REMITA_VERSION', '5.3.1');

register_activation_hook(__FILE__, 'jal_install');
function jal_install()
{
    global $jal_db_version;
    $jal_db_version = '1.0';
    global $wpdb;
    global $jal_db_version;

    $table_name = $wpdb->prefix . 'paymentgatewaytranx';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            remitaorderid varchar(255) NOT NULL,
            storeorderid varchar(255) NOT NULL,
            UNIQUE KEY id (id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('jal_db_version', $jal_db_version);
}

add_filter('plugins_loaded', 'wc_remita_init');
function wc_remita_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Remita extends WC_Payment_Gateway
    {

        public function __construct()
        {
            global $woocommerce;

            $this->id           = 'remita';
            $this->icon         = apply_filters('woocommerce_remita_icon', plugins_url('assets/images/remita-payment-options.png', __FILE__));
            $this->method_title = __('Remita', 'woocommerce');
            //    $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Remita', home_url( '/' ) ) );
            $this->notify_url   = WC()->api_request_url('WC_Remita');

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->title                  = $this->settings['remita_title'];
            $this->description            = $this->settings['remita_description'];
            $this->publicKey              = $this->settings['remita_publickey'];
            $this->secretKey              = $this->settings['remita_secretkey'];
            $this->remita_mode            = $this->settings['remita_mode'];
            $this->remita_checkout_method = $this->settings['remita_checkout_method'];
            $this->feedback_message       = '';
            add_action('woocommerce_receipt_remita', array(
                &$this,
                'receipt_page'
            ));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options'
            ));

            add_action('woocommerce_thankyou_' . $this->id, array(
                &$this,
                'thankyou_page'
            ));
            // Payment listener/API hook
            add_action('woocommerce_api_wc_remita', array(
                &$this,
                'transaction_verification'
            ));
            //Filters
            add_filter('woocommerce_currencies', array(
                $this,
                'add_ngn_currency'
            ));
            add_filter('woocommerce_currency_symbol', array(
                $this,
                'add_ngn_currency_symbol'
            ), 10, 2);

            // Hooks
            add_action('wp_enqueue_scripts', array(
                $this,
                'payment_scripts'
            ));

        }


        function add_ngn_currency($currencies)
        {
            $currencies['NGN'] = __('Nigerian Naira (NGN)', 'woocommerce');
            return $currencies;
        }

        function add_ngn_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'NGN':
                    $currency_symbol = '₦';
                    break;
            }

            return $currency_symbol;
        }

        function is_valid_for_use()
        {
            $return = true;

            if (!in_array(get_option('woocommerce_currency'), array(
                'NGN'
            ))) {
                $return = false;
            }

            return $return;
        }

        function admin_options()
        {
            echo '<h3>' . __('Remita Payment Gateway', 'woocommerce') . '</h3>';
            echo '<p>' . __('<br><img src="' . plugins_url('assets/images/remita.png', __FILE__) . '" >', 'woocommerce') . '</p>';
            echo '<table class="form-table">';

            if ($this->is_valid_for_use()) {
                $this->generate_settings_html();
            } else {
                echo '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'woocommerce') . '</strong>: ' . __('Remita does not support your store currency.', 'woocommerce') . '</p></div>';
            }

            echo '</table>';

        }


        function init_form_fields()
        {

            $this->form_fields = array(
                'remita_title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'default' => __('Remita Payment Gateway', 'woocommerce')
                ),
                'remita_description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'Best way to pay on web.',
                    'desc_tip' => true,
                    'default' => 'Make payment using your debit and credit cards'
                ),

                'remita_enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'yes'
                ),
                'remita_publickey' => array(
                    'title' => __('Public Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => 'Login to remit.net to get your public key',
                    'desc_tip' => true

                ),
                'remita_secretkey' => array(
                    'title' => __('Secret Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => 'Login to remit.net to get your secret key',
                    'desc_tip' => true

                ),
                'remita_mode' => array(
                    'title' => __('Environment', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Select Test or Live modes.', 'woothemes'),
                    'desc_tip' => true,
                    'placeholder' => '',
                    'options' => array(
                        'Test' => "Test",
                        'Live' => "Live"

                    )
                ),

                'remita_checkout_method' => array(
                    'title' => __('Checkout Method', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Inline shows the payment popup on the page', 'woothemes'),
                    'desc_tip' => true,
                    'placeholder' => '',
                    'options' => array(
                        'REMITA_INLINE' => "Inline"

                    )
                )

            );

        }

        function payment_fields()
        {
            // Description of payment method from settings
            if ($this->description) {
                ?>
              <p><?php
                  echo $this->description;
                  ?></p>

                <?php
            }
            ?>


            <?php
        }


        public function transaction_verification()
        {

            @ob_clean();
            if (isset($_REQUEST['transactionId'])) {

                if ($this->remita_mode == 'Test') {
                    $remitaQueryUrl = 'https://remitademo.net/payment/v1/payment/query/' . $_REQUEST['transactionId'];

                } else if ($this->remita_mode == 'Live') {
                    $remitaQueryUrl = 'https://login.remita.net/payment/v1/payment/query/' . $_REQUEST['transactionId'];
                }
                $publicKey = $this->publicKey;
                $secretKey = $this->secretKey;

                $txnHash = hash('sha512', $_REQUEST['transactionId'] . $secretKey);

                $header = array(
                    'Content-Type: application/json',
                    'publicKey:' . $publicKey,
                    'TXN_HASH:' . $txnHash
                );


                //  Initiate curl
                $ch = curl_init();

                // Disable SSL verification
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                // Will return the response, if false it print the response
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


                // Set the url
                curl_setopt($ch, CURLOPT_URL, $remitaQueryUrl);

                // Set the header
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);


                // Execute
                $result = curl_exec($ch);

                // Closing
                curl_close($ch);

                // decode json
                $result_response = json_decode($result, true);

                $paymentReference = $result_response['responseData']['0']['paymentReference'];

                $order_details = explode('_', $result_response['responseData']['0']['transactionId']);

                $order_id = (int) $order_details[1];


                $order = wc_get_order($order_id);

                if ($result_response['responseCode'] == '00') {

                    if (in_array($order->get_status(), array(
                        'processing',
                        'completed',
                        'on-hold'
                    ))) {

                        wp_redirect($this->get_return_url($order));

                        exit;

                    }
                    $order_total = $order->get_total();

                    $order_currency = method_exists($order, 'get_currency') ? $order->get_currency() : $order->get_order_currency();

                    $amount_paid = $result_response['responseData']['0']['amount'];

                    // check if the amount paid is equal to the order amount.
                    if ($amount_paid < $order_total) {

                        $order->update_status('on-hold', '');

                        add_post_meta($order_id, '_transaction_id', $paymentReference, true);

                        $notice = 'Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order.';

                        $notice_type = 'notice';

                        $order->add_order_note($notice, 1);

                        $order->add_order_note('<strong>Kindly Look into this order</strong><br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was <strong>;' . $amount_paid . '</strong> while the total order amount is <strong>;' . $order_total . '</strong><br /> Remita Retrieval Reference: ' . $paymentReference);

                        function_exists('wc_reduce_stock_levels') ? wc_reduce_stock_levels($order_id) : $order->reduce_order_stock();

                        wc_add_notice($notice, $notice_type);
                    }

                    wc_empty_cart();


                } elseif ($result_response['responseCode'] == '34'){

                    $notice = '<strong>API HASHING ERROR.<br /> Please ensure you have valid credentials on your Admin Panel .</strong>';

                    $notice_type = 'notice';


                    wc_add_notice($notice, $notice_type);

                } else {

                    $order->update_status('failed', 'Payment was declined by REmita.');


                }

                if ($result_response['responseData']['0']['paymentState'] == 'SUCCESSFUL') {

                    $order->payment_complete($paymentReference);

                    $order->add_order_note(sprintf('Payment via Remita successful (Remita Retrieval Reference: %s)', $paymentReference));

                }

                wc_empty_cart();

                wp_redirect($this->get_return_url($order));

                exit;
            }


        }


        public function payment_scripts()
        {

            $order_id      = urldecode($_GET['order']);
            $order         = wc_get_order($order_id);
            $order_amount  = method_exists($order, 'get_total') ? $order->get_total() : $order->order_total;
            $order_amount  = $order_amount;
            $email         = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
            $billing_phone = method_exists($order, 'get_billing_phone') ? $order->get_billing_phone() : $order->billing_phone;
            $first_name    = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
            $last_name     = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
            $uniqueRef     = uniqid();
            $uniqueOrderId = $uniqueRef . '_' . $order_id;

            if ($this->remita_mode == 'Test') {

                $jsFile = "https://remitademo.net/payment/v1/remita-pay-inline.bundle.js";

            } else if ($this->remita_mode == 'Live') {

                $jsFile = "https://login.remita.net/payment/v1/remita-pay-inline.bundle.js";

            }
            wp_enqueue_script('remita', $jsFile);

            wp_register_script('wc_remita', plugins_url('assets/js/remita.js', WC_REMITA_MAIN_FILE), array(
                'jquery',
                'remita'
            ), WC_REMITA_VERSION, false);

            wp_enqueue_script('wc_remita');


            $remita_params = array(
                'key' => $this->publicKey,
                'amount' => $order_amount,
                'order_id' => $order_id,
                'billing_phone' => $billing_phone,
                'email' => $email,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'uniqueOrderId' => $uniqueOrderId

            );
            update_post_meta($order_id, '_uniqueOrderId', $uniqueOrderId);


            wp_localize_script('wc_remita', 'wc_remita_params', $remita_params);


        }


        function thankyou_page()
        {
            echo wpautop($this->feedback_message);
        }

        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);
            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function receipt_page($order_id)
        {
            $order = wc_get_order($order_id);
            echo '<p>' . __('Thank you for your order, please click the button below to pay with Remita.', 'woocommerce') . '</p>';

            echo '<div id="remita_form"><form id="order_review" method="post" action="' . WC()->api_request_url('WC_Remita') . '"></form><button class="button alt" id="remita-payment-button">Pay Now</button> <a class="button cancel" href="' . esc_url($order->get_cancel_order_url()) . '">Cancel order &amp; restore cart</a></div>
                  ';
        }


    }

    function woocommerce_add_remita_gateway($methods)
    {
        $methods[] = 'wc_remita';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_remita_gateway');

}

?>
