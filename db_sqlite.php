<?php

define( 'DB_TYPE_STR', SQLITE3_TEXT );
define( 'DB_TYPE_INT', SQLITE3_INTEGER );

$increment = 'INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE';
$suff      = '';

function db_init() {
    global $bot_name;
    $db = new SQLite3( $bot_name . '_db.sqlite3' );
    if ( !$db ) {
        log_error( 'SQLite initialisation fail' );
        return;
    }
    $db->busyTimeout( 10000 );
    return $db;
}

function db_close( $db ) {
    $db->close();
}

function db_last_error( $db ) { return $db ? $db->lastErrorMsg() : 'No DB given'; }

function db_last_insert_id( $db ) { return $db->lastInsertRowID(); }

function db_fetch( $query ) { return $query->fetchArray(); }

require_once( 'db.php' );
