<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ErrorHandler;

use ArgumentCountError;
use Fusonic\HttpKernelExtensions\ConstraintViolation\ArgumentCountConstraintViolation;
use Fusonic\HttpKernelExtensions\ConstraintViolation\MissingConstructorArgumentsConstraintViolation;
use Fusonic\HttpKernelExtensions\ConstraintViolation\NotNormalizableValueConstraintViolation;
use Fusonic\HttpKernelExtensions\ConstraintViolation\TypeConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;
use TypeError;

class ConstraintViolationErrorHandler implements ErrorHandlerInterface
{
    public function handleDenormalizeError(ExceptionInterface $ex): Throwable
    {
        if ($ex instanceof NotNormalizableValueException) {
            return ConstraintViolationException::fromConstraintViolation(
                new NotNormalizableValueConstraintViolation($ex)
            );
        }

        if ($ex instanceof MissingConstructorArgumentsException) {
            return ConstraintViolationException::fromConstraintViolation(
                new MissingConstructorArgumentsConstraintViolation($ex)
            );
        }

        if ($ex instanceof ArgumentCountError) {
            return ConstraintViolationException::fromConstraintViolation(new ArgumentCountConstraintViolation($ex));
        }

        if ($ex instanceof TypeError) {
            return ConstraintViolationException::fromConstraintViolation(new TypeConstraintViolation($ex));
        }

        return $ex;
    }

    public function handleConstraintViolations(ConstraintViolationListInterface $list): void
    {
        throw new ConstraintViolationException($list);
    }
}
