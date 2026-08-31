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
use Divergence\Models\Media\Image;

class ImageTest extends TestCase
{
    private static string $jpegPath;

    public static function setUpBeforeClass(): void
    {
        static::$jpegPath = dirname(__DIR__, 3) . '/assets/20210211232214_IMG_0570.JPG';

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

    private static function makeImage(array $record = [], bool $phantom = false): Image
    {
        return new Image($record, false, $phantom);
    }

    public function testGetValueMapsMimeTypeToExtension(): void
    {
        $this->assertSame('jpg', static::makeImage(['MIMEType' => 'image/jpeg'])->getValue('Extension'));
        $this->assertSame('png', static::makeImage(['MIMEType' => 'image/png'])->getValue('Extension'));
        $this->assertSame('gif', static::makeImage(['MIMEType' => 'image/gif'])->getValue('Extension'));
        $this->assertSame('psd', static::makeImage(['MIMEType' => 'application/psd'])->getValue('Extension'));
        $this->assertSame('tif', static::makeImage(['MIMEType' => 'image/tiff'])->getValue('Extension'));
    }

    public function testGetValueThrowsForUnknownMimeType(): void
    {
        $image = static::makeImage(['MIMEType' => 'image/x-nonexistent']);

        $this->expectException(Exception::class);

        $image->getValue('Extension');
    }

    public function testGetValueThumbnailMimeTypeMapsPsdAndTiffToDifferentFormats(): void
    {
        $this->assertSame('image/png', static::makeImage(['MIMEType' => 'application/psd'])->getValue('ThumbnailMIMEType'));
        $this->assertSame('image/jpeg', static::makeImage(['MIMEType' => 'image/tiff'])->getValue('ThumbnailMIMEType'));
        $this->assertSame('image/jpeg', static::makeImage(['MIMEType' => 'image/jpeg'])->getValue('ThumbnailMIMEType'));
    }

    public function testAnalyzeFileReturnsRealImageDimensions(): void
    {
        $mediaInfo = Image::analyzeFile(static::$jpegPath);

        $this->assertSame(6960, $mediaInfo['width']);
        $this->assertSame(4640, $mediaInfo['height']);
        $this->assertSame(0, $mediaInfo['duration']);
    }

    public function testAnalyzeFileThrowsForInvalidImageFile(): void
    {
        $notAnImage = tempnam(sys_get_temp_dir(), 'not_an_image_');
        file_put_contents($notAnImage, 'this is definitely not a jpeg');

        $this->expectException(Exception::class);

        try {
            Image::analyzeFile($notAnImage);
        } finally {
            unlink($notAnImage);
        }
    }

    public function testGetImageLoadsRealJpegAndAppliesExifOrientation(): void
    {
        $image = static::makeImage(['MIMEType' => 'image/jpeg']);

        $gdImage = $image->getImage(static::$jpegPath);

        $this->assertInstanceOf(\GdImage::class, $gdImage);
        $this->assertSame(6960, imagesx($gdImage));
        $this->assertSame(4640, imagesy($gdImage));
    }

    public function testCreateThumbnailImageProducesRealThumbnailFile(): void
    {
        $image = static::makeImage(['ID' => 601, 'MIMEType' => 'image/jpeg']);

        $sourcePath = $image->getFilesystemPath();
        if (!is_dir($dir = dirname($sourcePath))) {
            mkdir($dir, 0775, true);
        }
        copy(static::$jpegPath, $sourcePath);

        $thumbPath = tempnam(sys_get_temp_dir(), 'thumb_test_') . '.jpg';

        $image->createThumbnailImage($thumbPath, 200, 200);

        $this->assertFileExists($thumbPath);
        $size = getimagesize($thumbPath);
        $this->assertLessThanOrEqual(200, $size[0]);
        $this->assertLessThanOrEqual(200, $size[1]);

        unlink($thumbPath);
    }

    public function testGetThumbnailCreatesAndCachesThumbnailFile(): void
    {
        $image = static::makeImage(['ID' => 602, 'MIMEType' => 'image/jpeg']);

        $sourcePath = $image->getFilesystemPath();
        if (!is_dir($dir = dirname($sourcePath))) {
            mkdir($dir, 0775, true);
        }
        copy(static::$jpegPath, $sourcePath);

        $thumbPath = $image->getThumbnail(150, 150);

        $this->assertFileExists($thumbPath);

        $secondCallPath = $image->getThumbnail(150, 150);
        $this->assertSame($thumbPath, $secondCallPath);
    }
}
