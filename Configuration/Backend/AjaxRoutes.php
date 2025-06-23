<?php

return [
    'invero_tree_language' => [
        'path' => '/invero/tree-language',
        'target' => \GeorgRinger\Invero\Controller\AjaxController::class . '::treeLanguageAction',
    ],
    'password_meter_verify' => [
        'path' => '/password-meter/verify',
        'target' => \GeorgRinger\Invero\Controller\PasswordMeterController::class . '::verifyAction',
    ],
];
