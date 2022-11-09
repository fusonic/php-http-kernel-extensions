<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\TypeConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;

class TypeConstraintViolationTest extends TestCase
{
    public function testMessage(): void
    {
        $error = null;

        try {
            $testClassName = DummyClassA::class;

            // Instantiate class with wrong argument type to trigger the exception
            // @phpstan-ignore-next-line
            new $testClassName('wrong type');
        } catch (\TypeError $ex) {
            $error = $ex;
        }

        self::assertNotNull($error);

        $constraintViolation = new TypeConstraintViolation($error);
        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertEquals(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'requiredArgument: This value should be of type int.',
                'violations' => [
                        [
                            'propertyPath' => 'requiredArgument',
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
