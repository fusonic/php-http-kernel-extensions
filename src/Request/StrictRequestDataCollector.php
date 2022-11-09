<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Request;

use Fusonic\HttpKernelExtensions\Cache\ReflectionClassCache;
use Fusonic\HttpKernelExtensions\Request\BodyParser\FormRequestBodyParser;
use Fusonic\HttpKernelExtensions\Request\BodyParser\JsonRequestBodyParser;
use Fusonic\HttpKernelExtensions\Request\BodyParser\RequestBodyParserInterface;
use Fusonic\HttpKernelExtensions\Request\UrlParser\FilterVarUrlParser;
use Fusonic\HttpKernelExtensions\Request\UrlParser\UrlParserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyInfo\Type;

final class StrictRequestDataCollector implements RequestDataCollectorInterface
{
    public const METHODS_WITH_STRICT_TYPE_CHECKS = [
        Request::METHOD_PUT,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PATCH,
    ];

    /**
     * @var array<string, RequestBodyParserInterface>
     */
    private readonly array $requestBodyParsers;

    private readonly UrlParserInterface $urlParser;

    public function __construct(
        ?UrlParserInterface $urlParser = null,

        /*
         * @var array<string, RequestBodyParserInterface>|null
         */
        ?array $requestBodyParsers = null,
    ) {
        $this->urlParser = $urlParser ?? new FilterVarUrlParser();

        $this->requestBodyParsers = $requestBodyParsers ?? [
            'json' => new JsonRequestBodyParser(),
            'default' => new FormRequestBodyParser(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function collect(Request $request, string $className): array
    {
        $routeParameters = $this->parseProperties($request->attributes->get('_route_params', []), $className);

        if (in_array($request->getMethod(), self::METHODS_WITH_STRICT_TYPE_CHECKS, true)) {
            return $this->mergeRequestData($this->parseRequestBody($request), $routeParameters);
        }

        return $this->mergeRequestData($this->parseProperties($request->query->all(), $className), $routeParameters);
    }

    /**
     * @param array<mixed>         $data
     * @param array<string, mixed> $routeParameters
     *
     * @return array<string, mixed>
     */
    private function mergeRequestData(array $data, array $routeParameters): array
    {
        if (count($keys = array_intersect_key($data, $routeParameters)) > 0) {
            throw new BadRequestHttpException(sprintf('Parameters (%s) used as route attributes can not be used in the request body or query parameters.', implode(', ', array_keys($keys))));
        }

        return array_merge($data, $routeParameters);
    }

    /**
     * @return mixed[]
     */
    private function parseRequestBody(Request $request): array
    {
        $requestBodyParser = $this->requestBodyParsers[$request->getContentType()] ?? null;

        if (null === $requestBodyParser) {
            $requestBodyParser = $this->requestBodyParsers['default'];
        }

        return $requestBodyParser->parse($request);
    }

    /**
     * Parse the string properties of the appropriate types based on the types in the class. Since route parameters
     * and query parameters always come in as strings.
     *
     * @param class-string         $className
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function parseProperties(array $params, string $className): array
    {
        $reflectionClass = ReflectionClassCache::getReflectionClass($className);

        foreach ($params as $name => $param) {
            if ($reflectionClass->hasProperty($name) && is_string($param)) {
                $property = $reflectionClass->getProperty($name);
                /** @var \ReflectionNamedType|null $propertyType */
                $propertyType = $property->getType();
                $type = $propertyType?->getName();

                if (null !== $propertyType && null !== $type) {
                    if ($propertyType->allowsNull() && $this->urlParser->isNull($param)) {
                        $params[$name] = null;
                    } elseif (Type::BUILTIN_TYPE_INT === $type) {
                        $value = $this->urlParser->parseInteger($param);

                        if (null === $value) {
                            $this->urlParser->handleFailure($name, $className, Type::BUILTIN_TYPE_INT, $param);
                        }

                        $params[$name] = $value;
                    } elseif (Type::BUILTIN_TYPE_FLOAT === $type) {
                        $value = $this->urlParser->parseFloat($param);

                        if (null === $value) {
                            $this->urlParser->handleFailure($name, $className, Type::BUILTIN_TYPE_FLOAT, $param);
                        }

                        $params[$name] = $value;
                    } elseif (Type::BUILTIN_TYPE_BOOL === $type) {
                        $value = $this->urlParser->parseBoolean($param);

                        if (null === $value) {
                            $this->urlParser->handleFailure($name, $className, Type::BUILTIN_TYPE_BOOL, $param);
                        }

                        $params[$name] = $value;
                    } else {
                        $params[$name] = $this->urlParser->parseString($param);
                    }
                }
            }
        }

        return $params;
    }
}
