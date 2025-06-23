<?php

namespace GeorgRinger\Invero\Xclass;

use GeorgRinger\Invero\Backend\RecordGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Clipboard\Clipboard;
use TYPO3\CMS\Backend\Controller\Event\RenderAdditionalContentToRecordListEvent;
use TYPO3\CMS\Backend\Controller\RecordListController;
use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XclassedRecordListController extends RecordListController
{

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleData = $request->getAttribute('moduleData');

        $languageService = $this->getLanguageService();
        $backendUser = $this->getBackendUserAuthentication();
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
        $this->getDocHeaderButtons($view, $clipboard, $request, $this->table, $dbList->listURL(), [], $pageinfo ? $dbList->getTablesOnPage() : []);
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

    protected function getDocHeaderButtons(ModuleTemplate $view, Clipboard $clipboard, ServerRequestInterface $request, string $table, string $listUrl, array $moduleSettings, array $tablesOnPage = []): void
    {
        parent::getDocHeaderButtons($view, $clipboard, $request, $table, $listUrl, $moduleSettings);

        if ($tablesOnPage) {
            $recordGroup = GeneralUtility::makeInstance(RecordGroup::class);
            $recordGroups = $recordGroup->generate($tablesOnPage);
            $menu = $view->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
            $menu->setIdentifier('RecordListTable');
            $menu->setLabel('Tables');

            $menuItem = $menu->makeMenuItem()
                ->setTitle('All tables')
                ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_list', [
                    'id' => $this->id,
                ]));
            $menu->addMenuItem($menuItem);

            foreach ($recordGroups as $control) {
                $menuOptgroupItem = $menu->makeMenuOptgroupItem()
                    ->setTitle($control['title']);

                foreach ($control['items'] as $table => $item) {
                    $menuItem = $menu->makeMenuItem()
                        ->setTitle($item['label'])
                        ->setHref((string)$this->uriBuilder->buildUriFromRoute('web_list', [
                            'id' => $this->id,
                            'table' => $table,
                        ]))
                        ->setActive($table === $this->table);
                    $menuOptgroupItem->addMenuItem($menuItem);
                }
                $menu->addMenuOptgroupItem($menuOptgroupItem);
                $view->getDocHeaderComponent()->getMenuRegistry()->addMenu($menu);
            }
        }

    }


}
