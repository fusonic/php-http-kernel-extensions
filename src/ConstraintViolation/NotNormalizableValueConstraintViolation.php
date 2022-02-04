<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ConstraintViolation;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Wraps a {@see NotNormalizableValueException} into a {@see ConstraintViolation}.
 */
class NotNormalizableValueConstraintViolation extends ConstraintViolation
{
    private const EXPECTED_MATCHES = 5;

    public function __construct(NotNormalizableValueException $exception)
    {
        $message = $exception->getMessage();

        if (str_starts_with($message, 'The type of the')) {
            $pattern = '/The type of the "(\w+)" attribute for class "(.+)" must be one of "(.+)" \("(.+)" given\)\./';
        } elseif (str_starts_with($message, 'Failed to denormalize attribute')) {
            $pattern = '/Failed to denormalize attribute "(\w+)" value for class "(.+)": Expected argument of type "(.+)", "(.+)" given/';
        } else {
            throw $exception;
        }

        $matches = null;
        preg_match($pattern, $message, $matches);

        if (count($matches) < self::EXPECTED_MATCHES) {
            throw $exception;
        }

        $constraint = new Type($matches[1]);

        parent::__construct(
            message: str_replace('{{ type }}', $matches[3], $constraint->message),
            messageTemplate: $constraint->message,
            parameters: ['{{ type }}' => $matches[3]],
            root: null,
            propertyPath: $matches[1],
            invalidValue: $matches[4],
            code: Type::INVALID_TYPE_ERROR,
            constraint: $constraint
        );
    }
}
