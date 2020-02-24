<?php
// Вызов TelegramBot API
function call_api_method( $method, $params ) {
    global $token;
    $postdata = http_build_query( $params );
    $opts     = array(
        'http' => array(
            'ignore_errors' => 1,
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\n" .
                               'Content-Length: ' . strlen( $postdata ) . "\r\n",
            'content'       => $postdata
        ),
        'ssl' => array(
            'allow_self_signed' => true,
            'verify_peer'       => false,
            'verify_peer_name'  => false
        )
    );
    return file_get_contents( 'https://api.telegram.org/bot' . $token . '/' . $method, false, stream_context_create( $opts ) );
}
// Ответ методом на запрос телеграма
function answer_by_method( $method, $params ) {
    $params['method'] = $method;
    header( 'Content-Type: application/json' );
    echo json_encode( $params );
}
// Пустой ответ на запрос телеграма
function answer_ok() {
    header( 'Content-Type: text/plain' );
    echo 'OK';
}
