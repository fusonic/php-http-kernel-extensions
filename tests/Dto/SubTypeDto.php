<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

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
