<?php

/*
 * This file is part of Reader.
 *
 * (c) Sven Eisenschmidt <sven.eisenschmidt@gmail.com>
 *
 * SPDX-License-Identifier: MIT
 */

namespace App\Tests\Unit\Twig;

use App\Twig\AssetInlineExtension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Twig\TwigFunction;

class AssetInlineExtensionTest extends TestCase
{
    #[Test]
    public function getFunctionsReturnsOneFunction(): void
    {
        $extension = $this->createExtension();

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertContainsOnlyInstancesOf(TwigFunction::class, $functions);

        $names = array_map(fn (TwigFunction $f) => $f->getName(), $functions);
        $this->assertContains('asset_inline', $names);
    }

    #[Test]
    public function assetInlineReturnsEmptyStringWhenAssetNotFound(): void
    {
        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper
            ->method('getAsset')
            ->with('js/nonexistent.js')
            ->willReturn(null);

        $extension = $this->createExtension($assetMapper);

        $this->assertEquals('', $extension->assetInline('js/nonexistent.js'));
    }

    #[Test]
    public function assetInlineReturnsFileContentWithPathComment(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'asset_test_');
        file_put_contents(
            $tempFile,
            '(function() { console.log("test"); })();',
        );

        $asset = new MappedAsset(
            'js/test.js',
            $tempFile,
            publicPath: '/assets/js/test-abc123.js',
        );

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper
            ->method('getAsset')
            ->with('js/test.js')
            ->willReturn($asset);

        $extension = $this->createExtension($assetMapper);

        $result = $extension->assetInline('js/test.js');

        $this->assertStringContainsString(
            '/* /assets/js/test-abc123.js */',
            $result,
        );
        $this->assertStringContainsString(
            '(function() { console.log("test"); })();',
            $result,
        );

        unlink($tempFile);
    }

    #[Test]
    public function assetInlineLoadsFromPublicDirWhenExists(): void
    {
        $publicDir = sys_get_temp_dir().'/public_test_'.uniqid();
        mkdir($publicDir.'/assets/js', 0777, true);

        $publicFile = $publicDir.'/assets/js/test-abc123.js';
        file_put_contents($publicFile, '/* minified */console.log("min");');

        $sourceFile = tempnam(sys_get_temp_dir(), 'source_');
        file_put_contents($sourceFile, '/* source */console.log("src");');

        $asset = new MappedAsset(
            'js/test.js',
            $sourceFile,
            publicPath: '/assets/js/test-abc123.js',
        );

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper
            ->method('getAsset')
            ->with('js/test.js')
            ->willReturn($asset);

        $extension = $this->createExtension($assetMapper, $publicDir);

        $result = $extension->assetInline('js/test.js');

        $this->assertStringContainsString('/* minified */', $result);
        $this->assertStringNotContainsString('/* source */', $result);

        unlink($publicFile);
        unlink($sourceFile);
        rmdir($publicDir.'/assets/js');
        rmdir($publicDir.'/assets');
        rmdir($publicDir);
    }

    #[Test]
    public function assetInlineFunctionIsSafeForHtml(): void
    {
        $extension = $this->createExtension();

        $functions = $extension->getFunctions();
        $assetInlineFunction = array_filter(
            $functions,
            fn (TwigFunction $f) => $f->getName() === 'asset_inline',
        );

        $function = reset($assetInlineFunction);
        $options = new \ReflectionClass($function)
            ->getProperty('options')
            ->getValue($function);
        $this->assertContains('html', $options['is_safe']);
    }

    private function createExtension(
        ?AssetMapperInterface $assetMapper = null,
        string $publicDir = '/tmp',
    ): AssetInlineExtension {
        return new AssetInlineExtension(
            $assetMapper ?? $this->createStub(AssetMapperInterface::class),
            $publicDir,
        );
    }
}
