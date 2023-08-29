<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\MissingConstructorArgumentsConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassB;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;
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

        self::assertSame('https://symfony.com/errors/validation', $result['type']);
        self::assertSame('Validation Failed', $result['title']);
        self::assertMatchesRegularExpression('/\$?secondArgument: This value should not be null\./', $result['detail']);
        self::assertMatchesRegularExpression('/\$?secondArgument/', $result['violations'][0]['propertyPath']);
        self::assertSame('This value should not be null.', $result['violations'][0]['title']);
        self::assertSame([], $result['violations'][0]['parameters']);
        self::assertSame('urn:uuid:ad32d13f-c3d4-423b-909a-857b961eb720', $result['violations'][0]['type']);
        self::assertSame('This value should not be null.', $result['violations'][0]['messageTemplate']);
        self::assertSame('IS_NULL_ERROR', $result['violations'][0]['errorName']);

        if (Kernel::VERSION_ID >= 60300) {
            self::assertSame('This value should not be null.', $result['violations'][0]['template']);
        }
    }
}
