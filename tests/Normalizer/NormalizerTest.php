<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Normalizer;

use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validation;

class NormalizerTest extends TestCase
{
    public function testValidationError(): void
    {
        $validator = Validation::createValidator();
        $normalizer = new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer());

        $violations = $validator->validate('Bernhard', [
            new Length(['min' => 10]),
            new NotBlank(),
        ]);

        self::assertCount(1, $violations);

        $exception = new ConstraintViolationException($violations);

        self::assertTrue($normalizer->supportsNormalization($exception));
        self::assertTrue($normalizer->hasCacheableSupportsMethod());
        $result = $normalizer->normalize($exception);

        self::assertSame('ConstraintViolation: This value is too short. It should have 10 characters or more.', $exception->getMessage());
        self::assertEquals(
            [
                'type' => 'https://symfony.com/errors/validation',
                'title' => 'Validation Failed',
                'detail' => 'This value is too short. It should have 10 characters or more.',
                'violations' => [
                        [
                            'propertyPath' => '',
                            'title' => 'This value is too short. It should have 10 characters or more.',
                            'messageTemplate' => 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.',
                            'parameters' => [
                                    '{{ value }}' => '"Bernhard"',
                                    '{{ limit }}' => '10',
                                ],
                            'type' => 'urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45',
                            'errorName' => 'TOO_SHORT_ERROR',
                        ],
                    ],
            ],
            $result
        );
    }
}
