<?php

return [
    /**
     * Main form rendering script
     * By sending certain parameters to this script you can bring up a form
     * which allows the user to edit the content of one or more database records.
     */
    'record_edit_bulk' => [
        'path' => '/record/editBulk',
        'target' => \GeorgRinger\Invero\Controller\BulkEditDocumentController::class . '::mainAction',
        'redirect' => [
            'enable' => true,
            'parameters' => [
                'edit' => true,
                'columnsOnly' => true,
            ],
        ],
    ],

];
