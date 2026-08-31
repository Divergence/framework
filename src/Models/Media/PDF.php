<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Media;

use Exception;

/**
 * PDF Media Model
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 *
 * {@inheritDoc}
 */
class PDF extends Media
{
    public static $extractPageCommand = 'convert %1$s JPEG:- 2>/dev/null'; // 1=escaped 'pdf path[page]' argument
    public static $extractPageIndex = 0;

    public function getValue($name)
    {
        switch ($name) {
            case 'ThumbnailMIMEType':
                return 'image/png';

            case 'Extension':

                switch ($this->getValue('MIMEType')) {
                    case 'application/pdf':
                        return 'pdf';
                    case 'application/postscript':
                        return 'eps';
                    case 'image/svg+xml':
                        return 'svg';
                    default:
                        throw new Exception('Unable to find document extension for mime-type: '.$this->getValue('MIMEType'));
                }

            default:
                return parent::getValue($name);
        }
    }

    public function getImage($sourceFile = null): false|\GdImage
    {
        if (!isset($sourceFile)) {
            $sourceFile = $this->FilesystemPath ? $this->FilesystemPath : $this->BlankPath;
        }

        $cmd = sprintf(static::$extractPageCommand, escapeshellarg($sourceFile . '[' . static::$extractPageIndex . ']'));

        if (!$imageData = shell_exec($cmd)) {
            return false;
        }

        return imagecreatefromstring($imageData);
    }

    public static function analyzeFile($filename, $mediaInfo = [])
    {
        $cmd = sprintf(static::$extractPageCommand, escapeshellarg($filename . '[' . static::$extractPageIndex . ']'));

        if (!$imageData = shell_exec($cmd)) {
            throw new Exception('Unable to convert PDF, ensure that imagemagick is installed on the server');
        }

        $pageIm = imagecreatefromstring($imageData);

        if (!$pageIm) {
            throw new Exception('Unable to convert PDF, ensure that imagemagick is installed on the server');
        }

        $mediaInfo['width'] = imagesx($pageIm);
        $mediaInfo['height'] = imagesy($pageIm);

        return $mediaInfo;
    }
}
