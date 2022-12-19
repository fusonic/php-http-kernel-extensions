<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ConstraintViolation;

use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Wraps a {@see MissingConstructorArgumentsException} into a {@see ConstraintViolation}.
 */
class MissingConstructorArgumentsConstraintViolation extends ConstraintViolation
{
    private const EXPECTED_MATCHES = 3;

    public function __construct(MissingConstructorArgumentsException $exception)
    {
        $message = $exception->getMessage();

        if (!str_starts_with($message, 'Cannot create an instance of')) {
            throw $exception;
        }

        $matches = null;
        $pattern = '/Cannot create an instance of "(.+)" from serialized data because its constructor requires parameter "(.+)" to be present\./';

        preg_match($pattern, $message, $matches);

        if (\count($matches) < self::EXPECTED_MATCHES) {
            throw $exception;
        }

        $constraint = new NotNull();

        parent::__construct(
            message: $constraint->message,
            messageTemplate: $constraint->message,
            parameters: [],
            root: null,
            propertyPath: $matches[2],
            invalidValue: null,
            code: NotNull::IS_NULL_ERROR,
            constraint: $constraint
        );
    }
}
