# SuiteCRMBackups
Schedule your own backups of dababase and files


## How to use

This little plugin will make the backups for you and copy them to your FTP server.

Requirements for SFTP and SCP:
sudo apt install php-ssh2
sudo apt install php7.2-ssh2

After installing, you will have to adapt `config_override.php` settings to match your environment.
```php
$sugar_config['backup']['email_addresses']=['your@email.com'];
$sugar_config['backup']['LicenseId']='YourLicenseKey';
$sugar_config['backup']['ftps']['server1']['server']="myserver.com";
$sugar_config['backup']['ftps']['server1']['user_name']="myusername";
$sugar_config['backup']['ftps']['server1']['user_pass']="extreMELYcomplexPasw0rdWithTypos:)";
$sugar_config['backup']['ftps']['server1']['dir']="/";
$sugar_config['backup']['ftps']['server1']['ssl']=false;
$sugar_config['backup']['destination_directory']="/";
$sugar_config['backup']['delete_local_backups']= true;
$sugar_config['backup']['custom_directory_only'] = false;
$sugar_config['backup']['additional_directories']="";
$sugar_config['backup']['tar']= true;
$sugar_config['backup']['excluded_directories']="";

```
