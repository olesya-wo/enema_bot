<?php

define( 'DB_TYPE_STR', PDO::PARAM_STR );
define( 'DB_TYPE_INT', PDO::PARAM_INT );

$increment = 'AUTO_INCREMENT';
$suff      = 'ENGINE=InnoDB CHARACTER SET=utf8mb4';

function db_init() {
    global $mysql_host, $mysql_db, $mysql_user, $mysql_pass;
    $dsn  = "mysql:host=$mysql_host;dbname=$mysql_db";
    $opt  = [ PDO::ATTR_ERRMODE            => PDO::ERRMODE_SILENT, // Только установка кодов ошибок
              PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_BOTH,     // Выборка в ассоциативный и нумерованный массив
              PDO::ATTR_PERSISTENT         => true                 // Не закрывать соединение после каждого запроса
            ];
    $db = null;
    try {
        $db = new PDO( $dsn, $mysql_user, $mysql_pass );
    }
    catch ( PDOException $e ) {
        return null;
    }
    return $db;
}

function db_last_error( $db ) { return $db ? ( $db->errorInfo()[0] . ': ' . $db->errorInfo()[2] ) : 'No DB given'; }

function db_last_insert_id( $db ) { return $db->lastInsertId(); }

function db_fetch( $query ) { return $query->fetch(); }

require_once( 'db.php' );
