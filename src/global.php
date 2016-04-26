<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 26.04.16 at 10:43
 */

// Define this module identifier
define('STATIC_RESOURCE_HANDLER', 'view');

/** Collection of supported mime types */
$mimeTypes = array(
    'css' => 'text/css',
    'woff' => 'application/x-font-woff',
    'woff2' => 'application/x-font-woff2',
    'otf' => 'application/octet-stream',
    'ttf' => 'application/octet-stream',
    'eot' => 'application/vnd.ms-fontobject',
    'js' => 'application/x-javascript',
    'htm' => 'text/html;charset=utf-8',
    'htc' => 'text/x-component',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'jpg' => 'image/jpg',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'pdf' => 'application/pdf',
    'rtf' => 'application/rtf',
    'doc' => 'application/msword',
    'xls' => 'application/msexcel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'svg' => 'image/svg+xml',
    'mp4' => 'video/mp4',
    'ogg' => 'video/ogg'
);

// Perform custom simple URL parsing to match needed URL for static resource serving
$url = array_key_exists('REQUEST_URI', $_SERVER) ? $_SERVER['REQUEST_URI'] : '';

// Get URL path from URL and split with "/"
$url = array_values(array_filter(explode('/', parse_url($url, PHP_URL_PATH))));

// Special hook to avoid further framework loading if this is static resource request
if (array_key_exists(0, $url) && $url[0] === STATIC_RESOURCE_HANDLER) {
    // Получить путь к ресурсу системы по URL
    $filename = realpath('../' . $_GET['p']);

    if (file_exists($filename)) {
        // Этот параметр характеризирует время последней модификации ресурса
        // и любые его доп параметры конечно( ПОКА ТАКИХ НЕТ )
        $c_etag = array_key_exists('HTTP_IF_NONE_MATCH', $_SERVER) ? $_SERVER['HTTP_IF_NONE_MATCH'] : '';

        // Получим параметр от сервера как отметку времени последнего
        // изменения оригинала ресурса и любые его доп параметры
        // конечно( ПОКА ТАКИХ НЕТ )
        $s_etag = filemtime($filename);

        // Установим заголовки для кеширования
        // Поддержка кеша браузера
        header('Cache-Control:max-age=1800');

        // Установим заголовок с текущим значением параметра валидности ресурса
        header('ETag:' . $s_etag);

        // Get file extension
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Если эти параметры совпадают - значит оригинал ресурса в кеше клиента - валидный
        // Сообщим об этом клиенту специальным заголовком
        if ($c_etag === $s_etag) {
            header('HTTP/1.1 304 Not Modified');
        } else {
            // Если эти параметры НЕ совпадают - значит оригинал ресурса был изменен
            // и мы поддерживаем данное расширение для выдачи как ресурс
            if (array_key_exists($extension, $mimeTypes)) {
                // Укажем тип выдаваемого ресурса
                header('Content-type: ' . $mimeTypes[$extension]);

                // Выведем содержимое файла
                echo file_get_contents($filename);
            } else { // Мы не поддерживаем указанное расширение файла для выдачи как ресурс
                header('HTTP/1.0 404 Not Found');
            }
        }
    } else { // Требуемый файл не существует на сервере
        header('HTTP/1.0 404 Not Found');
    }

    // Avoid further request processing
    die();
}
