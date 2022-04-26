<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

use Fusonic\HttpKernelExtensions\ConstraintViolation\ArrayDenormalizerConstraintViolation;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Tests\Dto\ArrayDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ArrayDenormalizerConstraintViolationTest extends TestCase
{
    public function testMessage(): void
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
        self::assertInstanceOf(InvalidArgumentException::class, $error);

        $constraintViolation = new ArrayDenormalizerConstraintViolation($error, $data, ArrayDto::class);

        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());
        $result = $normalizer->normalize(ConstraintViolationException::fromConstraintViolation($constraintViolation));

        self::assertEquals(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'items: This value should be of type array.',
                'violations' => [
                        [
                            'propertyPath' => 'items',
                            'title' => 'This value should be of type array.',
                            'parameters' => [
                                    '{{ type }}' => 'array',
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
