<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Controller;

use Fusonic\HttpKernelExtensions\Attribute\FromRequest;
use Fusonic\HttpKernelExtensions\ErrorHandler\ErrorHandler;
use Fusonic\HttpKernelExtensions\ErrorHandler\ErrorHandlerInterface;
use Generator;
use ReflectionAttribute;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestDtoResolver implements ArgumentValueResolverInterface
{
    private const MAX_JSON_DEPTH = 512;
    private const METHODS_WITH_STRICT_TYPE_CHECKS = [
        Request::METHOD_PUT,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PATCH,
    ];

    private ErrorHandlerInterface $errorHandler;

    public function __construct(private DenormalizerInterface $serializer, private ValidatorInterface $validator, ?ErrorHandlerInterface $errorHandler = null)
    {
        $this->errorHandler = $errorHandler ?? new ErrorHandler();
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $this->isSupportedArgument($argument);
    }

    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        if (!$this->isSupportedArgument($argument)) {
            throw new \LogicException('The parameter has to have the attribute .'.FromRequest::class.'! This should have been check in the supports function!');
        }

        $routeParameters = $this->getRouteParams($request);

        if (in_array($request->getMethod(), self::METHODS_WITH_STRICT_TYPE_CHECKS, true)) {
            $options = [];
            $data = $this->mergeRequestData($this->getRequestContent($request), $routeParameters);
        } else {
            $options = [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];
            $data = $this->mergeRequestData($this->getRequestQueries($request), $routeParameters);
        }

        /** @var string $clazz */
        $clazz = $argument->getType();
        $dto = $this->denormalize($data, $clazz, $options);
        $this->validate($dto);

        yield $dto;
    }

    private function isSupportedArgument(ArgumentMetadata $argument): bool
    {
        // no type and non existent classes should be ignored
        if (!is_string($argument->getType()) || '' === $argument->getType() || !class_exists($argument->getType())) {
            return false;
        }

        // attribute via parameter
        if ($argument->getAttribute() instanceof FromRequest) {
            return true;
        }

        // attribute via class
        $class = new ReflectionClass($argument->getType());
        $attributes = $class->getAttributes(FromRequest::class, ReflectionAttribute::IS_INSTANCEOF);
        if (count($attributes) > 0) {
            return true;
        }

        return false;
    }

    private function getRequestContent(Request $request): array
    {
        $content = $request->getContent();
        if (!is_string($content) || '' === $content) {
            return [];
        }

        try {
            $data = json_decode($content, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            throw new BadRequestHttpException('The request body seems to contain invalid json!', $ex);
        }

        if (null === $data) {
            throw new BadRequestHttpException('The request body could not be decoded or has too many hierarchy levels (max '.self::MAX_JSON_DEPTH.')!');
        }

        return $data;
    }

    private function getRequestQueries(Request $request): array
    {
        return $request->query->all();
    }

    private function getRouteParams(Request $request): array
    {
        $params = $request->attributes->get('_route_params', []);

        foreach ($params as $key => $param) {
            $value = filter_var($param, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
            $params[$key] = $value ?? $param;
        }

        return $params;
    }

    private function denormalize(array $data, string $class, array $options): object
    {
        try {
            if ($data) {
                $dto = $this->serializer->denormalize($data, $class, JsonEncoder::FORMAT, $options);
            } else {
                $dto = new $class();
            }

            return $dto;
        } catch (ExceptionInterface $ex) {
            throw $this->errorHandler->handleDenormalizeError($ex);
        }
    }

    private function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            $this->errorHandler->handleConstraintViolations($violations);
        }
    }

    private function mergeRequestData(array $data, array $routeParameters): array
    {
        if (count($keys = array_intersect_key($data, $routeParameters)) > 0) {
            throw new BadRequestHttpException(sprintf('Parameters (%s) used as route attributes can not be used in the request body or query parameters.', implode(', ', array_keys($keys))));
        }

        return array_merge($data, $routeParameters);
    }
}
