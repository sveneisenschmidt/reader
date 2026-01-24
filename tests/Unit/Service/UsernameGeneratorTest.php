<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service;

use App\Domain\User\Service\UsernameGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UsernameGeneratorTest extends TestCase
{
    private UsernameGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new UsernameGenerator();
    }

    #[Test]
    public function generateReturnsString(): void
    {
        $username = $this->generator->generate();

        $this->assertIsString($username);
        $this->assertNotEmpty($username);
    }

    #[Test]
    public function generateReturnsTwoWords(): void
    {
        $username = $this->generator->generate();

        $parts = explode(' ', $username);
        $this->assertCount(2, $parts);
    }

    #[Test]
    public function generateReturnsTitleCase(): void
    {
        $username = $this->generator->generate();

        $parts = explode(' ', $username);
        foreach ($parts as $part) {
            $this->assertMatchesRegularExpression('/^[A-Z][a-z]+$/', $part);
        }
    }

    #[Test]
    public function generateReturnsRandomResults(): void
    {
        $results = [];
        for ($i = 0; $i < 10; ++$i) {
            $results[] = $this->generator->generate();
        }

        $uniqueResults = array_unique($results);
        $this->assertGreaterThan(1, count($uniqueResults));
    }
}
