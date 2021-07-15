<?php

namespace Fusonic\HttpKernelExtensions\Tests\Dto;

use Fusonic\HttpKernelExtensions\Attribute\FromRequest;
use Symfony\Component\Validator\Constraints as Assert;

#[FromRequest]
class ClassDtoWithAttribute
{
    #[Assert\NotNull]
    private int $int;

    #[Assert\NotNull]
    private string $string;
    private string $float;
    private int $bool;

    #[Assert\Valid]
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

    public function getFloat(): string
    {
        return $this->float;
    }

    public function setFloat(string $float): void
    {
        $this->float = $float;
    }

    public function isBool(): int
    {
        return $this->bool;
    }

    public function setBool(int $bool): void
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
