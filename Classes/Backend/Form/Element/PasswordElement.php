<?php

declare(strict_types=1);

namespace GeorgRinger\Invero\Backend\Form\Element;

use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

class PasswordElement extends \TYPO3\CMS\Backend\Form\Element\PasswordElement
{

    public function render(): array
    {
        $resultArray = parent::render();
        unset($resultArray['javaScriptModules'][0]);
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@georgringer/invero/password-element.js'
        );

        // add empty span for every password requirement
        $resultArray['html'] = preg_replace_callback(
            '/(<li\b[^>]*>)(.*?)(<\/li>)/is',
            static function ($matches) {
                return $matches[1] . '<span></span>&nbsp;' . $matches[2] . $matches[3];
            },
            $resultArray['html']
        );

        return $resultArray;
    }
}
