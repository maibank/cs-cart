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

defined('BOOTSTRAP') or die('Access denied');

$schema['maib_order'] = [
    'modes' => [
        'reverse' => [
            'permissions' => 'edit_order'
        ],
    ],
];

return $schema;
