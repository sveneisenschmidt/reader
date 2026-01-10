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
            true,
        );

        $this->assertNull($preference->getId());
        $this->assertEquals(1, $preference->getUserId());
        $this->assertEquals(
            UserPreference::SHOW_NEXT_UNREAD,
            $preference->getPreferenceKey(),
        );
        $this->assertTrue($preference->isEnabled());
    }

    #[Test]
    public function constructorDefaultsToDisabled(): void
    {
        $preference = new UserPreference(1, UserPreference::SHOW_NEXT_UNREAD);

        $this->assertFalse($preference->isEnabled());
    }

    #[Test]
    public function setEnabledUpdatesValue(): void
    {
        $preference = new UserPreference(
            1,
            UserPreference::SHOW_NEXT_UNREAD,
            false,
        );

        $result = $preference->setEnabled(true);

        $this->assertTrue($preference->isEnabled());
        $this->assertSame($preference, $result);
    }
}
