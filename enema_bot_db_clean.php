<?php
chdir(__DIR__);
$token = "";
$admin_id = "";

$db = new SQLite3("enema_db.sqlite3");
$sql = "SELECT COUNT(ID) FROM main_polls";
$res = $db->query($sql);
if ($res){
	$log = "In DB: ".$res->fetchArray()[0]."\n";
	$sql = "SELECT COUNT(ID) FROM main_polls WHERE state='deleted'";
	$res = $db->query($sql);
	if ($res){
		$deleted = $res->fetchArray()[0];
		$log = $log."Deleted: ".$deleted."\n";
		$sql = "SELECT COUNT(ID) FROM main_polls WHERE state='clean'";
		$res = $db->query($sql);
		if ($res){
			$clean = $res->fetchArray()[0];
			$log = $log."Clean: ".$clean."\n";
			$sql = "DELETE FROM main_polls WHERE state='clean'";
			if ($db->exec($sql)){
				$sql = "UPDATE main_polls SET state='clean' WHERE state='deleted'";
				if ($db->exec($sql)){
					if ($clean>0 or $deleted>0){
						$sql = "SELECT COUNT(ID) FROM main_polls";
						$res = $db->query($sql);
						if ($res){
							$log = $log."After in DB: ".$res->fetchArray()[0];
							file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode($log));
						}else{
							file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode("Error: CLEAN_COUNT_AFTER_FAIL\n".$db->lastErrorMsg()));
						}
					}
				}else{
					file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode("Error: CLEAN_MARK_FAIL\n".$db->lastErrorMsg()));
				}
			}else{
				file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode("Error: CLEAN_DELETE_FAIL\n".$db->lastErrorMsg()));
			}
		}else{
			file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode("Error: CLEAN_COUNT_CLEAN_FAIL\n".$db->lastErrorMsg()));
		}
	}else{
		file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode("Error: CLEAN_COUNT_DELETED_FAIL\n".$db->lastErrorMsg()));
	}
}else{
	file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$admin_id."&text=".urlencode("Error: CLEAN_COUNT_TOTAL_FAIL\n".$db->lastErrorMsg()));
}
$db->close();
