<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Tests\Controllers;

use Divergence\App;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Stream;
use Divergence\Routing\Path;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use Divergence\Models\Media\Media;
use Divergence\Responders\Emitter;
use GuzzleHttp\Psr7\ServerRequest;
use Divergence\Controllers\MediaRequestHandler;

class MediaRequestHandlerTest extends TestCase
{
    public function tearDown(): void
    {
        foreach (scandir(App::$App->ApplicationPath.'/media/original/') as $file) {
            if (in_array($file, ['.','..'])) {
                unlink(realpath(App::$App->ApplicationPath.'/media/original/'.$file));
            }
        }

        unlink(realpath(App::$App->ApplicationPath.'/media/original/'));
        unlink(realpath(App::$App->ApplicationPath.'/media/'));
    }
    public function testEmptyUpload()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"You did not select a file to upload"}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrNoFile()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_NO_FILE,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => '',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"You did not select a file to upload"}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrIniSize()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_INI_SIZE,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => '',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"Your file exceeds the maximum upload size. Please try again with a smaller file."}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrPartial()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_PARTIAL,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => '',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"Your file was only partially uploaded, please try again."}}');
        (new Emitter($response))->emit();
    }

    public function testUploadErrUnknown()
    {
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_EXTENSION,
            'tmp_name' => '/tmp/uploadedFile8190',
            'size' => 4096,
            'name' => 'example.jpg',
            'type' => '',
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString('{"success":false,"failed":{"errors":"There was an unknown problem while processing your upload, please try again."}}');
        (new Emitter($response))->emit();
    }

    /*public function testUploadPNG()
    {
        $PNG = realpath(App::$App->ApplicationPath . 'tests/assets/logo.png');
        //dump($PNG);
        $_FILES['test'] = [
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $PNG,
            'size' => filesize($PNG),
            'name' => 'logo.png',
            'type' => ''
        ];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        //$this->expectOutputString('{"success":false,"failed":{"errors":"The file you uploaded is not of a supported media format"}}');
        (new Emitter($response))->emit();
    }*/

    public function testCreatePNGFromFile()
    {
        $tempName = tempnam('/tmp', 'testmedia').'.png';
        $PNG = realpath(App::$App->ApplicationPath . '/tests/assets/logo.png');
        copy($PNG, $tempName);
        $media = Media::createFromFile($tempName);
        $_SERVER['REQUEST_METHOD'] = 'POST';

        App::$App->Path = new Path('/json/'.$media->ID);
        $controller = new MediaRequestHandler();
        $controller->uploadFileFieldName = 'test';
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->expectOutputString(json_encode(['success'=>true,'data'=>$media]));
        (new Emitter($response))->emit();
    }

    public function testCreatePNGFromPUT()
    {
        $PNG = realpath(App::$App->ApplicationPath . '/tests/assets/logo.png');
        vfsStream::setup('input', null, ['data' => file_get_contents($PNG)]);
        MediaRequestHandler::$inputStream = 'vfs://input/data';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(2);
        $this->expectOutputString(json_encode(['success'=>true,'data'=>$media]));
        (new Emitter($response))->emit();
    }

    public function testGetAllMedia()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        App::$App->Path = new Path('/json/');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getAll([
            'order' => [
                'ID' => 'DESC',
            ],
        ]);
        $this->expectOutputString(json_encode(['success'=>true,'data'=>$media,'conditions'=>[],'total'=>count($media),'limit'=>false,'offset'=>false]));
        (new Emitter($response))->emit();
    }

    public function testGetInfo()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        App::$App->Path = new Path('/json/info/1');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(1);
        $this->expectOutputString(json_encode(['success'=>true,'data'=>$media]));
        (new Emitter($response))->emit();
    }

    public function testReadMedia()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        App::$App->Path = new Path('/1');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(1);
        $this->expectOutputString(file_get_contents($media->getFilesystemPath('original')));
        $emitter = new Emitter($response);
        $this->assertEquals('image/png', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('public, max-age= 31536000', $response->getHeader('Cache-Control')[0]);
        $this->assertEquals(gmdate('D, d M Y H:i:s \G\M\T', time()+60*60*24*365), $response->getHeader('Expires')[0]);
        $this->assertEquals('public', $response->getHeader('Pragma')[0]);
        $emitter->emit();
    }

    public function testReadThumbnail()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        App::$App->Path = new Path('/thumbnail/1');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(1);
        $this->expectOutputString(file_get_contents($media->getFilesystemPath('100x100')));
        $emitter = new Emitter($response);
        $this->assertEquals('image/png', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('public, max-age= 31536000', $response->getHeader('Cache-Control')[0]);
        $this->assertEquals(gmdate('D, d M Y H:i:s \G\M\T', time()+60*60*24*365), $response->getHeader('Expires')[0]);
        $this->assertEquals('public', $response->getHeader('Pragma')[0]);
        $emitter->emit();
        $size = getimagesizefromstring(file_get_contents($media->getFilesystemPath('100x100')));
        $this->assertEquals([100,100,3,'width="100" height="100"',"bits"=>8,"mime"=>"image/png"], $size);
    }

    public function testReadThumbnail10x10()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        App::$App->Path = new Path('/thumbnail/1/10x10');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(1);
        $this->expectOutputString(file_get_contents($media->getFilesystemPath('10x10')));
        $emitter = new Emitter($response);
        $this->assertEquals('image/png', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('public, max-age= 31536000', $response->getHeader('Cache-Control')[0]);
        $this->assertEquals(gmdate('D, d M Y H:i:s \G\M\T', time()+60*60*24*365), $response->getHeader('Expires')[0]);
        $this->assertEquals('public', $response->getHeader('Pragma')[0]);
        $emitter->emit();
        $size = getimagesizefromstring(file_get_contents($media->getFilesystemPath('10x10')));
        $this->assertEquals([10,10,3,'width="10" height="10"',"bits"=>8,"mime"=>"image/png"], $size);
    }

    public function testReadThumbnail25()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        App::$App->Path = new Path('/thumbnail/1/25x25');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(1);
        $this->expectOutputString(file_get_contents($media->getFilesystemPath('25x25')));
        $emitter = new Emitter($response);
        $this->assertEquals('image/png', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('public, max-age= 31536000', $response->getHeader('Cache-Control')[0]);
        $this->assertEquals(gmdate('D, d M Y H:i:s \G\M\T', time()+60*60*24*365), $response->getHeader('Expires')[0]);
        $this->assertEquals('public', $response->getHeader('Pragma')[0]);
        $emitter->emit();
        $size = getimagesizefromstring(file_get_contents($media->getFilesystemPath('25x25')));
        $this->assertEquals([25,25,3,'width="25" height="25"',"bits"=>8,"mime"=>"image/png"], $size);
    }

    public function testHttpConditional()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_NONE_MATCH'] = true;
        App::$App->Path = new Path('/1');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('Not Modified', $response->getReasonPhrase());
        $this->assertEquals(304, $response->getStatusCode());
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
    }

    public function testHttpConditionalSecondary()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = true;
        App::$App->Path = new Path('/1');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('Not Modified', $response->getReasonPhrase());
        $this->assertEquals(304, $response->getStatusCode());
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }

    public function testCreateMP4FromPUT()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        vfsStream::setup('input', null, ['data' => file_get_contents($mp4)]);
        MediaRequestHandler::$inputStream = 'vfs://input/data';
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        App::$App->Path = new Path('/json/upload');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $media = Media::getByID(3);
        $this->expectOutputString(json_encode(['success'=>true,'data'=>$media]));
        (new Emitter($response))->emit();
    }

    public function file_get_contents_at_seek(int $seek, string $file)
    {
        $fp = new Stream(fopen($file, 'r'));
        $fp->seek($seek);
        $amountToRead = 0;
        if (!$amountToRead) {
            $amountToRead = $fp->getSize();
        }

        $responseChunkSize = 4096;

        $output = '';

        if ($amountToRead) {
            while ($amountToRead > 0 && !$fp->eof()) {
                $length = min($responseChunkSize, $amountToRead);
                $data = $fp->read($length);
                
                $output .= $data;

                $amountToRead -= strlen($data);

                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        } else {
            while (!$fp->eof()) {
                echo $fp->read($responseChunkSize);
                if (connection_status() !== CONNECTION_NORMAL) {
                    break;
                }
            }
        }
        return $output;
    }

    /**
     *
     * Returns entire file as download
     */
    public function testDownload()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        App::$App->Path = new Path('/download/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('video/mp4', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('media-3-original', $response->getHeader('ETag')[0]);
        $this->assertEquals('bytes 0-1062814/1062815', $response->getHeader('Content-Range')[0]);
        $this->assertEquals('1062815', $response->getHeader('Content-Length')[0]);

        $realfile = realpath(App::$App->ApplicationPath . '/media/original/3.mp4');

        $this->assertEquals('attachment; filename="'.$realfile.'"', $response->getHeader('Content-Disposition')[0]);

        $expectedOutput = $this->file_get_contents_at_seek(0, $realfile);
        $this->expectOutputString($expectedOutput);
        (new Emitter($response))->emit();
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Returns entire file
     */
    public function testHTTPRangeZeroDash()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SERVER['HTTP_RANGE'] = 'bytes=0-';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('video/mp4', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('media-3-original', $response->getHeader('ETag')[0]);
        $this->assertEquals('bytes 0-1062814/1062815', $response->getHeader('Content-Range')[0]);
        $this->assertEquals('1062815', $response->getHeader('Content-Length')[0]);

        $expectedOutput = $this->file_get_contents_at_seek(0, $mp4); 
        $this->expectOutputString($expectedOutput);
        (new Emitter($response))->emit();
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Should only return last byte
     */
    public function testHTTPRangeLastByte()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SERVER['HTTP_RANGE'] = 'bytes=1062814-';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('video/mp4', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('media-3-original', $response->getHeader('ETag')[0]);
        $this->assertEquals('bytes 1062814-1062814/1062815', $response->getHeader('Content-Range')[0]);
        $this->assertEquals('1', $response->getHeader('Content-Length')[0]);

        $expectedOutput = $this->file_get_contents_at_seek(1062814, $mp4);
        $this->expectOutputString($expectedOutput);
        (new Emitter($response))->emit();
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Returns second half of the file
     */
    public function testHTTPRangeSecondHalf()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $half = floor(filesize($mp4)/2);
        $_SERVER['HTTP_RANGE'] = 'bytes='.$half.'-';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('video/mp4', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('media-3-original', $response->getHeader('ETag')[0]);
        $this->assertEquals('bytes 531407-1062814/1062815', $response->getHeader('Content-Range')[0]);
        $this->assertEquals('531408', $response->getHeader('Content-Length')[0]);

        $expectedOutput = $this->file_get_contents_at_seek(531407, $mp4);
        $this->expectOutputString($expectedOutput);
        (new Emitter($response))->emit();
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Returns second half of the file
     */
    public function testHTTPRangeAfterArbitraryValue()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $half = floor(filesize($mp4)/2);
        $_SERVER['HTTP_RANGE'] = 'bytes=720896-';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('video/mp4', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('media-3-original', $response->getHeader('ETag')[0]);
        $this->assertEquals('bytes 720896-1062814/1062815', $response->getHeader('Content-Range')[0]);
        $this->assertEquals('341919', $response->getHeader('Content-Length')[0]);

        $expectedOutput = $this->file_get_contents_at_seek(720896, $mp4);
        $this->expectOutputString($expectedOutput);
        (new Emitter($response))->emit();
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Returns second half of the file
     */
    public function testHTTPRangeOutOfBounds()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SERVER['HTTP_RANGE'] = 'bytes=99999999-';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());

        // response will still give us the valid byte range
        $this->assertEquals('bytes 0-1062814/1062815', $response->getHeader('Content-Range')[0]);

        // but also give a 416
        $this->assertEquals(416, $response->getStatusCode());
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Returns second half of the file
     */
    public function testHTTPRangeMalformed()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SERVER['HTTP_RANGE'] = 'bytes=-';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        $this->assertEquals('video/mp4', $response->getHeader('Content-Type')[0]);
        $this->assertEquals('media-3-original', $response->getHeader('ETag')[0]);
        $this->assertEquals('bytes 0-1062814/1062815', $response->getHeader('Content-Range')[0]);
        $this->assertEquals('1062815', $response->getHeader('Content-Length')[0]);

        $expectedOutput = $this->file_get_contents_at_seek(0, $mp4);
        $this->expectOutputString($expectedOutput);
        (new Emitter($response))->emit();
    }

    /**
     * @see https://www.zeng.dev/post/2023-http-range-and-play-mp4-in-browser/
     *
     * Not supported yet so we return a 416
     */
    public function testHTTPRangeMultiPart()
    {
        $mp4 = realpath(App::$App->ApplicationPath . '/tests/assets/bunny.mp4');
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SERVER['HTTP_RANGE'] = 'bytes=0-500,700-800';
        App::$App->Path = new Path('/3');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());
        // response will still give us the valid byte range
        $this->assertEquals('bytes 0-1062814/1062815', $response->getHeader('Content-Range')[0]);

        // but also give a 416
        $this->assertEquals(416, $response->getStatusCode());
    }

    public function testNotFound()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $_SERVER['HTTP_RANGE'] = 'bytes=0-500,700-800';
        App::$App->Path = new Path('/json/999');
        $controller = new MediaRequestHandler();
        $response = $controller->handle(ServerRequest::fromGlobals());

        $this->expectOutputString(json_encode(['success'=>false,'failed'=>['errors'=>'Record not found.']]));
        (new Emitter($response))->emit();
    }
}
