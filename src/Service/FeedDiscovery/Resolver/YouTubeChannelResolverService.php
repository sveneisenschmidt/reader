<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Service\FeedDiscovery\Resolver;

use App\Service\FeedDiscovery\FeedResolverInterface;
use App\Service\FeedDiscovery\FeedResolverResult;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class YouTubeChannelResolverService implements FeedResolverInterface
{
    private const RESOLVER_NAME = 'youtube-channel';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Generate a SOCS cookie value to bypass YouTube GDPR consent.
     *
     * The SOCS cookie is a base64-encoded protobuf message that indicates
     * the user has made a consent choice. This generates a valid cookie
     * that opts out of personalized content.
     *
     * Structure (nested protobuf):
     * - Field 1 (varint): version = 1
     * - Field 2 (embedded message): consent data
     *   - Field 1 (varint): consent mode = 3 (reject all)
     *   - Field 2 (string): random ID
     *   - Field 3 (string): language "en"
     *   - Field 4 (varint): flag = 1
     * - Field 3 (embedded message): timestamp
     *   - Field 1 (varint): unix timestamp
     */
    private function generateSocsCookie(): string
    {
        $timestamp = time();
        $randomId = (string) random_int(100000000, 999999999);

        // Build inner consent message (field 2)
        $innerMessage = '';
        $innerMessage .= "\x08\x03"; // Field 1: consent mode = 3
        $innerMessage .= "\x12".chr(strlen($randomId)).$randomId; // Field 2: random ID
        $innerMessage .= "\x1a\x02en"; // Field 3: language "en"
        $innerMessage .= " \x01"; // Field 4: flag = 1

        // Build timestamp message (field 3)
        $timestampVarint = $this->encodeVarint($timestamp);
        $timestampMessage = "\x08".$timestampVarint;

        // Build outer message
        $proto = '';
        $proto .= "\x08\x01"; // Field 1: version = 1
        $proto .= "\x12".chr(strlen($innerMessage)).$innerMessage; // Field 2: consent data
        $proto .= "\x1a".chr(strlen($timestampMessage)).$timestampMessage; // Field 3: timestamp

        // Use URL-safe base64 encoding (replace + with - and / with _)
        return rtrim(strtr(base64_encode($proto), '+/', '-_'), '=');
    }

    /**
     * Encode an integer as a protobuf varint.
     */
    private function encodeVarint(int $value): string
    {
        $result = '';
        while ($value > 127) {
            $result .= chr(($value & 0x7F) | 0x80);
            $value >>= 7;
        }
        $result .= chr($value & 0x7F);

        return $result;
    }

    public function supports(string $input): bool
    {
        $url = $this->normalizeUrl($input);
        $host = parse_url($url, PHP_URL_HOST);

        if (
            $host === null
            || !preg_match('/(?:^|\.)(youtube\.com)$/i', $host)
        ) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if ($path === '') {
            return false;
        }

        return $this->isChannelPath($path);
    }

    public function resolve(string $input): FeedResolverResult
    {
        $url = $this->normalizeUrl($input);

        $result = new FeedResolverResult(self::RESOLVER_NAME);

        $channelId = $this->extractChannelIdFromUrl($url);
        if ($channelId !== null) {
            return $result->setFeedUrl($this->buildFeedUrl($channelId));
        }

        try {
            $socsCookie = $this->generateSocsCookie();
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; Reader/1.0)',
                    'Cookie' => 'CONSENT=PENDING+999; SOCS='.$socsCookie,
                    'Accept-Language' => 'en-US,en;q=0.9',
                ],
            ]);
            $content = $response->getContent();
        } catch (TransportExceptionInterface $e) {
            return $result->setError(
                'Could not fetch YouTube channel page: '.$e->getMessage(),
            );
        } catch (\Throwable $e) {
            return $result->setError(
                'Could not fetch YouTube channel page: '.$e->getMessage(),
            );
        }

        $channelId = $this->extractChannelIdFromHtml($content);
        if ($channelId === null) {
            return $result->setError(
                'Could not determine YouTube channel ID from the page',
            );
        }

        return $result->setFeedUrl($this->buildFeedUrl($channelId));
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (
            !str_starts_with($url, 'http://')
            && !str_starts_with($url, 'https://')
        ) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    private function buildFeedUrl(string $channelId): string
    {
        return sprintf(
            'https://www.youtube.com/feeds/videos.xml?channel_id=%s',
            $channelId,
        );
    }

    private function extractChannelIdFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (preg_match('#^/channel/(UC[a-zA-Z0-9_-]+)#', $path, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractChannelIdFromHtml(string $html): ?string
    {
        $patterns = [
            '/"channelId":"(UC[a-zA-Z0-9_-]+)"/',
            '/<meta[^>]+itemprop=["\']channelId["\'][^>]+content=["\'](UC[a-zA-Z0-9_-]+)["\']/i',
            '/data-channel-external-id=["\'](UC[a-zA-Z0-9_-]+)["\']/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function isChannelPath(string $path): bool
    {
        return str_starts_with($path, '/channel/')
            || str_starts_with($path, '/c/')
            || str_starts_with($path, '/user/')
            || str_starts_with($path, '/@');
    }
}
