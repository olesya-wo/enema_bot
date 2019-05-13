<?php
$token = "";
$admin_id = "";
$lang = "en";
$lang_strings = null;
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
	return $lang_strings->{$ID};
}
function init_db(){
	$my_db_name = "enema_db.sqlite3";
	if (!file_exists($my_db_name)){
        $db = new SQLite3($my_db_name);
        $sql="CREATE TABLE `main_polls` (
											ID INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
											author_id INTEGER NOT NULL,
											datetime TEXT,
											name TEXT,
											poll_items TEXT,
											poll_data TEXT,
											text TEXT,
											type TEXT,
											file TEXT,
											state TEXT DEFAULT 'active'
										);";
        $db->query($sql);
    }else{
       $db = new SQLite3($my_db_name);
    }
	return $db;
}
function send_message($chat_id, $txt, $keyboard=null){
	global $token;
	$chat_id = intval($chat_id);
	file_get_contents("https://api.telegram.org/bot".$token."/sendMessage?chat_id=".$chat_id."&text=".urlencode($txt).
						"&parse_mode=HTML".($keyboard ? "&reply_markup=".json_encode($keyboard) : ""));
}
function send_photo($chat_id, $file, $caption, $keyboard){
	global $token;
	$chat_id = intval($chat_id);
	file_get_contents("https://api.telegram.org/bot".$token."/sendPhoto?chat_id=".$chat_id."&caption=".urlencode($caption)."&photo=".$file."&reply_markup=".json_encode($keyboard)."&parse_mode=HTML");
}
function send_document($chat_id, $file, $caption, $keyboard){
	global $token;
	$chat_id = intval($chat_id);
	file_get_contents("https://api.telegram.org/bot".$token."/sendDocument?chat_id=".$chat_id."&caption=".urlencode($caption)."&document=".$file."&reply_markup=".json_encode($keyboard)."&parse_mode=HTML");
}
function add_poll($author, $name, $text, $type, $file, $data){
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
	$dt = date("Y-m-d H:i:s");
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':author_id', $author, SQLITE3_INTEGER);
	$stmt->bindValue(':datetime', $dt, SQLITE3_TEXT);
	$stmt->bindValue(':name', $name, SQLITE3_TEXT);
	$stmt->bindValue(':poll_items', implode("\n", $data), SQLITE3_TEXT);
	$stmt->bindValue(':poll_data', str_repeat("\n", count($data)-1), SQLITE3_TEXT);
	$stmt->bindValue(':text', $text, SQLITE3_TEXT);
	$stmt->bindValue(':type', $type, SQLITE3_TEXT);
	$stmt->bindValue(':file', $file, SQLITE3_TEXT);
	$stmt->bindValue(':state', "active", SQLITE3_TEXT);
	$res = $stmt->execute();
	if ($res){
		$sql = "SELECT ID FROM main_polls WHERE author_id=".$author." AND datetime='".$dt."';";
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
	$sql = "SELECT poll_items FROM main_polls WHERE ID=".$id." AND author_id=".$author." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if ($res){
		$res = $res->fetchArray();
		if ($res==false){
			$db->close();
			return tr("IDNOTFOUND");
		}
		$res = $res["poll_items"];
		if ($data==null){
			$data = explode("\n", $res);
		}else{
			if (count(explode("\n", $res))!=count($data)){
				$db->close();
				return tr("ITEMCNTNOTMATCH");
			}
		}
	}else{
		$db->close();
		return "Error: EDIT_SELECT_FAIL\n".$db->lastErrorMsg();
	}
	$sql = "UPDATE main_polls SET poll_items=:poll_items, text=:text, type=:type, file=:file WHERE ID=".$id;
	$stmt = $db->prepare($sql);
	$stmt->bindValue(':poll_items', implode("\n", $data), SQLITE3_TEXT);
	$stmt->bindValue(':text', $text, SQLITE3_TEXT);
	$stmt->bindValue(':type', $type, SQLITE3_TEXT);
	$stmt->bindValue(':file', $file, SQLITE3_TEXT);
	if (!$stmt->execute()){
		$db->close();
		return "Error: EDIT_UPDATE_FAIL\n".$db->lastErrorMsg();
	}
	$db->close();
	return "OK";
}
function publish_poll($author, $chat_id, $id){
	$db = init_db();
	$author = intval($author);
	$chat_id = intval($chat_id);
	$id = intval($id);
	$sql = "SELECT author_id, poll_items, text, type, file FROM main_polls WHERE ID=".$id." AND (state='active' OR state='locked');";
	$res = $db->query($sql);
	if (!$res){
		$db->close();
		send_message($chat_id, "Error: PUBLISH_SELECT_FAIL.\n".$db->lastErrorMsg());
		return;
	}
	$db_author = 0;
	$items = "";
	$poll_text = "";
	$type = "";
	$file_id = "";
	$row = $res->fetchArray();
	if ($row){
		$db_author = $row["author_id"];
		$items = $row["poll_items"];
		$poll_text = $row["text"];
		$type = $row["type"];
		$file_id = $row["file"];
	}
	if ($db_author==0){
		$db->close();
		send_message($chat_id, tr("IDNOTFOUND"));
		return;
	}
	if ($db_author!=$author and $author!=null){
		$db->close();
		send_message($chat_id, tr("NOTAUTHOR"));
		return;
	}
	$poll_items = explode("\n", $items);
	$cnt = 0;
	$optimal_cnt = get_optimal_cols(count($poll_items));
	$inline_keyboard=array();
	$keyboard_row=array();
	foreach($poll_items as $item){
		$item = trim($item);
		$btn = array("text"=>$item,"callback_data"=>$id.':'.$cnt);
		$cnt += 1;
		array_push($keyboard_row, $btn);
		if ($cnt%$optimal_cnt == 0){
			array_push($inline_keyboard, $keyboard_row);
			$keyboard_row=array();
		}
	}
	if ($cnt%$optimal_cnt != 0){array_push($inline_keyboard, $keyboard_row);}
	$keyboard=array("inline_keyboard"=>$inline_keyboard);
	if ($type == "photo"){
		send_photo($chat_id, $file_id, $poll_text, $keyboard);
	}else if ($type == "document"){
		send_document($chat_id, $file_id, $poll_text, $keyboard);
	}else{
		send_message($chat_id, $poll_text, $keyboard);
	}
	$db->close();
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
			if ($row["state"]=="locked"){
				$list = $list."🔐 ";
			}
			if ($row["state"]=="deleted"){
				$list = $list."❌ ";
			}
			if ($row["state"]=="clean"){
				$list = $list."❌♻️ ";
			}
			$list = $list.$row["name"].":\n".$row["datetime"]."\nID: ".$row["ID"]."\n\n";
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
function vote($user_id, $id, $item){
	$db = init_db();
	$user_id = intval($user_id);
	$id = intval($id);
	$item = intval($item);
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
		$db->close();
		return $res;
	}
	$pos = mb_strpos($list[$curr], $user_id.",");
	$list[$curr]=mb_substr($list[$curr], 0, $pos).mb_substr($list[$curr], $pos+mb_strlen($user_id.","));
	$list[$item]=$list[$item].$user_id.",";
	$sql = "UPDATE main_polls SET poll_data='".implode("\n", $list)."' WHERE ID=".$id;
	$res = $db->exec($sql) ? "🔄 ".tr("CHANGED") : "Error: VOTE_UPDATE_FAIL1\n".$db->lastErrorMsg();
	$db->close();
	return $res;
}
function set_lock($user_id, $id, $new_state){
	$db = init_db();
	$user_id = intval($user_id);
	$id = intval($id);
	$sql = "SELECT state FROM main_polls WHERE ID=".$id." AND (state='active' OR state='locked');";
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
	if ($state==new_state){
		$db->close();
		return tr("INSTATE");
	}
	$sql = "UPDATE main_polls SET state='".$new_state."' WHERE ID=".$id;
	$res = $db->exec($sql) ? tr("STATECHANGED") : "Error: LOCK_UPDATE_FAIL\n".$db->lastErrorMsg();
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
	$res = "In DB".$cnt."\n".file_get_contents("last_poll_datetime.txt");
	$cnt = count(explode("\n", file_get_contents("users.txt"))) - 1;
	$res = $res."\nUsers: ".$cnt;
	$cnt = count(explode("\n", file_get_contents("authors.txt"))) - 1;
	$res = $res."\nAuthors: ".$cnt;
	return $res;
}
function get_optimal_cols($cnt){
	if ($cnt<4){return $cnt;}
	$max_k = 0.0;
	$max_n = 0;
	for ($i = 2; $i < 6; $i++){
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
// command handlers
function on_start($chat_id, $from_id, $txt){
	if ($chat_id == $from_id){
		$users = file_get_contents("users.txt");
		if (mb_strpos($users, $from_id."\n")===false){file_put_contents("users.txt", $users.$from_id."\n");}
		send_message($chat_id, tr("HELP"));
	}else{
		if (mb_substr(mb_strtolower($txt),0,19)=="/start@enema_bot id"){
			$id = mb_substr($txt,19);
			publish_poll($from_id, $chat_id, $id);
		}else if (mb_substr(mb_strtolower($txt),0,16)=="/start@enema_bot"){
			send_message($chat_id, tr("CHAT_HELP"));
		}
	}
}
function on_new($chat_id, $from_id, $txt){
	$txtpos = mb_strpos(mb_strtolower($txt), "/text");
	$itemspos = mb_strpos(mb_strtolower($txt), "/items");
	$poll_name = "";
	$poll_text = "";
	$poll_items = "";
	if ($txtpos and $itemspos and $itemspos > $txtpos){
		$poll_name = trim(mb_substr($txt, 4, $txtpos - 4));
		$poll_text = trim(mb_substr($txt, $txtpos + 5, $itemspos - $txtpos - 5));
		$poll_items = trim(mb_substr($txt, $itemspos + 6));
	}
	if (mb_strlen($poll_name)>0 and mb_strlen($poll_items)>0){
		$doc_type = "text";
		$file_id = "";
		if ($data->{'message'}->{'photo'} != null){
			$file_id = $data->{'message'}->{'photo'}[count($data->{'message'}->{'photo'})-1]->{'file_id'};
			$doc_type = "photo";
		}
		if ($data->{'message'}->{'document'} != null){
			$file_id = $data->{'message'}->{'document'}->{'file_id'};
			$doc_type = "document";
		}
		if ($doc_type == "text" and mb_strlen($poll_text)<1){
			send_message($chat_id, tr("EMPTYPOLL"));
		}else{
			$poll_items = explode("\n", $poll_items);
			$id = add_poll($from_id, $poll_name, $poll_text, $doc_type, $file_id, $poll_items);
			if (is_numeric($id)){
				publish_poll($from_id, $chat_id, $id);
				send_message($chat_id, tr("SHARE0").$id.tr("SHARE1").$id.tr("SHARE2"));
			}else{
				send_message($chat_id, $id);
			}
		}
	}else{
		send_message($chat_id, tr("NEWERROR"));
	}
}
function on_edit($chat_id, $from_id, $txt){
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
	}else if ($txtpos){
		$poll_id = trim(mb_substr($txt, 5, $txtpos - 5));
		$poll_text = trim(mb_substr($txt, $txtpos + 5));
	}
	if (mb_strlen($poll_id)<1){
		send_message($chat_id, tr("INVALIDFORMAT"));
		return;
	}
	$doc_type = "text";
	$file_id = "";
	if ($data->{'message'}->{'photo'} != null){
		$file_id = $data->{'message'}->{'photo'}[count($data->{'message'}->{'photo'})-1]->{'file_id'};
		$doc_type = "photo";
	}
	if ($data->{'message'}->{'document'} != null){
		$file_id = $data->{'message'}->{'document'}->{'file_id'};
		$doc_type = "document";
	}
	if ($doc_type == "text" and mb_strlen($poll_text)<1){
		send_message($chat_id, tr("EMPTYPOLL"));
		return;
	}
	if (mb_strtolower($poll_id) == "last"){
		$poll_id = get_last_id($from_id);
		if (!is_numeric($poll_id)){
			send_message($chat_id, $poll_id);
			return;
		}
	}
	$res = edit_poll($from_id, $poll_id, $poll_text, $doc_type, $file_id, $poll_items);
	if ($res!="OK"){
		send_message($chat_id, $res);
	}else{
		publish_poll($from_id, $chat_id, $poll_id);
	}
}
function on_publish($chat_id, $from_id, $txt){
	$id = trim(mb_substr($txt, 8));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			send_message($chat_id, $id);
			return;
		}
	}
	if (is_numeric($id)){
		publish_poll($from_id, $chat_id, $id);
	}else{
		send_message($chat_id, tr("INVALIDID"));
	}
}
function on_delete($chat_id, $from_id, $txt){
	$id = trim(mb_substr($txt, 7));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			send_message($chat_id, $id);
			return;
		}
	}
	$res = is_numeric($id) ? delete_poll($from_id, $id) : tr("INVALIDID");
	send_message($chat_id, $res);
}
function on_restore($chat_id, $from_id, $txt){
	$id = trim(mb_substr($txt, 8));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			send_message($chat_id, $id);
			return;
		}
	}
	$res = is_numeric($id) ? restore_poll($from_id, $id) : tr("INVALIDID");
	send_message($chat_id, $res);
}
function on_lock($chat_id, $from_id, $txt, $state){
	$id = trim(mb_substr($txt, 5));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			send_message($chat_id, $id);
			return;
		}
	}
	$res = is_numeric($id) ? set_lock($from_id, $id, $state) : tr("INVALIDID");
	send_message($chat_id, $res);
}
function on_get($chat_id, $from_id, $txt){
	$id = trim(mb_substr($txt, 4));
	if (mb_strtolower($id) == "last"){
		$id = get_last_id($from_id);
		if (!is_numeric($id)){
			send_message($chat_id, $id);
			return;
		}
	}
	$res = is_numeric($id) ? get_info($from_id, $id) : tr("INVALIDID");
	send_message($chat_id, $res);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$raw_inp = file_get_contents('php://input');
    $data = json_decode($raw_inp);
	if ($data->{'message'} != null) {
		$chat_id = $data->{'message'}->{'chat'}->{'id'};
		$from_id = $data->{'message'}->{'from'}->{'id'};
		load_translation($data->{'message'}->{'from'}->{'language_code'});
		$txt = $data->{'message'}->{'text'};
		$caption = $data->{'message'}->{'caption'};
		if ($txt == null and $caption != null){$txt = $caption;}
		if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/help"){
			if ($chat_id == $from_id){send_message($chat_id, tr("HELP"));}else{
				send_message($chat_id, tr("CHAT_HELP"));
			}
		}else if (mb_substr(mb_strtolower($txt), 0, 6 ) == "/start"){
			on_start($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 4 ) == "/new" and $chat_id == $from_id){
			on_new($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/edit" and $chat_id == $from_id){
			on_edit($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/publish"){
			on_publish($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 7 ) == "/delete" and $chat_id == $from_id){
			on_delete($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/restore" and $chat_id == $from_id){
			on_restore($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/lock" and $chat_id == $from_id){
			on_lock($chat_id, $from_id, $txt, "locked");
		}else if (mb_substr(mb_strtolower($txt), 0, 7 ) == "/unlock" and $chat_id == $from_id){
			on_lock($chat_id, $from_id, $txt, "active");
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/list" and $chat_id == $from_id){
			send_message($chat_id, get_list($from_id));
		}else if (mb_substr(mb_strtolower($txt), 0, 4 ) == "/get"){
			on_get($chat_id, $from_id, $txt);
		}else if (mb_substr(mb_strtolower($txt), 0, 5 ) == "/stat" and $chat_id == $from_id and $from_id==$admin_id){
			send_message($chat_id, get_stat());
		}
	}else if ($data->{'callback_query'} != null){
		$query_id = $data->{"callback_query"}->{"id"};
		$vote_data = $data->{"callback_query"}->{"data"};
		$lst = explode(":", $vote_data, $limit = 2);
		$user_id = $data->{"callback_query"}->{"from"}->{"id"};
		load_translation($data->{'callback_query'}->{'from'}->{'language_code'});
		$vote_answer = count($lst)==2 ? vote($user_id, $lst[0], $lst[1]) : tr("INVALIDBTNDATA");
		file_get_contents("https://api.telegram.org/bot".$token."/answerCallbackQuery?callback_query_id=".$query_id."&text=".urlencode($vote_answer));
	}else if ($data->{'channel_post'} != null){
		$chat_id = $data->{'channel_post'}->{'chat'}->{'id'};
		$txt = $data->{'channel_post'}->{'text'};
		if (mb_substr(mb_strtolower($txt), 0, 8 ) == "/publish"){
			$id = trim(mb_substr($txt, 8));
			if (is_numeric($id)){
				publish_poll(null, $chat_id, $id);
			}else{
				send_message($chat_id, tr("INVALIDID"));
			}
		}
	}
}else{
	echo("<h1>Enema bot</h1><br>Author: @lapka_td");
}
