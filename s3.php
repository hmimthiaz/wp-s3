<?php

/*
Plugin Name: Wordpress Amazon S3 Plugin
Plugin URI: http://imthi.com/wp-s3/
Description: This plugin helps the users to view your blog in a pda and iPhone browser.
Author: Imthiaz Rafiq
Version: 1.0
Author URI: http://imthi.com/
*/
class S3Plugin {
	
	var $enabled;
	
	var $s3CacheFolder;
	var $mediaUrl;
	var $mediaPath;
	
	var $s3AccessKey;
	var $s3SecretKey;
	var $s3BucketName;
	var $s3UseSSL;
	
	var $cronScheduleTime;
	var $cronUploadLimit;
	/*
	 * 
	 * @var wpdb
	 */
	var $db;
	var $tabeImageQueue;

	function S3Plugin() {
		global $wpdb;
		
		if (get_option ( 's3plugin_enabled', 'inactive' ) == 'active') {
			$this->enabled = TRUE;
		} else {
			$this->enabled = FALSE;
		}
		
		$this->s3CacheFolder = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 's3temp' . DIRECTORY_SEPARATOR;
		$this->mediaUrl = get_option ( 'siteurl' ) . '/' . get_option ( 'upload_path', 'wp-content/uploads' ) . '/';
		$this->mediaPath = ABSPATH . get_option ( 'upload_path', 'wp-content/uploads' ) . DIRECTORY_SEPARATOR;
		
		$this->s3AccessKey = get_option ( 's3plugin_amazon_key_id' );
		$this->s3SecretKey = get_option ( 's3plugin_amazon_secret_key' );
		$this->s3BucketName = get_option ( 's3plugin_amazon_bucket_name' );
		$this->s3UseSSL = ( bool ) get_option ( 's3plugin_use_ssl', 0 );
		
		$this->cronScheduleTime = get_option ( 's3plugin_cron_interval', 300 );
		$this->cronUploadLimit = get_option ( 's3plugin_cron_limit', 20 );
		
		$this->db = $wpdb;
		$this->tabeImageQueue = $wpdb->prefix . 's3_image_queue';
		
		register_activation_hook ( plugin_basename ( __FILE__ ), array (
			&$this, 
			'activatePlugin' ) );
		register_deactivation_hook ( plugin_basename ( __FILE__ ), array (
			&$this, 
			'deactivatePlugin' ) );
		add_action ( 'admin_menu', array (&$this, 's3AdminMenu' ) );
		if (isset ( $_GET [ 'page' ] ) && $_GET [ 'page' ] == 's3plugin-options') {
			ob_start ();
		}
		
		if ($this->enabled) {
			add_filter ( 'the_content', array (&$this, 'theContent' ), 12 );
			add_filter ( 'cron_schedules', array (
				&$this, 
				'cronSchedules' ) );
			if (! wp_next_scheduled ( 's3CronHook' )) {
				wp_schedule_event ( time (), 's3_cron_schedule', 's3CronHook' );
			}
			add_action ( 's3CronHook', array (&$this, 'executeCron' ) );
		} else {
			if (wp_next_scheduled ( 's3CronHook' )) {
				wp_clear_scheduled_hook ( 's3CronHook' );
			}
		}
	}

	function s3AdminMenu() {
		if (function_exists ( 'add_submenu_page' )) {
			add_submenu_page ( 'plugins.php', __ ( 'Amazon S3' ), __ ( 'Amazon S3' ), 'manage_options', 's3plugin-options', array (
				&$this, 
				's3PluginOption' ) );
		}
	
	}

	function s3PluginOption() {
		if (isset ( $_POST [ 'Submit' ] )) {
			if (function_exists ( 'current_user_can' ) && ! current_user_can ( 'manage_options' )) {
				die ( __ ( 'Cheatin&#8217; uh?' ) );
			}
			update_option ( 's3plugin_amazon_key_id', $_POST [ 's3plugin_amazon_key_id' ] );
			update_option ( 's3plugin_amazon_secret_key', $_POST [ 's3plugin_amazon_secret_key' ] );
			update_option ( 's3plugin_amazon_bucket_name', $_POST [ 's3plugin_amazon_bucket_name' ] );
			if (isset ( $_POST [ 's3plugin_use_ssl' ] )) {
				update_option ( 's3plugin_use_ssl', $_POST [ 's3plugin_use_ssl' ] );
				$useSSL = TRUE;
			} else {
				delete_option ( 's3plugin_use_ssl' );
				$useSSL = FALSE;
			}
			if ($this->checkS3AccessAndBucket ( $_POST [ 's3plugin_amazon_key_id' ], $_POST [ 's3plugin_amazon_secret_key' ], $useSSL, $_POST [ 's3plugin_amazon_bucket_name' ] ) === FALSE) {
				$s3PuginMessage = 'Connection failed. Plugin not active.';
				update_option ( 's3plugin_enabled', 'inactive' );
			} else {
				$s3PuginMessage = 'Settings saved. Plugin is active.';
				update_option ( 's3plugin_enabled', 'active' );
			}
			update_option ( 's3plugin_cron_interval', $_POST [ 's3plugin_cron_interval' ] );
			update_option ( 's3plugin_cron_limit', $_POST [ 's3plugin_cron_limit' ] );
			ob_end_clean ();
			wp_redirect ( 'plugins.php?page=s3plugin-options&msg=' . urlencode ( $s3PuginMessage ) );
			exit ();
		}
		include_once ('s3-options.php');
	}

	function activatePlugin() {
		$query = "CREATE TABLE IF NOT EXISTS `{$this->tabeImageQueue}` (
		  `id` varchar(32) NOT NULL,
		  `path` varchar(255) NOT NULL,
		  `status` enum('queue','done','error') NOT NULL,
		  `added` datetime NOT NULL,
		  PRIMARY KEY (`id`)
		) ENGINE=MyISAM;";
		$this->db->query ( $query );
	}

	function deactivatePlugin() {
	
	}

	function cronSchedules($param) {
		return array (
			's3_cron_schedule' => array (
				'interval' => $this->cronScheduleTime, 
				'display' => 'S3 Cron Schedule' ) );
	}

	function checkS3AccessAndBucket($accessKey, $secretKey, $useSSL, $bucketName) {
		include_once dirname ( __FILE__ ) . DIRECTORY_SEPARATOR . 's3-php5-curl' . DIRECTORY_SEPARATOR . 'S3.php';
		
		$s3Adapter = new S3 ( $accessKey, $secretKey, $useSSL );
		$availableBuckets = @$s3Adapter->listBuckets ();
		if (! empty ( $availableBuckets ) && in_array ( $bucketName, $availableBuckets ) == TRUE) {
			return TRUE;
		}
		return FALSE;
	}

	function executeCron() {
		ignore_user_abort ( true );
		set_time_limit ( 0 );
		
		include_once dirname ( __FILE__ ) . DIRECTORY_SEPARATOR . 's3-php5-curl' . DIRECTORY_SEPARATOR . 'S3.php';
		
		$s3Adapter = new S3 ( $this->s3AccessKey, $this->s3SecretKey, $this->s3UseSSL );
		$availableBuckets = @$s3Adapter->listBuckets ();
		if (! empty ( $availableBuckets ) && in_array ( $this->s3BucketName, $availableBuckets ) == TRUE) {
			$query = "SELECT * FROM {$this->tabeImageQueue} WHERE status='queue' ORDER BY added LIMIT {$this->cronUploadLimit};";
			$filesToUpload = $this->db->get_results ( $query, ARRAY_A );
			if (! empty ( $filesToUpload )) {
				foreach ( $filesToUpload as $fileInfo ) {
					$shouldUpload = TRUE;
					$fileStatus = 'error';
					$filePath = $this->mediaPath . $fileInfo [ 'path' ];
					$fileObjectInfo = $s3Adapter->getObjectInfo ( $this->s3BucketName, $fileInfo [ 'path' ], TRUE );
					if (! empty ( $fileObjectInfo )) {
						if ($fileObjectInfo [ 'size' ] != filesize ( $filePath )) {
							if ($s3Adapter->deleteObject ( $this->s3BucketName, $fileInfo [ 'path' ] ) === FALSE) {
								$shouldUpload = FALSE;
							}
						} else {
							$shouldUpload = FALSE;
							$fileStatus = 'done';
						}
					}
					if ($shouldUpload) {
						if ($s3Adapter->putObjectFile ( $filePath, $this->s3BucketName, $fileInfo [ 'path' ], S3::ACL_PUBLIC_READ ) === TRUE) {
							$fileStatus = 'done';
						}
					}
					print "Processing: {$fileInfo['path']} Status: {$fileStatus}. <br />\n";
					$this->writeToFile ( $this->getFilePath ( $fileInfo [ 'path' ] ), $fileStatus );
					$this->db->update ( $this->tabeImageQueue, array (
						'status' => $fileStatus ), array (
						'id' => $fileInfo [ 'id' ] ) );
				}
			}
		}
	}

	function theContent($the_content) {
		$id = 0;
		$post = &get_post ( $id );
		if ($post->post_status != 'publish') {
			return $the_content;
		}
		$mediaList = $this->getMediaFromContent ( $the_content );
		if (! empty ( $mediaList )) {
			foreach ( $mediaList as $mediaInfo ) {
				if (file_exists ( $mediaInfo [ 'cache' ] ) === TRUE) {
					$fileContents = file_get_contents ( $mediaInfo [ 'cache' ] );
					if ($fileContents == 'done') {
						$cdnUrl = "http://{$this->s3BucketName}.s3.amazonaws.com/" . $mediaInfo [ 'path' ];
						$the_content = str_replace ( $mediaInfo [ 'url' ], $cdnUrl, $the_content );
					}
				} else {
					$pathHash = md5 ( $mediaInfo [ 'path' ] );
					$query = "SELECT count(*) FROM {$this->tabeImageQueue} WHERE id='{$pathHash}';";
					if ($this->db->get_var ( $query ) == 0) {
						$insertArray = array (
							'id' => md5 ( $mediaInfo [ 'path' ] ), 
							'path' => $mediaInfo [ 'path' ], 
							'status' => 'queue', 
							'added' => current_time ( 'mysql' ) );
						$this->db->insert ( $this->tabeImageQueue, $insertArray );
					} else {
						$updateArray = array (
							'status' => 'queue', 
							'added' => current_time ( 'mysql' ) );
						$this->db->update ( $this->tabeImageQueue, $updateArray, array (
							'id' => $pathHash ) );
					}
					$this->writeToFile ( $mediaInfo [ 'cache' ] );
				}
			}
		}
		return $the_content;
	}

	function getMediaFromContent($content) {
		$mediaList = array ();
		$regex = '/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';
		preg_match_all ( $regex, $content, $matches );
		if (isset ( $matches [ 0 ] ) && ! empty ( $matches [ 0 ] )) {
			foreach ( $matches [ 0 ] as $url ) {
				if (strpos ( $url, $this->mediaUrl ) !== FALSE) {
					$relativePath = str_replace ( $this->mediaUrl, '', $url );
					if (file_exists ( $this->mediaPath . $relativePath )) {
						$mediaList [] = array (
							'path' => $relativePath, 
							'url' => $url, 
							'cache' => $this->getFilePath ( $relativePath ) );
					}
				}
			}
		}
		return $mediaList;
	}

	function writeToFile($file, $status = 'QUEUE') {
		$fileDir = dirname ( $file );
		$this->createDirectory ( $fileDir );
		file_put_contents ( $file, $status );
	
	}

	protected function getFilePath($file) {
		$hash = md5 ( $file );
		$path = $this->s3CacheFolder;
		for($i = 0; $i < 3; $i ++) {
			$path .= substr ( $hash, 0, $i + 1 ) . DIRECTORY_SEPARATOR;
		}
		return $path . $hash . '.txt';
	}

	public static function createDirectory($path, $permission = 0755) {
		if (! file_exists ( $path )) {
			S3Plugin::createDirectory ( dirname ( $path ), $permission );
			mkdir ( $path, $permission );
			chmod ( $path, $permission );
			if ($handle = @fopen ( $path . '/index.php', 'w' )) {
				fwrite ( $handle, '<?php print ":-)"; ?>' );
				fclose ( $handle );
				chmod ( $path . '/index.php', 0644 );
			}
		}
	}

}

$wp_s3 = new S3Plugin ( );

function cmVarDebug($var, $echo = true) {
	$dump = "<div style=\"border:1px solid #f00;font-family:arial;font-size:12px;font-weight:normal;background:#f0f0f0;text-align:left;padding:3px;\"><pre>" . print_r ( $var, true ) . "</pre></div>";
	if ($echo) {
		echo $dump;
	} else {
		return $dump;
	}
}




