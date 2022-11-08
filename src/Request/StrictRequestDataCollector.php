<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Request;

use Fusonic\HttpKernelExtensions\Cache\ReflectionClassCache;
use Fusonic\HttpKernelExtensions\Controller\RequestDtoResolver;
use Fusonic\HttpKernelExtensions\Request\BodyParser\FormRequestBodyParser;
use Fusonic\HttpKernelExtensions\Request\BodyParser\JsonRequestBodyParser;
use Fusonic\HttpKernelExtensions\Request\BodyParser\RequestBodyParserInterface;
use Fusonic\HttpKernelExtensions\Request\UrlParser\FilterVarUrlParser;
use Fusonic\HttpKernelExtensions\Request\UrlParser\UrlParserInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class StrictRequestDataCollector implements RequestDataCollectorInterface
{
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

        if (in_array($request->getMethod(), RequestDtoResolver::METHODS_WITH_STRICT_TYPE_CHECKS, true)) {
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
    protected function mergeRequestData(array $data, array $routeParameters): array
    {
        if (count($keys = array_intersect_key($data, $routeParameters)) > 0) {
            throw new BadRequestHttpException(sprintf('Parameters (%s) used as route attributes can not be used in the request body or query parameters.', implode(', ', array_keys($keys))));
        }

        return array_merge($data, $routeParameters);
    }

    /**
     * @return mixed[]
     */
    protected function parseRequestBody(Request $request): array
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
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function parseProperties(array $params, string $className): array
    {
        $reflectionClass = ReflectionClassCache::getReflectionClass($className);

        foreach ($params as $key => $param) {
            if ($reflectionClass->hasProperty($key) && is_string($param)) {
                $property = $reflectionClass->getProperty($key);
                /** @var \ReflectionNamedType|null $propertyType */
                $propertyType = $property->getType();
                $type = $propertyType?->getName();

                if (null !== $type) {
                    $params[$key] = match ($type) {
                        'int' => $this->urlParser->parseInteger($param),
                        'float' => $this->urlParser->parseFloat($param),
                        'bool' => $this->urlParser->parseBoolean($param),
                        default => $this->urlParser->parseString($param)
                    };
                }
            }
        }

        return $params;
    }
}
