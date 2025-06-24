<?php

namespace GeorgRinger\Invero\Xclass;

use TYPO3\CMS\Backend\RecordList\DatabaseRecordList;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Service\DependencyOrderingService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XclassedDatabaseRecordList12 extends DatabaseRecordList
{

    protected array $tablesOnPage = [];

    public function getTablesOnPage(): array
    {
        $list = [];
        foreach ($this->getTablesToRender(false) as $table) {
            if ($this->getCountOfTable($table)) {
                $list[] = $table;
            }
        }
        return $list;
    }

    public function getCountOfTable(string $table): int
    {
        $queryBuilderTotalItems = $this->getQueryBuilder($table, ['*'], false, 0, 1);
        $queryBuilderTotalItems->resetQueryPart('orderBy');
        $totalItems = (int)$queryBuilderTotalItems
            ->count('*')
            ->executeQuery()
            ->fetchOne();
        return $totalItems;
    }


    protected function getTablesToRender(bool $respectSingleTable = true): array
    {
        $hideTablesArray = GeneralUtility::trimExplode(',', $this->hideTables);
        $backendUser = $this->getBackendUserAuthentication();

        // pre-process tables and add sorting instructions
        $tableNames = array_flip(array_keys($GLOBALS['TCA']));
        foreach ($tableNames as $tableName => $_) {
            $hideTable = false;

            // Checking if the table should be rendered:
            // Checks that we see only permitted/requested tables:
            if (($respectSingleTable && $this->table && $tableName !== $this->table)
                || ($this->tableList && !GeneralUtility::inList($this->tableList, (string)$tableName))
                || !$backendUser->check('tables_select', $tableName)
            ) {
                $hideTable = true;
            }

            if (!$hideTable) {
                // Don't show table if hidden by TCA ctrl section
                // Don't show table if hidden by page TSconfig mod.web_list.hideTables
                $hideTable = !empty($GLOBALS['TCA'][$tableName]['ctrl']['hideTable'])
                    || in_array($tableName, $hideTablesArray, true)
                    || in_array('*', $hideTablesArray, true);
                // Override previous selection if table is enabled or hidden by TSconfig TCA override mod.web_list.table
                $hideTable = (bool)($this->tableTSconfigOverTCA[$tableName . '.']['hideTable'] ?? $hideTable);
            }
            if ($hideTable) {
                unset($tableNames[$tableName]);
            } else {
                if (isset($this->tableDisplayOrder[$tableName])) {
                    // Copy display order information
                    $tableNames[$tableName] = $this->tableDisplayOrder[$tableName];
                } else {
                    $tableNames[$tableName] = [];
                }
            }
        }
        try {
            $orderedTableNames = GeneralUtility::makeInstance(DependencyOrderingService::class)
                ->orderByDependencies($tableNames);
        } catch (\UnexpectedValueException $e) {
            // If you have circular dependencies we just keep the original order and give a notice
            // Example mod.web_list.tableDisplayOrder.pages.after = tt_content
            $lang = $this->getLanguageService();
            $header = $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.tableDisplayOrder.title');
            $msg = $lang->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:warning.tableDisplayOrder.message');
            $flashMessage = GeneralUtility::makeInstance(FlashMessage::class, $msg, $header, ContextualFeedbackSeverity::WARNING, true);
            $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
            $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
            $defaultFlashMessageQueue->enqueue($flashMessage);
            $orderedTableNames = $tableNames;
        }
        return array_keys($orderedTableNames);
    }

}
