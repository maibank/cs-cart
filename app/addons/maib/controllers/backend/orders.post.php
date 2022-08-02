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

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    echo 'Access denied';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
}

if ($mode == 'details') {
    $order_id = $_REQUEST['order_id'];
    $order_info = $order_id ? fn_get_order_info($order_id) : array();
    $maib_payments = fn_maib_get_maib_payments();
    $payment_id = isset($order_info['payment_method']['payment_id']) ?
      $order_info['payment_method']['payment_id'] : null;
    $payment_complete = in_array($order_info['status'], array('P', 'C'));
    $maib_transaction_id = isset($order_info['payment_info']['transaction_id']) ?
        $order_info['payment_info']['transaction_id'] : null;

    $maib_was_used = false;
    if (
        $payment_id
        && isset($maib_payments[$payment_id])
        && $payment_complete
        && $maib_transaction_id
    ) {
        $maib_was_used = true;
    }
    Tygh::$app['view']->assign('maib_was_used', $maib_was_used);

    $maib_incomplete_order = false;
    if (
        $payment_id && isset($maib_payments[$payment_id])
        && $order_info['status'] == INCOMPLETED
        && fn_maib_get_transaction_id($order_id)
    ) {
        $maib_incomplete_order = true;
    }
    Tygh::$app['view']->assign('maib_incomplete_order', $maib_incomplete_order);
}
