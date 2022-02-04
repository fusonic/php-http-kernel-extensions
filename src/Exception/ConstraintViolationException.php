<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Exception;

use RuntimeException;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Wraps a {@see ConstraintViolationListInterface} into an exception. The intended usage of this exception is
 * to throw this exception with the results of calling `validate` on the
 * validator ({@see \Symfony\Component\Validator\Validator\ValidatorInterface}).
 */
class ConstraintViolationException extends RuntimeException
{
    private ConstraintViolationListInterface $constraintViolationList;

    public const NAME = 'ConstraintViolation';

    public function __construct(ConstraintViolationListInterface $constraintViolationList)
    {
        $this->constraintViolationList = $constraintViolationList;

        $messages = [];

        /** @var ConstraintViolation $constraintViolation */
        foreach ($constraintViolationList as $constraintViolation) {
            $messages[] = $constraintViolation->getMessage();
        }

        parent::__construct(sprintf('%s: %s', self::NAME, implode('; ', $messages)));
    }

    public function getConstraintViolationList(): ConstraintViolationListInterface
    {
        return $this->constraintViolationList;
    }

    public static function fromConstraintViolation(ConstraintViolationInterface $constraintViolation): self
    {
        return new self(new ConstraintViolationList([$constraintViolation]));
    }
}
