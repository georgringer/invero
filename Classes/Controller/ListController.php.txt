<?php

namespace GeorgRinger\Invero\Controller;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use TYPO3\CMS\Backend\Controller\RecordListController;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownHeader;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItem;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownItemInterface;
use TYPO3\CMS\Backend\Template\Components\Buttons\DropDown\DropDownRadio;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\DataHandling\PageDoktypeRegistry;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchema;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

#[AsController]
class ListController extends RecordListController {

    protected bool $newPagesSelectPosition = true;
    protected array $allowedNewTables = [];
    protected array $deniedNewTables = [];
    protected array $newRecordSortList = [];
    protected array $tRows = [];
    protected bool $newPagesInto = false;
    protected bool $newContentInto = false;
    protected bool $newPagesAfter = false;
    protected array $pidInfo = [];


    public function __construct(
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly UriBuilder $uriBuilder,
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly TcaSchemaFactory $tcaSchemaFactory,
        protected readonly FlashMessageService $flashMessageService,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleData = $request->getAttribute('moduleData');

        $languageService = $this->getLanguageService();
        $backendUser = $this->getBackendUserAuthentication();
        $beUser = $this->getBackendUserAuthentication();
        $parsedBody = $request->getParsedBody();
        $queryParams = $request->getQueryParams();

        $this->pageRenderer->addInlineLanguageLabelFile('EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf');
        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/element/dispatch-modal-button.js');

        BackendUtility::lockRecords();
        $perms_clause = $backendUser->getPagePermsClause(Permission::PAGE_SHOW);
        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $pointer = max(0, (int)($parsedBody['pointer'] ?? $queryParams['pointer'] ?? 0));
        $this->table = (string)($parsedBody['table'] ?? $queryParams['table'] ?? '');
        $this->searchTerm = trim((string)($parsedBody['searchTerm'] ?? $queryParams['searchTerm'] ?? ''));
        $this->returnUrl = GeneralUtility::sanitizeLocalUrl((string)($parsedBody['returnUrl'] ?? $queryParams['returnUrl'] ?? ''));
        $cmd = (string)($parsedBody['cmd'] ?? $queryParams['cmd'] ?? '');
        $siteLanguages = $request->getAttribute('site')->getAvailableLanguages($this->getBackendUserAuthentication(), false, $this->id);

        // Loading module configuration, clean up settings, current page and page access
        $this->modTSconfig = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_list.'] ?? [];
        $pageinfo = BackendUtility::readPageAccess($this->id, $perms_clause);
        $access = is_array($pageinfo);
        $this->pageInfo = is_array($pageinfo) ? $pageinfo : [];
        $this->pagePermissions = new Permission($backendUser->calcPerms($pageinfo));

        // Check if Clipboard is allowed to be shown:
        if (($this->modTSconfig['enableClipBoard'] ?? '') === 'activated') {
            $this->allowClipboard = false;
        } elseif (($this->modTSconfig['enableClipBoard'] ?? '') === 'selectable') {
            $this->allowClipboard = true;
        } elseif (($this->modTSconfig['enableClipBoard'] ?? '') === 'deactivated') {
            $this->allowClipboard = false;
        }

        if ($this->pageinfo['uid'] ?? false) {
            $this->pidInfo = BackendUtility::getRecord('pages', ($this->pageInfo['pid'] ?? 0)) ?? [];
            // Get record of parent page
//            $this->pidInfo = BackendUtility::getRecord('pages', ($this->pageinfo['pid'] ?? 0)) ?? [];
            // Checking the permissions for the user with regard to the parent page: Can he create new pages, new
            // content record, new page after?
            if ($beUser->doesUserHaveAccess($this->pageinfo, Permission::PAGE_NEW)) {
                $this->newPagesInto = true;
            }
            if ($beUser->doesUserHaveAccess($this->pageinfo, Permission::CONTENT_EDIT)) {
                $this->newContentInto = true;
            }
            if (($beUser->isAdmin() || !empty($this->pidInfo)) && $beUser->doesUserHaveAccess($this->pidInfo, Permission::PAGE_NEW)) {
                $this->newPagesAfter = true;
            }
        } elseif ($beUser->isAdmin()) {
            // Admins can do it all
            $this->newPagesInto = true;
            $this->newContentInto = true;
            $this->newPagesAfter = false;
        } else {
            // People with no permission can do nothing
            $this->newPagesInto = false;
            $this->newContentInto = false;
            $this->newPagesAfter = false;
        }

        // Check if SearchBox is allowed to be shown:
        $this->allowSearch = !($this->modTSconfig['disableSearchBox'] ?? false);

        // Overwrite to show search on search request
        if (!empty($this->searchTerm)) {
            $this->allowSearch = true;
            $this->moduleData->set('searchBox', true);
        }

        // Get search levels from request or fall back to default, set in TSconifg
        $search_levels = (int)($parsedBody['search_levels'] ?? $queryParams['search_levels'] ?? $this->modTSconfig['searchLevel.']['default'] ?? 0);

        $dbList = GeneralUtility::makeInstance(DatabaseRecordList::class);
        $dbList->setRequest($request);
        $dbList->setModuleData($this->moduleData);
        $dbList->calcPerms = $this->pagePermissions;
        $dbList->returnUrl = $this->returnUrl;
        $dbList->showClipboardActions = true;
        $dbList->disableSingleTableView = $this->modTSconfig['disableSingleTableView'] ?? false;
        $dbList->listOnlyInSingleTableMode = $this->modTSconfig['listOnlyInSingleTableView'] ?? false;
        $dbList->hideTables = $this->modTSconfig['hideTables'] ?? '';
        $dbList->hideTranslations = (string)($this->modTSconfig['hideTranslations'] ?? '');
        $dbList->tableTSconfigOverTCA = $this->modTSconfig['table.'] ?? [];
        $dbList->allowedNewTables = GeneralUtility::trimExplode(',', $this->modTSconfig['allowedNewTables'] ?? '', true);
        $dbList->deniedNewTables = GeneralUtility::trimExplode(',', $this->modTSconfig['deniedNewTables'] ?? '', true);
        $dbList->pageRow = $this->pageInfo;
        $dbList->modTSconfig = $this->modTSconfig;
        $dbList->setLanguagesAllowedForUser($siteLanguages);
        $clickTitleMode = trim($this->modTSconfig['clickTitleMode'] ?? '');
        $dbList->clickTitleMode = $clickTitleMode === '' ? 'edit' : $clickTitleMode;
        if (isset($this->modTSconfig['tableDisplayOrder.'])) {
            $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);
            $dbList->setTableDisplayOrder($typoScriptService->convertTypoScriptArrayToPlainArray($this->modTSconfig['tableDisplayOrder.']));
        }
        $clipboard = $this->initializeClipboard($request, (bool)$this->moduleData->get('clipBoard'));
        $dbList->clipObj = $clipboard;
        $additionalRecordListEvent = $this->eventDispatcher->dispatch(new RenderAdditionalContentToRecordListEvent($request));

        $view = $this->moduleTemplateFactory->create($request);

        $tableListHtml = '';
        if ($access || ($this->id === 0 && $search_levels !== 0 && $this->searchTerm !== '')) {
            // If there is access to the page or root page is used for searching, then perform actions and render table list.
            if ($cmd === 'delete' && $request->getMethod() === 'POST') {
                $this->deleteRecords($request, $clipboard);
            }
            $dbList->start($this->id, $this->table, $pointer, $this->searchTerm, $search_levels);
            $tableListHtml = $dbList->generateList();
        }

        if (!$this->id) {
            $title = $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'];
        } else {
            $title = $pageinfo['title'] ?? '';
        }
        $languageSelectorHtml = '';
        if ($this->id && !$this->searchTerm && !$cmd && !$this->table) {
            // Show the selector to add page translations, but only when in "default" mode.
            $languageSelectorHtml = $this->languageSelector($siteLanguages, $request->getAttribute('normalizedParams')->getRequestUri());
        }
        $pageTranslationsHtml = '';
        if ($this->id && !$this->searchTerm && !$cmd && !$this->table && $this->showPageTranslations()) {
            // Show page translation table if there are any and display is allowed.
            $pageTranslationsHtml = $this->renderPageTranslations($dbList, $siteLanguages);
        }
        $searchBoxHtml = '';
        if ($this->allowSearch && $this->moduleData->get('searchBox')) {
            $searchBoxHtml = $this->renderSearchBox($request, $dbList, $this->searchTerm, $search_levels);
        }
        $clipboardHtml = '';
        if ($this->allowClipboard && $this->moduleData->get('clipBoard') && ($tableListHtml || $clipboard->hasElements())) {
            $clipboardHtml = '<hr class="spacer"><typo3-backend-clipboard-panel return-url="' . htmlspecialchars($dbList->listURL()) . '"></typo3-backend-clipboard-panel>';
        }

        $view->setTitle($languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_mod_web_list.xlf:mlang_tabs_tab'), $title);
        if (empty($tableListHtml)) {
            $this->addNoRecordsFlashMessage($view, $this->table);
        }
        if ($pageinfo) {
            $view->getDocHeaderComponent()->setMetaInformation($pageinfo);
        }
        $this->getDocHeaderButtons($view, $clipboard, $request, $this->table, $dbList->listURL(), []);
        $view->assignMultiple([
            'pageId' => $this->id,
            'pageTitle' => $title,
            'isPageEditable' => $this->isPageEditable(),
            'additionalContentTop' => $additionalRecordListEvent->getAdditionalContentAbove(),
            'languageSelectorHtml' => $languageSelectorHtml,
            'pageTranslationsHtml' => $pageTranslationsHtml,
            'searchBoxHtml' => $searchBoxHtml,
            'tableListHtml' => $tableListHtml,
            'clipboardHtml' => $clipboardHtml,
            'additionalContentBottom' => $additionalRecordListEvent->getAdditionalContentBelow(),
        ]);
        return $view->renderResponse('RecordList');
    }

    protected function __getDocHeaderButtons(ModuleTemplate $view, Clipboard $clipboard, ServerRequestInterface $request, string $table, string $listUrl, array $moduleSettings): void
    {
        parent::getDocHeaderButtons($view, $clipboard, $request, $table, $listUrl, $moduleSettings);

        $buttonBar = $view->getDocHeaderComponent()->getButtonBar();

        $allowedTables = $this->getAllowedTables();
        $recordControls = $this->getNewRecordControls($allowedTables);

        foreach ($recordControls as $control) {
            $viewModeItems[] = GeneralUtility::makeInstance(DropDownHeader::class)
                ->setLabel($control['title']);

            foreach($control['items'] as $table => $item) {
//                DebuggerUtility::var_dump($item['icon']);
                $viewModeItems[] = GeneralUtility::makeInstance(DropDownItem::class)
//                    ->setActive($this->table === $tableName)
                    ->setLabel($item['label'])
                    ->setIcon($item['icon'] ?? null)
                    ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_invero', [
                        'id' => $this->id,
                        'table' => $table,
                    ]));
            }
        }

        $viewModeButton = $buttonBar->makeDropDownButton()
            ->setLabel('Selection')
            ->setShowLabelText(true);
        foreach ($viewModeItems as $viewModeItem) {
            $viewModeButton->addItem($viewModeItem);
        }

        $buttonBar->addButton($viewModeButton, ButtonBar::BUTTON_POSITION_LEFT, 3);
        return;
    }

    private function countOnTable(string $table)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUserAuthentication()->workspace));
        $queryBuilder
            ->select('*')
            ->from($table);
        $schema = $this->tcaSchemaFactory->get($table);



        $queryBuilder->andWhere(
            $queryBuilder->expr()->eq(
                $table . '.pid',
                $queryBuilder->createNamedParameter($this->id, Connection::PARAM_INT)
            )
        );


//        // Filtering on displayable pages (permissions):
//        if ($table === 'pages' && $this->perms_clause) {
//            $queryBuilder->andWhere($this->perms_clause);
//        }


        // Finding the total amount of records on the page
        $queryBuilderTotalItems = $queryBuilder;
        $totalItems = (int)$queryBuilderTotalItems
            ->count('*')
            ->resetOrderBy()
            ->executeQuery()
            ->fetchOne();
       return $totalItems;
    }


    /**
     * @return list<string>
     */
    protected function getAllowedTables(): array
    {
        $allowedTables = [];

        foreach ($this->tcaSchemaFactory->all() as $table => $schema) {
            $isTablesAllowed = match ($table) {
                'pages' => true,
                'tt_content' => true, // Skip, as inserting content elements is part of the page module
                default => $this->newContentInto && $this->isRecordCreationAllowedForTable($table) && $this->isTableAllowedOnPage($schema, $this->pageInfo)
            };

            if ($isTablesAllowed && $this->countOnTable($table)) {
                $allowedTables[] = $table;
            }
        }

        return $allowedTables;
    }

    protected function isRecordCreationAllowedForTable(string $table, array $allowedNewTables = [], array $deniedNewTables = []): bool
    {
        if (!$this->getBackendUserAuthentication()->check('tables_modify', $table)) {
            return false;
        }

        $schema = $this->tcaSchemaFactory->get($table);

        if ($schema->hasCapability(TcaSchemaCapability::AccessReadOnly)
            || $schema->hasCapability(TcaSchemaCapability::HideInUi)
            || ($schema->hasCapability(TcaSchemaCapability::AccessAdminOnly)  && !$this->getBackendUserAuthentication()->isAdmin())
        ) {
            return false;
        }

        $allowedNewTables = $allowedNewTables ?: $this->allowedNewTables;
        $deniedNewTables = $deniedNewTables ?: $this->deniedNewTables;
        // No deny/allow tables are set:
        if (empty($allowedNewTables) && empty($deniedNewTables)) {
            return true;
        }

        return !in_array($table, $deniedNewTables) && (empty($allowedNewTables) || in_array($table, $allowedNewTables));
    }

    protected function getNewRecordControls(array $allowedTables): array
    {
        $lang = $this->getLanguageService();
        // Get TSconfig for current page
        $pageTS = BackendUtility::getPagesTSconfig($this->id);
        // Finish initializing new pages options with TSconfig
        // Each new page option may be hidden by TSconfig
        // Enabled option for the position of a new page
        $this->newPagesSelectPosition = !empty($pageTS['mod.']['wizards.']['newRecord.']['pages.']['show.']['pageSelectPosition']);
        $displayNewPagesIntoLink = $this->newPagesInto && !empty($pageTS['mod.']['wizards.']['newRecord.']['pages.']['show.']['pageInside']);
        $displayNewPagesAfterLink = $this->newPagesAfter && !empty($pageTS['mod.']['wizards.']['newRecord.']['pages.']['show.']['pageAfter']);
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
        $groupedLinksOnTop = [];
        foreach ($allowedTables as $table) {
            $schema = $this->tcaSchemaFactory->get($table);
            $ctrlTitle = $schema->getTitle();

            if ($table === 'pages') {
                       $groupedLinksOnTop['pages'] = [
                        'title' => $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_misc.xlf:createNewPage'),
                        'icon' => $this->iconFactory->getIcon('actions-page-new', IconSize::SMALL),
                        'items' => [
                            'pages' => [
                                'label' => 'paaage',
                            ]
                        ],
                    ];

            } else {
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
                            $_EXTKEY = substr($_EXTKEY, 0, (int)strpos($_EXTKEY, '/'));
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
                $this->tRows[$groupName]['title'] = $this->tRows[$groupName]['title'] ?? $groupTitles[$groupName] ?? $nameParts[1] ?? $ctrlTitle;
                $this->tRows[$groupName]['icon'] = $this->tRows[$groupName]['icon'] ?? $iconFile[$groupName] ?? $iconFile['system'] ?? '';
                $this->tRows[$groupName]['items'][$table] = [
//                    'url' => $this->renderLink($table, $this->id),
                    'icon' => $this->iconFactory->getIconForRecord($table, [], IconSize::SMALL),
                    'label' => $lang->sL($ctrlTitle),
                ];
            }
        }
        // User sort
        if (isset($pageTS['mod.']['wizards.']['newRecord.']['order'])) {
            $this->newRecordSortList = GeneralUtility::trimExplode(',', $pageTS['mod.']['wizards.']['newRecord.']['order'], true);
        }
        uksort($this->tRows, $this->sortTableRows(...));
        $this->tRows = array_merge($groupedLinksOnTop, $this->tRows);

        return $this->tRows;
    }
    protected function sortTableRows(string $a, string $b): int
    {
        if (!empty($this->newRecordSortList)) {
            if (in_array($a, $this->newRecordSortList) && in_array($b, $this->newRecordSortList)) {
                // Both are in the list, return relative to position in array
                $sub = array_search($a, $this->newRecordSortList) - array_search($b, $this->newRecordSortList);
                $ret = ($sub < 0 ? -1 : $sub == 0) ? 0 : 1;
            } elseif (in_array($a, $this->newRecordSortList)) {
                // First element is in array, put to top
                $ret = -1;
            } elseif (in_array($b, $this->newRecordSortList)) {
                // Second element is in array, put first to bottom
                $ret = 1;
            } else {
                // No element is in array, return alphabetic order
                $ret = strnatcasecmp($this->tRows[$a]['title'] ?? '', $this->tRows[$b]['title'] ?? '');
            }
            return $ret;
        }
        // Return alphabetic order
        return strnatcasecmp($this->tRows[$a]['title'] ?? '', $this->tRows[$b]['title'] ?? '');
    }

    protected function isTableAllowedOnPage(TcaSchema $schema, array $page): bool
    {
        $rootLevelCapability = $schema->getCapability(TcaSchemaCapability::RestrictionRootLevel);

        $rootLevelConstraintMatches = ($rootLevelCapability->canExistOnRootLevel() && $this->id === 0) || ($this->id && $rootLevelCapability->canExistOnPages());
        if (empty($page)) {
            return $rootLevelConstraintMatches && $this->getBackendUserAuthentication()->isAdmin();
        }
        if (!$this->getBackendUserAuthentication()->workspaceCanCreateNewRecord($schema->getName())) {
            return false;
        }
        // Checking doktype
        $isAllowed = GeneralUtility::makeInstance(PageDoktypeRegistry::class)->isRecordTypeAllowedForDoktype($schema->getName(), $page['doktype']);
        return $rootLevelConstraintMatches && $isAllowed;
    }

}
