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

        self::assertSame(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'someProperty: This value should be of type int.',
                'violations' => [
                        [
                            'propertyPath' => 'someProperty',
                            'title' => 'This value should be of type int.',
                            'parameters' => [
                                '{{ type }}' => 'int',
                            ],
                            'type' => 'urn:uuid:ba785a8c-82cb-4283-967c-3cf342181b40',
                            'messageTemplate' => 'This value should be of type {{ type }}.',
                            'errorName' => 'INVALID_TYPE_ERROR',
                        ],
                    ],
            ],
            $result
        );
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

        self::assertSame(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'items: This value should be of type Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA[].',
                'violations' => [
                    [
                        'propertyPath' => 'items',
                        'title' => 'This value should be of type Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA[].',
                        'parameters' => [
                            '{{ type }}' => 'Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA[]',
                        ],
                        'type' => 'urn:uuid:ba785a8c-82cb-4283-967c-3cf342181b40',
                        'messageTemplate' => 'This value should be of type {{ type }}.',
                        'errorName' => 'INVALID_TYPE_ERROR',
                    ],
                ],
            ],
            $result
        );
    }
}
