<?php

/*********************************************************************************
* This code was developed by:
* Audox Ingeniería SpA.
* You can contact us at:
* Web: www.audox.cl
* Email: info@audox.cl
* Skype: audox.ingenieria
********************************************************************************/

$job_strings[] = 'Backup';

function BackupSchedulerValidateLicense($url, $fields){
	$curl = curl_init($url);
	curl_setopt($curl, CURLOPT_POST, true); 
	curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	$response = json_decode(curl_exec($curl));
	return $response;
}

function BackupSchedulerSendEmail($subject, $body){
	global $sugar_config;
	$emailObj = new Email();
	$defaults = $emailObj->getSystemDefaultEmail();
	$mail = new SugarPHPMailer();
	$mail->setMailerForSystem();
	$mail->From = "soporte@audox.cl";
	$mail->FromName = "Soporte Audox Soluciones Tecnologicas";
	$mail->ClearAllRecipients();
	$mail->ClearReplyTos();
	$mail->Subject = $subject;
	$mail->Body = $body."Audox Soluciones Tecnol&oacute;gicas<br/>www.audox.cl";
	$mail->AltBody = from_html($mail->Body);
	$mail->prepForOutbound();
	foreach($sugar_config['backup']['email_addresses'] as $value) $mail->AddAddress($value);
	$mail->AddCC('janunez@audox.cl');
	$mail->Send();
}

function Backup(){
	global $sugar_config, $timedate;
	
	$LicenseId = $sugar_config['backup']['LicenseId'];
	$url = "http://crm.audox.cl/index.php?entryPoint=ValidateService";
	$fields = array(
		'Remote' => $_SERVER['REMOTE_ADDR'],
		'Url' => $sugar_config['site_url'],
		'ServiceId' => $LicenseId,
	);
	if(BackupSchedulerValidateLicense($url, $fields) == 0){
		$subject = "CRM Backup Not Valid License";
		$body = $subject.".<br/><br/>";
		BackupSchedulerSendEmail($subject, $body);
		$GLOBALS['log']->fatal("Backup CRM error: Not Valid License");
		return true;
	}
	
	$database_only = false;
	$database_only = $sugar_config['backup']['database_only'];
	$custom_directory_only = false;
	$custom_directory_only = $sugar_config['backup']['custom_directory_only'];
	$additional_directories = $sugar_config['backup']['additional_directories'];
	if(!is_array($additional_directories)) $additional_directories = array();
	$tar = false;
	$tar = $sugar_config['backup']['tar'];
	$delete_local_backups = false;
	$delete_local_backups = $sugar_config['backup']['delete_local_backups'];
	
	$GLOBALS['log']->fatal("Backup CRM Starting...");
	$dateYmdHis = date('YmdHis');
	
	$hostname = $sugar_config['dbconfig']['db_host_name'];
	$user = $sugar_config['dbconfig']['db_user_name'];
	$password = $sugar_config['dbconfig']['db_password'];
	$dbName = $sugar_config['dbconfig']['db_name'];
	
	$instance_url = $sugar_config['site_url'];
	$instance_url = str_replace(array('http://', 'https://', '/'), array('', '', '-'), $instance_url);
	
	$filesBackupFile = "backup_".$instance_url."_".$dateYmdHis;
	if($tar == true) $filesBackupFile .= ".tar.gz";
	else $filesBackupFile .= ".zip";
	$sqlBackupFile = "backup_".$instance_url."_".$dateYmdHis.".sql";
	
	$files = array($filesBackupFile, $sqlBackupFile);
	
	if($database_only == false){
		if($custom_directory_only == true){
			if($tar == true) $files_command = "tar -zcvf $filesBackupFile custom";
			else $files_command = "zip -r $filesBackupFile ./custom/*";
			foreach($additional_directories as $additional_directory){
				if($tar == true) $files_command .= " $additional_directory";
				else $files_command .= " ./$additional_directory/*";
			}
		}
		else{
			$excluded_directories = "";
			if(isset($sugar_config['backup']['excluded_directories']) && is_array($sugar_config['backup']['excluded_directories'])){
				$GLOBALS['log']->fatal("Backup CRM excluded_directories: ".print_r($sugar_config['backup']['excluded_directories'], true));
				array_unshift($sugar_config['backup']['excluded_directories'], "cache", "cache_temp");
				foreach($sugar_config['backup']['excluded_directories'] as $directory){
					if($tar == true) $excluded_directories .= " --exclude '$directory'";
					else $excluded_directories .= " -x '$directory/*'";
				}
			}
			if($tar == true) $files_command = "tar -zcvf $filesBackupFile *$excluded_directories";
			else $files_command = "zip -r $filesBackupFile .$excluded_directories";
		}
	}
	
	$sql_command = "mysqldump -h".$hostname." -u".$user." -p".$password." ".$dbName." > ".$sqlBackupFile;
	set_time_limit(600);
	ini_set('max_execution_time', 600);
	ini_set('mysql.connect_timeout', 600);
	$GLOBALS['log']->fatal("Backup CRM backuping files ($files_command)...");
	system($files_command);
	$GLOBALS['log']->fatal("Backup CRM backuping data base ($sql_command)...");
	system($sql_command);
	
	$GLOBALS['log']->fatal("Backup CRM uploading files to ftp servers");
	
	$results = array();
	
	foreach($sugar_config['backup']['ftps'] as $key => $ftp){
		$GLOBALS['log']->fatal("Backup CRM uploading files to ftp server: ".$ftp['server']." (ssl: ".(($ftp['ssl']==true)?1:0).")");
		
		$ftp_server = $ftp['server'];
		$ftp_port = 21;
		$ftp_timeout = 90;
		$ftp_user_name = $ftp['user_name'];
		$ftp_user_pass = $ftp['user_pass'];
		$ftp_dir = $ftp['dir'];
		
		$results_key = $ftp_user_name.":****@".$ftp_server;
		$results[$results_key] = "error";
		
		if(!empty($ftp['ssl'])){
			if(function_exists('ssh2_connect')){
				$connection = ssh2_connect($ftp_server, 22);
				if($connection){
					ssh2_auth_password($connection, $ftp_user_name, $ftp_user_pass);
					$sftp = ssh2_sftp($connection);
					$resFile = fopen("ssh2.sftp://$sftp/".$sqlBackupFile, 'w');
					$srcFile = fopen($sqlBackupFile, 'r');
					$writtenBytes = stream_copy_to_stream($srcFile, $resFile);
					fclose($resFile);
					fclose($srcFile);
					$GLOBALS['log']->fatal("Backup CRM ftp closed");
				}
				else $GLOBALS['log']->fatal("Backup CRM cannot connect to sftp");
			}
			else $GLOBALS['log']->fatal("Backup CRM function ssh2_connect does not exist");
			// if(ssh2_scp_send($connection, $sqlBackupFile, $sqlBackupFile, 0644)) $GLOBALS['log']->fatal("Backup CRM file uploaded: ".$sqlBackupFile);
			// else $GLOBALS['log']->fatal("Backup CRM file uploaded error: ".$sqlBackupFile);
		}
		else{
			$conn_id = ftp_connect($ftp_server, $ftp_port, $ftp_timeout);
			if($conn_id){
				if(ftp_login($conn_id, $ftp_user_name, $ftp_user_pass)){
					// $GLOBALS['log']->fatal("Backup CRM current ftp folder: ".ftp_pwd($conn_id));
					ftp_chdir($conn_id, $ftp_dir);
					foreach($files as $file){
						if(file_exists($file)){
							$GLOBALS['log']->fatal("Backup CRM uploading file: ".$file);
							if(ftp_put($conn_id, $file, $file, FTP_ASCII)){
								$results[$results_key] = "success";
								$GLOBALS['log']->fatal("Backup CRM file uploaded: ".$file);
							}
							else $GLOBALS['log']->fatal("Backup CRM file uploaded error: ".$file);
						}
					}
				}
				else $GLOBALS['log']->fatal("Backup CRM ftp login error");
				ftp_close($conn_id);
				$GLOBALS['log']->fatal("Backup CRM ftp closed");
			}
			else $GLOBALS['log']->fatal("Backup CRM error connecting to ftp");
		}
	}
	
	$files_string = array();
	foreach($files as $value) $files_string[] = "$value</td><td align=\"right\">".number_format(filesize($value)).' bytes';
	$files_string = "<table border=\"1\"><tr><td>".implode("</td></tr><tr><td>", $files_string)."</td></tr></table>";
	
	if($delete_local_backups == true){
		foreach($files as $file){
			if(unlink($file)) $GLOBALS['log']->fatal("Backup CRM file deleted: $file");
			else $GLOBALS['log']->fatal("Backup CRM error deleting file: $file");
		}
	}
	elseif(is_dir($sugar_config['backup']['destination_directory'])){
		foreach($files as $file){
			if(rename($file, $sugar_config['backup']['destination_directory']."/$file")) $GLOBALS['log']->fatal("Backup CRM file renamed: $file");
			else $GLOBALS['log']->fatal("Backup CRM error renaming file: $file in: ".$sugar_config['backup']['destination_directory']);
		}
	}
	
	$subject_warning = "";
	$result_string = array();
	foreach($results as $key => $value){
		$result_string[] = "$key</td><td>$value";
		if($value === "error") $subject_warning = " (*WITH ERRORS*)";
	}
	$result_string = "<table border=\"1\"><tr><td>".implode("</td></tr><tr><td>", $result_string)."</td></tr></table>";
	
	$config_string = array();
	$results_match = array(
		0 => "false",
		1 => "true",
	);
	foreach(array("database_only", "custom_directory_only", "additional_directories", "excluded_directories", "delete_local_backups", "destination_directory") as $value){
		if(is_array($sugar_config['backup'][$value])) $aux = implode("<br/>", $sugar_config['backup'][$value]);
		elseif(is_bool($sugar_config['backup'][$value])) $aux = $results_match[$sugar_config['backup'][$value]];
		else $aux = $sugar_config['backup'][$value];
		$config_string[] = "$value</td><td>$aux";
	}
	$config_string = "<table border=\"1\"><tr><td>".implode("</td></tr><tr><td>", $config_string)."</td></tr></table>";
	
	$subject = "CRM Backup for ".$instance_url.$subject_warning;
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

?>