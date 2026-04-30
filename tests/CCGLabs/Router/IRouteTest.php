<?php

/**
 * This file contains Tests\CCGLabs\Router\IRouteTest
 *
 * Copyright 2025 Brian Reich
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the “Software”), to deal in the
 * Software without restriction, including without limitation the rights to use, copy,
 * modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the
 * following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 * OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author Brian Reich <brian@brianreich.dev>
 * @copyright Copyright (C) 2025 Brian Reich
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\CCGLabs\Router;

use CCGLabs\Router\IRoute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IRouteTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(IRoute::class));
    }

    public function testInterfaceHasMatchesMethod(): void
    {
        $reflectionClass = new ReflectionClass(IRoute::class);
        $this->assertTrue($reflectionClass->hasMethod('matches'));

        $reflectionMethod = $reflectionClass->getMethod('matches');
        $returnType = $reflectionMethod->getReturnType();
        $this->assertEquals('bool', (string) $returnType);

        $this->assertEquals(1, $reflectionMethod->getNumberOfParameters());
        $reflectionParameters = $reflectionMethod->getParameters();
        $this->assertEquals('path', $reflectionParameters[0]->getName());
        $this->assertEquals('string', $reflectionParameters[0]->getType());
    }
}
