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
    public function __construct(NotNormalizableValueException $exception, array $data, string $className)
    {
        $message = $exception->getMessage();
        $matches = null;
        $propertyPath = null;
        $invalidValue = null;

        if (str_starts_with($message, 'The type of the')) {
            $pattern = '/The type of the "(\w+)" attribute for class "(.+)" must be one of "(.+)" \("(.+)" given\)\./';
            preg_match($pattern, $message, $matches);

            if (count($matches) < 5) {
                throw $exception;
            }

            $invalidValue = $matches[4];
            $expectedType = $matches[3];
            $propertyPath = $matches[1];
        } elseif (str_starts_with($message, 'Failed to denormalize attribute')) {
            $pattern = '/Failed to denormalize attribute "(\w+)" value for class "(.+)": Expected argument of type "(.+)", "(.+)" given/';
            preg_match($pattern, $message, $matches);

            if (count($matches) < 5) {
                throw $exception;
            }

            $invalidValue = $matches[4];
            $expectedType = $matches[3];
            $propertyPath = $matches[1];
        } elseif (str_starts_with($message, 'Data expected to be')) {
            $pattern = '/Data expected to be "(.+)", (.+) given\./';

            preg_match($pattern, $message, $matches);
            if (count($matches) < 3) {
                throw $exception;
            }

            $expectedType = $matches[1];

            if (!class_exists($className)) {
                throw $exception;
            }

            $propertyPath = $this->determinePropertyPath($data, $className);
        } else {
            throw $exception;
        }

        $propertyPath = null === $propertyPath ? $exception->getPath() ?? '' : $propertyPath;
        $invalidValue = null === $invalidValue ? $exception->getCurrentType() ?? null : $invalidValue;
        $constraint = new Type($propertyPath);

        parent::__construct(
            message: str_replace('{{ type }}', $expectedType, $constraint->message),
            messageTemplate: $constraint->message,
            parameters: ['{{ type }}' => $expectedType],
            root: null,
            propertyPath: $propertyPath,
            invalidValue: $invalidValue,
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
        $class = new \ReflectionClass($className);

        $constructor = $class->getConstructor();
        $parameters = $constructor?->getParameters() ?? [];

        foreach ($parameters as $parameter) {
            $parameterName = $parameter->getName();
            /** @var \ReflectionNamedType|null $reflectionType */
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
