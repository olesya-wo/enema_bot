<?php
require_once( 'settings.php' );
require_once( 'db_sqlite.php' );
require_once( 'localization.php' );
require_once( 'tg_bot_api.php' );

// Синтаксическое упрощение для получения ссылки на БД
function get_db() {
    global $bot_name;
    return db_init( $bot_name . '_db.sqlite3' );
}
// Публикация опроса (не endpoint, т.е. не вызывает answer_by_method)
function publish_poll( $db, $chat_id, $from_id, $poll_id ) {
    $chat_id   = intval( $chat_id );
    $from_id   = intval( $from_id );
    $poll_id   = intval( $poll_id );
    $poll      = db_get_poll( $db, $poll_id );
    if ( $poll == -1 ) {
        call_api_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll == 0 ) {
        call_api_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLNOTFOUND' ) ) );
        return;
    }
    // Публиковать может только автор
    if ( $poll['author_id'] != $from_id ) {
        call_api_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTAUTHOR' ) ) );
        return;
    }
    // Публиковать можно только не удалённый опрос
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        call_api_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLISDELETED' ) ) );
        return;
    }
    $keyboard  = build_keyboard( $db, $poll );
    $poll_text = $poll['text'];
    $type      = $poll['type'];
    $file_id   = $poll['file'];
    if ( $type == 'photo' ) {
        call_api_method( 'sendPhoto', array(
                                            'chat_id'      => $chat_id,
                                            'caption'      => $poll_text,
                                            'photo'        => $file_id,
                                            'reply_markup' => json_encode( $keyboard ),
                                            'parse_mode'   => 'HTML'
                                        )
        );
    }
    else if ( $type == 'document' ) {
        call_api_method( 'sendDocument', array(
                                            'chat_id'      => $chat_id,
                                            'caption'      => $poll_text,
                                            'document'     => $file_id,
                                            'reply_markup' => json_encode( $keyboard ),
                                            'parse_mode'   => 'HTML'
                                        )
        );
    }
    else if ( $type == 'audio' ) {
        call_api_method( 'sendAudio', array(
                                            'chat_id'      => $chat_id,
                                            'caption'      => $poll_text,
                                            'audio'        => $file_id,
                                            'reply_markup' => json_encode( $keyboard ),
                                            'parse_mode'   => 'HTML'
                                        )
        );
    }
    else if ( $type == 'voice' ) {
        call_api_method( 'sendVoice', array(
                                            'chat_id'      => $chat_id,
                                            'caption'      => $poll_text,
                                            'voice'        => $file_id,
                                            'reply_markup' => json_encode( $keyboard ),
                                            'parse_mode'   => 'HTML'
                                        )
        );
    }
    else if ( $type == 'sticker' ) {
        call_api_method( 'sendSticker', array(
                                            'chat_id'      => $chat_id,
                                            'sticker'      => $file_id,
                                            'reply_markup' => json_encode( $keyboard )
                                        )
        );
    }
    else if ( $type == 'video_note' ) {
        call_api_method( 'sendVideoNote', array(
                                            'chat_id'      => $chat_id,
                                            'video_note'   => $file_id,
                                            'reply_markup' => json_encode( $keyboard )
                                        )
        );
    }
    else if ( $type == 'venue' ) {
        $args = json_decode( $file_id );
        call_api_method( 'sendVenue', array(
                                            'chat_id'      => $chat_id,
                                            'latitude'     => $args->{'location'}->{'latitude'},
                                            'longitude'    => $args->{'location'}->{'latitude'},
                                            'title'        => $poll_text,
                                            'address'      => $args->{'address'},
                                            'reply_markup' => json_encode( $keyboard )
                                        )
        );
    }
    else if ( $type == 'location' ) {
        $args = json_decode( $file_id );
        call_api_method( 'sendLocation', array(
                                            'chat_id'      => $chat_id,
                                            'latitude'     => $args->{'latitude'},
                                            'longitude'    => $args->{'longitude'},
                                            'reply_markup' => json_encode( $keyboard )
                                        )
        );
    }
    else if ( $type == 'contact' ) {
        $args = json_decode( $file_id );
        call_api_method( 'sendContact', array(
                                            'chat_id'      => $chat_id,
                                            'phone_number' => $args->{'phone_number'},
                                            'first_name'   => $args->{'first_name'},
                                            'reply_markup' => json_encode( $keyboard )
                                        )
        );
    }
    else {
        call_api_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $poll_text, 'reply_markup' => json_encode( $keyboard ), 'parse_mode' => 'HTML' ) );
    }
}
// Вывод inline-списка опросов
function get_list_inline( $query_id, $author ) {
    $db       = get_db();
    $query_id = intval( $query_id );
    $author   = intval( $author );
    $res      = db_get_poll_list( $db, $author );
    if ( $res == -1 ) {
        answer_by_method( 'answerInlineQuery',
                          array(
                            'inline_query_id'     => $query_id,
                            'results'             => '[]',
                            'cache_time'          => '10',
                            'is_personal'         => true,
                            'switch_pm_text'      => $db->lastErrorMsg(),
                            'switch_pm_parameter' => 'ID'
                          )
                        );
        $db->close();
        return;
    }
    if ( !$res ) {
        answer_by_method( 'answerInlineQuery',
                          array(
                            'inline_query_id'     => $query_id,
                            'results'             => '[]',
                            'cache_time'          => '10',
                            'is_personal'         => true,
                            'switch_pm_text'      => tr( 'EMPTYLIST' ),
                            'switch_pm_parameter' => 'ID'
                          )
                        );
        $db->close();
        return;
    }
    $cnt  = 0;
    $list = array();
    while ( $row = $res[$cnt] ) {
        // Список только из активных опросов
        if ( $row['state'] != 'active' ) {
            $cnt += 1;
            continue;
        }
        $keyboard = build_keyboard( $db, $row );
        if ( $row['type'] == 'photo' ) {
            array_push( $list, array(
                                    'type'          => 'photo',
                                    'id'            => strval( $cnt ),
                                    'title'         => $row['name'],
                                    'photo_file_id' => $row['file'],
                                    'caption'       => $row['text'],
                                    'parse_mode'    => 'HTML',
                                    'reply_markup'  => $keyboard,
                                    'description'   => $row['text']
                                )
            );
        }
        else if ( $row['type'] == 'document' ) {
            array_push( $list, array(
                                    'type'             => 'document',
                                    'id'               => strval( $cnt ),
                                    'title'            => $row['name'],
                                    'document_file_id' => $row['file'],
                                    'caption'          => $row['text'],
                                    'parse_mode'       => 'HTML',
                                    'reply_markup'     => $keyboard,
                                    'description'      => $row['text']
                                )
            );
        }
        else if ( $row['type'] == 'audio' ) {
            array_push( $list, array(
                                    'type'          => 'audio',
                                    'id'            => strval( $cnt ),
                                    'audio_file_id' => $row['file'],
                                    'caption'       => $row['text'],
                                    'parse_mode'    => 'HTML',
                                    'reply_markup'  => $keyboard
                                )
            );
        }
        else if ( $row['type'] == 'voice' ) {
            array_push( $list, array(
                                    'type'          => 'voice',
                                    'id'            => strval( $cnt ),
                                    'title'         => $row['name'],
                                    'voice_file_id' => $row['file'],
                                    'caption'       => $row['text'],
                                    'parse_mode'    => 'HTML',
                                    'reply_markup'  => $keyboard,
                                    'description'   => $row['text']
                                )
            );
        }
        else if ( $row['type'] == 'sticker' ) {
            array_push( $list, array(
                                    'type'            => 'sticker',
                                    'id'              => strval( $cnt ),
                                    'sticker_file_id' => $row['file'],
                                    'reply_markup'    => $keyboard
                                )
            );
        }
        else if ( $row['type'] == 'location' ) {
            $args = json_decode( $row['file'] );
            array_push( $list, array(
                                    'type'         => 'location',
                                    'id'           => strval( $cnt ),
                                    'title'        => $row['name'],
                                    'latitude'     => $args->{'latitude'},
                                    'longitude'    => $args->{'longitude'},
                                    'reply_markup' => $keyboard
                                )
            );
        }
        else if ( $row['type'] == 'venue' ) {
            $args = json_decode( $row['file'] );
            array_push( $list, array(
                                    'type'         => 'venue',
                                    'id'           => strval( $cnt ),
                                    'latitude'     => $args->{'location'}->{'latitude'},
                                    'longitude'    => $args->{'location'}->{'latitude'},
                                    'title'        => $row['text'],
                                    'address'      => $args->{'address'},
                                    'reply_markup' => $keyboard
                                )
            );
        }
        else if ( $row['type'] == 'contact' ) {
            $args = json_decode( $row['file'] );
            array_push( $list, array(
                                    'type'         => 'contact',
                                    'id'           => strval( $cnt ),
                                    'phone_number' => $args->{'phone_number'},
                                    'first_name'   => $args->{'first_name'},
                                    'reply_markup' => $keyboard
                                )
            );
        }
        else if ( $row['type'] == 'video_note' ) {
            //array_push( $list, array( 'type' => 'video', 'id' => strval( $cnt ), 'video_file_id' => $row['file'], 'reply_markup' => $keyboard ) );
        }
        else {
            array_push( $list, array(
                                    'type'                  => 'article',
                                    'id'                    => strval( $cnt ),
                                    'title'                 => $row['name'],
                                    'input_message_content' => array(
                                        'message_text' => $row['text'],
                                        'parse_mode'   => 'HTML'
                                    ),
                                    'reply_markup'          => $keyboard,
                                    'description'           => $row['text']
                                )
            );
        }
        $cnt += 1;
    }
    $db->close();
    answer_by_method( 'answerInlineQuery', array(
                                            'inline_query_id' => $query_id,
                                            'results'         => json_encode( $list ),
                                            'cache_time'      => '10',
                                            'is_personal'     => true
                                        )
    );
}
// Отдаёт указанный список юзеров, получив по ним информацию
function get_users( $chat_id, $from_id, $file ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Только админ
    global $admin_id;
    if ( $admin_id != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'ONLYADMIN' ) ) );
        return;
    }
    $users = array_filter( explode( "\n", file_get_contents( $file . '.txt' ) ) );
    $res   = $file . ":\n";
    foreach( $users as $user ) {
        $u        = call_api_method( 'getChat', array( 'chat_id' => $user ) );
        $udata    = json_decode( $u );
        $username = '';
        if ( $udata->{'ok'} == true ) {
            $username = $udata->{'result'}->{'first_name'} . ' ';
            if ( property_exists( $udata->{'result'}, 'last_name' ) ) {
                $username = $username . $udata->{'result'}->{'last_name'};
            }
            if ( mb_strlen( $username ) < 2 ) { $username = $user; }
        }
        else {
            $username = $user;
        }
        $res = $res . "<a href=\"tg://user?id=" . $user . "\">" . $username . "</a>\n";
    }
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text'=> $res, 'parse_mode' => 'HTML' ) );
}
// Внесение/обновление голоса
function vote( $db, $user_id, $poll_id, $item ) {
    $user_id = intval( $user_id );
    $poll_id = intval( $poll_id );
    $item    = intval( $item );
    // Ищем опрос
    $poll    = db_get_poll( $db, $poll_id );
    if ( $poll == -1 ) {
        return $db->lastErrorMsg();
    }
    if ( !$poll ) {
        return tr( 'POLLNOTFOUND' );
    }
    // Проверяем его state
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        return '✖️ ' . tr( 'POLLISDELETED' );
    }
    if ( $poll['state'] == 'locked' ) {
        return '🔐 ' . tr( 'POLLLOCKED' );
    }
    // Варианты опроса
    $items = explode( "\n", $poll['items'] );
    // Количество вариантов в опросе
    $items_count = count( $items );
    // На всякий случай проверить диапазон
    if ( $item < 1 or $item > $items_count ) {
        return tr( 'INVALIDBTNDATA' );
    }
    // За какой пункт у этого юзера есть голос
    $current = db_get_vote( $db, $poll_id, $user_id );
    if ( $current == -1 ) {
        return $db->lastErrorMsg();
    }
    // Голос уже учтён
    if ( $current == $item ) {
        return '☑️ ' . tr( 'COUNTED' );
    }
    // Очистить старые голоса
    if ( !db_delete_vote( $db, $poll_id, $user_id ) ) {
        return $db->lastErrorMsg();
    }
    // Внести новый
    if ( !db_add_vote( $db, $poll_id, $item, $user_id ) ) {
        return $db->lastErrorMsg();
    }
    if ( $current == 0 ) {
        // Голос внесён
        return '✅ ' . tr( 'VOTED' );
    }
    else {
        // Голос изменён
        return '🔄 ' . tr( 'VOTECHANGED' );
    }
}
// Получает из параметров команды id опроса
function get_id_argument( $db, $chat_id, $arguments ) {
    $id = $arguments;
    if ( mb_strtolower( $id ) == 'last' ) {
        $id = db_get_last( $db, $chat_id );
        // Ошибка БД
        if ( $id == -1 ) {
            answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
            return 0;
        }
        // У автора нет опросов
        if ( $id == 0 ) {
            answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'LASTIDERROR' ) ) );
            return 0;
        }
    }
    // Передали и не last и не число, а бред
    if ( !is_numeric( $id ) ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INVALIDID' ) ) );
        return 0;
    }
    return $id;
}
// Блокирует/разблокирует опрос для голосования
function set_poll_lock( $db, $chat_id, $from_id, $arguments, $state ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    $id = get_id_argument( $db, $from_id, $arguments );
    if ( $id == 0 ) { return; }
    // id знаем, берём данные из БД
    $poll = db_get_poll( $db, $id );
    // Ошибка БД
    if ( $poll == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Не найдено такого
    if ( !$poll ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLNOTFOUND' ) ) );
        return;
    }
    // Проверяем права
    if ( $poll['author_id'] != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTAUTHOR' ) ) );
        return;
    }
    // Изменять можно только не удалённый опрос
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLISDELETED' ) ) );
        return;
    }
    // Опрос уже в этом состоянии
    if ( $poll['state'] == $state ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INSTATE' ) ) );
        return;
    }
    // Блокируем/разблокируем
    $res = db_set_poll_state( $db, $id, $state );
    // Ошибка БД
    if ( !$res ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Отвечаем, что заблокировали/разблокировали
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'STATECHANGED' ) ) );
}
// Делает опрос публичным или скрытым
function set_poll_public( $db, $chat_id, $from_id, $arguments, $state ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    $id = get_id_argument( $db, $from_id, $arguments );
    if ( $id == 0 ) { return; }
    // id знаем, берём данные из БД
    $poll = db_get_poll( $db, $id );
    // Ошибка БД
    if ( $poll == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Не найдено такого
    if ( !$poll ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLNOTFOUND' ) ) );
        return;
    }
    // Проверяем права
    if ( $poll['author_id'] != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTAUTHOR' ) ) );
        return;
    }
    // Изменять можно только не удалённый опрос
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLISDELETED' ) ) );
        return;
    }
    // Опрос уже в этом состоянии
    if ( $poll['public'] == $state ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INSTATE' ) ) );
        return;
    }
    // Блокируем/разблокируем
    $res = db_set_poll_public( $db, $id, $state );
    // Ошибка БД
    if ( !$res ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Отвечаем, что заблокировали/разблокировали
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'STATECHANGED' ) ) );
}
// Оптимальное количество кнопок в строке клавиатуры
function get_optimal_cols( $cnt, $max_len ) {
    if ( $max_len > 12 ) {
        return 1;
    }
    $cols = intval( round( 25 / $max_len ) );
    if ( $cols > 8 ) {
        $cols = 8;
    }
    if ( $cnt <= $cols ) {
        return $cols;
    }
    $max_k = 0.0;
    $max_n = 0;
    // cols - [2,8]
    for ( $i = 2; $i <= $cols; $i++ ) {
        $k = $cnt % $i;
        if ( $k == 0 ) {
            $max_k = 1.0;
            $max_n = $i;
        }
        else {
            $k = ( float ) $k / ( float ) $i;
            if ( $k > $max_k ) {
                $max_k = $k;
                $max_n = $i;
            }
        }
    }
    return $max_n;
}
// Сборка json-данных клавиатуры по переданному объекту опроса
function build_keyboard( $db, $poll ) {
    if ( !$poll['items'] ) { return array( 'inline_keyboard' => array() ); }
    // Пункты опроса
    $poll_items = explode( "\n", $poll['items'] );
    // Сколько пунктов в опросе
    $btn_count  = count( $poll_items );
    // Вспомогательный массив, хранящий количество голосов
    $score      = array();
    if ( $poll['public'] ) {
        for ( $i = 0; $i < $btn_count; $i++ ) {
            $t = db_get_poll_item_votes( $db, $poll['poll_id'], $i + 1 );
            if ( $t == -1 ) {
                array_push( $score, '[?]' );
            }
            else {
                array_push( $score, $t > 0 ? ' [' . $t . ']' : '' );
            }
        }
    }
    // Максимальная длина среди вариантов ответа
    $applen  = $poll['public'] ? 4: 0;
    $max_len = 0;
    for ( $i = 0; $i < $btn_count; $i++ ) {
        $len = mb_strlen( $poll_items[$i] ) + $applen;
        if ( $len > $max_len ) { $max_len = $len; }
    }
    // Непосредственно сборка массивов
    $cnt             = 0;
    $optimal_cnt     = get_optimal_cols( $btn_count, $max_len );
    $inline_keyboard = array();
    $keyboard_row    = array();
    foreach( $poll_items as $item ) {
        $score_cnt = $poll['public'] ? $score[$cnt] : '';
        $cnt += 1;
        $btn  = array( 'text' => $item . $score_cnt, 'callback_data' => $poll['poll_id'] . ':' . $cnt );
        array_push( $keyboard_row, $btn );
        if ( $cnt % $optimal_cnt == 0 ) {
            array_push( $inline_keyboard, $keyboard_row );
            $keyboard_row = array();
        }
    }
    if ( $cnt % $optimal_cnt != 0 ) { array_push( $inline_keyboard, $keyboard_row ); }
    return array( 'inline_keyboard' => $inline_keyboard );
}
// Обновляет записть в таблице stack актуальным аттачем
function update_stack( $db, $user_id, $data ) {
    $user_id = intval( $user_id );
    $type    = 'none';
    $file    = '';
    if ( property_exists( $data->{'message'}, 'photo' ) ) {
        $type = 'photo';
        $file = $data->{'message'}->{'photo'}[count( $data->{'message'}->{'photo'} ) - 1]->{'file_id'};
    }
    else if ( property_exists( $data->{'message'}, 'document' ) ) {
        $type = 'document';
        $file = $data->{'message'}->{'document'}->{'file_id'};
    }
    else if ( property_exists( $data->{'message'}, 'audio' ) ) {
        $type = 'audio';
        $file = $data->{'message'}->{'audio'}->{'file_id'};
    }
    else if ( property_exists( $data->{'message'}, 'voice' ) ) {
        $type = 'voice';
        $file = $data->{'message'}->{'voice'}->{'file_id'};
    }
    else if ( property_exists( $data->{'message'}, 'sticker' ) ) {
        $type = 'sticker';
        $file = $data->{'message'}->{'sticker'}->{'file_id'};
    }
    else if ( property_exists( $data->{'message'}, 'video_note' ) ) {
        $type = 'video_note';
        $file = $data->{'message'}->{'video_note'}->{'file_id'};
    }
    else if ( property_exists( $data->{'message'}, 'venue' ) ) {
        $type = 'venue';
        $file = json_encode( $data->{'message'}->{'venue'} );
    }
    else if ( property_exists( $data->{'message'}, 'location' ) ) {
        $type = 'location';
        $file = json_encode( $data->{'message'}->{'location'} );
    }
    else if ( property_exists( $data->{'message'}, 'contact' ) ) {
        $type = 'contact';
        $file = json_encode( $data->{'message'}->{'contact'} );
    }
    if ( $type != 'none' ) {
        if ( db_update_media_stack( $db, $user_id, $type, $file ) ) {
            return $type;
        }
        else {
            return -1;
        }
    }
    return 0;
}

// Подключаем обработчики команд
require_once( 'handlers.php' );

// Для корректного времени по Москве
date_default_timezone_set( 'Europe/Moscow' );

// Парсер
if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
    $raw_inp = file_get_contents( 'php://input' );
    $data    = json_decode( $raw_inp );
    // Текстовая команда
    if ( property_exists( $data, 'message' ) and isset( $data->{'message'} ) ) {
        $chat_id = $data->{'message'}->{'chat'}->{'id'};
        $from_id = $data->{'message'}->{'from'}->{'id'};
        load_translation( $data->{'message'}->{'from'}->{'language_code'} );
        $txt     = property_exists( $data->{'message'}, 'text' )    ? $data->{'message'}->{'text'}    : null;
        $caption = property_exists( $data->{'message'}, 'caption' ) ? $data->{'message'}->{'caption'} : null;
        if ( $txt == null and $caption != null ) { $txt = $caption; }
        $txt = trim( $txt );
        // Альбомы не поддерживаются
        if ( property_exists( $data->{'message'}, 'media_group_id' ) and $txt != null ) {
            answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'GROUPNOTSUPPORT' ) ) );
        }
        else {
            $db = get_db();
            // Ищем по regex команду
            $m_res = preg_match( "/^(?:\/([a-z]+)$)|(?:\/([a-z]+)\s+)|(?:\/([a-z]+)@$bot_name)/i", $txt, $matches );
            if ( $m_res ) {
                // help, start, publish, get
                // activity, new, edit, attach, delete, restore, lock, unlock, show, hide, list, feedback
                // stat, users, authors
                $c = $matches[1] ? $matches[1] : ( $matches[2] ? $matches[2] : $matches[3] );
                $d = 'on_' . mb_strtolower( $c ) . '_cb';
                if ( function_exists( $d ) ) {
                    // Отрезаем текст после команды, если он есть
                    $arguments = trim( mb_substr( $txt, mb_strlen( $c ) + 1 ) );
                    // и передаём его как аргумент в обработчик вместе с остальными параметрами
                    call_user_func( $d, $db, $chat_id, $from_id, $arguments, $data );
                }
                else {
                    // Совсем молчать нехорошо, но в общих чатах бот должен отвечать только на сообщения персонально ему
                    // Если это личка, то ответим, что команда не найдена
                    if ( $chat_id == $from_id ) {
                        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'UNKNOWNCOMMAND' ) ) );
                    }
                }
            }
            else {
                if ( $chat_id == $from_id ) {
                    // Если это личка, то проверим и запомним, если это медиаданные
                    $media = update_stack( $db, $chat_id, $data );
                    if ( $media == -1 ) {
                        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
                    }
                    elseif ( $media === 0 ) {
                        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'UNKNOWNCOMMAND' ) ) );
                    }
                    else {
                        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'MEDIASAVED' ) . $media ) );
                    }
                }
                else {
                    // Не в личке ничего не отвечаем
                    answer_ok();
                }
            }
            $db->close();
        }
    }
    // Обработчик кнопок голосования
    else if ( property_exists( $data, 'callback_query' ) and isset( $data->{'callback_query'} ) ) {
        $query_id = $data->{'callback_query'}->{'id'};
        $user_id  = $data->{'callback_query'}->{'from'}->{'id'};
        $btn_data = $data->{'callback_query'}->{'data'};
        load_translation( $data->{'callback_query'}->{'from'}->{'language_code'} );
        $inline   = false;
        $chat_id  = 0;
        if ( property_exists( $data->{'callback_query'}, 'inline_message_id' ) ) {
            $message_id = $data->{'callback_query'}->{'inline_message_id'};
            $inline     = true;
        }
        else {
            $chat_id    = $data->{'callback_query'}->{'message'}->{'chat'}->{'id'};
            $message_id = $data->{'callback_query'}->{'message'}->{'message_id'};
        }
        // Внести/обновить голос
        $db          = get_db();
        $lst         = explode( ':', $btn_data, $limit = 2 );
        $poll_id     = intval( $lst[0] );
        $vote_answer = count( $lst ) == 2 ? vote( $db, $user_id, $poll_id, $lst[1] ) : tr( 'INVALIDBTNDATA' );
        answer_by_method( 'answerCallbackQuery', array( 'callback_query_id' => $query_id, 'text' => $vote_answer ) );
        // После голоса надо обновить клавиатуру
        $poll = db_get_poll( $db, $poll_id );
        if ( $poll != -1 and $poll != 0 ) {
            $keyboard = build_keyboard( $db, $poll );
            if ( $inline ) {
                call_api_method( 'editMessageReplyMarkup', array( 'inline_message_id' => $message_id, 'reply_markup' => json_encode( $keyboard ) ) );
            }
            else {
                call_api_method( 'editMessageReplyMarkup', array( 'chat_id' => $chat_id, 'message_id' => $message_id, 'reply_markup' => json_encode( $keyboard ) ) );
            }
        }
        $db->close();
    }
    // Публикация в канал
    // Пока отключена, так как невозможно проверить права - нет поля from
    // else if ( property_exists( $data, 'channel_post' ) and isset( $data->{'channel_post'} ) ) {
    //     $chat_id = $data->{'channel_post'}->{'chat'}->{'id'};
    //     $txt     = $data->{'channel_post'}->{'text'};
    //     if ( mb_substr( mb_strtolower( $txt ), 0, mb_strlen( '/publish' ) ) == '/publish' ) {
    //         $db = get_db();
    //         $id = get_id_argument( $db, $chat_id, trim( mb_substr( $txt, mb_strlen( '/publish' ) ) ) );
    //         if ( $id ) {
    //             publish_poll( $db, null, $chat_id, $id );
    //             answer_ok();
    //         }
    //         $db->close();
    //     }
    // }
    // Inline-список опросов
    else if ( property_exists( $data, 'inline_query' ) and isset( $data->{'inline_query'} ) ) {
        $query_id = $data->{'inline_query'}->{'id'};
        $user_id  = $data->{'inline_query'}->{'from'}->{'id'};
        load_translation( $data->{'inline_query'}->{'from'}->{'language_code'} );
        get_list_inline( $query_id, $user_id );
    }
}
else {
    echo( "<h1>Enema bot</h1>Author: @lapka_td<br><br>" );
    echo date( 'Y-m-d H:i:s' );
}
