<?php

if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() === 12) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository::class] = [
        'className' => \GeorgRinger\Invero\Xclass\XclassedPageTreeRepository12::class,
    ];
} else {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository::class] = [
        'className' => \GeorgRinger\Invero\Xclass\XclassedPageTreeRepository::class,
    ];
}
