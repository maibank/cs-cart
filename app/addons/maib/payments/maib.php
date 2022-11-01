<?php

/***************************************************************************
*                                                                          *
*   (c) 2020 Indrivo SRL                                                   *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************/

use Maib\MaibApi\MaibClient;

if (!defined('AREA')) {
    echo 'Access denied';
    exit;
}

if (defined('PAYMENT_NOTIFICATION')) {
    /**
     * Receiving and processing the answer
     * from third-party services and payment systems.
     *
     * Available variables:
     * @var string $mode The purpose of the request
     */

    $transaction_id = isset($_REQUEST[MAIB_TRANS_ID]) ? $_REQUEST[MAIB_TRANS_ID] : null;
    $order_id = fn_maib_get_order_id($transaction_id);
    $error_message = isset($_POST['error']) ? $_POST['error'] : null;

    if ($mode == 'return' && $order_id) {
        $order_info = fn_get_order_info($order_id);
        if (empty($processor_data)) {
            $processor_data = fn_get_processor_data($order_info['payment_id']);
        }
        $capture = isset($processor_data['processor_params']['transaction_type'])
          && $processor_data['processor_params']['transaction_type'] == 'capture';

        // check payment status
        $client = fn_maib_get_client($processor_data['processor_params'], $order_info['company_id']);
        try {
            $payment_info = $client->getTransactionResult($transaction_id, $order_info['ip_address']);
        } catch (\Exception $e) {
            fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $e->getMessage()]);
            fn_set_notification('E', __('error'), __('text_transaction_declined'), true);
            fn_redirect(fn_url('checkout.checkout'));
            exit;
        }
        $pp_response = array();

        $result = isset($payment_info[MAIB_RESULT]) ? $payment_info[MAIB_RESULT] : null;

        if ($result == MAIB_RESULT_OK) {
            // successfull transaction
            $pp_response['order_status'] = $capture ? 'P' : 'O';
            $pp_response['reason_text'] = __('transaction_approved');
            $pp_response['transaction_id'] = $transaction_id;
        } elseif ($result != MAIB_RESULT_PENDING) {
            $pp_response['order_status'] = 'F';
            $pp_response['reason_text'] = __('text_transaction_declined') . ' '
              . '[' . $payment_info[MAIB_RESULT] . ':'
              . (isset($payment_info[MAIB_RESULT_CODE]) ? $payment_info[MAIB_RESULT_CODE] : '-') . ']';
            $pp_response['transaction_id'] = $transaction_id;
        } else {
            // still pending
            $pp_response['order_status'] = $order_info['status'];//'O';
            $pp_response['reason_text'] = __('pending');
            $pp_response['transaction_id'] = $transaction_id;
        }

        if (fn_check_payment_script('maib.php', $order_id)) {
            fn_finish_payment($order_id, $pp_response);
            fn_order_placement_routines('route', $order_id);
        }
        exit;
    } elseif ($mode == 'fail') {
        $message = 'Transaction id ' . $transaction_id . ' error: ' . $error_message;
    } else {
        $message = 'Unknown mode. Transaction id ' . $transaction_id . ' error: ' . $error_message;
    }

    // log about failed event
    fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $message]);

    // go back to cart and let customer chose another method or retry
    fn_set_notification('E', __('error'), __('text_transaction_declined'), true);
    fn_redirect(fn_url('checkout.checkout'));
    exit;
} else {
    /**
     * Running the necessary logics for payment acceptance
     * get token & redirect to gateway
     */

    $processor_data['processor_params'] += [
        'mode' => 'test',
        'private_key' => '',
        'pkey_pass' => '',
        'public_key' => '',
        'transaction_type' => 'capture',
    ];

    /* \Fruitware\MaibApi\MaibClient */
    $client = fn_maib_get_client($processor_data['processor_params'], $order_info['company_id']);

    $amount = (float)$order_info['total'];
    $currency = CART_PRIMARY_CURRENCY;
    if ($currency != MAIB_CURRENCY_MDL && $currency != MAIB_CURRENCY_EUR) {
        fn_set_notification('E', __('error'), __('maib.wrong_currency'), true);
        return;
    }
    $client_ip = $order_info['ip_address'];
    $description = 'Order #' . $order_id;
    $language = CART_LANGUAGE;
    $capture = $processor_data['processor_params']['transaction_type'] == 'capture';
    $payment_url = ($processor_data['processor_params']['mode'] == 'live')
        ? MaibClient::MAIB_LIVE_REDIRECT_URL
        : MaibClient::MAIB_TEST_REDIRECT_URL;
    try {
        $transaction = $capture ?
            $client->registerSmsTransaction($amount, $currency, $client_ip, $description, $language) :
            $client->registerDmsAuthorization($amount, $currency, $client_ip, $description, $language);
    } catch (\Exception $e) {
        fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $e->getMessage()]);
        fn_set_notification('E', __('error'), __('text_transaction_declined'), true);
        return;
    }

    if (!empty($transaction['error'])) {
        fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $transaction['error']]);
    } elseif (!empty($transaction[MAIB_TRANSACTION_ID])) {
        $transaction_id = $transaction[MAIB_TRANSACTION_ID];
        fn_maib_store_transaction($order_id, $transaction_id);
        fn_log_event('orders', 'update', ['order_id' => $order_id, 'maib_transaction_id' => $transaction_id]);

        $post_data = array(
            MAIB_TRANS_ID => $transaction_id,
        );
        fn_create_payment_form($payment_url, $post_data, $processor_data['processor']);
    } else {
        fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => 'null transaction_id']);
    }
}
