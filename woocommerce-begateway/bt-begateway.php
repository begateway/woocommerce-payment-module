<?php
/*
Plugin Name: WooCommerce beGateway Payment Gateway
Plugin URI: https://github.com/beGateway/woocommerce-payment-module
Description: Extends WooCommerce with beGateway payment gateway.
Version: 1.0.0
Author: beGateway development team

Text Domain: woocommerce-begateway
Domain Path: /languages/

*/

//setup definitions - may not be needed but belts and braces chaps!
define('BT_BEGATEWAY_VERSION', '1.0.0');

if ( !defined('WP_CONTENT_URL') )
  define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');

if ( !defined('WP_PLUGIN_URL') )
  define('WP_PLUGIN_URL', WP_CONTENT_URL.'/plugins');

if ( !defined('WP_CONTENT_DIR') )
  define('WP_CONTENT_DIR', ABSPATH . 'wp-content');

if ( !defined('WP_PLUGIN_DIR') )
  define('WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins');

define("BT_BEGATEWAY_PLUGINPATH", "/" . plugin_basename( dirname(__FILE__) ));

define('BT_BEGATEWAY_BASE_URL', WP_PLUGIN_URL . BT_BEGATEWAY_PLUGINPATH);

define('BT_BEGATEWAY_BASE_DIR', WP_PLUGIN_DIR . BT_BEGATEWAY_PLUGINPATH);

//go looking for woocommerce - if not found then do not allow this plugin to do anything
if(!function_exists('bt_get_plugins'))
{
  function bt_get_plugins()
  {
    if ( !is_multisite() )
      return false;

    $all_plugins = array_keys((array) get_site_option( 'active_sitewide_plugins' ));
    if (!is_array($all_plugins) )
      return false;

    return $all_plugins;
  }
}

if ( in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins' )  ) || in_array('woocommerce/woocommerce.php', (array) bt_get_plugins() ) ) 
{
  load_plugin_textdomain('woocommerce-begateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
  add_action('plugins_loaded', 'bt_begateway_go', 0);
  add_filter('woocommerce_payment_gateways', 'bt_begateway_add_gateway' );

}

//Launch plugin
function bt_begateway_go()
{

  class BT_beGateway extends WC_Payment_Gateway
  {
    var $notify_url;

    public $id = 'begateway';
    public $icon;//not used
    public $has_fields = true;
    public $method_title;
    public $title;
    public $settings;

    /**
     * constructor
     *
     */
    function __construct()
    {
      global $woocommerce;
      // load form fields
      $this->init_form_fields();
      // initialise settings
      $this->init_settings();
      // variables
      $this->title                    = $this->settings['title'];
      //admin title
      if ( current_user_can( 'manage_options' ) ){
        $this->title                    = $this->settings['admin_title'];
      }

      $this->payment_url   = 'https://' . $this->settings['domain-gateway'] . '/transactions/payments';
      $this->authorise_url = 'https://' . $this->settings['domain-gateway'] . '/transactions/authorizations';
      $this->capture_url   = 'https://' . $this->settings['domain-gateway'] . '/transactions/captures';
      $this->void_url      = 'https://' . $this->settings['domain-gateway'] . '/transactions/voids';
      $this->refund_url    = 'https://' . $this->settings['domain-gateway'] . '/transactions/refunds';
      $this->token_url     = 'https://' . $this->settings['domain-checkout'] . '/ctp/api/checkouts';
      //callback URL - hooks into the WP/WooCommerce API and initiates the payment class for the bank server so it can access all functions
      $this->notify_url    = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'BT_beGateway', home_url( '/' ) ) );
      $this->notify_url    = str_replace('carts.local','webhook.begateway.com:8443', $this->notify_url);

      $this->method_title             = $this->title;
      $this->description              = $this->settings['description'];
      $this->shop_id                  = $this->settings['shop-id'];
      $this->transaction_type         = $this->settings['tx-type'];
      $this->secret_key               = $this->settings['secret-key'];
      $this->debug                    = $this->settings['debug'];
      $this->curr_multiplyer          = $this->bt_currency_multiplyer(get_woocommerce_currency());
      $this->show_transaction_table   = $this->settings['show-transaction-table'] == 'yes' ? true : false;
      // Logs
      if ( 'yes' == $this->debug ){
        $this->log = new WC_Logger();
      }

      add_action('admin_menu', array($this, 'bt_admin_hide') );
      add_action('admin_notices',array($this, 'bt_admin_error') );
      add_action('admin_notices',array($this, 'bt_admin_message') );
      add_action('woocommerce_receipt_begateway', array( $this, 'receipt_page'));
      add_action('woocommerce_api_bt_begateway', array( $this, 'check_ipn_response' ) );
      add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

      // display transaction table
      if ( is_admin() && $this->show_transaction_table )
      {
        add_action( 'add_meta_boxes', array($this, 'create_order_transactions_meta_box') );
        //$this->create_order_transactions_meta_box();
      }
    } // end __construct

    public function admin_options()
    {   //check for curl
      $curl_check='';
      if(!function_exists('curl_exec')){
        $curl_check.='</h3><h3>
          PHP cURL does not appear to be enabled on your server: please verify.';
      }
      //end check for curl
      echo '<h3>' . __('beGateway'.$curl_check, 'woocommerce-begateway') . '</h3>';
      echo '<table class="form-table">';
      // generate the settings form.
      $this->generate_settings_html();
      echo '</table><!--/.form-table-->';
    } // end admin_options()


    public function init_form_fields()
    {
      // transaction options
      $tx_options = array('payment' => __('Payment', 'woocommerce-begateway'), 'authorization' => __('Authorization', 'woocommerce-begateway'));

      $this->form_fields = array(
        'enabled' => array(
          'title' => __( 'Enable/Disable', 'woocommerce-begateway' ),
          'type' => 'checkbox',
          'label' => __( 'Enable beGateway', 'woocommerce-begateway' ),
          'default' => 'yes'
        ),
        'title' => array(
          'title' => __( 'Title', 'woocommerce-begateway' ),
          'type' => 'text',
          'description' => __( 'This is the title displayed to the user during checkout.', 'woocommerce-begateway' ),
          'default' => __( 'Credit or debit card', 'woocommerce-begateway' )
        ),
        'admin_title' => array(
          'title' => __( 'Admin Title', 'woocommerce-begateway' ),
          'type' => 'text',
          'description' => __( 'This is the title displayed to the admin user', 'woocommerce-begateway' ),
          'default' => __( 'beGateway', 'woocommerce-begateway' )
        ),
        'description' => array(
          'title' => __( 'Description', 'woocommerce-begateway' ),
          'type' => 'textarea',
          'description' => __( 'This is the description which the user sees during checkout.', 'woocommerce-begateway' ),
          'default' => __("VISA, Mastercard", 'woocommerce-begateway')
        ),
        'shop-id' => array(
          'title' => __( 'Shop ID', 'woocommerce-begateway' ),
          'type' => 'text',
          'description' => __( 'Please enter your Shop Id.', 'woocommerce-begateway' ),
          'default' => ''
        ),
        'secret-key' => array(
          'title' => __( 'Secret Key', 'woocommerce-begateway' ),
          'type' => 'text',
          'description' => __( 'Please enter your Shop secret key.', 'woocommerce-begateway' ),
          'default' => ''
        ),
        'domain-gateway' => array(
          'title' => __( 'Payment gateway domain', 'woocommerce-begateway' ),
          'type' => 'text',
          'description' => __( 'Please enter payment gateway domain of your payment processor.', 'woocommerce-begateway' ),
          'default' => ''
        ),
        'domain-checkout' => array(
          'title' => __( 'Payment page domain', 'woocommerce-begateway' ),
          'type' => 'text',
          'description' => __( 'Please enter payment page domain of your payment processor.', 'woocommerce-begateway' ),
          'default' => ''
        ),
        'tx-type'      => array(
          'title' => __('Transaction Type', 'woocommerce-begateway'),
          'type' => 'select',
          'options' => $tx_options,
          'description' => __( 'Select Payment (Authorization & Capture)  or Authorization.', 'woocommerce-begateway' )
        ),

        'show-transaction-table' => array(
          'title' => __('Enable admin capture etc.', 'woocommerce-begateway'),
          'type' => 'checkbox',
          'label' => __('Show Transaction Table', 'woocommerce-begateway'),
          'description' => __( 'Allows admin to send capture/void/refunds', 'woocommerce-begateway' ),
          'default' => 'yes'
        ),

        'debug' => array(
          'title' => __( 'Debug Log', 'woocommerce-begateway' ),
          'type' => 'checkbox',
          'label' => __( 'Enable logging', 'woocommerce-begateway' ),
          'default' => 'no',
          'description' =>  __( 'Log events', 'woocommerce-begateway' ),
        )

      );
    } // end init_form_fields()

    function generate_begateway_form( $order_id ) {
      //creates a self-submitting form to pass the user through to the beGateway server
      global $woocommerce;
      $order = new WC_order( $order_id );
      if ( 'yes' == $this->debug ){
        $this->log->add( 'begateway', 'Generating payment form for order ' . $order->get_order_number()  );
      }
      // Order number & Cart Contents for description field - may change
      $item_loop = 0;
      //grab the langauge

      $lang = explode('-', get_bloginfo('language'));
      $lang = $lang[0];

      $possible_langauge_array=array('en','es','tr','de','it','ru','zh','fr');
      if(in_array($lang,$possible_langauge_array)) {
        $language=$lang;
      } else {
        $language='en';
      }

      $payment_request = array(
        "checkout" => array (
          "transaction_type"=>$this->transaction_type,
          "order"    => array(
            "amount"		=> $this->curr_multiplyer * $order->order_total,
            "currency"	=>  get_woocommerce_currency(),
            "description"	=> __('Order', 'woocommerce') . ' # ' .$order->get_order_number(),
            "tracking_id"	=> ltrim( $order->get_order_number(), '#' )),
          "customer" => array (
            "first_name"	=> $order->billing_first_name,
            "last_name"	=> $order->billing_last_name,
            "country"	=> $order->billing_country,
            "city"	=> $order->billing_city,
            "state"	=> $order->billing_state,
            "phone"	=> $order->billing_phone,
            "zip"		=> $order->billing_postcode,
            "address"	=> $order->billing_address_1. $order->billing_address_2,
            "email"	=> $order->billing_email
          ),
          "settings" => array(
            "success_url" => $this->get_return_url( $order) ,
            "decline_url" =>  $order->get_cancel_order_url(),
            "fail_url" =>  $order->get_cancel_order_url(),
            "cancel_url" =>  $order->get_cancel_order_url(),
            "notification_url" => $this->notify_url,
            "language" => $language,
            "customer_fields" => array(
              "hidden" => array()
            )
          )
        )
      );

      //the native WP transport class is refusing to handle POST data to the server so we're
      //going to substitute PHP Curl
      //check for curl - this should already have been done in the admin setup
      if ( 'yes' == $this->debug ){
        $this->log->add( 'begateway', 'Requesting token for order ' . $order->get_order_number()  );
      }

      if(function_exists('curl_exec'))
      {
        $process = curl_init($this->token_url);
        curl_setopt($process, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
        curl_setopt($process, CURLOPT_URL,$this->token_url);
        curl_setopt($process, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
        curl_setopt($process, CURLOPT_TIMEOUT, 59);
        curl_setopt($process, CURLOPT_POST, 1);
        curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($payment_request) );
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($process);

        curl_close($process);

        $result_array=json_decode($response,true);
        $error_to_show=$response;

      }//end of curl check
      else{//no curl show a generic error
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Unable to use CURL on order: ' . $order_id  );
        }
        $woocommerce->add_error(__('Unable to contact the payment server at this time. Please try later.'));
        exit();
      }

      //now look to the result array for the token
      if(isset($result_array['checkout']['token'])){
        $token=$result_array['checkout']['token'];
        $payment_url="https://" . $this->settings['domain-checkout'] . "/checkout?token=".$token;
        update_post_meta(  ltrim( $order->get_order_number(), '#' ), '_Token', $token );
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Token received, forwarding customer to: '.$payment_url);
        }
      } else{
        $woocommerce->add_error(__('Payment error: '.$error_to_show));
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Payment error order ' . $order_id.'  '.$error_to_show  );
        }
        exit('Sorry - there was an error contacting the bank server, please try later');
      }

      wc_enqueue_js('
        jQuery("body").block({
          message: "'.__('Thank you for your order. We are now redirecting you to make the payment.', 'woocommerce-begateway').'",
            overlayCSS:
        {
          background: "#fff",
            opacity: 0.6
        },
        css: {
          padding:        20,
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:		"32px"
        }
        });
        jQuery("#submit_begateway_payment_form").click();
      ');
      return '<form action="'.$payment_url.'" method="post" id="begateway_payment_form">
        <input type="submit" class="button-alt" id="submit_begateway_payment_form" value="'.__('Make payment', 'woocommerce-begateway').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woothemes').'</a>
        </form>';
    }

    function process_payment( $order_id ) {
      global $woocommerce;

      $order = new WC_Order( $order_id );

      // Return payment page
      return array(
        'result'    => 'success',
        'redirect'	=> add_query_arg('key', $order->order_key, $order->get_checkout_payment_url( true ))
      );
    }
    // end process_payment

    function receipt_page( $order ) {

      echo $this->generate_begateway_form( $order );

    }

    function thankyou_page()
    {
      if ($this->description) echo wpautop(wptexturize($this->description));
    } // end thankyou_page


    public function create_order_transactions_meta_box()
    {
      //add a metabox
      add_meta_box( 'bt-begateway-order-transaction-content',
        $this->title,
        array(&$this, 'order_transaction_content_meta_box'),
        'shop_order', 'normal', 'default');
    }// end meta_box_order_transactions

    public function order_transaction_content_meta_box($post) {
      //wordpress strips <form> tags so you cannot send POST data instead you have to
      //make up a GET url and append the amount field using jQuery

      $order = new WC_Order($post->ID);
      $display="";

      //WordPress will also mutilate or just plain kill any PHP Sessions setup so, instead of passing the return URL that way we'll pop it into a post meta
      update_post_meta($post->ID,'_return_url', $this->curPageURL());

      switch ( $order->status){
      case 'pending':
        $display.=__('Order currently pending no capture/refund available', 'woocommerce-begateway');
        break;
      case 'on-hold':
        //params for capture
        $arr_params = array( 'wc-api' => 'BT_beGateway', 
          'begateway' => 'capture',
          'uid' => md5(get_post_meta($post->ID, '_uid', true)),
          'oid' => $post->ID );
        $display.="<script>
          function addURL(element)
          {
            jQuery(element).attr('href', function() {
              return this.href + '&amount='+ jQuery('#bt_amount').val();
            });
          }
          </script>";
        $display.=__('Payment has been authorised', 'woocommerce-begateway').'<br/>'.
                  __('Please enter amount to capture','woocommerce-begateway').' '.get_woocommerce_currency_symbol().'
                  <input type="text" id="bt_amount" size="8" value="'. ($order->get_total() ).'" />
                <a  onclick="javascript:addURL(this);"  href="'.str_replace( 'https:', 'http:', add_query_arg( $arr_params, home_url( '/' ) ) ).'"><button type="button">'.__('Capture','woocommerce-begateway').'</button></a>';
        //params for void
         $arr_params = array( 'wc-api' => 'BT_beGateway',
           'begateway' => 'void',
           'uid' => md5(get_post_meta($post->ID, '_uid', true)),
           'oid' => $post->ID );
         $display.="<script>
           function addVoidURL(element)
           {
             jQuery(element).attr('href', function() {
               return this.href + '&amount='+ jQuery('#bt_void_amount').val();
             });
           }
           </script>";
         $display.='<br/>
                    '.__('Please enter amount to void','woocommerce-begateway').' '.get_woocommerce_currency_symbol().'
                    <input type="text" id="bt_void_amount" size="8" value="'. ($order->get_total() ).'" />
                    <a  onclick="javascript:addVoidURL(this);"  href="'.str_replace( 'https:', 'http:', add_query_arg( $arr_params, home_url( '/' ) ) ).'"><button type="button">'.__('Void','woocommerce-begateway').'</button></a>';

         break;
      case 'processing':
        //params for refund
        $arr_params = array( 'wc-api' => 'BT_beGateway',
          'begateway' => 'refund',
          'uid' => md5(get_post_meta($post->ID, '_uid', true)),
          'oid' => $post->ID );
        $display.="<script>
          function addRefundURL(element)
          {
            jQuery(element).attr('href', function() {
              return this.href + '&amount='+ jQuery('#bt_refund_amount').val();
            });
          }
          </script>";
        $display.='<br/>
                   '.__('Please enter amount to refund','woocommerce-begateway').' '.get_woocommerce_currency_symbol().'
                   <input type="text" id="bt_refund_amount" size="8" value="'. ($order->get_total()).'" />
                   <a  onclick="javascript:addRefundURL(this);"  href="'.str_replace( 'https:', 'http:', add_query_arg( $arr_params, home_url( '/' ) ) ).'"><button type="button">Refund</button></a>';
        break;

      default:
        $display.='';
        break;
      }
      echo '<div class="panel-wrap woocommerce">';
      echo $display;
      echo '</div>';

    }// end order_transaction_content_meta_box


    private function plugin_url()
    {
        return $this->plugin;
    }// end plugin_url

    /**
     *this function is called via the wp-api when the begateway server sends
     *callback data
    */
    function check_ipn_response() {
      //check for refund/capture/void
      if (isset($_GET['begateway']) && isset($_GET['uid']) &&   isset($_GET['oid'])){

        switch ($_GET['begateway']){

          case 'refund':
            //do refund;
            $this->refund($_GET['uid'], $_GET['oid'], $_GET['amount']*$this->curr_multiplyer);
            break;
          case 'capture':
              //do capture only for full amount at present
            $this->capture($_GET['uid'], $_GET['oid'], $_GET['amount']*$this->curr_multiplyer);
            break;
          case 'void':
              //do void only for full amount at present
            $this->void($_GET['uid'], $_GET['oid'], $_GET['amount']*$this->curr_multiplyer);
            break;
          default:
            exit();
            }
        exit();
      }
      //end if (isset($_GET['begateway']) - do normal callback response

      global $woocommerce;
      @ob_clean();
      $transaction_results=array();
      //get the incoming data
      $putdata = fopen("php://input", "r");
      $transaction_results = json_decode(stream_get_contents($putdata),true);
      fclose($putdata);
      //log
      if ( "yes" == $this->debug ){
        $display="\n-------------------------------------------\n";
        $display.= "Order No: ".$transaction_results['transaction']['tracking_id'];
        $display.= "\nUID: ".$transaction_results['transaction']['uid'];
        $display.="\n--------------------------------------------\n";
        $this->log->add( "begateway", $display  );
      }

      if ( ! empty( $transaction_results['transaction']['tracking_id'] ) ) {
        $this->order_query( $transaction_results['transaction']['tracking_id'] );

      } else {
        if ( "yes" == $this->debug ){
          $display="\n----------- Unable to proceed --------------\n";
          $display.= "Order No: ".$transaction_results['transaction']['tracking_id'];
          $display.="\n--------------------------------------------\n";
          $this->log->add( "begateway", $display  );
        }
        wp_die( "beGateway Notify Failure" );
      }
    }
    //end of check_ipn_response

    function order_query($order_id) {

      global $woocommerce;
      //takes order number and fires a request off to begateway to get the result
      //file_get_contents is not sending auth headers so we'll use curl
      $token= get_post_meta($order_id, '_Token', true);
      $query_url=$this->token_url.'/'.$token;
      //Debug log
      if ( 'yes' == $this->debug ){
        $this->log->add( 'begateway', 'Checking transaction data  for order ' . $order_id . ' on url '.$query_url );
      }
      //end debug log
      if(function_exists('curl_exec'))
      {
        $process = curl_init($query_url);
        curl_setopt($process, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
        curl_setopt($process, CURLOPT_URL,$query_url);
        curl_setopt($process, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        // curl_setopt($process, CURLOPT_POST, 1);
        // curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($payment_request) );
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($process);

        curl_close($process);

        $result_array=json_decode($response,true);
        $error_to_show=$response;

      } else {//no curl show a generic error
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Unable to use CURL on callback for order: ' . $order_id  );
        }
        $woocommerce->add_error(__('Unable to contact the payment server at this time. Please try later - your items are still in your cart','woocommerce-begateway'));
        exit();
      }

      $order = new WC_Order( $order_id );

      //now look to the result array for the response
      if(isset($result_array['checkout']['gateway_response']['payment'])){
        //payment received save the uid
        update_post_meta(  $order_id, '_uid', $result_array['checkout']['gateway_response']['payment']['uid'] );
        //determine status
        $status=$result_array['checkout']['gateway_response']['payment']['status'];
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Payment status '.$status.' UID:'.$result_array['checkout']['gateway_response']['payment']['uid']);
        }

        switch($status){
        case 'successful':
          $order->add_order_note( __('Payment success:', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')'.'<br />' );
          $order->payment_complete();
          break;
        case 'failed':
          $order->update_status( 'failed', $status.'Payment  failed. UID: '.$result_array['checkout']['gateway_response']['authorization']['uid'].'<br />' );
          break;
        case 'incomplete':
          $order->add_order_note( __('Payment incomplete, order status not updated', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')'.'<br />' );	
          break;
        case 'error':
          $order->add_order_note( __('Payment error, order status not updated', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')'.'<br />' );	
        default : //should not get here but just in case
          $order->add_order_note( __('Callback error, order status not updated', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')'.'<br />' );	            
        }
      } elseif(isset($result_array['checkout']['gateway_response']['authorization'])){

        //auth received save the uid
        update_post_meta(  $order_id, '_uid', $result_array['checkout']['gateway_response']['authorization']['uid'] );
        //determine status
        $status=$result_array['checkout']['gateway_response']['authorization']['status'];
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Authorization status '.$status.' UID:'.$result_array['checkout']['gateway_response']['authorization']['uid']);
        }

        switch($status){
        case 'successful':
          $order->update_status( 'on-hold', 'Payment authorised. UID: '.$result_array['checkout']['gateway_response']['authorization']['uid'].'<br />' ); 
          break;
        case 'failed':   //payment declined

          $order->update_status( 'failed', $status.'Payment authorisation failed. UID: '.$result_array['checkout']['gateway_response']['authorization']['uid'].'<br />' );     
          break;
        case 'incomplete':
          $order->add_order_note( __('Authorisation incomplete, order status not updated.', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')'.'<br />' );	
          break;
        case 'error':
          $order->add_order_note( __('Authorisation error, order status not updated', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')' .'<br />');	
        default : //should not get here but just in case
          $order->add_order_note( __('Callback error, order status not updated', 'woocommerce-begateway') . ' (UID: ' .$result_array['checkout']['gateway_response']['payment']['uid'] . ')'.'<br />' );	  
        }
      } else {
        //no data
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Invalid or no response data from server query');
        }
      }
    }//end function


    function capture($uid, $order_id, $amount_to_capture){
      global $woocommerce;
      $capture_error='no';
      $order = new WC_order( $order_id );
      //get the uid from order and compare to md5 in the $_GET
      $post_uid = get_post_meta($order_id,'_uid',true);
      $check_uid=md5($post_uid);
      if ($check_uid != $uid){exit ('uid not correct');}
      //check order status is on hold exit if not
      if ($order->status !='on-hold'){exit ('wrong status capture not possible');}
      // now send data to the server

      $capture_request = array(
        'request' => array(
          'parent_uid'=> $post_uid,
          'amount'=>(int) $amount_to_capture
        )
      );

      $process = curl_init($this->capture_url);
      curl_setopt($process, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
      curl_setopt($process, CURLOPT_URL,$this->capture_url);
      curl_setopt($process, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
      curl_setopt($process, CURLOPT_TIMEOUT, 30);
      curl_setopt($process, CURLOPT_POST, 1);
      curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($capture_request) );
      curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
      $response = curl_exec($process);

      curl_close($process);

      $result_array=json_decode($response,true);
      $error_to_show=$response;

      //determine status if success
      if($result_array['transaction']['status'] == 'successful'){
        $order->payment_complete();
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Capture status '.$result_array['transaction']['message']);
        }
        update_post_meta($order_id,'_bt_admin_message','Capture status '.$result_array['transaction']['message']);
        update_post_meta($order_id,'_uid',$result_array['transaction']['uid']);
      }else{
        //do nothing if not complete
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Capture attempt failed. Server message: '.$result_array['response']['message']);
        }
        update_post_meta($order_id,'_bt_admin_error','Capture attempt failed. Server message: '.$result_array['response']['message']);
      }
      $location = get_post_meta($order_id, '_return_url', true);
      delete_post_meta($order_id, '_return_url', true);

      //exit('<pre>'.print_r($result_array));
      header ('Location:'.  $location);
      exit();
    }

    function bt_admin_error(){
      if(isset($_GET['post']))
      {
        if(get_post_meta($_GET['post'],'_bt_admin_error'))
        {
          $error=get_post_meta($_GET['post'],'_bt_admin_error',true);
          delete_post_meta($_GET['post'],'_bt_admin_error');
          echo '<div class="error">
            <p>'.$error.'</p>
            </div>';
        }
      }
    }

    function bt_admin_message(){
      if(isset($_GET['post']))
      {
        if(get_post_meta($_GET['post'],'_bt_admin_message'))
        {
          $message=get_post_meta($_GET['post'],'_bt_admin_message',true);
          delete_post_meta($_GET['post'],'_bt_admin_message');
          echo '<div class="updated">
            <p>'.$message.'</p>
            </div>';
        }
      }
    }

    function bt_admin_hide(){
      //  remove_meta_box('postcustom', 'page', 'normal');
    }


    // Now we set that function up to execute when the admin_notices action is called

    function void($uid, $order_id,$amount_to_void){
      global $woocommerce;
      $void_error='no';
      $order = new WC_order( $order_id );
      //get the uid from order and compare to md5 in the $_GET
      $post_uid = get_post_meta($order_id,'_uid',true);
      $check_uid=md5($post_uid);
      if ($check_uid != $uid){exit ('uid not correct');}
      //check order status is on hold exit if not
      if ($order->status !='on-hold'){exit ('wrong status void not possible');}
      // now send data to the server
      $void_request = array(
        'request' => array(
          'parent_uid'=> $post_uid,
          'amount'=> (int)$amount_to_void
        )
      );

      $process = curl_init($this->void_url);
      curl_setopt($process, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
      curl_setopt($process, CURLOPT_URL,$this->void_url);
      curl_setopt($process, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
      curl_setopt($process, CURLOPT_TIMEOUT, 30);
      curl_setopt($process, CURLOPT_POST, 1);
      curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($void_request) );
      curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
      $response = curl_exec($process);

      curl_close($process);

      $result_array=json_decode($response,true);
      $error_to_show=$response;


      //determine status if success then cancel order
      if($result_array['transaction']['status'] == 'successful'){
        $order->update_status( 'cancelled', 'Payment voided.' );
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Payment voided');                          }
        update_post_meta($order_id,'_bt_admin_message','Payment voided, order cancelled');
      }else{
        //do nothing if not complete

        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Void attempt failed. Server message: '.$result_array['response']['message']);
        }
        update_post_meta($order_id,'_bt_admin_error','Void attempt failed. Server message: '.$result_array['response']['message']);
      }
      $location = get_post_meta($order_id, '_return_url', true);
      delete_post_meta($order_id, '_return_url', true);

      //exit('<pre>'.print_r($result_array));
      header ('Location:'.  $location);
      exit();
    }

    function refund($uid, $order_id, $amount_to_refund){
      global $woocommerce;
      $refund_error='no';
      $order = new WC_order( $order_id );
      //get the uid from order and compare to md5 in the $_GET
      $post_uid = get_post_meta($order_id,'_uid',true);
      $check_uid=md5($post_uid);
      if ($check_uid != $uid){exit ('uid not correct');}
      //check order status is on hold exit if not
      if ($order->status !='processing'){exit ('wrong status refund not possible');}
      //admin user
      $user_info = get_userdata(1);
      $admin_name = $user_info->first_name.' '. $user_info->last_name;
      // now send data to the server

      $refund_request = array(
        'request' => array(
          'parent_uid'=> $post_uid,
          'amount'=> (int)$amount_to_refund,
          'reason'=> 'Woocommerce admin refund by '.$admin_name
        )
      );
      $process = curl_init($this->refund_url);
      curl_setopt($process, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
      curl_setopt($process, CURLOPT_URL,$this->refund_url);
      curl_setopt($process, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
      curl_setopt($process, CURLOPT_TIMEOUT, 30);
      curl_setopt($process, CURLOPT_POST, 1);
      curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($refund_request) );
      curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
      $response = curl_exec($process);

      curl_close($process);

      $result_array=json_decode($response,true);
      $error_to_show=$response;

      //exit('<pre>'.print_r($result_array));

      //determine status if success then cancel order
      if($result_array['transaction']['status'] == 'successful'){
        $order->update_status( 'refunded', 'Payment refunded.' );
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Refund status '.$result_array['transaction']['message']);
        }
        update_post_meta($order_id,'_bt_admin_message','Refund status '.$result_array['transaction']['message']);
      }else{
        if ( 'yes' == $this->debug ){
          $this->log->add( 'begateway', 'Refund attempt failed. Server message: '.$result_array['response']['message'].$result_array['errors']['base'][0]);
        }
        update_post_meta($order_id,'_bt_admin_error','Refund attempt failed. Server message: '.$result_array['response']['message'].$result_array['errors']['base'][0]);
      }


      $location = get_post_meta($order_id, '_return_url', true);
      delete_post_meta($order_id, '_return_url', true);

      //exit('<pre>'.print_r($result_array));
      header ('Location:'.  $location);

    }



    function curPageURL() {
      $pageURL = 'http';
      if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
      $pageURL .= "://";
      if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
      } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
      }
      $pageURL=rtrim($pageURL,'&message=1');
      return $pageURL;
    }


    function bt_begateway_curl ($curl_url,$curl_data) {
      $process = curl_init($curl_url);
      curl_setopt($process, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-type: application/json'));
      curl_setopt($process, CURLOPT_URL, $curl_url);
      curl_setopt($process, CURLOPT_USERPWD, $this->shop_id . ":" . $this->secret_key);
      curl_setopt($process, CURLOPT_TIMEOUT, 30);
      curl_setopt($process, CURLOPT_POST, 1);
      curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($curl_data) );
      curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
      $response = curl_exec($process);

      curl_close($process);

      return json_decode($response);
    }

    //function to get the currency multiplyer to reduce currency to base units - the default is 100 (e.g. USD, GBP,EUR) but we have a list here of other currencies to be checked 
    function bt_currency_multiplyer($order_currency){
      //array currency code => mutiplyer
      $exceptions=array(
        'BIF'=>1,
        'BYR'=>1,
        'CLF'=>1,
        'CLP'=>1,
        'CVE'=>1,
        'DJF'=>1,
        'GNF'=>1,
        'IDR'=>1,
        'IQD'=>1,
        'IRR'=>1,
        'ISK'=>1,
        'JPY'=>1,
        'KMF'=>1,
        'KPW'=>1,
        'KRW'=>1,
        'LAK'=>1,
        'LBP'=>1,
        'MMK'=>1,
        'PYG'=>1,
        'RWF'=>1,
        'SLL'=>1,
        'STD'=>1,
        'UYI'=>1,
        'VND'=>1,
        'VUV'=>1,
        'XAF'=>1,
        'XOF'=>1,
        'XPF'=>1,
        'MOP'=>10,
        'BHD'=>1000,
        'JOD'=>1000,
        'KWD'=>1000,
        'LYD'=>1000,
        'OMR'=>1000,
        'TND'=>1000
      );
      $multiplyer=100;//default value
      foreach($exceptions as $key=>$value)
      {
        if(($order_currency==$key))
        {
          $multiplyer=$value;
          break;
        }
      }
      return $multiplyer; 
    }
  }//end of class

  if(is_admin())
    new BT_beGateway();
}

//add to gateways
function bt_begateway_add_gateway( $methods )
{
    $methods[] = 'BT_beGateway';
    return $methods;
}
?>
