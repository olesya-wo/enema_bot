<?php
$current_lang = 'en';
$en_strings = null;
$tr_strings = null;

function get_help() {
    global $current_lang;
    $help_file = 'help_';
    $help      = null;
    $filename  = $help_file . $current_lang . '.txt';
    if ( file_exists( $filename ) ) {
        $help = file_get_contents( $filename );
    }
    else {
        $help = file_get_contents( $help_file . 'en.txt' );
    }
    return $help ? $help : tr( 'HELPNOTFOUND' );
}

/*
Инициализация словаря переводов
Принимает код языка (en/ru/...) и загружает его словарь, а так же словарь английского
*/
function load_translation( $lang_code ) {
    global $en_strings;
    $en_strings = json_decode( file_get_contents( 'tr_en.json' ) );
    global $current_lang;
    global $tr_strings;
    if ( file_exists( 'tr_' . $lang_code . '.json' ) ) {
        $current_lang = $lang_code;
        $tr_strings   = json_decode( file_get_contents( 'tr_' . $current_lang . '.json' ) );
    }
}

/*
Отдаёт человекочитаемый текст на нужном языке по его коду
Пытается найти в словаре переводов указанного языка, потом в английском, потом отдаёт сам код, если ничего не найдено
*/
function tr( $ID ) {
    global $current_lang;
    global $tr_strings;
    global $en_strings;
    if ( $tr_strings && property_exists( $tr_strings, $ID ) ) { return $tr_strings->{ $ID }; }
    if ( $en_strings && property_exists( $en_strings, $ID ) ) { return $en_strings->{ $ID }; }
    return $ID;
}
