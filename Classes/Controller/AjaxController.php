<?php
declare(strict_types=1);

namespace GeorgRinger\Invero\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class AjaxController
{

    public function treeLanguageAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->persistLanguage((int)($request->getQueryParams()['language'] ?? 0));
        return new JsonResponse(['result' => 'Done']);
    }

    protected function persistLanguage(int $language): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
        $connection->update(
            'be_users',
            ['tx_invero_tree_language' => $language],
            ['uid' => $this->getBackendUserId()]
        );
    }

    private function getBackendUserId(): int
    {
        return $GLOBALS['BE_USER']->user['uid'];
    }
}
