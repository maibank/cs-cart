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

if (!defined('AREA')) {
    die('Access denied');
}

fn_register_hooks(
    'save_log',
    'update_payment_pre',
    'change_order_status'
);
