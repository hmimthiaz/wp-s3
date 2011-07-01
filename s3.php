<?php

/*
  Plugin Name: Wordpress Amazon S3 Plugin
  Plugin URI: http://imthi.com/wp-s3/
  Description: This plugin helps the users to view your blog in a pda and iPhone browser.
  Author: Imthiaz Rafiq
  Version: 1.1 Alpha
  Author URI: http://imthi.com/
 */

error_reporting(E_ALL);

class S3Plugin {

    var $enabled;
    var $s3CacheFolder;
    var $siteURL;
    var $isCloudFrontURLEnabled;
    var $s3AccessKey;
    var $s3SecretKey;
    var $s3BucketName;
    var $s3UseSSL;
    var $s3UseCloudFrontURL;
    var $s3CloudFrontURL;
    var $s3DirPrefix;
    var $cronScheduleTime;
    var $cronUploadLimit;
    /*
     * 
     * @var wpdb
     */
    var $db;
    var $tabeImageQueue;
    var $blockDirectory;
    var $blockExtension;
    /**
     *
     * @var S3Plugin 
     */
    protected static $_instance = null;

    /**
     * Singleton instance
     *
     * @return S3Plugin
     */
    public static function getInstance() {
	if (null === self::$_instance) {
	    self::$_instance = new self();
	}
	return self::$_instance;
    }

    private function __construct() {
	global $wpdb;

	if (get_option('s3plugin_enabled', 'inactive') == 'active') {
	    $this->enabled = TRUE;
	} else {
	    $this->enabled = FALSE;
	}

	$this->blockDirectory = array('wpcf7_captcha', 'wp-admin');
	$this->blockExtension = array('php', 'htm', 'html');

	$this->s3CacheFolder = ABSPATH . 'wp-content' . DIRECTORY_SEPARATOR . 's3temp' . DIRECTORY_SEPARATOR;
	$this->siteURL = untrailingslashit(get_option('siteurl'));

	$this->s3AccessKey = get_option('s3plugin_amazon_key_id');
	$this->s3SecretKey = get_option('s3plugin_amazon_secret_key');
	$this->s3BucketName = get_option('s3plugin_amazon_bucket_name');
	$this->s3UseSSL = (bool) get_option('s3plugin_use_ssl', 0);
	$this->s3DirPrefix = get_option('s3plugin_dir_prefix');

	$this->s3UseCloudFrontURL = (bool) get_option('s3plugin_use_cloudfrontURL', 0);
	$this->s3CloudFrontURL = untrailingslashit(get_option('s3plugin_cloudfrontURL'));

	if ($this->s3UseCloudFrontURL && !empty($this->s3UseCloudFrontURL)) {
	    $this->isCloudFrontURLEnabled = TRUE;
	} else {
	    $this->isCloudFrontURLEnabled = FALSE;
	}


	$this->cronScheduleTime = get_option('s3plugin_cron_interval', 300);
	$this->cronUploadLimit = get_option('s3plugin_cron_limit', 20);

	$this->db = $wpdb;
	$this->tabeImageQueue = $wpdb->prefix . 's3_image_queue';

	register_activation_hook(plugin_basename(__FILE__), array(
	    &$this,
	    'activatePlugin'));
	register_deactivation_hook(plugin_basename(__FILE__), array(
	    &$this,
	    'deactivatePlugin'));
	add_action('admin_menu', array(&$this, 's3AdminMenu'));
	add_filter('script_loader_src', array(&$this, 'script_loader_src'), 99);
	add_filter('style_loader_src', array(&$this, 'style_loader_src'), 99);

	if (isset($_GET ['page']) && $_GET ['page'] == 's3plugin-options') {
	    ob_start();
	}

	if ($this->enabled) {
	    add_filter('the_content', array(&$this, 'theContent'), 12);
	    add_filter('cron_schedules', array(
		&$this,
		'cronSchedules'));
	    if (!wp_next_scheduled('s3CronHook')) {
		wp_schedule_event(time(), 's3_cron_schedule', 's3CronHook');
	    }
	    add_action('s3CronHook', array(&$this, 'executeCron'));
	} else {
	    if (wp_next_scheduled('s3CronHook')) {
		wp_clear_scheduled_hook('s3CronHook');
	    }
	}
    }

    private function __clone() {
	
    }

    function s3AdminMenu() {
	if (function_exists('add_submenu_page')) {
	    add_submenu_page('plugins.php', __('Amazon S3'), __('Amazon S3'), 'manage_options', 's3plugin-options', array(
		&$this,
		's3PluginOption'));
	}
    }

    function s3PluginOption() {
	if (isset($_POST ['Submit'])) {
	    if (function_exists('current_user_can') && !current_user_can('manage_options')) {
		die(__('Cheatin&#8217; uh?'));
	    }
	    update_option('s3plugin_amazon_key_id', $_POST ['s3plugin_amazon_key_id']);
	    update_option('s3plugin_amazon_secret_key', $_POST ['s3plugin_amazon_secret_key']);
	    update_option('s3plugin_amazon_bucket_name', $_POST ['s3plugin_amazon_bucket_name']);
	    if (isset($_POST ['s3plugin_use_ssl'])) {
		update_option('s3plugin_use_ssl', $_POST ['s3plugin_use_ssl']);
	    } else {
		delete_option('s3plugin_use_ssl');
	    }
	    if (isset($_POST ['s3plugin_use_cloudfrontURL'])) {
		update_option('s3plugin_use_cloudfrontURL', $_POST ['s3plugin_use_cloudfrontURL']);
	    } else {
		delete_option('s3plugin_use_cloudfrontURL');
	    }
	    if (isset($_POST ['s3plugin_clear_cache'])) {
		$this->recursive_remove_directory($this->s3CacheFolder, FALSE);
		$this->db->query("DELETE FROM `{$this->tabeImageQueue}` WHERE 1=1;");
		update_option('s3plugin_dir_prefix', substr(md5(time() + microtime()), 0, 6) . '/');
	    }

	    update_option('s3plugin_cloudfrontURL', $_POST ['s3plugin_cloudfrontURL']);

	    if ($this->checkS3AccessAndBucket($_POST ['s3plugin_amazon_key_id'], $_POST ['s3plugin_amazon_secret_key'], $useSSL, $_POST ['s3plugin_amazon_bucket_name']) === FALSE) {
		$s3PuginMessage = 'Connection failed. Plugin not active.';
		update_option('s3plugin_enabled', 'inactive');
	    } else {
		$s3PuginMessage = 'Settings saved. Plugin is active.';
		update_option('s3plugin_enabled', 'active');
	    }
	    update_option('s3plugin_cron_interval', $_POST ['s3plugin_cron_interval']);
	    update_option('s3plugin_cron_limit', $_POST ['s3plugin_cron_limit']);
	    ob_end_clean();
	    wp_redirect('plugins.php?page=s3plugin-options&msg=' . urlencode($s3PuginMessage));
	    exit();
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
	$this->db->query($query);
    }

    function deactivatePlugin() {
	
    }

    function cronSchedules($param) {
	return array(
	    's3_cron_schedule' => array(
		'interval' => $this->cronScheduleTime, // seconds
		'display' => 'S3 Cron Schedule'));
    }

    function checkS3AccessAndBucket($accessKey, $secretKey, $useSSL, $bucketName) {
	include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 's3-php5-curl' . DIRECTORY_SEPARATOR . 'S3.php';

	$s3Adapter = new S3($accessKey, $secretKey, $useSSL);
	$availableBuckets = @$s3Adapter->listBuckets();
	if (!empty($availableBuckets) && in_array($bucketName, $availableBuckets) == TRUE) {
	    return TRUE;
	}
	return FALSE;
    }

    function executeCron() {
	ignore_user_abort(true);
	set_time_limit(0);
	
	print "Hello";

	include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 's3-php5-curl' . DIRECTORY_SEPARATOR . 'S3.php';

	$s3Adapter = new S3($this->s3AccessKey, $this->s3SecretKey, $this->s3UseSSL);
	$availableBuckets = @$s3Adapter->listBuckets();
	cmVarDebug($availableBuckets);
	
	if (!empty($availableBuckets) && in_array($this->s3BucketName, $availableBuckets) == TRUE) {
	    $query = "SELECT * FROM {$this->tabeImageQueue} WHERE status='queue' ORDER BY added LIMIT {$this->cronUploadLimit};";
	    $filesToUpload = $this->db->get_results($query, ARRAY_A);
	    if (!empty($filesToUpload)) {
		foreach ($filesToUpload as $fileInfo) {
		    $shouldUpload = TRUE;
		    $fileStatus = 'error';
		    $filePath = ABSPATH . $fileInfo ['path'];
		    $fileObjectInfo = $s3Adapter->getObjectInfo($this->s3BucketName, $this->s3DirPrefix . $fileInfo ['path'], TRUE);
		    if (!empty($fileObjectInfo)) {
			if ($fileObjectInfo ['size'] != filesize($filePath)) {
			    if ($s3Adapter->deleteObject($this->s3BucketName, $this->s3DirPrefix . $fileInfo ['path']) === FALSE) {
				$shouldUpload = FALSE;
			    }
			} else {
			    $shouldUpload = FALSE;
			    $fileStatus = 'done';
			}
		    }
		    if ($shouldUpload) {
			$fileMimeType = $this->getFileType($filePath);
			if ($s3Adapter->putObjectFile($filePath, $this->s3BucketName, $this->s3DirPrefix . $fileInfo ['path'], S3::ACL_PUBLIC_READ, array(), $fileMimeType) === TRUE) {
			    $fileStatus = 'done';
			}
		    }
		    print "Processing: {$fileInfo['path']} Status: {$fileStatus}. <br />\n";
		    $this->writeToFile($this->getFilePath($fileInfo ['path']), $fileStatus);
		    $this->db->update($this->tabeImageQueue, array(
			'status' => $fileStatus), array(
			'id' => $fileInfo ['id']));
		}
	    }
	}
    }

    function getFileType($file) {
	$ext = strtolower(pathInfo($file, PATHINFO_EXTENSION));
	static $exts = array(
	'jpg' => 'image/jpeg',
	'gif' => 'image/gif',
	'png' => 'image/png',
	'tif' => 'image/tiff',
	'tiff' => 'image/tiff',
	'ico' => 'image/x-icon',
	'swf' => 'application/x-shockwave-flash',
	'pdf' => 'application/pdf',
	'zip' => 'application/zip',
	'gz' => 'application/x-gzip',
	'tar' => 'application/x-tar',
	'bz' => 'application/x-bzip',
	'bz2' => 'application/x-bzip2',
	'txt' => 'text/plain',
	'asc' => 'text/plain',
	'htm' => 'text/html',
	'html' => 'text/html',
	'css' => 'text/css',
	'js' => 'text/javascript',
	'xml' => 'text/xml',
	'xsl' => 'application/xsl+xml',
	'ogg' => 'application/ogg',
	'mp3' => 'audio/mpeg',
	'wav' => 'audio/x-wav',
	'avi' => 'video/x-msvideo',
	'mpg' => 'video/mpeg',
	'mpeg' => 'video/mpeg',
	'mov' => 'video/quicktime',
	'flv' => 'video/x-flv',
	'php' => 'text/x-php'
	);
	return isset($exts[$ext]) ? $exts[$ext] : 'application/octet-stream';
    }

    function script_loader_src($scriptURL) {
	if (!is_admin()) {
	    $urlParts = parse_url($scriptURL);
	    $justURL = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
	    $fileCDNURL = self::getCDNURL($justURL);
	    if ($fileCDNURL !== FALSE) {
		if (isset($urlParts['query']) && !empty($urlParts['query'])) {
		    return $fileCDNURL . '?' . $urlParts['query'];
		}
		return $fileCDNURL;
	    }
	}
	return $scriptURL;
    }

    function style_loader_src($cssURL) {
	if (!is_admin()) {
	    $urlParts = parse_url($cssURL);
	    $justURL = $urlParts['scheme'] . '://' . $urlParts['host'] . $urlParts['path'];
	    $fileCDNURL = self::getCDNURL($justURL);
	    if ($fileCDNURL !== FALSE) {
		if (isset($urlParts['query']) && !empty($urlParts['query'])) {
		    return $fileCDNURL . '?' . $urlParts['query'];
		}
		return $fileCDNURL;
	    } else {
		$realPath = $this->getRealPath($justURL);
		if (file_exists($realPath)) {
		    $cssFolder = dirname($realPath);
		    $cssRelatedFiles = $this->scanDirectoryRecursively($cssFolder);
		    if (!empty($cssRelatedFiles)) {
			foreach ($cssRelatedFiles as $relatedFile) {
			    $queueFiles = self::getCDNURL($this->siteURL . '/' . $relatedFile);
			}
		    }
		}
	    }
	}
	return $cssURL;
    }

    function scanDirectoryRecursively($directory, $filter=FALSE, $directoryFiles = array()) {

	if (substr($directory, -1) == DIRECTORY_SEPARATOR) {
	    $directory = substr($directory, 0, -1);
	}

	$extensionToInclude = array('css', 'png', 'gif', 'jpg', 'jpeg');

	if (!file_exists($directory) || !is_dir($directory)) {
	    return FALSE;
	} elseif (is_readable($directory)) {
	    $directory_list = opendir($directory);
	    while ($file = readdir($directory_list)) {
		if ($file != '.' && $file != '..') {
		    $path = $directory . DIRECTORY_SEPARATOR . $file;
		    if (is_readable($path)) {
			if (is_dir($path)) {
			    $directoryFiles = $this->scanDirectoryRecursively($path, $filter, $directoryFiles);
			} elseif (is_file($path)) {
			    $extension = strtolower(end(explode('.', $path)));
			    if (in_array($extension, $extensionToInclude)) {
				$directoryFiles[] = str_replace(ABSPATH, '', $path);
			    }
			}
		    }
		}
	    }
	    closedir($directory_list);
	    return $directoryFiles;
	} else {
	    return FALSE;
	}
    }

    function getRealPath($fileURL) {
	$relativePath = ltrim(str_replace($this->siteURL, '', $fileURL), '/');
	return ABSPATH . $relativePath;
    }

    public static function getCDNURL($fileURL) {
	$instance = self::getInstance();
	$relativePath = ltrim(str_replace($instance->siteURL, '', $fileURL), '/');
	$realPath = $instance->getRealPath($fileURL);
	if (file_exists($realPath)) {
	    foreach ($instance->blockDirectory as $blokedDirectory) {
		if (stripos($relativePath, $blokedDirectory) !== FALSE) {
		    return FALSE;
		}
	    }
	    $filetype = strtolower(substr(strstr($relativePath, '.'), 1));

	    foreach ($instance->blockExtension as $blockedExtension) {
		if ($blockedExtension == $filetype) {
		    return FALSE;
		}
	    }
	    $cacheFilePath = $instance->getFilePath($relativePath);
	    if (file_exists($cacheFilePath) === TRUE) {
		$fileContents = file_get_contents($cacheFilePath);
		if ($fileContents == 'done') {
		    if ($instance->isCloudFrontURLEnabled) {
			return $instance->s3CloudFrontURL . '/' . $instance->s3DirPrefix . $relativePath;
		    } else {
			return "http://{$instance->s3BucketName}.s3.amazonaws.com/" . $instance->s3DirPrefix . $relativePath;
		    }
		}
	    } else {
		$pathHash = md5($relativePath);
		$query = "SELECT count(*) FROM {$instance->tabeImageQueue} WHERE id='{$pathHash}';";
		if ($instance->db->get_var($query) == 0) {
		    $insertArray = array(
			'id' => $pathHash,
			'path' => $relativePath,
			'status' => 'queue',
			'added' => current_time('mysql'));
		    $instance->db->insert($instance->tabeImageQueue, $insertArray);
		} else {
		    $updateArray = array(
			'status' => 'queue',
			'added' => current_time('mysql'));
		    $instance->db->update($instance->tabeImageQueue, $updateArray, array(
			'id' => $pathHash));
		}
		$instance->writeToFile($cacheFilePath);
	    }
	}
	return FALSE;
    }

    public static function scanForImages($htmlContent) {
	$instance = self::getInstance();
	$mediaList = $instance->getMediaFromContent($htmlContent);
	if (!empty($mediaList)) {
	    foreach ($mediaList as $fileURL) {
		$fileCDNURL = self::getCDNURL($fileURL);
		if ($fileCDNURL !== FALSE) {
		    $htmlContent = str_replace($fileURL, $fileCDNURL, $htmlContent);
		}
	    }
	}
	return $htmlContent;
    }

    function theContent($the_content) {
	$id = 0;
	$post = &get_post($id);
	if ($post->post_status != 'publish') {
	    return $the_content;
	}
	return self::scanForImages($the_content);
    }

    function getMediaFromContent($content) {
	$regex = '/\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';
	preg_match_all($regex, $content, $matches);

	$mediaList = array();
	if (isset($matches [0]) && !empty($matches [0])) {
	    $mediaList = $matches [0];
	}
	return $mediaList;
    }

    function writeToFile($file, $status = 'QUEUE') {
	$fileDir = dirname($file);
	$this->createDirectory($fileDir);
	file_put_contents($file, $status);
    }

    protected function getFilePath($file) {
	$hash = md5($file);
	$path = $this->s3CacheFolder;
	for ($i = 0; $i < 3; $i++) {
	    $path .= substr($hash, 0, $i + 1) . DIRECTORY_SEPARATOR;
	}
	return $path . $hash . '.txt';
    }

    public static function createDirectory($path, $permission = 0755) {
	if (!file_exists($path)) {
	    S3Plugin::createDirectory(dirname($path), $permission);
	    mkdir($path, $permission);
	    chmod($path, $permission);
	    $handle = @fopen($path . '/index.php', 'w');
	    if ($handle) {
		fwrite($handle, '<?php print ":-)"; ?>');
		fclose($handle);
		chmod($path . '/index.php', 0644);
	    }
	}
    }

    function recursive_remove_directory($directory, $empty=FALSE) {
	if (substr($directory, -1) == '/') {
	    $directory = substr($directory, 0, -1);
	}
	if (!file_exists($directory) || !is_dir($directory)) {
	    return FALSE;
	} elseif (is_readable($directory)) {
	    $handle = opendir($directory);
	    while (FALSE !== ($item = readdir($handle))) {
		if ($item != '.' && $item != '..') {
		    $path = $directory . '/' . $item;
		    if (is_dir($path)) {
			$this->recursive_remove_directory($path);
		    } else {
			unlink($path);
		    }
		}
	    }
	    closedir($handle);
	    if ($empty == FALSE) {
		if (!rmdir($directory)) {
		    return FALSE;
		}
	    }
	}
	return TRUE;
    }

}

$wp_s3 = S3Plugin::getInstance();

function jsDebug($var='') {
    print "<script type='text/javascript'>\n";
    print "console.log('{$var}');\n";
    print "</script>\n";
}

function cmVarDebug($var, $echo = true) {
    $dump = "<div style=\"border:1px solid #f00;font-family:arial;font-size:12px;font-weight:normal;background:#f0f0f0;text-align:left;padding:3px;\"><pre>" . print_r($var, true) . "</pre></div>";
    if ($echo) {
	echo $dump;
    } else {
	return $dump;
    }
}

