<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ArrayDto
{
    /**
     * @var DummyClassA[]
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    private array $items;

    /**
     * @param DummyClassA[] $items
     */
    public function __construct(private int $requiredArgument, array $items)
    {
        $this->items = $items;
    }

    public function getRequiredArgument(): int
    {
        return $this->requiredArgument;
    }

    /**
     * @return DummyClassA[]
     */
    public function getItems(): ?array
    {
        return $this->items;
    }
}
