<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Models\Media;

use Exception;
use PHPUnit\Framework\TestCase;
use Divergence\App;
use Divergence\Models\Media\Video;

class VideoTest extends TestCase
{
    private static string $bunnyPath;

    public static function setUpBeforeClass(): void
    {
        static::$bunnyPath = dirname(__DIR__, 3) . '/assets/bunny.mp4';

        if (!isset(App::$App)) {
            new App(dirname(__DIR__, 4));
        }
    }

    protected function tearDown(): void
    {
        $mediaPath = App::$App->ApplicationPath . '/media';

        if (is_dir($mediaPath)) {
            exec('rm -rf ' . escapeshellarg($mediaPath));
        }
    }

    private static function makeVideo(array $record = [], bool $phantom = false): Video
    {
        return new Video($record, false, $phantom);
    }

    public function testGetValueMapsKnownMimeTypeToExtension(): void
    {
        $video = static::makeVideo(['MIMEType' => 'video/webm']);

        $this->assertSame('webm', $video->getValue('Extension'));
    }

    public function testGetValueFallsBackToSubtypeForUnknownVideoMimeType(): void
    {
        $video = static::makeVideo(['MIMEType' => 'video/x-newformat']);

        $this->assertSame('x-newformat', $video->getValue('Extension'));
    }

    public function testGetValueThrowsForNonVideoMimeType(): void
    {
        $video = static::makeVideo(['MIMEType' => 'application/octet-stream']);

        $this->expectException(Exception::class);

        $video->getValue('Extension');
    }

    public function testGetValueReturnsThumbnailMimeType(): void
    {
        $video = static::makeVideo(['MIMEType' => 'video/mp4']);

        $this->assertSame('image/jpeg', $video->getValue('ThumbnailMIMEType'));
    }

    public function testAnalyzeFileReturnsRealFfprobeMetadata(): void
    {
        $mediaInfo = Video::analyzeFile(static::$bunnyPath);

        $this->assertSame(480, $mediaInfo['width']);
        $this->assertSame(270, $mediaInfo['height']);
        $this->assertEqualsWithDelta(12.16, $mediaInfo['duration'], 0.5);
        $this->assertSame(0, $mediaInfo['rotation']);
    }

    public function testAnalyzeFileThrowsForUnreadableFile(): void
    {
        $this->expectException(Exception::class);

        Video::analyzeFile('/nonexistent/path/to/nothing.mp4');
    }

    public function testGetImageExtractsRealFrameFromVideo(): void
    {
        $video = static::makeVideo(['MIMEType' => 'video/mp4', 'Duration' => 12.16]);

        $image = $video->getImage(static::$bunnyPath);

        $this->assertInstanceOf(\GdImage::class, $image);
        $this->assertSame(480, imagesx($image));
        $this->assertSame(270, imagesy($image));
    }

    public function testGetFilesystemPathReturnsNullForPhantomRecord(): void
    {
        $video = static::makeVideo([], true);

        $this->assertNull($video->getFilesystemPath());
    }

    public function testGetFilesystemPathUsesEncodingProfileExtensionForKnownVariant(): void
    {
        $video = static::makeVideo(['ID' => 501, 'MIMEType' => 'video/mp4']);

        $path = $video->getFilesystemPath('h264-high-480p');

        $this->assertStringContainsString('/video-h264-high-480p/', $path);
        $this->assertStringEndsWith('501.mp4', $path);
    }

    public function testGetMIMETypeReturnsEncodingProfileMimeTypeForKnownVariant(): void
    {
        $video = static::makeVideo(['MIMEType' => 'video/mp4']);

        $this->assertSame('video/webm', $video->getMIMEType('webm-480p'));
    }

    public function testGetMIMETypeFallsBackToParentForUnknownVariant(): void
    {
        $video = static::makeVideo(['MIMEType' => 'video/mp4']);

        $this->assertSame('video/mp4', $video->getMIMEType('original'));
    }

    public function testIsVariantAvailableReturnsTrueWhenEncodedFileExists(): void
    {
        $video = static::makeVideo(['ID' => 502, 'MIMEType' => 'video/mp4']);

        $path = $video->getFilesystemPath('h264-high-480p');
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, 'fake-encoded-output');

        $this->assertTrue($video->isVariantAvailable('h264-high-480p'));
    }

    public function testIsVariantAvailableReturnsFalseWhenEncodedFileMissing(): void
    {
        $video = static::makeVideo(['ID' => 503, 'MIMEType' => 'video/mp4']);

        $this->assertFalse($video->isVariantAvailable('h264-high-480p'));
    }

    public function testWriteFileMovesSourceAndLaunchesEncodingJobs(): void
    {
        $video = static::makeVideo(['ID' => 504, 'MIMEType' => 'video/mp4']);

        $tempCopy = tempnam(sys_get_temp_dir(), 'bunny_test_');
        copy(static::$bunnyPath, $tempCopy);

        $video->writeFile($tempCopy);

        $originalPath = $video->getFilesystemPath();

        $this->assertFileExists($originalPath);
        $this->assertGreaterThan(0, filesize($originalPath));
        $this->assertFileDoesNotExist($tempCopy);

        foreach (['h264-high-480p', 'webm-480p'] as $profileName) {
            $this->assertDirectoryExists(dirname($video->getFilesystemPath($profileName)));
        }
    }
}
