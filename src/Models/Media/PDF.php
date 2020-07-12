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
 * @author Chris Alfano <themightychris@gmail.com>
 *
 * {@inheritDoc}
 */
class PDF extends Media
{
    // configurables
    public static $extractPageCommand = 'convert \'%1$s[%2$u]\' JPEG:- 2>/dev/null'; // 1=pdf path, 2=page
    public static $extractPageIndex = 0;

    public function getValue($name)
    {
        switch ($name) {
            case 'ThumbnailMIMEType':
                return 'image/png';

            case 'Extension':

                switch ($this->MIMEType) {
                    case 'application/pdf':
                        return 'pdf';
                    case 'application/postscript':
                        return 'eps';
                    case 'image/svg+xml':
                        return 'svg';
                    default:
                        throw new Exception('Unable to find document extension for mime-type: '.$this->MIMEType);
                }

                // no break
            default:
                return parent::getValue($name);
        }
    }


    // public methods
    public function getImage($sourceFile = null)
    {
        if (!isset($sourceFile)) {
            $sourceFile = $this->FilesystemPath ? $this->FilesystemPath : $this->BlankPath;
        }

        $cmd = sprintf(static::$extractPageCommand, $sourceFile, static::$extractPageIndex);
        $fileImage = imagecreatefromstring(shell_exec($cmd));

        return $fileImage;
    }

    // static methods
    public static function analyzeFile($filename, $mediaInfo = [])
    {
        $cmd = sprintf(static::$extractPageCommand, $filename, static::$extractPageIndex);
        $pageIm = @imagecreatefromstring(shell_exec($cmd));

        if (!$pageIm) {
            throw new Exception('Unable to convert PDF, ensure that imagemagick is installed on the server');
        }

        $mediaInfo['width'] = imagesx($pageIm);
        $mediaInfo['height'] = imagesy($pageIm);

        return $mediaInfo;
    }
}
