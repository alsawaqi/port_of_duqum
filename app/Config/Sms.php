<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Sms extends BaseConfig
{
    public bool $enabled = false;
    public string $endpoint = 'https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx';
    public string $userId = '';
    public string $password = '';
    public string $header = '';

    public function __construct()
    {
        parent::__construct();
        $this->enabled = filter_var(env('ISMARTSMS_ENABLED', false), FILTER_VALIDATE_BOOL);
        $this->userId = trim((string) env('ISMARTSMS_USER_ID', ''));
        $this->password = (string) env('ISMARTSMS_PASSWORD', '');
        $this->header = trim((string) env('ISMARTSMS_HEADER', ''));
    }
}
