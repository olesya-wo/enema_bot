<?php
// Обработчики команд
// Все обработчики ничего не возвращают, должны ответить с помощью answer_by_method
function on_help_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    answer_by_method( 'sendMessage',
                      array(
                        'chat_id'                  => $chat_id,
                        'text'                     => get_help(),
                        'disable_web_page_preview' => true,
                        'parse_mode'               => 'HTML'
                      )
                    );
}
function on_start_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    if ( $chat_id == $from_id ) {
        // В личке просто запоминаем юзера
        $users = file_get_contents( 'users.txt' );
        if ( mb_strpos( $users, $from_id . "\n" ) == false ) { file_put_contents( 'users.txt', $users . $from_id . "\n" ); }
        // и отдаём ему справку
        on_help_cb( $db, $chat_id, $from_id, $arguments, $data );
    }
    else {
        global $bot_name;
        $bn = '@' . $bot_name;
        // В группах надо смотреть, что start относится к этому боту
        if ( mb_substr( mb_strtolower( $arguments ), 0, mb_strlen( $bn . ' id' ) ) == $bn . ' id' ) {
            // И если после start передан id, значит это публикация
            $id = mb_substr( $arguments, mb_strlen( $bn . ' id' ) );
            publish_poll( $db, $chat_id, $from_id, $id );
            answer_ok();
        }
        // Если нет id, значит просто справка
        else if ( mb_substr( mb_strtolower( $arguments ), 0, mb_strlen( $bn ) ) == $bn ) {
            on_help_cb( $db, $chat_id, $from_id, $arguments, $data );
        }
    }
}
function on_publish_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    $id = get_id_argument( $db, $from_id, $arguments );
    if ( $id ) {
        publish_poll( $db, $chat_id, $from_id, $id );
        answer_ok();
    }
}
function on_get_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Получаем id
    $poll_id = get_id_argument( $db, $from_id, $arguments );
    if ( $poll_id == 0 ) { return; }
    // Ищем опрос по id
    $poll = db_get_poll( $db, $poll_id );
    if ( $poll == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll == 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLNOTFOUND' ) ) );
        return;
    }
    // Проверяем права
    if ( $poll['author_id'] != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTAUTHOR' ) ) );
        return;
    }
    // Есть ли голоса вообще
    $voted = db_get_poll_votes( $db, $poll_id, 0 );
    if ( $voted == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $voted == 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOVOTES' ) ) );
        return;
    }
    // Получаем данные по вариантам
    $items = explode( "\n", $poll['items'] );
    $arr   = array();
    $res   = $poll['name'] . ":\n";
    for ( $i = 0; $i < count( $items ); $i += 1 ) {
        // Сколько голосов за i вариант
        $cnt = db_get_poll_item_votes( $db, $poll_id, $i + 1 );
        if ( $cnt == -1 ) {
            answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
            return;
        }
        $arr[$items[$i]] = $cnt;
    }
    // Сортируем по убыванию
    arsort( $arr, $sort_flags = SORT_NUMERIC );
    // Выводим в из массива в текст
    foreach( $arr as $k => $v ) {
        $res = $res . $k . ' - ' . $v . "\n";
    }
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $res ) );
}
function on_activity_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Позиции аргументов
    $time_pos   = mb_strpos( mb_strtolower( $arguments ), '/time'  );
    // Аргументы
    $poll_id    = '';
    $poll_time  = 0;
    if ( $time_pos ) {
        // Всё, что до /time, это id опроса
        $poll_id   = trim( mb_substr( $arguments, 0, $time_pos ) );
        // После /time - время
        $poll_time = intval( trim( mb_substr( $arguments, $time_pos + 5 ) ) );
    }
    if ( mb_strlen( $poll_id ) < 1 or $poll_time < 1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INVALIDFORMAT' ) ) );
        return;
    }
    $poll_id = get_id_argument( $db, $from_id, $poll_id );
    if ( !$poll_id ) { return; }
    $poll = db_get_poll( $db, $poll_id );
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
    $res = db_get_poll_votes( $db, $poll_id, $poll_time );
    if ( $res == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    answer_by_method( 'sendMessage', array(
                                        'chat_id' => $chat_id,
                                        'text'    => sprintf( tr( 'ACTIVITY' ), $poll_time, $res )
                                    )
    );
}
function on_new_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Позиции аргументов
    $txtpos     = mb_strpos( mb_strtolower( $arguments ), '/text'  );
    $itemspos   = mb_strpos( mb_strtolower( $arguments ), '/items' );
    // Аргументы
    $poll_name  = '';
    $poll_text  = '';
    $poll_items = '';
    // Парсинг аргументов
    if ( $txtpos and $itemspos and $itemspos > $txtpos ) {
        // Всё, что до /text, это имя опроса
        $poll_name  = trim( mb_substr( $arguments, 0, $txtpos ) );
        // После /text и до /items - текст опроса
        $poll_text  = trim( mb_substr( $arguments, $txtpos + 5, $itemspos - $txtpos - 5 ) );
        // Всё, что после /items - пункты опроса
        $poll_items = trim( mb_substr( $arguments, $itemspos + 6 ) );
    }
    // Не найдено имя или варианта для опроса
    if ( mb_strlen( $poll_name ) < 1 or mb_strlen( $poll_items ) < 1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NEWERROR' ) ) );
        return;
    }
    // Находим аттач, если он есть
    $doc_type = 'text';
    $file_id  = '';
    if ( property_exists( $data->{'message'}, 'photo' ) ) {
        $file_id  = $data->{'message'}->{'photo'}[count( $data->{'message'}->{'photo'} ) - 1]->{'file_id'};
        $doc_type = 'photo';
    }
    if ( property_exists( $data->{'message'}, 'document' ) ) {
        $file_id  = $data->{'message'}->{'document'}->{'file_id'};
        $doc_type = 'document';
    }
    if ( property_exists( $data->{'message'}, 'audio' ) ) {
        $file_id  = $data->{'message'}->{'audio'}->{'file_id'};
        $doc_type = 'audio';
    }
    // Нет ни текста, ни файлов
    if ( $doc_type == 'text' and mb_strlen( $poll_text ) < 1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'EMPTYPOLL' ) ) );
        return;
    }
    $poll_items = explode( "\n", $poll_items );
    // Преобразуем варианты, если они записаны через ;
    if ( count( $poll_items ) == 1 and mb_strpos( $poll_items[0], ';' ) ) {
        $bck = $poll_items;
        $poll_items = array_filter( explode( ';', $poll_items[0] ) );
        if ( count( $poll_items ) == 1 ) { $poll_items = $bck; }
    }
    // Не более 10 активных опросов
    $poll_count = db_get_poll_count( $db, $chat_id, 0 );
    if ( $poll_count == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll_count > 9 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'MAX10' ) ) );
        return;
    }
    // Не более 100 опросов всего
    $poll_count  = db_get_poll_count( $db, $chat_id, 1 );
    if ( $poll_count == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll_count > 99 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'MAX100' ) ) );
        return;
    }
    // Пробуем создать
    $id = db_add_poll( $db, $chat_id, $poll_name, $poll_items, $poll_text, $doc_type, $file_id );
    if ( $id == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Для статистики
    file_put_contents( 'last_poll_datetime.txt', 'Last poll: ' . date( 'Y-m-d' ) . "\nLast ID: " . $id );
    $authors = file_get_contents( 'authors.txt' );
    if ( mb_strpos( $authors, $from_id . "\n" ) === false ) { file_put_contents( 'authors.txt', $authors . $from_id . "\n" ); }
    // Опубликовать его тут же
    publish_poll( $db, $chat_id, $from_id, $id );
    // Вывести подсказку, что делать дальше
    global $bot_name;
    call_api_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => sprintf( tr( 'SHARE' ), $bot_name, $id, $id ), 'disable_web_page_preview' => true ) );
    // Клянчим на хлеб
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'DONATE' ), 'disable_web_page_preview' => true, 'parse_mode' => 'HTML' ) );
}
function on_edit_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Позиции аргументов
    $txtpos   = mb_strpos( mb_strtolower( $arguments ), '/text'  );
    $itemspos = mb_strpos( mb_strtolower( $arguments ), '/items' );
    // Аргументы
    $poll_id    = '';
    $poll_text  = '';
    $poll_items = null;
    if ( $txtpos and $itemspos and $itemspos > $txtpos ) {
        // Всё, что до /text, это id опроса
        $poll_id    = trim( mb_substr( $arguments, 0, $txtpos ) );
        // После /text до /items - текст опроса
        $poll_text  = trim( mb_substr( $arguments, $txtpos + 5, $itemspos - $txtpos - 5 ) );
        // После /items - варианты опроса
        $poll_items = trim( mb_substr( $arguments, $itemspos + 6 ) );
        $poll_items = explode( "\n", $poll_items );
        // Преобразуем варианты, если они записаны через ;
        if ( count( $poll_items ) == 1 and mb_strpos( $poll_items[0], ';' ) ) {
            $bck = $poll_items;
            $poll_items = array_filter( explode( ';', $poll_items[0] ) );
            if ( count( $poll_items ) == 1 ) { $poll_items = $bck; }
        }
    }
    else if ( $txtpos ) {
        $poll_id   = trim( mb_substr( $arguments, 0, $txtpos ) );
        $poll_text = trim( mb_substr( $arguments, $txtpos + 5 ) );
    }
    if ( mb_strlen( $poll_id ) < 1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INVALIDFORMAT' ) ) );
        return;
    }
    $doc_type = 'text';
    $file_id  = '';
    if ( property_exists( $data->{'message'}, 'photo' ) ) {
        $file_id  = $data->{'message'}->{'photo'}[count( $data->{'message'}->{'photo'} ) - 1]->{'file_id'};
        $doc_type = 'photo';
    }
    if ( property_exists( $data->{'message'}, 'document' ) ) {
        $file_id  = $data->{'message'}->{'document'}->{'file_id'};
        $doc_type = 'document';
    }
    if ( property_exists( $data->{'message'}, 'audio' ) ) {
        $file_id  = $data->{'message'}->{'audio'}->{'file_id'};
        $doc_type = 'audio';
    }
    if ( $doc_type == 'text' and mb_strlen( $poll_text ) < 1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'EMPTYPOLL' ) ) );
        return;
    }
    $poll_id = get_id_argument( $db, $from_id, $poll_id );
    if ( !$poll_id ) { return; }
    $poll = db_get_poll( $db, $poll_id );
    if ( $poll == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll == 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLNOTFOUND' ) ) );
        return;
    }
    if ( $poll['author_id'] != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTAUTHOR' ) ) );
        return;
    }
    // Редактировать можно только не удалённый опрос
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLISDELETED' ) ) );
        return;
    }
    $voted = db_get_poll_votes( $db, $poll_id, 0 );
    if ( $voted > 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INPROGRESSPOLL' ) ) );
        return;
    }
    // Если пункты не обновляются, то берём старые
    if ( count( $poll_items) < 1 ) {
        $poll_items = explode( "\n", $poll['items'] );
    }
    // Обновляем данные в БД
    $res = db_edit_poll( $db, $poll_id, $poll_items, $poll_text, $doc_type, $file_id );
    if ( $res == 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Опубликовать обновлённый опрос
    publish_poll( $db, $chat_id, $from_id, $poll_id );
    // Ответить, что изменено
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'CHANGED' ) ) );
}
function on_attach_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Получаем id
    $poll_id = get_id_argument( $db, $from_id, $arguments );
    if ( $poll_id == 0 ) { return; }
    // Получаем сохранённое медиа
    $media = db_get_media_stack( $db, $chat_id );
    if ( $media == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Ничего не найдено
    if ( !$media or $media['type'] == 'none' or mb_strlen( $media['file'] ) < 1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INVALIDMEDIA' ) ) );
        return;
    }
    // Найти сам опрос
    $poll = db_get_poll( $db, $poll_id );
    if ( $poll == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll == 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLNOTFOUND' ) ) );
        return;
    }
    // Проверить авторство
    if ( $poll['author_id'] != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTAUTHOR' ) ) );
        return;
    }
    // Редактировать можно только не удалённый опрос
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'POLLISDELETED' ) ) );
        return;
    }
    // Нельзя изменять уже идущий опрос
    $voted = db_get_poll_votes( $db, $poll_id, 0 );
    if ( $voted > 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'INPROGRESSPOLL' ) ) );
        return;
    }
    // Обновить поля у опроса
    $poll['items'] = explode( "\n", $poll["items"] );
    $res = db_edit_poll( $db, $poll_id, $poll['items'], $poll['text'], $media['type'], $media['file'] );
    if ( $res == 0 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Сбросить сохранённое медиа
    $res = db_update_media_stack( $db, $from_id, 'none', '' );
    if ( !$res ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Опубликовать обновлённый опрос
    publish_poll( $db, $chat_id, $from_id, $poll_id );
    // Ответить, что изменено
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'CHANGED' ) ) );
}
function on_delete_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Получаем аргумент
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
    // Опрос уже удалён
    if ( $poll['state'] == 'deleted' or $poll['state'] == 'clean' ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'ALREADYDELETED' ) ) );
        return;
    }
    // Удаляем
    $res = db_set_poll_state( $db, $id, 'deleted' );
    // Ошибка БД
    if ( !$res ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Отвечаем, что удалили
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'HASBEENDELETED' ) ) );
}
function on_restore_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Получаем аргумент
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
    // Опрос не удалён
    if ( $poll['state'] == 'active' or $poll['state'] == 'locked' ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'NOTDELETED' ) ) );
        return;
    }
    // Не более 10 активных опросов
    $poll_count = db_get_poll_count( $db, $chat_id, 0 );
    if ( $poll_count == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( $poll_count > 9 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'MAX10' ) ) );
        return;
    }
    // Восстанавливаем
    $res = db_set_poll_state( $db, $id, 'active' );
    // Ошибка БД
    if ( !$res ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    // Отвечаем, что восстановили
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'RESTORED' ) ) );
}
function on_lock_cb( $db, $chat_id, $from_id, $arguments, $data ) { set_poll_lock( $db, $chat_id, $from_id, $arguments, 'locked' ); }

function on_unlock_cb( $db, $chat_id, $from_id, $arguments, $data ) { set_poll_lock( $db, $chat_id, $from_id, $arguments, 'active' ); }

function on_show_cb( $db, $chat_id, $from_id, $arguments, $data ) { set_poll_public( $db, $chat_id, $from_id, $arguments, 1 ); }

function on_hide_cb( $db, $chat_id, $from_id, $arguments, $data ) { set_poll_public( $db, $chat_id, $from_id, $arguments, 0 ); }

function on_list_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    $chat_id = intval( $chat_id );
    $from_id = intval( $from_id );
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Получаем список из базы
    $res = db_get_poll_list( $db, $from_id );
    if ( $res == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    if ( !$res ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'EMPTYLIST' ) ) );
        return;
    }
    $list = '';
    $cnt = 0;
    while ( $row = $res[$cnt] ) {
        if ( $row['public'] == 1 ) {
            $list = $list . '📊';
        }
        if ( $row['state'] == 'locked' ) {
            $list = $list . '🔐 ';
        }
        if ( $row['state'] == 'deleted' ) {
            $list = $list . '❌ ';
        }
        if ( $row['state'] == 'clean' ) {
            $list = $list .'❌♻️ ';
        }
        $dt   = new DateTime( '@' . $row['created'] );
        $list = $list . $row['name'] . ":\n" . $dt->format( 'Y-m-d' ) . ' ID: ' . $row['poll_id'] . "\n\n";
        $cnt += 1;
    }
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $list ) );
}
function on_feedback_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    global $admin_id;
    if ( mb_strlen( $arguments ) > 0 ) {
        $file_id = '';
        if ( property_exists( $data->{'message'}, 'photo' ) ) {
            $file_id = $data->{'message'}->{'photo'}[count( $data->{'message'}->{'photo'} ) - 1]->{'file_id'};
            call_api_method( 'sendPhoto', array( 'chat_id' => $admin_id, 'caption' => "#feedback\n" . $arguments, 'photo' => $file_id, 'parse_mode' => 'HTML' ) );
        }
        else if ( property_exists( $data->{'message'}, 'document' ) ) {
            $file_id = $data->{'message'}->{'document'}->{'file_id'};
            call_api_method( 'sendDocument', array( 'chat_id' => $admin_id, 'caption' => "#feedback\n" . $arguments, 'document' => $file_id, 'parse_mode' => 'HTML' ) );
        }
        else {
            call_api_method( 'sendMessage', array( 'chat_id' => $admin_id, 'text' => "#feedback\n" . $arguments, 'parse_mode' => 'HTML' ) );
        }
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'FEEDBACKOK' ) ) );
    }
    else {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'FEEDBACKERROR' ) ) );
    }
}
function on_stat_cb( $db, $chat_id, $from_id, $arguments, $data ) {
    // Только личка
    if ( $chat_id != $from_id ) { answer_ok(); return; }
    // Только админ
    global $admin_id;
    if ( $admin_id != $from_id ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => tr( 'ONLYADMIN' ) ) );
        return;
    }
    // Общее количество опросов
    $cnt = db_get_poll_count( $db, 0, null );
    if ( $cnt == -1 ) {
        answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $db->lastErrorMsg() ) );
        return;
    }
    $res = 'In DB: ' . $cnt . "\n" . file_get_contents( 'last_poll_datetime.txt' );
    $cnt = count( explode( "\n", file_get_contents( 'users.txt' ) ) ) - 1;
    $res = $res . "\nUsers: " . $cnt;
    $cnt = count( explode( "\n", file_get_contents( 'authors.txt' ) ) ) - 1;
    $res = $res . "\nAuthors: " . $cnt;
    answer_by_method( 'sendMessage', array( 'chat_id' => $chat_id, 'text' => $res ) );
}
function on_users_cb( $db, $chat_id, $from_id, $arguments, $data ) { get_users( $chat_id, $from_id, 'users' ); }

function on_authors_cb( $db, $chat_id, $from_id, $arguments, $data ) { get_users( $chat_id, $from_id, 'authors' ); }
