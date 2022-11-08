<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ConstraintViolation;

use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use TypeError;

/**
 * Wraps a {@see TypeError} into a {@see ConstraintViolation}.
 */
final class TypeConstraintViolation extends ConstraintViolation
{
    private const EXPECTED_MATCHES = 4;

    public function __construct(\TypeError $error)
    {
        $message = $error->getMessage();

        if (!str_contains($message, 'must be of type')) {
            throw $error;
        }

        $matches = null;
        $pattern = '/.+: Argument #\d+ \(\$(.+)\) must be of type \??(.+), (.+) given/';

        preg_match($pattern, $message, $matches);

        if (count($matches) < self::EXPECTED_MATCHES) {
            throw $error;
        }

        $constraint = new Type($matches[2]);

        parent::__construct(
            message: str_replace('{{ type }}', $matches[2], $constraint->message),
            messageTemplate: $constraint->message,
            parameters: ['{{ type }}' => $matches[2]],
            root: null,
            propertyPath: $matches[1],
            invalidValue: $matches[3],
            code: Type::INVALID_TYPE_ERROR,
            constraint: $constraint
        );
    }
}
