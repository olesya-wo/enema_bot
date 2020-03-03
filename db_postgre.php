<?php

define( 'DB_TYPE_STR', PDO::PARAM_STR );
define( 'DB_TYPE_INT', PDO::PARAM_INT );

$increment = 'SERIAL PRIMARY KEY';
$suff      = '';

function db_init() {
    global $db_host, $db_name, $db_user, $db_pass;
    $db = null;
    try {
        $db = new PDO( "pgsql:host=$db_host dbname=$db_name", $db_user, $db_pass, array( PDO::ATTR_PERSISTENT => true) );
    }
    catch ( PDOException $e ) {
        logger( 'PostgreSQL initialisation fail. ' . $e->getMessage() );
        return null;
    }
    if ( !$db ) {
        logger( 'PostgreSQL initialisation fail.' );
        return null;
    }
    if ( !$db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT ) ) {
        logger( 'ATTR_ERRMODE fail.' );
    }
    if ( !$db->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_BOTH ) ) {
        logger( 'ATTR_DEFAULT_FETCH_MODE fail.' );
    }
    return $db;
}

function db_close( $db ) {}

function db_last_error( $db ) { return $db ? ( $db->errorInfo()[0] . ': ' . $db->errorInfo()[2] ) : 'No DB given'; }

function db_last_insert_id( $db ) { return $db->lastInsertId(); }

function db_fetch( $query ) { return $query->fetch(); }

require_once( 'db.php' );
