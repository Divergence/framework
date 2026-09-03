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
 *
 * {@inheritDoc}
 */
class Video extends Media
{
    // configurables
    public static $ExtractFrameCommand = 'ffmpeg -ss %2$u -i %1$s -an -vframes 1 -f mjpeg pipe:1 2>/dev/null';
    public static $ExtractFramePosition = 3;

    public static $encodingProfiles = [
        'h264-high-480p' => [
            'enabled' => true,
            'extension' => 'mp4',
            'mimeType' => 'video/mp4',
            'inputOptions' => [],
            'videoCodec' => 'libx264',
            'videoOptions' => [
                'profile:v' => 'high',
                'preset' => 'slow',
                'b:v' => '500k',
                'maxrate' => '500k',
                'bufsize' => '1000k',
                'vf' => 'scale=trunc(oh*a/2)*2:480',
            ],
            'audioCodec' => 'aac',
            'audioOptions' => [],
        ],

        'webm-480p' => [
            'enabled' => true,
            'extension' => 'webm',
            'mimeType' => 'video/webm',
            'inputOptions' => [],
            'videoCodec' => 'libvpx-vp9',
            'videoOptions' => [
                'vf' => 'scale=-2:480',
                'b:v' => '500k',
                'deadline' => 'good',
                'cpu-used' => '2',
            ],
            'audioCodec' => 'libopus',
            'audioOptions' => [],
        ],
    ];


    public static $mimeTypeExtensions = [
        'video/mp4'        => 'mp4',
        'video/webm'       => 'webm',
        'video/ogg'        => 'ogv',
        'video/x-matroska' => 'mkv',
        'video/x-msvideo'  => 'avi',
        'video/quicktime'  => 'mov',
        'video/x-flv'      => 'flv',
        'video/3gpp'       => '3gp',
        'video/x-ms-wmv'   => 'wmv',
        'video/mpeg'       => 'mpg',
        'video/x-m4v'      => 'm4v',
    ];

    public function getValue($name)
    {
        switch ($name) {
            case 'ThumbnailMIMEType':
                return 'image/jpeg';

            case 'Extension':
                $mime = $this->getValue('MIMEType');
                if (isset(static::$mimeTypeExtensions[$mime])) {
                    return static::$mimeTypeExtensions[$mime];
                }
                if (str_starts_with($mime, 'video/')) {
                    return substr($mime, 6);
                }
                throw new Exception('Unable to find video extension for mime-type: ' . $mime);

            default:
                return parent::getValue($name);
        }
    }

    public function getImage($sourceFile = null): false|\GdImage
    {
        if (!isset($sourceFile)) {
            $sourceFile = $this->getValue('FilesystemPath') ?: $this->getValue('BlankPath');
        }

        $duration = (float)$this->getValue('Duration');
        $position = min(static::$ExtractFramePosition, max(0, (int)floor($duration)));

        $cmd = sprintf(static::$ExtractFrameCommand, escapeshellarg($sourceFile), $position);

        if ($imageData = shell_exec($cmd)) {
            return imagecreatefromstring($imageData);
        } elseif ($sourceFile !== $this->getValue('BlankPath')) {
            return static::getImage($this->getValue('BlankPath'));
        }

        return false;
    }

    /**
     * @param string $filename
     * @param array $mediaInfo
     * @return array
     */
    public static function analyzeFile($filename, $mediaInfo = [])
    {
        $output = shell_exec('ffprobe -of json -show_streams -show_format -v quiet ' . escapeshellarg($filename));

        if (!$output || !($json = json_decode($output, true)) || empty($json['streams'])) {
            throw new Exception('Unable to examine video with ffprobe, ensure ffmpeg (with ffprobe) is installed');
        }

        $videoStreams = array_values(array_filter($json['streams'], fn ($s) => $s['codec_type'] === 'video'));

        if (!count($videoStreams)) {
            throw new Exception('ffprobe did not detect any video streams');
        }

        $mediaInfo['streams']     = $json['streams'];
        $mediaInfo['videoStream'] = $videoStreams[0];

        $mediaInfo['width']    = (int)$mediaInfo['videoStream']['width'];
        $mediaInfo['height']   = (int)$mediaInfo['videoStream']['height'];

        $mediaInfo['duration'] = (float)(
            $mediaInfo['videoStream']['duration']
            ?? $json['format']['duration']
            ?? 0
        );

        $rotation = 0;
        foreach ($mediaInfo['videoStream']['side_data_list'] ?? [] as $sideData) {
            if (($sideData['side_data_type'] ?? '') === 'Display Matrix') {
                $rotation = (int)abs($sideData['rotation'] ?? 0);
                break;
            }
        }
        $mediaInfo['rotation'] = $rotation;

        return $mediaInfo;
    }

    public function writeFile($sourceFile): bool
    {
        parent::writeFile($sourceFile);

        $mediaInfo = static::analyzeFile($this->FilesystemPath);
        $sourceRotation = (int)($mediaInfo['rotation'] ?? 0);

        // fork encoding job with each configured profile
        foreach (static::$encodingProfiles as $profileName => $profile) {
            if (empty($profile['enabled'])) {
                continue;
            }

            // build paths and create directories if needed
            $outputPath = $this->getFilesystemPath($profileName);
            if ($outputPath === null) {
                throw new Exception('Unable to determine encoded video output path.');
            }
            if (!is_dir($outputDir = dirname($outputPath))) {
                mkdir($outputDir, static::$newDirectoryPermissions, true);
            }

            $tmpOutputPath = $outputDir . '/tmp-' . basename($outputPath);

            $cmd = ['ffmpeg', '-loglevel quiet', '-y'];

            // -- input options
            if (!empty($profile['inputOptions'])) {
                static::_appendFfmpegOptions($cmd, $profile['inputOptions']);
            }
            $cmd[] = '-i';
            $cmd[] = escapeshellarg($this->FilesystemPath);

            $cmd[] = '-codec:v';
            $cmd[] = $profile['videoCodec'];

            $videoOptions = $profile['videoOptions'] ?? [];

            if ($sourceRotation !== 0) {
                $transpose = match($sourceRotation) {
                    90  => 'transpose=1',
                    180 => 'transpose=1,transpose=1',
                    270 => 'transpose=2',
                    default => null,
                };
                if ($transpose) {
                    $videoOptions['vf'] = isset($videoOptions['vf'])
                        ? $videoOptions['vf'] . ',' . $transpose
                        : $transpose;
                }
            }

            if (!empty($videoOptions)) {
                static::_appendFfmpegOptions($cmd, $videoOptions);
            }

            $cmd[] = '-metadata:s:v:0';
            $cmd[] = 'rotate=0';

            $cmd[] = '-codec:a';
            $cmd[] = $profile['audioCodec'];
            if (!empty($profile['audioOptions'])) {
                static::_appendFfmpegOptions($cmd, $profile['audioOptions']);
            }

            // -- general output options
            if (!empty($profile['outputOptions'])) {
                static::_appendFfmpegOptions($cmd, $profile['outputOptions']);
            }

            $cmd[] = escapeshellarg($tmpOutputPath);
            $cmd[] = '&& mv ' . escapeshellarg($tmpOutputPath) . ' ' . escapeshellarg($outputPath);

            $fullCmd = '(nohup ' . implode(' ', $cmd) . ') > /dev/null 2>/dev/null & echo $!';

            $pid = exec($fullCmd);
        }

        return true;
    }

    public function getFilesystemPath($variant = 'original', $filename = null): ?string
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

        return parent::getMIMEType();
    }

    public function isVariantAvailable($variant): bool
    {
        $path = $this->getFilesystemPath($variant);

        if (
            array_key_exists($variant, static::$encodingProfiles) &&
            !empty(static::$encodingProfiles[$variant]['enabled']) &&
            $path !== null &&
            is_readable($path)
        ) {
            return true;
        }

        return parent::isVariantAvailable($variant);
    }

    protected static function _appendFfmpegOptions(array &$cmd, array $options): void
    {
        foreach ($options as $key => $value) {
            if (!is_int($key)) {
                $cmd[] = '-' . $key;
            }
            if ($value !== null && $value !== false) {
                $cmd[] = escapeshellarg((string) $value);
            }
        }
    }
}
