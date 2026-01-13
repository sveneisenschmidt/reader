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

        $asset = new MappedAsset('js/test.js', $tempFile);

        $assetMapper = $this->createMock(AssetMapperInterface::class);
        $assetMapper
            ->method('getAsset')
            ->with('js/test.js')
            ->willReturn($asset);

        $extension = $this->createExtension($assetMapper);

        $result = $extension->assetInline('js/test.js');

        $this->assertStringContainsString('/* js/test.js */', $result);
        $this->assertStringContainsString(
            '(function() { console.log("test"); })();',
            $result,
        );

        unlink($tempFile);
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
    ): AssetInlineExtension {
        return new AssetInlineExtension(
            $assetMapper ?? $this->createStub(AssetMapperInterface::class),
        );
    }
}
