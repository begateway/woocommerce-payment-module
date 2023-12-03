<?php
/*
Plugin Name: BeGateway Payment Gateway for WooCommerce
Description: Extends WooCommerce with BeGateway payment gateway.
Version: 3.0.0
Author: BeGateway
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Text Domain: wc-begateway-payment
Domain Path: /languages

WC requires at least: 7.0.0
WC tested up to: 8.3.1
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class WC_BeGateway
{
    public static $version = '3.0.0';
    function __construct()
    {
        $this->id = 'begateway';

        add_action('before_woocommerce_init', array($this, 'woocommerce_begateway_declare_hpos_compatibility'));

        add_action('woocommerce_loaded', array($this, 'woocommerce_loaded'), 40);

        add_action('woocommerce_blocks_loaded', array($this, 'woocommerce_begateway_woocommerce_blocks_support'));

        // Load translation files
        add_action('init', __CLASS__ . '::load_plugin_textdomain', 3);

        // Add statuses for payment complete
        add_filter('woocommerce_valid_order_statuses_for_payment_complete', array(
            $this,
            'add_valid_order_statuses'
        ), 10, 2);

        // Add Admin Backend Actions
        add_action('wp_ajax_begateway_capture', array(
            $this,
            'ajax_begateway_capture'
        )
        );

        add_action('wp_ajax_begateway_cancel', array(
            $this,
            'ajax_begateway_cancel'
        )
        );

        add_action('wp_ajax_begateway_refund', array(
            $this,
            'ajax_begateway_refund'
        )
        );

        add_action('wp_ajax_begateway_capture_partly', array(
            $this,
            'ajax_begateway_capture_partly'
        )
        );

        add_action('wp_ajax_begateway_refund_partly', array(
            $this,
            'ajax_begateway_refund'
        )
        );
        
        if ( is_admin() ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
        }

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'), 10, 2);

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(
            $this,
            'woocommerce_begateway_plugin_links'
        )
        );
    } // end __construct

    /**
     * Called on plugins_loaded to load any translation files.
     *
     * @since 1.1
     */
    public static function load_plugin_textdomain()
    {

        $plugin_rel_path = apply_filters('woocommerce_begateway_translation_file_rel_path', dirname(plugin_basename(__FILE__)) . '/languages');
        load_plugin_textdomain('wc-begateway-payment', false, $plugin_rel_path);
    }

    /**
     * WooCommerce Loaded: load classes
     * @return void
     */
    public function woocommerce_loaded()
    {
        require_once(dirname(__FILE__) . '/vendor/autoload.php');
        include_once(dirname(__FILE__) . '/includes/class-wc-gateway-begateway-utils.php');
        include_once(dirname(__FILE__) . '/includes/class-wc-gateway-begateway.php');

        if ($this->is_woocommerce_subscription_support_enabled()) {
            // register gateway with subscription support
            include_once(dirname(__FILE__) . '/includes/class-wc-gateway-begateway-subscriptions.php');
            WC_BeGateway::register_gateway('WC_Gateway_BeGateway_Subscriptions');
        } else {
            WC_BeGateway::register_gateway('WC_Gateway_BeGateway');
        }
    }

    /**
     * Register payment gateway
     *
     * @param string $class_name
     */
    public static function register_gateway($class_name)
    {
        global $gateways;

        if (!$gateways) {
            $gateways = array();
        }

        if (!isset($gateways[$class_name])) {
            // Initialize instance
            if ($gateway = new $class_name) {
                $gateways[] = $class_name;

                // Register gateway instance
                add_filter('woocommerce_payment_gateways', function ($methods) use ($gateway) {
                    $methods[] = $gateway;

                    return $methods;
                });
            }
        }
    }

    /**
     * Declare blocks support
     *
     * @param 
     */
    function woocommerce_begateway_woocommerce_blocks_support()
    {

        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            require_once dirname(__FILE__) . '/includes/blocks/class-wc-gateway-begateway-blocks-support.php';
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new WC_BeGateway_Blocks_Support);
                }
            );
        }
    }

    /**
     * Add links to plugin description
     *
     * @param array $links
     * @return array
     */
    function woocommerce_begateway_plugin_links($links)
    {

        $settings_url = add_query_arg(
            array(
                'page' => 'wc-settings',
                'tab' => 'checkout',
                'section' => 'wc_gateway_begateway',
            ),
            admin_url('admin.php')
        );

        $plugin_links = array(
            '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'wc-begateway-payment') . '</a>',
            '<a href="https://wordpress.org/support/plugin/wc-begateway-payment/">' . esc_html__('Support', 'wc-begateway-payment') . '</a>',
            '<a href="https://wordpress.org/plugins/wc-begateway-payment/#description">' . esc_html__('Docs', 'wc-begateway-payment') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }

    /**
     * Declare HPOS support
     *
     */
    function woocommerce_begateway_declare_hpos_compatibility()
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    /**
     * Allow processing/completed statuses for capture
     *
     * @param array    $statuses
     * @param WC_Order $order
     *
     * @return array
     */
    public function add_valid_order_statuses($statuses, $order)
    {
        if ($this->id == $order->get_payment_method()) {
            $statuses = array_merge($statuses, array(
                'processing',
                'completed'
            )
            );
        }

        return $statuses;
    }

    /**
     * beGateway metabox
     *
     * @param Object           $screen The current screen object.
     * @param WC_Order|WP_Post $order The current post/order object.
     * @return void
     */
    public function add_meta_boxes($screen, $order)
    {
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order->ID);
        if (!($order instanceof \WC_Order)) {
            return;
        }

        if ($this->id !== $order->get_payment_method()) {
            return;
        }

        $screen = WC_Gateway_BeGateway_Utils::get_edit_order_screen_id();

        //$post_types = [ 'shop_order', 'shop_subscription' ];

        $post_types = apply_filters('woocommerce_begateway_admin_meta_box_post_types', array($screen));

        foreach ($post_types as $post_type) {
            add_meta_box(
                'begateway-payment-actions',
                __('Transactions', 'wc-begateway-payment'),
                [
                    &
                    $this,
                    'meta_box_payment',
                ],
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * beGateway metabox content.
     *
     * @param WC_Order|WP_Post $object The current post/order object.
     * @return void
     */
    public function meta_box_payment($object)
    {
        $order = $object instanceof WC_Order ? $object : wc_get_order($object->ID);

        if (!($order instanceof \WC_Order)) {
            return;
        }

        $payment_method = $order->get_payment_method();

        if ($this->id == $payment_method) {

            do_action('woocommerce_begateway_meta_box_payment_before_content', $order);

            // Get Payment Gateway
            $gateways = WC()->payment_gateways()->get_available_payment_gateways();

            /** @var WC_Gateway_BeGateway $gateway */
            $gateway = $gateways[$payment_method];

            try {
                wc_get_template(
                    'admin/metabox-order.php',
                    array(
                        'gateway' => $gateway,
                        'order' => $order,
                        'order_id' => $order->get_id(),
                        'order_data' => $gateway->get_invoice_data($order)
                    ),
                    '',
                    dirname(__FILE__) . '/templates/'
                );
            } catch (Exception $e) {
            }
        }
    }

    /**
     * Enqueue Scripts in admin
     *
     * @param $hook
     *
     * @return void
     */
    public function admin_enqueue_scripts( $hook )
    {

        // Scripts
        wp_register_script(
            'begateway-js-input-mask',
            plugin_dir_url(__FILE__) . 'js/jquery.inputmask.js',
            array('jquery'),
            '5.0.3'
        );
        wp_register_script(
            'begateway-admin-js',
            plugin_dir_url(__FILE__) . 'js/admin.js',
            array(
                'jquery',
                'begateway-js-input-mask'
            )
        );
        wp_enqueue_style('wc-gateway-begateway', plugins_url('/css/style.css', __FILE__), array(), FALSE, 'all');

        // Localize the script
        $translation_array = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'text_wait' => __('Please wait...', 'wc-begateway-payment'),
        );
        wp_localize_script('begateway-admin-js', 'BeGateway_Admin', $translation_array);

        // Enqueued script with localized data
        wp_enqueue_script('begateway-admin-js');
    }

    /**
     * Action for Capture
     */
    public function ajax_begateway_capture()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'begateway')) {
            exit('Invalid nonce');
        }

        $order_id = (int) sanitize_text_field($_REQUEST['order_id']);
        $order = wc_get_order($order_id);

        // Get Payment Gateway
        $payment_method = $order->get_payment_method();
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        /** @var WC_Gateway_BeGateway $gateway */
        $gateway = $gateways[$payment_method];
        $result = $gateway->capture_payment($order_id, $order->get_total());

        if (!is_wp_error($result)) {
            wp_send_json_success(__('Capture success', 'wc-begateway-payment'));
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }

    /**
     * Action for Cancel
     */
    public function ajax_begateway_cancel()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'begateway')) {
            exit('Invalid nonce');
        }

        $order_id = (int) sanitize_text_field($_REQUEST['order_id']);
        $order = wc_get_order($order_id);

        //
        // Check if the order is already cancelled
        // ensure no more actions are made
        //
        if ($order->get_meta('_begateway_transaction_voided', true) === "yes") {
            wp_send_json_success(__('Order already cancelled', 'wc-begateway-payment'));
            return;
        }

        // Get Payment Gateway
        $payment_method = $order->get_payment_method();
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        /** @var WC_Gateway_BeGateway $gateway */
        $gateway = $gateways[$payment_method];

        $result = $gateway->cancel_payment($order_id, $order->get_total());

        if (!is_wp_error($result)) {
            wp_send_json_success(__('Cancel success', 'wc-begateway-payment'));
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }

    /**
     * Action for Cancel
     */
    public function ajax_begateway_refund()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], 'begateway')) {
            exit('Invalid nonce');
        }

        $amount = sanitize_text_field($_REQUEST['amount']);
        $order_id = (int) sanitize_text_field($_REQUEST['order_id']);
        $order = wc_get_order($order_id);

        $amount = str_replace(",", ".", $amount);
        $amount = floatval($amount);

        $payment_method = $order->get_payment_method();
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        /** @var WC_Gateway_BeGateway $gateway */
        $gateway = $gateways[$payment_method];
        $result = $gateway->refund_payment($order_id, $amount);

        if (!is_wp_error($result)) {
            wp_send_json_success(__('Refund success', 'wc-begateway-payment'));
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }

    /**
     * Action for partial capture
     */
    public function ajax_begateway_capture_partly()
    {

        if (!wp_verify_nonce($_REQUEST['nonce'], 'begateway')) {
            exit('Invalid nonce');
        }

        $amount = sanitize_text_field($_REQUEST['amount']);
        $order_id = (int) sanitize_text_field($_REQUEST['order_id']);
        $order = wc_get_order($order_id);

        $amount = str_replace(",", ".", $amount);
        $amount = floatval($amount);

        // Get Payment Gateway
        $payment_method = $order->get_payment_method();
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();

        /** @var WC_Gateway_BeGateway $gateway */
        $gateway = $gateways[$payment_method];
        $result = $gateway->capture_payment($order_id, $amount);

        if (!is_wp_error($result)) {
            wp_send_json_success(__('Capture partly success', 'wc-begateway-payment'));
        } else {
            wp_send_json_error($result->get_error_message());
        }
    }

    protected function is_woocommerce_subscription_support_enabled()
    {
        return class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order');
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_url()
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Plugin url.
     *
     * @return string
     */
    public static function plugin_abspath()
    {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * Plugin version.
     *
     * @return string
     */
    public static function plugin_version()
    {
        return self::$version;
    }

} //end of class

new WC_BeGateway();
