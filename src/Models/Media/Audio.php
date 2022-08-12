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
 * Audio Media Model
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 * @author Chris Alfano <themightychris@gmail.com>
 *
 * {@inheritDoc}
 */
class Audio extends Media
{
    // configurables
    public static $previewExtractCommand = 'ffmpeg -i %1$s -ss %3$u -t %4$u -f mp3 -y %2$s'; // 1=input file, 2=output file, 3=start time, 4=duration
    public static $previewDuration = 30;
    public static $iconPath = '/site-root/img/icons/filetypes/mp3.png';

    public function getValue($name)
    {
        switch ($name) {
            case 'ThumbnailMIMEType':
                return 'image/png';

            case 'Width':
                return 128;

            case 'Height':
                return 128;

            case 'Extension':

                switch ($this->MIMEType) {
                    case 'audio/mpeg':
                        return 'mp3';
                    default:
                        throw new Exception('Unable to find audio extension for mime-type: '.$this->MIMEType);
                }

                // no break
            default:
                return parent::getValue($name);
        }
    }


    // public methods
    public static function getBlankPath($contextClass)
    {
        $node = Site::resolvePath(static::$iconPath);
        return $node ? $node->RealPath : null;
    }

    public function getImage($sourceFile = null)
    {
        if (!isset($sourceFile)) {
            $sourceFile = $this->BlankPath;
        }

        return imagecreatefromstring(file_get_contents($sourceFile));
    }

    public function createPreview()
    {
        // check if a preview already exists

        if (!empty($_REQUEST['startTime']) && is_numeric($_REQUEST['startTime']) && ($_REQUEST['startTime'] >= 0) && ($_REQUEST['startTime'] < $this->Duration)) {
            $startTime = $_REQUEST['startTime'];
        } else {
            $startTime = 0;
        }

        $previewPath = tempnam('/tmp', 'mediaPreview');

        // generate preview
        $cmd = sprintf(static::$previewExtractCommand, $this->FilesystemPath, $previewPath, $startTime, static::$previewDuration);
        shell_exec($cmd);

        if (!filesize($previewPath)) {
            throw new Exception('Preview output is empty');
        }

        // create media instance
        $PreviewMedia = Media::createFromFile($previewPath, [
            'ContextClass' => 'Media'
            ,'ContextID' => $this->ID
            ,'Caption' => sprintf('%u sec preview (%us-%us)', static::$previewDuration, $startTime, $startTime+static::$previewDuration),
        ]);

        return $PreviewMedia;
    }

    // static methods
#    public static function analyzeFile($filename, $mediaInfo = array())
#    {
#        // Initialize getID3 engine
#        $getID3 = new getID3();
#
#        $mediaInfo['id3Info'] = $getID3->analyze($filename);
#
#        $mediaInfo['width'] = 0;
#        $mediaInfo['height'] = 0;
#        $mediaInfo['duration'] = $mediaInfo['id3Info']['playtime_seconds'];
#
#        return $mediaInfo;
#    }
}
