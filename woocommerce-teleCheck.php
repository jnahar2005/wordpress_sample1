<?php
/* Synergytop TeleCheck Payment Gateway Class */
class Tem_TeleCheck extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "tem_teleCheck";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "Synergytop TeleCheck", 'tem-teleCheck' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "Synergytop TeleCheck Payment Gateway Plug-in for WooCommerce", 'tem-teleCheck' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "Synergytop TeleCheck", 'tem-teleCheck' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// Supports the default credit card form
		//$this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	} // End __construct()

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'tem-teleCheck' ),
				'label'		=> __( 'Enable this payment gateway', 'tem-teleCheck' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'tem-teleCheck' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'tem-teleCheck' ),
				'default'	=> __( 'TeleCheck', 'tem-teleCheck' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'tem-teleCheck' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'tem-teleCheck' ),
				'default'	=> __( 'Pay securely using your TeleCheck.', 'tem-teleCheck' ),
				'css'		=> 'max-width:350px;'
			),
			'RNID' => array(
				'title'		=> __( 'RetailNetID', 'tem-teleCheck' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API RetailNetID provided by by Synergytop when you signed up for an account.', 'tem-teleCheck' ),
			),
			'RNCERT' => array(
				'title'		=> __( 'RetailNet Site Certificate', 'tem-teleCheck' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Issued by Synergytop and used to secure and authenticate the connection. Store and transmit this element securely.', 'tem-teleCheck' ),
			),
			'environment' => array(
				'title'		=> __( 'TeleCheck Test Mode', 'tem-teleCheck' ),
				'label'		=> __( 'Enable Test Mode', 'tem-teleCheck' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'tem-teleCheck' ),
				'default'	=> 'no',
			)
		);		
	}

	 function payment_fields(){
	 	if($this -> description) echo wpautop(wptexturize($this -> description));
	 	echo 'Example : <a href="'.plugins_url('/woocommerce-gateway-teleCheck/example.png').'" target="_blank" alt="example" ><img class="telecheckImg" src="'.plugins_url('/woocommerce-gateway-teleCheck/example.png').'" title="Example" /></a>';
        echo '<table cellspacing="0" class="widefat wc_input_table sortable form-row">
          <thead>
            <tr class="">
              <th class="sort">&nbsp;</th>
               	<th>Account Holder Name<abbr class="required" title="required">*</abbr></th>   
               	<th>Routing Number<abbr class="required" title="required">*</abbr></th>                  
              	<th>Account Number<abbr class="required" title="required">*</abbr></th>                     
               	<th>Bank Name<abbr class="required" title="required">*</abbr></th>             
              	<th>Driver’s License<abbr class="required" title="required">*</abbr></th>
              	<th>Driver’s License State<abbr class="required" title="required">*</abbr></th>
            </tr>
          </thead>
          <tbody class="accounts ui-sortable">
            <tr class="account ui-sortable-handle ">
                  	<td class="sort"></td>
                  	<td><input type="text" name="teleCheck[Account Holder Name]" value=""></td>
                  	<td><input type="text" name="teleCheck[Routing Number]" value=""></td>
                  	<td><input type="text" name="teleCheck[Account Number]" value=""></td>
                  	<td><input type="text" name="teleCheck[Bank Name]" value=""></td>                              
                  	<td><input type="text" name="teleCheck[Driver’s License]" value=""></td>
                  	<td><select name="teleCheck[Driver’s License State]">
						  <option value="">Select State</option>
					';					
					foreach ($this->statesListData() as $key => $value) {

						echo "<option value='$key'>$value</option>";	
					}
                  echo '</select> 
                  </td>
                </tr>         
          </tbody>          
        </table><style type="text/css">.telecheckImg {width: 100px; height: 30px;}</style>';
    }

	
	// Submit payment and handle response
	public function process_payment( $order_id ) {
		global $woocommerce;
		
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );		
		
		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';
		
		// Decide which URL to post to
		$environment_url = ( $this->environment == "yes" ) ? 'https://dev.spectrumretailnet.com/ppsapi' : 'https://secure4.spectrumretailnet.com/PPSAPI';			
				
		$xml	= sprintf("<TRANSACTION>
					<RNID>%s</RNID>
					<RNCERT>%s</RNCERT>
					<TRANSACTIONTYPE>TCAUTH</TRANSACTIONTYPE>
					<SERVICENAME>ICA</SERVICENAME>
					<CHECKAMT>%s</CHECKAMT>
					<CHECKROUTING>%s</CHECKROUTING>
					<CHECKACCOUNT>%s</CHECKACCOUNT>
					<CHECKDL>%s</CHECKDL>
					<CHECKDLSTATE>%s</CHECKDLSTATE>
					</TRANSACTION>",
					$this->RNID,
					$this->RNCERT,
					$customer_order->get_total(),
					$_POST['teleCheck']['Routing Number'],
					$_POST['teleCheck']['Account Number'],
					$_POST['teleCheck']['Driver’s License'],
					$_POST['teleCheck']['Driver’s License State']
					);			

		// Send this payload to Authorize.net for processing
		$response = wp_remote_post( $environment_url, array(
			'method'    => 'POST',
			'body'      => $xml,
			'timeout'   => 90,
			'sslverify' => false,
		) );

		if ( is_wp_error( $response ) ) 
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'tem-teleCheck' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'Synergytop TeleCheck\'s Response was empty.', 'tem-teleCheck' ) );
			
		// Retrieve the body's resopnse if no errors found
		$response_body = wp_remote_retrieve_body( $response );

		$xml = simplexml_load_string($response_body);
		$json = json_encode($xml);
		$resp = json_decode($json,TRUE);
		
		// Get the values we need
		$r['response_code']             = $resp['TCRESPONSECODE'];	
		$r['response_status']           = $resp['TCECASTATUS'];		
		$r['response_sub_code']         = $resp['TRANSUCCESS'];
		$r['response_reason_code']      = $resp['TCDISPLAYTEXT'];
		$r['response_reason_text']      = $resp['TRANRESPMESSAGE'];
		$r['response_reason_text']      = $json;
		// Test the code to know if the transaction went through or not.
		// 1 or 4 means the transaction was a success
		if ($r['response_sub_code']=="TRUE" && $r['response_status']==1 && ($r['response_code']==7 || $r['response_code']==95)) {
			// Payment has been successful
			$customer_order->add_order_note( __( 'Synergytop TeleCheck payment completed.', 'tem-teleCheck' ) );
												 
			// Mark order as Paid
			$customer_order->payment_complete();

			// Empty the cart (Very important step)
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			// Transaction was not succesful
			// Add notice to the cart
			wc_add_notice( $r['response_reason_text'], 'error' );
			// Add note to the order for your reference
			$customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
		}

	}
	
	// Validate fields
	public function validate_fields() {	
		if(isset($_POST['teleCheck'])){
			foreach ($_POST['teleCheck'] as $key => $value) {
				if($value==''){
					SV_WC_Helper::wc_add_notice( esc_html__(sprintf("%s is missing", $key), 'tem-teleCheck' ), 'error' );
					$error[] = 1;					
				}else{						
					$error[]=0;
				}

			}
		}

		if(in_array(1, $error)){
			return false;
		}
		else{
			return true;
		}
	}
	
	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

/*Validate Routing */
public function validateRouting($routing)
	{
	$dig1 = 0; 
	$dig2 = 0; 
	$dig3 = 0;
	$dig4 = 0; 
	$dig5 = 0;
	$dig6 = 0; 
	$dig7 = 0; 
	$dig8 = 0;
	$dig9 = 0;
	$dig9 = (int)$routing % 10;
	$routing /= 10;
	$dig8 = (int)$routing % 10;
	$routing /= 10;
	$dig7 = (int)$routing % 10;
	$routing /= 10;
	$dig6 = (int)$routing % 10;
	$routing /= 10;
	$dig5 = (int)$routing % 10;
	$routing /= 10;
	$dig4 = (int)$routing % 10;
	$routing /= 10;
	$dig3 = (int)$routing % 10;
	$routing /= 10;
	$dig2 = (int)$routing % 10;
	$routing /= 10;
	$dig1 = (int)$routing;
	$cal = (((3 * ($routing + $dig4 + $dig7)) + (7 * ($dig2 + $dig5 + $dig8)) + ($dig3 + $dig6 + $dig9)) % 10);
	if ($cal == 0)
	{
	return 1;
	}
	return 0;
}


public function statesListData()
{
	$states = array(
	'AL'=>'Alabama',
	'AK'=>'Alaska',
	'AZ'=>'Arizona',
	'AR'=>'Arkansas',
	'CA'=>'California',
	'CO'=>'Colorado',
	'CT'=>'Connecticut',
	'DC'=>'District of Columbia',
	'FL'=>'Florida',
	'GA'=>'Georgia',
	'HI'=>'Hawaii',
	'ID'=>'Idaho',
	'IL'=>'Illinois',
	'IN'=>'Indiana',
	'IA'=>'Iowa',
	'KS'=>'Kansas',
	'KY'=>'Kentucky',
	'LA'=>'Louisiana',
	'ME'=>'Maine',
	'MD'=>'Maryland',
	'MA'=>'Massachusetts',
	'MI'=>'Michigan',
	'MN'=>'Minnesota',
	'MS'=>'Mississippi',
	'MO'=>'Missouri',
	'MT'=>'Montana',
	'NE'=>'Nebraska',
	'NV'=>'Nevada',
	'NH'=>'New Hampshire',
	'NJ'=>'New Jersey',
	'NM'=>'New Mexico',
	'NY'=>'New York',
	'NC'=>'North Carolina',
	'ND'=>'North Dakota',
	'OH'=>'Ohio',
	'OK'=>'Oklahoma',
	'OR'=>'Oregon',
	'PA'=>'Pennsylvania',
	'RI'=>'Rhode Island',
	'SC'=>'South Carolina',
	'SD'=>'South Dakota',
	'TN'=>'Tennessee',
	'TX'=>'Texas',
	'UT'=>'Utah',
	'VT'=>'Vermont',
	'VA'=>'Virginia',
	'WA'=>'Washington',
	'WV'=>'West Virginia',
	'WI'=>'Wisconsin',
	'WY'=>'Wyoming',
	);
	return $states;
}


} // End of tem-teleCheck