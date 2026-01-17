<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Service\FeedDiscovery\Resolver;

use App\Domain\Discovery\Resolver\YouTubeChannelResolverService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class YouTubeChannelResolverServiceTest extends TestCase
{
    #[Test]
    public function resolvesChannelUrlWithoutHttp(): void
    {
        $resolver = new YouTubeChannelResolverService(
            $this->createMock(HttpClientInterface::class),
        );

        $result = $resolver->resolve(
            'https://www.youtube.com/channel/UC123456789',
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UC123456789',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesChannelByFetchingHtml(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getContent')
            ->willReturn('{"channelId":"UCABCDEF"}');

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://www.youtube.com/@user')
            ->willReturn($response);

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/@user');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCABCDEF',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function returnsErrorWhenChannelIdMissing(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getContent')
            ->willReturn('<title>Missing ID</title>');

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://www.youtube.com/@user')
            ->willReturn($response);

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/@user');

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString(
            'Could not determine YouTube channel ID',
            $result->getError(),
        );
    }

    #[Test]
    public function returnsErrorWhenRequestFails(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(
                $this->createMock(TransportExceptionInterface::class),
            );

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/@user');

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString(
            'Could not fetch YouTube channel page',
            $result->getError(),
        );
    }

    #[Test]
    #[DataProvider('supportedUrlsProvider')]
    public function supportsYouTubeChannelUrls(
        string $url,
        bool $expected,
    ): void {
        $resolver = new YouTubeChannelResolverService(
            $this->createMock(HttpClientInterface::class),
        );

        $this->assertEquals($expected, $resolver->supports($url));
    }

    public static function supportedUrlsProvider(): array
    {
        return [
            'channel path' => ['https://www.youtube.com/channel/UC123', true],
            'channel path without https' => ['youtube.com/channel/UC123', true],
            'custom url with /c/' => [
                'https://www.youtube.com/c/SomeChannel',
                true,
            ],
            'user path' => ['https://www.youtube.com/user/SomeUser', true],
            'handle path' => ['https://www.youtube.com/@SomeHandle', true],
            'non-youtube domain' => [
                'https://example.com/channel/UC123',
                false,
            ],
            'youtube without path' => ['https://www.youtube.com', false],
            'youtube with empty path' => ['https://www.youtube.com/', false],
            'youtube video url' => [
                'https://www.youtube.com/watch?v=abc123',
                false,
            ],
            'youtube playlist url' => [
                'https://www.youtube.com/playlist?list=abc',
                false,
            ],
            'random url' => ['https://example.com/feed.xml', false],
            'mobile youtube channel' => [
                'https://m.youtube.com/channel/UC123',
                true,
            ],
            'mobile youtube handle' => [
                'https://m.youtube.com/@SomeHandle',
                true,
            ],
            'music youtube' => [
                'https://music.youtube.com/channel/UC123',
                true,
            ],
            'fake youtube domain' => [
                'https://notyoutube.com/channel/UC123',
                false,
            ],
            'fake subdomain' => [
                'https://youtube.com.evil.com/channel/UC123',
                false,
            ],
        ];
    }

    #[Test]
    public function resolvesUrlWithoutProtocol(): void
    {
        $resolver = new YouTubeChannelResolverService(
            $this->createMock(HttpClientInterface::class),
        );

        $result = $resolver->resolve('youtube.com/channel/UCtest123');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCtest123',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesUrlWithWhitespace(): void
    {
        $resolver = new YouTubeChannelResolverService(
            $this->createMock(HttpClientInterface::class),
        );

        $result = $resolver->resolve(
            '  https://www.youtube.com/channel/UCtest123  ',
        );

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCtest123',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function extractsChannelIdFromMetaTag(): void
    {
        $html =
            '<html><head><meta itemprop="channelId" content="UCmetaTag123"></head></html>';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($html);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/@user');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCmetaTag123',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function extractsChannelIdFromDataAttribute(): void
    {
        $html = '<div data-channel-external-id="UCdataAttr456"></div>';

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn($html);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/@user');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCdataAttr456',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesCustomUrlPath(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getContent')
            ->willReturn('{"channelId":"UCcustom789"}');

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://www.youtube.com/c/CustomChannel')
            ->willReturn($response);

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/c/CustomChannel');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCcustom789',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function resolvesUserPath(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response
            ->method('getContent')
            ->willReturn('{"channelId":"UCuser999"}');

        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://www.youtube.com/user/SomeUser')
            ->willReturn($response);

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/user/SomeUser');

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(
            'https://www.youtube.com/feeds/videos.xml?channel_id=UCuser999',
            $result->getFeedUrl(),
        );
    }

    #[Test]
    public function returnsErrorOnGenericException(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new \RuntimeException('Connection timeout'));

        $resolver = new YouTubeChannelResolverService($client);

        $result = $resolver->resolve('https://www.youtube.com/@user');

        $this->assertFalse($result->isSuccessful());
        $this->assertStringContainsString(
            'Could not fetch YouTube channel page',
            $result->getError(),
        );
        $this->assertStringContainsString(
            'Connection timeout',
            $result->getError(),
        );
    }
}
