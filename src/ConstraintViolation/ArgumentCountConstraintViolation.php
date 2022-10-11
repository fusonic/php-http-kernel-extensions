<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ConstraintViolation;

use ArgumentCountError;
use Fusonic\HttpKernelExtensions\Cache\ReflectionClassCache;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Wraps a {@see ArgumentCountError} into a {@see ConstraintViolation}.
 */
class ArgumentCountConstraintViolation extends ConstraintViolation
{
    private const EXPECTED_MATCHES = 6;

    public function __construct(\ArgumentCountError $error)
    {
        $message = $error->getMessage();

        if (!str_starts_with($message, 'Too few arguments to function')) {
            throw $error;
        }

        $matches = null;
        $pattern = '/Too few arguments to function (.+)::__construct\(\), (\d+) passed in (.+) on line (\d+) and (?:at least|exactly) (\d+) expected/';

        preg_match($pattern, $message, $matches);

        if (count($matches) < self::EXPECTED_MATCHES) {
            throw $error;
        }

        // Get the first missing constructor argument
        if (!class_exists($matches[1])) {
            throw $error;
        }

        $class = ReflectionClassCache::getReflectionClass($matches[1]);

        $constructor = $class->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];

        $propertyPath = null;

        foreach ($parameters as $parameter) {
            if (!$parameter->isOptional()) {
                $propertyPath = $parameter->getName();
            }
        }

        // If no propertyPath is found throw the exception up
        if (null === $propertyPath) {
            throw $error;
        }

        $constraint = new NotNull();

        parent::__construct(
            message: $constraint->message,
            messageTemplate: $constraint->message,
            parameters: [],
            root: null,
            propertyPath: $propertyPath,
            invalidValue: null,
            code: NotNull::IS_NULL_ERROR,
            constraint: $constraint
        );
    }
}
