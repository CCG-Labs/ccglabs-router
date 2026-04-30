<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router\HTTP;

use CCGLabs\Router\HTTP\Verb;
use PHPUnit\Framework\TestCase;

class VerbTest extends TestCase
{
    public function testsVerbs(): void
    {
        $this->assertContains(Verb::GET, Verb::cases());
        $this->assertContains(Verb::PATCH, Verb::cases());
        $this->assertContains(Verb::POST, Verb::cases());
        $this->assertContains(Verb::PUT, Verb::cases());
        $this->assertContains(Verb::DELETE, Verb::cases());
        $this->assertContains(Verb::HEAD, Verb::cases());
        $this->assertContains(Verb::TRACE, Verb::cases());
        $this->assertContains(Verb::CONNECT, Verb::cases());
    }
}
