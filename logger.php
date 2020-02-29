<?php

/*
Общая функция, не предназначенная для непосредственного вызова в других модулях
*/
function logger( $lvl, $text ) {
    // Получаем строку и файл, из которого была вызвана эта функция
    $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
    $file  = basename( $trace[1]['file'], '.php' );
    $line  = $trace[1]['line'];
    // Имя файла лога = текущий год и месяц
    $day   = date( 'Y-m' );
    $fn    = "logs/$day.log";
    // Запись
    $now   = date( 'd H:i:s' );
    file_put_contents( $fn,  "$now: $file $line: $lvl: " . $text . "\r\n", FILE_APPEND );
}

/*
Запись в лог ошибки
*/
function log_error( $text ) {
    global $log_error_on;
    if ( $log_error_on ) { logger( 'Error', $text ); }
}

/*
Запись в лог информационного сообщения
*/
function log_info( $text ) {
    global $log_info_on;
    if ( $log_info_on ) { logger( 'Info', $text ); }
}
