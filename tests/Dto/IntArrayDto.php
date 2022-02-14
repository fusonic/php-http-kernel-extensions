<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class IntArrayDto
{
    /**
     * @var int[]
     */
    #[Assert\NotNull]
    #[Assert\Valid]
    private array $items;

    /**
     * @param int[] $items
     */
    public function __construct(array $items)
    {
        $this->items = $items;
    }

    /**
     * @return int[]
     */
    public function getItems(): ?array
    {
        return $this->items;
    }
}
