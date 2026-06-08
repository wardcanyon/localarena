<?php

function localarena_db_password(): string {
    $pw = getenv('DB_PASSWORD');
    if ($pw !== false && $pw !== '') {
        return $pw;
    }
    return trim(file_get_contents(getenv('DB_PASSWORD_FILE_PATH')));
}

function localarena_db_port(): int {
    $port = getenv('DB_PORT');
    if ($port !== false && $port !== '') {
        return (int)$port;
    }
    return 3306;
}
