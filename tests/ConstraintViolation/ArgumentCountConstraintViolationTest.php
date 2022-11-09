<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\ArgumentCountConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;

class ArgumentCountConstraintViolationTest extends TestCase
{
    public function testMessage(): void
    {
        $error = null;

        try {
            $testClassName = DummyClassA::class;

            // Instantiate class without required arguments to trigger the exception
            // @phpstan-ignore-next-line
            new $testClassName();
        } catch (\ArgumentCountError $ex) {
            $error = $ex;
        }

        self::assertNotNull($error);

        $constraintViolation = new ArgumentCountConstraintViolation($error);

        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertEquals(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'requiredArgument: This value should not be null.',
                'violations' => [
                        [
                            'propertyPath' => 'requiredArgument',
                            'title' => 'This value should not be null.',
                            'messageTemplate' => 'This value should not be null.',
                            'parameters' => [],
                            'type' => 'urn:uuid:ad32d13f-c3d4-423b-909a-857b961eb720',
                            'errorName' => 'IS_NULL_ERROR',
                        ],
                    ],
            ],
            $result
        );
    }
}
