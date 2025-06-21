<?php

declare(strict_types=1);


namespace GeorgRinger\Invero\Backend\ToolbarItems;

use Doctrine\DBAL\Exception\TableNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Domain\Model\Element\ImmediateActionElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Opendocs\Service\OpenDocumentService;


#[Autoconfigure(public: true)]
class LanguageToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly UriBuilder         $uriBuilder,
        private readonly BackendViewFactory $backendViewFactory,
    )
    {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    /**
     * Checks whether the user has access to this toolbar item.
     */
    public function checkAccess(): bool
    {
        return true;
        return !(bool)($this->getBackendUser()->getTSConfig()['backendToolbarItem.']['tx_opendocs.']['disabled'] ?? false);
    }

    /**
     * Render toolbar icon via Fluid
     */
    public function getItem(): string
    {
        $view = $this->backendViewFactory->create($this->request, ['georgringer/invero']);
        return $view->render('ToolbarItems/ToolbarItem');
    }

    /**
     * This item has a drop-down.
     */
    public function hasDropDown(): bool
    {
        return true;
    }

    /**
     * Render drop-down.
     */
    public function getDropDown(): string
    {
        $view = $this->backendViewFactory->create($this->request, ['georgringer/invero']);
        $view->assignMultiple([
            'languages' => $this->populateAvailableSiteLanguages(),
            'selected' => $this->getPreferredLanguageId(),
        ]);
        return $view->render('ToolbarItems/DropDown');
    }

    /**
     * No additional attributes
     */
    public function getAdditionalAttributes(): array
    {
        return [];
    }

    /**
     * Position relative to others
     */
    public function getIndex(): int
    {
        return 30;
    }

    protected function populateAvailableSiteLanguages(): array
    {
        $allLanguages = [];
        foreach ($this->getAllSites() as $site) {
            foreach ($site->getAllLanguages() as $language) {
                $languageId = $language->getLanguageId();
                if (isset($allLanguages[$languageId])) {
                    // Language already provided by another site, just add the label separately
                    $allLanguages[$languageId]['label'] .= ', ' . $language->getTitle() . ' [Site: ' . $site->getIdentifier() . ']';
                    continue;
                }
                $allLanguages[$languageId] = [
                    'label' => $language->getTitle() . ' [Site: ' . $site->getIdentifier() . ']',
                    'value' => $languageId,
                    'icon' => $language->getFlagIdentifier(),
                ];
            }
        }
        return $allLanguages;

    }

    protected function getAllSites(): array
    {
        return GeneralUtility::makeInstance(SiteFinder::class)->getAllSites();
    }

    /**
     * Returns the data for a recent or open document
     *
     * @return array The data of a recent or closed document, or empty array if no record was found (e.g. deleted)
     */
    protected function getMenuEntry(array $document, string $identifier): array
    {
        $table = $document[3]['table'] ?? '';
        $uid = $document[3]['uid'] ?? 0;

        try {
            $record = BackendUtility::getRecordWSOL($table, $uid);
        } catch (TableNotFoundException) {
            // This exception is caught in cases, when you have an recently opened document
            // from an extension record (let's say a sys_note record) and then uninstall
            // the extension and drop the DB table. After then, the DB table could
            // not be found anymore and will throw an exception making the
            // whole backend unusable.
            $record = null;
        }

        if (!is_array($record)) {
            // Record seems to be deleted
            return [];
        }

        $result = [];
        $result['table'] = $table;
        $result['record'] = $record;
        $result['label'] = strip_tags(htmlspecialchars_decode($document[0]));
        $uri = $this->uriBuilder->buildUriFromRoute('record_edit', ['returnUrl' => $document[4] ?? null]) . '&' . $document[2];
        $pid = (int)$document[3]['pid'];

        if ($document[3]['table'] === 'pages') {
            $pid = (int)$document[3]['uid'];
        }

        $result['pid'] = $pid;
        $result['uri'] = $uri;
        $result['md5sum'] = $identifier;

        return $result;
    }

    private function getPreferredLanguageId(): int
    {
        return (int)$this->getBackendUser()->user['tx_invero_tree_language'];
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
