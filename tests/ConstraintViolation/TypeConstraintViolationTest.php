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
use Symfony\Component\HttpKernel\Kernel;
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

        self::assertSame('https://symfony.com/errors/validation', $result['type']);
        self::assertSame('Validation Failed', $result['title']);
        self::assertSame('requiredArgument: This value should be of type int.', $result['detail']);
        self::assertSame('requiredArgument', $result['violations'][0]['propertyPath']);
        self::assertSame('This value should be of type int.', $result['violations'][0]['title']);
        self::assertSame(['{{ type }}' => 'int'], $result['violations'][0]['parameters']);
        self::assertSame('urn:uuid:ba785a8c-82cb-4283-967c-3cf342181b40', $result['violations'][0]['type']);
        self::assertSame('This value should be of type {{ type }}.', $result['violations'][0]['messageTemplate']);
        self::assertSame('INVALID_TYPE_ERROR', $result['violations'][0]['errorName']);

        if (Kernel::VERSION_ID >= 60300) {
            self::assertSame('This value should be of type {{ type }}.', $result['violations'][0]['template']);
        }
    }
}
