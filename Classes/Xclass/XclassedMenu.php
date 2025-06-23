<?php

namespace GeorgRinger\Invero\Xclass;

use GeorgRinger\Invero\Backend\Menu\MenuOptgroupItem;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class XclassedMenu extends Menu
{
    public function addMenuOptgroupItem(MenuOptgroupItem $item)
    {
        if (!$item->isValid($item)) {
            throw new \InvalidArgumentException('MenuOptgroupItem "' . $item->getTitle() . '" is not valid', 1750100012);
        }
        $this->menuItems[] = clone $item;
    }

    public function makeMenuOptgroupItem(): MenuOptgroupItem
    {
        return GeneralUtility::makeInstance(MenuOptgroupItem::class);
    }


}
