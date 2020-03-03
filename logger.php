<?php

function logger( $text ) {
    global $logger_on;
    if ( !$logger_on ) { return; }
    // Получаем строку и файл, из которого была вызвана эта функция
    $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
    $file  = basename( $trace[0]['file'], '.php' );
    $line  = $trace[0]['line'];
    // Имя файла лога = текущий год и месяц
    $day   = date( 'Y-m' );
    $fn    = "logs/$day.log";
    // Запись
    $now   = date( 'd H:i:s' );
    file_put_contents( $fn,  "$now: $file $line: " . $text . "\r\n", FILE_APPEND );
}
