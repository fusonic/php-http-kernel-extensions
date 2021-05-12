<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ErrorHandler;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

interface ErrorHandlerInterface
{
    /**
     * If no object can be created from the given class and request no further processing is possible and an exception
     * has to be thrown.
     */
    public function handleDenormalizeError(ExceptionInterface $ex): \Throwable;

    /**
     * If only constraint violations are discovered but the object could be created, the choice what to do is the devs.
     */
    public function handleConstraintViolations(ConstraintViolationListInterface $list): void;
}
