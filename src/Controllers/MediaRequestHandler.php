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

use Divergence\Controllers\Media\Endpoints\Browse;
use Divergence\Controllers\Media\Endpoints\Caption;
use Divergence\Controllers\Media\Endpoints\Create;
use Divergence\Controllers\Media\Endpoints\Delete;
use Divergence\Controllers\Media\Endpoints\Download;
use Divergence\Controllers\Media\Endpoints\Edit;
use Divergence\Controllers\Media\Endpoints\Info;
use Divergence\Controllers\Media\Endpoints\Media as MediaEndpoint;
use Divergence\Controllers\Media\Endpoints\MediaDelete;
use Divergence\Controllers\Media\Endpoints\MultiDestroy;
use Divergence\Controllers\Media\Endpoints\MultiSave;
use Divergence\Controllers\Media\Endpoints\Record;
use Divergence\Controllers\Media\Endpoints\Thumbnail;
use Divergence\Controllers\Media\Endpoints\Upload;
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

/**
 * @method ResponseInterface handleUploadRequest()
 * @method ResponseInterface handleMediaRequest($mediaID)
 * @method ResponseInterface handleInfoRequest($mediaID)
 * @method ResponseInterface handleDownloadRequest($mediaID, $filename = null)
 * @method ResponseInterface handleCaptionRequest($mediaID)
 * @method ResponseInterface handleThumbnailRequest()
 * @method ResponseInterface handleMediaBrowseRequest($options = [], $conditions = [], $responseID = null, $responseData = [])
 * @method ResponseInterface handleMediaDeleteRequest($mediaID = null)
 */
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

    public function __construct()
    {
        parent::__construct();
        $this->registerMediaEndpointClasses();
    }

    protected function registerMediaEndpointClasses(): void
    {
        foreach ([
            Upload::class,
            MediaEndpoint::class,
            Info::class,
            Download::class,
            Caption::class,
            Thumbnail::class,
            [Browse::class, 'handleMediaBrowseRequest'],
            MediaDelete::class,
        ] as $registration) {
            if (is_array($registration)) {
                [$className, $endpointName] = $registration;
                $this->registerEndpointClass($className, $endpointName);
                continue;
            }

            $this->registerEndpointClass($registration);
        }
    }

    public function getRequest(): ?ServerRequest
    {
        return $this->request;
    }

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
                    return $this->handleMediaDeleteRequest($mediaID);
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

                    return $this->handleMediaBrowseRequest();
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


    public function respondRangeNotSatisfiable(string $responseID, int $start, int $end, int $size): Response
    {
        $this->responseBuilder = EmptyBuilder::class;

        return $this->respondEmpty($responseID)
            ->withStatus(416) // Range Not Satisfiable
            ->withHeader('Content-Range', "bytes $start-$end/$size");
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

            // comma indicates multiple ranges which we currently do not support
            if (strpos($range, ',') !== false) {
                return $this->respondRangeNotSatisfiable($responseID, $start, $end, $size);
            }

            if ($range == '-') { // missing range start and end
                $range = '0-';
            }

            $range = explode('-', $range);
            $chunkStart = $range[0];
            $chunkEnd = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;


            $chunkEnd = ($chunkEnd > $end) ? $end : $chunkEnd;

            // requested content out of bounds
            if ($chunkStart > $chunkEnd || $chunkStart > $size - 1 || $chunkEnd >= $size) {
                return $this->respondRangeNotSatisfiable($responseID, $start, $end, $size);
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
            // only send 206 if response is less than the whole file
            if ($end-$start+1<$size) {
                $response = $response->withStatus(206);
            }
            $response = $response->withHeader('Content-Range', "bytes $start-$end/$size")
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

    public function respondWithThumbnail(Media $Media, $variant, $responseID, $responseData = []): ResponseInterface
    {
        if ($this->responseBuilder != MediaBuilder::class) {
            throw new Exception('Media responses require MediaBuilder for putting together a response.');
        }
        $className = $this->responseBuilder;
        $responseBuilder = new $className($responseID, $responseData);

        $responseBuilder->setContentType($Media->ThumbnailMIMEType);

        $response = new MediaResponse($responseBuilder);
        $response = $this->setCache($response);

        $response = $response->withHeader('ETag', "media-$Media->ID-$variant")
            ->withHeader('Content-Length', filesize($responseID));

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
