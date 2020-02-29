<?php
require_once( 'settings.php' );
require_once( 'logger.php' );
require_once( 'db_' . $db_module . '.php' );

// Инициализация базы
$db = db_init();
if ( !$db ) { echo __LINE__; die; }

// Очистка таблиц
if ( !db_clear( $db ) ) {
    echo db_last_error( $db );
    die;
}

// Инициализация таблиц
if ( !db_init_tables( $db, $increment, $suff ) ) {
    echo db_last_error( $db );
    die;
}

// Создание опроса
$author  = '39879348';
$name    = 'Тестовое имя опроса 1';
$items   = [ '1', '2', '3', '4', '5' ];
$text    = 'Текст опроса 📊';
$type    = 'photo';
$file    = 'kdnskjnvdskljv';
$poll_id = db_add_poll( $db, $author, $name, $items, $text, $type, $file );
if ( $poll_id < 1 ) { echo __LINE__."\n"; var_dump($poll_id); echo(db_last_error( $db )); die; }

// Получение опроса
$res = db_get_poll( $db, $poll_id );
if ( !$res or $res == -1 ) { echo __LINE__; die; }
$res["items"] = explode( "\n", $res["items"] );
if ( $res["author_id"] != $author  ) { echo __LINE__."\n"; var_dump($res["author_id"]);die; }
if ( time() - $res["created"] > 5  ) { echo __LINE__."\n"; var_dump($res["created"]);  die; }
if ( $res["name"]      != $name    ) { echo __LINE__."\n"; var_dump($res["name"]);     die; }
if ( $res["items"]     != $items   ) { echo __LINE__."\n"; var_dump($res["items"]);    die; }
if ( $res["text"]      != $text    ) { echo __LINE__."\n"; var_dump($res["text"]);     die; }
if ( $res["type"]      != $type    ) { echo __LINE__."\n"; var_dump($res["type"]);     die; }
if ( $res["file"]      != $file    ) { echo __LINE__."\n"; var_dump($res["file"]);     die; }
if ( $res["state"]     != 'active' ) { echo __LINE__."\n"; var_dump($res["state"]);    die; }
if ( $res["public"]    != 0        ) { echo __LINE__."\n"; var_dump($res["public"]);   die; }

// Редактирование опроса
$items   = [ '6', '5', '4', '3', '2', '1' ];
$text    = 'Текст опроса \n📊';
$type    = 'document';
$file    = 'sdkvnskdjvnslkjdv';
$res = db_edit_poll( $db, $poll_id, $items, $text, $type, $file );
if ( !$res ) { echo __LINE__; die; }
$res = db_get_poll( $db, $poll_id );
if ( !$res or $res == -1 ) { echo __LINE__; die; }
$res["items"] = explode( "\n", $res["items"] );
if ( $res["author_id"] != $author  ) { echo __LINE__."\n"; var_dump($res["author_id"]);die; }
if ( time() - $res["created"] > 5  ) { echo __LINE__."\n"; var_dump($res["created"]);  die; }
if ( $res["name"]      != $name    ) { echo __LINE__."\n"; var_dump($res["name"]);     die; }
if ( $res["items"]     != $items   ) { echo __LINE__."\n"; var_dump($res["items"]);    die; }
if ( $res["text"]      != $text    ) { echo __LINE__."\n"; var_dump($res["text"]);     die; }
if ( $res["type"]      != $type    ) { echo __LINE__."\n"; var_dump($res["type"]);     die; }
if ( $res["file"]      != $file    ) { echo __LINE__."\n"; var_dump($res["file"]);     die; }
if ( $res["state"]     != 'active' ) { echo __LINE__."\n"; var_dump($res["state"]);    die; }
if ( $res["public"]    != 0        ) { echo __LINE__."\n"; var_dump($res["public"]);   die; }

// Удаление/восстановление опроса
$state = 'locked';
$res = db_set_poll_state( $db, $poll_id, $state );
if ( !$res ) { echo __LINE__; die; }
$res = db_get_poll( $db, $poll_id );
if ( !$res ) { echo __LINE__; die; }
if ( $res["state"] != $state ) { echo __LINE__."\n"; var_dump($res["state"]); die; }

// Показ/скрытие результатов опроса
$public = 1;
$res = db_set_poll_public( $db, $poll_id, $public );
if ( !$res ) { echo __LINE__; die; }
$res = db_get_poll( $db, $poll_id );
if ( !$res ) { echo __LINE__; die; }
if ( $res["public"] != $public ) { echo __LINE__."\n"; var_dump($res["public"]); die; }

// Количество опросов
$count = 100;
for ( $i = 0; $i < $count - 1; $i++ ) {
    $a_i   = '398793498' . $i;
    $name  = 'Тестовое имя опроса ' . $i;
    $text  = 'Текст опроса 📊 ' . $i;
    $id    = db_add_poll( $db, $a_i, $name, $items, $text, 'text', '' );
    if ( $id < $i + 1 ) { echo __LINE__."\n"; var_dump($id); die; }
}
$res = db_get_poll_count( $db, null, 0 );
if ( $res != $count ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_poll_count( $db, $author, 0 );
if ( $res != 1  ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_poll_count( $db, $author, 1 );
if ( $res != 1  ) { echo __LINE__."\n"; var_dump($res); die; }
$state = 'deleted';
$res = db_set_poll_state( $db, $poll_id, $state );
if ( !$res ) { echo __LINE__; die; }
$res = db_get_poll_count( $db, $author, 0 );
if ( $res != 0  ) { echo __LINE__."\n"; var_dump($res); die; }

// Получение списка
$name    = 'Тестовое имя опроса 2';
$text    = 'Текст опроса 📊 2';
$new_id = db_add_poll( $db, $author, $name, $items, $text, $type, $file );
if ( $new_id < $count ) { echo __LINE__."\n"; var_dump($new_id); die; }
$res = db_get_poll_list( $db, $author );
if ( !$res or $res == -1 ) { echo __LINE__; die; }
if ( count( $res ) != 2  ) { echo __LINE__."\n"; var_dump($res); die; }
if ( $res[1]["name"] != $name  ) { echo __LINE__."\n"; var_dump($res["name"]); die; }
if ( $res[1]["text"] != $text  ) { echo __LINE__."\n"; var_dump($res["text"]); die; }

// Последний id опроса
$res = db_get_last( $db, $author );
if ( $res != $count + 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_last( $db, $author . '0' );
if ( $res != 0 ) { echo __LINE__."\n"; var_dump($res); die; }

// Добавление голоса
$user_id_0 = '989847309';
$user_id_1 = '139182372';
$user_id_2 = '139483372';
$res = db_add_vote( $db, $poll_id, 1, $user_id_0 );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_add_vote( $db, $poll_id, 1, $user_id_1 );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_add_vote( $db, $poll_id, 3, $user_id_2 );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }

// Получение голосов за пункт
$res = db_get_poll_item_votes( $db, $poll_id, 1 );
if ( $res != 2 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_poll_item_votes( $db, $poll_id, 2 );
if ( $res != 0 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_poll_item_votes( $db, $new_id, 1 );
if ( $res != 0 ) { echo __LINE__."\n"; var_dump($res); die; }

// Получение голоса
$res = db_get_vote( $db, $poll_id, $user_id_0 );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_vote( $db, $poll_id, $user_id_1 );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_vote( $db, $poll_id, $user_id_2 );
if ( $res != 3 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_vote( $db, $new_id, $user_id_0 );
if ( $res != 0 ) { echo __LINE__."\n"; var_dump($res); die; }

// Получение голосов у всего опроса
$user_id_2 = '954984985';
$res = db_add_vote( $db, $poll_id, 3, $user_id_2 );
if ( !$res ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_poll_votes( $db, $poll_id, 1 );
if ( $res != 4 ) { echo __LINE__."\n"; var_dump($res); die; }

// Удаление голоса
$res = db_delete_vote( $db, $poll_id, $user_id_2 );
if ( !$res ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_poll_votes( $db, $poll_id, 1 );
if ( $res != 3 ) { echo __LINE__."\n"; var_dump($res); die; }

// Обновление стека
$res = db_update_media_stack( $db, $user_id_0, $type, $file );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_update_media_stack( $db, $user_id_0, $type, $file );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }

// Получение стека
$res = db_get_media_stack( $db, $user_id_1 );
if ( $res != 0 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_media_stack( $db, $user_id_0 );
if ( !$res or $res == -1 ) { echo __LINE__."\n"; var_dump($res); die; }
if ( $res['type']      != $type      ) { echo __LINE__."\n"; var_dump($res['type']);      die; }
if ( $res['file']      != $file      ) { echo __LINE__."\n"; var_dump($res['file']);      die; }
if ( $res['author_id'] != $user_id_0 ) { echo __LINE__."\n"; var_dump($res['author_id']); die; }

$user_id_0 = 257139195;
$type      = 'photo';
$file      = 'AgACAgIAAxkBAAIBFF5SPMR4b6s1jb16GKnQ40DDENmFAAKWrDEbwAqZSrrskb3riitiG2XBDwAEAQADAgADeQAD484EAAEYBA';
$res = db_update_media_stack( $db, $user_id_0, $type, $file );
if ( $res != 1 ) { echo __LINE__."\n"; var_dump($res); die; }
$res = db_get_media_stack( $db, $user_id_0 );
if ( !$res or $res == -1 ) { echo __LINE__."\n"; var_dump($res); die; }
if ( $res['type']      != $type      ) { echo __LINE__."\n"; var_dump($res['type']);      die; }
if ( $res['file']      != $file      ) { echo __LINE__."\n"; var_dump($res['file']);      die; }
if ( $res['author_id'] != $user_id_0 ) { echo __LINE__."\n"; var_dump($res['author_id']); die; }

db_close( $db );

echo "OK\n";
