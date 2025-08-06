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

namespace GeorgRinger\Invero\Controller;

use GeorgRinger\Invero\Configuration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\PasswordPolicy\PasswordPolicyAction;
use TYPO3\CMS\Core\PasswordPolicy\PasswordPolicyValidator;
use TYPO3\CMS\Core\PasswordPolicy\Validator\Dto\ContextData;

#[AsController]
class PasswordMeterController
{
    public function verifyAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkIfEnabled();
        $password = $request->getParsedBody()['password'] ?? '';
        $validators = $request->getParsedBody()['list'] ?? [];

        $passwordPolicy = $GLOBALS['TYPO3_CONF_VARS']['BE']['passwordPolicy'] ?? 'default';
        $passwordPolicyValidator = new PasswordPolicyValidator(
            PasswordPolicyAction::UPDATE_USER_PASSWORD,
            is_string($passwordPolicy) ? $passwordPolicy : ''
        );
        $contextData = new ContextData();
        $passwordPolicyValidator->isValidPassword($password, $contextData);

        $errors = $passwordPolicyValidator->getValidationErrors();
        if (empty($errors)) {
            return new JsonResponse(['success' => true]);
        }

        $foundErrors = [];
        foreach ($errors as $identifier => $error) {
            foreach ($validators as $validatorId) {
                if (str_ends_with($validatorId, $identifier)) {
                    $foundErrors[] = $validatorId;
                }
            }
        }

        // todo what if mismatch in count of $errors + $foundErrors
        return new JsonResponse(['success' => false, 'errors' => $foundErrors, 'pwd' => $password]);
    }

    protected function checkIfEnabled(): void
    {
        $configuration = new Configuration();
        if (!$configuration->isPasswordMeter()) {
            throw new \RuntimeException('Password Meter is disabled');
        }
    }


}
