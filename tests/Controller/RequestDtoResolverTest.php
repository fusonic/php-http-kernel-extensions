<?php

namespace Fusonic\HttpKernelExtensions\Tests\Controller;

use Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver;
use Fusonic\HttpKernelExtensions\Tests\Dto\EmptyDto;
use Fusonic\HttpKernelExtensions\Tests\Dto\NotADto;
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

class RequestDtoResolverTest extends TestCase
{
    public function testSupportInvalidClass(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = new ArgumentMetadata('routeParameterDto', NotADto::class, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertFalse($resolver->supports($request, $argument));
    }

    public function testSupportOfNotExistingClass(): void
    {
        $request = new Request([], [], ['_route_params' => ['id' => 5]]);
        $argument = new ArgumentMetadata(
            'NotExistingClass',
            'My\Wonderfully\Namespaced\NotExistingClass.php',
            false,
            false,
            null
        );

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
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        self::assertTrue($resolver->supports($request, $argument));
    }

    public function testValidation(): void
    {
        $this->expectException(BadRequestHttpException::class);

        /** @var string $data */
        $data = json_encode(
            [
                'float' => 9.99,
                'bool' => true,
            ]
        );

        $request = new Request([], [], [], [], [], [], $data);
        $request->setMethod(Request::METHOD_POST);
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $iterable = $resolver->resolve($request, $argument);

        /** @var TestDto $dto */
        $dto = $iterable->current();
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
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

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
        $this->expectException(BadRequestHttpException::class);
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
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

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
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);
        $generator->current();
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
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

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
        $this->expectException(BadRequestHttpException::class);
        $query = [
            'int' => [
                'subentity' => [1, 2, 3, 4],
            ],
        ];
        $request = new Request($query);
        $request->setMethod(Request::METHOD_GET);
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

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
        $argument = new ArgumentMetadata('dto', RouteParameterDto::class, false, false, null);

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
        $argument = new ArgumentMetadata('dto', RouteParameterDto::class, false, false, null);

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
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(
            'The type of the "float" attribute for class "Fusonic\HttpKernelExtensions\Tests\Dto\TestDto" must be one of "float" ("string" given).'
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
        $argument = new ArgumentMetadata('dto', TestDto::class, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);
        $generator->current();
    }

    public function testEmptyBodyHandling(): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $argument = new ArgumentMetadata('dto', EmptyDto::class, false, false, null);

        $resolver = new RequestDtoResolver($this->getDenormalizer(), $this->getValidator());
        $generator = $resolver->resolve($request, $argument);

        /** @var EmptyDto $dto */
        $dto = $generator->current();
        self::assertInstanceOf(EmptyDto::class, $dto);
    }

    private function getDenormalizer(): DenormalizerInterface
    {
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $encoders = [new JsonEncoder()];
        $normalizers = [
            new ProblemNormalizer(),
            new JsonSerializableNormalizer(),
            new DateTimeNormalizer(),
            new ConstraintViolationListNormalizer(),
            new DateTimeZoneNormalizer(),
            new DateIntervalNormalizer(),
            new DataUriNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(null, null, null, $extractor),
        ];

        return new Serializer($normalizers, $encoders);
    }

    private function getValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAnnotationMapping()
            ->getValidator();
    }
}
