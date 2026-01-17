<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Twig;

use App\Domain\User\Entity\User;
use App\Domain\User\Service\UserPreferenceService;
use App\Enum\PreferenceDefault;
use App\Twig\UserPreferenceExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\TwigFunction;

class UserPreferenceExtensionTest extends TestCase
{
    #[Test]
    public function getFunctionsReturnsThreeFunctions(): void
    {
        $extension = $this->createExtension();

        $functions = $extension->getFunctions();

        $this->assertCount(3, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);

        $names = array_map(fn (TwigFunction $f) => $f->getName(), $functions);
        $this->assertContains('user_theme', $names);
        $this->assertContains('user_keyboard_shortcuts', $names);
        $this->assertContains('user_auto_mark_read', $names);
    }

    #[Test]
    public function getThemeReturnsDefaultWhenNoUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $extension = $this->createExtension(security: $security);

        $this->assertEquals(PreferenceDefault::Theme->value(), $extension->getTheme());
    }

    #[Test]
    public function getThemeReturnsUserPreference(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $preferenceService = $this->createMock(UserPreferenceService::class);
        $preferenceService
            ->expects($this->once())
            ->method('getTheme')
            ->with(1)
            ->willReturn('light');

        $extension = $this->createExtension($preferenceService, $security);

        $this->assertEquals('light', $extension->getTheme());
    }

    #[Test]
    public function hasKeyboardShortcutsReturnsDefaultWhenNoUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $extension = $this->createExtension(security: $security);

        $this->assertEquals(
            PreferenceDefault::KeyboardShortcuts->asBool(),
            $extension->hasKeyboardShortcuts(),
        );
    }

    #[Test]
    public function hasKeyboardShortcutsReturnsUserPreference(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $preferenceService = $this->createMock(UserPreferenceService::class);
        $preferenceService
            ->expects($this->once())
            ->method('isKeyboardShortcutsEnabled')
            ->with(1)
            ->willReturn(true);

        $extension = $this->createExtension($preferenceService, $security);

        $this->assertTrue($extension->hasKeyboardShortcuts());
    }

    #[Test]
    public function hasAutoMarkReadReturnsDefaultWhenNoUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $extension = $this->createExtension(security: $security);

        $this->assertEquals(
            PreferenceDefault::AutoMarkRead->asBool(),
            $extension->hasAutoMarkRead(),
        );
    }

    #[Test]
    public function hasAutoMarkReadReturnsUserPreference(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $preferenceService = $this->createMock(UserPreferenceService::class);
        $preferenceService
            ->expects($this->once())
            ->method('isAutoMarkReadEnabled')
            ->with(1)
            ->willReturn(false);

        $extension = $this->createExtension($preferenceService, $security);

        $this->assertFalse($extension->hasAutoMarkRead());
    }

    private function createExtension(
        ?UserPreferenceService $preferenceService = null,
        ?Security $security = null,
    ): UserPreferenceExtension {
        return new UserPreferenceExtension(
            $preferenceService ?? $this->createStub(UserPreferenceService::class),
            $security ?? $this->createStub(Security::class),
        );
    }
}
