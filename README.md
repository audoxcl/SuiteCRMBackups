# SuiteCRMBackups

Schedule your own backups of database and files and avoid loss your data in case your CRM or hosting or server crashed or failed.

## How to use

This simple module backups your SugarCRM/SuiteCRM instance and uploads the backup files to many others servers at the same time.

You can backup database and files at the same time or create different schedulers, for instance:  
* Backup database everyday
* Backup files Sundays at 00:00
* Backup database and files 1st of each month at 00:00

With this module you are able to upload the backup files to many servers at the same time using these protocols:
* FTP
* SFTP
* SCP

## Requirements

Requirements for SCP and SFTP:  
sudo apt install php-ssh2  
sudo apt install php7.2-ssh2

## Configuration

After installing, you will have to adapt `config_override.php` settings to match your environment.

```php
$sugar_config['backup']['LicenseURL'] = 'YourLicenseURL';
$sugar_config['backup']['LicenseId'] = 'YourLicenseKey';
$sugar_config['backup']['validate_license'] = true;
$sugar_config['backup']['email_addresses'] = array('your@email.com');

$sugar_config['backup']['tar'] = true;
$sugar_config['backup']['custom_directory_only'] = false;
$sugar_config['backup']['additional_directories'] = "";
$sugar_config['backup']['delete_local_backups'] = true;
$sugar_config['backup']['excluded_directories'] = array('.git', 'vendor');
$sugar_config['backup']['destination_directory'] = "/";

$sugar_config['backup']['connections'][0]['protocol'] = 'scp';
$sugar_config['backup']['connections'][0]['host'] = 'myserver1.com';
$sugar_config['backup']['connections'][0]['username'] = 'myusername';
$sugar_config['backup']['connections'][0]['password'] = 'extreMELYcomplexPasw0rdWithTypos';
$sugar_config['backup']['connections'][0]['path'] = '';

$sugar_config['backup']['connections'][1]['protocol'] = 'scp';
$sugar_config['backup']['connections'][1]['host'] = 'myserver2.com';
$sugar_config['backup']['connections'][1]['username'] = 'myusername';
$sugar_config['backup']['connections'][1]['password'] = 'extreMELYcomplexPasw0rdWithTypos';
$sugar_config['backup']['connections'][1]['path'] = '';
```

To disable license validation please use this setting in `config_override.php`:

```php
$sugar_config['backup']['validate_license'] = false;
```

Once you set `config_override.php` you have to enable the schedulers you need in:  
Administration -> Schedulers

You can use the 3 schedulers that are created automatically once you install the module.

## Download

Download:  
[Here you can download ZIP file ready to install in your CRM instance](https://github.com/audoxcl/SuiteCRMBackups/releases/latest/download/BackupsScheduler.zip)
