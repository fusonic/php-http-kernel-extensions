<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Request\BodyParser;

use Symfony\Component\HttpFoundation\Request;

interface RequestBodyParserInterface
{
    /**
     * @return mixed[]
     */
    public function parse(Request $request): array;
}
