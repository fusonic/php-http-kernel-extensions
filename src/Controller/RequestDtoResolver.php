<?php

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Controller;

use Fusonic\HttpKernelExtensions\Dto\RequestDto;
use Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestDtoResolver implements ArgumentValueResolverInterface
{
    private const MAX_JSON_DEPTH = 512;
    private DenormalizerInterface $serializer;
    private ValidatorInterface $validator;
    private const METHODS_WITH_STRICT_TYPE_CHECKS = [
        Request::METHOD_PUT,
        Request::METHOD_POST,
        Request::METHOD_DELETE,
        Request::METHOD_PATCH,
    ];

    public function __construct(DenormalizerInterface $serializer, ValidatorInterface $validator)
    {
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        if (!is_string($argument->getType()) || '' === $argument->getType()) {
            return false;
        }

        $interfaces = class_implements($argument->getType());

        return in_array(RequestDto::class, $interfaces, true);
    }

    public function resolve(Request $request, ArgumentMetadata $argument): Generator
    {
        $class = $argument->getType();
        if (!is_string($class) || '' === $class) {
            throw new \LogicException('The argument type should be a class which implements .'.RequestDto::class.' interface! This should have been already check in the supports function!');
        }

        $routeParameters = $this->getRouteParams($request);

        if (in_array($request->getMethod(), self::METHODS_WITH_STRICT_TYPE_CHECKS, true)) {
            $options = [AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true];
            $content = $this->getRequestContent($request);
            $data = array_merge($content, $routeParameters);
        } else {
            $options = [];
            $queries = $this->getRequestQueries($request);
            $data = array_merge($queries, $routeParameters);
        }

        $dto = $this->denormalize($data, $class, $options);
        $this->validate($dto);

        yield $dto;
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

    private function denormalize(array $data, string $class, array $options): RequestDto
    {
        try {
            if ($data) {
                $dto = $this->serializer->denormalize($data, $class, null, $options);
            } else {
                $dto = new $class();
            }

            return $dto;
        } catch (NotNormalizableValueException $ex) {
            throw new BadRequestHttpException($ex->getMessage());
        }
    }

    private function validate(RequestDto $dto): void
    {
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            $details = '';
            /** @var ConstraintViolation $violation */
            foreach ($violations as $violation) {
                $details .= $violation->getPropertyPath().': '.$violation->getMessage().PHP_EOL;
            }

            throw new BadRequestHttpException('The request payload is invalid!'.PHP_EOL.$details);
        }
    }
}
