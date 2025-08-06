<?php

declare(strict_types=1);

namespace GeorgRinger\Invero\EventListener;

use GeorgRinger\Invero\Configuration;
use TYPO3\CMS\Backend\RecordList\Event\ModifyRecordListTableActionsEvent;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ModifyRecordListTableActionsEventListener
{

    public function __invoke(ModifyRecordListTableActionsEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $databaseRecordlist = $event->getRecordList();
        $columns = array_values($databaseRecordlist->getColumnsToRender($event->getTable(), false));
        if (count($columns) > 1) {
            // hack to remove title
            array_shift($columns);
        }
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $editActionConfiguration = [
            'idField' => 'uid',
            'tableName' => $event->getTable(),
            'url' => (string)$uriBuilder->buildUriFromRoute('record_edit_bulk'),
            'columns' => implode(',', $columns),
            'returnUrl' => $databaseRecordlist->listURL(),
        ];
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->loadJavaScriptModule('@georgringer/invero/BulkEdit.js');
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $size = (new Typo3Version())->getMajorVersion() === 12 ? Icon::SIZE_SMALL : IconSize::SMALL;
        $action = '<button
                        type="button"
                        title="bulkedit"
                        class="btn btn-sm btn-default"
                        data-multi-record-selection-action="editBulk"
                        data-multi-record-selection-action-config="' . GeneralUtility::jsonEncodeForHtmlAttribute(array_merge($editActionConfiguration, ['columnsOnly' => $columns, 'url' => (string)$uriBuilder->buildUriFromRoute('record_edit_bulk')])) . '"
                    >
                        ' . $iconFactory->getIcon('actions-document-open', $size)->render() . '
                        ' . htmlspecialchars('bulk me') . '
                    </button>';
        $event->setAction($action, 'bulk');

    }


    protected function isEnabled(): bool
    {
        $configuration = new Configuration();
        return $configuration->isBulkEditing();
    }

}
