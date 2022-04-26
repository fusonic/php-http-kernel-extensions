<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ConstraintViolation;

use ReflectionClass;
use ReflectionNamedType;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * Wraps a {@see InvalidArgumentException} into a {@see ConstraintViolation}.
 *
 * The InvalidArgumentException is caused by a non-nullable array which has objects as its type.
 * If the value for it is null, the exception will be thrown.
 */
class ArrayDenormalizerConstraintViolation extends ConstraintViolation
{
    private const EXPECTED_MATCHES = 2;

    /**
     * @param array<string, mixed> $data
     * @param class-string         $className
     */
    public function __construct(InvalidArgumentException $error, array $data, string $className)
    {
        $message = $error->getMessage();

        if (!str_starts_with($message, 'Data expected to be an array')) {
            throw $error;
        }

        $matches = null;
        $pattern = '/Data expected to be an array, (.+) given\./';

        preg_match($pattern, $message, $matches);

        if (count($matches) < self::EXPECTED_MATCHES) {
            throw $error;
        }

        if (!class_exists($className)) {
            throw $error;
        }

        $propertyPath = $this->determinePropertyPath($data, $className);

        $constraint = new Type('array');

        parent::__construct(
            message: str_replace('{{ type }}', 'array', $constraint->message),
            messageTemplate: $constraint->message,
            parameters: ['{{ type }}' => 'array'],
            root: null,
            propertyPath: $propertyPath,
            invalidValue: $matches[1],
            code: Type::INVALID_TYPE_ERROR,
            constraint: $constraint
        );
    }

    /**
     * Find out which property is an array that shouldn't be null.
     *
     * @param array<string, mixed> $data
     * @param class-string         $className
     */
    private function determinePropertyPath(array $data, string $className): ?string
    {
        $class = new ReflectionClass($className);

        $constructor = $class->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];

        foreach ($parameters as $parameter) {
            $parameterName = $parameter->getName();
            /** @var ReflectionNamedType|null $reflectionType */
            $reflectionType = $parameter->getType();

            if (!$parameter->isOptional() && null !== $reflectionType && 'array' === $reflectionType->getName()) {
                $parameterValue = $data[$parameterName] ?? null;

                if (null === $parameterValue) {
                    return $parameterName;
                }
            }
        }

        return null;
    }
}
