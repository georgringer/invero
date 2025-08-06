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

namespace GeorgRinger\Invero;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Configuration
{

    protected bool $recordListSelection = true;
    protected bool $l10nTree = true;
    protected bool $passwordMeter = true;
    protected bool $bulkEditing = true;

    public function __construct()
    {
        try {
            $settings = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('invero');
            $this->recordListSelection = (bool)($settings['recordListSelection'] ?? true);
            $this->l10nTree = (bool)($settings['l10nTree'] ?? true);
            $this->passwordMeter = (bool)($settings['passwordMeter'] ?? true);
            $this->bulkEditing = (bool)($settings['bulkEditing'] ?? true);
        } catch (\Exception $e) {
            // do nothing
        }
    }

    public function isRecordListSelection(): bool
    {
        return $this->recordListSelection;
    }

    public function isL10nTree(): bool
    {
        return $this->l10nTree;
    }

    public function isPasswordMeter(): bool
    {
        return $this->passwordMeter;
    }

    public function isBulkEditing(): bool
    {
        return $this->bulkEditing;
    }

}
