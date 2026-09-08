<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SmsProcess extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'sms:process';
    protected $description = 'Process up to 20 queued workflow SMS notifications.';

    public function run(array $params)
    {
        helper('general');
        // CLI has no App_Controller to populate the RISE settings cache.
        $settings = db_connect()->table('settings')->where('type', 'app')->where('deleted', 0)->get()->getResult();
        foreach ($settings as $setting) { config('Rise')->app_settings_array[$setting->setting_name] = $setting->setting_value; }
        CLI::write((new \App\Libraries\Sms\WorkflowSmsOutbox())->process(20) . ' notification records processed.');
    }
}
