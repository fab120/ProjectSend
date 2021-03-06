<?php
/**
 * This file is called on header.php and checks the database to see
 * if it up to date with the current software version.
 *
 * In case you are updating from an old one, the new values, columns
 * and rows will be created, and a message will appear under the menu
 * one time only.
 *
 * @package		ProjectSend
 * @subpackage	Updates
 */

$allowed_update = array(9,8,7);
if (in_session_or_cookies($allowed_update)) {

	/** Remove "r" from version */
	$current_version = substr(CURRENT_VERSION, 1);
	$updates_made = 0;
	$updates_errors = 0;
	$updates_error_messages = array();
	
	/**
	 * Check for updates only if the option exists.
	 */
	if (defined('VERSION_LAST_CHECK')) {
		/**
		 * Compare the date for the last checked with
		 * today's. Checks are done only once per day.
		 */
		 $today = date('d-m-Y');
		 $today_timestamp = strtotime($today);
		 if (VERSION_LAST_CHECK != $today) {
			if (VERSION_NEW_FOUND == '0') {
				/**
				 * Compare against the online value.
				 */
				$feed = simplexml_load_file(UPDATES_FEED_URI);
				$v = 0;
				$max_items = 1;
				foreach ($feed->channel->item as $item) {
					while ($v < $max_items) {
						$namespaces = $item->getNameSpaces(true);
						$release = $item->children($namespaces['release']);
						$diff = $item->children($namespaces['diff']);
						$online_version = substr($release->version, 1);

						 if ($online_version > $current_version) {
							/**
							 * The values are set here since they didn't
							 * come from the database.
							 */
							define('VERSION_NEW_NUMBER',$online_version);
							define('VERSION_NEW_URL',$item->link);
							define('VERSION_NEW_CHLOG',$release->changelog);
							define('VERSION_NEW_SECURITY',$diff->security);
							define('VERSION_NEW_FEATURES',$diff->features);
							define('VERSION_NEW_IMPORTANT',$diff->important);
							/**
							 * Save the information from the new release
							 * to the database.
							 */
							$database->query("UPDATE tbl_options SET value ='$release->version' WHERE name='version_new_number'");
							$database->query("UPDATE tbl_options SET value ='$item->link' WHERE name='version_new_url'");
							$database->query("UPDATE tbl_options SET value ='$release->changelog' WHERE name='version_new_chlog'");
							$database->query("UPDATE tbl_options SET value ='$diff->security' WHERE name='version_new_security'");
							$database->query("UPDATE tbl_options SET value ='$diff->features' WHERE name='version_new_features'");
							$database->query("UPDATE tbl_options SET value ='$diff->important' WHERE name='version_new_important'");
							$database->query("UPDATE tbl_options SET value ='1' WHERE name='version_new_found'");
						 }
						 else {
							 reset_update_status();
						 }

						/**
						 * Change the date and versions values on the
						 * database so it's not checked again today.
						 */
						$database->query("UPDATE tbl_options SET value ='$today' WHERE name='version_last_check'");

						/** Stop the foreach loop */
						$v++;
					}
				}
			 }
		 }
	}

	/**
	 * r264 updates
	 * Save the value of the last update on the database, to prevent
	 * running all this queries everytime a page is loaded.
	 * Done on top for convenience.
	 */
	$version_query = "SELECT value FROM tbl_options WHERE name = 'last_update'";
	$version_sql = $database->query($version_query);

	if(!mysql_num_rows($version_sql)) {
		$updates_made++;
		$qv = "INSERT INTO tbl_options (name, value) VALUES ('last_update', '264')";
		$sqlv = $database->query($qv);
		$updates_made++;
	}
	else {
		while($vres = mysql_fetch_array($version_sql)) {
			$last_update = $vres['value'];
		}
	}
	
	if ($last_update < $current_version || !isset($last_update)) {

		/**
		 * r92 updates
		 * The logo file name is now stored on the database.
		 * If the row doesn't exist, create it and add the default value.
		 */
		if ($last_update < 92) {
			$new_database_values = array(
											'logo_filename' => 'logo.png'
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r94 updates
		 * A new column was added on the clients table, to store the value of the
		 * user that created it.
		 * If the column doesn't exist, create it.
		 */
		if ($last_update < 94) {
			$q = $database->query("SELECT created_by FROM tbl_clients");
			if (!$q) {
				mysql_query("ALTER TABLE tbl_clients ADD created_by VARCHAR(".MAX_USER_CHARS.") NOT NULL");
				$updates_made++;
			}
		}

		/**
		 * DEPRECATED
		 * r102 updates
		 * A function was added to hide or show uploaded files from the clients lists.
		 * If the "hidden" column on the files table doesn't exist, create it.
		 */
		/*
		$q = $database->query("SELECT hidden FROM tbl_files");
		if (!$q) {
			mysql_query("ALTER TABLE tbl_files ADD hidden INT(1) NOT NULL");
			$updates_made++;
		}
		*/

		/**
		 * r135 updates
		 * The e-mail address used for notifications to new users, clients and files
		 * can now be defined on the options page. When installing or updating, it
		 * will default to the primary admin user's e-mail.
		 */
		if ($last_update < 135) {
			$sql = $database->query('SELECT * FROM tbl_users WHERE id="1"');
			while($row = mysql_fetch_array($sql)) {
				$set_admin_email = $row['email'];
			}
		
			$new_database_values = array(
											'admin_email_address' => $set_admin_email
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r183 updates
		 * A new column was added on the clients table, to store the value of the
		 * account active status.
		 * If the column doesn't exist, create it. Also, mark every existing
		 * client as active (1).
		 */
		if ($last_update < 183) {
			$q = $database->query("SELECT active FROM tbl_clients");
			if (!$q) {
				mysql_query("ALTER TABLE tbl_clients ADD active tinyint(1) NOT NULL");
				$sql = $database->query('SELECT * FROM tbl_clients');
				while($row = mysql_fetch_array($sql)) {
					$database->query('UPDATE tbl_clients SET active = 1');
				}
				$updates_made++;
		
				/**
				 * Add the "users can register" value to the options table.
				 * Defaults to 0, since this is a new feature.
				 * */
				$new_database_values = array(
												'clients_can_register' => '0'
											);
				foreach($new_database_values as $row => $value) {
					$q = "SELECT * FROM tbl_options WHERE name = '$row'";
					$sql = $database->query($q);
			
					if(!mysql_num_rows($sql)) {
						$updates_made++;
						$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
						$sqli = $database->query($qi);
					}
					unset($q);
				}
			}
		}

		/**
		 * r189 updates
		 * Move every uploaded file to a neutral location
		 */
		if ($last_update < 189) {
			$work_folder = ROOT_DIR.'/upload/';
			$folders = glob($work_folder."*", GLOB_NOSORT);
		
			foreach ($folders as $folder) {
				if(is_dir($folder) && !stristr($folder,'/temp') && !stristr($folder,'/files')) {
					$files = glob($folder.'/*', GLOB_NOSORT);
					foreach ($files as $file) {
						if(is_file($file) && !stristr($file,'index.php')) {
							$filename = basename($file);
							$mark_for_moving[$filename] = $file;
						}
					}
				}
			}
			$work_folder = UPLOADED_FILES_FOLDER;
			if (!empty($mark_for_moving)) {
				foreach ($mark_for_moving as $filename => $path) {
					$new = UPLOADED_FILES_FOLDER.'/'.$filename;
					$try_moving = rename($path, $new);
					chmod($new, 0644);
				}
			}
		}

		/**
		 * r202 updates
		 * Combine clients and users on the same table.
		 */
		if ($last_update < 202) {
			$q = $database->query("SELECT created_by FROM tbl_users");
			if (!$q) {
				/* Mark existing users as active */
				$database->query("ALTER TABLE tbl_users ADD address TEXT NULL, ADD phone varchar(32) NULL, ADD notify TINYINT(1) NOT NULL default='0', ADD contact TEXT NULL, ADD created_by varchar(32) NULL, ADD active TINYINT(1) NOT NULL default='1'");
				$database->query("INSERT INTO tbl_users"
										." (user, password, name, email, timestamp, address, phone, notify, contact, created_by, active, level)"
										." SELECT client_user, password, name, email, timestamp, address, phone, notify, contact, created_by, active, '0' FROM tbl_clients");
				$database->query("UPDATE tbl_users SET active = 1");
				$updates_made++;
			}
		}

		/**
		 * r210 updates
		 * A new database table was added, that allows the creation of clients groups.
		 */
		if ($last_update < 210) {
			$q = $database->query("SELECT id FROM tbl_groups");
			if (!$q) {
				/** Create the GROUPS table */
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_groups` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `created_by` varchar(32) NOT NULL,
				  `name` varchar(32) NOT NULL,
				  `description` text NOT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q1);
				$updates_made++;
		
				/**
				 * r215 updates
				 * Change the engine of every table to InnoDB, to use foreign keys on the 
				 * groups feature.
				 * Included inside the previous update since that is not an officially
				 * released version.
				 */
				foreach ($current_tables as $working_table) {
					$q = $database->query("ALTER TABLE $working_table ENGINE = InnoDB");
					$updates_made++;
				}
			}
		}

		/**
		 * r219 updates
		 * A new database table was added.
		 * Folders are related to clients or groups.
		 */
		if ($last_update < 219) {
			$q = $database->query("SELECT id FROM tbl_folders");
			if (!$q) {
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_folders` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `parent` int(11) DEFAULT NULL,
				  `name` varchar(32) NOT NULL,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `client_id` int(11) DEFAULT NULL,
				  `group_id` int(11) DEFAULT NULL,
				  FOREIGN KEY (`parent`) REFERENCES tbl_folders(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`group_id`) REFERENCES tbl_groups(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q1);
				$updates_made++;
			}
		}

		/**
		 * r217 updates (after previous so the folder column can be created)
		 * A new database table was added, to facilitate the relation of files
		 * with clients and groups.
		 */
		if ($last_update < 217) {
			$q = $database->query("SELECT id FROM tbl_files_relations");
			if (!$q) {
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_files_relations` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `file_id` int(11) NOT NULL,
				  `client_id` int(11) DEFAULT NULL,
				  `group_id` int(11) DEFAULT NULL,
				  `folder_id` int(11) DEFAULT NULL,
				  `hidden` int(1) NOT NULL,
				  `download_count` int(16) NOT NULL default "0",
				  FOREIGN KEY (`file_id`) REFERENCES tbl_files(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`group_id`) REFERENCES tbl_groups(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`folder_id`) REFERENCES tbl_folders(`id`) ON UPDATE CASCADE,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q1);
				$updates_made++;
			}
		}

		/**
		 * r241 updates
		 * A new database table was added, that stores users and clients actions.
		 */
		if ($last_update < 241) {
			$q = $database->query("SELECT id FROM tbl_actions_log");
			if (!$q) {
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_actions_log` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `action` int(2) NOT NULL,
				  `owner_id` int(11) NOT NULL,
				  `owner_user` text DEFAULT NULL,
				  `affected_file` int(11) DEFAULT NULL,
				  `affected_account` int(11) DEFAULT NULL,
				  `affected_file_name` text DEFAULT NULL,
				  `affected_account_name` text DEFAULT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q1);
				$updates_made++;
			}
		}
		
		/**
		 * r266 updates
		 * Set timestamp columns as real timestamp data, instead of INT
		 */
		if ($last_update < 266) {
			$q1 = "ALTER TABLE `tbl_users` ADD COLUMN `timestamp2` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()";
			$q2 = "UPDATE `tbl_users` SET `timestamp2` = FROM_UNIXTIME(`timestamp`)";
			$q3 = "ALTER TABLE `tbl_users` DROP COLUMN `timestamp`";
			$q4 = "ALTER TABLE `tbl_users` CHANGE `timestamp2` `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()";
			$database->query($q1);
			$database->query($q2);
			$database->query($q3);
			$database->query($q4);
			$updates_made++;
		}

		/**
		 * r275 updates
		 * A new database table was added.
		 * It stores the new files-to clients relations to be
		 * used on notifications.
		 */
		if ($last_update < 275) {
			$q = $database->query("SELECT id FROM tbl_notifications");
			if (!$q) {
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_notifications` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `file_id` int(11) NOT NULL,
				  `client_id` int(11) NOT NULL,
				  `upload_type` int(11) NOT NULL,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q1);
				$updates_made++;
			}
		}

		/**
		 * r278 updates
		 * Set timestamp columns as real timestamp data, instead of INT
		 */
		if ($last_update < 278) {
			$q1 = "ALTER TABLE `tbl_files` ADD COLUMN `timestamp2` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()";
			$q2 = "UPDATE `tbl_files` SET `timestamp2` = FROM_UNIXTIME(`timestamp`)";
			$q3 = "ALTER TABLE `tbl_files` DROP COLUMN `timestamp`";
			$q4 = "ALTER TABLE `tbl_files` CHANGE `timestamp2` `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP()";
			$database->query($q1);
			$database->query($q2);
			$database->query($q3);
			$database->query($q4);
			$updates_made++;
		}


		/**
		 * r282 updates
		 * Add new options to select the handler for sending emails.
		 */
		if ($last_update < 282) {
			$new_database_values = array(
											'mail_system_use' => 'mail',
											'mail_smtp_host' => '',
											'mail_smtp_port' => '',
											'mail_smtp_user' => '',
											'mail_smtp_pass' => '',
											'mail_from_name' => THIS_INSTALL_SET_TITLE
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r338 updates
		 * The Members table wasn't being created on existing installations.
		 */
		if ($last_update < 338) {
			$q = $database->query("SELECT id FROM tbl_members");
			if (!$q) {
				/** Create the MEMBERS table */
				$q2 = '
				CREATE TABLE IF NOT EXISTS `tbl_members` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  `added_by` varchar(32) NOT NULL,
				  `client_id` int(11) NOT NULL,
				  `group_id` int(11) NOT NULL,
				  PRIMARY KEY (`id`),
				  FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`group_id`) REFERENCES tbl_groups(`id`) ON DELETE CASCADE ON UPDATE CASCADE
				) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q2);
				$updates_made++;
			}
		}

		/**
		 * r346 updates
		 * chmod the cache folder and main files of timthumb to 775
		 */
		if ($last_update < 346) {
			update_chmod_timthumb();
		}

		/**
		 * r348 updates
		 * chmod the emails folder and files to 777
		 */
		if ($last_update < 348) {
			update_chmod_emails();
		}

		/**
		 * r352 updates
		 * chmod the main system files to 644
		 */
		if ($last_update < 352) {
			chmod_main_files();
		}

		/**
		 * r353 updates
		 * Create a new option to let the user decide wheter to
		 * use the relative or absolute file url when generating
		 * thumbnails with timthumb.php
		 */
		if ($last_update < 353) {
			$new_database_values = array(
											'thumbnails_use_absolute' => '0'
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r354 updates
		 * Import the files relations (up until r335 it was
		 * only one-to-one with clients) into the new database
		 * table. This should have been done before r335 release.
		 * Sorry :(
		 */
		if ($last_update < 354) {
			import_files_relations();
		}


		/**
		 * r358 updates
		 * New columns where added to the notifications table, to
		 * store values about the state of it.
		 * If the columns don't exist, create them.
		 */
		if ($last_update < 358) {
			$q = $database->query("SELECT sent_status FROM tbl_notifications");
			if (!$q) {
				$sql1 = $database->query("ALTER TABLE tbl_notifications ADD sent_status INT(2) NOT NULL");
				$sql2 = $database->query("ALTER TABLE tbl_notifications ADD times_failed INT(11) NOT NULL");
				$updates_made++;
			}
		}


		/**
		 * r364 updates
		 * Add new options to send copies of notifications emails
		 * to the specified addresses.
		 */
		if ($last_update < 364) {
			$new_database_values = array(
											'mail_copy_user_upload' => '',
											'mail_copy_client_upload' => '',
											'mail_copy_main_user' => '',
											'mail_copy_addresses' => ''
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/** Update the database */
		$database->query("UPDATE tbl_options SET value ='$current_version' WHERE name='last_update'");

		/** Record the action log */
		$new_log_action = new LogActions();
		$log_action_args = array(
								'action' => 30,
								'owner_id' => $global_id,
								'affected_account_name' => $current_version
							);
		$new_record_action = $new_log_action->log_action_save($log_action_args);


		/**
		 * r377 updates
		 * Add new options to store the last time the system checked
		 * for a new version.
		 */
		$today = date('d-m-Y');
		if ($last_update < 377) {
			$new_database_values = array(
											'version_last_check'	=> $today,
											'version_new_found'		=> '0',
											'version_new_number'	=> '',
											'version_new_url'		=> '',
											'version_new_chlog'		=> '',
											'version_new_security'	=> '',
											'version_new_features'	=> '',
											'version_new_important'	=> ''
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}


		/**
		 * r386 / r412 updates
		 * Add new options to handle actions related to clients
		 * self registrations.
		 */
		if ($last_update < 412) {
			$new_database_values = array(
											'clients_auto_approve'	=> '0',
											'clients_auto_group'	=> '0',
											'clients_can_upload'	=> '1'
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}


		/**
		 * r419 updates
		 * Add new options to customize the emails sent by the system.
		 */
		if ($last_update < 419) {
			$new_database_values = array(
										/**
										 * On or Off fields
										 * Each one corresponding to a type of email
										 */
											'email_new_file_by_user_customize'		=> '0',
											'email_new_file_by_client_customize'	=> '0',
											'email_new_client_by_user_customize'	=> '0',
											'email_new_client_by_self_customize'	=> '0',
											'email_new_user_customize'				=> '0',
										/**
										 * Text fields
										 * Each one corresponding to a type of email
										 */
											'email_new_file_by_user_text'			=> '',
											'email_new_file_by_client_text'			=> '',
											'email_new_client_by_user_text'			=> '',
											'email_new_client_by_self_text'			=> '',
											'email_new_user_text'					=> ''
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r426 updates
		 * Add new options to customize the header and footer of emails.
		 */
		if ($last_update < 426) {
			$new_database_values = array(
										'email_header_footer_customize'		=> '0',
										'email_header_text'					=> '',
										'email_footer_text'					=> '',
									);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r442 updates
		 * Add new options to customize the header and footer of emails.
		 */
		if ($last_update < 442) {
			$new_database_values = array(
										'email_pass_reset_customize'		=> '0',
										'email_pass_reset_text'				=> '',
									);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}

		/**
		 * r464 updates
		 * New columns where added to the files table, to
		 * set expiry dates and download limit.
		 * Also, set a new option to hide or show expired
		 * files to clients.
		 */
		if ($last_update < 464) {
			$q = $database->query("SELECT expires FROM tbl_files");
			if (!$q) {
				$sql1 = $database->query("ALTER TABLE tbl_files ADD expires INT(1) NOT NULL default '0'");
				$sql2 = $database->query("ALTER TABLE tbl_files ADD expiry_date TIMESTAMP NOT NULL");
				$updates_made++;
			}

			$new_database_values = array(
										'expired_files_hide'		=> '1',
									);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}


		/**
		 * r474 updates
		 * A new database table was added.
		 * Each download will now be saved here, to distinguish
		 * individual downloads even if the origin is a group.
		 */
		if ($last_update < 474) {
			$q = $database->query("SELECT id FROM tbl_downloads");
			if (!$q) {
				$q1 = '
				CREATE TABLE IF NOT EXISTS `tbl_downloads` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `user_id` int(11) DEFAULT NULL,
				  `file_id` int(11) NOT NULL,
				  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
				  FOREIGN KEY (`user_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  FOREIGN KEY (`file_id`) REFERENCES tbl_files(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
				  PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
				';
				$database->query($q1);
				$updates_made++;
			}
		}


		/**
		 * r475 updates
		 * New columns where added to the files table, to
		 * allow public downloads via a token.
		 */
		if ($last_update < 475) {
			$q = $database->query("SELECT public_allow FROM tbl_files");
			if (!$q) {
				$sql1 = $database->query("ALTER TABLE tbl_files ADD public_allow INT(1) NOT NULL default '0'");
				$sql2 = $database->query("ALTER TABLE tbl_files ADD public_token varchar(32) NULL");
				$updates_made++;
			}
		}


		/**
		 * r487 updates
		 * Add new options to limit the retries of notifications emails
		 * and also set an expiry date.
		 */
		if ($last_update < 487) {
			$new_database_values = array(
											'notifications_max_tries' => '2',
											'notifications_max_days' => '15',
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
				}
				unset($q);
			}
		}


		/**
		 * r490 updates
		 * Set foreign keys to update the notifications table automatically.
		 * Rows that references deleted users or files will be deleted
		 * before adding the keys.
		 */
		if ($last_update < 490) {
			$sql = $database->query("DELETE FROM tbl_notifications WHERE file_id NOT IN (SELECT id FROM tbl_files)");
			$sql = $database->query("DELETE FROM tbl_notifications WHERE client_id NOT IN (SELECT id FROM tbl_users)");
			$sql = $database->query("ALTER TABLE tbl_notifications ADD FOREIGN KEY (`file_id`) REFERENCES tbl_files(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
			$sql = $database->query("ALTER TABLE tbl_notifications ADD FOREIGN KEY (`client_id`) REFERENCES tbl_users(`id`) ON DELETE CASCADE ON UPDATE CASCADE");
			$updates_made++;
		}


		/**
		 * r501 updates
		 * Migrate the download count on each client to the new table.
		 */
		if ($last_update < 501) {
			$sql = $database->query("SELECT * FROM tbl_files_relations WHERE client_id IS NOT NULL AND download_count > 0");
			if(mysql_num_rows($sql)) {
				while($row = mysql_fetch_array($sql)) {
					$download_count	= $row['download_count'];
					$client_id		= $row['client_id'];
					$file_id		= $row['file_id'];
					for ($i = 0; $i < $download_count; $i++) {
						$sql_new = $database->query("INSERT INTO tbl_downloads (file_id, user_id) VALUES ('$file_id', '$client_id')");
					}
				}
				$updates_made++;
			}
		}


		/**
		 * r528 updates
		 * Add new options for email security, file types limits and
		 * requirements for passwords.
		 * and also set an expiry date.
		 */
		if ($last_update < 528) {
			$new_database_values = array(
											'file_types_limit_to'	=> 'all',
											'pass_require_upper'	=> '0',
											'pass_require_lower'	=> '0',
											'pass_require_number'	=> '0',
											'pass_require_special'	=> '0',
											'mail_smtp_auth'		=> 'none'
										);
			
			foreach($new_database_values as $row => $value) {
				$q = "SELECT * FROM tbl_options WHERE name = '$row'";
				$sql = $database->query($q);
		
				if(!mysql_num_rows($sql)) {
					$updates_made++;
					$qi = "INSERT INTO tbl_options (name, value) VALUES ('$row', '$value')";
					$sqli = $database->query($qi);
					$updates_made++;
				}
				unset($q);
			}
		}



		/**
		 * r557 updates
		 * Change the database collations
		 */
		if ($last_update < 557) {
			$dbsql = $database->query('ALTER DATABASE '.DB_NAME.' CHARACTER SET utf8 COLLATE utf8_general_ci');
			$sql = $database->query('SHOW TABLES');
			$fk1 = $database->query('SET foreign_key_checks = 0');
			while ($tables = mysql_fetch_array($sql) ) {
				foreach ($tables as $key => $value) {
					mysql_query("ALTER TABLE $value CONVERT TO CHARACTER SET utf8 COLLATE utf8_general_ci");
					$updates_made++;
				}
			}
			$fk2 = $database->query('SET foreign_key_checks = 1');

			//Add password field to table files
			$newField = $database->query('ALTER TABLE tbl_files ADD COLUMN password VARCHAR(60) NULL');
		}

	}
}	
?>
