<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\ErrorHandler;

use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

interface ErrorHandlerInterface
{
    /**
     * If no object can be created from the given class and request no further processing is possible and an exception
     * has to be thrown. The request data and the Dto class name are passed here. These can be used to determine
     * some missing details. For example: the {@see \Symfony\Component\Serializer\Exception\InvalidArgumentException}
     * class does not provide any info about the possible 'property path'. With the given data and the class name
     * reflection can be used to figure it out.
     *
     * @param array<string, mixed> $data
     * @param class-string         $className
     */
    public function handleDenormalizeError(Throwable $ex, array $data, string $className): Throwable;

    /**
     * If only constraint violations are discovered but the object could be created, the choice what to do is the devs.
     */
    public function handleConstraintViolations(ConstraintViolationListInterface $list): void;
}
