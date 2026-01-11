<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Entity\Users;

use App\Entity\Users\UserPreference;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserPreferenceTest extends TestCase
{
    #[Test]
    public function constructorSetsProperties(): void
    {
        $preference = new UserPreference(
            1,
            UserPreference::SHOW_NEXT_UNREAD,
            '1',
        );

        $this->assertNull($preference->getId());
        $this->assertEquals(1, $preference->getUserId());
        $this->assertEquals(
            UserPreference::SHOW_NEXT_UNREAD,
            $preference->getPreferenceKey(),
        );
        $this->assertTrue($preference->isEnabled());
        $this->assertEquals('1', $preference->getValue());
    }

    #[Test]
    public function constructorDefaultsToDisabled(): void
    {
        $preference = new UserPreference(1, UserPreference::SHOW_NEXT_UNREAD);

        $this->assertFalse($preference->isEnabled());
        $this->assertEquals('0', $preference->getValue());
    }

    #[Test]
    public function setEnabledUpdatesValue(): void
    {
        $preference = new UserPreference(
            1,
            UserPreference::SHOW_NEXT_UNREAD,
            '0',
        );

        $result = $preference->setEnabled(true);

        $this->assertTrue($preference->isEnabled());
        $this->assertEquals('1', $preference->getValue());
        $this->assertSame($preference, $result);
    }

    #[Test]
    public function setValueUpdatesValue(): void
    {
        $preference = new UserPreference(1, UserPreference::FILTER_WORDS);

        $result = $preference->setValue("word1\nword2");

        $this->assertEquals("word1\nword2", $preference->getValue());
        $this->assertSame($preference, $result);
    }
}
