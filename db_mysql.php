<?php

define( 'DB_TYPE_STR', PDO::PARAM_STR );
define( 'DB_TYPE_INT', PDO::PARAM_INT );

$increment = 'AUTO_INCREMENT';
$suff      = 'ENGINE=InnoDB CHARACTER SET=utf8mb4';

function db_init() {
    global $db_host, $db_name, $db_user, $db_pass;
    $db = null;
    try {
        $db = new PDO( "mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass, array( PDO::ATTR_PERSISTENT => true) );
    }
    catch ( PDOException $e ) {
        log_error( 'MySQL initialisation fail. ' . $e->getMessage() );
        return null;
    }
    if ( !$db ) {
        log_error( 'MySQL initialisation fail.' );
        return null;
    }
    if ( !$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT ) ) {
        log_error( 'ATTR_ERRMODE fail.' );
    }
    if ( !$db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_BOTH ) ) {
        log_error( 'ATTR_DEFAULT_FETCH_MODE fail.' );
    }
    return $db;
}

function db_close( $db ) {}

function db_last_error( $db ) { return $db ? ( $db->errorInfo()[0] . ': ' . $db->errorInfo()[2] ) : 'No DB given'; }

function db_last_insert_id( $db ) { return $db->lastInsertId(); }

function db_fetch( $query ) { return $query->fetch(); }

require_once( 'db.php' );
