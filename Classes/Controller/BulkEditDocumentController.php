<?php

declare(strict_types=1);

namespace GeorgRinger\Invero\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Controller\EditDocumentController;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextFactory;
use TYPO3Fluid\Fluid\View\TemplateView;

#[AsController]
class BulkEditDocumentController extends EditDocumentController
{

    private bool $isBulk = false;
    private array $bulkEdits = [];

    protected function main(ModuleTemplate $view, ServerRequestInterface $request): string
    {
        $html = parent::main($view, $request);
        $debug = 'All fields are copied to following records<br>';
        foreach ($this->bulkEdits as $table => $conf) {
            foreach ($conf as $k => $val) {
                $debug .= sprintf('%s from %s to %s<br>', $table, $k, $val);
            }
        }
        $context = GeneralUtility::makeInstance(RenderingContextFactory::class)->create();
        $context->getTemplatePaths()->setTemplateSource('<f:be.infobox state="1" title="Bulk Editing">' . $debug . '</f:be.infobox>');
        $debug = (new TemplateView($context))->render();
        return $debug . $html;
    }

    protected function preInit(ServerRequestInterface $request): ?ResponseInterface
    {
        $preInitResult = parent::preInit($request);
        foreach ($this->editconf as $table => $conf) {
            foreach ($conf as $cKey => $cmd) {
                if ($cmd === 'edit') {
                    // Traverse the ids:
                    $ids = GeneralUtility::trimExplode(',', (string)$cKey, true);
                    if (count($ids) > 1) {
                        $this->isBulk = true;
                        $firstId = array_shift($ids);
                        $this->bulkEdits[$table][$firstId] = implode(',', $ids);

                        unset($this->editconf[$table][(string)$cKey]);
                        $this->editconf[$table][$firstId] = $cmd;
                    }
                }
            }
        }
        return $preInitResult;
    }


    /**
     * Do processing of data, submitting it to DataHandler. May return a RedirectResponse.
     */
    protected function processData(ModuleTemplate $view, ServerRequestInterface $request): ?ResponseInterface
    {
        $parsedBody = $request->getParsedBody();

        $beUser = $this->getBackendUser();

        // Processing related GET / POST vars
        $this->data = $parsedBody['data'] ?? [];
        $this->cmd = $parsedBody['cmd'] ?? [];
        $this->mirror = $parsedBody['mirror'] ?? [];
        $this->returnNewPageId = (bool)($parsedBody['returnNewPageId'] ?? false);

        // Only options related to $this->data submission are included here
        $tce = GeneralUtility::makeInstance(DataHandler::class);

        $tce->setControl($parsedBody['control'] ?? []);

        // Set internal vars
        if (isset($beUser->uc['neverHideAtCopy']) && $beUser->uc['neverHideAtCopy']) {
            $tce->neverHideAtCopy = true;
        }

        // Set default values fetched previously from GET / POST vars
        if (is_array($this->defVals) && $this->defVals !== []) {
            $tce->defaultValues = array_merge_recursive($this->defVals, $tce->defaultValues);
        }

        // Load DataHandler with data
        $tce->start($this->data, $this->cmd);
        $this->mirror = $this->bulkEdits;
        if (is_array($this->mirror)) {
            $tce->setMirror($this->mirror);
        }

//        DebuggerUtility::var_dump($tce->datamap, 'tce-datamap');die;

        // Perform the saving operation with DataHandler:
        if ($this->doSave === true) {
            $tce->process_datamap();
            $tce->process_cmdmap();

            // Update the module menu for the current backend user, as they updated their UI language
            $currentUserId = $beUser->getUserId();
            if ($currentUserId
                && (string)($this->data['be_users'][$currentUserId]['lang'] ?? '') !== ''
                && $this->data['be_users'][$currentUserId]['lang'] !== $beUser->user['lang']
            ) {
                $newLanguageKey = $this->data['be_users'][$currentUserId]['lang'];
                // Update the current backend user language as well
                $beUser->user['lang'] = $newLanguageKey;
                // Re-create LANG to have the current request updated the translated page as well
                $this->getLanguageService()->init($newLanguageKey);
                BackendUtility::setUpdateSignal('updateModuleMenu');
                BackendUtility::setUpdateSignal('updateTopbar');
            }
        }
        // If pages are being edited, we set an instruction about updating the page tree after this operation.
        if ($tce->pagetreeNeedsRefresh
            && (isset($this->data['pages']) || $beUser->workspace !== 0 && !empty($this->data))
        ) {
            BackendUtility::setUpdateSignal('updatePageTree');
        }
        // If there was saved any new items, load them:
        if (!empty($tce->substNEWwithIDs_table)) {
            // Save the expanded/collapsed states for new inline records, if any
            $this->updateInlineView($request->getParsedBody()['uc'] ?? $request->getQueryParams()['uc'] ?? null, $tce);
            $newEditConf = [];
            foreach ($this->editconf as $tableName => $tableCmds) {
                $keys = array_keys($tce->substNEWwithIDs_table, $tableName);
                if (!empty($keys)) {
                    foreach ($keys as $key) {
                        $editId = $tce->substNEWwithIDs[$key];
                        // Check if the $editId isn't a child record of an IRRE action
                        if (!(is_array($tce->newRelatedIDs[$tableName] ?? null)
                            && in_array($editId, $tce->newRelatedIDs[$tableName]))
                        ) {
                            // Translate new id to the workspace version
                            if ($versionRec = BackendUtility::getWorkspaceVersionOfRecord(
                                $beUser->workspace,
                                $tableName,
                                $editId,
                                'uid'
                            )) {
                                $editId = $versionRec['uid'];
                            }
                            $newEditConf[$tableName][$editId] = 'edit';
                        }
                        // Traverse all new records and forge the content of ->editconf so we can continue to edit these records!
                        if ($tableName === 'pages'
                            && $this->retUrl !== (string)$this->uriBuilder->buildUriFromRoute('dummy')
                            && $this->retUrl !== $this->getCloseUrl()
                            && $this->returnNewPageId
                        ) {
                            $this->retUrl .= '&id=' . $tce->substNEWwithIDs[$key];
                        }
                    }
                } else {
                    $newEditConf[$tableName] = $tableCmds;
                }
            }
            // Reset editconf if newEditConf has values
            if (!empty($newEditConf)) {
                $this->editconf = $newEditConf;
            }
            // Finally, set the editconf array in the "getvars" so they will be passed along in URLs as needed.
            $this->R_URL_getvars['edit'] = $this->editconf;
            // Unset default values since we don't need them anymore.
            unset($this->R_URL_getvars['defVals']);
            // Recompile the store* values since editconf changed
            $this->compileStoreData($request);
        }
        // See if any records was auto-created as new versions?
        if (!empty($tce->autoVersionIdMap)) {
            $this->fixWSversioningInEditConf($tce->autoVersionIdMap);
        }
//        // If a document is saved and a new one is created right after.
//        if (isset($parsedBody['_savedoknew']) && is_array($this->editconf)) {
//            if ($redirect = $this->closeDocument(self::DOCUMENT_CLOSE_MODE_NO_REDIRECT, $request)) {
//                return $redirect;
//            }
//            // Find the current table
//            reset($this->editconf);
//            $nTable = (string)key($this->editconf);
//            // Determine insertion mode: 'top' is self-explaining,
//            // otherwise new elements are inserted after one using a negative uid
//            $insertRecordOnTop = ($this->getTsConfigOption($nTable, 'saveDocNew') === 'top');
//            // Fetching id's - might be a comma-separated list
//            reset($this->editconf[$nTable]);
//            $ids = GeneralUtility::trimExplode(',', (string)key($this->editconf[$nTable]), true);
//            // Depending on $insertRecordOnTop, retrieve either the first or last id to get the records' pid+uid
//            if ($insertRecordOnTop) {
//                $nUid = (int)reset($ids);
//            } else {
//                $nUid = (int)end($ids);
//            }
//            $recordFields = 'pid,uid';
//            if (BackendUtility::isTableWorkspaceEnabled($nTable)) {
//                $recordFields .= ',t3ver_oid';
//            }
//            $nRec = BackendUtility::getRecord($nTable, $nUid, $recordFields);
//            // Setting a blank editconf array for a new record:
//            $this->editconf = [];
//            // Determine related page ID for regular live context
//            if ((int)($nRec['t3ver_oid'] ?? 0) === 0) {
//                if ($insertRecordOnTop) {
//                    $relatedPageId = $nRec['pid'];
//                } else {
//                    $relatedPageId = -$nRec['uid'];
//                }
//            } else {
//                // Determine related page ID for workspace context
//                if ($insertRecordOnTop) {
//                    // Fetch live version of workspace version since the pid value is always -1 in workspaces
//                    $liveRecord = BackendUtility::getRecord($nTable, $nRec['t3ver_oid'], $recordFields);
//                    $relatedPageId = $liveRecord['pid'];
//                } else {
//                    // Use uid of live version of workspace version
//                    $relatedPageId = -$nRec['t3ver_oid'];
//                }
//            }
//            $this->editconf[$nTable][$relatedPageId] = 'new';
//            // Finally, set the editconf array in the "getvars" so they will be passed along in URLs as needed.
//            $this->R_URL_getvars['edit'] = $this->editconf;
//            // Recompile the store* values since editconf changed...
//            $this->compileStoreData($request);
//        }

        // Explicitly require a save operation
        if ($this->doSave) {
            $erroneousRecords = $tce->printLogErrorMessages();
            $messages = [];
            $table = (string)key($this->editconf);
            $uidList = GeneralUtility::intExplode(',', (string)key($this->editconf[$table]));

            foreach ($uidList as $uid) {
                $uid = (int)abs($uid);
                if (!in_array($table . '.' . $uid, $erroneousRecords, true)) {
                    $realUidInPayload = ($tceSubstId = array_search($uid, $tce->substNEWwithIDs, true)) !== false ? $tceSubstId : $uid;
                    $row = $this->data[$table][$uid] ?? $this->data[$table][$realUidInPayload] ?? null;
                    if ($row === null) {
                        continue;
                    }
                    // Ensure, uid is always available to make labels with foreign table lookups possible
                    $row['uid'] ??= $realUidInPayload;
                    // If the label column of the record is not available, fetch it from database.
                    // This is the when EditDocumentController is booted in single field mode (e.g.
                    // Template module > 'info/modify' > edit 'setup' field) or in case the field is
                    // not in "showitem" or is set to readonly (e.g. "file" in sys_file_metadata).
                    $labelArray = [$GLOBALS['TCA'][$table]['ctrl']['label'] ?? null];
                    $labelAltArray = GeneralUtility::trimExplode(',', $GLOBALS['TCA'][$table]['ctrl']['label_alt'] ?? '', true);
                    $labelFields = array_unique(array_filter(array_merge($labelArray, $labelAltArray)));
                    foreach ($labelFields as $labelField) {
                        if (!isset($row[$labelField])) {
                            $tmpRecord = BackendUtility::getRecord($table, $uid, implode(',', $labelFields));
                            if ($tmpRecord !== null) {
                                $row = array_merge($row, $tmpRecord);
                            }
                            break;
                        }
                    }
                    $recordTitle = GeneralUtility::fixed_lgd_cs(BackendUtility::getRecordTitle($table, $row), (int)$this->getBackendUser()->uc['titleLen']);
                    $messages[] = sprintf($this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:notification.record_saved.message'), $recordTitle);
                }
            }

            // Add messages to the flash message container only if the request is a save action (excludes "duplicate")
            if ($messages !== []) {
                $label = $this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:notification.record_saved.title.plural');
                if (count($messages) === 1) {
                    $label = $this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:notification.record_saved.title.singular');
                }
                if (count($messages) > 10) {
                    $messages = [sprintf($this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_alt_doc.xlf:notification.mass_saving.message'), count($messages))];
                }
                $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
                $defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier(FlashMessageQueue::NOTIFICATION_QUEUE);
                $flashMessage = GeneralUtility::makeInstance(
                    FlashMessage::class,
                    implode(LF, $messages),
                    $label,
                    ContextualFeedbackSeverity::OK,
                    true
                );
                $defaultFlashMessageQueue->enqueue($flashMessage);
            }
        }

//        // If a document should be duplicated.
//        if (isset($parsedBody['_duplicatedoc']) && is_array($this->editconf)) {
//            $this->closeDocument(self::DOCUMENT_CLOSE_MODE_NO_REDIRECT, $request);
//            // Find current table
//            reset($this->editconf);
//            $nTable = (string)key($this->editconf);
//            // Find the first id, getting the records pid+uid
//            reset($this->editconf[$nTable]);
//            $nUid = key($this->editconf[$nTable]);
//            if (!MathUtility::canBeInterpretedAsInteger($nUid)) {
//                $nUid = $tce->substNEWwithIDs[$nUid];
//            }
//
//            $recordFields = 'pid,uid';
//            if (BackendUtility::isTableWorkspaceEnabled($nTable)) {
//                $recordFields .= ',t3ver_oid';
//            }
//            $nRec = BackendUtility::getRecord($nTable, $nUid, $recordFields);
//
//            // Setting a blank editconf array for a new record:
//            $this->editconf = [];
//
//            if ((int)($nRec['t3ver_oid'] ?? 0) === 0) {
//                $relatedPageId = -$nRec['uid'];
//            } else {
//                $relatedPageId = -$nRec['t3ver_oid'];
//            }
//
//            $duplicateTce = GeneralUtility::makeInstance(DataHandler::class);
//
//            $duplicateCmd = [
//                $nTable => [
//                    $nUid => [
//                        'copy' => $relatedPageId,
//                    ],
//                ],
//            ];
//
//            $duplicateTce->start([], $duplicateCmd);
//            $duplicateTce->process_cmdmap();
//
//            $duplicateMappingArray = $duplicateTce->copyMappingArray;
//            $duplicateUid = $duplicateMappingArray[$nTable][$nUid];
//
//            if ($nTable === 'pages') {
//                BackendUtility::setUpdateSignal('updatePageTree');
//            }
//
//            $this->editconf[$nTable][$duplicateUid] = 'edit';
//            // Finally, set the editconf array in the "getvars" so they will be passed along in URLs as needed.
//            $this->R_URL_getvars['edit'] = $this->editconf;
//            // Recompile the store* values since editconf changed...
//            $this->compileStoreData($request);
//
//            // Inform the user of the duplication
//            $view->addFlashMessage($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.recordDuplicated'));
//        }

        if ($this->closeDoc < self::DOCUMENT_CLOSE_MODE_DEFAULT
            || isset($parsedBody['_saveandclosedok'])
        ) {
            // Redirect if element should be closed after save
            return $this->closeDocument((int)abs($this->closeDoc), $request);
        }
        return null;
    }
}
