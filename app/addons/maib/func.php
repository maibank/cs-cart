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

use Tygh\Embedded;
use Tygh\Registry;
use Tygh\Settings;
use Tygh\Http;

include __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\MessageFormatter;
use Maib\MaibApi\MaibClient;

if (!defined('AREA')) {
    die('Access denied');
}

/**
 * Uninstall callback
 */
function fn_maib_delete_payment_processors()
{
    db_query("DELETE FROM ?:payment_descriptions WHERE payment_id IN"
      . "(SELECT payment_id FROM ?:payments WHERE processor_id IN"
      . "(SELECT processor_id FROM ?:payment_processors WHERE processor_script IN ('maib.php')))");
    db_query("DELETE FROM ?:payments WHERE processor_id IN"
      . "(SELECT processor_id FROM ?:payment_processors WHERE processor_script IN ('maib.php'))");
    db_query("DELETE FROM ?:payment_processors WHERE processor_script IN ('maib.php')");
    db_query("DROP TABLE ?:maib_transactions");
}

/**
 * Creates and returns MaibClient object
 * @staticvar MaibClient $client
 * @param array $params - payment processor settings params
 * @return \Fruitware\MaibApi\MaibClient
 */
function fn_maib_get_client($params = null, $company_id = 0)
{
    static $client;

    if (isset($client[$company_id])) {
        return $client[$company_id];
    }

    if (!$params) {
        $payment_id = fn_maib_detect_payment_id();
        $processor_data = fn_get_processor_data($payment_id);
        $params = $processor_data['processor_params'];
    }

    $live = $params['mode'] == 'live';

    $options = [
        'base_uri' => $live ? MaibClient::MAIB_LIVE_BASE_URI : MaibClient::MAIB_TEST_BASE_URI,
        'debug' => false,
        'verify' => true,
        'ssl_key' => [$params['private_key'], $params['pkey_pass']],
        'cert' => $params['public_key'],
        'config' => [
            'curl' => [
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
            ]
        ]
    ];

    if (!empty($params['debug_log'])) {
        $log_file = fn_maib_get_log_dir($company_id) . 'maib_request.log';
        $log = new Logger('maib_request');
        $log->pushHandler(new StreamHandler($log_file, Logger::DEBUG));
        $formatter = new MessageFormatter(MessageFormatter::DEBUG);
        $stack = HandlerStack::create();
        $stack->push(
            Middleware::log($log, $formatter)
        );

        $options['handler'] = $stack;
    }

    $guzzleClient = new Client($options);
    $client[$company_id] = new MaibClient($guzzleClient);

    return $client[$company_id];
}

/**
 * Detect id of maib payment
 * @return int
 */
function fn_maib_detect_payment_id()
{
    return db_get_field("SELECT p.payment_id FROM ?:payment_processors pp "
        . "INNER JOIN ?:payments p ON pp.processor_id = p.processor_id "
        . "WHERE pp.processor_script LIKE 'maib.php'");
}

/**
 * Store transaction id for an order before redirecting to maib server
 * @param int $order_id
 * @param string $transaction_id
 */
function fn_maib_store_transaction($order_id, $transaction_id)
{
    db_query("DELETE FROM ?:maib_transactions WHERE order_id = ?i", $order_id);
    if ($transaction_id) {
        $data = ['order_id' => $order_id, 'transaction_id' => $transaction_id, 'stamp' => time()];
        db_query("INSERT INTO ?:maib_transactions ?m", [$data]);
    }
}

/**
 * Retrieves order_id for an transaction id
 * @param string $transaction_id received from MAIB
 * @return int order_id or null
 */
function fn_maib_get_order_id($transaction_id)
{
    if ($transaction_id) {
        return db_get_field("SELECT order_id FROM ?:maib_transactions WHERE transaction_id = ?s", $transaction_id);
    }
}

function fn_maib_get_transaction_id($order_id)
{
    if ($order_id) {
        return db_get_field("SELECT transaction_id FROM ?:maib_transactions WHERE order_id = ?i", $order_id);
    }
}

/**
 * Hook save_log
 * We reuse orders/update event logging
 * and will alter $contents array to include additional info
 * @see fn_log_event()
 * @param $type
 * @param $action
 * @param $data
 * @param $user_id
 * @param $content
 * @param $event_type
 * @param $object_primary_keys
 */
function fn_maib_save_log($type, $action, $data, $user_id, &$content, &$event_type, $object_primary_keys)
{
    if ($type == 'orders' && $action == 'update') {
        if (isset($data['maib_transaction_id'])) {
            $content['message'] = 'MAIB transaction_id ' . $data['maib_transaction_id'];
        } elseif (isset($data['error'])) {
            $content['message'] = 'MAIB error: ' . $data['error'];
            // this should be logged as an error
            $event_type = 'E';
        }
    }
}

/**
 * Hook change_order_status
 * Check if order is in <Open> status and perform payment capture if required
 * @param string $status_to
 * @param string $status_from
 * @param array $order_info
 * @param mixed $force_notification
 * @param mixed $order_statuses
 * @param mixed $place_order
 */
function fn_maib_change_order_status(
    &$status_to,
    $status_from,
    $order_info,
    $force_notification,
    $order_statuses,
    $place_order
) {
    $order_id = $order_info['order_id'];
    if (
        $order_id && $order_info['status'] == 'O'
        && fn_check_payment_script('maib.php', $order_info['order_id'])
        && in_array($status_to, ['P', 'C'])
    ) {
        $processor_data = fn_get_processor_data($order_info['payment_id']);
        $client = fn_maib_get_client($processor_data['processor_params'], $order_info['company_id']);
        $transaction_order_id = empty($order_info['parent_order_id']) ?
            $order_info['order_id'] : $order_info['parent_order_id'];
        $transaction_id = fn_maib_get_transaction_id($transaction_order_id);
        $amount = (float)$order_info['total'];
        $currency = CART_PRIMARY_CURRENCY;
        if ($currency != MAIB_CURRENCY_MDL) {
            $amount = fn_format_price_by_currency($amount, CART_PRIMARY_CURRENCY, MAIB_CURRENCY_MDL);
            $currency = MAIB_CURRENCY_MDL;
        }
        $client_ip = $order_info['ip_address'];

        // capture transaction
        if ($transaction_id) {
            $transaction = $client->makeDMSTrans(
                $transaction_id,
                $amount,
                $currency,
                $client_ip,
                'Order #' . $order_id,
                CART_LANGUAGE
            );

            if (!empty($transaction['error'])) {
                $error = $transaction['error'];
                fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $transaction['error']]);
            } elseif (!empty($transaction[MAIB_RESULT]) && $transaction[MAIB_RESULT] == MAIB_RESULT_OK) {
                fn_log_event('orders', 'update', [
                    'order_id' => $order_id,
                    'maib_transaction_id' => __('maib.capture_performed') . ' - ' . $transaction_id
                ]);
            } else {
                $error = 'Invalid response for capture transaction';
                fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $error]);
            }
        } else {
            $error = 'Failed to capture order payment without transaction id';
            fn_log_event('orders', 'update', ['order_id' => $order_id, 'error' => $error]);
        }
        if (!empty($error)) {
            // force status change to fail
            $status_to = $status_from;
        }
    }
}

/**
 * Check if business day was closed
 * @return boolean
 */
function fn_maib_day_closed($mark_as_closed = false, $company_id = 0)
{
  // close business day after midnight
    $today = date('Ymd');
    $lock_file = fn_maib_get_log_dir($company_id) . 'maib_close_day.' . $today;

    if ($mark_as_closed) {
        foreach (glob(fn_maib_get_log_dir($company_id) . 'maib_close_day.*') as $filename) {
            unlink($filename);
        }
        file_put_contents($lock_file, TIME . ':' . json_encode($mark_as_closed));
    }

    return file_exists($lock_file);
}

function fn_maib_get_log_dir($company_id = 0)
{
    $dir = Registry::get('config.dir.var') . 'maib/' . $company_id . '/';

    if (!file_exists($dir)) {
        mkdir($dir, 0750, true);
    }

    return $dir;
}

/**
 * Hook update_payment_pre
 * Handle PEM files upload or, if provided, pfx file extraction
 * @param array $payment_data
 * @param int $payment_id
 * @param string $lang_code
 * @param string $certificate_file
 * @param string $certificates_dir
 * @param mixed $can_remove_offline_payment_params
 */
function fn_maib_update_payment_pre(
    &$payment_data,
    $payment_id,
    $lang_code,
    $certificate_file,
    $certificates_dir,
    $can_remove_offline_payment_params
) {
    $company_id = isset($payment_data['company_id']) ? $payment_data['company_id'] : 0;
    $dir = fn_maib_get_log_dir($company_id);
    $private_key = $dir . 'key.pem';
    $public_key = $dir . 'cert.pem';
    $pfx_import = !empty($_POST['maib_pfx_import']);
    $isset_pfx_file = isset($_FILES['maib_pfx_file']['error']);
    $pfx_file_error = $isset_pfx_file ? $_FILES['maib_pfx_file']['error'] : null;

    // PFX files have higher priority and will overWrite PEM files settings
    if (
        $pfx_import && $isset_pfx_file && $pfx_file_error == UPLOAD_ERR_OK
        && is_uploaded_file($_FILES['maib_pfx_file']['tmp_name'])
        && function_exists('openssl_pkcs12_read')
    ) {
        $pfx_file = $_FILES['maib_pfx_file']['tmp_name'];
        $pfx_pass = isset($_POST['maib_pfx_pass']) ? $_POST['maib_pfx_pass'] : '';
        $pfx_force_pks1 = fn_maib_is_curl_nss();
        $pfx_data = file_get_contents($pfx_file);

        if (
            $pfx_data !== false
            && ($result = fn_maib_extract_certificates($pfx_data, $pfx_pass, $pfx_force_pks1))
        ) {
            file_put_contents($private_key, $result['key']);
            file_put_contents($public_key, $result['pcert'] . $result['cacert']);
            $payment_data['processor_params']['private_key'] = $private_key;
            $payment_data['processor_params']['pkey_pass'] = $pfx_pass;
            $payment_data['processor_params']['public_key'] = $public_key;
        } else {
            // fn_set_notification('E', __('error'), 'PFX file processing failed');
        }
    } elseif ($pfx_import && $isset_pfx_file && $pfx_file_error /*!= UPLOAD_ERR_NO_FILE*/) {
        fn_set_notification('E', __('error'), 'PFX file upload failed, error ' . $_FILES['maib_pfx_file']['error']);
    } elseif ($isset_pfx_file && $pfx_file_error == UPLOAD_ERR_OK && !function_exists('openssl_pkcs12_read')) {
        fn_set_notification('E', __('error'), 'PFX file processing failed, php openssl module not found');
    } elseif (!empty($_POST['maib_delete_keys'])) {
        @unlink($payment_data['processor_params']['private_key']);
        @unlink($payment_data['processor_params']['public_key']);
        $payment_data['processor_params']['private_key'] = '';
        $payment_data['processor_params']['pkey_pass'] = '';
        $payment_data['processor_params']['public_key'] = '';
    } else {
        // handle PEM upload
        $files = ['public_key' => 'maib_public_key_file', 'private_key' => 'maib_private_key_file'];
        foreach ($files as $settings_k => $post_file) {
            if (isset($_FILES[$post_file]['error']) && $_FILES[$post_file]['error'] != UPLOAD_ERR_NO_FILE) {
                $file = ${$settings_k};
                if (
                    $_FILES[$post_file]['error'] == UPLOAD_ERR_OK
                    && is_uploaded_file($_FILES[$post_file]['tmp_name'])
                    && move_uploaded_file($_FILES[$post_file]['tmp_name'], $file)
                ) {
                    $payment_data['processor_params'][$settings_k] = $file;
                } else {
                    fn_set_notification('E', __('error'), 'Upload failed for ' . $settings_k . ', error '
                        . $_FILES[$post_file]['error']);
                }
            }
        }
        fn_maib_check_private_key($payment_data);
    }
}

/**
 * Extract PEM keys from PFX certificate
 * @param string $pfx_data
 * @param string $pfx_pass
 * @param boolean $pfx_force_pks1
 * @return array containing private and public keys
 */
function fn_maib_extract_certificates($pfx_data, $pfx_pass, $pfx_force_pks1 = false)
{
    $result = array();
    $pfx_certs = array();
    $error = null;

    if (openssl_pkcs12_read($pfx_data, $pfx_certs, $pfx_pass)) {
        if (isset($pfx_certs['pkey'])) {
            $pfx_key = null;
            $ssl_configargs = ['private_key_type' => OPENSSL_KEYTYPE_RSA];
            if ($pfx_force_pks1) {
                $ssl_configargs['encrypt_key_cipher'] = OPENSSL_CIPHER_3DES;
                $pfx_key = fn_maib_pkey_pkcs8_topkcs1($pfx_certs['pkey'], $pfx_pass);
            }
            if ($pfx_key || openssl_pkey_export($pfx_certs['pkey'], $pfx_key, $pfx_pass, $ssl_configargs)) {
                $result['key'] = $pfx_key;
                $result['pcert'] = $pfx_certs['cert'];
                $result['cacert'] = '';

                if (isset($pfx_certs['extracerts'])) {
                    foreach ($pfx_certs['extracerts'] as $extra_cert) {
                        $result['cacert'] .= $extra_cert;
                    }
                }
            }
        }
    } else {
        fn_set_notification('E', __('error'), 'Invalid PFX certificate or wrong passphrase');
    }

    return $result;
}

/**
 * Check if private key belongs to private key
 * @param array $payment_data Payment data
 * @return boolean
 */
function fn_maib_check_private_key($payment_data)
{
    if (function_exists('openssl_x509_check_private_key')) {
        $cert_data = file_get_contents($payment_data['processor_params']['public_key']);
        $key_data = [
            file_get_contents($payment_data['processor_params']['private_key']),
            $payment_data['processor_params']['pkey_pass']
        ];

        if (false === openssl_x509_check_private_key($cert_data, $key_data)) {
            fn_set_notification('E', __('error'), 'Private key does not correspond to client certificate');
            return false;
        }
        return true;
    }
}

/**
 * Get a list of payment methods created based on maib gateway
 * @return array payment id as key and company id as value
 */
function fn_maib_get_maib_payments()
{
    return db_get_hash_single_array("SELECT payment_id, company_id FROM ?:payments WHERE processor_id IN"
      . "(SELECT processor_id FROM ?:payment_processors WHERE processor_script IN ('maib.php'))", [
        'payment_id',
        'company_id'
    ]);
}

/**
 * Convert an encoded private rsa key from pkcs8 to pkcs1
 * @param string $pfx_key
 * @param string $pfx_pass
 * @return string new key or null
 */
function fn_maib_pkey_pkcs8_topkcs1($pfx_key, $pfx_pass)
{
    try {
        $rsa = new \phpseclib\Crypt\RSA();
        $rsa->setPassword($pfx_pass);
        $rsa->loadKey($pfx_key);
        $rsa->setPassword($pfx_pass); // reuse pass
        return $rsa->getPrivateKey();
    } catch (\Exception $e) {
        fn_set_notification('E', __('error'), 'Private key to PKCS#1 convertion failed');
    }
}

/**
 * Check if curl is compiled with nss (rhel)
 * @return boolean
 */
function fn_maib_is_curl_nss()
{
    $info = curl_version();
    return isset($info['ssl_version']) && preg_match('#NSS#', $info['ssl_version']);
}

/**
 * Hook get_order_info
 * @param array $order order info
 * @param type $additional_data
 */
function fn_maib_get_order_info(&$order, $additional_data)
{
    $maib_payments = fn_maib_get_maib_payments();
    $payment_id = isset($order['payment_method']['payment_id']) ? $order['payment_method']['payment_id'] : null;
    $payment_complete = in_array($order['status'], array('P', 'C'));
    $maib_transaction_id = isset($order['payment_info']['transaction_id']) ?
        $order['payment_info']['transaction_id'] : null;
    if ($payment_id && isset($maib_payments[$payment_id]) && $payment_complete && $maib_transaction_id) {
    }
}
