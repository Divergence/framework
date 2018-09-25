<?php
namespace Divergence\Models\Media;

use Exception;

class Image extends Media
{
    // configurables
    public static $jpegCompression = 90;

    // magic methods
    public static function __classLoaded()
    {
        $className = get_called_class();

        Media::$mimeHandlers['image/gif'] = $className;
        Media::$mimeHandlers['image/jpeg'] = $className;
        Media::$mimeHandlers['image/png'] = $className;
        Media::$mimeHandlers['image/tiff'] = $className;
        Media::$mimeHandlers['application/psd'] = $className;

        parent::__classLoaded();
    }


    public function getValue($name)
    {
        switch ($name) {
            case 'ThumbnailMIMEType':
                switch ($this->MIMEType) {
                    case 'application/psd':
                        return 'image/png';
                    case 'image/tiff':
                        return 'image/jpeg';
                    default:
                        return $this->MIMEType;
                }

                // no break
            case 'Extension':

                switch ($this->MIMEType) {
                    case 'application/psd':
                        return 'psd';

                    case 'image/tiff':
                        return 'tif';

                    case 'image/gif':
                        return 'gif';

                    case 'image/jpeg':
                        return 'jpg';

                    case 'image/png':
                        return 'png';

                    default:
                        throw new Exception('Unable to find photo extension for mime-type: '.$this->MIMEType);
                }

                // no break
            default:
                return parent::getValue($name);
        }
    }


    // public methods


    // static methods
    public static function analyzeFile($filename, $mediaInfo = [])
    {
        if (!$mediaInfo['imageInfo'] = @getimagesize($filename)) {
            throw new Exception('Failed to read image file information');
        }

        // store image data
        $mediaInfo['width'] = $mediaInfo['imageInfo'][0];
        $mediaInfo['height'] = $mediaInfo['imageInfo'][1];
        $mediaInfo['duration'] = 0;

        return $mediaInfo;
    }
}
