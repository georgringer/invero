<?php

$extensionConfiguration = new \GeorgRinger\Invero\Configuration();

if ($extensionConfiguration->isL10nTree()) {
    if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() === 12) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository::class] = [
            'className' => \GeorgRinger\Invero\Xclass\XclassedPageTreeRepository12::class,
        ];
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository::class] = [
            'className' => \GeorgRinger\Invero\Xclass\XclassedPageTreeRepository::class,
        ];
    }
}

if ($extensionConfiguration->isRecordListSelection()) {
    if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() === 12) {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class] = [
            'className' => \GeorgRinger\Invero\Xclass\XclassedDatabaseRecordList12::class,
        ];
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\RecordList\DatabaseRecordList::class] = [
            'className' => \GeorgRinger\Invero\Xclass\XclassedDatabaseRecordList::class,
        ];
    }
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Template\Components\Menu\Menu::class] = [
        'className' => \GeorgRinger\Invero\Xclass\XclassedMenu::class
    ];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Controller\RecordListController::class] = [
        'className' => \GeorgRinger\Invero\Xclass\XclassedRecordListController::class
    ];
}

if ($extensionConfiguration->isPasswordMeter()) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1750713070] = [
        'nodeName' => 'password',
        'priority' => 40,
        'class' => \GeorgRinger\Invero\Backend\Form\Element\PasswordElement::class,
    ];
}
