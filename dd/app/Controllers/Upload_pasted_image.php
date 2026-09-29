<?php

namespace App\Controllers;

use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;

class Upload_pasted_image extends Security_Controller {

    function __construct() {
        parent::__construct();
    }

    function index() {
        show_404();
    }

    function save() {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405);
        }

        $userId = (int)($this->login_user->id ?? 0);
        $ipHash = hash('sha256', (string)$this->request->getIPAddress());
        if (!service('throttler')->check('pasted_image_' . $userId . '_' . $ipHash, 20, 60)) {
            return $this->response->setStatusCode(429)->setBody('Upload rejected.');
        }

        $file = $this->request->getFile('file');
        if (!$file) {
            return $this->response->setStatusCode(422)->setBody('Upload rejected.');
        }

        $storedPath = '';
        try {
            $security = new Upload_security();
            $metadata = $security->storeUploadedFile(
                $file,
                WRITEPATH . 'uploads/secure_temp/user_' . $userId,
                Upload_security::CONTEXT_IMAGE,
                'paste_'
            );
            $storedPath = $metadata['path'];
            register_secure_temp_upload($metadata['original_name'], $metadata, Upload_security::CONTEXT_IMAGE);
            $timelineFilePath = get_setting('timeline_file_path');
            $fileInfo = move_temp_file(
                $metadata['original_name'],
                $timelineFilePath,
                'pasted_image',
                null,
                '',
                '',
                false,
                $metadata['size_bytes']
            );
            if (!$fileInfo) {
                throw new UploadSecurityException('The image could not be stored.');
            }

            $newFileName = (string)get_array_value($fileInfo, 'file_name');
            $fullSize = (bool)$this->request->getPost('full_size_image');
            $url = get_source_url_of_file($fileInfo, $timelineFilePath, 'thumbnail', false, false, $fullSize);
            $safeUrl = htmlspecialchars((string)$url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $safeName = htmlspecialchars($newFileName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return $this->response
                ->setContentType('text/html')
                ->setBody('<span class="timeline-images inline-block"><img class="pasted-image" src="'
                    . $safeUrl . '" alt="' . $safeName . '"/></span>');
        } catch (\Throwable $e) {
            log_message('notice', 'Pasted image upload rejected.');
            if ($storedPath !== '' && is_file($storedPath)) {
                @unlink($storedPath);
            }
            return $this->response->setStatusCode(422)->setBody('Upload rejected.');
        }

        /* Legacy raw upload flow retired.
        if (!(isset($_FILES['file']) && $_FILES['file']['error'] == 0)) {
            //no file found
            return false;
        }

        $full_size_image = $this->request->getPost('full_size_image');

        $file = get_array_value($_FILES, "file");
        $temp_file = get_array_value($file, "tmp_name");
        $file_name = get_array_value($file, "name");
        $file_size = get_array_value($file, "size");

        if (!is_viewable_image_file($file_name)) {
            //not an image file
            return false;
        }

        $image_name = "image_" . make_random_string(5) . ".png";
        $timeline_file_path = get_setting("timeline_file_path");

        $file_info = move_temp_file($image_name, $timeline_file_path, "pasted_image", $temp_file, "", "", false, $file_size);
        if (!$file_info) {
            // couldn't upload it
            return false;
        }

        $new_file_name = get_array_value($file_info, 'file_name');
        $url = get_source_url_of_file($file_info, $timeline_file_path, "thumbnail", false, false, $full_size_image ? true : false);

        echo "<span class='timeline-images inline-block'><img class='pasted-image' src='$url' alt='$new_file_name'/></span>";
        */
    }
}
