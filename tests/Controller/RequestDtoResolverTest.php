<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Controller;

use Fusonic\HttpKernelExtensions\Attribute\FromRequest;
use Fusonic\HttpKernelExtensions\ConstraintViolation\ArgumentCountConstraintViolation;
use Fusonic\HttpKernelExtensions\ConstraintViolation\MissingConstructorArgumentsConstraintViolation;
use Fusonic\HttpKernelExtensions\ConstraintViolation\NotNormalizableValueConstraintViolation;
use Fusonic\HttpKernelExtensions\ConstraintViolation\TypeConstraintViolation;
use Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver;
use Fusonic\HttpKernelExtensions\Exception\ConstraintViolationException;
use Fusonic\HttpKernelExtensions\Normalizer\ConstraintViolationExceptionNormalizer;
use Fusonic\HttpKernelExtensions\Provider\ContextAwareProviderInterface;
use Fusonic\HttpKernelExtensions\Tests\Dto\ClassDtoWithAttribute;
use Fusonic\HttpKernelExtensions\Tests\Dto\DummyClassA;
use Fusonic\HttpKernelExtensions\Tests\Dto\EmptyDto;
use Fusonic\HttpKernelExtensions\Tests\Dto\NotADto;
use Fusonic\HttpKernelExtensions\Tests\Dto\QueryDtoWithAttribute;
use Fusonic\HttpKernelExtensions\Tests\Dto\RouteParameterDto;
use Fusonic\HttpKernelExtensions\Tests\Dto\TestDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ConstraintViolationListNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

class RequestDtoResolverTest extends TestCase
{
    public function testSupportOfNotSupportedClass(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 15]]);
        $argument = $this->createArgumentMetadata(NotADto::class, []);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertFalse($resolver->supports($request, $argument));
    }

    public function testResolveOfNotSupportedClass(): void
    {
        $this->expectException(\LogicException::class);
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = $this->createArgumentMetadata(NotADto::class, []);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $resolver->resolve($request, $argument)->current();
    }

    public function testSupportOfNotExistingClass(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = $this->createArgumentMetadata('NotExistingClass', [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertFalse($resolver->supports($request, $argument));
    }

    public function testSupportWithNull(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = new ArgumentMetadata('routeParameterDto', null, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertFalse($resolver->supports($request, $argument));
    }

    public function testSupportValidClass(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertTrue($resolver->supports($request, $argument));
    }

    public function testSupportWithAttributeSetToClass(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = $this->createArgumentMetadata(QueryDtoWithAttribute::class, []);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertTrue($resolver->supports($request, $argument));
    }

    public function testSupportValidClassAndClassAttribute(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = $this->createArgumentMetadata(ClassDtoWithAttribute::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertTrue($resolver->supports($request, $argument));
    }

    public function testValidation(): void
    {
        $this->expectException(ConstraintViolationException::class);

        /** @var string $data */
        $data = json_encode(
            [
                'float' => 9.99,
                'bool' => true,
            ]
        );

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $iterable = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $iterable->current();
        self::assertInstanceOf(TestDto::class, $dto);
    }

    public function testExpectedFloatProvidedIntStrictTypeChecking(): void
    {
        /** @var string $data */
        $data = json_encode(
            [
                'int' => 5,
                'float' => 9,
                'string' => 'foobar',
                'bool' => true,
                'subType' => [
                    'test' => 'barfoo',
                ],
            ]
        );

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(TestDto::class, $dto);
        self::assertEquals(9, $dto->getFloat());
    }

    public function testStrictTypeMappingForPostRequestBody(): void
    {
        /** @var string $data */
        $data = json_encode(
            [
                'int' => 5,
                'float' => 9.99,
                'string' => 'foobar',
                'bool' => true,
                'subType' => [
                    'test' => 'barfoo',
                ],
            ]
        );

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(TestDto::class, $dto);
        self::assertEquals(5, $dto->getInt());
        self::assertEquals(9.99, $dto->getFloat());
        self::assertEquals('foobar', $dto->getString());
        self::assertEquals(true, $dto->isBool());

        self::assertEquals('barfoo', $dto->getSubType()->getTest());
    }

    public function testSkippingBodyGetRequest(): void
    {
        $this->expectException(ConstraintViolationException::class);

        /** @var string $data */
        $data = json_encode(
            [
                'int' => 5,
                'float' => 9.99,
                'string' => 'foobar',
                'bool' => true,
                'subType' => [
                    'test' => 'barfoo',
                ],
            ]
        );

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_GET);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $generator->current();
    }

    public function testInvalidRequestBodyHandling(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $data = [
            'int' => 5,
            'float' => 9.99,
            'string' => 'foobar',
            'bool' => true,
        ];
        $request = new Request([], [], [], [], [], [], json_encode($data).'foobar');
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);
        $generator->current();
    }

    public function testDuplicateKeyHandling(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $query = [
            'int' => 5,
            'float' => 9.99,
            'string' => 'foobar',
            'bool' => true,
        ];
        $attributes = [
            '_route_params' => [
                'int' => 5,
                'float' => 9.99,
                'string' => 'foobar',
                'bool' => true,
            ],
        ];

        $request = new Request($query, [], $attributes);
        $request->setMethod(Request::METHOD_GET);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $generator->current();
    }

    public function testQueryParameterHandling(): void
    {
        $query = [
            'int' => 5,
            'float' => 9.99,
            'string' => 'foobar',
            'bool' => true,
        ];
        $request = new Request($query);
        $request->setMethod(Request::METHOD_GET);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(TestDto::class, $dto);
        self::assertEquals(5, $dto->getInt());
        self::assertEquals(9.99, $dto->getFloat());
        self::assertEquals('foobar', $dto->getString());
        self::assertEquals(true, $dto->isBool());
    }

    public function testInvalidQueryParameterHandling(): void
    {
        $this->expectException(ConstraintViolationException::class);

        $query = [
            'int' => [
                'subentity' => [1, 2, 3, 4],
            ],
        ];
        $request = new Request($query);
        $request->setMethod(Request::METHOD_GET);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $generator->current();
    }

    public function testRouteParameterHandlingWithNotMatchingTypes(): void
    {
        $attributes = [
            '_route_params' => [
                'int' => 5,
                'float' => 9.99,
                'string' => 'foobar',
                'bool' => true,
            ],
        ];
        $request = new Request([], [], $attributes);
        $argument = $this->createArgumentMetadata(RouteParameterDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var RouteParameterDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(RouteParameterDto::class, $dto);
        self::assertEquals(5, $dto->getInt());
        self::assertEquals(9.99, $dto->getFloat());
        self::assertEquals('foobar', $dto->getString());
        self::assertEquals(true, $dto->isBool());
    }

    public function testRouteParameterHandlingWithStrings(): void
    {
        $attributes = [
            '_route_params' => [
                'int' => '5',
                'float' => '9.99',
                'string' => 'foobar',
                'bool' => true,
            ],
        ];
        $request = new Request([], [], $attributes);
        $request->setMethod(Request::METHOD_GET);
        $argument = $this->createArgumentMetadata(RouteParameterDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var RouteParameterDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(RouteParameterDto::class, $dto);
        self::assertEquals(5, $dto->getInt());
        self::assertEquals(9.99, $dto->getFloat());
        self::assertEquals('foobar', $dto->getString());
        self::assertEquals(true, $dto->isBool());
    }

    public function testInvalidTypeMappingHandling(): void
    {
        $this->expectException(ConstraintViolationException::class);
        $this->expectExceptionMessage(
            'ConstraintViolation: This value should be of type float.'
        );
        /** @var string $data */
        $data = json_encode(
            [
                'int' => 5,
                'float' => 'foobar',
                'string' => 'foobar',
                'bool' => true,
                'subType' => [
                    'test' => 'barfoo',
                ],
            ]
        );
        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);
        $generator->current();
    }

    public function testEmptyBodyHandling(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(EmptyDto::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var EmptyDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(EmptyDto::class, $dto);
    }

    public function testContextAwareProviderCalling(): void
    {
        /** @var string $data */
        $data = json_encode(
            [
                'int' => 5,
                'float' => 9.99,
                'string' => 'foobar',
                'bool' => true,
                'subType' => [
                    'test' => 'barfoo',
                ],
            ]
        );

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);
        $argument = $this->createArgumentMetadata(TestDto::class, [new FromRequest()]);

        $mockProvider1 = $this->createMock(ContextAwareProviderInterface::class);
        $mockProvider1->expects(self::once())->method('supports')->willReturn(true);
        $mockProvider1->expects(self::once())->method('provide');

        $mockProvider2 = $this->createMock(ContextAwareProviderInterface::class);
        $mockProvider2->expects(self::once())->method('supports')->willReturn(false);
        $mockProvider2->expects(self::never())->method('provide');

        $providers = [$mockProvider1, $mockProvider2];

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator(), null, $providers);
        $resolver->resolve($request, $argument)->current();
    }

    /**
     * @param array<mixed> $data
     * @param class-string $dtoClass
     * @param class-string $expectedViolationClass
     *
     * @dataProvider errorTestData
     */
    public function testConstraintViolationErrors(array $data, string $dtoClass, string $expectedViolationClass): void
    {
        /** @var string $data */
        $data = json_encode($data);
        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);

        $argument = $this->createArgumentMetadata($dtoClass, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertTrue($resolver->supports($request, $argument));
        $generator = $resolver->resolve($request, $argument);

        $exception = null;
        try {
            $generator->current();
        } catch (Throwable $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertInstanceOf(ConstraintViolationException::class, $exception);
        $violations = $exception->getConstraintViolationList();

        self::assertCount(1, $violations);
        self::assertInstanceOf($expectedViolationClass, $violations->get(0));
    }

    public function testTypeError(): void
    {
        $request = new Request(['requiredArgument' => null]);
        $request->setMethod(Request::METHOD_GET);

        $argument = $this->createArgumentMetadata(DummyClassA::class, [new FromRequest()]);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertTrue($resolver->supports($request, $argument));
        $generator = $resolver->resolve($request, $argument);

        $exception = null;
        try {
            $generator->current();
        } catch (Throwable $e) {
            $exception = $e;
        }

        self::assertNotNull($exception);
        self::assertInstanceOf(ConstraintViolationException::class, $exception);
        $violations = $exception->getConstraintViolationList();

        self::assertCount(1, $violations);
        self::assertInstanceOf(TypeConstraintViolation::class, $violations->get(0));
    }

    public function errorTestData(): array
    {
        return [
            [
                [],
                DummyClassA::class,
                ArgumentCountConstraintViolation::class,
            ],
            [
                ['requiredArgument' => 'test'],
                DummyClassA::class,
                NotNormalizableValueConstraintViolation::class,
            ],
            [
                ['nonExistingArgument' => 1],
                DummyClassA::class,
                MissingConstructorArgumentsConstraintViolation::class,
            ],
        ];
    }

    private function getDenormalizer(): DenormalizerInterface
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $encoders = [new JsonEncoder()];
        $normalizers = [
            new ProblemNormalizer(),
            new JsonSerializableNormalizer(),
            new DateTimeNormalizer(),
            new ConstraintViolationExceptionNormalizer(new ConstraintViolationListNormalizer()),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new DataUriNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, $extractor),
        ];

        return new Serializer($normalizers, $encoders);
    }

    private function createArgumentMetadata(string $class, array $arguments): ArgumentMetadata
    {
        return new ArgumentMetadata('dto', $class, false, false, null, false, $arguments);
    }

    private function getValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();
    }
}
