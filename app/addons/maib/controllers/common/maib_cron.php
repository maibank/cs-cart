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

if (!defined('BOOTSTRAP') || PHP_SAPI !== 'cli') {
    die('Access denied');
}

if ($mode == 'close_day' && !fn_maib_day_closed()) {
    $maib_payments = fn_maib_get_maib_payments();

    foreach ($maib_payments as $payment_id => $company_id) {
        $processor_data = fn_get_processor_data($payment_id);
        try {
            $client = fn_maib_get_client($processor_data['processor_params'], $company_id);
            $result = $client->closeDay();
        } catch (\Exception $e) {
            $result = ['company_id' => $company_id, 'exception' => $e->getMessage()];
            print($e->getMessage());
        }
        if (isset($result[MAIB_RESULT]) && $result[MAIB_RESULT] != MAIB_RESULT_OK) {
            $result['company_id'] = $company_id;
            fn_log_event('requests', 'http', [
                'response' => $result, 'url' => 'maib-close-day', 'data' => '']);
        }
        fn_maib_day_closed($result, $company_id);
    }
} elseif ($mode == 'check_orders') {
    // look for orders with maib payment method in initial status and check status
    $maib_payments = fn_maib_get_maib_payments();
    foreach ($maib_payments as $maib_payment_id => $company_id) {
        $orders_query = "SELECT o.order_id FROM ?:orders o "
            . "INNER JOIN ?:maib_transactions t ON o.order_id = t.order_id "
            . "WHERE o.status = 'N' AND o.payment_id = ?i AND t.stamp < ?i";
        $order_ids = db_get_fields($orders_query, $maib_payment_id, TIME - 1200);

        if ($order_ids) {
            echo '[INFO] Stalled orders detected' . PHP_EOL;
            $processor_data = fn_get_processor_data($maib_payment_id);
            $client = fn_maib_get_client($processor_data['processor_params'], $company_id);
            $capture = isset($processor_data['processor_params']['transaction_type'])
              && $processor_data['processor_params']['transaction_type'] == 'capture';

            foreach ($order_ids as $order_id) {
                $order_info = fn_get_order_info($order_id);
                $transaction_id = fn_maib_get_transaction_id($order_id);
                $payment_info = $client->getTransactionResult($transaction_id, $order_info['ip_address']);

                $pp_response = array();

                $result = isset($payment_info[MAIB_RESULT]) ? $payment_info[MAIB_RESULT] : null;

                if ($result == MAIB_RESULT_OK) {
                    // successfull transaction
                    $pp_response['order_status'] = $capture ? 'P' : 'O';
                    $pp_response['reason_text'] = __('transaction_approved');
                    $pp_response['transaction_id'] = $transaction_id;

                    echo '[OK] Transaction ' . $transaction_id . ' for order #' . $order_id . ' was successfull' . PHP_EOL;
                } elseif ($result != MAIB_RESULT_PENDING) {
                    $pp_response['order_status'] = 'F';
                    $pp_response['reason_text'] = __('text_transaction_declined') . ' '
                      . '[' . $payment_info[MAIB_RESULT] . ':'
                      . (isset($payment_info[MAIB_RESULT_CODE]) ? $payment_info[MAIB_RESULT_CODE] : '-') . ']';
                    $pp_response['transaction_id'] = $transaction_id;
                    $pp_response['remote_status'] = $result;

                    echo '[FAIL] Transaction ' . $transaction_id . ' for order #' . $order_id . ' failed' . PHP_EOL;
                }

                fn_finish_payment($order_id, $pp_response);
            }
        }
    }
}

exit;
