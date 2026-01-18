<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Controller;

use App\Domain\Feed\Repository\SubscriptionRepository;
use App\Tests\Trait\AuthenticatedTestTrait;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SubscriptionControllerTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    #[Test]
    public function subscriptionsPageRedirectsWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/subscriptions');

        $this->assertResponseRedirects();
    }

    #[Test]
    public function subscriptionsPageLoads(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
    }

    #[Test]
    public function subscriptionsPageShowsForm(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="subscriptions[new][url]"]');
    }

    #[Test]
    public function subscriptionsFormHasCsrfProtection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/subscriptions');

        $this->assertSelectorExists('input[name="subscriptions[_token]"]');
    }

    #[Test]
    public function subscriptionsFormHasSubscribeButton(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button[name="subscriptions[add]"]');
    }

    #[Test]
    public function subscriptionsPageShowsExistingSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.page-section--bordered');
        $this->assertSelectorExists(
            'button[name="subscriptions[existing][0][save]"]',
        );
    }

    #[Test]
    public function subscriptionsPageShowsRemoveButtonForExistingFeeds(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(
            'button[name="subscriptions[existing][0][remove]"]',
        );
    }

    #[Test]
    public function addingNewFeedShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['subscriptions[new][url]'] = 'https://example.com/new-feed.xml';

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorExists('p.flash-success');
        $this->assertSelectorTextContains('p.flash-success', 'Feed added');
    }

    #[Test]
    public function addingDuplicateFeedShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['subscriptions[new][url]'] = 'https://example.com/feed.xml';

        $client->submit($form);

        $this->assertSelectorExists('p.form-error');
        $this->assertSelectorTextContains('p.form-error', 'already subscribed');
    }

    #[Test]
    public function removingFeedShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Remove')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorExists('p.flash-success');
        $this->assertSelectorTextContains('p.flash-success', 'Feed removed');
    }

    #[Test]
    public function updatingFeedNameShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Update')->form();
        $form['subscriptions[existing][0][name]'] = 'Updated Feed Name';

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorExists('p.flash-success');
        $this->assertSelectorTextContains('p.flash-success', 'Feed updated');
    }

    #[Test]
    public function existingSubscriptionShowsFeedUrl(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            '.subscription-item__url',
            'https://example.com/feed.xml',
        );
    }

    #[Test]
    public function addingNewFeedSetsRefreshTimestamp(): void
    {
        $client = static::createClient();
        $user = $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['subscriptions[new][url]'] = 'https://example.com/new-feed.xml';

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');

        // Verify the subscription has a refresh timestamp
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );
        $subscriptions = $subscriptionRepository->findByUserId($user->getId());

        $this->assertCount(1, $subscriptions);
        $this->assertNotNull(
            $subscriptions[0]->getLastRefreshedAt(),
            'New subscription should have refresh timestamp set',
        );
    }

    #[Test]
    public function addingInvalidFeedShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Subscribe')->form();
        $form['subscriptions[new][url]'] =
            'https://example.com/invalid-feed.xml';

        $client->submit($form);

        $this->assertSelectorExists('p.form-error');
    }

    #[Test]
    public function subscriptionFormShowsArchiveIsCheckbox(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $client->request('GET', '/subscriptions');

        $this->assertSelectorExists('input[name*="useArchiveIs"]');
    }

    #[Test]
    public function updatingUseArchiveIsShowsSuccessMessage(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Update')->form();
        $form['subscriptions[existing][0][useArchiveIs]']->tick();

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorExists('p.flash-success');
    }

    #[Test]
    public function useArchiveIsSettingIsPersisted(): void
    {
        $client = static::createClient();
        $user = $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $subscription = $this->createTestSubscription();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Update')->form();
        $form['subscriptions[existing][0][useArchiveIs]']->tick();

        $client->submit($form);

        // Verify the setting was persisted
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );
        $updatedSubscription = $subscriptionRepository->findBySubscriptionGuid(
            $subscription->getGuid(),
        );

        $this->assertTrue($updatedSubscription->getUseArchiveIs());
    }

    #[Test]
    public function subscriptionsPageShowsImportExportSection(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[name="subscriptions[opml]"]');
        $this->assertSelectorExists('button[name="subscriptions[import]"]');

        // Check for Import / Export heading
        $headings = $crawler->filter('h2')->extract(['_text']);
        $this->assertContains('Import / Export', $headings);
    }

    #[Test]
    public function subscriptionsPageShowsExportLink(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);

        $crawler = $client->request('GET', '/subscriptions');

        $this->assertResponseIsSuccessful();
        $exportLink = $crawler->filter('a[href="/subscriptions/export"]');
        $this->assertCount(1, $exportLink);
    }

    #[Test]
    public function exportReturnsOpmlFile(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $client->request('GET', '/subscriptions/export');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/xml');
        $this->assertStringContainsString(
            'attachment; filename="reader-subscriptions.opml"',
            $client->getResponse()->headers->get('Content-Disposition'),
        );
    }

    #[Test]
    public function exportContainsSubscriptions(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $client->request('GET', '/subscriptions/export');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString(
            'https://example.com/feed.xml',
            $content,
        );
        $this->assertStringContainsString('Test Feed', $content);
    }

    #[Test]
    public function exportRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/subscriptions/export');

        $this->assertResponseRedirects();
    }

    #[Test]
    public function importWithoutFileShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Import')->form();
        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorTextContains('.flash-error', 'No file uploaded');
    }

    #[Test]
    public function importWithEmptyOpmlShowsError(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $opmlContent =
            '<?xml version="1.0"?><opml version="2.0"><head></head><body></body></opml>';
        $tempFile = tempnam(sys_get_temp_dir(), 'opml');
        file_put_contents($tempFile, $opmlContent);

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Import')->form();
        $form['subscriptions[opml]']->upload($tempFile);

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorTextContains('.flash-error', 'No feeds found');

        unlink($tempFile);
    }

    #[Test]
    public function importValidOpmlImportsFeeds(): void
    {
        $client = static::createClient();
        $user = $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $opmlContent = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline type="rss" text="Example Feed" xmlUrl="https://example.com/feed.xml"/>
                </body>
            </opml>';
        $tempFile = tempnam(sys_get_temp_dir(), 'opml');
        file_put_contents($tempFile, $opmlContent);

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Import')->form();
        $form['subscriptions[opml]']->upload($tempFile);

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'imported');

        // Verify subscription was created
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );
        $subscriptions = $subscriptionRepository->findByUserId($user->getId());

        $this->assertCount(1, $subscriptions);

        unlink($tempFile);
    }

    #[Test]
    public function importSkipsDuplicateFeeds(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();
        $this->createTestSubscription();

        $opmlContent = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline type="rss" text="Existing Feed" xmlUrl="https://example.com/feed.xml"/>
                </body>
            </opml>';
        $tempFile = tempnam(sys_get_temp_dir(), 'opml');
        file_put_contents($tempFile, $opmlContent);

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Import')->form();
        $form['subscriptions[opml]']->upload($tempFile);

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'skipped');

        unlink($tempFile);
    }

    #[Test]
    public function importWithFolderSetsFolderOnSubscription(): void
    {
        $client = static::createClient();
        $user = $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $opmlContent = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline text="Tech">
                        <outline type="rss" text="Tech Feed" xmlUrl="https://example.com/feed.xml"/>
                    </outline>
                </body>
            </opml>';
        $tempFile = tempnam(sys_get_temp_dir(), 'opml');
        file_put_contents($tempFile, $opmlContent);

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Import')->form();
        $form['subscriptions[opml]']->upload($tempFile);

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');

        // Verify folder was set
        $container = static::getContainer();
        $subscriptionRepository = $container->get(
            SubscriptionRepository::class,
        );
        $subscriptions = $subscriptionRepository->findByUserId($user->getId());

        $this->assertCount(1, $subscriptions);
        $this->assertSame('Tech', $subscriptions[0]->getFolder());

        unlink($tempFile);
    }

    #[Test]
    public function importSkipsInvalidFeedUrls(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $opmlContent = '<?xml version="1.0"?>
            <opml version="2.0">
                <head><title>Test</title></head>
                <body>
                    <outline type="rss" text="Invalid Feed" xmlUrl="https://example.com/invalid-feed.xml"/>
                </body>
            </opml>';
        $tempFile = tempnam(sys_get_temp_dir(), 'opml');
        file_put_contents($tempFile, $opmlContent);

        $crawler = $client->request('GET', '/subscriptions');

        $form = $crawler->selectButton('Import')->form();
        $form['subscriptions[opml]']->upload($tempFile);

        $client->submit($form);

        $this->assertResponseRedirects('/subscriptions');
        $client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'skipped');

        unlink($tempFile);
    }

    #[Test]
    public function exportWithEmptySubscriptionsReturnsValidOpml(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        $client->request('GET', '/subscriptions/export');

        $content = $client->getResponse()->getContent();
        $this->assertStringContainsString(
            '<?xml version="1.0" encoding="UTF-8"?>',
            $content,
        );
        $this->assertStringContainsString('<opml version="2.0">', $content);
        $this->assertStringContainsString('Reader Subscriptions', $content);
    }

    #[Test]
    public function exportGroupsSubscriptionsByFolder(): void
    {
        $client = static::createClient();
        $this->loginAsTestUser($client);
        $this->deleteAllSubscriptionsForTestUser();

        // Create subscription with folder
        $subscription = $this->createTestSubscription();
        $container = static::getContainer();
        $subscriptionService = $container->get(
            \App\Domain\Feed\Service\SubscriptionService::class,
        );
        $subscriptionService->updateSubscription(
            $subscription->getUserId(),
            $subscription->getGuid(),
            'Test Feed',
            'Tech',
            false,
        );

        $client->request('GET', '/subscriptions/export');

        $content = $client->getResponse()->getContent();
        $xml = new \SimpleXMLElement($content);

        // Should have folder outline containing the feed
        $folderOutline = $xml->body->outline[0];
        $this->assertSame('Tech', (string) $folderOutline['text']);
    }
}
