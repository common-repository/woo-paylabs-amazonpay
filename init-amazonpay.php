<?php
/*
 * Plugin Name: Amazon Pay WooCommerce payment gateway
 * Plugin URI: https://wordpress.org/plugins/woo-paylabs-amazonpay/
 * Description: Amazon Pay WooCommerce payment gateway trusted way to pay. Single, Subscription and Recurring Payments, Refund with Amazon Pay from WP Admin, Add WooCommerce Subscription Products, Revenue with automatic Recurring Payments, Customized Order status Subscription & Recurring and more Payment options developed by PluginStar.
 * Version: 2.9
 * Author: Subhadeep Mondal
 * Author URI: https://www.linkedin.com/in/subhadeep-mondal
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 2.0.0
 * WC tested up to: 3.8
 * Text Domain: woo-paylabs-amazonpay
 * Domain Path: /languages
 */
if (!defined('ABSPATH')) exit;
Class Ps_Paylabs_Amazonpay
{
    /**
    * Amazon Pay __construct() function.
    *
    */
    public function __construct()
    {
        define('PSAP_VER', '2.9');
        define('PSAP_VER_CSS', '1.0');
        define('PSAP_PATH', plugin_dir_path(__FILE__));
        define('PSAP_URL', plugin_dir_url(__FILE__));
        define('PSAP_BASENAME', plugin_basename(__FILE__));
        
        require_once(PSAP_PATH . 'lab-inc/ApiCall.php');
        $this->ApiCall                   = new AmazonPay\ApiCall;
        $settings                  = get_option('woocommerce_wpl_paylabs_amazonpay_settings');
        if(!empty($settings))
        {
            $settings['seller_region'] = $this->wpl_ps_amazonpay_get_region($settings['seller_currency']);
            $this->config                    = array(
                'merchant_id' => $settings['seller_id'],
                'access_key' => $settings['access_key'],
                'secret_key' => $settings['secret_key'],
                'client_id' => $settings['client_id'],
                'region' => $settings['seller_region'],
                'sandbox' => $settings['testmode'] == 'yes' ? true : false
            );
            $this->seller_currency = $settings['seller_currency'];
        }
        
       register_deactivation_hook(__FILE__, array(
            $this,
            'wpl_ps_amazonpay_deactivation'
        ));
        add_action('plugins_loaded', array(
            $this,
            'wpl_ps_amazonpay_init_gateway'
        ));
        add_action( 'plugins_loaded', array($this,'wpl_ps_amazonpay_load_textdomain' ));
        add_action( 'ps_subscription_repeat', array(
            $this,
            'wpl_ps_subscription_repeat_for_recurring'
        ));
         add_action( 'ps_subscription_order_valid', array(
            $this,
            'wpl_ps_ChkSubscriptionOrderValid'
        ));
        if (get_option('_ps_api_key')) 
        {
            add_action( 'ps_remove_activation_key', array(
                $this,
                'wpl_ps_remove_expired_activation_key'
            ));
        }
        $this->wpl_ps_init();
    }// Amazon Pay __construct() function end
    
    /**
    * Amazon Pay init gateway and include WC_Payment_Gateway class wpl_ps_amazonpay_init_gateway()
    *
    */
    public function wpl_ps_amazonpay_init_gateway()
    {
        if (!class_exists('WC_Payment_Gateway'))
            return;
        require_once(PSAP_PATH . 'lab-inc/amazonpay.functions.php');
    }// Amazon Pay init gateway and include WC_Payment_Gateway class wpl_ps_amazonpay_init_gateway() end
    
    /**
    * Initialize the plugin wpl_ps_init()
    *
    */
    public function wpl_ps_init()
    { 
        add_filter('woocommerce_payment_gateways', array(
            $this,
            'wpl_ps_add_paylabs_amazonpay_gateway'
        ));
         add_action('admin_head', array(
            $this,
            'wpl_ps_custom_style'
        ));
        
        if (get_option('_ps_api_key')) /// Active for PRO Version 
        {
            add_filter('woocommerce_product_data_tabs', array(
                $this,
                'wpl_ps_add_subscription_product_data_tab'
                ), 99, 1);
            add_action('woocommerce_product_data_panels', array(
                $this,
                'wpl_ps_add_subscription_product_data_fields'
            ));
            add_action('woocommerce_process_product_meta', array(
                $this,
                'wpl_ps_woocommerce_process_product_meta_fields_save'
            ));
            add_filter('product_type_options', array(
                $this,
                'wpl_ps_add_subscription_product_option'
            ));
            add_action('woocommerce_after_cart_table', array(
                $this,
                'wpl_ps_custom_cart_totals_after_order_total'
            ));
            add_filter('woocommerce_update_cart_validation', array(
                $this,
                'wpl_ps_only_one_subscription_allowed_cart_update'
            ) ,10, 4);
            add_filter('woocommerce_add_to_cart_validation', array(
                $this,
                'wpl_ps_only_one_subscription_allowed_add_to_cart'
            ));
            add_action( 'woocommerce_order_status_changed', array( $this, 'wpl_psap_woocommerce_order_status_changed' ), 10, 4);
       
            add_filter('woocommerce_register_shop_order_post_statuses', array(
                $this,
                'wpl_ps_register_custom_order_status'
            ));
            add_filter('wc_order_statuses', array(
                $this,
                'wpl_ps_show_custom_order_status'
            ));
            add_filter('bulk_actions-edit-shop_order', array(
                $this,
                'wpl_ps_get_custom_order_status_bulk'
            ));
        }

        if (is_admin())  /// All function works on WP Admin 
        {
            add_action('admin_enqueue_scripts', array(
                $this,
                'wpl_ps_my_enqueue'
            ));
            add_filter('plugin_action_links_'.PSAP_BASENAME, array(
                $this,
                'wpl_ps_amazonpay_add_action_links'
            ));
            add_filter( 'plugin_row_meta', array($this, 'wpl_psap_plugin_row_meta' ), 10, 2 );
            add_action( 'admin_notices',  array(
                $this,
                'wpl_ps_notice_pro_version'
            ));
            add_action('wp_ajax_auth_req', array(
                $this,
                'wpl_ps_auth_req_api'
            ));
            add_action('wp_ajax_auth_del', array(
                $this,
                'wpl_ps_auth_del_api'
            ));
        }
        $this->wpl_ps_cron_job(); /// Add cron job for this plugin 
    }// Initialize the plugin wpl_ps_init()
   
    /**
    * load textdomain for localization languages wpl_ps_amazonpay_load_textdomain()
    *
    */
    function wpl_ps_amazonpay_load_textdomain() 
    {
          load_plugin_textdomain( 'woo-paylabs-amazonpay', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
    }// load textdomain for localization languages wpl_ps_amazonpay_load_textdomain() end
   
    /**
    * Deactivation of this plugin wpl_ps_amazonpay_deactivation()
    *
    */
    public function wpl_ps_amazonpay_deactivation()
    {
        $this->wpl_ps_clear_cron_job();
    }// Deactivation of this plugin wpl_ps_amazonpay_deactivation() end
    
    /**
    * Uninstall this plugin from website wpl_ps_amazonpay_uninstall()
    *
    */
    public function wpl_ps_amazonpay_uninstall()
    {
        delete_option('_ps_api_key');
        delete_option('_ps_api_key_check');
        delete_option('_ps_api_key_notice');
        delete_option('woocommerce_wpl_paylabs_amazonpay');
        delete_option('woocommerce_wpl_paylabs_amazonpay_settings');
    }// Uninstall this plugin from website wpl_ps_amazonpay_uninstall() end

    /**
    * Admin Notice for PRO version expire date wpl_ps_notice_pro_version()
    *
    */
    public function wpl_ps_notice_pro_version() 
    {
        if (get_option('_ps_api_key_notice') > 0)
        {
            $class = 'notice notice-warning is-dismissible';
            $message = __('Amazon Pay PluginStar PRO version will expire in','woo-paylabs-amazonpay');
            sprintf('<div class="%1$s"><strong><p>%2$s %3$s %4$s</p></strong></div>', $class, $message,
                                __( get_option('_ps_api_key_notice'), 'woo-paylabs-amazonpay' ),
                                __( 'day(s)', 'woo-paylabs-amazonpay' )
                            );
        }
        $this->wpl_amazonpay_UpdateOnholdOrders();
    }// Admin Notice for PRO version expire date wpl_ps_notice_pro_version() end
    
    /**
    * Wpl PayLabs WC Amazonpay method add wpl_ps_add_paylabs_amazonpay_gateway()
    *
    */
    public function wpl_ps_add_paylabs_amazonpay_gateway($methods)
    {
        $methods[] = 'Wpl_PayLabs_WC_Amazonpay';
        return $methods;
    }// Wpl PayLabs WC Amazonpay method add wpl_ps_add_paylabs_amazonpay_gateway() end

    /**
    * Add Option on plugin list action link wpl_ps_amazonpay_add_action_links()
    *
    */
    public function wpl_ps_amazonpay_add_action_links($links)
    {
        $mylinks = array(
            '<a href="'.admin_url('admin.php?page=wc-settings&tab=checkout&section=wpl_paylabs_amazonpay').'"><b>' . esc_html__('Settings', 'woo-paylabs-amazonpay') . '</b></a>',
            '<a href="'.esc_url('https://pluginstar.in/get-key/').'" target="_blank"><span style="color:#27c300;"><b>' . esc_html__('PRO Version', 'woo-paylabs-amazonpay') . '</b></span></a>',
        );
        return array_merge($mylinks, $links);
    }// Add Option on plugin list action link wpl_ps_amazonpay_add_action_links() end
   
    /**
    * Add Option on plugin list Meta section wpl_psap_plugin_row_meta()
    *
    */
    public function wpl_psap_plugin_row_meta( $links, $file ) 
    {
        if ( PSAP_BASENAME === $file ) 
        {
            $row_meta = array(
            'api_key'    => '<a href="'.esc_url('https://pluginstar.in/get-key/').'" target="_blank">' . esc_html__( 'API Key', 'woo-paylabs-amazonpay' ) . '</a>',
            'support'    => '<a href="'.esc_url('https://pluginstar.in/contact/').'" target="_blank">' . esc_html__( 'Support', 'woo-paylabs-amazonpay' ) . '</a>',
            );
            return array_merge( $links, $row_meta );
        }
        return (array) $links;
    }// Add Option on plugin list Meta section wpl_psap_plugin_row_meta() end
    
    /**
    * Subscription option on product tab wpl_ps_add_subscription_product_data_tab()
    *
    */
    public function wpl_ps_add_subscription_product_data_tab($product_data_tabs)
    {
        $product_data_tabs['subscription'] = array(
            'class' => array(
                'show_if_ps_subscription_chkbox'
            ),
            'label' => __('Subscription', 'woo-paylabs-amazonpay'),
            'target' => 'subscription_product_data'
        );
        return $product_data_tabs;
    }//Subscription option on product tab wpl_ps_add_subscription_product_data_tab() end
    
    /**
    * Subscription option on product tab fields wpl_ps_add_subscription_product_data_fields()
    *
    */
    public function wpl_ps_add_subscription_product_data_fields()
    {
        global $woocommerce, $post;
        ?>
        <div id="subscription_product_data" class="panel woocommerce_options_panel">
            <?php
        
        woocommerce_wp_text_input(array(
            'id' => '_ps_subscription_time',
            'wrapper_class' => 'show_if_ps_subscription_chkbox',
            'label' => __('Subscription', 'woo-paylabs-amazonpay'),
            'desc_tip' => false,
            'style' => 'width:150px',
            'description' => __('Set Duration (Ex. 1,7,12,14,30..etc)', 'woo-paylabs-amazonpay')
        ));
        woocommerce_wp_select(array(
            'id' => '_ps_subscription_duration',
            'wrapper_class' => 'show_if_ps_subscription_chkbox',
            'label' => __('Per', 'woo-paylabs-amazonpay'),
            'options' => array(
                'd' => __('Day(s)', 'woo-paylabs-amazonpay'),
                'w' => __('Week(s)', 'woo-paylabs-amazonpay'),
                'm' => __('Month(s)', 'woo-paylabs-amazonpay'),
                'y' => __('Year(s)', 'woo-paylabs-amazonpay')
            ),
            'desc_tip' => false,
            'style' => 'width:150px',
            'description' => __('Set duration time', 'woo-paylabs-amazonpay')
        ));
        woocommerce_wp_text_input(array(
            'id' => '_ps_total_subscription',
            'wrapper_class' => 'show_if_ps_subscription_chkbox',
            'label' => __('Number of total Subscription(s)', 'woo-paylabs-amazonpay'),
            'desc_tip' => false,
            'style' => 'width:150px',
            'description' => __('Leave blank if this subscription is infinite time', 'woo-paylabs-amazonpay')
        ));
        ?>
        </div>
        <?php
    }//Subscription option on product tab fields wpl_ps_add_subscription_product_data_fields() end
   
    /**
    * Subscription woocommerce process product meta fields save on DB wpl_ps_woocommerce_process_product_meta_fields_save()
    *
    */
    public function wpl_ps_woocommerce_process_product_meta_fields_save($post_id)
    {
        $subscription_time     = isset($_POST['_ps_subscription_time']) ? $_POST['_ps_subscription_time'] : '';
        $subscription_duration = isset($_POST['_ps_subscription_duration']) ? $_POST['_ps_subscription_duration'] : '';
        $total_subscription    = (isset($_POST['_ps_total_subscription'])  && $_POST['_ps_total_subscription']!='') ? $_POST['_ps_total_subscription'] : 0;
        update_post_meta($post_id, '_ps_subscription_time', $subscription_time);
        update_post_meta($post_id, '_ps_subscription_duration', $subscription_duration);
        update_post_meta($post_id, '_ps_total_subscription', $total_subscription);
        $is_subscription = isset($_POST['_ps_subscription_chkbox']) ? 'yes' : 'no';
        update_post_meta($post_id, '_ps_subscription_chkbox', $is_subscription);
    }// Subscription woocommerce process product meta fields save on DB wpl_ps_woocommerce_process_product_meta_fields_save() end
   
    /**
    * Custom CSS style added wpl_ps_custom_style()
    *
    */
    public function wpl_ps_custom_style()
    {
        ?><style>
        #description_key_info{border: 1px solid #62d108;padding: 5px;background-color: #d7f3c5; color: #000; margin-top: 10px;}
        #woocommerce-product-data ul.wc-tabs li.subscription_options a:before { font-family: WooCommerce; content: '\e00e'; }
        </style><?php
    }//Custom CSS style added wpl_ps_custom_style() end
   
    /**
    * Add subscription product option wpl_ps_add_subscription_product_option()
    *
    */
    public function wpl_ps_add_subscription_product_option($product_type_options)
    {
        global $woocommerce, $post;
        $product_type_options['ps_subscription_chkbox'] = array(
            'id' => '_ps_subscription_chkbox',
            'wrapper_class' => 'show_if_simple',
            'label' => __('Subscription', 'woo-paylabs-amazonpay'),
            'description' => __('Subscription and recurring payment', 'woo-paylabs-amazonpay'),
            'default' => 'no'
        );
        return $product_type_options;
    }// Add subscription product option wpl_ps_add_subscription_product_option() end
    
    /**
    * Show notice on website if Subscription and Recurring type Payment custom_cart_totals_after_order_total()
    *
    */
    public function wpl_ps_custom_cart_totals_after_order_total()
    {
        foreach (WC()->cart->get_cart() as $cart_item) {
            $newproduct_id                     = $cart_item['product_id'];
            $subscription_newproductnewproduct = get_post_meta($newproduct_id, '_ps_subscription_chkbox', true);
            if ($subscription_newproductnewproduct == 'yes') {
                 printf('<div class="woocommerce-notices-wrapper">
                            <ul class="woocommerce-error" role="alert">
                            <li>' . esc_html__( 'Subscription and Recurring type Payment', 'woo-paylabs-amazonpay' ) . '</li></ul>');
            }
        }
    }// Show notice on website if Subscription and Recurring type Payment custom_cart_totals_after_order_total() end
  
    /**
    * One item will be added if Product type is subscription Payment cart update wpl_ps_only_one_subscription_allowed_cart_update()
    *
    */
    public function wpl_ps_only_one_subscription_allowed_cart_update($passed, $cart_item_key, $values, $updated_quantity )
    {
        $cart_items_count         = WC()->cart->get_cart_contents_count();
        $original_quantity        = $values['quantity'];
        $total_count              = (($cart_items_count - $original_quantity) + $updated_quantity);
        $subscription_chkbox_prev = '';
        $subscription_chkbox      = get_post_meta($product_id, '_ps_subscription_chkbox', true);
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $newproduct_id                     = $cart_item['product_id'];
            $subscription_newproductnewproduct = get_post_meta($newproduct_id, '_ps_subscription_chkbox', true);
            if ($subscription_newproductnewproduct == 'yes') {
                $subscription_chkbox_prev = 'yes';
            }
        }
        if (($cart_items_count >= 1 || $total_count >= 1) && ($subscription_chkbox == 'yes' || $subscription_chkbox_prev == 'yes')) {
            $passed = false;
            wc_add_notice(__("You can't add Subscription more than 1 item in cart", "woo-paylabs-amazonpay"), "error");
        }
        return $passed;
    }// One item will be added if Product type is subscription Payment cart update wpl_ps_only_one_subscription_allowed_cart_update() end
    
    /**
    * Order status change option wpl_psap_woocommerce_order_status_changed()
    *
    */
    function wpl_psap_woocommerce_order_status_changed( $order_id, $status_transition_from, $status_transition_to, $instance ) 
    { 
        if($status_transition_to != 'subscription')
        {
            update_post_meta($order_id, '_ps_subs_valid', 0);
        }
        elseif( get_post_meta($order_id, '_ap_billing_agreement_id', true) !='')
        {
            update_post_meta($order_id, '_ps_subs_valid', 1);
        } 
    }// Order status change option wpl_psap_woocommerce_order_status_changed() end
    
    /**
    * One item will be added if Product type is subscription Payment add to cart wpl_ps_only_one_subscription_allowed_add_to_cart()
    *
    */
    public function wpl_ps_only_one_subscription_allowed_add_to_cart($passed)
    {
        if(isset($_GET['add-to-cart'])) 
            $product_id = $_GET['add-to-cart'];
        elseif(isset($_POST['add-to-cart']))
             $product_id = $_POST['add-to-cart'];
        elseif(isset($_GET['product_id']))
         $product_id = $_GET['product_id'];
        elseif(isset($_POST['product_id']))
         $product_id = $_POST['product_id'];
        $product_id = absint($product_id);
        $quantity          = empty( $_POST['quantity'] ) ? 1 : absint($_POST['quantity']);
        $cart_items_count         = WC()->cart->get_cart_contents_count();
        $total_count              = ($cart_items_count + $quantity);
        $subscription_chkbox_prev = '';
        $subscription_chkbox      = get_post_meta($product_id, '_ps_subscription_chkbox', true);
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $newproduct_id                     = $cart_item['product_id'];
            $subscription_newproductnewproduct = get_post_meta($newproduct_id, '_ps_subscription_chkbox', true);
            if ($subscription_newproductnewproduct == 'yes') {
                $subscription_chkbox_prev = 'yes';
            }
        }
       
        if (($cart_items_count > 1 || $total_count > 1) && ($subscription_chkbox == 'yes' || $subscription_chkbox_prev == 'yes')) {
            $passed = false;
            wc_add_notice(__("You can't add Subscription more than 1 item in cart", "woo-paylabs-amazonpay"), "error");
        }
        return $passed;
    }// One item will be added if Product type is subscription Payment add to cart wpl_ps_only_one_subscription_allowed_add_to_cart() end
   
    /**
    * Add custom JS file wpl_ps_my_enqueue()
    *
    */
    public function wpl_ps_my_enqueue($hook)
    {
        wp_enqueue_script('ajax-script', PSAP_URL . 'js/custom.form.js', array(
            'jquery'
        ));
    }// Add custom JS file wpl_ps_my_enqueue() end
    
    /**
    * Varify the API key wpl_ps_auth_req_api()
    *
    */
    public function wpl_ps_auth_req_api()
    {
        $response = wp_remote_post('https://pluginstar.in/wp-json/app/v1/appkey/', array(
            'method' => 'POST',
            'headers' => array(),
            'body' => array(
                'api_data' => array(
                    'capp_key' => esc_attr($_POST['app_key']),
                    'url' => esc_url(get_home_url())
                )
            ),
            'cookies' => array()
        ));
        if (is_wp_error($response)) {
            printf( __('Error! something went wrong: %1$s', 'woo-paylabs-amazonpay'), $response->get_error_message() );
        } 
        else 
        {
            $jsn = json_decode(wp_remote_retrieve_body($response), true);
            if($jsn > 0)
            {
                update_option('_ps_api_key', esc_attr($_POST['app_key']), false);
                if ($jsn <= 10) update_option('_ps_api_key_notice', esc_attr($jsn), false);
            }
            _e($jsn,'woo-paylabs-amazonpay');
        }
        wp_die();
    }// Varify the API key wpl_ps_auth_req_api() end
    
    /**
    * Delete the API key wpl_ps_auth_del_api()
    *
    */
    public function wpl_ps_auth_del_api()
    {
        if (get_option('_ps_api_key') != '') 
        {
            delete_option('_ps_api_key');
            delete_option('_ps_api_key_check');
            delete_option('_ps_api_key_notice');
            echo true;
        }
        wp_die();
    }// Delete the API key wpl_ps_auth_del_api() end
    
    /**
    * Cron to check expired API key wpl_ps_remove_expired_activation_key()
    *
    */
    public function wpl_ps_remove_expired_activation_key()
    {
        if (get_option('_ps_api_key') == '' || strlen(get_option('_ps_api_key')) != 40) {
            delete_option('_ps_api_key');
            delete_option('_ps_api_key_check');
            delete_option('_ps_api_key_notice');
        } elseif (date('Y-m-d', get_option('_ps_api_key_check')) != date('Y-m-d') && get_option('_ps_api_key')) {
            $response = wp_remote_post('https://pluginstar.in/wp-json/app/v1/appkey/', array(
                'method' => 'POST',
                'headers' => array(),
                'body' => array(
                    'api_data' => array(
                        'capp_key' => esc_attr(get_option('_ps_api_key')),
                        'url' => esc_url(get_home_url())
                    )
                ),
                'cookies' => array()
            ));
            if (!is_wp_error($response)) {
                $jsn = json_decode(wp_remote_retrieve_body($response), true);
                if (($jsn < 1) || (isset($jsn['data']['status']) && $jsn['data']['status'] != 200)) {
                    delete_option('_ps_api_key');
                    delete_option('_ps_api_key_check');
                    delete_option('_ps_api_key_notice');
                } else
                {
                    update_option('_ps_api_key_check', time(), false);
                    if ($jsn <= 10) update_option('_ps_api_key_notice', esc_attr($jsn), false);
                }
                    
            }
        }
    }// Cron to check expired API key wpl_ps_remove_expired_activation_key() end
    
    /**
    * Cron Job Active wpl_ps_cron_job()
    *
    */
    public function wpl_ps_cron_job()
    {
        if (!wp_next_scheduled('ps_remove_activation_key'))
            wp_schedule_event(time(), 'daily', 'ps_remove_activation_key');
        if (!wp_next_scheduled('ps_subscription_repeat'))
            wp_schedule_event(time(), 'twicedaily', 'ps_subscription_repeat');
        if (!wp_next_scheduled('ps_subscription_order_valid'))
            wp_schedule_event(time(), 'twicedaily', 'ps_subscription_order_valid');
    }// Cron Job Active wpl_ps_cron_job() end
    
    /**
    * Cron Job Deactivate wpl_ps_clear_cron_job()
    *
    */
    public function wpl_ps_clear_cron_job()
    {
        wp_clear_scheduled_hook('ps_remove_activation_key');
        wp_unschedule_event(time(), 'ps_remove_activation_key');
        wp_clear_scheduled_hook('ps_subscription_repeat');
        wp_unschedule_event(time(), 'ps_subscription_repeat');
        wp_clear_scheduled_hook('ps_subscription_order_valid');
        wp_unschedule_event(time(), 'ps_subscription_order_valid');
    }// Cron Job Deactivate wpl_ps_clear_cron_job() end
    
    /**
    * Cron Job for recurring payment wpl_ps_subscription_repeat_for_recurring()
    *
    */
    public function wpl_ps_subscription_repeat_for_recurring()
    {
        global $woocommerce;
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array('wc'), 
            'numberposts' => -1,
            'meta_query' => array(
                   array(
                       'key' => '_ps_next_payment',
                       'value' => date('Y-m-d'),
                       'compare' => '<=',
                   ),
                   array(
                       'key' => '_payment_method',
                       'value' => 'wpl_paylabs_amazonpay',
                       'compare' => '=',
                   ),
                   array(
                       'key' => '_ps_subs_valid',
                       'value' => '0',
                       'compare' => '>',
                   )
               )
            );
        $post_id_arr = get_posts( $args );
        if (!empty($post_id_arr)) 
        {
            foreach ($post_id_arr as $post) 
            {
                if (get_post_meta($post->ID, '_ap_billing_agreement_id', true) != '') 
                {
                    $subscription_data_obj = get_post_meta($post->ID, '_ps_subscription_data', true);
                    $subscription_data     = json_decode($subscription_data_obj, true);
                    
                    if ($subscription_data['remaining_subscription'] > 0) 
                    {
                        $order_id     = $post->ID;
                        $agreement_id = get_post_meta($post->ID, '_ap_billing_agreement_id', true);
                        $order_total  = $subscription_data['order_total'];

                        $subscription_duration = $subscription_data['subscription_type'];
                        $total_subscription    = $subscription_data['total_subscription'];
                        $subscription_time     = $subscription_data['subscription_time'];
                        
                        $next_subscription = '';
                        if ($subscription_duration == 'd') {
                            $next_subscription = date('Y-m-d', strtotime("+" . $subscription_time . " days"));
                        }
                        if ($subscription_duration == 'w') {
                            $next_subscription = date('Y-m-d', strtotime("+" . $subscription_time . " weeks"));
                        }
                        if ($subscription_duration == 'm') {
                            $next_subscription = date('Y-m-d', strtotime("+" . $subscription_time . " months"));
                        }
                        if ($subscription_duration == 'y') {
                            $next_subscription = date('Y-m-d', strtotime("+" . $subscription_time . " years"));
                        }
                        $remaining_subscription = ($subscription_data['remaining_subscription'] - 1);
                        $running_subscription   = ($subscription_data['running_subscription'] + 1);
                        
                        $txn_id      = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
                        $productinfo = sprintf(__( 'Order ID: #%1$s Transaction ID: #%2$s', 'woo-paylabs-amazonpay' ), $order_id, $txn_id);

                        update_post_meta($order_id, '_transaction_id', $txn_id);
                        
                        $apiCallParams = array(
                            'merchant_id' => $this->config['merchant_id'],
                            'amazon_billing_agreement_id' => $agreement_id,
                            'authorization_reference_id' => $txn_id,
                            'authorization_amount' => $order_total,
                            'currency_code' => $this->seller_currency,
                            'seller_authorization_note' => $productinfo,
                            'capture_now' => true,
                            'seller_note' => $productinfo,
                            'custom_information' => $order_id,
                            'seller_order_id' => $txn_id,
                            'store_name' => get_bloginfo('name')
                        );
                        $response_obj = $this->ApiCall->authorizeOnBillingAgreement($apiCallParams, $this->config);
                        $response_arr = $this->ApiCall->GetArrayResponse($response_obj->response);
                        
                        if (isset($response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationStatus']['State']) && ($response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Pending' || $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Completed')) 
                        {
                            $subscription_data_new = array(
                                'next_subscription_date' => $next_subscription,
                                'subscription_type' => $subscription_duration,
                                'total_subscription' => (int) $total_subscription,
                                'remaining_subscription' => $remaining_subscription,
                                'running_subscription' => $running_subscription,
                                'subscription_time' => $subscription_time,
                                'order_total' => $order_total,
                                'created_timestamp' => time()
                            );
                            update_post_meta($order_id, '_ap_authorization_id', $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AmazonAuthorizationId']);
                            update_post_meta($order_id, '_ap_capture_id', $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['IdList']['member']);
                            update_post_meta($order_id, '_ps_next_payment', $next_subscription);
                            update_post_meta($order_id, '_date_paid', time());
                            update_post_meta($order_id, '_ps_subs_valid', $remaining_subscription);
                            update_post_meta($order_id, '_ps_subscription_data', json_encode($subscription_data_new));
                            update_post_meta($order_id, '_ps_payment_verify', 0);
                            $order_note = sprintf(__('Subscription and recurring Payment<br>Amazon Pay Transaction ID: %1$s<br>Order Reference: %2$s<br>Subscription Time: %3$s <br>Next Payment: %4$s', 'woo-paylabs-amazonpay'), $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationReferenceId'], $response_arr['AuthorizeOnBillingAgreementResult']['AmazonOrderReferenceId'], $this->wpl_ps_ordinal($running_subscription), date('M j, Y', strtotime($next_subscription)));
                            $this->wpl_ps_insert_order_note($order_id, $order_note);
                        }
                    }
                }
            }
        }
    }// Cron Job for recurring payment wpl_ps_subscription_repeat_for_recurring() end
   
    /**
    * Number prefix wpl_ps_ordinal()
    *
    */
    public function wpl_ps_ordinal($number)
    {
        $ends = array(
            'th',
            'st',
            'nd',
            'rd',
            'th',
            'th',
            'th',
            'th',
            'th',
            'th'
        );
        if ((($number % 100) >= 11) && (($number % 100) <= 13))
            return $number . 'th';
        else
            return $number . $ends[$number % 10];
    }// Number prefix wpl_ps_ordinal() end

    /**
    * Custom add order note wpl_ps_insert_order_note()
    *
    */
    public function wpl_ps_insert_order_note($post_id = null, $note = '')
    {
        $comment_author       = __('WooCommerce', 'woo-paylabs-amazonpay');
        $comment_author_email = strtolower(__('WooCommerce', 'woo-paylabs-amazonpay')) . '@';
        $comment_author_email .= isset($_SERVER['HTTP_HOST']) ? str_replace('www.', '', sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']))) : 'noreply.com'; // WPCS: input var ok.
        $comment_author_email = sanitize_email($comment_author_email);
        
        $commentdata = array(
            'comment_post_ID' => $post_id,
            'comment_author' => $comment_author,
            'comment_author_email' => $comment_author_email,
            'comment_author_url' => '',
            'comment_content' => $note,
            'comment_agent' => 'WooCommerce',
            'comment_type' => 'order_note',
            'comment_parent' => 0,
            'comment_approved' => 1
        );
        return wp_insert_comment($commentdata);
    }// Custom add order note wpl_ps_insert_order_note() end

    /**
    * Get region for Amazon Widget JS from currency wpl_ps_amazonpay_get_region()
    *
    */
    public function wpl_ps_amazonpay_get_region($currency)
    {
        $region = 'de';
        if (in_array(strtoupper($currency), array(
            'USD',
            'ZAR'
        ))) {
            $region = 'us';
        } elseif (in_array(strtoupper($currency), array(
            'GBP'
        ))) {
            $region = 'uk';
        } elseif (in_array(strtoupper($currency), array(
            'JPY',
            'AUD'
        ))) {
            $region = 'jp';
        }
        return $region;
    }// Get region for Amazon Widget JS from currency wpl_ps_amazonpay_get_region() end

    /**
    * Register Custom Order status Subscription & Recurring wpl_ps_register_custom_order_status()
    *
    */
    public function wpl_ps_register_custom_order_status($order_statuses)
    {
        $order_statuses['wc-subscription'] = array(
            'label' => _x('Subscription & Recurring', 'Order status', 'woo-paylabs-amazonpay'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Subscription & Recurring <span class="count">(%s)</span>', 'Subscription & Recurring <span class="count">(%s)</span>', 'woo-paylabs-amazonpay')
        );
        return $order_statuses;
    }// Register Custom Order status Subscription & Recurring wpl_ps_register_custom_order_status() end
    
    /**
    * Show custom order status Subscription & Recurring wpl_ps_show_custom_order_status()
    *
    */
    public function wpl_ps_show_custom_order_status($order_statuses)
    {
        $order_statuses['wc-subscription'] = _x('Subscription & Recurring', 'Order status', 'woo-paylabs-amazonpay');
        return $order_statuses;
    }// Show custom order status Subscription & Recurring wpl_ps_show_custom_order_status() end
   
    /**
    * Show Bulk custom order status Subscription & Recurring wpl_ps_get_custom_order_status_bulk()
    *
    */
    public function wpl_ps_get_custom_order_status_bulk($bulk_actions)
    {
        $bulk_actions['mark_subscription'] = 'Change status to Subscription & Recurring';
        return $bulk_actions;
    }// Show Bulk custom order status Subscription & Recurring wpl_ps_get_custom_order_status_bulk() end
    
    /**
    * Check Captured Payment status wpl_amazonpay_paymentStatusChk()
    *
    */
    public function wpl_amazonpay_paymentStatusChk()
    {
        global $woocommerce;
        $Ps_Paylabs_Amazonpay = new Ps_Paylabs_Amazonpay();

        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array('wc'), 
            'numberposts' => -1,
            'meta_query' => array(
                   array(
                       'key' => '_transaction_id',
                       'value' => esc_attr($_POST['sellerOrderId']),
                       'compare' => '=',
                   ),
                   array(
                       'key' => '_payment_method',
                       'value' => 'wpl_paylabs_amazonpay',
                       'compare' => '=',
                   )
               )
            );
        $post_id_arr = get_posts( $args );
        $order_id = $post_id_arr[0]->ID;
        $order = new WC_Order($order_id);
        $apiCallParams = array( 'merchant_id' => $Ps_Paylabs_Amazonpay->config['merchant_id'],
                                'amazon_capture_id' => esc_attr($_POST['amazon_capture_id']),
                                'mws_auth_token' => $Ps_Paylabs_Amazonpay->config['client_id']
                            );
        $response = $Ps_Paylabs_Amazonpay->ApiCall->getCaptureDetails($apiCallParams,$Ps_Paylabs_Amazonpay->config);
        $response_arr = $Ps_Paylabs_Amazonpay->ApiCall->GetArrayResponse($response->response);
        if(isset($response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']) &&  $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']=='Completed')
        {
            update_post_meta($order_id, '_ps_payment_verify', 1);
            $order->payment_complete();
            if(esc_attr($_POST['isSubs']) > 0)
            {
                $order->update_status('subscription');
            }
        }
        elseif(isset($response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']) &&  $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']=='Declined')
        {
            update_post_meta($order_id, '_ps_payment_verify', 1);
            $order_note = sprintf(__('Amazon Payment Declined: %1$s<br>Reason: %2$s', 'woo-paylabs-amazonpay'), $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureReferenceId'], $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['ReasonCode']);
            $Ps_Paylabs_Amazonpay->wpl_ps_insert_order_note($order_id, $order_note);
            $order->update_status('pending');
        }
        printf(__('%1$s %2$s', 'woo-paylabs-amazonpay'), $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State'], $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['ReasonCode']);
        wp_die();
    }// Check Captured Payment status wpl_amazonpay_paymentStatusChk() end

    /**
    * Check Subscription Captured Payment status wpl_ps_ChkSubscriptionOrderValid()
    *
    */
    public function wpl_ps_ChkSubscriptionOrderValid()
    {
        global $woocommerce;
         $args = array(
            'post_type'   => 'shop_order',
            'post_status' => array('wc'), 
            'numberposts' => -1,
            'meta_query' => array(
                   array(
                       'key' => '_date_paid',
                       'value' => strtotime("-2 days"),
                       'compare' => '>=',
                   ),
                   array(
                       'key' => '_payment_method',
                       'value' => 'wpl_paylabs_amazonpay',
                       'compare' => '=',
                   ),
                   array(
                       'key' => '_ps_subs_valid',
                       'value' => '0',
                       'compare' => '>',
                   ),
                   array(
                       'key' => '_ps_payment_verify',
                       'value' => '0',
                       'compare' => '=',
                   )
               )
            );
        $post_id_arr = get_posts( $args );
        if (!empty($post_id_arr)) 
        {
            foreach ($post_id_arr as $post) 
            {
                $order_id = $post->ID;
                if (get_post_meta($order_id, '_ap_billing_agreement_id', true) != '') 
                {
                    $amazon_capture_id = get_post_meta($order_id, '_ap_capture_id', true);
                    $order = new WC_Order($order_id);
                    $apiCallParams = array( 'merchant_id' => $this->config['merchant_id'],
                                'amazon_capture_id' => $amazon_capture_id,
                                'mws_auth_token' => $this->config['client_id']
                            );
                    $response = $this->ApiCall->getCaptureDetails($apiCallParams,$this->config);
                    $response_arr = $this->ApiCall->GetArrayResponse($response->response);
                    if(isset($response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']) && $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']=='Declined')
                    {
                        update_post_meta($order_id, '_ps_payment_verify', 1);
                        $order_note = sprintf(__('Amazon Payment Declined: %1$s<br>Reason: %2$s', 'woo-paylabs-amazonpay'), $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureReferenceId'], response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['ReasonCode']);
                        $this->wpl_ps_insert_order_note($order_id, $order_note);
                        $order->update_status('pending');
                    }
                    elseif(isset($response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']) && $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']=='Completed')
                    {
                        update_post_meta($order_id, '_ps_payment_verify', 1);
                    }
                }
            }
        }
    }// Check Subscription Captured Payment status wpl_ps_ChkSubscriptionOrderValid() end
   
    /**
    * Check last 3 On Hold order and update order status wpl_amazonpay_UpdateOnholdOrders()
    *
    */
    function wpl_amazonpay_UpdateOnholdOrders()
    {
        global $woocommerce;
        $args = array(
            'post_type'   => 'shop_order',
            'post_status' => 'wc-on-hold', 
            'numberposts' => 3,
            'date_query' => array(
                    'after' => date('Y-m-d', strtotime('-2 days')) 
                ),
            'meta_query' => array(
                   array(
                       'key' => '_payment_method',
                       'value' => 'wpl_paylabs_amazonpay',
                       'compare' => '=',
                   ),
                   array(
                       'key' => '_ps_payment_verify',
                       'value' => '0',
                       'compare' => '=',
                   )
               )
            );
            $post_orders = get_posts( $args );
            if (!empty($post_orders)) 
            {
                foreach($post_orders as $post_order )
                {
                    $order = new WC_Order( $post_order->ID );
                    $order_id = $post_order->ID;
                    $amazon_capture_id  = get_post_meta( $order_id, '_ap_capture_id', true );
                    if($amazon_capture_id != '')
                    {
                        $apiCallParams = array( 'merchant_id' => $this->config['merchant_id'],
                                'amazon_capture_id' => $amazon_capture_id,
                                'mws_auth_token' => $this->config['client_id']
                            );
                        $response = $this->ApiCall->getCaptureDetails($apiCallParams,$this->config);
                        $response_arr = $this->ApiCall->GetArrayResponse($response->response);
                        if(isset($response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']) && $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']=='Completed')
                        {
                            update_post_meta($order_id, '_ps_payment_verify', 1);
                            $order->payment_complete();
                            if(get_post_meta( $order_id, '_ap_billing_agreement_id', true ) != '')
                            {
                                $order->update_status('subscription');
                            }
                        }
                        elseif(isset($response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']) && $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State']=='Declined')
                        {
                            update_post_meta($order_id, '_ps_payment_verify', 1);
                            $order_note = sprintf(__('Amazon Payment Declined: %1$s<br>Reason: %2$s', 'woo-paylabs-amazonpay'), $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureReferenceId'], $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['ReasonCode']);
                            $this->wpl_ps_insert_order_note($order_id, $order_note);
                            $order->update_status('pending');

                        }
                    }
                }
            }
    }// Check last 3 On Hold order and update order status wpl_amazonpay_UpdateOnholdOrders() end

}// Class end Ps_Paylabs_Amazonpay

    // Let's Gateway ready !!!!
    new Ps_Paylabs_Amazonpay();
    register_uninstall_hook(__FILE__,array('Ps_Paylabs_Amazonpay','wpl_ps_amazonpay_uninstall'));
    add_action('wp_head', 'wpl_amazonpay_ajaxurl');
    function wpl_amazonpay_ajaxurl() {
                   printf('<script type="text/javascript">
                           var ajaxurl = "' . admin_url('admin-ajax.php') . '";
                         </script>');
                }   
    add_action('wp_ajax_chk_pay', array('Ps_Paylabs_Amazonpay', 'wpl_amazonpay_paymentStatusChk'));
    add_action( 'wp_ajax_nopriv_chk_pay', array('Ps_Paylabs_Amazonpay', 'wpl_amazonpay_paymentStatusChk'));