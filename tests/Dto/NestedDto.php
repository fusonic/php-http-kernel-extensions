<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class NestedDto
{
    #[Assert\NotNull]
    #[Assert\Valid]
    private DummyClassA $objectArgument;

    public function __construct(DummyClassA $objectArgument)
    {
        $this->objectArgument = $objectArgument;
    }

    public function getObjectArgument(): DummyClassA
    {
        return $this->objectArgument;
    }
}
