<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\NotNormalizableValueConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Tests\Dto\ArrayDto;
use Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassB;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class NotNormalizableValueConstraintViolationTest extends TestCase
{
    public function testInvalidPropertyType(): void
    {
        $normalizer = new ObjectNormalizer();
        $class = DummyClassB::class;

        $exception = null;
        $data = ['requiredArgument' => 1, 'secondArgument' => 'test', 'someProperty' => null];
        try {
            $normalizer->denormalize(
                $data,
                $class
            );
        } catch (NotNormalizableValueException $ex) {
            $exception = $ex;
        }

        self::assertNotNull($exception);

        $constraintViolation = new NotNormalizableValueConstraintViolation($exception, $data, $class);
        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertSame('https://symfony.com/errors/validation', $result['type']);
        self::assertSame('Validation Failed', $result['title']);
        self::assertSame('someProperty: This value should be of type int.', $result['detail']);
        self::assertSame('someProperty', $result['violations'][0]['propertyPath']);
        self::assertSame('This value should be of type int.', $result['violations'][0]['title']);
        self::assertSame(['{{ type }}' => 'int'], $result['violations'][0]['parameters']);
        self::assertSame('urn:uuid:ba785a8c-82cb-4283-967c-3cf342181b40', $result['violations'][0]['type']);
        self::assertSame('This value should be of type {{ type }}.', $result['violations'][0]['messageTemplate']);
        self::assertSame('INVALID_TYPE_ERROR', $result['violations'][0]['errorName']);

        if (Kernel::VERSION_ID >= 60300) {
            self::assertSame('This value should be of type {{ type }}.', $result['violations'][0]['template']);
        }
    }

    public function testMissingArray(): void
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $encoders = [new JsonEncoder()];
        $normalizers = [
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, $extractor),
        ];

        $serializer = new Serializer($normalizers, $encoders);
        $error = null;
        $data = ['requiredArgument' => 1, 'items' => null];

        try {
            $serializer->denormalize($data, ArrayDto::class, 'json');
        } catch (\Throwable $ex) {
            $error = $ex;
        }

        self::assertNotNull($error);
        self::assertInstanceOf(NotNormalizableValueException::class, $error);

        $constraintViolation = new NotNormalizableValueConstraintViolation($error, $data, ArrayDto::class);

        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertSame('https://symfony.com/errors/validation', $result['type']);
        self::assertSame('Validation Failed', $result['title']);
        self::assertSame('items: This value should be of type Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA[].', $result['detail']);
        self::assertSame('items', $result['violations'][0]['propertyPath']);
        self::assertSame('This value should be of type Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA[].', $result['violations'][0]['title']);
        self::assertSame(['{{ type }}' => 'Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA[]'], $result['violations'][0]['parameters']);
        self::assertSame('urn:uuid:ba785a8c-82cb-4283-967c-3cf342181b40', $result['violations'][0]['type']);
        self::assertSame('This value should be of type {{ type }}.', $result['violations'][0]['messageTemplate']);
        self::assertSame('INVALID_TYPE_ERROR', $result['violations'][0]['errorName']);

        if (Kernel::VERSION_ID >= 60300) {
            self::assertSame('This value should be of type {{ type }}.', $result['violations'][0]['template']);
        }
    }
}
