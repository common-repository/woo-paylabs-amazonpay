<?php 
/**
* 	Amazon Pay WooCommerce payment gateway
* 	Author: Subhadeep Mondal
*	Author URI: https://www.linkedin.com/in/subhadeep-mondal
*	Created: 16/08/2018
*	Modified: 14/11/2019
**/
if (!defined('ABSPATH')) exit;
Class Wpl_PayLabs_WC_Amazonpay extends WC_Payment_Gateway 
{
	/**
    * Wpl_PayLabs_WC_Amazonpay __construct() function.
    *
    */
	public function __construct() 
	{
		require_once 'ApiCall.php';
		global $woocommerce;
		$this->ApiCall = new AmazonPay\ApiCall;
		$this->id				= 'wpl_paylabs_amazonpay';
		$this->method_title = __('Amazon Pay PluginStar', 'woo-paylabs-amazonpay');
		$this->method_description = __('Amazon Pay is familiar trusted way to pay by PluginStar', 'woo-paylabs-amazonpay');
		$this->icon 			= PSAP_URL . 'images/amazonpay_icon.png';
		$this->loader 			= PSAP_URL . 'images/load-ajax.gif';
		$this->css 				= PSAP_URL . 'css/style.css?ver='.PSAP_VER_CSS;
		$this->has_fields 		= true;
		$this->init_form_fields();
		$this->init_settings();
		$this->responseVal		= '';
		$this->api_key          = get_option('_ps_api_key');
		if ($this->api_key!='')
			$this->supports = array('refunds');
		$uploads = wp_upload_dir();
		$this->txn_log = $uploads['basedir']."/txn_log/amazonpay";
		wp_mkdir_p($this->txn_log); /// Create TXN Log files for backup transaction details

		$this->enabled			= $this->settings['enabled'];
		$this->testmode			= $this->settings['testmode'];
		if (in_array(get_bloginfo('language'), array('de-DE','en-GB','es-ES','fr-FR','it-IT')))
		{
			$this->lang = get_bloginfo('language');
		}
		else
			$this->lang = 'en-US';
		
		if(isset($this->settings['thank_you_message']))
			$this->thank_you_message = __($this->settings['thank_you_message'], 'woo-paylabs-amazonpay');
		else
			$this->thank_you_message = __('Thank you! and your order has been received.', 'woo-paylabs-amazonpay');

		if(isset($this->settings['redirect_message']) && $this->settings['redirect_message']!='')
			$this->redirect_message = __( $this->settings['redirect_message'], 'woo-paylabs-amazonpay' );
		else
			$this->redirect_message = __( 'Thank you for your order. We are now redirecting you to Amazon Pay to make payment.', 'woo-paylabs-amazonpay' );

		$this->settings['seller_region'] = $this->wpl_amazonpay_getRegion($this->settings['seller_currency']);
		$this->config = array(
		                'merchant_id'         => $this->settings['seller_id'],
		                'access_key'          => $this->settings['access_key'],
		                'secret_key'          => $this->settings['secret_key'],
		                'client_id'           => $this->settings['client_id'],
		                'region'              => $this->settings['seller_region'],
		                'sandbox'             => $this->settings['testmode']=='yes' ? true : false 
		            );
		
		if('yes'==$this->testmode) 
		{
			$this->title 		= 'Sandbox Amazon Pay';
			$this->description 	= '<a href="https://pay.amazon.com/us/developer/documentation" target="_blank">'.__( 'Development Guide', 'woo-paylabs-amazonpay' ).'</a>';
		}
		else
		{
			$this->title 			= $this->settings['title'];
			$this->description  	= $this->settings['description'];
		}
		if(isset($_GET['wpl_paylabs_ap_callback']) && isset($_GET['results']) && esc_attr($_GET['wpl_paylabs_ap_callback'])==1 && esc_attr($_GET['results']) != '') 
		{
			$this->responseVal = $_GET['results'];
			add_filter( 'woocommerce_thankyou_order_received_text', array($this, 'wpl_amazonpay_thankyou'));
			
		}
		add_action('init', array(&$this, 'wpl_amazonpay_transaction'));
		add_action( 'woocommerce_api_wpl_paylabs_wc_amazonpay' , array( $this, 'wpl_amazonpay_transaction' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'wpl_amazonpay_receipt_page' ) );
	}// Wpl_PayLabs_WC_Amazonpay __construct() function.

	/**
    * init Gateway Form Fields init_form_fields()
    *
    */
	function init_form_fields() 
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'			=> __('Enable/Disable:','woo-paylabs-amazonpay'),
				'type'			=> 'checkbox',
				'label' 		=> __( 'Enable Amazon Pay', 'woo-paylabs-amazonpay' ),
				'default'		=> 'yes'
			),
			'title' => array(
				'title' 		=> __( 'Title:', 'woo-paylabs-amazonpay' ),
				'type' 			=> 'text',
				'description'	=> __( 'This controls the title which the user sees during checkout.', 'woo-paylabs-amazonpay' ),
				'custom_attributes' => array( 'required' => 'required' ),
				'default' 		=> __( 'Amazon Pay', 'woo-paylabs-amazonpay' )
			),
			'description' => array(
				'title' 		=> __( 'Description:', 'woo-paylabs-amazonpay' ),
				'type' 			=> 'textarea',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woo-paylabs-amazonpay' ),
				'default' 		=> __( 'Amazon Pay offers a buying experience you can trust.', 'woo-paylabs-amazonpay' ),
			),
			'seller_id' => array(
				'title' 		=> __( 'Seller ID :', 'woo-paylabs-amazonpay' ),
				'type'	 		=> 'text',
				'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
				'description' 	=> __( 'String of characters provided by Amazon Pay', 'woo-paylabs-amazonpay' ),
				'default' 		=> ''
			),
			'access_key' => array(
				'title' 		=> __( 'Access Key :', 'woo-paylabs-amazonpay' ),
				'type'	 		=> 'text',
				'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
				'description' 	=> __( 'Access Key provided by Amazon Pay', 'woo-paylabs-amazonpay' ),
				'default' 		=> ''
			),
			'secret_key' => array(
				'title' 		=> __( 'Secret key :', 'woo-paylabs-amazonpay' ),
				'type'	 		=> 'text',
				'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
				'description' 	=> __( 'Secret key provided by Amazon Pay', 'woo-paylabs-amazonpay' ),
				'default' 		=> ''
			),
			'client_id' => array(
				'title' 		=> __( 'Client ID:', 'woo-paylabs-amazonpay' ),
				'type'	 		=> 'text',
				'custom_attributes' => array( 'required' => 'required', 'autocomplete'=> 'off' ),
				'description' 	=> __( 'Client ID provided by Amazon Pay', 'woo-paylabs-amazonpay' ),
				'default' 		=> ''
			),
			'seller_currency' => array(
				'title' 		=> __('Seller currency:', 'woo-paylabs-amazonpay'),
				'type' 			=> 'select',
				'label' 		=> __('Seller currency', 'woo-paylabs-amazonpay'),
				'options' 		=> array('AUD'=>'AUD','GBP'=>'GBP','DKK'=>'DKK','EUR'=>'EUR','HKD'=>'HKD','JPY'=>'JPY','NZD'=>'NZD','NOK'=>'NOK','ZAR'=>'ZAR','SEK'=>'SEK','CHF'=>'CHF','USD'=>'USD'),
				'default' 		=> 'USD',
				'description' 	=> __('Set Seller currency as per your amazon Seller bank account <a href="https://developer.amazon.com/docs/eu/amazon-pay-onetime/supported-currencies.html" target="_blank">Supported currencies</a>', 'woo-paylabs-amazonpay' )
                ),
			'testmode' => array(
				'title' 		=> __('Mode of transaction:', 'woo-paylabs-amazonpay'),
				'type' 			=> 'select',
				'label' 		=> __('Amazon Pay Tranasction Mode.', 'woo-paylabs-amazonpay'),
				'options' 		=> array('yes'=>'Test / Sandbox Mode','no'=>'Live Mode'),
				'default' 		=> 'no',
				'description' 	=> __('Mode of Amazon Pay activities', 'woo-paylabs-amazonpay')
                ),
			'thank_you_message' => array(
				'title' 		=> __( 'Thank you page message:', 'woo-paylabs-amazonpay' ),
				'type' 			=> 'textarea',
				'description' 	=> __( 'Thank you page order success message when order has been received', 'woo-paylabs-amazonpay' ),
				'default' 		=> __( 'Thank you! your order has been received.', 'woo-paylabs-amazonpay' ),
				),
			'redirect_message' => array(
				'title' 		=> __( 'Redirecting you to Amazon Pay :', 'woo-paylabs-amazonpay' ),
				'type' 			=> 'textarea',
				'description' 	=> __( 'We are now redirecting you to Amazon Pay to make payment', 'woo-paylabs-amazonpay' ),
				'default' 		=> __( 'Thank you for your order. We are now redirecting you to Amazon Pay to make payment.', 'woo-paylabs-amazonpay' ),
				),
			'subscription'    => array(
	          'title'   => __( 'Subscription and Recurring Payment', 'woo-paylabs-amazonpay' ),
	          'type'    => 'checkbox',
	          'label'   => __( 'Active on PRO version', 'woo-paylabs-amazonpay' ),
	          'default' => 'no',
	          'custom_attributes' => array('onclick' => 'return false;' ),
	       	 	),
	        'refund'    => array(
	          'title'   => __( 'Refund with Amazon Pay', 'woo-paylabs-amazonpay' ),
	          'type'    => 'checkbox',
	          'custom_attributes' => array('onclick' => 'return false;' ),
	          'label'   => __( 'Active on PRO version', 'woo-paylabs-amazonpay' ),
	          'default' => 'no',
	        	),
	        'woo_product'    => array(
	          'title'   => __( 'Add WooCommerce Subscription Product', 'woo-paylabs-amazonpay' ),
	          'type'    => 'checkbox',
	          'custom_attributes' => array('onclick' => 'return false;' ),
	          'label'   => __( 'Active on PRO version', 'woo-paylabs-amazonpay' ),
	          'default' => 'no',
	        	),
	        'ord_status'    => array(
	          'title'   => __( 'Customized Order status Subscription & Recurring ', 'woo-paylabs-amazonpay' ),
	          'type'    => 'checkbox',
	          'custom_attributes' => array('onclick' => 'return false;' ),
	          'label'   => __( 'Active on PRO version', 'woo-paylabs-amazonpay' ),
	          'default' => 'no',
	        	),
			);
	}// init Gateway Form Fields init_form_fields() end

	/**
    * WP Admin options admin_options()
    *
    */
	public function admin_options() 
	{
    	?>
    	<h3><?php _e($this->method_title, 'woo-paylabs-amazonpay' ); ?></h3>
    	<p><?php _e( 'Amazon Pay WooCommerce payment gateway trusted way to pay. ✔ Single, Subscription and Recurring Payments ✔ Refund with Amazon Pay from WP Admin ✔ Add WooCommerce Subscription Products ✔ Revenue with automatic Recurring Payments ✔ Customized Order status <strong>Subscription & Recurring</strong> and more Payment options developed by PluginStar.', 'woo-paylabs-amazonpay' ); ?></p>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
					<tr valign="top">
						<th scope="row" class="titledesc">
							<label for="woocommerce_<?php echo $this->id; ?>_app_key"><?php _e('API KEY:', 'woo-paylabs-amazonpay'); ?></label>
						</th>
						<td class="forminp">
							<fieldset>
								<legend class="screen-reader-text"><span><?php _e('API KEY:', 'woo-paylabs-amazonpay'); ?></span></legend>
								<input class="input-text regular-input" name="woocommerce_<?php echo $this->id; ?>_app_key" id="woocommerce_<?php echo $this->id; ?>_app_key" <?php if(strlen(get_option('_ps_api_key')) == 40 ) echo "readonly"; ?> value="<?php echo get_option('_ps_api_key'); ?>" type="text" autocomplete="off">
								<button class="button-secondary" type="button" name="woocommerce_<?php echo $this->id; ?>_customize_button" id="woocommerce_<?php echo $this->id; ?>_valid_button" <?php if(strlen(get_option('_ps_api_key')) == 40 ) echo "disabled"; ?>>
									<?php if(strlen(get_option('_ps_api_key')) == 40 ) printf(__( '&#9989; Valid', 'woo-paylabs-amazonpay' )); else printf(__( 'Validate Key', 'woo-paylabs-amazonpay' )); ?></button>
								<?php if(strlen(get_option('_ps_api_key')) == 40 ){ ?>
								<button class="button-secondary" type="button" name="woocommerce_<?php echo $this->id; ?>_edit_key" id="woocommerce_<?php echo $this->id; ?>_edit_key">&#9998; <?php _e('Edit', 'woo-paylabs-amazonpay' ); ?></button>
								<button class="button-secondary" type="button" name="woocommerce_<?php echo $this->id; ?>_delete_key" id="woocommerce_<?php echo $this->id; ?>_delete_key">X <?php _e('Delete', 'woo-paylabs-amazonpay' ); ?></button>
								<?php } ?>
								<span id="key_info"></span>
								<?php printf('<p class="description" id="description_key_info">%1$s <a href="https://pluginstar.in/get-key/" target="_blank">%2$s</a> %3$s</p>',
										__('Get your API Key', 'woo-paylabs-amazonpay'),
										__('Click to GET KEY', 'woo-paylabs-amazonpay'),
										__('and Active PRO version of this plugin', 'woo-paylabs-amazonpay')
										 ) ?>
							</fieldset>
						</td>
					</tr>
				</table>
			<?php
	}// WP Admin options admin_options() end

	/**
    * Build the form after click on checkout wpl_generate_paylabs_amazonpay_form()
    *
    */
    private function wpl_generate_paylabs_amazonpay_form($order_id) 
    {	
    	global $wp;
    	global $woocommerce;
		$returnSameURL = home_url(add_query_arg(array('key'=>$_GET['key']), $wp->request));
    	$this->wpl_amazonpay_clear_cache();
		$order = new WC_Order($order_id);
		$txn_id = substr(hash('sha256', mt_rand() . microtime()), 0, 20);
		$productinfo = sprintf( esc_html__( 'Order ID: #%1$s Transaction ID: #%2$s', 'woo-paylabs-amazonpay' ), $order_id, $txn_id);
		update_post_meta( $order_id, '_transaction_id', $txn_id );
		$posturl= $this->wpl_amazonpay_getWidgetsJsURL($this->settings['seller_currency']);
		
		$subscription_chkbox_prev = '';
		if(!empty(WC()->cart->get_cart()))
		{
			$items = WC()->cart->get_cart();
		}
		else
		{
			$items = $order->get_items();
		}
		foreach ( $items as $item ) 
		{
			$newproduct_id = $item['product_id'];
            $subscription_newproductnewproduct = get_post_meta( $newproduct_id, '_ps_subscription_chkbox', true );
            if($subscription_newproductnewproduct=='yes')
            {
                $subscription_chkbox_prev = 'yes';
            }
		}
		if((strlen($this->api_key)==40) && $subscription_chkbox_prev == 'yes')
		{
			return $this->wpl_amazonpay_rpForm($order_id,$posturl,$txn_id,$returnSameURL);
		}
		else
		{
			return $this->wpl_amazonpay_spForm($order_id,$posturl,$txn_id,$productinfo);
		}
	}// Build the form after click on checkout wpl_generate_paylabs_amazonpay_form() end
	
	/**
    * One time or Single payment form JS wpl_amazonpay_spForm()
    *
    */
	private function wpl_amazonpay_spForm($order_id,$posturl,$txn_id,$productinfo) 
    {
    	$ap_form = __('<div id="AmazonPayButton"></div>
							<script type="text/javascript" src="'.$posturl.'"></script>
							<script type="text/javascript">
						        OffAmazonPayments.Button("AmazonPayButton", "'.$this->config['merchant_id'].'", {
						            type: "hostedPayment",
						            language: "'.$this->lang.'",
						            hostedParametersProvider: function(done) {
						                    done('.$this->wpl_ap_calc_sign($order_id,$productinfo,$txn_id).');
						            },
						            onError: function(errorCode) {
						                console.log(errorCode.getErrorCode() + " " + errorCode.getErrorMessage());
						            }
						        });

								jQuery(window).on("load", function () {
										jQuery("#AmazonPayButton img").trigger("click");
									});
								jQuery(function(){
									jQuery("body").block(
										{
											message: "'.__($this->redirect_message, 'woo-paylabs-amazonpay').'",
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
										        cursor:         "wait"
										    }
										});
							});
							</script>');
		return wp_specialchars_decode($ap_form);
    }// One time or Single payment form JS wpl_amazonpay_spForm() end

    /**
    * Recurring payment form JS wpl_amazonpay_rpForm()
    *
    */
	private function wpl_amazonpay_rpForm($order_id,$posturl,$txn_id,$returnSameURL) 
    {
    	global $woocommerce;
    	$returnURL = $woocommerce->api_request_url(strtolower(get_class($this)));
    	$ap_form_sb = __('<link rel="stylesheet" id="style-css" href="'.$this->css.'" type="text/css" media="all" />
											<script async="async" type="text/javascript" src="'.$posturl.'"></script>
													<button type="button" name="button" id="LogoutButton">Logout from Amazon Pay</button>
													<div id="AmazonPayButton"></div>
													<div id="addressBookWidgetDiv"></div>
													<div id="walletWidgetDiv"></div>
													<div id="consentWidgetDiv"></div>
													
											<script type="text/javascript">
											function getURLParameter(name, source) {
											return decodeURIComponent((new RegExp("[?|&amp;|#]" + name + "=" +
											                "([^&;]+?)(&|#|;|$)").exec(source) || [, ""])[1].replace(/\+/g, "%20")) || null;
											}

											var accessToken = getURLParameter("access_token", location.hash);
											if (typeof accessToken === "string" && accessToken.match(/^Atza/)) {
											document.cookie = "amazon_Login_accessToken=" + accessToken + ";path=/;secure";
											}
											window.onAmazonLoginReady = function() {
											amazon.Login.setClientId("'.$this->config['client_id'].'");
											jQuery("#LogoutButton").hide();
											jQuery("#payNowButton").hide();
											};
											window.onAmazonPaymentsReady = function() {
											showLoginButton();
											showAddressBookWidget();

											};
											document.getElementById("LogoutButton").onclick = function() {
											amazon.Login.logout();
											jQuery("#LogoutButton").hide();
											showLoginButton();
											document.cookie = "amazon_Login_accessToken=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
											window.location.href = "'.$returnSameURL.'";
											};
											</script>
											<script type="text/javascript">
											function showLoginButton() {
											var authRequest;
											OffAmazonPayments.Button("AmazonPayButton", "'.$this->config['merchant_id'].'", {
											  type:  "PwA",
											  color: "Gold",
											  size:  "medium",
											  language: "'.$this->lang.'",
											  authorization: function() {
											    loginOptions = {scope: "profile payments:widget payments:shipping_address", popup: true};
											    authRequest = amazon.Login.authorize (loginOptions, "'.$returnSameURL.'");
											  }
											});
											}
											function showAddressBookWidget() {
											new OffAmazonPayments.Widgets.AddressBook({
											  sellerId: "'.$this->config['merchant_id'].'",
											  agreementType: "BillingAgreement",
											  onReady: function (billingAgreement) {
											  	jQuery("#AmazonPayButton img").hide(); 
											  	jQuery("#LogoutButton").show();
											      var billingAgreementId = billingAgreement.getAmazonBillingAgreementId();
											      var el;
											      if ((el = document.getElementById("billingAgreementId"))) {
											        el.value = billingAgreementId;
											      }
											      showWalletWidget(billingAgreementId);
											      showConsentWidget(billingAgreement);
											  },
											  onAddressSelect: function (billingAgreement) {
											  },
											  design: {
											      designMode: "responsive"
											  },
											  onError: function (error) {
											      alert(error.getErrorMessage());
											      switch (error.getErrorCode()) {
											        case "AddressNotModifiable":
											            // You cannot modify the shipping address when the order reference is in the given state.
											            break;
											        case "BuyerNotAssociated":
											            // The buyer is not associated with the given order reference. The buyer must sign in before you render the widget.
											            break;
											        case "BuyerSessionExpired":
											            // The buyer"s session with Amazon has expired. The buyer must sign in before you render the widget.
											            break;
											        case "InvalidAccountStatus":
											            // Your merchant account is not in an appropriate state to execute this request. For example, it has been suspended or you have not completed registration.
											            break;
											        case "InvalidOrderReferenceId":
											            // The specified order reference identifier is invalid.
											            break;
											        case "InvalidParameterValue":
											            // The value assigned to the specified parameter is not valid.
											            break;
											        case "InvalidSellerId":
											            // The merchant identifier that you have provided is invalid. Specify a valid SellerId.
											            break;
											        case "MissingParameter":
											            // The specified parameter is missing and must be provided.
											            break;
											        case "PaymentMethodNotModifiable":
											            // You cannot modify the payment method when the order reference is in the given state.
											            break;
											        case "ReleaseEnvironmentMismatch":
											            // You have attempted to render a widget in a release environment that does not match the release environment of the Order Reference object. The release environment of the widget and the Order Reference object must match.
											            break;
											        case "StaleOrderReference":
											            // The specified order reference was not confirmed in the allowed time and is now canceled. You cannot associate a payment method and an address with a canceled order reference.
											            break;
											        case "UnknownError":
											            // There was an unknown error in the service.
											            break;
											        default:
											            // Oh My God, What"s going on?
											      }
											  }
											}).bind("addressBookWidgetDiv");
											}
											function showWalletWidget(billingAgreementId) {
											new OffAmazonPayments.Widgets.Wallet({
											  sellerId: "'.$this->config['merchant_id'].'",
											  agreementType: "BillingAgreement",
											  amazonBillingAgreementId: billingAgreementId,
											  onReady: function(billingAgreement) {
											  		 amazonBillingAgreementId = billingAgreement.getAmazonBillingAgreementId();
											  },
											  onPaymentSelect: function() {
											  },
											  design: {
											      designMode: "responsive"
											  },
											  onError: function(error) {
											      alert(error.getErrorMessage());
											  }
											}).bind("walletWidgetDiv");
											}
											function showConsentWidget(billingAgreement) {
											new OffAmazonPayments.Widgets.Consent({
											  sellerId: "'.$this->config['merchant_id'].'",
											  amazonBillingAgreementId: billingAgreement.getAmazonBillingAgreementId(),
											  onReady: function(billingAgreementConsentStatus){
											  		jQuery("#payNowButton").hide();
													if(billingAgreementConsentStatus.getConsentStatus() == "true")
													{
														jQuery("#payNowButton").show();
													}
											  },
											  onConsent: function(billingAgreementConsentStatus){
											  		jQuery("#payNowButton").hide();
													if(billingAgreementConsentStatus.getConsentStatus() == "true")
													{
														jQuery("#payNowButton").show();
													}
											  },
											  design: {
											      designMode: "responsive"
											  },
											  onError: function(error) {
											      alert(error.getErrorMessage());
											  }
											}).bind("consentWidgetDiv");
											}
											jQuery(window).on("load", function () {
											jQuery("#payNowButton").click(function(){
												window.location.href = "'.$returnURL.'?amazonBillingId="+amazonBillingAgreementId+"&orderId='.$order_id.'&txn_id='.$txn_id.'&resultCode=Success&rp=1";
											});
											});
											</script>
											<button type="button" name="button" id="payNowButton" readonly>Pay with Amazon Pay</button>');
		return wp_specialchars_decode($ap_form_sb);    
    }// Recurring payment form JS wpl_amazonpay_rpForm() end

    /**
    * Calculate signature for Amazon Pay button wpl_ap_calc_sign()
    *
    */
	private function wpl_ap_calc_sign($order_id,$productinfo,$txn_id) 
    {
    	global $woocommerce;
		$order = new WC_Order($order_id);
       	$returnURL = $woocommerce->api_request_url(strtolower(get_class($this)));
       	$cancelReturnURL = esc_url_raw($order->get_checkout_payment_url(false));

        $parameters["accessKey"]               = $this->config['access_key'];
        $parameters["amount"]                  = $order->get_total();
        $parameters["sellerId"]                = $this->config['merchant_id'];
        $parameters["returnURL"]               = $returnURL;
        $parameters["cancelReturnURL"]         = $returnURL;
        $parameters["lwaClientId"]             = $this->config['client_id'];
        $parameters["sellerNote"]              = $productinfo;
        $parameters["sellerOrderId"]           = $txn_id;
        $parameters["shippingAddressRequired"] = "false";
        $parameters["paymentAction"]           = "None";

        $parameters = array_filter($parameters);
        uksort($parameters, 'strcmp');
        $Signature = $this->ApiCall->getSignature($this->config,$parameters);
        $parameters["signature"] = str_replace('%7E', '~', rawurlencode($Signature));
        return (json_encode($parameters));
    }// Calculate signature for Amazon Pay button wpl_ap_calc_sign() end
   
	
	/**
    * Payment Process after checkout process_payment()
    *
    */
	function process_payment($order_id) 
	{
		$this->wpl_amazonpay_clear_cache();
		global $woocommerce;
		$order = new WC_Order( $order_id );
		return array(
			'result' 	=> 'success',
			'redirect'	=> $order->get_checkout_payment_url(true)
		);
	}// Payment Process after checkout process_payment() end

	/**
    * Page after cheout button and redirect to Amazon Pay payment page wpl_amazonpay_receipt_page()
    *
    */
	function wpl_amazonpay_receipt_page($order_id) 
	{
		$this->wpl_amazonpay_clear_cache();
		global $woocommerce;
		$order = new WC_Order($order_id);
		printf('<h3>%1$s</h3>',__('Thank you for your order, please click the button below to make payment', 'woo-paylabs-amazonpay'));
		_e($this->wpl_generate_paylabs_amazonpay_form($order_id ));
	}// Page after cheout button and redirect to Amazon Pay payment page wpl_amazonpay_receipt_page() end

	/**
    * Check the status of current transaction and get response wpl_amazonpay_transaction()
    *
    */
	function wpl_amazonpay_transaction() 
	{
		global $woocommerce;
		if(isset($_GET['txn_id']) && $_GET['txn_id'] != '')
		{
			$trnid = $_GET['txn_id'];
		}
		elseif(isset($_GET['sellerOrderId']) && $_GET['sellerOrderId'] != '')
		{
			$trnid = $_GET['sellerOrderId'];
		}
		$args = array(
        'post_type'   => 'shop_order',
        'post_status' => array('wc'), 
        'numberposts' => 1,
        'meta_query' => array(
               array(
                   'key' => '_transaction_id',
                   'value' => $trnid,
                   'compare' => '=',
               )
           )
        );
	    $post_id_arr = get_posts( $args );
	    if(isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='')
	    	$order_id = $post_id_arr[0]->ID;
		
    	$order = new WC_Order($order_id);
		if(esc_attr($_GET['resultCode'])=='Success')
	    {
			if(isset($_GET['rp']) && esc_attr($_GET['rp'])==1)
			{
				$Authorize = $this->wpl_ap_pro_authorizeOnBilling($_GET);
			}
			else
			{
				$Authorize = $this->wpl_ap_pro_authorize($_GET);
			}
			
	    	if(!empty($Authorize))
	    	{
		        if(isset($Authorize['AuthorizationReferenceId'])) 
		        {
		        	$order->update_status('on-hold');
		            update_post_meta( $order_id, '_ap_authorization_id', $Authorize['AmazonAuthorizationId'] );
		            update_post_meta( $order_id, '_ap_capture_id', $Authorize['member'] );
		            $order_note = sprintf( __('Amazon Pay Transaction ID:  %1$s<br>Order Reference ID: %2$s', 'woo-paylabs-amazonpay' ), $Authorize['AuthorizationReferenceId'], $Authorize['AmazonOrderReferenceId']) ;
		            $next_subscription_pass = '';
		            
		            if(isset($Authorize['AmazonBillingAgreementId']))
		            {
		            	$subscription_chkbox_prev = '';
						$subscription_data = '';
						if(!empty(WC()->cart->get_cart()))
						{
							$items = WC()->cart->get_cart();
						}
						else
						{
							$items = $order->get_items();
						}
						foreach($items  as $cart_item )
						{
				            $newproduct_id = $cart_item['product_id'];
				            $subscription_newproductnewproduct = get_post_meta( $newproduct_id, '_ps_subscription_chkbox', true );
				            if($subscription_newproductnewproduct=='yes')
				            {
				            	$running_subscription = 1;
				                $subscription_chkbox_prev = 'yes';
				                $subscription_time = get_post_meta( $newproduct_id, '_ps_subscription_time', true );
					            $subscription_duration = get_post_meta( $newproduct_id, '_ps_subscription_duration', true );
					            $total_subscription = get_post_meta( $newproduct_id, '_ps_total_subscription', true );

					            $next_subscription = '';
					            if($subscription_duration=='d')
					            {
					            	$next_subscription = date('Y-m-d', strtotime("+".$subscription_time." days"));
					            }
					            if($subscription_duration=='w')
					            {
					            	$next_subscription = date('Y-m-d', strtotime("+".$subscription_time." weeks"));
					            }
					            if($subscription_duration=='m')
					            {
					            	$next_subscription = date('Y-m-d', strtotime("+".$subscription_time." months"));
					            }
					            if($subscription_duration=='y')
					            {
					            	$next_subscription = date('Y-m-d', strtotime("+".$subscription_time." years"));
					            }
					            if($total_subscription > 0)
					            	$remaining_subscription = ($total_subscription-1);
					            else
					            	$remaining_subscription = 1000;

					            $subscription_data_obj = get_post_meta($order_id, '_ps_subscription_data', true);
				            	if(!empty($subscription_data_obj))
				            	{
				            		$subscription_data_old     = json_decode($subscription_data_obj, true);
				            		$remaining_subscription = $subscription_data_old['remaining_subscription'];
				            		$running_subscription = $subscription_data_old['running_subscription'];
				            		$next_subscription = $subscription_data_old['next_subscription_date'];
				            	}

					            $subscription_data = array(	'next_subscription_date'=> $next_subscription, 
					            							'subscription_type'		=> $subscription_duration,
					            							'total_subscription'	=> (int)$total_subscription,
					            							'remaining_subscription'=>$remaining_subscription,
					            							'running_subscription'	=>$running_subscription,
					            							'subscription_time'		=> $subscription_time,
					            							'order_total'			=> $order->get_total(),
					            							'created_timestamp'		=>time(),
					            							);
				            }
					    }
					    update_post_meta( $order_id, '_ps_subscription_data', json_encode($subscription_data));
						update_post_meta( $order_id, '_ps_next_payment', $next_subscription);
						update_post_meta( $order_id, '_ps_subs_valid', 1);
		            	update_post_meta( $order_id, '_ap_billing_agreement_id', $Authorize['AmazonBillingAgreementId'] );
		            	$order_note = sprintf( __('Subscription and recurring Payment<br>Amazon Pay Transaction ID: %1$s<br>Order Reference: %2$s<br>Subscription Time: %4$s <br>Next Payment: %3$s', 'woo-paylabs-amazonpay' ), $Authorize['AuthorizationReferenceId'], $Authorize['AmazonOrderReferenceId'], date('M j, Y', strtotime($next_subscription)), $this->wpl_ps_ordinal($running_subscription));
		            	$next_subscription_pass = strtotime($next_subscription);
		            }
		            $order->add_order_note($order_note);
			        $result_arr = array('resultCode'=>'success', 'sellerOrderId'=>$Authorize['AuthorizationReferenceId'], 'orderReferenceId'=>$Authorize['AmazonOrderReferenceId'], 'next_subscription'=> $next_subscription_pass,'amazon_capture_id'=> $Authorize['member']);
					$results = urlencode(base64_encode(json_encode($result_arr)));
					$return_url = add_query_arg(array('wpl_paylabs_ap_callback'=>1,'results'=>$results), $this->get_return_url($order));
			        wp_redirect($return_url);
		        } 
				else 
				{
				 	wc_add_notice( __('Error on payment: Amazon Pay payment failed !', 'woo-paylabs-amazonpay'), 'error');
					wp_redirect($order->get_checkout_payment_url(false));
				}
	    	}
	    }
	    else
    	{
    		wc_add_notice( __('Error on payment: Amazon Pay payment failed !', 'woo-paylabs-amazonpay'), 'error');
			wp_redirect($order->get_checkout_payment_url(false));
    	}
	}// Check the status of current transaction and get response wpl_amazonpay_transaction() end

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
    * clear cache wpl_amazonpay_clear_cache()
    *
    */
	private function wpl_amazonpay_clear_cache()
	{
		header("Pragma: no-cache");
		header("Cache-Control: no-cache");
		header("Expires: 0");
	}// clear cache wpl_amazonpay_clear_cache() end
	
	/**
    * Thank you page after payment wpl_amazonpay_thankyou()
    *
    */
	function wpl_amazonpay_thankyou() 
	{
		global $woocommerce;
		$wpl_paylabs_response = json_decode(base64_decode(urldecode($this->responseVal)), true);

		if(strtolower($wpl_paylabs_response['resultCode'])=='success')
		{
			$added_text = '';
			$apiCallParams = array( 'merchant_id' => $this->config['merchant_id'],
                            'amazon_capture_id' => $wpl_paylabs_response['amazon_capture_id'],
                            'mws_auth_token' => $this->config['client_id']
                        );

	        $response = $this->ApiCall->getCaptureDetails($apiCallParams,$this->config);
	        $response_arr = $this->ApiCall->GetArrayResponse($response->response);
	        $CaptureStatus = $response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['State'];
	        $isSubs = 0;
	        if(isset($wpl_paylabs_response['next_subscription']) && $wpl_paylabs_response['next_subscription']!='')
            {
            	$isSubs = 1;
            }
	        if($CaptureStatus=='Completed')
	        {
	        	
	            if(isset($wpl_paylabs_response['sellerOrderId']) && $wpl_paylabs_response['sellerOrderId'] != '')
				{
					$trnid = $wpl_paylabs_response['sellerOrderId'];
				}
				$args = array(
	            'post_type'   => 'shop_order',
	            'post_status' => array('wc'), 
	            'numberposts' => 1,
	            'meta_query' => array(
	                   array(
	                       'key' => '_transaction_id',
	                       'value' => $trnid,
	                       'compare' => '=',
	                   )
	               )
	            );
		        $post_id_arr = get_posts( $args );
		        if(isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='')
		        	$order_id = $post_id_arr[0]->ID;
		        
		        $order = new WC_Order($order_id);
		        $order->payment_complete();
	            if($isSubs > 0)
	            {
	            	$order->update_status('subscription');
	            }
	        }
	        elseif($CaptureStatus=='Pending')
	        {
	        	$added_text .= printf('<script type="text/javascript">
								jQuery(function(){
									var data = {
										"action": "chk_pay",
										"amazon_capture_id":"'.$wpl_paylabs_response['amazon_capture_id'].'",
										"sellerOrderId":"'.$wpl_paylabs_response['sellerOrderId'].'",
										"isSubs":"'.$isSubs.'"
									};
									 myVar = setInterval(function(){ 
									 	jQuery.post(ajaxurl, data, function(response) {
									 		if(response.trim()!="Pending")
									 		{
									 			jQuery("#payment_status_ap").html(response);
									 			clearInterval(myVar);
									 		}
										});
									  }, 8000);
									
								});
								</script>');
	        	$CaptureStatus = sprintf('<img src="'.$this->loader.'" width="20" alt="Loading.." style="display:inline-block">&nbsp;&nbsp;'.__('Payment waiting for approval...','woo-paylabs-amazonpay'));
	        }
	        elseif($CaptureStatus=='Declined')
            {
                $CaptureStatus = __('Amazon Payment Declined: '.$response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureReferenceId'].' <br>Reason: '.$response_arr['GetCaptureDetailsResult']['CaptureDetails']['CaptureStatus']['ReasonCode']);
            }

			$subscription_data = '';
			if($wpl_paylabs_response['next_subscription']!='')
				$subscription_data 	=  __('(Subscription & Recurring Payment)', 'woo-paylabs-amazonpay');
			$added_text .= printf( '<section class="woocommerce-order-details">
								<h3>'.$this->thank_you_message.'</h3>
								<h3 class="woocommerce-order-details__title">'.__('Transaction details', 'woo-paylabs-amazonpay').$subscription_data.'</h3>
								<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
									<thead>
										<tr>
											<th class="woocommerce-table__product-name product-name">'.__('Amazon Pay Transaction ID:', 'woo-paylabs-amazonpay').'</th>
											<th class="woocommerce-table__product-table product-total">'.$wpl_paylabs_response['sellerOrderId'].'</th>
										</tr>
									</thead>
									<tbody>
										<tr class="woocommerce-table__line-item order_item">
											<td class="woocommerce-table__product-name product-name">'.__('Order Reference ID:', 'woo-paylabs-amazonpay').'</td>
											<td class="woocommerce-table__product-total product-total">'.$wpl_paylabs_response['orderReferenceId'].'</td>
										</tr>
										<tr class="woocommerce-table__line-item order_item">
											<td class="woocommerce-table__product-name product-name">'.__('Payment Status:', 'woo-paylabs-amazonpay').'</td>
											<td style="vertical-align:middle" class="woocommerce-table__product-total product-total" id="payment_status_ap">'.$CaptureStatus.'</td>
										</tr>');
							if($wpl_paylabs_response['next_subscription']!='')
							{
								$added_text .= printf('<tr class="woocommerce-table__line-item order_item">
										<td class="woocommerce-table__product-name product-name">'.__('Next Payment Date:', 'woo-paylabs-amazonpay').'</td>
										<td class="woocommerce-table__product-total product-total">'.date('M j, Y', $wpl_paylabs_response['next_subscription']).'</td>
										</tr>');
							}
					$added_text .=	printf('</tbody>
								</table>
							</section>');
		}
		else 
        {
        	wc_add_notice( __('Error on payment: Amazon Pay payment failed !', 'woo-paylabs-amazonpay'), 'error');
			wp_redirect($order->get_checkout_payment_url(false));
        }
	}// Thank you page after payment wpl_amazonpay_thankyou() end

	/**
    * JS widget url wpl_amazonpay_getWidgetsJsURL()
    *
    */
	private function wpl_amazonpay_getWidgetsJsURL($currency)
	{
		if($this->testmode=='yes')
			$sandbox = "sandbox/";
	    else
	        $sandbox = "";
	    $region = '';
	    if(in_array(strtoupper($currency), array('USD','ZAR')))
	    {
	    	$region = 'us';
	    }
	    elseif(in_array(strtoupper($currency), array('GBP')))
	    {
	    	$region = 'uk';
	    }
	    elseif(in_array(strtoupper($currency), array('JPY','AUD')))
	    {
	    	$region = 'jp';
	    }
	    switch (strtolower($region)) {
	        case "us":
	            return "https://static-na.payments-amazon.com/OffAmazonPayments/us/" . $sandbox . "js/Widgets.js";
	            break;
	        case "uk":
	            return "https://static-eu.payments-amazon.com/OffAmazonPayments/uk/" . $sandbox . "lpa/js/Widgets.js";
	            break;
	        case "jp":
	            return "https://static-fe.payments-amazon.com/OffAmazonPayments/jp/" . $sandbox . "lpa/js/Widgets.js";
	            break;
	        default:
	            return "https://static-eu.payments-amazon.com/OffAmazonPayments/eur/" . $sandbox . "lpa/js/Widgets.js";
	            break;
	    }
	}// JS widget url wpl_amazonpay_getWidgetsJsURL() end

	/**
    * Get region from currency wpl_amazonpay_getRegion()
    *
    */
	private function wpl_amazonpay_getRegion($currency)
	{
	    $region = 'de';
	    if(in_array(strtoupper($currency), array('USD','ZAR')))
	    {
	    	$region = 'us';
	    }
	    elseif(in_array(strtoupper($currency), array('GBP')))
	    {
	    	$region = 'uk';
	    }
	    elseif(in_array(strtoupper($currency), array('JPY','AUD')))
	    {
	    	$region = 'jp';
	    }
	    return $region;
	}// Get region from currency wpl_amazonpay_getRegion() end

	/**
    * Authorize Capture API call wpl_ap_pro_authorize()
    *
    */
	private function wpl_ap_pro_authorize($response='')
	{
		global $woocommerce;
		$apiCallParams = array( 'merchant_id' => $this->config['merchant_id'],
		                        'amazon_order_reference_id' => $response['orderReferenceId'],
		                        'authorization_amount' => $response['amount'],
		                        'currency_code' => $response['currencyCode'],
		                        'authorization_reference_id' => $response['sellerOrderId'],
		                        'capture_now' => true,
		                        'mws_auth_token' => $this->config['client_id']
		                    );

		$response_api = $this->ApiCall->AuthorizeCapture($apiCallParams,$this->config);
		$response_arr = $this->ApiCall->GetArrayResponse($response_api->response);
		if(isset($response['sellerOrderId']) && $response['sellerOrderId'] != '')
		{
			$trnid = $response['sellerOrderId'];
		}
		$args = array(
        'post_type'   => 'shop_order',
        'post_status' => array('wc'), 
        'numberposts' => 1,
        'meta_query' => array(
               array(
                   'key' => '_transaction_id',
                   'value' => $trnid,
                   'compare' => '=',
               )
           )
        );
        $post_id_arr = get_posts( $args );
        if(isset($post_id_arr[0]->ID) && $post_id_arr[0]->ID !='')
        	$order_id = $post_id_arr[0]->ID;
        
    	$order = new WC_Order($order_id);
    	update_post_meta($order_id, '_ps_payment_verify', 0);

		if(isset($response_arr['AuthorizeResult']['AuthorizationDetails']['AuthorizationStatus']['State']) && ($response_arr['AuthorizeResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Pending' || $response_arr['AuthorizeResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Completed' ||  $response_arr['AuthorizeResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Closed') )
		{
			return array(	'AmazonAuthorizationId'	=> $response_arr['AuthorizeResult']['AuthorizationDetails']['AmazonAuthorizationId'],
							'member'				=> $response_arr['AuthorizeResult']['AuthorizationDetails']['IdList']['member'],
							'AmazonOrderReferenceId'=> $response['orderReferenceId'],
							'AuthorizationReferenceId'	=> $response_arr['AuthorizeResult']['AuthorizationDetails']['AuthorizationReferenceId'],
							);
		}
		else
		{
	    	wc_add_notice( __('Error on payment: '.$response_arr['Error']['Message'], 'woo-paylabs-amazonpay'), 'error');
			wp_redirect($order->get_checkout_payment_url(false));
		}
	}// Authorize Capture API call wpl_ap_pro_authorize() end

	/**
    * Authorize On Billing subscription API call wpl_ap_pro_authorizeOnBilling()
    *
    */
	private function wpl_ap_pro_authorizeOnBilling($response='')
	{
		global $woocommerce;
		$order = new WC_Order($response['orderId']);
		$txn_id = $response['txn_id'];
		$productinfo = sprintf( esc_html__( 'Order ID: #%1$s Transaction ID: #%2$s', 'woo-paylabs-amazonpay' ), $response['orderId'], $txn_id);

		$apiCallParams = array(
            'merchant_id'                 => $this->config['merchant_id'],
            'amazon_billing_agreement_id' => $response['amazonBillingId'],
            'authorization_reference_id'  => $txn_id,
            'amount'        			  => $order->get_total(),
            'currency_code'               => $this->settings['seller_currency'],
            'seller_note'                 => $productinfo,
            'seller_billing_agreement_id' => $txn_id,
            'custom_information'          => $response['orderId'],
            'store_name'                  => get_bloginfo('name'),
        );

		$this->ApiCall->setBillingAgreementDetails($apiCallParams,$this->config);
		$apiCallParams = array(
		    'merchant_id'                 => $this->config['merchant_id'],
		    'amazon_billing_agreement_id' => $response['amazonBillingId']
		);
		$this->ApiCall->confirmBillingAgreement($apiCallParams,$this->config);
		$this->ApiCall->validateBillingAgreement($apiCallParams,$this->config);
		$apiCallParams = array(
		            'merchant_id'                 => $this->config['merchant_id'],
		            'amazon_billing_agreement_id' => $response['amazonBillingId'],
		            'authorization_reference_id'  => $txn_id,
		            'authorization_amount'        => $order->get_total(),
		            'currency_code'               => $this->settings['seller_currency'],
		            'seller_authorization_note'   => $productinfo,
		            'capture_now'                 => true,
		            'seller_note'                 => $productinfo,
		            'custom_information'          => $response['orderId'],
		            'seller_order_id'             => $txn_id,
		            'store_name'                  => get_bloginfo('name')
		        );

		$response_obj = $this->ApiCall->authorizeOnBillingAgreement($apiCallParams,$this->config);
		$response_arr = $this->ApiCall->GetArrayResponse($response_obj->response);
		update_post_meta($response['orderId'], '_ps_payment_verify', 0);

		if(isset($response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationStatus']['State']) && $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Pending' || $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationStatus']['State'] == 'Completed')
		{
			return array(	'AmazonAuthorizationId'	=> $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AmazonAuthorizationId'],
							'member'				=> $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['IdList']['member'],
							'AmazonOrderReferenceId'=> $response_arr['AuthorizeOnBillingAgreementResult']['AmazonOrderReferenceId'],
							'AuthorizationReferenceId'	=> $response_arr['AuthorizeOnBillingAgreementResult']['AuthorizationDetails']['AuthorizationReferenceId'],
							'AmazonBillingAgreementId'	=> $response['amazonBillingId'],
							);
		}
		else
		{
			wc_add_notice( __('Error on payment: '.$response_arr['Error']['Message'], 'woo-paylabs-amazonpay'), 'error');
			wp_redirect($order->get_checkout_payment_url(false));
		}
	}// Authorize On Billing subscription API call wpl_ap_pro_authorizeOnBilling() end

	/**
    * Process refund call process_refund()
    *
    */
	function process_refund( $order_id, $amount = null, $reason='' ) 
	{
		global $woocommerce;
		$order = new WC_Order($order_id);
		$capture_id 		= get_post_meta( $order_id, '_ap_capture_id', true );
		$reference_id = 'REF-'.substr(hash('sha256', mt_rand() . microtime()), 0, 10);
		$refund_note =  sprintf(__( 'Refunded %1$s%2$s Amazon Pay Refund Reference: %3$s', 'woo-paylabs-amazonpay'), $this->settings['seller_currency'], $amount, $reference_id);
		$apiCallParams = array( 'currency_code' => $this->settings['seller_currency'],
								'merchant_id' => $this->config['merchant_id'],
                                'amazon_capture_id' => $capture_id,
                                'refund_reference_id' => $reference_id,
                                'refund_amount' => $amount,
                                'seller_refund_note' => $refund_note,
                                'mws_auth_token' => $this->config['client_id']
                            );

		$response = $this->ApiCall->Refund($apiCallParams,$this->config);
		$response_arr = $this->ApiCall->GetArrayResponse($response->response);
		if(isset($response_arr['RefundResult']['RefundDetails']['RefundStatus']['State']) && ($response_arr['RefundResult']['RefundDetails']['RefundStatus']['State'] == 'Pending' || $response_arr['RefundResult']['RefundDetails']['RefundStatus']['State'] == 'Completed'))
		{
			$order->add_order_note($refund_note);
			return true;
		}
		else
		{
			return new WP_Error( 'error', __( $response_arr['Error']['Message'], 'woo-paylabs-amazonpay' ) );
		}
	}// Process refund call process_refund() end

} //  End Wpl_PayLabs_WC_Amazonpay Class