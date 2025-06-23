<?php

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

namespace GeorgRinger\Invero\Backend\Menu;

use TYPO3\CMS\Backend\Template\Components\AbstractControl;
use TYPO3\CMS\Backend\Template\Components\Menu\MenuItem;

class MenuOptgroupItem extends AbstractControl
{
    protected array $menuItems = [];

    public function addMenuItem(MenuItem $menuItem)
    {
        if (!$menuItem->isValid($menuItem)) {
            throw new \InvalidArgumentException('MenuItem "' . $menuItem->getTitle() . '" is not valid', 1750099769);
        }
        $this->menuItems[] = clone $menuItem;
    }

    public function getMenuItems(): array
    {
        return $this->menuItems;
    }

    public function isValid(MenuOptgroupItem $menuItem)
    {
        return $menuItem->getTitle() !== '';
    }

    public function isOptgroup()
    {
        return true;
    }
}
