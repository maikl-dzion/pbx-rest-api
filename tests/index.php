<?php

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');

ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

$dirName = dirname(__DIR__);

$protocol   = $_SERVER['REQUEST_SCHEME'];
$serverName = $_SERVER['SERVER_NAME'];
$requestUri = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
array_pop($requestUri);
$requestUri = implode('/', $requestUri);
$apiUrl = $protocol .'://'. $serverName .'/'. $requestUri;

// lg($apiUrl);

// define('API_URL', 'https://192.168.3.187/pbxapi');
define('API_URL', $apiUrl);
define('JWT_TOKEN_NAME', 'USE-JWT-TOKEN');

// Авторизация

$response = auth();

// Старт тестирования
$pbxTest = new PbxApiTest();
$pbxTest->run();
$results = $pbxTest->results;
// Конец тестирования

printMessage('<h2>Подробная информация:</h2>', 'blue');
lg($results);


//////////////////////////////////////////
/////////////////////////////////////////
///    ACTIONS  FUNCTIONS
///


class PbxApiTest {

    public $results = [];
    public $errors  = [];

    protected $deleteUrls = [];
    protected $extensionsItems = [];

    protected $itemName;
    protected $itemIdName;
    protected $itemType;
    protected $newExtension = 0;
    protected $secondExtension = 0;

    public function __construct() {

        $this->deleteItems();

        $url  = API_URL . '/extensions';
        $this->extensionsItems = fetchData($url);

        if(!empty($this->extensionsItems[0]))
            $this->secondExtension = $this->extensionsItems[0]->extension;
    }

    public function run() {

        printHtml('<body style="background: white" >');
        printMessage('<h3>API URL: ' . API_URL . ' </h3>', 'blue');
        printMessage('<h5>Старт тестирования</h5>', 'chocolate');

        $this->extensions();
        $this->ringgroups();
        $this->queues();
        $this->ivrs();
        $this->trunks();

        $this->deleteItems();

        printMessage('<h5>Конец тестирования</h5>', 'chocolate');

        printHtml('</body>');

    }

    public function extensions() {


        $url  = API_URL . '/extensions';
        $newUserName = 'NewUser_78910';
        $data = [
            'name'  => $newUserName,
            // 'extension' => '',
        ];

        $this->itemName   = 'name';
        $this->itemIdName = 'extension';
        $this->itemType   = 'extension';

        printMessage('<h3>ВНУТРЕННИЕ НОМЕРА</h3>', 'blue');

        $this->results['extensions'] = $this->testBlock($url, $data);

        $this->newExtension = $this->results['extensions']['extension'];


    }

    public function ringgroups() {

        $url     = API_URL . '/ringgroups';
        $newName = 'NewGroup_78910';

        $this->itemName   = 'name';
        $this->itemIdName = 'extension';
        $this->itemType   = 'group';

        $list = $this->getLinkList();

        $data = [
            'name'  => $newName,
            'extension_list' => $list,
            // 'extension' => '',
        ];

        printMessage('<h3>ГРУППЫ</h3>', 'blue');

        $this->results['ringgroups'] = $this->testBlock($url, $data);

    }

    public function queues() {

        $url = API_URL . '/queues';
        $newUserName = 'NewQueue_78910';

        $this->itemName   = 'name';
        $this->itemIdName = 'extension';
        $this->itemType   = 'queue';

        $list = $this->getLinkList();

        $data = [
            'name' => $newUserName,
            'static_members' => $list,
            // 'extension' => '',
            // 'static_members' => ["43, 0", "44, 1"],
        ];

        printMessage('<h3>ОЧЕРЕДИ</h3>', 'blue');
        $this->results['queues'] = $this->testBlock($url, $data);

    }

    public function ivrs() {

        $url = API_URL . '/ivrs';
        $newUserName = 'NewIvr_78910';

        $entries = [

            [
              "ivr_id"    => 0,
              "selection" => 1,
              "dest"      => "from-did-direct,40,1",
              "ivr_ret"   => "0"
            ],

            [
                "ivr_id"    => 0,
                "selection" => 2,
                "dest"      => "ext-group,656,1",
                "ivr_ret"   => "0"
            ],

            [
                "ivr_id"    => 0,
                "selection" => 3,
                "dest"      =>  "ext-queues,802,1",
                "ivr_ret"   => "0"
            ],

        ];

        $data = [
            'name' => $newUserName,
            'entries_list' => $entries,
        ];

        $this->itemName   = 'name';
        $this->itemIdName = 'id';
        $this->itemType   = 'ivr';

        printMessage('<h3>ГОЛОСОВОЕ МЕНЮ</h3>', 'blue');
        $this->results['ivrs'] = $this->testBlock($url, $data);

    }

    public function trunks() {

        $url = API_URL . '/trunks';
        $newUserName = 'NewTrunk_78910';
        $data = [
            'name' => $newUserName,
            'trinkid'        => 0,

            'channelid'      => "",
            'continue'       => "",
            'dialoutprefix'  => "",
            'extension_list' => [],
            'maxchans'       => "",
            'outcid'         => "",
            'tech'           => "sip",
            'usercontext'    => "",
        ];

        $this->itemName   = 'name';
        $this->itemIdName = 'id';
        $this->itemType   = 'trunk';

        printMessage('<h3>ВНЕШНИЙ НОМЕР</h3>', 'blue');
        $this->results['trunks'] = $this->testBlock($url, $data);

    }

    protected function testBlock($url, $data) {

        $extension = '';
        $response  = [];

        $name   = $this->itemName;
        $idName = $this->itemIdName;
        $type   = $this->itemType;

        $newName   = $data[$name];

        $color = 'green';

        printMessage('<div>Url : '.$url.' </div>', 'blue');
        printMessage('<div>Request data</div>', 'green');
        printArray($data);

        ////////////////////////////////////////////////////////
        // ----------------------------------------------------
        printMessage('Тестируем создание объекта', $color);

        $create = _post($url, $data);
        $createResponse = $this->saveResponse($create);
        if((!empty($resp['error'])) && (!empty($resp['info']['error'])) ) {
            lg(['message' => 'Ошибка при создании', 'save' => $resp]);
        }

        $list = fetchData($url);

        foreach ($list as $key => $item) {
            if($item->$name != $newName)
                continue;
            $response['create_status'] = true;
            $response['list'] = $item;
            // lg([$item, $idName]);
            $extension = $item->$idName;
            break;
        }

        printMessage('Выборка списка объектов прошла успешно (count='.count($list).')', $color);

        if(!$extension) {
            lg([
                'message' => 'Не удалось найти созданный элемент',
                'create'  => $create,
                'list'    => $list,
                'name'    => $name,
                'id'      => $idName,
            ]);
        }

        $response['extension'] = $extension;

        printMessage('Создание объекта выполнилось УСПЕШНО', $color);
        printMessage('<div>Response (add)</div>', 'green');
        printArray($createResponse);
        // ----------------------------------------------------
        ////////////////////////////////////////////////////////

        $extensionUrl = $url . '/' . $extension;

        $fetch = fetchItem($extensionUrl);
        $response['create'] = ['save' => $create, 'fetch' => $fetch];

        printMessage('Выборка 1 объектa прошла успешно (extension=<span style="color:indigo;font-size:18px;">'.$extension.'</span>)', $color);

        ////////////////////////////////////////////////////////
        // ----------------------------------------------------
        printMessage('Тестируем изменение объекта ', $color);
        $newName .= '_testUpdate';
        $data['name'] = $newName;
        $update = _put($extensionUrl, $data);
        $updateResponse = $this->saveResponse($update);

        $fetch  = fetchItem($extensionUrl);
        $response['update'] = ['save' => $update, 'fetch' => $fetch];
        printMessage('Изменение объекта выполнилось УСПЕШНО', $color);
        printMessage('<div>Response (update)</div>', 'green');
        printArray($updateResponse);
        // ----------------------------------------------------
        ////////////////////////////////////////////////////////


        $_SESSION['DELETE_URLS'][$this->itemType] = $extensionUrl;

//        ////////////////////////////////////////////////////////
//        // ----------------------------------------------------
//        printMessage('Тестируем удаление объекта ', $color);
//        $delete = _delete($extensionUrl);
//        $response['delete'] = fetchItem($extensionUrl);
//        printMessage('Удаление объекта выполнилось УСПЕШНО', $color);
//        // ----------------------------------------------------
//        ////////////////////////////////////////////////////////

        return $response;

    }

    public function deleteItems() {


        if(!empty($_SESSION['DELETE_URLS'])) {

            $deleteUrls = $_SESSION['DELETE_URLS'];
            $response = [];

            printMessage('<h2>ТЕСТИРУЕМ УДАЛЕНИЕ</h2>', 'blue');

            foreach ($deleteUrls as $name => $item) {

                $color = 'green';
                $extensionUrl = $item;

                printMessage('Тестируем удаление объекта: ' . $name , $color);
                $delete = _delete($extensionUrl);
                $fetch  = fetchItem($extensionUrl);
                $response[$name] = ['del' => $delete, 'fetch' => $fetch];
                printMessage('Удаление объекта выполнилось УСПЕШНО', $color);

            }

            $this->results['delete'] = $response;

        }

        $_SESSION['DELETE_URLS'] = [];
    }

    public function saveResponse($data) {

        $result = $error = [];

        if(!empty($data['result'])) {

             if(!empty(json_decode($data['result'])->results))
                $result = (array)json_decode($data['result'])->results;

             if(!empty(json_decode($data['result'])->error))
                $error = (array)json_decode($data['result'])->error;

             $response = (array)json_decode($data['result']);
             if(!empty($response['results']))
                 $result = (array)$response['results'];

             if(!empty($response['error']))
                 $error = (array)$response['error'];
        }

        return [
            'result' => $result,
            'error'  => $error,
            'info'   => $data,
        ];
    }

    protected function getLinkList() {

        $list = [];

        switch ($this->itemType) {
            case 'group' :
                if($this->newExtension)
                    $list[] = $this->newExtension;
                if($this->secondExtension)
                    $list[] = $this->secondExtension;
                break;

            case 'queue':
                if($this->newExtension)
                    $list[] = "{$this->newExtension}, 0";
                if($this->secondExtension)
                    $list[] = "{$this->secondExtension}, 0";
                break;
        }

        return $list;
    }
    //    public function __construct() {}

}


function auth() {

    $authData = [
        'user'     => 'admin',
        'password' => 'St969FMetido',
        // 'password' => '123456Aa',
    ];

    $authUrl = API_URL . '/authenticate';

    printMessage('<h2>Тестируем авторизацию:</h2>', 'blue');
    printMessage('<div>Url: '.$authUrl.' </div>', 'blue');
    printArray($authData);

    $response = _post($authUrl, $authData, false);
    $record = (array)json_decode($response['result']);

    printArray($response);
    printArray($record);

    setToken($record);
    return $record;
}


//////////////////////////////////////////
/////////////////////////////////////////
///      HELPERS FUNCTIONS
///

function getToken($fname = 'access_token') {
    $token = trim($_SESSION[JWT_TOKEN_NAME][$fname]);
    return $token;
}

function setToken($tokens) {
    $_SESSION[JWT_TOKEN_NAME] = $tokens;
}

function fetchData($url) {
    $response = _get($url);
    $result = (array)json_decode($response['result']);
    $error  = (array)json_decode($response['error']);
    if(!empty($error))
        lg([ 'HTTP_ERROR' => $error]);
    if(!empty($result['results'])) {
        $result = $result['results'];
    }
    return $result;
}

function fetchItem($url, $index = 0) {
    $item = fetchData($url);
    if(empty($item))
        return false;

    if(is_object($item))
        $item = (array)$item;

    if(!empty($item[$index])) {
        return $item[$index];
    } elseif(!empty($item['results'])) {
        return $item['results'];
    }

    return $item;
}


function _get($url) {

    $jwtToken = getToken();

    $headers = [
        'Authorization: Bearer ' . $jwtToken,
        'Content-Type: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_HEADER, true);
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close ($ch);
    return [
        'result' => $result,
        'error'  => $error,
        'code'   => $code,
    ];
}


function _post($url, $data, $setHeader = true) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    if($setHeader) {
        $data = json_encode ($data, JSON_UNESCAPED_UNICODE);
        $jwtToken = getToken();
        $headers = [
            'Authorization: Bearer ' . $jwtToken,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } else {
        $data = dataConvert($data);
    }

    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close ($ch);
    return [
        'result' => $result,
        'error'  => $error,
        'code'   => $code,
    ];
}

function _put($url, $data, $setHeader = true)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    if($setHeader) {
        $data = json_encode ($data, JSON_UNESCAPED_UNICODE);
        $jwtToken = getToken();
        $headers = [
            'Authorization: Bearer ' . $jwtToken,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    } else {
        $data = dataConvert($data);
    }

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close ($ch);
    return [
        'result' => $result,
        'error'  => $error,
        'code'   => $code,
    ];
}

function _delete($url) {

    $jwtToken = getToken();

    $headers = [
        'Authorization: Bearer ' . $jwtToken,
        'Content-Type: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_HEADER, true);
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close ($ch);
    return [
        'result' => $result,
        'error'  => $error,
        'code'   => $code,
    ];
}


function dataConvert($data) {
    $result = [];
    foreach ($data as $fname => $value) {
        $result[] = "$fname = $value";
    }
    return implode('&', $result);
}

function printMessage($message, $color = 'green') {
   $content = "<div class='print-message' 
                   style='font-size:16px;color:{$color}; margin:5px; padding:5px; border:1px gainsboro solid;' 
                >{$message}</div>";
   print $content;
}

function printHtml($content) {
    print $content;
}

function printArray($data) {
   $content =  print_r($data, true);
   print "<div style='font-style: italic; color:grey; margin-left:20px;'><pre>$content</pre></div>";
}

function lg()
{
    $out = '';
    $get = false;
    $style = 'margin:10px; padding:10px; border:3px red solid;';
    $args = func_get_args();
    foreach ($args as $key => $value) {
        $itemArr = array();
        $itemStr = '';
        is_array($value) ? $itemArr = $value : $itemStr = $value;
        if ($itemStr == 'get') $get = true;
        $line = print_r($value, true);
        $out .= '<div style="' . $style . '" ><pre>' . $line . '</pre></div>';
    }

    $debugTrace = debug_backtrace();
    $line = print_r($debugTrace, true);
    $out .= '<div style="' . $style . '" ><pre>' . $line . '</pre></div>';

    if ($get) return $out;
    print $out;
    exit;

}


