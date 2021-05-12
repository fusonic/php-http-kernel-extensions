<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ErrorHandler;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ErrorHandler implements ErrorHandlerInterface
{
    public function handleDenormalizeError(ExceptionInterface $ex): \Throwable
    {
        return new BadRequestHttpException($ex->getMessage());
    }

    public function handleConstraintViolations(ConstraintViolationListInterface $list): void
    {
        $details = '';
        foreach ($list as $violation) {
            $details .= $violation->getPropertyPath().': '.$violation->getMessage().PHP_EOL;
        }

        throw new BadRequestHttpException('The request payload is invalid!'.PHP_EOL.$details);
    }
}
