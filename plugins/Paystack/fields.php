<?php
/**
 * Copyright (c) Since 2024 InnoShop - All Rights Reserved
 *
 * @link       https://www.innoshop.com
 * @author     InnoShop <team@innoshop.com>
 * @license    https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

return [
    [
        'name'      => 'public_key',
        'label_key' => 'common.public_key',
        'type'      => 'string',
        'required'  => true,
        'rules'     => 'required|min:32',
    ],
    [
        'name'      => 'secret_key',
        'label_key' => 'common.secret_key',
        'type'      => 'string',
        'required'  => true,
        'rules'     => 'required|min:32',
    ],
    [
        'name'      => 'webhook_secret',
        'label_key' => 'common.webhook_secret',
        'type'      => 'string',
        'required'  => false,
    ],
    [
        'name'      => 'test_mode',
        'label_key' => 'common.test_mode',
        'type'      => 'select',
        'options'   => [
            ['value' => '1', 'label_key' => 'common.enabled'],
            ['value' => '0', 'label_key' => 'common.disabled'],
        ],
        'required'    => true,
        'emptyOption' => false,
    ],
];
