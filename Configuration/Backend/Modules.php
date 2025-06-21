<?php

return [

    'web_invero' => [
        'parent' => 'web',
        'position' => ['after' => 'web_list'],
        'access' => 'user',
        'path' => '/module/web/invero',
        'iconIdentifier' => 'module-list',
        'labels' => 'LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf',
        'routes' => [
            '_default' => [
                'target' => \GeorgRinger\Invero\Controller\ListController::class . '::mainAction',
            ],
        ],
        'moduleData' => [
            'clipBoard' => true,
            'searchBox' => false,
            'collapsedTables' => [],
        ],
    ],
];
