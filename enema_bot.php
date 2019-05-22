<?php
$token = "";
$admin_id = "";
$lang = "en";
$lang_strings = null;
// Localization
function load_translation($lang_code){
	global $lang;
	global $lang_strings;
	if (file_exists("tr_".$lang_code.".json")){$lang = $lang_code;}
	$lang_strings = json_decode(file_get_contents("tr_".$lang.".json"));
}
function tr($ID){
	global $lang;
	global $lang_strings;
	if (property_exists($lang_strings, $ID)){return $lang_strings->{$ID};}
	load_translation("en");
	return property_exists($lang_strings, $ID) ? $lang_strings->{$ID} : "";
}
// Answers
function call_api_method($method, $params){
	global $token;
	$postdata = http_build_query($params);
	$opts = array(
		'http'=>array(
			'ignore_errors'=>1,
			'method'=>"POST",
			'header'=>"Content-Type: application/x-www-form-urlencoded\r\n".
					  "Content-Length: ".strlen($postdata)."\r\n",
			'content'=>$postdata
		),
		"ssl"=>array(
			"allow_self_signed"=>true,
			"verify_peer"=>false,
			"verify_peer_name"=>false
		)
	);
	return file_get_contents("https://api.telegram.org/bot".$token."/".$method, false, stream_context_create($opts));
}
function answer_by_method($method, $params){
	$params["method"] = $method;
	header("Content-Type: application/json");
	echo json_encode($params);
}
// DB queries
function init_db(){
	$my_db_name = "enema_db.sqlite3";
	if (!file_exists($my_db_name)){
        $db = new SQLite3($my_db_name);
        $sql="CREATE TABLE `main_polls`(ID INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
										author_id INTEGER NOT NULL,
										datetime TEXT,
										name TEXT,
										poll_items TEXT,
										poll_data TEXT,
										text TEXT,
										type TEXT,
										file TEXT,
										state TEXT DEFAULT 'active',
										public INTEGER DEFAULT 0
									);";
        $db->query($sql);
		$sql="CREATE TABLE `stack`(author_id INTEGER NOT NULL PRIMARY KEY UNIQUE,
									type TEXT,
									file TEXT DEFAULT ''
								);";
        $db->query($sql);
    }else{
       $db = new SQLite3($my_db_name);
    }
	$db->busyTimeout(10000);
	return $db;
}
function add_poll($author, $name, $text, $type, $file, $poll_items){
	$author = intval($author);
	$db = init_db();
	$sql = "SELECT COUNT(ID) FROM main_polls WHERE author_id=".$author." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if ($res){
		$cnt = $res->fetchArray()[0];
		if ($cnt>9){
			$db->close();
			return tr("MAX10");
		}
	}else{
		$db->close();
		return "Error: ADD_COUNT0_FAIL\n".$db->lastErrorMsg();
	}
	$sql = "SELECT COUNT(ID) FROM main_polls WHERE author_id=".$author." AND (state='deleted' OR state='clean');";
	$res = $db->query($sql);
	if ($res){
		$cnt = $res->fetchArray()[0];
		if ($cnt>99){
			$db->close();
			return tr("MAX100");
		}
	}else{
		$db->close();
		return "Error: ADD_COUNT1_FAIL\n".$db->lastErrorMsg();
	}
	$id = 0;
	$sql = "INSERT INTO main_polls (author_id, datetime, name, poll_items, poll_data, text, type, file, state) 
						VALUES (:author_id, :datetime, :name, :poll_items, :poll_data, :text, :type, :file, :state);";
	$dt = date("Y-m-d");
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':author_id', $author, SQLITE3_INTEGER);
	$stmt->bindValue(':datetime', $dt, SQLITE3_TEXT);
	$stmt->bindValue(':name', $name, SQLITE3_TEXT);
	$stmt->bindValue(':poll_items', implode("\n", $poll_items), SQLITE3_TEXT);
	$stmt->bindValue(':poll_data', str_repeat("\n", count($poll_items)-1), SQLITE3_TEXT);
	$stmt->bindValue(':text', $text, SQLITE3_TEXT);
	$stmt->bindValue(':type', $type, SQLITE3_TEXT);
	$stmt->bindValue(':file', $file, SQLITE3_TEXT);
	$stmt->bindValue(':state', "active", SQLITE3_TEXT);
	$res = $stmt->execute();
	if ($res){
		$sql = "SELECT ID FROM main_polls WHERE author_id=".$author." ORDER BY ID DESC LIMIT 1;";
		$res = $db->query($sql);
		$id = 0;
		if ($res != false){
			$row = $res->fetchArray();
			if($row){$id = $row["ID"];}
			if ($id==0){$id = "Error: ADD_ID_FAIL.";}else{
				file_put_contents("last_poll_datetime.txt", "Last poll: ".date("Y-m-d")."\nLast ID: ".$id);
				$authors = file_get_contents("authors.txt");
				if (mb_strpos($authors, $author."\n")===false){file_put_contents("authors.txt", $authors.$author."\n");}
			}
		}else{
			$id = "Error: ADD_SELECT_FAIL.\n".$db->lastErrorMsg();
		}
	}else{
		$id = "Error: ADD_INSERT_FAIL\n".$db->lastErrorMsg();
	}
	$db->close();
	return $id;
}
function edit_poll($author, $id, $text, $type, $file, $data){
	$db = init_db();
	$author = intval($author);
	$id = intval($id);
	if (!$db->exec("BEGIN EXCLUSIVE TRANSACTION;")){
		$db->close();
		return "Error: EDIT_BEGIN_FAIL.\n".$db->lastErrorMsg();
	}
	$sql = "SELECT poll_items, poll_data FROM main_polls WHERE ID=".$id." AND author_id=".$author." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if ($res){
		$res = $res->fetchArray();
		if ($res==false){
			$db->close();
			return tr("IDNOTFOUND");
		}
		$items = $res["poll_items"];
		$v = $res["poll_data"];
		if ($data==null){
			$data = explode("\n", $items);
		}else{
			if (mb_strlen(trim($v))>0 and count(explode("\n", $items))!=count($data)){
				$db->close();
				return tr("ITEMCNTNOTMATCH");
			}
			if (mb_strlen(trim($v))<1 and count(explode("\n", $items))!=count($data)){
				$v = str_repeat("\n", count($data)-1);
			}
		}
	}else{
		$db->close();
		return "Error: EDIT_SELECT_FAIL\n".$db->lastErrorMsg();
	}
	$sql = "UPDATE main_polls SET poll_data=:poll_data, poll_items=:poll_items, text=:text, type=:type, file=:file WHERE ID=".$id;
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':poll_data', $v, SQLITE3_TEXT);
	$stmt->bindValue(':poll_items', implode("\n", $data), SQLITE3_TEXT);
	$stmt->bindValue(':text', $text, SQLITE3_TEXT);
	$stmt->bindValue(':type', $type, SQLITE3_TEXT);
	$stmt->bindValue(':file', $file, SQLITE3_TEXT);
	if (!$stmt->execute()){
		$db->close();
		return "Error: EDIT_UPDATE_FAIL\n".$db->lastErrorMsg();
	}
	if (!$db->exec("COMMIT;")){
		$db->close();
		return "Error: EDIT_COMMIT_FAIL\n".$db->lastErrorMsg();
	}
	$db->close();
	return "OK";
}
function publish_poll($author, $chat_id, $id){
	$db = init_db();
	$author = intval($author);
	$chat_id = intval($chat_id);
	$id = intval($id);
	$sql = "SELECT * FROM main_polls WHERE ID=".$id." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>"Error: PUBLISH_SELECT_FAIL.\n".$db->lastErrorMsg()));
		return "error";
	}
	$db_author = 0;
	$items = "";
	$data = "";
	$poll_text = "";
	$type = "";
	$file_id = "";
	$row = $res->fetchArray();
	if ($row){
		$db_author = $row["author_id"];
		$items = $row["poll_items"];
		$data = $row["poll_data"];
		$poll_text = $row["text"];
		$type = $row["type"];
		$file_id = $row["file"];
	}
	if ($db_author==0){
		$db->close();
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("IDNOTFOUND")));
		return "error";
	}
	if ($db_author!=$author and $author!=null){
		$db->close();
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("NOTAUTHOR")));
		return "error";
	}
	$keyboard=build_keyboard($id, $items, $row["public"]==1 ? $data : null);
	if ($type == "photo"){
		call_api_method("sendPhoto", array("chat_id"=>$chat_id, "caption"=>$poll_text, "photo"=>$file_id, "reply_markup"=>json_encode($keyboard), "parse_mode"=>"HTML"));
	}else if ($type == "document"){
		call_api_method("sendDocument", array("chat_id"=>$chat_id, "caption"=>$poll_text, "document"=>$file_id, "reply_markup"=>json_encode($keyboard), "parse_mode"=>"HTML"));
	}else if ($type == "audio"){
		call_api_method("sendAudio", array("chat_id"=>$chat_id, "caption"=>$poll_text, "audio"=>$file_id, "reply_markup"=>json_encode($keyboard), "parse_mode"=>"HTML"));
	}else if ($type == "voice"){
		call_api_method("sendVoice", array("chat_id"=>$chat_id, "caption"=>$poll_text, "voice"=>$file_id, "reply_markup"=>json_encode($keyboard), "parse_mode"=>"HTML"));
	}else if ($type == "sticker"){
		call_api_method("sendSticker", array("chat_id"=>$chat_id, "sticker"=>$file_id, "reply_markup"=>json_encode($keyboard)));
	}else if ($type == "video_note"){
		call_api_method("sendVideoNote", array("chat_id"=>$chat_id, "video_note"=>$file_id, "reply_markup"=>json_encode($keyboard)));
	}else if ($type == "venue"){
		$args = json_decode($file_id);
		call_api_method("sendVenue", array("chat_id"=>$chat_id, "latitude"=>$args->{'location'}->{'latitude'}, "longitude"=>$args->{'location'}->{'latitude'},
											"title"=>$poll_text, "address"=>$args->{'address'}, "reply_markup"=>json_encode($keyboard)));
	}else if ($type == "location"){
		$args = json_decode($file_id);
		call_api_method("sendLocation", array("chat_id"=>$chat_id, "latitude"=>$args->{'latitude'}, "longitude"=>$args->{'longitude'}, "reply_markup"=>json_encode($keyboard)));
	}else if ($type == "contact"){
		$args = json_decode($file_id);
		call_api_method("sendContact", array("chat_id"=>$chat_id, "phone_number"=>$args->{'phone_number'}, "first_name"=>$args->{'first_name'}, "reply_markup"=>json_encode($keyboard)));
	}else{
		call_api_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$poll_text, "reply_markup"=>json_encode($keyboard), "parse_mode"=>"HTML"));
	}
	$db->close();
	return "OK";
}
function delete_poll($author, $id){
	$db = init_db();
	$author = intval($author);
	$id = intval($id);
	$sql = "SELECT state FROM main_polls WHERE ID=".$id." AND author_id=".$author;
	$res = $db->query($sql);
	if ($res){
		$res = $res->fetchArray();
		if ($res){
			if ($res["state"]=="deleted" or $res["state"]=="clean"){
				$res = tr("ALREADYDELETED");
			}else{
				$sql = "UPDATE main_polls SET state='deleted' WHERE ID=".$id;
				$res = $db->exec($sql) ? tr("DELETED") : "Error: DEL_UPDATE_FAIL\n".$db->lastErrorMsg();
			}
		}else{
			$res = tr("IDNOTFOUND");
		}
	}else{
		$res = "Error: DEL_COUNT_FAIL\n".$db->lastErrorMsg();
	}
	$db->close();
	return $res;
}
function restore_poll($author, $id){
	$db = init_db();
	$author = intval($author);
	$id = intval($id);
	$sql = "SELECT state FROM main_polls WHERE ID=".$id." AND author_id=".$author;
	$res = $db->query($sql);
	if ($res){
		$res = $res->fetchArray();
		if ($res){
			if ($res["state"]=="active" or $res["state"]=="locked"){
				$res = tr("NOTDELETED");
			}else{
				$sql = "SELECT COUNT(ID) FROM main_polls WHERE author_id=".$author." AND (state='active' OR state='locked');";
				$res = $db->query($sql);
				if ($res){
					$cnt = $res->fetchArray()[0];
					if ($cnt>9){
						$db->close();
						return tr("MAX10");
					}
				}else{
					$db->close();
					return "Error: RESTORE_COUNT0_FAIL\n".$db->lastErrorMsg();
				}
				$sql = "UPDATE main_polls SET state='active' WHERE ID=".$id;
				$res = $db->exec($sql) ? tr("RESTORED") : "Error: RESTORE_UPDATE_FAIL\n".$db->lastErrorMsg();
			}
		}else{
			$res = tr("IDNOTFOUND");
		}
	}else{
		$res = "Error: RESTORE_COUNT1_FAIL\n".$db->lastErrorMsg();
	}
	$db->close();
	return $res;
}
function get_list($author){
	$db = init_db();
	$author = intval($author);
	$sql = "SELECT * FROM main_polls WHERE author_id=".$author;
	$res = $db->query($sql);
	$list = "";
	if ($res){
		$cnt = 0;
		while ($row = $res->fetchArray()) {
			if ($row["public"]==1){
				$list = $list."📊";
			}
			if ($row["state"]=="locked"){
				$list = $list."🔐 ";
			}
			if ($row["state"]=="deleted"){
				$list = $list."❌ ";
			}
			if ($row["state"]=="clean"){
				$list = $list."❌♻️ ";
			}
			$list = $list.$row["name"].":\n".$row["datetime"]." ID: ".$row["ID"]."\n\n";
			$cnt += 1;
		}
		if ($cnt==0){
			$list = tr("EMPTYLIST");
		}
	}else{
		$list = "Error: LIST_SELECT_FAIL.\n".$db->lastErrorMsg();
	}
	$db->close();
	return $list;
}
function get_list_inline($author){
	$db = init_db();
	$author = intval($author);
	$sql = "SELECT * FROM main_polls WHERE author_id=".$author." AND state='active'";
	$res = $db->query($sql);
	$list = array();
	if ($res){
		$cnt = 0;
		while ($row = $res->fetchArray()){
			$keyboard = build_keyboard($row["ID"], $row["poll_items"], $row["public"]==1 ? $row["poll_data"] : null);
			if ($row["type"]=="photo"){
				array_push($list, array("type"=>"photo", "id"=>strval($cnt), "title"=>$row["name"],
										"photo_file_id"=>$row["file"], "caption"=>$row["text"], "parse_mode"=>"HTML", "reply_markup"=>$keyboard, "description"=>$row["text"]));
			}else if ($row["type"]=="document"){
				array_push($list, array("type"=>"document", "id"=>strval($cnt), "title"=>$row["name"],
										"document_file_id"=>$row["file"], "caption"=>$row["text"], "parse_mode"=>"HTML", "reply_markup"=>$keyboard, "description"=>$row["text"]));
			}else if ($row["type"]=="audio"){
				array_push($list, array("type"=>"audio", "id"=>strval($cnt), "audio_file_id"=>$row["file"], "caption"=>$row["text"], "parse_mode"=>"HTML", "reply_markup"=>$keyboard));
			}else if ($row["type"]=="voice"){
				array_push($list, array("type"=>"voice", "id"=>strval($cnt), "title"=>$row["name"],
										"voice_file_id"=>$row["file"], "caption"=>$row["text"], "parse_mode"=>"HTML", "reply_markup"=>$keyboard, "description"=>$row["text"]));
			}else if ($row["type"]=="sticker"){
				array_push($list, array("type"=>"sticker", "id"=>strval($cnt), "sticker_file_id"=>$row["file"], "reply_markup"=>$keyboard));
			}else if ($row["type"]=="location"){
				$args = json_decode($row["file"]);
				array_push($list, array("type"=>"location", "id"=>strval($cnt), "title"=>$row["name"],
										"latitude"=>$args->{'latitude'}, "longitude"=>$args->{'longitude'}, "reply_markup"=>$keyboard));
			}else if ($row["type"]=="venue"){
				$args = json_decode($row["file"]);
				array_push($list, array("type"=>"venue", "id"=>strval($cnt), "latitude"=>$args->{'location'}->{'latitude'}, "longitude"=>$args->{'location'}->{'latitude'},
										 "title"=>$row["text"], "address"=>$args->{'address'}, "reply_markup"=>$keyboard));
			}else if ($row["type"]=="contact"){
				$args = json_decode($row["file"]);
				array_push($list, array("type"=>"contact", "id"=>strval($cnt), "phone_number"=>$args->{'phone_number'}, "first_name"=>$args->{'first_name'}, "reply_markup"=>$keyboard));
			}else if ($row["type"]=="video_note"){
				//array_push($list, array("type"=>"video", "id"=>strval($cnt), "video_file_id"=>$row["file"], "reply_markup"=>$keyboard));
			}else{
				array_push($list, array("type"=>"article", "id"=>strval($cnt), "title"=>$row["name"],
										"input_message_content"=>array("message_text"=>$row["text"], "parse_mode"=>"HTML"), "reply_markup"=>$keyboard, "description"=>$row["text"]));
			}
			$cnt += 1;
		}
	}
	$db->close();
	return $list;
}
function get_last_id($author){
	$db = init_db();
	$author = intval($author);
	$sql = "SELECT ID FROM main_polls WHERE author_id=".$author." ORDER BY ID DESC LIMIT 1";
	$res = $db->query($sql);
	$id = 0;
	if ($res){
		$row = $res->fetchArray();
		if ($row){$id = $row["ID"];}else{$id = tr("LASTIDERROR");}
	}else{
		$id = "Error: LAST_SELECT_FAIL.\n".$db->lastErrorMsg();
	}
	$db->close();
	return $id;
}
function get_info($author, $id){
	$db = init_db();
	$author = intval($author);
	$id = intval($id);
	$sql = "SELECT author_id, name, poll_items, poll_data FROM main_polls WHERE ID=".$id." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if ($res){
		$db_author = 0;
		$items = "";
		$data = "";
		$name = "";
		$row = $res->fetchArray();
		if ($row){
			$db_author = $row["author_id"];
			$items = $row["poll_items"];
			$data = $row["poll_data"];
			$name = $row["name"];
		}
		if ($db_author==0){
			$res = tr("IDNOTFOUND");
		}else if ($db_author!=$author){
			$res = tr("NOTAUTHOR");
		}else{
			$items = explode("\n", $items);
			$data = explode("\n", $data);
			if (count($items) != count($data)){
				$res = "Error: GET_ITEMS_FAIL.";
			}else{
				$arr = array();
				$res = $name.":\n";
				for ($i = 0; $i<count($items); $i += 1){
					$cnt = count(explode(",", $data[$i])) - 1;
					$arr[$items[$i]]=$cnt;
				}
				arsort($arr, $sort_flags=SORT_NUMERIC);
				foreach($arr as $k => $v){
					$res = $res.$k." - ".$v."\n";
				}
			}
		}
	}else{
		$res = "Error: GET_SELECT_FAIL.\n".$db->lastErrorMsg();
	}
	$db->close();
	return $res;
}
function set_lock($author, $id, $new_state){
	$db = init_db();
	$author = intval($author);
	$id = intval($id);
	$sql = "SELECT state FROM main_polls WHERE ID=".$id." AND (state='active' OR state='locked')"." AND author_id=".$author;
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		return "Error: LOCK_SELECT_FAIL.\n".$db->lastErrorMsg();
	}
	$state = "";
	$row = $res->fetchArray();
	if ($row){
		$state = $row["state"];
	}else{
		$db->close();
		return tr("IDNOTFOUND");
	}
	if ($state==$new_state){
		$db->close();
		return tr("INSTATE");
	}
	$sql = "UPDATE main_polls SET state='".$new_state."' WHERE ID=".$id;
	$res = $db->exec($sql) ? tr("STATECHANGED") : "Error: LOCK_UPDATE_FAIL\n".$db->lastErrorMsg();
	$db->close();
	return $res;
}
function set_public($author, $id, $new_state){
	$db = init_db();
	$author = intval($author);
	$id = intval($id);
	$sql = "SELECT public FROM main_polls WHERE ID=".$id." AND author_id=".$author;
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		return "Error: PUBLIC_SELECT_FAIL.\n".$db->lastErrorMsg();
	}
	$state = "";
	$row = $res->fetchArray();
	if ($row){
		$state = $row["public"];
	}else{
		$db->close();
		return tr("IDNOTFOUND");
	}
	if ($state==$new_state){
		$db->close();
		return tr("INSTATE");
	}
	$sql = "UPDATE main_polls SET public=".$new_state." WHERE ID=".$id;
	$res = $db->exec($sql) ? tr("STATECHANGED") : "Error: PUBLIC_UPDATE_FAIL\n".$db->lastErrorMsg();
	$db->close();
	return $res;
}
function get_stat(){
	$db = init_db();
	$sql = "SELECT COUNT(ID) FROM main_polls";
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		return "Error: STAT_COUNT_FAIL\n".$db->lastErrorMsg();
	}
	$cnt = $res->fetchArray()[0];
	$db->close();
	$res = "In DB: ".$cnt."\n".file_get_contents("last_poll_datetime.txt");
	$cnt = count(explode("\n", file_get_contents("users.txt"))) - 1;
	$res = $res."\nUsers: ".$cnt;
	$cnt = count(explode("\n", file_get_contents("authors.txt"))) - 1;
	$res = $res."\nAuthors: ".$cnt;
	return $res;
}
function get_users($file){
	global $token;
	$users = array_filter(explode("\n", file_get_contents($file.".txt")));
	$res = "";
	foreach($users as $user){
		$u = call_api_method("getChat", array("chat_id"=>$user));
		$udata = json_decode($u);
		$username = "";
		if ($udata->{"ok"}==true){
			$username = $udata->{"result"}->{"first_name"}." ";
			if (property_exists($udata->{"result"}, "last_name")){
				$username = $username.$udata->{"result"}->{"last_name"};
			}
			if (mb_strlen($username)<2){$username = $user;}
		}else{
			$username = $user;
		}
		$res = $res."<a href=\"tg://user?id=".$user."\">".$username."</a>\n";
	}
	return $res;
}
function vote($user_id, $id, $item){
	$db = init_db();
	$user_id = intval($user_id);
	$id = intval($id);
	$item = intval($item);
	if (!$db->exec("BEGIN EXCLUSIVE TRANSACTION;")){
		$db->close();
		return "Error: VOTE_BEGIN_FAIL.\n".$db->lastErrorMsg();
	}
	$sql = "SELECT poll_data, state FROM main_polls WHERE ID=".$id." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		return "Error: VOTE_SELECT_FAIL.\n".$db->lastErrorMsg();
	}
	$data = "";
	$state = "";
	$row = $res->fetchArray();
	if ($row){
		$data = $row["poll_data"];
		$state = $row["state"];
	}else{
		$db->close();
		return "✖️ ".tr("POLLNOTFOUND");
	}
	if ($state!="active"){
		$db->close();
		return "🔐 ".tr("POLLLOCKED");
	}
	$list = explode("\n", $data);
	$curr = -1;
	$cnt = -1;
	foreach($list as $i){
		$cnt += 1;
		if (mb_strpos($i, $user_id.",")===false){continue;}else{
			$curr = $cnt;
			break;
		}
	}
	if ($curr==$item){
		$db->close();
		return "☑️ ".tr("COUNTED");
	}
	if ($curr==-1){
		$list[$item]=$list[$item].$user_id.",";
		$sql = "UPDATE main_polls SET poll_data='".implode("\n", $list)."' WHERE ID=".$id;
		$res = $db->exec($sql) ? "✅ ".tr("VOTED") : "Error: VOTE_UPDATE_FAIL0\n".$db->lastErrorMsg();
		if (!$db->exec("COMMIT;")){
			$db->close();
			return tr("DBBUSY");
		}
		$db->close();
		return $res;
	}
	$pos = mb_strpos($list[$curr], $user_id.",");
	$list[$curr]=mb_substr($list[$curr], 0, $pos).mb_substr($list[$curr], $pos+mb_strlen($user_id.","));
	$list[$item]=$list[$item].$user_id.",";
	$sql = "UPDATE main_polls SET poll_data='".implode("\n", $list)."' WHERE ID=".$id;
	$res = $db->exec($sql) ? "🔄 ".tr("CHANGED") : "Error: VOTE_UPDATE_FAIL1\n".$db->lastErrorMsg();
	if (!$db->exec("COMMIT;")){
		$db->close();
		return tr("DBBUSY");
	}
	$db->close();
	return $res;
}
// Util
function get_optimal_cols($cnt, $max_len){
	if ($max_len>12){return 1;}
	$cols = intval(round(25/$max_len));
	if ($cols>8){$cols=8;}
	if ($cnt<=$cols){return $cols;}
	$max_k = 0.0;
	$max_n = 0;
	// cols - [2,8]
	for ($i = 2; $i<=$cols; $i++){
		$k = $cnt%$i;
		if ($k==0){
			$max_k = 1.0;
			$max_n = $i;
		}else{
			$k = (float)$k/(float)$i;
			if ($k>$max_k){
				$max_k = $k;
				$max_n = $i;
			}
		}
	}
	return $max_n;
}
function build_keyboard($id, $poll_items, $poll_data){
	// Build first array
	$poll_items = explode("\n", $poll_items);
	// Build second array
	$score = null;
	$applen = 0;
	if ($poll_data){
		$applen = 4;
		$score = explode("\n", $poll_data);
		for ($i=0; $i<count($score); $i++){
			$t = count(explode(",", $score[$i]))-1;
			$score[$i]=$t>0 ? " [".$t."]" : "";
		}
	}else{
		$score = $poll_items;
		for ($i=0; $i<count($score); $i++){$score[$i]="";}
	}
	// Get max item length
	$max_len = 0;
	for ($i=0; $i<count($poll_items); $i++){
		$len = mb_strlen($poll_items[$i])+$applen;
		if ($len>$max_len){$max_len=$len;}
	}
	//
	$cnt = 0;
	$optimal_cnt = get_optimal_cols(count($poll_items), $max_len);
	$inline_keyboard=array();
	$keyboard_row=array();
	foreach($poll_items as $item){
		$item = trim($item);
		$btn = array("text"=>$item.$score[$cnt],"callback_data"=>$id.':'.$cnt);
		$cnt += 1;
		array_push($keyboard_row, $btn);
		if ($cnt%$optimal_cnt == 0){
			array_push($inline_keyboard, $keyboard_row);
			$keyboard_row=array();
		}
	}
	if ($cnt%$optimal_cnt != 0){array_push($inline_keyboard, $keyboard_row);}
	return array("inline_keyboard"=>$inline_keyboard);
}
// command handlers
function on_help($chat_id){
	global $lang;
	$help_file = "help_";
	$help = "";
	if (file_exists($help_file.$lang.".txt")){
		$help = file_get_contents($help_file.$lang.".txt");
	}else{
		$help = file_get_contents($help_file."en.txt");
	}
	answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$help, "disable_web_page_preview"=>true, "parse_mode"=>"HTML"));
}
function on_start($chat_id, $from_id, $txt){
	if ($chat_id == $from_id){
		$users = file_get_contents("users.txt");
		if (mb_strpos($users, $from_id."\n")===false){file_put_contents("users.txt", $users.$from_id."\n");}
		on_help($chat_id);
	}else{
		if (mb_substr(mb_strtolower($txt),0,19)=="/start@enema_bot id"){
			$id = mb_substr($txt,19);
			publish_poll($from_id, $chat_id, $id);
		}else if (mb_substr(mb_strtolower($txt),0,16)=="/start@enema_bot"){
			on_help($chat_id);
		}
	}
}
function on_new($chat_id, $txt){
	global $data;
	$txtpos = mb_strpos(mb_strtolower($txt), "/text");
	$itemspos = mb_strpos(mb_strtolower($txt), "/items");
	$poll_name = "";
	$poll_text = "";
	$poll_items = "";
	if ($txtpos and $itemspos and $itemspos > $txtpos){
		$poll_name = explode("\n", trim(mb_substr($txt, 4, $txtpos - 4)))[0];
		$poll_text = trim(mb_substr($txt, $txtpos + 5, $itemspos - $txtpos - 5));
		$poll_items = trim(mb_substr($txt, $itemspos + 6));
	}
	if (mb_strlen($poll_name)>0 and mb_strlen($poll_items)>0){
		$doc_type = "text";
		$file_id = "";
		if (property_exists($data->{'message'}, 'photo')){
			$file_id = $data->{'message'}->{'photo'}[count($data->{'message'}->{'photo'})-1]->{'file_id'};
			$doc_type = "photo";
		}
		if (property_exists($data->{'message'}, 'document')){
			$file_id = $data->{'message'}->{'document'}->{'file_id'};
			$doc_type = "document";
		}
		if (property_exists($data->{'message'}, 'audio')){
			$file_id = $data->{'message'}->{'audio'}->{'file_id'};
			$doc_type = "audio";
		}
		if ($doc_type == "text" and mb_strlen($poll_text)<1){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("EMPTYPOLL")));
		}else{
			$poll_items = explode("\n", $poll_items);
			if (count($poll_items)==1 and mb_strpos($poll_items[0], ";")){
				$bck = $poll_items;
				$poll_items = array_filter(explode(";", $poll_items[0]));
				if (count($poll_items)==1){$poll_items = $bck;}
			}
			$id = add_poll($chat_id, $poll_name, $poll_text, $doc_type, $file_id, $poll_items);
			if (is_numeric($id)){
				publish_poll($chat_id, $chat_id, $id);
				call_api_method("sendMessage", array("chat_id"=>$chat_id, "text"=>sprintf(tr("SHARE"), $id, $id), "disable_web_page_preview"=>true));
				answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("DONATE"), "disable_web_page_preview"=>true, "parse_mode"=>"HTML"));
			}else{
				answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			}
		}
	}else{
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("NEWERROR")));
	}
}
function on_edit($chat_id, $txt){
	global $data;
	$txtpos = mb_strpos(mb_strtolower($txt), "/text");
	$itemspos = mb_strpos(mb_strtolower($txt), "/items");
	$poll_id = "";
	$poll_text = "";
	$poll_items = null;
	if ($txtpos and $itemspos and $itemspos > $txtpos){
		$poll_id = trim(mb_substr($txt, 5, $txtpos - 5));
		$poll_text = trim(mb_substr($txt, $txtpos + 5, $itemspos - $txtpos - 5));
		$poll_items = trim(mb_substr($txt, $itemspos + 6));
		$poll_items = explode("\n", $poll_items);
		if (count($poll_items)==1 and mb_strpos($poll_items[0], ";")){
			$bck = $poll_items;
			$poll_items = array_filter(explode(";", $poll_items[0]));
			if (count($poll_items)==1){$poll_items = $bck;}
		}
	}else if ($txtpos){
		$poll_id = trim(mb_substr($txt, 5, $txtpos - 5));
		$poll_text = trim(mb_substr($txt, $txtpos + 5));
	}
	if (mb_strlen($poll_id)<1){
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("INVALIDFORMAT")));
		return;
	}
	$doc_type = "text";
	$file_id = "";
	if (property_exists($data->{'message'}, 'photo')){
		$file_id = $data->{'message'}->{'photo'}[count($data->{'message'}->{'photo'})-1]->{'file_id'};
		$doc_type = "photo";
	}
	if (property_exists($data->{'message'}, 'document')){
		$file_id = $data->{'message'}->{'document'}->{'file_id'};
		$doc_type = "document";
	}
	if (property_exists($data->{'message'}, 'audio')){
		$file_id = $data->{'message'}->{'audio'}->{'file_id'};
		$doc_type = "audio";
	}
	if ($doc_type == "text" and mb_strlen($poll_text)<1){
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("EMPTYPOLL")));
		return;
	}
	if (mb_strtolower($poll_id) == "last"){
		$poll_id = get_last_id($chat_id);
		if (!is_numeric($poll_id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$poll_id));
			return;
		}
	}
	$res = edit_poll($chat_id, $poll_id, $poll_text, $doc_type, $file_id, $poll_items);
	if ($res!="OK"){
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$res));
	}else{
		publish_poll($chat_id, $chat_id, $poll_id);
	}
}
function on_publish($chat_id, $from_id, $txt){
	$id = trim(mb_substr($txt, 8));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	if (is_numeric($id)){
		publish_poll($from_id, $chat_id, $id);
	}else{
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("INVALIDID")));
	}
}
function on_delete($chat_id, $txt){
	$id = trim(mb_substr($txt, 7));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($chat_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	$res = is_numeric($id) ? delete_poll($chat_id, $id) : tr("INVALIDID");
	answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$res));
}
function on_restore($chat_id, $txt){
	$id = trim(mb_substr($txt, 8));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($chat_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	$res = is_numeric($id) ? restore_poll($chat_id, $id) : tr("INVALIDID");
	answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$res));
}
function on_lock($chat_id, $id, $state){
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($chat_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	$res = is_numeric($id) ? set_lock($chat_id, $id, $state) : tr("INVALIDID");
	answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$res));
}
function on_public($chat_id, $id, $state){
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($chat_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	$res = is_numeric($id) ? set_public($chat_id, $id, $state) : tr("INVALIDID");
	answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$res));
}
function on_get($chat_id, $from_id, $txt){
	$id = trim(mb_substr($txt, 4));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	$res = is_numeric($id) ? get_info($from_id, $id) : tr("INVALIDID");
	answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$res));
}
function on_feedback($chat_id, $txt){
	global $admin_id;
	global $data;
	$txt = trim(mb_substr($txt, 9));
	if (mb_strlen($txt)>0){
		$file_id = "";
		if (property_exists($data->{'message'}, 'photo')){
			$file_id = $data->{'message'}->{'photo'}[count($data->{'message'}->{'photo'})-1]->{'file_id'};
			call_api_method("sendPhoto", array("chat_id"=>$admin_id, "caption"=>"#feedback\n".$txt, "photo"=>$file_id, "parse_mode"=>"HTML"));
		}else if (property_exists($data->{'message'}, 'document')){
			$file_id = $data->{'message'}->{'document'}->{'file_id'};
			call_api_method("sendDocument", array("chat_id"=>$admin_id, "caption"=>"#feedback\n".$txt, "document"=>$file_id, "parse_mode"=>"HTML"));
		}else{
			call_api_method("sendMessage", array("chat_id"=>$admin_id, "text"=>"#feedback\n".$txt, "parse_mode"=>"HTML"));
		}
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("FEEDBACKOK")));
	}else{
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("FEEDBACKERROR")));
	}
}
function on_attach($chat_id, $txt){
	$id = trim(mb_substr($txt, 7));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($chat_id);
		if (!is_numeric($id)){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>$id));
			return;
		}
	}
	$id = intval($id);
	$db = init_db();
	$sql = "SELECT type, file FROM stack WHERE author_id=".$chat_id;
	$res = $db->query($sql);
	if (!$res){
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>"ATTACH_SELECT_FAIL.\n".$db->lastErrorMsg()));
		$db->close();
		return;
	}
	$row = $res->fetchArray();
	if (!$row or $row["type"]=="none" or mb_strlen($row["file"])<1){
		$db->close();
		answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("INVALIDMEDIA")));
		return;
	}else{
		$sql = "UPDATE main_polls SET type=:type, file=:file WHERE ID=".$id;
		$stmt = $db->prepare($sql);
		$stmt->bindValue(':type', $row["type"], SQLITE3_TEXT);
		$stmt->bindValue(':file', $row["file"], SQLITE3_TEXT);
		if (!$stmt->execute()){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>"ATTACH_UPDATE_FAIL.\n".$db->lastErrorMsg()));
			$db->close();
			return;
		}
		if (publish_poll($chat_id, $chat_id, $id)=="OK"){answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("STATECHANGED")));}
	}
	$db->close();
}
//
function on_callback($query_id, $user_id, $btn_data, $chat_id, $message_id, $inline){
	// vote
	$lst = explode(":", $btn_data, $limit = 2);
	$vote_answer = count($lst)==2 ? vote($user_id, $lst[0], $lst[1]) : tr("INVALIDBTNDATA");
	answer_by_method("answerCallbackQuery", array("callback_query_id"=>$query_id, "text"=>$vote_answer));
	// update keyboard
	$poll_id = intval($lst[0]);
	$db = init_db();
	$sql = "SELECT poll_items, poll_data, public FROM main_polls WHERE ID=".$poll_id;
	$res = $db->query($sql);
	if ($res){
		$row = $res->fetchArray();
		if ($row){
			$keyboard = build_keyboard($poll_id, $row["poll_items"], $row["public"]==1 ? $row["poll_data"] : null);
			if ($inline){
				call_api_method("editMessageReplyMarkup", array("inline_message_id"=>$message_id, "reply_markup"=>json_encode($keyboard)));
			}else{
				call_api_method("editMessageReplyMarkup", array("chat_id"=>$chat_id, "message_id"=>$message_id, "reply_markup"=>json_encode($keyboard)));
			}
		}
	}
	$db->close();
}
function update_stack($user_id){
	$user_id = intval($user_id);
	global $data;
	$type = "none";
	$file = "";
	if (property_exists($data->{'message'}, 'photo')){
		$type = "photo";
		$file = $data->{'message'}->{'photo'}[count($data->{'message'}->{'photo'})-1]->{'file_id'};
	}else if (property_exists($data->{'message'}, 'document')){
		$type = "document";
		$file = $data->{'message'}->{'document'}->{'file_id'};
	}else if (property_exists($data->{'message'}, 'audio')){
		$type = "audio";
		$file = $data->{'message'}->{'audio'}->{'file_id'};
	}else if (property_exists($data->{'message'}, 'voice')){
		$type = "voice";
		$file = $data->{'message'}->{'voice'}->{'file_id'};
	}else if (property_exists($data->{'message'}, 'sticker')){
		$type = "sticker";
		$file = $data->{'message'}->{'sticker'}->{'file_id'};
	}else if (property_exists($data->{'message'}, 'video_note')){
		$type = "video_note";
		$file = $data->{'message'}->{'video_note'}->{'file_id'};
	}else if (property_exists($data->{'message'}, 'venue')){
		$type = "venue";
		$file = json_encode($data->{'message'}->{'venue'});
	}else if (property_exists($data->{'message'}, 'location')){
		$type = "location";
		$file = json_encode($data->{'message'}->{'location'});
	}else if (property_exists($data->{'message'}, 'contact')){
		$type = "contact";
		$file = json_encode($data->{'message'}->{'contact'});
	}
	$db = init_db();
	$sql = "SELECT type, file FROM stack WHERE author_id=".$user_id;
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		return;
	}
	$row = $res->fetchArray();
	if ($row){
		if ($row["type"]==$type and $row["file"]==$file){
			$db->close();
			return;
		}
		$sql = "UPDATE stack SET type=:type, file=:file WHERE author_id=".$user_id;
		$stmt = $db->prepare($sql);
		$stmt->bindValue(':type', $type, SQLITE3_TEXT);
		$stmt->bindValue(':file', $file, SQLITE3_TEXT);
		$stmt->execute();
	}else{
		$sql = "INSERT INTO stack (author_id, type, file) VALUES (:author_id, :type, :file);";
		$stmt = $db->prepare($sql);
		$stmt->bindValue(':author_id', $user_id, SQLITE3_INTEGER);
		$stmt->bindValue(':type', $type, SQLITE3_TEXT);
		$stmt->bindValue(':file', $file, SQLITE3_TEXT);
		$stmt->execute();
	}
	$db->close();
}
// Parser
date_default_timezone_set('Europe/Moscow');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$raw_inp = file_get_contents('php://input');
    $data = json_decode($raw_inp);
	if (property_exists($data, 'message') and isset($data->{'message'})) {
		$chat_id = $data->{'message'}->{'chat'}->{'id'};
		$from_id = $data->{'message'}->{'from'}->{'id'};
		load_translation($data->{'message'}->{'from'}->{'language_code'});
		$txt = property_exists($data->{'message'}, 'text') ? $data->{'message'}->{'text'} : null;
		$caption = property_exists($data->{'message'}, 'caption') ? $data->{'message'}->{'caption'} : null;
		if ($txt == null and $caption != null){$txt = $caption;}
		$txt = trim($txt);
		if (property_exists($data->{'message'}, 'media_group_id') and $txt!=null){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("GROUPNOTSUPPORT")));
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/help"){
			on_help($chat_id);
		}else if (mb_substr(mb_strtolower($txt), 0, 6 ) == "/start"){
			on_start($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/publish"){
			on_publish($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 4 ) == "/get"){
			on_get($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 4 ) == "/new" and $chat_id == $from_id){
			on_new($chat_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/edit" and $chat_id == $from_id){
			on_edit($chat_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 7 ) == "/attach" and $chat_id == $from_id){
			on_attach($chat_id, $txt);
			$chat_id=0;
		}else if (mb_substr(mb_strtolower($txt), 0, 7 ) == "/delete" and $chat_id == $from_id){
			on_delete($chat_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/restore" and $chat_id == $from_id){
			on_restore($chat_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/lock" and $chat_id == $from_id){
			$id = trim(mb_substr($txt, 5));
			on_lock($chat_id, $id, "locked");
		}else if (mb_substr(mb_strtolower($txt), 0, 7 ) == "/unlock" and $chat_id == $from_id){
			$id = trim(mb_substr($txt, 7));
			on_lock($chat_id, $id, "active");
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/show" and $chat_id == $from_id){
			$id = trim(mb_substr($txt, 5));
			on_public($chat_id, $id, 1);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/hide" and $chat_id == $from_id){
			$id = trim(mb_substr($txt, 5));
			on_public($chat_id, $id, 0);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/list" and $chat_id == $from_id){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>get_list($chat_id)));
		}else if (mb_substr(mb_strtolower($txt), 0, 9 ) == "/feedback" and $chat_id == $from_id){
			on_feedback($chat_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/stat" and $chat_id == $from_id and $from_id==$admin_id){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>get_stat()));
		}else if (mb_substr(mb_strtolower($txt), 0, 6 ) == "/users" and $chat_id == $from_id and $from_id==$admin_id){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>get_users("users"), "parse_mode"=>"HTML"));
		}else if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/authors" and $chat_id == $from_id and $from_id==$admin_id){
			answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>get_users("authors"), "parse_mode"=>"HTML"));
		}
		if ($chat_id == $from_id){update_stack($chat_id);}
	}else if (property_exists($data, 'callback_query') and isset($data->{'callback_query'})){
		$query_id = $data->{"callback_query"}->{"id"};
		$user_id = $data->{"callback_query"}->{"from"}->{"id"};
		$btn_data = $data->{"callback_query"}->{"data"};
		load_translation($data->{'callback_query'}->{'from'}->{'language_code'});
		$inline = false;
		$chat_id = 0;
		if (property_exists($data->{"callback_query"}, 'inline_message_id')){
			$message_id = $data->{"callback_query"}->{"inline_message_id"};
			$inline = true;
		}else{
			$chat_id = $data->{"callback_query"}->{"message"}->{"chat"}->{"id"};
			$message_id = $data->{"callback_query"}->{"message"}->{"message_id"};
		}
		on_callback($query_id, $user_id, $btn_data, $chat_id, $message_id, $inline);
	}else if (property_exists($data, 'channel_post') and isset($data->{'channel_post'})){
		$chat_id = $data->{'channel_post'}->{'chat'}->{'id'};
		$txt = $data->{'channel_post'}->{'text'};
		if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/publish"){
			$id = trim(mb_substr($txt, 8));
			if (is_numeric($id)){publish_poll(null, $chat_id, $id);}else{answer_by_method("sendMessage", array("chat_id"=>$chat_id, "text"=>tr("INVALIDID")));}
		}
	}else if (property_exists($data, 'inline_query') and isset($data->{'inline_query'})){
		$query_id = $data->{"inline_query"}->{"id"};
		$user_id = $data->{"inline_query"}->{"from"}->{"id"};
		load_translation($data->{'inline_query'}->{'from'}->{'language_code'});
		$polls = get_list_inline($user_id);
		if (count($polls)==0){
			answer_by_method("answerInlineQuery", array("inline_query_id"=>$query_id, "results"=>"[]", "cache_time"=>"10", "is_personal"=>true,
							"switch_pm_text"=>tr("EMPTYLIST"), "switch_pm_parameter"=>"ID"));
		}else{
			answer_by_method("answerInlineQuery", array("inline_query_id"=>$query_id, "results"=>json_encode($polls), "cache_time"=>"10", "is_personal"=>true));
		}
	}
}else{
	echo("<h1>Enema bot</h1><img src=\"enema.png\"><br>Author: @lapka_td<br><br>");
	echo date("Y-m-d H:i:s");
}
