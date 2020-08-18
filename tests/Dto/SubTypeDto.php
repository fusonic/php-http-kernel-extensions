<?php

namespace Fusonic\HttpKernelExtensions\Tests\Dto;

class SubTypeDto
{
    public string $test = '';

    public function getTest(): string
    {
        return $this->test;
    }

    public function setTest(string $test): void
    {
        $this->test = $test;
    }
}
