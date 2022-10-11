<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Request\UrlParser;

interface UrlParserInterface
{
    public function parseInteger(string $value): ?int;

    public function parseFloat(string $value): ?float;

    public function parseBoolean(string $value): ?bool;

    public function parseString(string $value): ?string;
}
