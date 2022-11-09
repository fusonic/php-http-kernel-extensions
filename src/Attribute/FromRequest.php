<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Attribute;

use Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_CLASS)]
class FromRequest
{
}
