<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

class DummyClassB
{
    private int $someProperty;

    public function __construct(public int $requiredArgument, private string $secondArgument)
    {
    }

    public function getSecondArgument(): string
    {
        return $this->secondArgument;
    }

    public function getSomeProperty(): int
    {
        return $this->someProperty;
    }

    public function setSomeProperty(int $someProperty): void
    {
        $this->someProperty = $someProperty;
    }
}
