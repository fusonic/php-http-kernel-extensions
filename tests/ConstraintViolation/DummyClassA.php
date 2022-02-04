<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\ConstraintViolation;

class DummyClassA
{
    public function __construct(private int $requiredArgument)
    {
    }

    public function getRequiredArgument(): int
    {
        return $this->requiredArgument;
    }
}
