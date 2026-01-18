<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Feed\Entity\Subscription;

class OpmlService
{
    /**
     * Parse OPML content and extract feed information.
     *
     * @return array<array{url: string, title: string, folder: ?string}>
     */
    public function parse(string $content): array
    {
        $feeds = [];

        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            $xml = new \SimpleXMLElement($content);
        } catch (\Exception) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);

            return $feeds;
        }

        libxml_use_internal_errors($previousUseErrors);

        $this->parseOutlines($xml->body, $feeds, null);

        return $feeds;
    }

    /**
     * Generate OPML content from subscriptions.
     *
     * @param Subscription[] $subscriptions
     */
    public function generate(array $subscriptions): string
    {
        $xml = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><opml version="2.0"></opml>',
        );

        $head = $xml->addChild('head');
        $head->addChild('title', 'Reader Subscriptions');
        $head->addChild('dateCreated', new \DateTimeImmutable()->format('r'));

        $body = $xml->addChild('body');

        // Group by folder
        $grouped = [];
        $ungrouped = [];

        foreach ($subscriptions as $subscription) {
            $folder = $subscription->getFolder();
            if ($folder !== null && $folder !== '') {
                $grouped[$folder][] = $subscription;
            } else {
                $ungrouped[] = $subscription;
            }
        }

        // Add ungrouped feeds first
        foreach ($ungrouped as $subscription) {
            $this->addOutline($body, $subscription);
        }

        // Add grouped feeds
        foreach ($grouped as $folder => $folderSubscriptions) {
            $folderOutline = $body->addChild('outline');
            $folderOutline->addAttribute('text', $folder);

            foreach ($folderSubscriptions as $subscription) {
                $this->addOutline($folderOutline, $subscription);
            }
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());

        return $dom->saveXML();
    }

    /**
     * Recursively parse outline elements.
     *
     * @param array<array{url: string, title: string, folder: ?string}> $feeds
     */
    private function parseOutlines(
        \SimpleXMLElement $element,
        array &$feeds,
        ?string $folder,
    ): void {
        foreach ($element->outline as $outline) {
            $xmlUrl = (string) $outline['xmlUrl'];

            if ($xmlUrl !== '') {
                // This is a feed
                $feeds[] = [
                    'url' => $xmlUrl,
                    'title' => (string) $outline['text'],
                    'folder' => $folder,
                ];
            } else {
                // This is a folder, recurse into it
                $folderName = (string) $outline['text'];
                $this->parseOutlines($outline, $feeds, $folderName);
            }
        }
    }

    private function addOutline(
        \SimpleXMLElement $parent,
        Subscription $subscription,
    ): void {
        $outline = $parent->addChild('outline');
        $outline->addAttribute('type', 'rss');
        $outline->addAttribute('text', $subscription->getName());
        $outline->addAttribute('title', $subscription->getName());
        $outline->addAttribute('xmlUrl', $subscription->getUrl());
    }
}
