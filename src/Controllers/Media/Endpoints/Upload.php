<?php

namespace Divergence\Controllers\Media\Endpoints;

use Divergence\Controllers\Media\AbstractMediaEndpoint;
use Divergence\Controllers\MediaRequestHandler;
use Divergence\Models\ActiveRecord;
use Divergence\Models\Media\Media;
use Exception;
use Psr\Http\Message\ResponseInterface;

class Upload extends AbstractMediaEndpoint
{
    protected MediaRequestHandler $handler;

    public function __construct(MediaRequestHandler $handler)
    {
        $this->handler = $handler;
    }

    public function handle(...$arguments): ResponseInterface
    {
        [$options] = array_pad($arguments, 1, []);

        $this->handler->checkUploadAccess();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $options = array_merge([
                'fieldName' => $this->handler->uploadFileFieldName,
            ], $options);

            if (empty($_FILES[$options['fieldName']])) {
                return $this->handler->throwUploadError('You did not select a file to upload');
            }

            if ($_FILES[$options['fieldName']]['error'] != UPLOAD_ERR_OK) {
                switch ($_FILES[$options['fieldName']]['error']) {
                    case UPLOAD_ERR_NO_FILE:
                        return $this->handler->throwUploadError('You did not select a file to upload');
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        return $this->handler->throwUploadError('Your file exceeds the maximum upload size. Please try again with a smaller file.');
                    case UPLOAD_ERR_PARTIAL:
                        return $this->handler->throwUploadError('Your file was only partially uploaded, please try again.');
                    default:
                        return $this->handler->throwUploadError('There was an unknown problem while processing your upload, please try again.');
                }
            }

            if (!isset($options['Caption'])) {
                if (!empty($_REQUEST['Caption'])) {
                    $options['Caption'] = $_REQUEST['Caption'];
                } else {
                    $options['Caption'] = preg_replace('/\.[^.]+$/', '', $_FILES[$options['fieldName']]['name']);
                }
            }

            try {
                $Media = Media::createFromUpload($_FILES[$options['fieldName']]['tmp_name'], $options);
            } catch (Exception $e) {
                return $this->handler->throwUploadError($e->getMessage());
            }
        } elseif ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            $put = fopen($this->handler::$inputStream, 'r');
            $tmp = tempnam('/tmp', 'dvr');
            $fp = fopen($tmp, 'w');

            while ($data = fread($put, 1024)) {
                fwrite($fp, $data);
            }

            fclose($fp);
            fclose($put);

            try {
                $Media = Media::createFromFile($tmp, $options);
            } catch (Exception $e) {
                return $this->handler->throwUploadError('The file you uploaded is not of a supported media format');
            }
        } else {
            return $this->handler->respond('upload');
        }

        if (!empty($_REQUEST['ContextClass']) && !empty($_REQUEST['ContextID'])) {
            if (!is_subclass_of($_REQUEST['ContextClass'], ActiveRecord::class)
                || !in_array($_REQUEST['ContextClass']::getStaticRootClass(), Media::$fields['ContextClass']['values'])
                || !is_numeric($_REQUEST['ContextID'])) {
                return $this->handler->throwUploadError('Context is invalid');
            } elseif (!$Media->Context = $_REQUEST['ContextClass']::getByID($_REQUEST['ContextID'])) {
                return $this->handler->throwUploadError('Context class not found');
            }

            $Media->save();
        }

        return $this->handler->respond('uploadComplete', [
            'success' => (bool) $Media,
            'data' => $Media,
        ]);
    }
}
