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
        self::assertTrue($urlParser->parseBoolean('true'));
        self::assertTrue($urlParser->parseBoolean('On'));
        self::assertTrue($urlParser->parseBoolean('1'));
        self::assertFalse($urlParser->parseBoolean('0'));
        self::assertSame(0.0, $urlParser->parseFloat('0'));
        self::assertSame(1.11, $urlParser->parseFloat('1.11'));

        // Test invalid values
        self::assertNull($urlParser->parseInteger('test'));
        self::assertNull($urlParser->parseFloat('test'));
        self::assertNull($urlParser->parseBoolean('99'));
    }
}
