<?php

namespace App\Controllers;

use App\Libraries\Google;
use App\Libraries\StreamResponse;
use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;

class Uploader extends Security_Controller {

    function __construct() {
        parent::__construct();
    }

    function index() {
        show_404();
    }

    function upload_file() {
        return $this->_handleUpload(Upload_security::CONTEXT_GENERIC, false);
    }

    function upload_excel_import_file() {
        return $this->_handleUpload(Upload_security::CONTEXT_SPREADSHEET, true);
    }

    function validate_file() {
        return $this->_validateClaim(Upload_security::CONTEXT_GENERIC);
    }

    function validate_image_file() {
        return $this->_validateClaim(Upload_security::CONTEXT_IMAGE);
        /* Legacy client-name-only validation retired.
        $file_name = $this->request->getPost("file_name");
        if (!is_valid_file_to_upload($file_name)) {
            echo json_encode(array("success" => false, 'message' => app_lang('invalid_file_type')));
            exit();
        }

        if (is_image_file($file_name)) {
            echo json_encode(array("success" => true));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('please_upload_valid_image_files')));
        }
        */
    }

    function stream_google_drive_file($file_id, $file_name, $sufix="full") {
        $file_id = trim((string) $file_id);
        $file_name = trim((string) $file_name);
        $sufix = strtolower(trim((string) $sufix));
        $expires = (int) $this->request->getGet('expires');
        $signature = trim((string) $this->request->getGet('signature'));
        if (
            preg_match('/\A[A-Za-z0-9_-]{10,200}\z/D', $file_id) !== 1
            || !in_array($sufix, ['full', 'raw', 'thumbnail'], true)
            || !verify_google_drive_stream_signature(
                $signature,
                $file_id,
                $file_name,
                $sufix,
                $expires,
                (int) $this->login_user->id
            )
        ) {
            show_404();
        }

        if (!is_valid_file_to_upload($file_name)) {
            echo json_encode(array("success" => false, 'message' => app_lang('invalid_file_type')));
            exit();
        }

        $google = new Google();
        $file_data = $google->get_file_content($file_id);
        if (is_array($file_data)) {
            if (isset($file_data["mime_type"])) {
                return $this->_download($file_name, $file_data["contents"], true)
                    ->setHeader('Cache-Control', 'private, no-store')
                    ->setHeader('X-Content-Type-Options', 'nosniff');
            } else if (isset($file_data['error']['message'])) {
                show_404();
            }
        }
    }

    private function _handleUpload(string $defaultContext, bool $localOnly) {
        if ($guard = $this->_guardRequest('upload', 30, 60)) {
            return $guard;
        }

        try {
            $context = $this->_resolveContext($defaultContext);
            $result = upload_file_to_temp($localOnly, $context);

            return $this->response->setJSON([
                'success' => true,
                'file_name' => $result['file_name'],
                'size_bytes' => $result['size_bytes'],
            ]);
        } catch (UploadSecurityException $e) {
            log_message('notice', 'Secure upload rejected: ' . $e->getMessage());
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => app_lang('invalid_file_type'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Secure upload failed: {exception}', ['exception' => $e]);
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => app_lang('error_occurred'),
            ]);
        }
    }

    private function _validateClaim(string $defaultContext) {
        if ($guard = $this->_guardRequest('validate', 60, 60)) {
            return $guard;
        }

        try {
            $context = $this->_resolveContext($defaultContext);
            (new Upload_security())->validateClientClaim(
                (string)$this->request->getPost('file_name'),
                (int)$this->request->getPost('file_size'),
                $context,
                secure_upload_configured_extensions($context)
            );

            return $this->response->setJSON([
                'success' => true,
                'upload_context' => $context,
                'upload_context_token' => secure_upload_context_token($context),
            ]);
        } catch (UploadSecurityException $e) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => app_lang('invalid_file_type'),
            ]);
        }
    }

    private function _resolveContext(string $defaultContext): string {
        $requested = trim((string)$this->request->getPost('upload_context'));
        if ($requested === '') {
            return $defaultContext;
        }

        $allowed = [
            Upload_security::CONTEXT_GENERIC,
            Upload_security::CONTEXT_IMAGE,
            Upload_security::CONTEXT_SPREADSHEET,
            Upload_security::CONTEXT_SECURITY_DOCUMENT,
        ];
        $token = (string)$this->request->getPost('upload_context_token');
        if (!in_array($requested, $allowed, true)
            || !verify_secure_upload_context_token($token, $requested)
        ) {
            throw new UploadSecurityException('The upload context is invalid.');
        }

        return $requested;
    }

    private function _guardRequest(string $action, int $limit, int $seconds) {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON([
                'success' => false,
                'message' => 'Method not allowed.',
            ]);
        }

        $userId = (int)($this->login_user->id ?? 0);
        if ($userId < 1) {
            return $this->response->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Authentication required.',
            ]);
        }

        $ipHash = hash('sha256', (string)$this->request->getIPAddress());
        $key = 'secure_upload_' . $action . '_' . $userId . '_' . $ipHash;
        $throttler = service('throttler');
        if (!$throttler->check($key, $limit, $seconds)) {
            return $this->response
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string)max(1, $throttler->getTokenTime()))
                ->setJSON([
                    'success' => false,
                    'message' => 'Too many upload attempts. Please wait and try again.',
                ]);
        }

        return null;
    }

    private function _download(string $file_name = '', $data = '', bool $setMime = false) {
        if ($file_name === '' || $data === '') {
            return null;
        }

        $file_path = '';
        if ($data === null) {
            $file_path = $file_name;
            $file_name = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $file_name));
            $file_name = end($file_name);
        }

        $response = new StreamResponse($file_name, $setMime);

        if ($file_path !== '') {
            $response->setFilePath($file_path);
        } elseif ($data !== null) {
            $response->setBinary($data);
        }

        return $response;
    }
}
