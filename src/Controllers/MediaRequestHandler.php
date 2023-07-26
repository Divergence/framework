<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Controllers;

use Exception;
use Divergence\Models\Media\Media;
use Divergence\Models\ActiveRecord;
use Divergence\Responders\Response;
use Divergence\Responders\JsonBuilder;
use Divergence\Responders\EmptyBuilder;
use Divergence\Responders\EmptyResponse;
use Divergence\Responders\MediaBuilder;
use Psr\Http\Message\ResponseInterface;
use Divergence\Responders\MediaResponse;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

class MediaRequestHandler extends RecordsRequestHandler
{
    // RecordRequestHandler configuration
    public static $recordClass = Media::class;
    public $accountLevelRead = false;
    public $accountLevelBrowse = 'User';
    public $accountLevelWrite = 'User';
    public $accountLevelAPI = false;
    public $browseLimit = 100;
    public $browseOrder = ['ID' => 'DESC'];

    // MediaRequestHandler configuration
    public $defaultPage = 'browse';
    public $defaultThumbnailWidth = 100;
    public $defaultThumbnailHeight = 100;
    public $uploadFileFieldName = 'mediaFile';
    public $responseMode = 'html';

    public static $inputStream = 'php://input'; // this is a setting so that unit tests can provide a fake stream :)

    public $searchConditions = [
        'Caption' => [
            'qualifiers' => ['any','caption']
            ,'points' => 2
            ,'sql' => 'Caption LIKE "%%%s%%"',
        ]
        ,'CaptionLike' => [
            'qualifiers' => ['caption-like']
            ,'points' => 2
            ,'sql' => 'Caption LIKE "%s"',
        ]
        ,'CaptionNot' => [
            'qualifiers' => ['caption-not']
            ,'points' => 2
            ,'sql' => 'Caption NOT LIKE "%%%s%%"',
        ]
        ,'CaptionNotLike' => [
            'qualifiers' => ['caption-not-like']
            ,'points' => 2
            ,'sql' => 'Caption NOT LIKE "%s"',
        ],
    ];

    private ?ServerRequest $request;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;

        // handle json response mode
        if ($this->peekPath() == 'json') {
            $this->shiftPath();
            $this->responseBuilder = JsonBuilder::class;
        }

        // handle action
        switch ($action = $this->shiftPath()) {

            case 'upload':
                {
                    return $this->handleUploadRequest();
                }

            case 'open':
                {
                    $mediaID = $this->shiftPath();

                    return $this->handleMediaRequest($mediaID);
                }

            case 'download':
                {
                    $mediaID = $this->shiftPath();
                    if ($filename = $this->shiftPath()) {
                        $filename = urldecode($filename);
                    }
                    return $this->handleDownloadRequest($mediaID, $filename);
                }

            case 'info':
                {
                    $mediaID = $this->shiftPath();

                    return $this->handleInfoRequest($mediaID);
                }

            case 'caption':
                {
                    $mediaID = $this->shiftPath();

                    return $this->handleCaptionRequest($mediaID);
                }

            case 'delete':
                {
                    $mediaID = $this->shiftPath();
                    return $this->handleDeleteRequest($mediaID);
                }

            case 'thumbnail':
                {
                    return $this->handleThumbnailRequest();
                }

            case false:
            case '':
            case 'browse':
                {
                    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                        return $this->handleUploadRequest();
                    }

                    return $this->handleBrowseRequest();
                }

            default:
                {
                    if (ctype_digit($action)) {
                        return $this->handleMediaRequest($action);
                    } else {
                        return parent::handleRecordsRequest($action);
                    }
                }
        }
    }


    public function handleUploadRequest($options = []): ResponseInterface
    {
        $this->checkUploadAccess();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // init options
            $options = array_merge([
                'fieldName' => $this->uploadFileFieldName,
            ], $options);


            // check upload
            if (empty($_FILES[$options['fieldName']])) {
                return $this->throwUploadError('You did not select a file to upload');
            }

            // handle upload errors
            if ($_FILES[$options['fieldName']]['error'] != UPLOAD_ERR_OK) {
                switch ($_FILES[$options['fieldName']]['error']) {
                    case UPLOAD_ERR_NO_FILE:
                        return $this->throwUploadError('You did not select a file to upload');

                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        return $this->throwUploadError('Your file exceeds the maximum upload size. Please try again with a smaller file.');

                    case UPLOAD_ERR_PARTIAL:
                        return $this->throwUploadError('Your file was only partially uploaded, please try again.');

                    default:
                        return $this->throwUploadError('There was an unknown problem while processing your upload, please try again.');
                }
            }

            // init caption
            if (!isset($options['Caption'])) {
                if (!empty($_REQUEST['Caption'])) {
                    $options['Caption'] = $_REQUEST['Caption'];
                } else {
                    $options['Caption'] = preg_replace('/\.[^.]+$/', '', $_FILES[$options['fieldName']]['name']);
                }
            }

            // create media
            try {
                $Media = Media::createFromUpload($_FILES[$options['fieldName']]['tmp_name'], $options);
            } catch (Exception $e) {
                return $this->throwUploadError($e->getMessage());
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            $put = fopen(static::$inputStream, 'r'); // open input stream

            $tmp = tempnam('/tmp', 'dvr');  // use PHP to make a temporary file
            $fp = fopen($tmp, 'w'); // open write stream to temp file

            // write
            while ($data = fread($put, 1024)) {
                fwrite($fp, $data);
            }

            // close handles
            fclose($fp);
            fclose($put);

            // create media
            try {
                $Media = Media::createFromFile($tmp, $options);
            } catch (Exception $e) {
                return $this->throwUploadError('The file you uploaded is not of a supported media format');
            }
        } else {
            return $this->respond('upload');
        }

        // assign context
        if (!empty($_REQUEST['ContextClass']) && !empty($_REQUEST['ContextID'])) {
            if (!is_subclass_of($_REQUEST['ContextClass'], ActiveRecord::class)
                || !in_array($_REQUEST['ContextClass']::getStaticRootClass(), Media::$fields['ContextClass']['values'])
                || !is_numeric($_REQUEST['ContextID'])) {
                return $this->throwUploadError('Context is invalid');
            } elseif (!$Media->Context = $_REQUEST['ContextClass']::getByID($_REQUEST['ContextID'])) {
                return $this->throwUploadError('Context class not found');
            }

            $Media->save();
        }

        return $this->respond('uploadComplete', [
            'success' => (bool)$Media
            ,'data' => $Media,
        ]);
    }

    /**
     * Set caching headers
     *
     * @param Response $response
     * @return Response
     */
    public function setCache(Response $response): Response
    {
        $expires = 60*60*24*365;
        return $response->withHeader('Cache-Control', "public, max-age= $expires")
        ->withHeader('Expires', gmdate('D, d M Y H:i:s \G\M\T', time()+$expires))
        ->withHeader('Pragma', 'public');
    }

    public function respondWithMedia(Media $Media, $variant, $responseID, $responseData = []): ResponseInterface
    {
        if ($this->responseBuilder != MediaBuilder::class) {
            throw new Exception('Media responses require MediaBuilder for putting together a response.');
        }
        $className = $this->responseBuilder;
        $responseBuilder = new $className($responseID, $responseData);

        $responseBuilder->setContentType($Media->MIMEType);


        $size = filesize($responseID);
        $length = $size;
        $start = 0;
        $end = $size - 1;

        // interpret range requests
        $_server = $this->request->getServerParams();
        if (!empty($_server['HTTP_RANGE'])) {
            $chunkStart = $start;
            $chunkEnd = $end;

            list(, $range) = explode('=', $_server['HTTP_RANGE'], 2);

            if (strpos($range, ',') !== false) {
                $this->responseBuilder = EmptyBuilder::class;

                return $this->respondEmpty($responseID)
                    ->withStatus(416) // Range Not Satisfiable
                    ->withHeader('Content-Range', "bytes $start-$end/$size");
            }

            if ($range == '-') { // missing range start and end
                $range = '0-';
            }

            $range = explode('-', $range);
            $chunkStart = $range[0];
            $chunkEnd = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;


            $chunkEnd = ($chunkEnd > $end) ? $end : $chunkEnd;
            if ($chunkStart > $chunkEnd || $chunkStart > $size - 1 || $chunkEnd >= $size) {
                $this->responseBuilder = EmptyBuilder::class;

                return $this->respondEmpty($responseID)
                    ->withStatus(416) // Range Not Satisfiable
                    ->withHeader('Content-Range', "bytes $start-$end/$size");
            }

            $start = intval($chunkStart);
            $end = intval($chunkEnd);
            $length = $end - $start + 1;
            $responseBuilder->setRange($start, $end, $length);
        }


        $response = new MediaResponse($responseBuilder);
        $response = $this->setCache($response);

        // tell browser ranges are accepted
        $response = $response->withHeader('Accept-Ranges', 'bytes')
        // provide a unique ID for this media
        ->withHeader('ETag', 'media-'.$Media->ID.'-'.$variant);

        // if partial content provide proper response header
        if (isset($chunkStart)) {
            $response = $response->withStatus(206)
            ->withHeader('Content-Range', "bytes $start-$end/$size")
            ->withHeader('Content-Length', $length);
        } else {
            // range
            $filesize = filesize($Media->getFilesystemPath($variant));
            $end = $filesize - 1;
            $response = $response->withHeader('Content-Range', 'bytes 0-'.$end.'/'.$filesize)
                ->withHeader('Content-Length', $filesize);
        }

        return $response;
    }

    public function respondEmpty($responseID, $responseData = [])
    {
        if ($this->responseBuilder != EmptyBuilder::class) {
            throw new Exception('Media responses require MediaBuilder for putting together a response.');
        }
        $className = $this->responseBuilder;
        $responseBuilder = new $className($responseID, $responseData);
        $response = new EmptyResponse($responseBuilder);
        return $response;
    }


    public function handleMediaRequest($mediaID): ResponseInterface
    {
        if (empty($mediaID)) {
            return $this->throwNotFoundError();
        }

        // get media
        try {
            $Media = Media::getById($mediaID);
        } catch (Exception $e) {
            return $this->throwUnauthorizedError();
        }

        if (!$Media) {
            return $this->throwNotFoundError();
        }

        if (!$this->checkReadAccess($Media)) {
            return $this->throwNotFoundError();
        }


        $_server = $this->request->getServerParams();

        if (isset($_server['HTTP_ACCEPT'])) {
            if ($_server['HTTP_ACCEPT'] == 'application/json') {
                $this->responseBuilder = JsonBuilder::class;
            }
        }

        if ($this->responseBuilder == JsonBuilder::class) {
            return $this->respond('media', [
                'success' => true
                ,'data' => $Media,
            ]);
        } else {

            // determine variant
            if ($variant = $this->shiftPath()) {
                if (!$Media->isVariantAvailable($variant)) {
                    return $this->throwNotFoundError();
                }
            } else {
                $variant = 'original';
            }

            // initialize response
            $this->responseBuilder = MediaBuilder::class;
            set_time_limit(0);
            $filePath = $Media->getFilesystemPath($variant);

            // media are immutable for a given URL, so no need to actually check anything if the browser wants to revalidate its cache
            if (!empty($_server['HTTP_IF_NONE_MATCH']) || !empty($_server['HTTP_IF_MODIFIED_SINCE'])) {
                $this->responseBuilder = EmptyBuilder::class;
                $response = $this->respondEmpty($filePath);
                $response->withDefaults(304);

                return $response;
            }


            return $this->respondWithMedia($Media, $variant, $filePath);
        }
    }

    public function handleInfoRequest($mediaID): ResponseInterface
    {
        if (empty($mediaID) || !is_numeric($mediaID)) {
            $this->throwNotFoundError();
        }

        // get media
        try {
            $Media = Media::getById($mediaID);
        } catch (Exception $e) {
            return $this->throwUnauthorizedError();
        }

        if (!$Media) {
            return $this->throwNotFoundError();
        }

        if (!$this->checkReadAccess($Media)) {
            return $this->throwUnauthorizedError();
        }

        return parent::handleRecordRequest($Media);
    }

    public function handleDownloadRequest($media_id, $filename = false): ResponseInterface
    {
        if (empty($media_id) || !is_numeric($media_id)) {
            $this->throwNotFoundError();
        }

        // get media
        try {
            $Media = Media::getById($media_id);
        } catch (Exception $e) {
            return $this->throwUnauthorizedError();
        }


        if (!$Media) {
            return $this->throwNotFoundError();
        }

        if (!$this->checkReadAccess($Media)) {
            return $this->throwUnauthorizedError();
        }

        $filePath = $Media->getFilesystemPath('original');

        $this->responseBuilder = MediaBuilder::class;
        $response = $this->respondWithMedia($Media, 'original', $filePath);

        $response = $response->withHeader('Content-Disposition', 'attachment; filename="'.($filename ? $filename : $filePath).'"');

        return $response;
    }

    public function handleCaptionRequest($media_id): ResponseInterface
    {
        // require authentication
        $GLOBALS['Session']->requireAccountLevel('Staff');

        if (empty($media_id) || !is_numeric($media_id)) {
            return $this->throwNotFoundError();
        }

        // get media
        try {
            $Media = Media::getById($media_id);
        } catch (Exception $e) {
            return $this->throwUnauthorizedError();
        }


        if (!$Media) {
            $this->throwNotFoundError();
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $Media->Caption = $_REQUEST['Caption'];
            $Media->save();

            return $this->respond('mediaCaptioned', [
                'success' => true
                ,'data' => $Media,
            ]);
        }

        return $this->respond('mediaCaption', [
            'data' => $Media,
        ]);
    }

    public function handleDeleteRequest(ActiveRecord $Record): ResponseInterface
    {
        // require authentication
        $GLOBALS['Session']->requireAccountLevel('Staff');

        if ($mediaID = $this->peekPath()) {
            $mediaIDs = [$mediaID];
        } elseif (!empty($_REQUEST['mediaID'])) {
            $mediaIDs = [$_REQUEST['mediaID']];
        } elseif (is_array($_REQUEST['media'])) {
            $mediaIDs = $_REQUEST['media'];
        }

        $deleted = [];
        foreach ($mediaIDs as $mediaID) {
            if (!is_numeric($mediaID)) {
                continue;
            }

            // get media
            $Media = Media::getByID($mediaID);

            if (!$Media) {
                return $this->throwNotFoundError();
            }

            if ($Media->destroy()) {
                $deleted[] = $Media;
            }
        }

        return $this->respond('mediaDeleted', [
            'success' => true
            ,'data' => $deleted,
        ]);
    }

    public function handleThumbnailRequest(Media $Media = null): ResponseInterface
    {
        // send caching headers
        if (!headers_sent()) {
            // @codeCoverageIgnoreStart
            $expires = 60*60*24*365;
            header("Cache-Control: public, max-age=$expires");
            header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+$expires));
            header('Pragma: public');
            // @codeCoverageIgnoreEnd
        }

        // thumbnails are immutable for a given URL, so no need to actually check anything if the browser wants to revalidate its cache
        if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) || !empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            header('HTTP/1.0 304 Not Modified');
            exit();
        }

        // get media
        if (!$Media) {
            if (!$mediaID = $this->shiftPath()) {
                return $this->throwNotFoundError();
            } elseif (!$Media = Media::getByID($mediaID)) {
                return $this->throwNotFoundError();
            }
        }

        // get format
        if (preg_match('/^(\d+)x(\d+)(x([0-9A-F]{6})?)?$/i', $this->peekPath(), $matches)) {
            $this->shiftPath();
            $maxWidth = $matches[1];
            $maxHeight = $matches[2];
            $fillColor = !empty($matches[4]) ? $matches[4] : false;
        } else {
            $maxWidth = $this->defaultThumbnailWidth;
            $maxHeight = $this->defaultThumbnailHeight;
            $fillColor = false;
        }

        if ($this->peekPath() == 'cropped') {
            $this->shiftPath();
            $cropped = true;
        } else {
            $cropped = false;
        }

        // get thumbnail media
        try {
            $thumbPath = $Media->getThumbnail($maxWidth, $maxHeight, $fillColor, $cropped);
        } catch (Exception $e) {
            return $this->throwNotFoundError();
        }

        // emit
        if (!headers_sent()) {
            // @codeCoverageIgnoreStart
            header("ETag: media-$Media->ID-$maxWidth-$maxHeight-$fillColor-$cropped");
            header("Content-Type: $Media->ThumbnailMIMEType");
            header('Content-Length: '.filesize($thumbPath));
            readfile($thumbPath);
            // @codeCoverageIgnoreEnd
        }
        exit();
    }


    public function handleBrowseRequest($options = [], $conditions = [], $responseID = null, $responseData = []): ResponseInterface
    {
        // apply tag filter
        if (!empty($_REQUEST['tag'])) {
            // get tag
            if (!$Tag = Tag::getByHandle($_REQUEST['tag'])) {
                return $this->throwNotFoundError();
            }

            $conditions[] = 'ID IN (SELECT ContextID FROM tag_items WHERE TagID = '.$Tag->ID.' AND ContextClass = "Product")';
        }


        // apply context filter
        if (!empty($_REQUEST['ContextClass'])) {
            $conditions['ContextClass'] = $_REQUEST['ContextClass'];
        }

        if (!empty($_REQUEST['ContextID']) && is_numeric($_REQUEST['ContextID'])) {
            $conditions['ContextID'] = $_REQUEST['ContextID'];
        }

        return parent::handleBrowseRequest($options, $conditions, $responseID, $responseData);
    }



    public function handleMediaDeleteRequest(): ResponseInterface
    {
        // sanity check
        if (empty($_REQUEST['media']) || !is_array($_REQUEST['media'])) {
            return $this->throwNotFoundError();
        }

        // retrieve photos
        $media_array = [];
        foreach ($_REQUEST['media'] as $media_id) {
            if (!is_numeric($media_id)) {
                return $this->throwNotFoundError();
            }

            if ($Media = Media::getById($media_id)) {
                $media_array[$Media->ID] = $Media;

                if (!$this->checkWriteAccess($Media)) {
                    return $this->throwUnauthorizedError();
                }
            }
        }

        // delete
        $deleted = [];
        foreach ($media_array as $media_id => $Media) {
            if ($Media->delete()) {
                $deleted[] = $media_id;
            }
        }

        return $this->respond('mediaDeleted', [
            'success' => true
            ,'deleted' => $deleted,
        ]);
    }

    public function checkUploadAccess()
    {
        return true;
    }

    public function throwUploadError($error): ResponseInterface
    {
        return $this->respond('error', [
            'success' => false,
            'failed' => [
                'errors'	=>	$error,
            ],
        ]);
    }
}
