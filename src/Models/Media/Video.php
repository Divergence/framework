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
 * Video Media Model
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 * @author Chris Alfano <themightychris@gmail.com>
 *
 * {@inheritDoc}
 */
class Video extends Media
{
    // configurables
    public static $ExtractFrameCommand = 'avconv -ss %2$u -i %1$s -an -vframes 1 -f mjpeg -'; // 1=video path, 2=position
    public static $ExtractFramePosition = 3;
    public static $encodingProfiles = [
        // from https://www.virag.si/2012/01/web-video-encoding-tutorial-with-ffmpeg-0-9/
        'h264-high-480p' => [
            'enabled' => true,
            'extension' => 'mp4',
            'mimeType' => 'video/mp4',
            'inputOptions' => [],
            'videoCodec' => 'h264',
            'videoOptions' => [
                'profile:v' => 'high',
                'preset' => 'slow',
                'b:v' => '500k',
                'maxrate' => '500k',
                'bufsize' => '1000k',
                'vf' => 'scale="trunc(oh*a/2)*2:480"', // http://superuser.com/questions/571141/ffmpeg-avconv-force-scaled-output-to-be-divisible-by-2
            ],
            'audioCodec' => 'aac',
            'audioOptions' => [
                'strict' => 'experimental',
            ],
        ],

        // from http://superuser.com/questions/556463/converting-video-to-webm-with-ffmpeg-avconv
        'webm-480p' => [
            'enabled' => true,
            'extension' => 'webm',
            'mimeType' => 'video/webm',
            'inputOptions' => [],
            'videoCodec' => 'libvpx',
            'videoOptions' => [
                'vf' => 'scale=-1:480',
            ],
            'audioCodec' => 'libvorbis',
        ],
    ];



    public function getValue($name)
    {
        switch ($name) {
            case 'ThumbnailMIMEType':
                return 'image/jpeg';

            case 'Extension':

                switch ($this->getValue('MIMEType')) {
                    case 'video/x-flv':
                        return 'flv';

                    case 'video/mp4':
                        return 'mp4';

                    case 'video/quicktime':
                        return 'mov';

                    default:
                        throw new Exception('Unable to find video extension for mime-type: '.$this->getValue('MIMEType'));
                }

                // no break
            default:
                return parent::getValue($name);
        }
    }


    // public methods
    public function getImage($sourceFile = null): false|\GdImage
    {
        if (!isset($sourceFile)) {
            $sourceFile = $this->getValue('FilesystemPath') ? $this->getValue('FilesystemPath') : $this->getValue('BlankPath');
        }

        $cmd = sprintf(self::$ExtractFrameCommand, $sourceFile, min(self::$ExtractFramePosition, floor($this->getValue('Duration'))));

        if ($imageData = shell_exec($cmd)) {
            return imagecreatefromstring($imageData);
        } elseif ($sourceFile != $this->getValue('BlankPath')) {
            return static::getImage($this->getValue('BlankPath'));
        }

        return null;
    }

    /**
     * Uses ffprobe to analyze the given file and returns meta data from the first video stream found
     *
     * @param string $filename
     * @param array $mediaInfo
     * @return array
     */
    public static function analyzeFile($filename, $mediaInfo = [])
    {
        // examine media with ffprobe
        $output = shell_exec("ffprobe -of json -show_streams -v quiet $filename");

        if (!$output || !($json = json_decode($output, true)) || empty($json['streams'])) {
            throw new \Exception('Unable to examine video with ffprobe, ensure ffmpeg with ffprobe is installed');
        }

        // extract video streams
        $videoStreams = array_filter($json['streams'], function ($streamInfo) {
            return $streamInfo['codec_type'] == 'video';
        });

        if (!count($videoStreams)) {
            throw new Exception('avprobe did not detect any video streams');
        }

        // convert and write interesting information to mediaInfo
        $mediaInfo['streams'] = $json['streams'];
        $mediaInfo['videoStream'] = array_shift($videoStreams);

        $mediaInfo['width'] = (int)$mediaInfo['videoStream']['width'];
        $mediaInfo['height'] = (int)$mediaInfo['videoStream']['height'];
        $mediaInfo['duration'] = (float)$mediaInfo['videoStream']['duration'];

        return $mediaInfo;
    }

    public function writeFile($sourceFile): bool
    {
        parent::writeFile($sourceFile);


        // determine rotation metadata with exiftool
        $exifToolOutput = exec("exiftool -S -Rotation $this->FilesystemPath");

        if (!$exifToolOutput || !preg_match('/Rotation\s*:\s*(?<rotation>\d+)/', $exifToolOutput, $matches)) {
            throw new Exception('Unable to examine video with exiftool, ensure libimage-exiftool-perl is installed on the host system');
        }

        $sourceRotation = intval($matches['rotation']);


        // fork encoding job with each configured profile
        foreach (static::$encodingProfiles as $profileName => $profile) {
            if (empty($profile['enabled'])) {
                continue;
            }


            // build paths and create directories if needed
            $outputPath = $this->getFilesystemPath($profileName);
            if (!is_dir($outputDir = dirname($outputPath))) {
                mkdir($outputDir, static::$newDirectoryPermissions, true);
            }

            $tmpOutputPath = $outputDir.'/'.'tmp-'.basename($outputPath);
            ;


            // build avconv command
            $cmd = ['avconv', '-loglevel quiet'];

            // -- input options
            if (!empty($profile['inputOptions'])) {
                static::_appendAvconvOptions($cmd, $profile['inputOptions']);
            }
            $cmd[] = '-i';
            $cmd[] = $this->FilesystemPath;

            // -- video output options
            $cmd[] = '-codec:v';
            $cmd[] = $profile['videoCodec'];
            if (!empty($profile['videoOptions'])) {
                static::_appendAvconvOptions($cmd, $profile['videoOptions']);
            }

            // -- audio output options
            $cmd[] = '-codec:a';
            $cmd[] = $profile['audioCodec'];
            if (!empty($profile['audioOptions'])) {
                static::_appendAvconvOptions($cmd, $profile['audioOptions']);
            }

            // -- normalize smartphone rotation
            $cmd[] = '-metadata:s:v rotate="0"';

            if ($sourceRotation == 90) {
                $cmd[] = '-vf "transpose=1"';
            } elseif ($sourceRotation == 180) {
                $cmd[] = '-vf "transpose=1,transpose=1"';
            } elseif ($sourceRotation == 270) {
                $cmd[] = '-vf "transpose=1,transpose=1,transpose=1"';
            }

            // -- general output options
            if (!empty($profile['outputOptions'])) {
                static::_appendAvconvOptions($cmd, $profile['outputOptions']);
            }
            $cmd[] = $tmpOutputPath;


            // move to final path after it finished
            $cmd[] = "&& mv $tmpOutputPath $outputPath";


            // convert command to string and decorate for process control
            $cmd = '(nohup '.implode(' ', $cmd).') > /dev/null 2>/dev/null & echo $! &';


            // execute command and retrieve the spawned PID
            $pid = exec($cmd);
            // TODO: store PID somewhere in APCU cache so we can do something smarter when a video is requested before it's done encoding
        }

        return true;
    }

    public function getFilesystemPath($variant = 'original', $filename = null): string
    {
        if (!$filename && array_key_exists($variant, static::$encodingProfiles)) {
            $filename = $this->ID.'.'.static::$encodingProfiles[$variant]['extension'];
            $variant = 'video-'.$variant;
        }

        return parent::getFilesystemPath($variant, $filename);
    }

    public function getMIMEType($variant = 'original'): string
    {
        if (array_key_exists($variant, static::$encodingProfiles)) {
            return static::$encodingProfiles[$variant]['mimeType'];
        }

        return parent::getMIMEType($variant);
    }

    public function isVariantAvailable($variant)
    {
        if (
            array_key_exists($variant, static::$encodingProfiles) &&
            !empty(static::$encodingProfiles[$variant]['enabled']) &&
            is_readable($this->getFilesystemPath($variant))
        ) {
            return true;
        }

        return parent::isVariantAvailable($variant);
    }

    protected static function _appendAvconvOptions(array &$cmd, array $options)
    {
        foreach ($options as $key => $value) {
            if (!is_int($key)) {
                $cmd[] = '-'.$key;
            }

            if ($value) {
                $cmd[] = $value;
            }
        }
    }
}
