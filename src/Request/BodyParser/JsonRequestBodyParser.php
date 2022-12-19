<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Request\BodyParser;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class JsonRequestBodyParser implements RequestBodyParserInterface
{
    public function __construct(
        /**
         * @var int<1, 512>
         */
        private readonly int $maxJsonDepth = 512
    ) {
    }

    public function parse(Request $request): array
    {
        $content = $request->getContent();

        if ('' === $content) {
            return [];
        }

        try {
            $data = json_decode($content, true, $this->maxJsonDepth, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $ex) {
            throw new BadRequestHttpException('The request body seems to contain invalid json!', $ex);
        }

        if (null === $data) {
            throw new BadRequestHttpException('The request body could not be decoded or has too many hierarchy levels (max '.$this->maxJsonDepth.')!');
        }

        return $data;
    }
}
