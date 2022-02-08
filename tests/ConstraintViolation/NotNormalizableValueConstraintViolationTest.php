<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\NotNormalizableValueConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassB;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class NotNormalizableValueConstraintViolationTest extends TestCase
{
    public function testMessage(): void
    {
        $normalizer = new ObjectNormalizer();
        $class = DummyClassB::class;

        $exception = null;
        try {
            $normalizer->denormalize(
                ['requiredArgument' => 1, 'secondArgument' => 'test', 'someProperty' => null],
                $class
            );
        } catch (NotNormalizableValueException $ex) {
            $exception = $ex;
        }

        self::assertNotNull($exception);

        $constraintViolation = new NotNormalizableValueConstraintViolation($exception);
        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertEquals(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'someProperty: This value should be of type int.',
                'violations' => [
                        [
                            'propertyPath' => 'someProperty',
                            'title' => 'This value should be of type int.',
                            'messageTemplate' => 'This value should be of type {{ type }}.',
                            'parameters' => [
                                    '{{ type }}' => 'int',
                                ],
                            'type' => 'urn:uuid:ba785a8c-82cb-4283-967c-3cf342181b40',
                            'errorName' => 'INVALID_TYPE_ERROR',
                        ],
                    ],
            ],
            $result
        );
    }
}
