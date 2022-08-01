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

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'reverse') {
        $return = array(CONTROLLER_STATUS_REDIRECT, fn_url('orders.details?order_id=' . $_REQUEST['order_id']));

        if (!$order_info = fn_get_order_info($_REQUEST['order_id'])) {
            fn_set_notification('E', __('error'), 'Invalid order ID', true);
            return $return;
        }

        // check edit permissions
        if (empty($auth['user_id']) || !fn_check_current_user_access('edit_order')) {
            fn_set_notification('E', __('error'), __('access_denied_text'), true);
            return $return;
        }

        if ($auth['company_id'] != $order_info['payment_method']['company_id']) {
            fn_set_notification('E', __('error'), __('maib.payment_not_yours'), true);
            return $return;
        }

        $amount = isset($_REQUEST['maib_reverse_amount']) ? floatval($_REQUEST['maib_reverse_amount']) : 0;
        if (!$amount || $amount <= 0 || $amount > $order_info['total']) {
            fn_set_notification('E', __('error'), __("maib.invalid_amount"), true);
            return $return;
        }

        // check is maib payment
        $maib_payments = fn_maib_get_maib_payments();
        if (!isset($maib_payments[$order_info['payment_id']])) {
            fn_set_notification('E', __('error'), 'Wrong payment ID', true);
            return $return;
        }

        if (empty($order_info['payment_info']['transaction_id'])) {
            fn_set_notification('E', __('error'), 'Missing transaction ID', true);
            return $return;
        }

        $client = fn_maib_get_client($order_info['payment_method']['processor_params'], $order_info['company_id']);
        if (!$client) {
            fn_set_notification('E', __('error'), 'Invalid order ID', true);
            return $return;
        }

        try {
            $result = $client->revertTransaction($order_info['payment_info']['transaction_id'], $amount);

            if (!empty($result['error'])) {
                throw new \Exception($result['error']);
            }
            $status = isset($result[MAIB_RESULT]) ? $result[MAIB_RESULT] : '';

            if ($status == MAIB_RESULT_OK) {
                fn_set_notification('N', /*__('notice')*/'', __('successful'), true);
                fn_log_event('orders', 'update', [
                    'order_id' => $order_info['order_id'],
                    'maib_transaction_id' => $order_info['payment_info']['transaction_id'] . ' ' . __("maib.reverse_label") . ' - ' . $amount . ' MDL'
                ]);
                $pp_response['revert ' . time()] = $amount . ' MDL ' . date('Ymd H:i:s');
                fn_update_order_payment_info($order_info['order_id'], $pp_response);
            } elseif ($status == MAIB_RESULT_FAILED) {
                fn_set_notification('E', __('error'), ' result code: ' . $result[MAIB_RESULT_CODE], true);
            } elseif ($status == MAIB_RESULT_REVERSED) {
                fn_set_notification('E', __('error'), 'Already reversed, result code: '
                . $result[MAIB_RESULT_CODE], true);
            } else {
                throw new \Exception('Unknown return status');
            }
            return $return;
        } catch (\Exception $e) {
            fn_set_notification('E', __('error'), $e->getMessage(), true);
            return $return;
        }
        exit;
    }
}

if ($mode == 'reverse' && isset($_REQUEST['order_id']) && ($order_info = fn_get_order_info($_REQUEST['order_id']))) {
    Tygh::$app['view']->assign('maib_currency', 'MDL');
    Tygh::$app['view']->assign('order_info', $order_info);
    Tygh::$app['view']->display('addons/maib/views/maib_order/reverse.tpl');
    exit;
}

if ($mode == 'check') {
    $return_url = fn_url(isset($_REQUEST['return']) ? $_REQUEST['return'] : null);

    if (
        isset($_REQUEST['order_id'])
        && ($transaction_id = fn_maib_get_transaction_id($_REQUEST['order_id']))
        && ($order_info = fn_get_order_short_info($_REQUEST['order_id']))
        && $order_info['status'] == INCOMPLETED
    ) {
        $order_info = fn_get_order_info($_REQUEST['order_id']);
        $order_info['order_id'] = fn_maib_get_order_id($transaction_id);
        $processor_data = fn_get_processor_data($order_info['payment_id']);
        $capture = isset($processor_data['processor_params']['transaction_type'])
          && $processor_data['processor_params']['transaction_type'] == 'capture';

        // check payment status
        $client = fn_maib_get_client($processor_data['processor_params'], $order_info['company_id']);
        try {
            $payment_info = $client->getTransactionResult($transaction_id, $order_info['ip_address']);
            if (isset($payment_info['error'])) {
                throw new \Exception($payment_info['error']);
            }
        } catch (\Exception $e) {
            fn_set_notification('E', __('error'), $e->getMessage(), true);
            fn_redirect($return_url);
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

        fn_finish_payment($order_info['order_id'], $pp_response);
//         fn_order_placement_routines('route', $order_info['order_id']);
    }

    fn_redirect($return_url);
    exit;
}
