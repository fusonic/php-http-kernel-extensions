<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Normalizer;

use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Kernel;
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

        $result = $normalizer->normalize($exception);

        self::assertSame('ConstraintViolation: This value is too short. It should have 10 characters or more.', $exception->getMessage());

        self::assertSame('https://symfony.com/errors/validation', $result['type']);
        self::assertSame('Validation Failed', $result['title']);
        self::assertSame('This value is too short. It should have 10 characters or more.', $result['detail']);
        self::assertSame('', $result['violations'][0]['propertyPath']);
        self::assertSame(
            expected: 'This value is too short. It should have 10 characters or more.',
            actual: $result['violations'][0]['title']
        );
        self::assertSame('"Bernhard"', $result['violations'][0]['parameters']['{{ value }}']);
        self::assertSame('10', $result['violations'][0]['parameters']['{{ limit }}']);
        self::assertSame('urn:uuid:9ff3fdc4-b214-49db-8718-39c315e33d45', $result['violations'][0]['type']);
        self::assertSame(
            expected: 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.',
            actual: $result['violations'][0]['messageTemplate']
        );
        self::assertSame('TOO_SHORT_ERROR', $result['violations'][0]['errorName']);

        if (Kernel::VERSION_ID >= 60300) {
            self::assertSame(
                expected: 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.',
                actual: $result['violations'][0]['template']);
        }

        if (Kernel::VERSION_ID >= 60300) {
            self::assertSame('8', $result['violations'][0]['parameters']['{{ value_length }}']);
        }
    }
}
