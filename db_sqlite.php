<?php
/*
Схема таблиц:

    // Записи голосований
    polls
        poll_id   - INTEGER - автоинкремент
        author_id - INTEGER - tg_user_id автора
        created   - NUMERIC - таймштамп создания опроса
        name      - TEXT    - внутреннее имя
        items     - TEXT    - варианты голосования через \n
        text      - TEXT    - текст опроса
        type      - TEXT    - тип прикреплённого файла (text, photo, document, audio, voice, sticker, venue, location, contact, video_note)
        file      - TEXT    - tg_file_id прикреплённого файла или json-encoded параметры для venue, location или contact
        state     - TEXT    - состояние опроса (active, locked, deleted, clean)
        public    - INTEGER - публичный ли опрос (у публичных количество голосов отображается на кнопках)

    // Записи голосов
    votes
        vote_id   - INTEGER - автоинкремент
        poll_id   - INTEGER - в каком опросе голос
        item      - INTEGER - за что голос
        update    - NUMERIC - таймштамп создания или последнего обновления
        user_id   - INTEGER - от кого голос

    // Таблица для временного хранения файлов для прикреплению к голосованию отдельно командой attach
    stack
        author_id - INTEGER - tg_user_id
        type      - TEXT    - тип файла
        file      - TEXT    - tg_file_id

*/

/*
Инициализация БД
Проверяет наличие файла базы. Если его нет, то сначала создаёт все нужные таблицы
Возвращает объект соединения с БД
*/
function db_init( $db_name ) {
    if ( !file_exists( $db_name ) ) {
        $db = new SQLite3( $db_name );
        $sql = "CREATE TABLE `polls`( poll_id   INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                                      author_id INTEGER NOT NULL,
                                      created   NUMERIC,
                                      name      TEXT,
                                      items     TEXT,
                                      `text`    TEXT,
                                      type      TEXT,
                                      file      TEXT,
                                      state     TEXT DEFAULT 'active',
                                      public    INTEGER DEFAULT 0
                                    );";
        $db->query( $sql );
        $sql = "CREATE TABLE `votes`( vote_id   INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT UNIQUE,
                                      poll_id   INTEGER NOT NULL,
                                      item      INTEGER NOT NULL,
                                      `update`  NUMERIC,
                                      user_id   INTEGER NOT NULL
                                    );";
        $db->query( $sql );
        $sql = "CREATE TABLE `stack`( author_id INTEGER NOT NULL PRIMARY KEY UNIQUE,
                                      type      TEXT,
                                      file      TEXT DEFAULT ''
                                    );";
        $db->query( $sql );
    }
    else {
       $db = new SQLite3( $db_name );
    }
    $db->busyTimeout( 10000 );

    return $db;
}

/*
Создание опроса
Добавляет запись в таблицу polls
Возвращает id опроса, в случае успешного создания, или -1 в случае ошибки
*/
function db_add_poll( $db, $author, $name, $items, $text, $type, $file ) {
    $author = intval( $author );
    $sql   = "INSERT INTO polls ( author_id, created, name, items, text, type, file ) VALUES ( :author_id, :created, :name, :items, :text, :type, :file );";
    $items = implode( "\n", $items );
    $stmt  = $db->prepare( $sql );
    $stmt->bindValue( ':author_id', $author, SQLITE3_INTEGER );
    $stmt->bindValue( ':created',   time(),  SQLITE3_INTEGER );
    $stmt->bindValue( ':name',      $name,   SQLITE3_TEXT    );
    $stmt->bindValue( ':items',     $items,  SQLITE3_TEXT    );
    $stmt->bindValue( ':text',      $text,   SQLITE3_TEXT    );
    $stmt->bindValue( ':type',      $type,   SQLITE3_TEXT    );
    $stmt->bindValue( ':file',      $file,   SQLITE3_TEXT    );
    $res = $stmt->execute();
    return $res ? $db->lastInsertRowID() : -1;
}

/*
Редактирование опроса
Обновляет запись в таблице polls
Возвращает 1 в случае успеха, или 0 в случае ошибки
*/
function db_edit_poll( $db, $poll_id, $items, $text, $type, $file ) {
    $poll_id = intval( $poll_id );
    $sql     = "UPDATE polls SET items = :items, text = :text, type = :type, file = :file WHERE poll_id = :poll_id";
    $stmt    = $db->prepare( $sql );
    $items   = implode( "\n", $items );
    $stmt->bindValue( ':poll_id', $poll_id, SQLITE3_INTEGER );
    $stmt->bindValue( ':items',   $items,   SQLITE3_TEXT    );
    $stmt->bindValue( ':text',    $text,    SQLITE3_TEXT    );
    $stmt->bindValue( ':type',    $type,    SQLITE3_TEXT    );
    $stmt->bindValue( ':file',    $file,    SQLITE3_TEXT    );
    return $stmt->execute();
}

/*
Получение данных опроса
Возвращает массив с данными опроса, 0 если такого опроса нет, или -1 в случае ошибки
*/
function db_get_poll( $db, $poll_id ) {
    $poll_id = intval( $poll_id );
    $sql     = "SELECT * FROM polls WHERE poll_id = " . $poll_id;
    $res     = $db->query( $sql );
    return $res ? $res->fetchArray() : -1;
}

/*
Удаление/восстановление опроса
Ставит указанный state у опроса
Возвращает 1 в случае успеха, 0 при ошибке
*/
function db_set_poll_state( $db, $poll_id, $state ) {
    $poll_id = intval( $poll_id );
    $sql     = "UPDATE polls SET state = :state WHERE poll_id = :poll_id";
    $stmt    = $db->prepare( $sql );
    $stmt->bindValue( ':state',   $state,   SQLITE3_TEXT    );
    $stmt->bindValue( ':poll_id', $poll_id, SQLITE3_INTEGER );
    return $stmt->execute();
}

/*
Ставит указанное значение в поле public у опроса
Возвращает 1 в случае успеха, 0 при ошибке
*/
function db_set_poll_public( $db, $poll_id, $public ) {
    $poll_id = intval( $poll_id );
    $sql     = "UPDATE polls SET public = :public WHERE poll_id = :poll_id";
    $stmt    = $db->prepare( $sql );
    $stmt->bindValue( ':public',  $public,  SQLITE3_INTEGER );
    $stmt->bindValue( ':poll_id', $poll_id, SQLITE3_INTEGER );
    return $stmt->execute();
}

/*
Количество опросов
Если автор не указан, то учитываются все опросы
Возвращает количество опросов или -1 в случае ошибки
*/
function db_get_poll_count( $db, $author, $all ) {
    $author = intval( $author );
    $sql    = $author > 0 ? "SELECT COUNT( poll_id ) FROM polls WHERE author_id = " . $author : "SELECT COUNT( poll_id ) FROM polls";
    if ( !$all and $author > 0 ) { $sql = $sql . " AND state IN ( 'active', 'locked' )"; }
    $res = $db->query( $sql );
    return $res ? $res->fetchArray()[0] : -1;
}

/*
Получение списка опросов юзера
Возвращает массив массивов с данными опроса, -1 в случае ошибки или null в случае отсутствия опросов у автора
*/
function db_get_poll_list( $db, $author ) {
    $author = intval( $author );
    $sql    = "SELECT * FROM polls WHERE author_id = " . $author;
    $res    = $db->query( $sql );
    if ( !$res ) { return -1; }
    $list = null;
    while ( $row = $res->fetchArray() ) { $list[] = $row; }
    return $list;
}

/*
Получение id последнего созданного этим юзером голосования
Возвращает id опроса, в случае успешного получения, или -1 в случае ошибки. 0 - опросов у этого автора нет
*/
function db_get_last( $db, $author ) {
    $author = intval( $author );
    $sql    = "SELECT poll_id FROM polls WHERE author_id = " . $author . " ORDER BY poll_id DESC LIMIT 1";
    $res    = $db->query( $sql );
    if ( !$res ) { return -1; }
    $res = $res->fetchArray();
    return $res ? $res['poll_id'] : 0;
}

/*
Получение голосов по опросу за указанный пункт
Возвращает количество голосов или -1 в случае ошибки
*/
function db_get_poll_item_votes( $db, $poll_id, $item ) {
    $poll_id = intval( $poll_id );
    $item    = intval( $item );
    $sql     = "SELECT COUNT( vote_id ) FROM votes WHERE poll_id = " . $poll_id . " AND item = " . $item;
    $res = $db->query( $sql );
    return $res ? $res->fetchArray()[0] : -1;
}

/*
Получение голосов по опросу за последние h часов
Если h равен 0, то за всё время
Возвращает количество голосов или -1 в случае ошибки
*/
function db_get_poll_votes( $db, $poll_id, $h ) {
    $poll_id = intval( $poll_id );
    $h       = intval( $h );
    $ts      = $h > 0 ? time() - $h * 60 * 60 : 0;
    $sql     = "SELECT COUNT( vote_id ) FROM votes WHERE poll_id = " . $poll_id . " AND `update` > " . $ts;
    $res = $db->query( $sql );
    return $res ? $res->fetchArray()[0] : -1;
}

/*
Внесение голоса
Добавляет запись в таблицу votes
Возвращает 1 в случае успеха, 0 при ошибке
*/
function db_add_vote( $db, $poll_id, $item, $user_id ) {
    $poll_id = intval( $poll_id );
    $item    = intval( $item );
    $user_id = intval( $user_id );
    $sql     = "INSERT INTO votes ( poll_id, item, `update`, user_id ) VALUES ( :poll_id, :item, :update, :user_id );";
    $stmt    = $db->prepare( $sql );
    $stmt->bindValue( ':poll_id', $poll_id, SQLITE3_INTEGER );
    $stmt->bindValue( ':item',    $item,    SQLITE3_INTEGER );
    $stmt->bindValue( ':update',  time(),   SQLITE3_INTEGER );
    $stmt->bindValue( ':user_id', $user_id, SQLITE3_INTEGER );
    return $stmt->execute();
}

/*
Получение голоса данного юзера в этом голосовании
Возвращает номер пункта опроса, 0 в случае отсутствия голоса или -1 в случае ошибки
*/
function db_get_vote( $db, $poll_id, $user_id ) {
    $poll_id = intval( $poll_id );
    $user_id = intval( $user_id );
    $sql     = "SELECT item FROM votes WHERE poll_id = " . $poll_id . " AND user_id = " . $user_id;
    $res     = $db->query( $sql );
    if ( !$res ) { return -1; }
    $res = $res->fetchArray();
    return $res ? $res['item'] : 0;
}

/*
Удаление голоса данного юзера в этом голосовании
Возвращает 1 в случае успеха, 0 при ошибке
*/
function db_delete_vote( $db, $poll_id, $user_id ) {
    $poll_id = intval( $poll_id );
    $user_id = intval( $user_id );
    $sql     = "DELETE FROM votes WHERE poll_id = :poll_id AND user_id = :user_id";
    $stmt    = $db->prepare( $sql );
    $stmt->bindValue( ':poll_id', $poll_id, SQLITE3_INTEGER );
    $stmt->bindValue( ':user_id', $user_id, SQLITE3_INTEGER );
    return $stmt->execute();
}

/*
Получение данных аттача
Возвращает массив с данными медиа, 0 если ничего нет, или -1 в случае ошибки
*/
function db_get_media_stack( $db, $user_id ) {
    $user_id = intval( $user_id );
    $sql     = "SELECT * FROM stack WHERE author_id = " . $user_id;
    $res     = $db->query( $sql );
    return $res ? $res->fetchArray() : -1;
}

/*
Обновление данных аттача
Возвращает 1 в случае успеха, 0 при ошибке
*/
function db_update_media_stack( $db, $user_id, $type, $file ) {
    $user_id = intval( $user_id );
    $sql     = "SELECT * FROM stack WHERE author_id = " . $user_id;
    $res     = $db->query( $sql );
    if ( !$res ) { return 0; }
    $res = $res->fetchArray();
    if ( $res ) {
        if ( $res["type"] == $type and $res["file"] == $file ) { return 1; }
        $sql = "UPDATE stack SET type = :type, file = :file  WHERE author_id = :author_id";
    }
    else {
        $sql = "INSERT INTO stack ( author_id, type, file ) VALUES ( :author_id, :type, :file );";
    }
    $stmt = $db->prepare( $sql );
    $stmt->bindValue( ':author_id', $user_id, SQLITE3_INTEGER );
    $stmt->bindValue( ':type',      $type,    SQLITE3_TEXT    );
    $stmt->bindValue( ':file',      $file,    SQLITE3_TEXT    );
    return $stmt->execute();
}