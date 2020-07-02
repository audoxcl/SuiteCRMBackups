<?php

/*********************************************************************************
 * This code was developed by:
 * Audox Ingeniería SpA.
 * You can contact us at:
 * Web: www.audox.cl
 * Email: info@audox.cl
 * Skype: audox.ingenieria
 ********************************************************************************/

array_push($job_strings, 'Backup');
array_push($job_strings, 'BackupDatabase');
array_push($job_strings, 'BackupFiles');

function BackupSchedulerSendEmail($subject, $body)
{
    global $sugar_config;
    $emailObj = new Email();
    $defaults = $emailObj->getSystemDefaultEmail();
    $mail = new SugarPHPMailer();
    $mail->setMailerForSystem();
    $mail->From = "support@audox.cl";
    $mail->FromName = "Audox Soluciones Tecnologicas Support";
    $mail->ClearAllRecipients();
    $mail->ClearReplyTos();
    $mail->Subject = $subject;
    $mail->Body = $body . "Audox Soluciones Tecnol&oacute;gicas<br/>www.audox.cl";
    $mail->AltBody = from_html($mail->Body);
    $mail->prepForOutbound();
    foreach ($sugar_config['backup']['email_addresses'] as $value) {
        $mail->AddAddress($value);
    }
    $mail->Send();
}

/*
You can use this license validation function to create more advanced versions of this backup module.
To disable license validation please use this setting in config_override.php:
$sugar_config['backup']['validate_license'] = false;
*/

function validateLicense()
{
    global $sugar_config;

    $site_url = $sugar_config['site_url'];

    $LicenseURL = $sugar_config['backup']['LicenseURL'];
    $LicenseId = $sugar_config['backup']['LicenseId'];

    $fields = array(
        'Remote' => $_SERVER['REMOTE_ADDR'],
        'Url' => $site_url,
        'ServiceId' => $LicenseId,
    );

    $curl = curl_init($LicenseURL);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($curl));
    curl_close($curl);

    if ($response == 0) {
        $subject = "CRM Backup not valid license";
        $body = $subject . " for $site_url<br/><br/>";
        BackupSchedulerSendEmail($subject, $body);
        $GLOBALS['log']->fatal("Backup CRM error: Not Valid License");
        return false;
    }
    else return true;
}

function getFormatedInstanceUrl()
{
    global $sugar_config;
    $site_url = $sugar_config['site_url'];
    $site_url = str_replace(array(
        'http://',
        'https://',
        '/',
    ), array(
        '',
        '',
        '-',
    ), $site_url);
    return $site_url;
}

function UploadToHost($configArray)
{
    $GLOBALS['log']->fatal("Backup CRM uploading files");
    global $sugar_config;
    $results = array();
    $files = $configArray['files'];
    foreach ($sugar_config['backup']['connections'] as $key => $connection) {
        $GLOBALS['log']->fatal("Backup CRM uploading files to host: " . $connection['host'] . " (protocol: " . $connection['protocol'] . ")");

        $connection_host = $connection['host'];
        $connection_port = 21;
        $connection_timeout = 90;
        $connection_username = $connection['username'];
        $connection_password = $connection['password'];
        $connection_path = $connection['path'];

        $results_key = $connection_username . ":****@" . $connection_host;
        $results[$results_key] = "error";

        if(in_array($connection['protocol'], array("scp", "sftp"))){
            $conn = ssh2_connect($connection_host, 22);
            if ($conn) {
                if(ssh2_auth_password($conn, $connection_username, $connection_password)){
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $GLOBALS['log']->fatal("Backup CRM uploading file: " . $file);
                            if($connection['protocol'] == "scp"){
                                $time = time();
                                if (ssh2_scp_send($conn, $file, $file)) {
                                    $results[$results_key] = "success";
                                    $GLOBALS['log']->fatal("Backup CRM file uploaded in ".(time()-$time)." seconds: " . $file);
                                } else {
                                    $GLOBALS['log']->fatal("Backup CRM file uploaded error: " . $file);
                                }
                            }
                            elseif($connection['protocol'] == "sftp"){
                                // pending: add validations
                                $sftp = ssh2_sftp($conn);
                                $contents = file_get_contents($file);
                                $time = time();
                                file_put_contents("ssh2.sftp://{$sftp}".$connection_path."/".$file, $contents);
                                $GLOBALS['log']->fatal("Backup CRM file uploaded in ".(time()-$time)." seconds: " . $file);
                            }
                        }
                    }
                }
                else{
                    $GLOBALS['log']->fatal("Backup CRM auth error");
                }
            }
            else{
                $GLOBALS['log']->fatal("Backup CRM connection error");
            }
        }
        else {
            $conn_id = ftp_connect($connection_host, $connection_port, $connection_timeout);
            if ($conn_id) {
                if (ftp_login($conn_id, $connection_username, $connection_password)) {
                    ftp_pasv($conn_id, true);
                    ftp_chdir($conn_id, $connection_path);
                    foreach ($files as $file) {
                        if (file_exists($file)) {
                            $GLOBALS['log']->fatal("Backup CRM uploading file: " . $file);
                            $time = time();
                            if (ftp_put($conn_id, $file, $file, FTP_ASCII)) {
                                $results[$results_key] = "success";
                                $GLOBALS['log']->fatal("Backup CRM file uploaded in ".(time()-$time)." seconds: " . $file);
                            } else {
                                $GLOBALS['log']->fatal("Backup CRM file uploaded error: " . $file);
                            }
                        }
                    }
                } else {
                    $GLOBALS['log']->fatal("Backup CRM ftp login error");
                }
                ftp_close($conn_id);
                $GLOBALS['log']->fatal("Backup CRM ftp closed");
            } else {
                $GLOBALS['log']->fatal("Backup CRM error connecting to ftp");
            }

        }
    }

    $files_string = array();
    foreach ($files as $value) {
        $files_string[] = "$value</td><td align=\"right\">" . number_format(filesize($value)) . ' bytes';
    }

    $files_string = "<table border=\"1\"><tr><td>" . implode("</td></tr><tr><td>", $files_string) . "</td></tr></table>";
    $delete_local_backups = $sugar_config['backup']['delete_local_backups'];
    if ($delete_local_backups == true) {
        foreach ($files as $file) {
            if (unlink($file)) {
                $GLOBALS['log']->fatal("Backup CRM file deleted: $file");
            } else {
                $GLOBALS['log']->fatal("Backup CRM error deleting file: $file");
            }

        }
    } elseif (is_dir($sugar_config['backup']['destination_directory'])) {
        foreach ($files as $file) {
            if (rename($file, $sugar_config['backup']['destination_directory'] . "/$file")) {
                $GLOBALS['log']->fatal("Backup CRM file renamed: $file");
            } else {
                $GLOBALS['log']->fatal("Backup CRM error renaming file: $file in: " . $sugar_config['backup']['destination_directory']);
            }

        }
    }

    $subject_warning = "";
    $result_string = array();
    foreach ($results as $key => $value) {
        $result_string[] = "$key</td><td>$value";
        if ($value === "error") {
            $subject_warning = " (*WITH ERRORS*)";
        }
    }
    $result_string = "<table border=\"1\"><tr><td>" . implode("</td></tr><tr><td>", $result_string) . "</td></tr></table>";

    $config_string = array();
    $results_match = array(
        0 => "false",
        1 => "true",
    );

    foreach ($configArray as $value) {
        if (is_array($sugar_config['backup'][$value])) {
            $aux = implode("<br/>", $sugar_config['backup'][$value]);
        } elseif (is_bool($sugar_config['backup'][$value])) {
            $aux = $results_match[$sugar_config['backup'][$value]];
        } else {
            $aux = $sugar_config['backup'][$value];
        }
        if (is_array($value)) {
            $value = implode("<br/>", $value);
        }
        $config_string[] = "$value</td><td>$aux";
    }
    $config_string = "<table border=\"1\"><tr><td>" . implode("</td></tr><tr><td>", $config_string) . "</td></tr></table>";
    $instance_url = getFormatedInstanceUrl();
    $subject = "CRM Backup result for " . $instance_url . $subject_warning;
    $body = "CRM Backup result:<br/><br/>
    Files:<br/>
    $files_string<br/><br/>
    Results:<br/>
    $result_string<br/><br/>
    Config Options:<br/>
    $config_string<br/><br/>";
    BackupSchedulerSendEmail($subject, $body);

    $GLOBALS['log']->fatal("Backup CRM Ready!");
    return true;
}

function Backup()
{
    BackupDatabase();
    BackupFiles();

    return true;
}

function BackupDatabase()
{
    global $sugar_config;
    if($sugar_config['backup']['validate_license'] == true && validateLicense() == false) return true;
    $delete_local_backups = false;
    $delete_local_backups = $sugar_config['backup']['delete_local_backups'];

    $GLOBALS['log']->fatal("Backup CRM database backup starting...");
    $dateYmdHis = date('YmdHis');

    $hostname = $sugar_config['dbconfig']['db_host_name'];
    $user = $sugar_config['dbconfig']['db_user_name'];
    $password = $sugar_config['dbconfig']['db_password'];
    $dbName = $sugar_config['dbconfig']['db_name'];

    $instance_url = getFormatedInstanceUrl();

    $sqlBackupFile = "backup_" . $instance_url . "_" . $dateYmdHis . ".sql";

    $files = array(
        $sqlBackupFile,
    );

    $sql_command = "mysqldump -h" . $hostname . " -u" . $user . " -p" . $password . " " . $dbName . " > " . $sqlBackupFile;

    set_time_limit(600);
    ini_set('max_execution_time', 600);
    ini_set('mysql.connect_timeout', 600);
    // $GLOBALS['log']->fatal("Backup CRM backuping database ($sql_command)...");
    $GLOBALS['log']->fatal("Backup CRM backuping database...");
    $time = time();
    system($sql_command);
    $GLOBALS['log']->fatal("Backup CRM database backuped in ".(time()-$time)." seconds");

    $configArray = array(
        "destination_directory",
        'files' => $files,
    );

    UploadToHost($configArray);

    return true;

}

function BackupFiles()
{
    global $sugar_config;
    if($sugar_config['backup']['validate_license'] == true && validateLicense() == false) return true;
    $custom_directory_only = false;
    $custom_directory_only = $sugar_config['backup']['custom_directory_only'];
    $additional_directories = $sugar_config['backup']['additional_directories'];
    if (!is_array($additional_directories)) {
        $additional_directories = array();
    }

    $tar = false;
    $tar = $sugar_config['backup']['tar'];
    $delete_local_backups = !empty($sugar_config['backup']['delete_local_backups']) ? $sugar_config['backup']['delete_local_backups'] : false;

    $GLOBALS['log']->fatal("Backup CRM files backup starting...");
    $dateYmdHis = date('YmdHis');

    $instance_url = getFormatedInstanceUrl();

    $filesBackupFile = "backup_" . $instance_url . "_" . $dateYmdHis;
    if ($tar == true) {
        $filesBackupFile .= ".tar.gz";
    } else {
        $filesBackupFile .= ".zip";
    }

    $files = array(
        $filesBackupFile,
    );

    if ($custom_directory_only == true) {
        if ($tar == true) {
            $files_command = "tar -zcvf $filesBackupFile custom";
        } else {
            $files_command = "zip -r $filesBackupFile ./custom/*";
        }

        foreach ($additional_directories as $additional_directory) {
            if ($tar == true) {
                $files_command .= " $additional_directory";
            } else {
                $files_command .= " ./$additional_directory/*";
            }

        }
    } else {
        $excluded_directories = "";
        if (isset($sugar_config['backup']['excluded_directories']) && is_array($sugar_config['backup']['excluded_directories'])) {
            $GLOBALS['log']->fatal("Backup CRM excluded_directories: " . print_r($sugar_config['backup']['excluded_directories'], true));
            array_unshift($sugar_config['backup']['excluded_directories'], "cache", "cache_temp");
            foreach ($sugar_config['backup']['excluded_directories'] as $directory) {
                if ($tar == true) {
                    $excluded_directories .= " --exclude '$directory'";
                } else {
                    $excluded_directories .= " -x '$directory/*'";
                }

            }
        }
        if ($tar == true) {
            $files_command = "tar -zcvf $filesBackupFile *$excluded_directories";
        } else {
            $files_command = "zip -r $filesBackupFile .$excluded_directories";
        }

    }

    set_time_limit(600);
    ini_set('max_execution_time', 600);
    ini_set('mysql.connect_timeout', 600);
    // $GLOBALS['log']->fatal("Backup CRM backuping files ($files_command)...");
    $GLOBALS['log']->fatal("Backup CRM backuping files...");
    $time = time();
    system($files_command);
    $GLOBALS['log']->fatal("Backup CRM files backuped in ".(time()-$time)." seconds");

    $configurationArray = array(
        "custom_directory_only",
        "additional_directories",
        "excluded_directories",
        "delete_local_backups",
        "destination_directory",
        'files' => $files,
    );

    UploadToHost($configurationArray);

    return true;

}
