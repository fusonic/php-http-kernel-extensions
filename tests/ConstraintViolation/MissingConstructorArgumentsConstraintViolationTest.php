<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\MissingConstructorArgumentsConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class MissingConstructorArgumentsConstraintViolationTest extends TestCase
{
    public function testMessage(): void
    {
        $normalizer = new ObjectNormalizer();
        $class = DummyClassB::class;

        $error = null;
        try {
            $normalizer->denormalize(['requiredArgument' => 1], $class);
        } catch (MissingConstructorArgumentsException $ex) {
            $error = $ex;
        }

        self::assertNotNull($error);
        $constraintViolation = new MissingConstructorArgumentsConstraintViolation($error);

        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertEquals(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'secondArgument: This value should not be null.',
                'violations' => [
                    [
                        'propertyPath' => 'secondArgument',
                        'title' => 'This value should not be null.',
                        'messageTemplate' => 'This value should not be null.',
                        'parameters' => [
                        ],
                        'type' => 'urn:uuid:ad32d13f-c3d4-423b-909a-857b961eb720',
                        'errorName' => 'IS_NULL_ERROR',
                    ],
                ],
            ],
            $result
        );
    }
}
