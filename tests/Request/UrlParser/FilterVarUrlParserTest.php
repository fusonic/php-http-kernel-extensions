<?php

// Copyright (c) Fusonic GmbH. All rights reserved.
// Licensed under the MIT License. See LICENSE file in the project root for license information.

declare(strict_types=1);

namespace Fusonic\HttpKernelExtensions\Tests\Normalizer;

use Fusonic\HttpKernelExtensions\Request\UrlParser\FilterVarUrlParser;
use PHPUnit\Framework\TestCase;

class FilterVarUrlParserTest extends TestCase
{
    public function testParsing(): void
    {
        $urlParser = new FilterVarUrlParser();

        self::assertSame('test', $urlParser->parseString('test'));
        self::assertSame(1, $urlParser->parseInteger('1'));
        self::assertSame(-1, $urlParser->parseInteger('-1'));
        self::assertSame(true, $urlParser->parseBoolean('true'));
        self::assertSame(true, $urlParser->parseBoolean('On'));
        self::assertSame(true, $urlParser->parseBoolean('1'));
        self::assertSame(false, $urlParser->parseBoolean('0'));
        self::assertSame(0.0, $urlParser->parseFloat('0'));
        self::assertSame(1.11, $urlParser->parseFloat('1.11'));

        // Test invalid values
        self::assertSame(null, $urlParser->parseInteger('test'));
        self::assertSame(null, $urlParser->parseFloat('test'));
        self::assertSame(null, $urlParser->parseBoolean('99'));
    }
}
