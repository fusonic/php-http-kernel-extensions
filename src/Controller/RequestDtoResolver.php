<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Controller;

use Fusonic\HttpKernelExtensions\Attribute\FromRequest;
use Fusonic\HttpKernelExtensions\ErrorHandler\ConstraintViolationErrorHandler;
use Fusonic\HttpKernelExtensions\ErrorHandler\ErrorHandlerInterface;
use Fusonic\HttpKernelExtensions\Provider\ContextAwareProviderInterface;
use Fusonic\HttpKernelExtensions\Request\RequestDataCollectorInterface;
use Fusonic\HttpKernelExtensions\Request\StrictRequestDataCollector;
use Generator;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Throwable;

final class RequestDtoResolver implements ArgumentValueResolverInterface
{
    public const METHODS_WITH_STRICT_TYPE_CHECKS = [
        Request::METHOD_PUT,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PATCH,
    ];

    /**
     * @var ContextAwareProviderInterface[]
     */
    private array $providers = [];
    private ErrorHandlerInterface $errorHandler;
    private RequestDataCollectorInterface $modelDataParser;

    public function __construct(
        private readonly DenormalizerInterface $serializer,
        private readonly ValidatorInterface $validator,
        ?ErrorHandlerInterface $errorHandler = null,
        iterable $providers = [],
        ?RequestDataCollectorInterface $modelDataParser = null,
    ) {
        $this->errorHandler = $errorHandler ?? new ConstraintViolationErrorHandler();
        $this->modelDataParser = $modelDataParser ?? new StrictRequestDataCollector();

        foreach ($providers as $provider) {
            if ($provider instanceof ContextAwareProviderInterface) {
                $this->providers[] = $provider;
            } else {
                throw new InvalidArgumentException(sprintf('Given $providers must be instance of `%s`.', ContextAwareProviderInterface::class));
            }
        }
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $this->isSupportedArgument($argument);
    }

    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        if (!$this->isSupportedArgument($argument)) {
            throw new InvalidArgumentException('The parameter has to have the attribute .'.FromRequest::class.'! This should have been checked in the supports function!');
        }

        $data = $this->modelDataParser->collect($request);

        if (in_array($request->getMethod(), self::METHODS_WITH_STRICT_TYPE_CHECKS, true)) {
            $options = [];
        } else {
            $options = [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];
        }

        /** @var class-string $className */
        $className = $argument->getType();
        $dto = $this->denormalize($data, $className, $options);
        $this->applyProviders($dto);
        $this->validate($dto);

        yield $dto;
    }

    private function applyProviders(object $dto): void
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($dto)) {
                $provider->provide($dto);
            }
        }
    }

    private function isSupportedArgument(ArgumentMetadata $argument): bool
    {
        // no type and nonexistent classes should be ignored
        if (!is_string($argument->getType()) || '' === $argument->getType() || !class_exists($argument->getType())) {
            return false;
        }

        // attribute via parameter
        if (count($argument->getAttributes(FromRequest::class)) > 0) {
            return true;
        }

        // attribute via class
        $class = new ReflectionClass($argument->getType());
        $attributes = $class->getAttributes(FromRequest::class, ReflectionAttribute::IS_INSTANCEOF);

        return count($attributes) > 0;
    }

    /**
     * @param array<mixed>         $data
     * @param class-string         $class
     * @param array<string, mixed> $options
     */
    private function denormalize(array $data, string $class, array $options): object
    {
        try {
            if (count($data) > 0) {
                $dto = $this->serializer->denormalize($data, $class, JsonEncoder::FORMAT, $options);
            } else {
                $dto = new $class();
            }

            return $dto;
        } catch (Throwable $ex) {
            throw $this->errorHandler->handleDenormalizeError($ex, $data, $class);
        }
    }

    private function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            $this->errorHandler->handleConstraintViolations($violations);
        }
    }
}
