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
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
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
    private ?PropertyInfoExtractor $propertyInfoExtractor = null;

    /**
     * @param array<string, RequestBodyParserInterface>|null $requestBodyParsers
     */
    public function __construct(
        ?UrlParserInterface $urlParser = null,

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
        $routeParameters = $this->parseUrlProperties($request->attributes->get('_route_params', []), $className);

        if (in_array($request->getMethod(), self::METHODS_WITH_STRICT_TYPE_CHECKS, true)) {
            return $this->mergeRequestData($this->parseRequestBody($request), $routeParameters);
        }

        return $this->mergeRequestData($this->parseUrlProperties($request->query->all(), $className), $routeParameters);
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
    private function parseUrlProperties(array $params, string $className): array
    {
        $reflectionClass = ReflectionClassCache::getReflectionClass($className);

        foreach ($params as $name => $param) {
            if ($reflectionClass->hasProperty($name)) {
                $property = $reflectionClass->getProperty($name);
                /** @var \ReflectionNamedType|null $propertyType */
                $propertyType = $property->getType();
                /** @var string|class-string|null $type */
                $type = $propertyType?->getName();

                if (null !== $propertyType && null !== $type) {
                    if (in_array($type, Type::$builtinTypes, true)) {
                        if (is_string($param) || is_array($param)) {
                            $params[$name] = $this->parseProperty($className, $name, $type, $propertyType->allowsNull(), $param);
                        }
                    } elseif (class_exists($type)) {
                        $params[$name] = $this->parseUrlProperties($param, $type);
                    }
                }
            }
        }

        return $params;
    }

    /**
     * @param class-string            $className
     * @param array<array-key, mixed> $param
     *
     * @return array<array-key, float|int|bool|string|array<array-key, mixed>|null>
     */
    private function parseArrayProperty(string $className, string $name, array $param): array
    {
        $arrayPropertyTypes = $this->getPropertyInfoExtractor()->getTypes($className, $name);

        if (null === $arrayPropertyTypes) {
            return [];
        }

        $parsedValues = [];

        foreach ($arrayPropertyTypes as $arrayPropertyType) {
            $collectionValueTypes = $arrayPropertyType->getCollectionValueTypes();

            foreach ($collectionValueTypes as $collectionValueType) {
                foreach ($param as $key => $arrayItem) {
                    $parsedValues[$key] = $this->parseProperty($className, $name, $collectionValueType->getBuiltinType(), $collectionValueType->isNullable(), $arrayItem);
                }
            }
        }

        return $parsedValues;
    }

    /**
     * @param class-string $className
     */
    private function parseProperty(string $className, string $name, string $type, bool $isNullable, string|array $param): int|float|bool|string|null|array
    {
        $parsedValue = null;

        if ($isNullable && is_string($param) && $this->urlParser->isNull($param)) {
            return null;
        }

        if (Type::BUILTIN_TYPE_ARRAY !== $type && is_array($param)) {
            $this->urlParser->handleFailure($name, $className, $type, Type::BUILTIN_TYPE_ARRAY);
        } elseif (is_string($param)) {
            if (Type::BUILTIN_TYPE_INT === $type) {
                $parsedValue = $this->urlParser->parseInteger($param);
            } elseif (Type::BUILTIN_TYPE_FLOAT === $type) {
                $parsedValue = $this->urlParser->parseFloat($param);
            } elseif (Type::BUILTIN_TYPE_BOOL === $type) {
                $parsedValue = $this->urlParser->parseBoolean($param);
            } elseif (Type::BUILTIN_TYPE_STRING === $type) {
                $parsedValue = $this->urlParser->parseString($param);
            }
        } elseif (Type::BUILTIN_TYPE_ARRAY === $type) {
            $parsedValue = $this->parseArrayProperty($className, $name, $param);
        }

        if (null === $parsedValue) {
            $this->urlParser->handleFailure($name, $className, $type, is_array($param) ? '[]' : $param);
        }

        return $parsedValue;
    }

    private function getPropertyInfoExtractor(): PropertyInfoExtractor
    {
        if (null === $this->propertyInfoExtractor) {
            $this->propertyInfoExtractor = new PropertyInfoExtractor([],
                [new PhpDocExtractor(), new ReflectionExtractor()]);
        }

        return $this->propertyInfoExtractor;
    }
}
