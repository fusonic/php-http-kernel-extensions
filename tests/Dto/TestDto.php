<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

namespace Fusonic\HttpKernelExtensions\Tests\Dto;

use Fusonic\HttpKernelExtensions\Dto\RequestDto;
use Symfony\Component\Validator\Constraints as Assert;

class TestDto implements RequestDto
{
    /**
     * @Assert\NotNull
     */
    private int $int;

    /**
     * @Assert\NotNull
     */
    private string $string;
    private float $float;
    private bool $bool;

    /**
     * @Assert\Valid
     */
    private SubTypeDto $subType;

    private string $notSetValue;

    public function getInt(): int
    {
        return $this->int;
    }

    public function setInt(int $int): void
    {
        $this->int = $int;
    }

    public function getString(): string
    {
        return $this->string;
    }

    public function setString(string $string): void
    {
        $this->string = $string;
    }

    public function getFloat(): float
    {
        return $this->float;
    }

    public function setFloat(float $float): void
    {
        $this->float = $float;
    }

    public function isBool(): bool
    {
        return $this->bool;
    }

    public function setBool(bool $bool): void
    {
        $this->bool = $bool;
    }

    public function getNotSetValue(): ?string
    {
        return $this->notSetValue;
    }

    public function getSubType(): SubTypeDto
    {
        return $this->subType;
    }

    public function setSubType(SubTypeDto $subType): void
    {
        $this->subType = $subType;
    }
}
