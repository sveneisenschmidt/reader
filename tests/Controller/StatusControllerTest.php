<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Tests\Trait\AuthenticatedTestTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatusControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function statusPageRedirectsWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/status');

        $this->assertResponseRedirects();
    }

    #[Test]
    public function statusPageLoads(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/status');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('main#status');
    }

    #[Test]
    public function statusPageShowsSystemStatus(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/status');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main#status', 'Status');
        $this->assertSelectorTextContains('main#status', 'Worker');
        $this->assertSelectorTextContains('main#status', 'Webhook');
    }

    #[Test]
    public function statusPageShowsSubscriptionsTable(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/status');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main#status', 'Subscriptions');
        $this->assertSelectorExists('.status-table');
    }

    #[Test]
    public function statusPageShowsProcessedMessagesTable(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/status');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main#status', 'Processed Messages');
    }

    #[Test]
    public function preferencesPageShowsStatusLink(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/preferences');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/status"]');
    }
}
