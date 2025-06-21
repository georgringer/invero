<?php
declare(strict_types=1);

namespace GeorgRinger\Invero\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterPageTreeItemsPreparedEvent;
use TYPO3\CMS\Backend\Dto\Tree\Label\Label;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Information\Typo3Information;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsEventListener()]
class LocalizePageTree
{

    public function __invoke(AfterPageTreeItemsPreparedEvent $event): void
    {
        $preferredLanguageId = $this->getPreferredLanguageId();
        if ($preferredLanguageId === 0) {
            return;
        }

        $typo3Version = new Typo3Version();

        $items = $event->getItems();
        foreach ($items as $key => $element) {
            if ($element['recordType'] === 'pages' || $typo3Version->getMajorVersion() === 12) {
                $label = $this->getPossibleTranslation((int)$items[$key]['identifier'], $preferredLanguageId);
                if ($label) {
//                    $items[$key]['labels'][] = new Label(
//                        label: $items[$key]['name'],
//                        color: '#6daae0',
//                        priority: 100
//                    );

                    $labelRendering = $this->getLabelRendering($preferredLanguageId);
                    if ($labelRendering) {
                        $items[$key]['name'] = sprintf($labelRendering, $label);
                    }

                }
            }
        }
        $event->setItems($items);
    }

    private function getPossibleTranslation(int $uid, int $languageId): ?string
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        $row = $queryBuilder->select('title')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($uid)),
                $queryBuilder->expr()->eq('sys_language_uid', $languageId)
            )
            ->executeQuery()
            ->fetchAssociative();
        return $row['title'] ?? null;
    }

    private function getLabelRendering(int $language): ?string
    {
        try {
            $configuration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('invero', 'labelRendering');
            foreach (GeneralUtility::trimExplode(',', $configuration, true) as $item) {
                $line = GeneralUtility::trimExplode('=', $item, true, 2);
                if ((int)$line[0] === $language) {
                    return $line[1];
                }
            }
        } catch (\Exception $exception) {

        }
        return null;
    }

    private function getPreferredLanguageId(): int
    {
        return (int)$GLOBALS['BE_USER']->user['tx_invero_tree_language'];
    }
}
