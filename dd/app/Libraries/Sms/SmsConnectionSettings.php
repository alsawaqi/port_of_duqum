<?php

namespace App\Libraries\Sms;

use Config\Sms;

final class SmsConnectionSettings
{
    public static function load(): Sms
    {
        $config = clone config('Sms');
        $rows = db_connect()->table('settings')->where('type', 'sms_private')
            ->where('deleted', 0)->get()->getResultArray();
        $settings = array_column($rows, 'setting_value', 'setting_name');
        if (array_key_exists('ismartsms_enabled', $settings)) {
            $config->enabled = $settings['ismartsms_enabled'] === '1';
        }
        if (array_key_exists('ismartsms_user_id', $settings)) {
            $config->userId = $settings['ismartsms_user_id'];
        }
        if (array_key_exists('ismartsms_header', $settings)) {
            $config->header = $settings['ismartsms_header'];
        }
        if (array_key_exists('ismartsms_password', $settings)) {
            helper('general');
            try {
                $config->password = decode_id($settings['ismartsms_password'], 'ismartsms_password');
            } catch (\Throwable $e) {
                $config->password = '';
            }
        }
        return $config;
    }

    public static function save(Sms $config): bool
    {
        helper('general');
        $values = [
            'ismartsms_enabled' => $config->enabled ? '1' : '0',
            'ismartsms_user_id' => $config->userId,
            'ismartsms_header' => $config->header,
            'ismartsms_password' => encode_id($config->password, 'ismartsms_password'),
        ];
        $db = db_connect();
        $db->transStart();
        $model = new \App\Models\Settings_model();
        foreach ($values as $key => $value) {
            $model->save_setting($key, $value, 'sms_private');
        }
        $db->transComplete();
        return $db->transStatus();
    }
}
