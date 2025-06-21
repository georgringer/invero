<?php

namespace GeorgRinger\Invero\Xclass;

use TYPO3\CMS\Backend\Tree\Repository\PageTreeRepository;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\PlainDataResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Versioning\VersionState;

class XclassedPageTreeRepository12 extends PageTreeRepository
{

    /**
     * Retrieve the page tree based on the given search filter
     */
    public function fetchFilteredTree(string $searchFilter, array $allowedMountPointPageIds, string $additionalWhereClause): array
    {
        $preferredLanguageId = $this->getPreferredLanguageId();
        if ($preferredLanguageId === 0) {
            return parent::fetchFilteredTree($searchFilter, $allowedMountPointPageIds, $additionalWhereClause);
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        if (!empty($this->additionalQueryRestrictions)) {
            foreach ($this->additionalQueryRestrictions as $additionalQueryRestriction) {
                $queryBuilder->getRestrictions()->add($additionalQueryRestriction);
            }
        }

        $expressionBuilder = $queryBuilder->expr();

        if ($this->currentWorkspace === 0) {
            // Only include records from live workspace
            $workspaceIdExpression = $expressionBuilder->eq('t3ver_wsid', 0);
        } else {
            // Include live records PLUS records from the given workspace
            $workspaceIdExpression = $expressionBuilder->in(
                't3ver_wsid',
                [0, $this->currentWorkspace]
            );
        }

        $queryBuilder = $queryBuilder
            ->add('select', $this->quotedFields)
            ->from('pages')
            ->where(
            // Only show records in default language
                $expressionBuilder->eq('sys_language_uid', $queryBuilder->createNamedParameter($preferredLanguageId, Connection::PARAM_INT)),
                $workspaceIdExpression,
                QueryHelper::stripLogicalOperatorPrefix($additionalWhereClause)
            );

        $searchParts = $expressionBuilder->or();
        if (is_numeric($searchFilter) && $searchFilter > 0) {
            // Ensure that the LIVE id is also found
            if ($this->currentWorkspace > 0) {
                $uidFilter = $expressionBuilder->or(
                // Check for UID of live record
                    $expressionBuilder->and(
                        $expressionBuilder->eq('uid', $queryBuilder->createNamedParameter($searchFilter, Connection::PARAM_INT)),
                        $expressionBuilder->eq('t3ver_wsid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                    ),
                    // Check for UID of live record in versioned record
                    $expressionBuilder->and(
                        $expressionBuilder->eq('t3ver_oid', $queryBuilder->createNamedParameter($searchFilter, Connection::PARAM_INT)),
                        $expressionBuilder->eq('t3ver_wsid', $queryBuilder->createNamedParameter($this->currentWorkspace, Connection::PARAM_INT)),
                    ),
                    // Check for UID for new or moved versioned record
                    $expressionBuilder->and(
                        $expressionBuilder->eq('uid', $queryBuilder->createNamedParameter($searchFilter, Connection::PARAM_INT)),
                        $expressionBuilder->eq('t3ver_oid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                        $expressionBuilder->eq('t3ver_wsid', $queryBuilder->createNamedParameter($this->currentWorkspace, Connection::PARAM_INT)),
                    )
                );
            } else {
                $uidFilter = $expressionBuilder->eq('uid', $queryBuilder->createNamedParameter($searchFilter, Connection::PARAM_INT));
            }
            $searchParts = $searchParts->with($uidFilter);
        }
        $searchFilter = '%' . $queryBuilder->escapeLikeWildcards($searchFilter) . '%';

        $searchWhereAlias = $expressionBuilder->or(
            $expressionBuilder->like(
                'nav_title',
                $queryBuilder->createNamedParameter($searchFilter)
            ),
            $expressionBuilder->like(
                'title',
                $queryBuilder->createNamedParameter($searchFilter)
            )
        );
        $searchParts = $searchParts->with($searchWhereAlias);

        $queryBuilder->andWhere($searchParts);
        $pageRecords = $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();

        foreach($pageRecords as &$pageRecord) {
            if ($pageRecord['l10n_parent'] > 0 && $pageRecord['sys_language_uid'] > 0) {
                $pageRecord['uid'] = $pageRecord['l10n_parent'];
            }
        }

        $livePagePids = [];
        if ($this->currentWorkspace !== 0 && !empty($pageRecords)) {
            $livePageIds = [];
            foreach ($pageRecords as $pageRecord) {
                $livePageIds[] = (int)$pageRecord['uid'];
                $livePagePids[(int)$pageRecord['uid']] = (int)$pageRecord['pid'];
                if ((int)$pageRecord['t3ver_oid'] > 0) {
                    $livePagePids[(int)$pageRecord['t3ver_oid']] = (int)$pageRecord['pid'];
                }
                if ((int)$pageRecord['t3ver_state'] === VersionState::MOVE_POINTER) {
                    $movedPages[$pageRecord['t3ver_oid']] = [
                        'pid' => (int)$pageRecord['pid'],
                        'sorting' => (int)$pageRecord['sorting'],
                    ];
                }
            }
            // Resolve placeholders of workspace versions
            $resolver = GeneralUtility::makeInstance(
                PlainDataResolver::class,
                'pages',
                $livePageIds
            );
            $resolver->setWorkspaceId($this->currentWorkspace);
            $resolver->setKeepDeletePlaceholder(false);
            $resolver->setKeepMovePlaceholder(false);
            $resolver->setKeepLiveIds(false);
            $recordIds = $resolver->get();

            $pageRecords = [];
            if (!empty($recordIds)) {
                $queryBuilder->getRestrictions()->removeAll();
                $queryBuilder
                    ->add('select', $this->quotedFields)
                    ->from('pages')
                    ->where(
                        $queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($recordIds, Connection::PARAM_INT_ARRAY))
                    );
                $queryBuilder->andWhere($searchParts);
                $pageRecords = $queryBuilder
                    ->executeQuery()
                    ->fetchAllAssociative();
            }
        }

        $pages = [];
        foreach ($pageRecords as $pageRecord) {
            // In case this is a record from a workspace
            // The uid+pid of the live-version record is fetched
            // This is done in order to avoid fetching records again (e.g. via BackendUtility::workspaceOL()
            if ((int)$pageRecord['t3ver_oid'] > 0) {
                // This probably should also remove the live version
                if ((int)$pageRecord['t3ver_state'] === VersionState::DELETE_PLACEHOLDER) {
                    continue;
                }
                // When a move pointer is found, the pid+sorting of the versioned record be used
                if ((int)$pageRecord['t3ver_state'] === VersionState::MOVE_POINTER && !empty($movedPages[$pageRecord['t3ver_oid']])) {
                    $parentPageId = (int)$movedPages[$pageRecord['t3ver_oid']]['pid'];
                    $pageRecord['sorting'] = (int)$movedPages[$pageRecord['t3ver_oid']]['sorting'];
                } else {
                    // Just a record in a workspace (not moved etc)
                    $parentPageId = (int)$livePagePids[$pageRecord['t3ver_oid']];
                }
                // this is necessary so the links to the modules are still pointing to the live IDs
                $pageRecord['uid'] = (int)$pageRecord['t3ver_oid'];
                $pageRecord['pid'] = $parentPageId;
            }
            $pages[(int)$pageRecord['uid']] = $pageRecord;
        }
        unset($pageRecords);

        $pages = $this->filterPagesOnMountPoints($pages, $allowedMountPointPageIds);

        $groupedAndSortedPagesByPid = $this->groupAndSortPages($pages);

        $this->fullPageTree = [
            'uid' => 0,
            'title' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ?: 'TYPO3',
        ];
        $this->addChildrenToPage($this->fullPageTree, $groupedAndSortedPagesByPid);

        return $this->fullPageTree;
    }

    private function getPreferredLanguageId(): int
    {
        return (int)$GLOBALS['BE_USER']->user['tx_invero_tree_language'];
    }

}
