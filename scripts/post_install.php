<?php

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

function post_install()
{
    $job = BeanFactory::getBean('Schedulers');
    $job->name = 'Backup - Database and Files';
    $job->job = 'function::Backup';
    $job->date_time_start = '2005-01-01 00:00:00';
    $job->job_interval = '00::00::*::*::*';
    $job->status = 'Inactive';
    $job->catch_up = '0';
    $job->save();

    $db_job = BeanFactory::getBean('Schedulers');
    $db_job->name = 'Backup - Database Only';
    $db_job->job = 'function::BackupDatabase';
    $db_job->date_time_start = '2005-01-01 00:00:00';
    $db_job->job_interval = '00::00::*::*::*';
    $db_job->status = 'Inactive';
    $db_job->catch_up = '0';
    $db_job->save();

    $files_job = BeanFactory::getBean('Schedulers');
    $files_job->name = 'Backup - Files Only';
    $files_job->job = 'function::BackupFiles';
    $files_job->date_time_start = '2005-01-01 00:00:00';
    $files_job->job_interval = '00::00::*::*::*';
    $files_job->status = 'Inactive';
    $files_job->catch_up = '0';
    $files_job->save();

    require_once 'modules/Configurator/Configurator.php';

    $cfg = new Configurator();

    /** Your setting to save in config_override.php */
    $cfg->config['backup']['LicenseId'] = 'YourLicenseKey';
    $cfg->config['backup']['email_addresses'] = ['youremail@example.com'];
    $cfg->config['backup']['ftps']['server1']['server'] = "myserver.com";
    $cfg->config['backup']['ftps']['server1']['user_name'] = "myusername";
    $cfg->config['backup']['ftps']['server1']['user_pass'] = "extreMELYcomplexPasw0rdWithTypos:)";
    $cfg->config['backup']['ftps']['server1']['dir'] = "/";
    $cfg->config['backup']['ftps']['server1']['ssl'] = false;
    $cfg->config['backup']['destination_directory'] = "/";
    $cfg->config['backup']['delete_local_backups'] = true;
    $cfg->config['backup']['custom_directory_only'] = false;
    $cfg->config['backup']['additional_directories'] = "";
    $cfg->config['backup']['tar'] = true;
    $cfg->config['backup']['excluded_directories'] = [".git", "vendor"];

    $cfg->handleOverride();

}
