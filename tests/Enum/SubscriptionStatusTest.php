<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Enum;

use App\Enum\SubscriptionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SubscriptionStatusTest extends TestCase
{
    #[Test]
    public function hasAllExpectedCases(): void
    {
        $cases = SubscriptionStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(SubscriptionStatus::Pending, $cases);
        $this->assertContains(SubscriptionStatus::Success, $cases);
        $this->assertContains(SubscriptionStatus::Unreachable, $cases);
        $this->assertContains(SubscriptionStatus::Invalid, $cases);
        $this->assertContains(SubscriptionStatus::Timeout, $cases);
    }

    #[Test]
    public function hasCorrectStringValues(): void
    {
        $this->assertEquals('pending', SubscriptionStatus::Pending->value);
        $this->assertEquals('success', SubscriptionStatus::Success->value);
        $this->assertEquals('unreachable', SubscriptionStatus::Unreachable->value);
        $this->assertEquals('invalid', SubscriptionStatus::Invalid->value);
        $this->assertEquals('timeout', SubscriptionStatus::Timeout->value);
    }

    #[Test]
    public function canBeCreatedFromString(): void
    {
        $this->assertEquals(SubscriptionStatus::Pending, SubscriptionStatus::from('pending'));
        $this->assertEquals(SubscriptionStatus::Success, SubscriptionStatus::from('success'));
        $this->assertEquals(SubscriptionStatus::Unreachable, SubscriptionStatus::from('unreachable'));
        $this->assertEquals(SubscriptionStatus::Invalid, SubscriptionStatus::from('invalid'));
        $this->assertEquals(SubscriptionStatus::Timeout, SubscriptionStatus::from('timeout'));
    }

    #[Test]
    public function tryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(SubscriptionStatus::tryFrom('invalid_status'));
        $this->assertNull(SubscriptionStatus::tryFrom(''));
    }
}
