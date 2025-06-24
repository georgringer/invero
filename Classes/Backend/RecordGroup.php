<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace GeorgRinger\Invero\Backend;

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

class RecordGroup
{
    public function __construct(
        protected readonly IconFactory $iconFactory
    ) {}

    public function generate(array $tableList)
    {
        $rows = [];
        $lang = $this->getLanguageService();

        if ((new Typo3Version())->getMajorVersion() === 12) {
            $iconFile = [
                'backendaccess' => $this->iconFactory->getIcon('status-user-group-backend', Icon::SIZE_SMALL)->render(),
                'content' => $this->iconFactory->getIcon('content-panel', Icon::SIZE_SMALL)->render(),
                'frontendaccess' => $this->iconFactory->getIcon('status-user-group-frontend', Icon::SIZE_SMALL)->render(),
                'system' => $this->iconFactory->getIcon('apps-pagetree-root', Icon::SIZE_SMALL)->render(),
            ];
            $groupTitles = [
                'backendaccess' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:recordgroup.backendaccess'),
                'content' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:recordgroup.content'),
                'frontendaccess' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:recordgroup.frontendaccess'),
                'system' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:system_records'),
            ];
            $groupedLinksOnTop = [];
            foreach ($tableList as $table => $v) {

                $nameParts = explode('_', $table);
                $groupName = $v['ctrl']['groupName'] ?? null;
                $title = (string) ($v['ctrl']['title'] ?? '');
                if (!isset($iconFile[$groupName]) || $nameParts[0] === 'tx' || $nameParts[0] === 'tt') {
                    $groupName = $groupName ?? $nameParts[1] ?? null;
                    // Try to extract extension name
                    if ($groupName) {
                        $_EXTKEY = '';
                        $titleIsTranslatableLabel = str_starts_with($title, 'LLL:EXT:');
                        if ($titleIsTranslatableLabel) {
                            // In case the title is a locallang reference, we can simply
                            // extract the extension name from the given extension path.
                            $_EXTKEY = substr($title, 8);
                            $_EXTKEY = substr($_EXTKEY, 0, (int) strpos($_EXTKEY, '/'));
                        } elseif (ExtensionManagementUtility::isLoaded($groupName)) {
                            // In case $title is not a locallang reference, we check the groupName to
                            // be a valid extension key. This most probably work since by convention the
                            // first part after tx_ / tt_ is the extension key.
                            $_EXTKEY = $groupName;
                        }
                        // Fetch the group title from the extension name
                        if ($_EXTKEY !== '') {
                            // Try to get the extension title
                            $package = GeneralUtility::makeInstance(PackageManager::class)->getPackage($_EXTKEY);
                            $groupTitle = $lang->sL('LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_db.xlf:extension.title');
                            // If no localisation available, read title from the Package MetaData
                            if (!$groupTitle) {
                                $groupTitle = $package->getPackageMetaData()->getTitle();
                            }
                            $extensionIcon = ExtensionManagementUtility::getExtensionIcon($package->getPackagePath());
                            if (!empty($extensionIcon)) {
                                $iconFile[$groupName] = '<img src="' . PathUtility::getAbsoluteWebPath(ExtensionManagementUtility::getExtensionIcon(
                                        $package->getPackagePath(),
                                        true
                                    )) . '" width="16" height="16" alt="' . $groupTitle . '" />';
                            }
                            if (!empty($groupTitle)) {
                                $groupTitles[$groupName] = $groupTitle;
                            } else {
                                $groupTitles[$groupName] = ucwords($_EXTKEY);
                            }
                        }
                    } else {
                        // Fall back to "system" in case no $groupName could be found
                        $groupName = 'system';
                    }
                }
                $rows[$groupName]['title'] = $rows[$groupName]['title'] ?? $groupTitles[$groupName] ?? $nameParts[1] ?? $title;
                $rows[$groupName]['icon'] = $rows[$groupName]['icon'] ?? $iconFile[$groupName] ?? $iconFile['system'] ?? '';
            }

        } else {
            $schemaFactory = GeneralUtility::makeInstance(TcaSchemaFactory::class);
            $iconFile = [
                'backendaccess' => $this->iconFactory->getIcon('status-user-group-backend', IconSize::SMALL)->render(),
                'content' => $this->iconFactory->getIcon('content-panel', IconSize::SMALL)->render(),
                'frontendaccess' => $this->iconFactory->getIcon('status-user-group-frontend', IconSize::SMALL)->render(),
                'system' => $this->iconFactory->getIcon('apps-pagetree-root', IconSize::SMALL)->render(),
            ];
            $groupTitles = [
                'backendaccess' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:recordgroup.backendaccess'),
                'content' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:recordgroup.content'),
                'frontendaccess' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:recordgroup.frontendaccess'),
                'system' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:system_records'),
            ];

            foreach ($tableList as $table) {
                $schema = $schemaFactory->get($table);
                $ctrlTitle = $schema->getTitle();

                $nameParts = explode('_', $table);
                $groupName = $schema->getRawConfiguration()['groupName'] ?? null;
                if (!isset($iconFile[$groupName]) || $nameParts[0] === 'tx' || $nameParts[0] === 'tt') {
                    $groupName = $groupName ?? $nameParts[1] ?? null;
                    // Try to extract extension name
                    if ($groupName) {
                        $_EXTKEY = '';
                        $titleIsTranslatableLabel = str_starts_with($ctrlTitle, 'LLL:EXT:');
                        if ($titleIsTranslatableLabel) {
                            // In case the title is a locallang reference, we can simply
                            // extract the extension name from the given extension path.
                            $_EXTKEY = substr($ctrlTitle, 8);
                            $_EXTKEY = substr($_EXTKEY, 0, (int) strpos($_EXTKEY, '/'));
                        } elseif (ExtensionManagementUtility::isLoaded($groupName)) {
                            // In case $title is not a locallang reference, we check the groupName to
                            // be a valid extension key. This most probably work since by convention the
                            // first part after tx_ / tt_ is the extension key.
                            $_EXTKEY = $groupName;
                        }
                        // Fetch the group title from the extension name
                        if ($_EXTKEY !== '') {
                            // Try to get the extension title
                            $package = GeneralUtility::makeInstance(PackageManager::class)->getPackage($_EXTKEY);
                            $groupTitle = $lang->sL('LLL:EXT:' . $_EXTKEY . '/Resources/Private/Language/locallang_db.xlf:extension.title');
                            // If no localisation available, read title from the Package MetaData
                            if (!$groupTitle) {
                                $groupTitle = $package->getPackageMetaData()->getTitle();
                            }
                            $extensionIcon = $package->getPackageIcon();
                            if (!empty($extensionIcon)) {
                                $iconFile[$groupName] = '<img src="' . PathUtility::getAbsoluteWebPath($package->getPackagePath() . $extensionIcon) . '" width="16" height="16" alt="' . $groupTitle . '" />';
                            }
                            if (!empty($groupTitle)) {
                                $groupTitles[$groupName] = $groupTitle;
                            } else {
                                $groupTitles[$groupName] = ucwords($_EXTKEY);
                            }
                        }
                    } else {
                        // Fall back to "system" in case no $groupName could be found
                        $groupName = 'system';
                    }
                }
                $rows[$groupName]['title'] = $rows[$groupName]['title'] ?? $groupTitles[$groupName] ?? $nameParts[1] ?? $ctrlTitle;
                $rows[$groupName]['icon'] = $rows[$groupName]['icon'] ?? $iconFile[$groupName] ?? $iconFile['system'] ?? '';
                $rows[$groupName]['items'][$table] = [
                    'icon' => $this->iconFactory->getIconForRecord($table, [], IconSize::SMALL)->render(),
                    'label' => $lang->sL($ctrlTitle),
                ];
            }
        }

        return $rows;
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

}
