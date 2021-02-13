<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET,POST,PUT,DELETE,HEAD,OPTIONS");
header("Access-Control-Allow-Headers: Origin,Content-Type,Accept,Authorization");
header("Access-Control-Allow-Headers: *");

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// lg($_SERVER);

define('ISSABEL_PBX_CONF', '/etc/issabelpbx.conf');
define('GLOBAL_AMI_CONF' , '/etc/issabel.conf');
define('LOG_PATH'        , __DIR__ . '/log');
define('LOG_FILE'        , 'log.txt');

require_once __DIR__ . '/lib/base.php';
require_once __DIR__ . '/lib/CustomErrorHandler.php';
require_once __DIR__ . '/lib/CustomLogger.php';

$errorHandle  = new CustomErrorHandler();
$customLogger = new CustomLogger(LOG_PATH, LOG_FILE);

$amiAdminPwd = $dbPassword = $cyrusUserPwd = '';

if (is_file(GLOBAL_AMI_CONF)) {
    $globalAmiConf = parse_ini_file(GLOBAL_AMI_CONF);
    $dbPassword   = $globalAmiConf['mysqlrootpwd'];
    $amiAdminPwd  = $globalAmiConf['amiadminpwd'];
    $cyrusUserPwd = $globalAmiConf['cyrususerpwd'];
}

// $amiAdminPwd = 'gfhg';

//-- Параметры подключение к базе ------
$dbUser = 'root';
$host   = 'localhost';
$dbName = 'asterisk';
$driver = 'mysql';
$port   = 3306;
$dsn = "{$driver}:host={$host};port={$port};dbname=$dbName";

$options = [
    \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION, // generic attribute
    \PDO::ATTR_PERSISTENT => true,     // we want to use persistent connections
    \PDO::MYSQL_ATTR_COMPRESS => true, // MySQL-specific attribute
];
// ---------------------------------------

// Конфигурируем базовый класс
$app = Base::instance();
$app->set('AUTOLOAD', 'models/; controllers/'); // Запускаем автолоадинг
$app->set('DEBUG'   , 255);

$app->set('MGRPASS'     , $amiAdminPwd);
$app->set('ERROR_CLASS' , $errorHandle);
$app->set('DB', new DB\SQL($dsn, $dbUser, $dbPassword, $options));  // Создаем объект базы данных

$app->set('JWT_KEY', 'ZWRiYWMxZTYxYWFmZDI3YWNhOTE1ZDdmZTkxNmUwZDU5ZGY1ZGJjYjJjMzA4ZDJi');
// $app->set('JWT_EXPIRES', 210 * 210);  // Время жизни токена
$app->set('JWT_EXPIRES', 550 * 320);  // Время жизни токена

$app->route('GET /', 'help->display');
$app->map('/@controller', '@controller');
$app->map('/@controller/@id', '@controller');
$app->route('GET /@controller/search/@term', '@controller->search');

// lg($app);

try {

    $app->run();  //--- Запускаем приложение

} catch (Exception $exception) {

    $error = exceptionHandler($exception);

    $info  = $error['info'];
    $trace = $error['trace'];
    $json  = $error['json_data'];

    $message = $error['response']['message'];
    $code    = $error['response']['code'];


    if(!empty($json)) {
        $json['file'] = $info['file'];
        $json['line'] = $info['line'];
        $errorInfo = $json;
    } else {
        $errorInfo = $info;
    }

    // $log = $customLogger->log($saveInfo, $message);
    // lg($log);
    // @file_put_contents($log['path'], (string)$log['data'], FILE_APPEND);

    // throw new Exception(json_encode($info), 500);
    // throw new Exception('Ошибка распознана', 500);

    errorResponse($errorInfo);

    // lg($error);
    // header($_SERVER['SERVER_PROTOCOL'] . ' ' . $message, true, $code);
    // die();
}



///////////////////////////
///////////////////////////
///////////////////////////
///
///
///

//function createDbObject($DbClass, $user, $password, $dbName = 'asterisk', $host = 'localhost', $driver = 'mysql', $port = 3306) {
//
//    $options = [
//        \PDO::ATTR_ERRMODE    => \PDO::ERRMODE_EXCEPTION, // generic attribute
//        \PDO::ATTR_PERSISTENT => true,     // we want to use persistent connections
//        \PDO::MYSQL_ATTR_COMPRESS => true, // MySQL-specific attribute
//    ];
//
//    $dsn = "{$driver}:host={$host};port={$port};dbname=$dbName";
//    $db  = new $DbClass($dsn, $user, $password, $options);
//
//    return $db;
//}

function jsonResponse($data, $fname = '', $code = 200) {
    // header("HTTP/1.0 404 Not Found");
    if($fname) $data = [$fname => $data];
    die(json_encode($data));
}

function errorResponse($data, $code = 200) {
    jsonResponse($data, 'error');
}

function exceptionHandler($exception, $params = []) {

    $message = $exception->getMessage();
    $jsonData = json_decode($message);

    $code = $exception->getCode();
    $line = $exception->getLine();
    $file = $exception->getFile();

    $errorInfo = [
        'file'        => $file,
        'line'        => $line,
        'message'     => $message,
        'code'        => $code,
        'json_data'   => $jsonData,
    ];

    $errorTrace = [
        'trace'        => $exception->getTrace(),
        'trace_as_str' => $exception->getTraceAsString(),
    ];

    if(!empty($jsonData)) {
        if(isset($jsonData['message'])) {
            $message = $jsonData['message'];
        } elseif(isset($jsonData[0])) {
            $message = $jsonData[0];
        }
    }

    return [
        'info'      => $errorInfo,
        'trace'     => $errorTrace,
        'json_data' => $jsonData,
        'response'  => ['message' => $message, 'code' => $code],
    ];

}


function lg()
{
    $trace   = '';
    $return  = false;
    $results = ' <h1>Data</h1>';
    $style   = 'margin:10px; padding:10px; border:3px red solid;';

    $args   = func_get_args();
    if(!empty($args)) {
        foreach ($args as $key => $values) {
            if(is_string($values) && $values == 'get')
                $return = true;

            $line = print_r($values, true);
            $results .= "<div style='{$style}' ><pre>{$line}</pre></div>";
        }
    } else {
        $results .= "<div style='{$style}' ><pre>Empty</pre></div>";
    }

    $trace = debug_backtrace();
    $trace = print_r($trace, true);
    $trace = "<h1>Trace</h1>
              <div style='{$style}' ><pre>{$trace}</pre></div>";

    $results .= $trace;

    if ($return)
        return $results;
    print $results;
    exit;
}