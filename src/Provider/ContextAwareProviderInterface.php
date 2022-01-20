<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Provider;

interface ContextAwareProviderInterface
{
    public function supports(object $dto): bool;

    public function provide(object $dto): void;
}
