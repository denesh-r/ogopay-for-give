<?php
/**
 * Plugin Name: OGOPay for Give
 * Description: Take credit card payments via OGO Pay.
 * Author: Denesh Rajaratnam
 * Version: 1.0.0
*
 */

// Exit, if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}


add_filter('give_payment_gateways', 'ogopay_for_give_register_payment_method');
add_filter('give_get_sections_gateways', 'ogopay_for_give_register_payment_gateway_sections');
add_filter('give_get_settings_gateways', 'ogopay_for_give_register_payment_gateway_setting_fields');
add_action('give_gateway_ogopay', 'ogopay_for_give_process_donation');
add_action('give_ogopay_cc_form', 'ogopay_for_give_credit_card_form');
add_action('wp_enqueue_scripts', 'ogopay_for_give_frontend_scripts' );
add_action('parse_request', 'custom_parser');

function ogopay_for_give_register_payment_method($gateways)
{
    $gateways['ogopay'] = array(
        'admin_label'    => __('OGO Pay Credit Card', 'ogopay-for-give'), // This label will be displayed under Give settings in admin.
        'checkout_label' => __('OGO Pay Credit Card', 'ogopay-for-give'), // This label will be displayed on donation form in frontend.
    );
    
    return $gateways;
}

function ogopay_for_give_register_payment_gateway_sections($sections)
{
    $sections['ogopay-settings'] = __('OGO Pay', 'ogopay-for-give');
    return $sections;
}

function ogopay_for_give_register_payment_gateway_setting_fields($settings)
{
    switch (give_get_current_setting_section()) {
        
        case 'ogopay-settings':
            $settings = array(
                array(
                    'id'   => 'give_title_ogopay',
                    'type' => 'title',
                ),
            );
            
            $settings[] = array(
                'name' => __('Merchant ID', 'ogopay-for-give'),
                'desc' => __('Enter your Merchant ID.', 'ogopay-for-give'),
                'id'   => 'ogopay_for_give_merchant_id',
                'type' => 'text',
            );
            
            $settings[] = array(
                'name' => __('API Key', 'ogopay-for-give'),
                'desc' => __('Enter your API Key.', 'ogopay-for-give'),
                'id'   => 'ogopay_for_give_api_key',
                'type' => 'text',
            );
            
            $settings[] = array(
                'id'   => 'give_title_ogopay',
                'type' => 'sectionend',
            );
            
        break;
    }
    
    return $settings;
}

function ogopay_for_give_frontend_scripts() {

	$ogopay_params['url'] = get_site_url() . '/ogopay_get_order_details';

	wp_register_script('woocommerce_ogopay', plugins_url('ogopay.js', __FILE__), array( 'jquery' ));
	wp_localize_script('woocommerce_ogopay', 'ogopay_params', $ogopay_params);

	wp_enqueue_script('zoid', 'https://ogo-hosted-pages.s3.amazonaws.com/zoid.js');
	wp_enqueue_script('woocommerce_ogopay');

	wp_register_style('ogopay-style', plugins_url('ogopay.css', __FILE__));
	wp_enqueue_style('ogopay-style');

}

function ogopay_for_give_process_donation($posted_data)
{
    
    // Make sure we don't have any left over errors present.
    give_clear_errors();
    
    // Any errors?
    $errors = give_get_errors();
    
    // No errors, proceed.
    if (! $errors) {
        $form_id         = intval($posted_data['post_data']['give-form-id']);
        $price_id        = ! empty($posted_data['post_data']['give-price-id']) ? $posted_data['post_data']['give-price-id'] : 0;
        $donation_amount = ! empty($posted_data['price']) ? $posted_data['price'] : 0;
        
        // Setup the payment details.
        $donation_data = array(
            'price'           => $donation_amount,
            'give_form_title' => $posted_data['post_data']['give-form-title'],
            'give_form_id'    => $form_id,
            'give_price_id'   => $price_id,
            'date'            => $posted_data['date'],
            'user_email'      => $posted_data['user_email'],
            'purchase_key'    => $posted_data['purchase_key'],
            'currency'        => give_get_currency($form_id),
            'user_info'       => $posted_data['user_info'],
            'status'          => 'pending',
            'gateway'         => 'ogopay',
        );
        
        // Record the pending donation.
        $donation_id = give_insert_payment($donation_data);
        
        if (! $donation_id) {
            
            // Record Gateway Error as Pending Donation in Give is not created.
            give_record_gateway_error(
                __('OGO Pay Error', 'ogopay-for-give'),
                sprintf(
                    /* translators: %s Exception error message. */
                    __('Unable to create a pending donation with Give.', 'ogopay-for-give')
                )
            );
                
            // Send user back to checkout.
            give_send_back_to_checkout('?payment-mode=ogopay');
            return;
		}
		
		give_send_back_to_checkout('?payment-mode=ogopay&key=' . $posted_data['purchase_key']);
            
    } else {
            
            // Send user back to checkout.
        give_send_back_to_checkout('?payment-mode=ogopay');
    } // End if().
}


function handle_gateway_response()
{
	$order_id = $_REQUEST['orderId'];
	// $order = give_get_payment_by('key', $order_id);
	$donation_id = give_get_donation_id_by_key($order_id);

	// if (($_REQUEST['success'] == 'true') && ($order->get_total() == $_REQUEST['amount'])) {
	if ($_REQUEST['success'] == 'true') {
		//mark payment complete
		give_update_payment_status( $donation_id, 'publish' );
		give_set_payment_transaction_id($donation_id, $_REQUEST['transactionId']);
		give_insert_payment_note($donation_id, $_REQUEST['transactionDetails']);
		$checkout_url = urlencode(give_get_success_page_url());

	} else {
		// set checkout failed url with reason
		give_update_payment_status( $donation_id, 'failed' );
		give_set_payment_transaction_id($donation_id, $_REQUEST['transactionId']);
		give_insert_payment_note( $donation_id, __( 'Charge failed.', 'give' ) );
		give_insert_payment_note($donation_id, $_REQUEST['transactionDetails']);
		$checkout_url = urlencode(give_get_failed_transaction_uri());
	}
	
	// redirect to our custom page that closes the modal and redirects the page to the given url
	wp_safe_redirect(add_query_arg( array(
		'url' => $checkout_url
	), get_site_url() . '/ogopay_close_modal'));
	exit();
}

function ogopay_for_give_credit_card_form()
{
    echo '<div id="myModal" class="modal">';
    echo '<div class="modal-content">';
    echo '<div id="modalClose" class="close">&times;</div>';
    echo '<div id="cont"></div>';
    echo '</div>';
    echo '</div>';
}

// Endpoint that responds with order details from orderId passed via request
function get_order_details()
{
	$key = $_REQUEST['key'];
	$order = give_get_payment_by('key', $key);

	//strip decimals and commas
	$amount = str_replace(".", "", $order->total);
	$amount = str_replace(",", "", $order->total);
	//we need the amount in cents
	$amount = intval($amount) * 100;

	$customerId = $order->customer_id;
	$merchantId = give_get_option('ogopay_for_give_merchant_id');
	$time = time();

	$orderDetails = array(
		'orderId' => strval($key),
		'customerId' => strval($customerId),
		'merchantId' => $merchantId,
		'amount' => strval($amount),
		'time' => strval($time),
		'returnUrl' => urlencode(get_site_url() . '/ogopay_gateway_response')
	);

	$hash = generateHash($orderDetails);
	$orderDetails['hash'] = $hash;

	wp_send_json($orderDetails);
}

function custom_parser() {

	global $wp;
	$url = $wp->request;

	// display the close modal page
    if ($url == 'ogopay_close_modal') {
		$file_path = plugin_dir_path(__FILE__ ) . 'close_modal.html';
		$response = file_get_contents($file_path);
		echo $response;
 	    exit();
	}

	// when redirected to the payment methods page after adding a card, 
	// display the appropriate notice based on the result
	if ($url == 'ogopay_get_order_details') {
		get_order_details();
	}

	if ($url == 'ogopay_gateway_response') {
		handle_gateway_response();
	}
}

function generateHash($data_array)
{
	ksort($data_array);	// alphabetically sort keys
	$params = json_encode($data_array); // convert array to json

	$apiKey = give_get_option('ogopay_for_give_api_key');

	$hashed_params = hash_hmac('sha256', $params, $apiKey, true);
	$encoded_hashed_params = base64url_encode($hashed_params);

	return $encoded_hashed_params;
}

function base64url_encode($data)
{
	return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}