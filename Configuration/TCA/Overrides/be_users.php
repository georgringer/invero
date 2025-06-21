<?php


$columns = [
    'tx_invero_tree_language' => [
        'label' => 'Preferred Language',
        'config' => [
            'type' => 'number',
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_users', $columns);
