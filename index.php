<?php
/* 
 * MUHAMMAD FADHIIL RACHMAN © 2016 Made This
 *
 * fadhiilrachman@gmail.com - fadilus.com
 *
 */
require 'Instagram.php';
////////////
if($_SERVER['REQUEST_METHOD']!=='GET') {
	die('Fadhiil Rachman Ganteng');
}
$ig = new Instagram();
$login=$ig->login();
if($login['status']=='fail') {
	die($login['message']);
}
$userId=$ig->getUserId();
$log=$FADILUS['config'][5].$userId.'_likesTimeline.log';
if(!file_exists( $log )) {
	fopen($log,'a');
}
if($FADILUS['config'][0]==true) {
	$timelineFeed=$ig->timelineFeed();
	if($timelineFeed['status']=='fail') {
		if($timelineFeed['message']=='login_required') {
			$fileArray = array(
				"cache/".$FADILUS['ig'][0]."-cookies.log",
				"cache/".$FADILUS['ig'][0]."-token.log",
				"cache/".$FADILUS['ig'][0]."-userId.log"
			);
			foreach ($fileArray as $value) {
				if (file_exists($value)) {
					unlink($value);
				}
			}
		}
		die($timelineFeed['message']);
	}
	for($i = 0; $i <= count($timelineFeed['items']); $i++) {
		if(isset($timelineFeed['items'][$i]['id'])&&empty($timelineFeed['items'][$i]['dr_ad_type'])&&$timelineFeed['items'][$i]['has_liked']==false) {
			$media_id=$timelineFeed['items'][$i]['id'];
			$log_data=file_get_contents($log);
			$log_data=explode("\n", $log_data);
			if(!in_array($media_id, $log_data)) {
				// like to media_id
				$do_like=$ig->like($media_id);
				if($do_like==false) {
					file_put_contents($FADILUS['config'][6], "(".date('Y/m/d H:i:s').") [LIKE_MEDIA] => ".$media_id." (NOT_FOUND)\n", FILE_APPEND);
					echo "[NOT_FOUND] [LIKE_MEDIA] => " . $media_id . "\n";
				}
				if($do_like['status']=='fail') {
					file_put_contents($FADILUS['config'][6], "(".date('Y/m/d H:i:s').") [LIKE_MEDIA] => ".$media_id." (ERROR)\n", FILE_APPEND);
					echo "[ERROR] [LIKE_MEDIA] => " . $media_id . "\n";
				}
				if($do_like['status']=='ok') {
					// insert to log
					file_put_contents($log, $media_id . "\n", FILE_APPEND);
					echo "[SUCCESS] [LIKE_MEDIA] => " . $media_id . "\n";
				}
			}
		}
	}
}