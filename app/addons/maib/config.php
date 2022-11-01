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

fn_define('MAIB_PLUGIN', 'maib');
fn_define('MAIB_TRANS_ID', 'trans_id');
fn_define('MAIB_TRANSACTION_ID', 'TRANSACTION_ID');
fn_define('MAIB_RESULT', 'RESULT');
fn_define('MAIB_RESULT_OK', 'OK'); //successfully completed transaction
fn_define('MAIB_RESULT_FAILED', 'FAILED'); //transaction has failed
fn_define('MAIB_RESULT_CREATED', 'CREATED'); //transaction just registered in the system
fn_define('MAIB_RESULT_PENDING', 'PENDING'); //transaction is not accomplished yet
fn_define('MAIB_RESULT_DECLINED', 'DECLINED'); //transaction declined by ECOMM, because ECI is in blocked ECI list
fn_define('MAIB_RESULT_REVERSED', 'REVERSED'); //transaction is reversed
fn_define('MAIB_RESULT_AUTOREVERSED', 'AUTOREVERSED'); //transaction is reversed by autoreversal
fn_define('MAIB_RESULT_TIMEOUT', 'TIMEOUT'); //transaction was timed out
fn_define('MAIB_RESULT_CODE', 'RESULT_CODE');
fn_define('MAIB_CURRENCY_MDL', 498);
fn_define('MAIB_CURRENCY_EUR', 978);
