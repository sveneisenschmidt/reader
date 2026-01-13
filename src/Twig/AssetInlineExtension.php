<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Twig;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AssetInlineExtension extends AbstractExtension
{
    public function __construct(
        private AssetMapperInterface $assetMapper,
        private string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'asset_inline',
                [$this, 'assetInline'],
                ['is_safe' => ['html']],
            ),
        ];
    }

    public function assetInline(string $path): string
    {
        $asset = $this->assetMapper->getAsset($path);

        if ($asset === null) {
            return '';
        }

        // Try compiled file in public/ first, then fall back to source
        $publicFile = $this->publicDir.$asset->publicPath;
        if (file_exists($publicFile)) {
            $content = file_get_contents($publicFile);
        } else {
            $content = $asset->content ?? file_get_contents($asset->sourcePath);
        }

        return '/* '.$asset->publicPath." */\n".$content;
    }
}
