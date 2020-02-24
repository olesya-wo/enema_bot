<?php
chdir(__DIR__);
require_once( 'settings.php' );
require_once( 'tg_bot_api.php' );

function send_to_admin( $text ) {
    global $admin_id;
    call_api_method( 'sendMessage', array( 'chat_id' => $admin_id, 'text' => $text ) );
}

// Подключиться к БД
$db = new SQLite3( $bot_name . '_db.sqlite3' );

// Сколько всего опросов на момент начала работы клинера
$sql = "SELECT COUNT(poll_id) FROM polls";
$res = $db->query( $sql );
if ( !$res ) {
    send_to_admin( "Error: CLEAN_COUNT_TOTAL_FAIL\n" . $db->lastErrorMsg() );
    $db->close();
    exit();
}
$log = 'In DB: ' . $res->fetchArray()[0] . "\n";

// Сколько опросов удалено юзерами
$sql = "SELECT COUNT(poll_id) FROM polls WHERE state = 'deleted'";
$res = $db->query( $sql );
if ( !$res ) {
    send_to_admin( "Error: CLEAN_COUNT_DELETED_FAIL\n" . $db->lastErrorMsg() );
    $db->close();
    exit();
}
$deleted = $res->fetchArray()[0];
$log     = $log . 'Deleted: ' . $deleted . "\n";

// Сколько опросов уже было помечено и их пора удалять
$sql     = "SELECT COUNT(poll_id) FROM polls WHERE state = 'clean'";
$res     = $db->query( $sql );
if ( !$res ) {
    send_to_admin( "Error: CLEAN_COUNT_CLEAN_FAIL\n" . $db->lastErrorMsg() );
    $db->close();
    exit();
}
$clean = $res->fetchArray()[0];
$log   = $log . 'Clean: ' . $clean . "\n";


// Удаление голосов у помеченных опросов
$sql = "DELETE FROM votes WHERE votes.poll_id IN (SELECT polls.poll_id FROM polls INNER JOIN votes ON ( votes.poll_id = polls.poll_id ) WHERE polls.state='clean');";
if ( !$db->exec( $sql ) ) {
    send_to_admin( "Error: CLEAN_DELETE_FAIL\n" . $db->lastErrorMsg() );
    $db->close();
    exit();
}

// Удаление помеченных опросов
$sql   = "DELETE FROM polls WHERE state = 'clean'";
if ( !$db->exec( $sql ) ) {
    send_to_admin( "Error: CLEAN_DELETE_FAIL\n" . $db->lastErrorMsg() );
    $db->close();
    exit();
}

// Помечаем удалённые
$sql = "UPDATE polls SET state = 'clean' WHERE state = 'deleted'";
if ( !$db->exec( $sql ) ) {
    send_to_admin( "Error: CLEAN_MARK_FAIL\n" . $db->lastErrorMsg() );
    $db->close();
    exit();
}

// Посылаем отчёт, только если реально было что-то сделано
if ( $clean > 0 or $deleted > 0 ) {
    // Сколько всего опросов на момент окончания работы клинера
    $sql = "SELECT COUNT(poll_id) FROM polls";
    $res = $db->query( $sql );
    if ( $res ) {
        $log = $log . "After in DB: " . $res->fetchArray()[0];
        send_to_admin( $log );
    } else {
        send_to_admin( "Error: CLEAN_COUNT_AFTER_FAIL\n" . $db->lastErrorMsg() );
    }
}

$db->close();
