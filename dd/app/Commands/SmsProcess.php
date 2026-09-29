<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SmsProcess extends BaseCommand
{
    protected $group = 'Notifications';
    protected $name = 'sms:process';
    protected $description = 'Process queued workflow SMS and Gate Pass email/visitor notices.';

    public function run(array $params)
    {
        helper('general');
        // CLI has no App_Controller to populate the RISE settings cache.
        $settings = db_connect()->table('settings')->where('type', 'app')->where('deleted', 0)->get()->getResult();
        foreach ($settings as $setting) { config('Rise')->app_settings_array[$setting->setting_name] = $setting->setting_value; }
        $gatePassCount = (new \App\Libraries\Gate_pass_notifications())->process(20);
        CLI::write(($gatePassCount + (new \App\Libraries\Sms\WorkflowSmsOutbox())->process(20)) . ' notification records processed.');
    }
}
