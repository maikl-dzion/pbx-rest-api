<?php

class CustomLogger  {

    private $path;
    private $file = 'log.txt';

    private $title = '';
    private $level = 1;
    private $data = [];

    public  function __construct($path = '', $file = 'log.txt') {
        $this->setPath($path, $file);
    }

    public  function setPath($path = '', $file = '') {
        if($path) {
            if(!file_exists($path)) mkdir($path);
            $this->path = trim($path, '/') . DIRECTORY_SEPARATOR;
        }
        if($file) $this->file = $file;
    }

    // Записываем в лог
    public function log($data = [], $title = '', $level = 1){

        $this->title = $title;
        $this->level = $level;
        $message = $this->formattedData($data);
        return $this->write($message);
    }

    // Читаем из лога
    public function read($fileName = '', $endCount = -4) {
        $file = (!$fileName) ? $this->file : $fileName;
        $filePath = $this->path .  $file;
        $contents = file_get_contents($filePath);
        $input = explode('==Start', $contents);
        $output = array_slice($input, $endCount);
        $output = array_reverse($output);
        // echo "<pre>" . print_r($output, true) . "</pre>";
        return $this->render($output);
    }

    private function write($data) {
        $filePath = $this->getFilePath();
        // return @file_put_contents($filePath, $data, FILE_APPEND);
        return ['path' => $filePath, 'data' => $data];
    }

    private function formattedData($data = []){

        $eof = "\n";
        $fs  = "|-";
        $datetime = $this->datetime();

        $info = [
            'DATETIME'  => $datetime,
            'TITLE'     => $this->title,
            'LEVEL'     => $this->level,
            'SERIALIZE' => serialize($data),
            'DATA'      => var_export($data, true),
        ];

        $message = "";
        foreach ($info as $fieldName => $value) {
            $message .= "{$eof}{$fs} {$fieldName} = {$value}";
        }

        $l = "==============";
        return "{$eof}==Start=={$l}" . $message . "{$eof}==End===={$l}{$eof}";
    }

    private function getFilePath() {
        $filePath = $this->path . $this->file;
        return $filePath;
    }

    private function datetime() {
        $time = microtime(true);
        $micro = sprintf("%06d", ($time - floor($time)) * 1000000);
        return date('Y-m-d H:i:s.' . $micro);
    }

    private function render($items) {
        $fs = "|-";
        $result = [];
        foreach ($items as $k => $item) {

            $input = explode($fs, $item);
            array_shift($input);
            array_pop($input);
            $info = [];

            foreach ($input as $key => $value) {

                $_arr  = explode('=', $value);
                $param = trim($_arr[1]);
                $fieldName = '';

                switch ($key) {
                    case 0 : $fieldName = 'datetime';  break;
                    case 1 : $fieldName = 'title';     break;
                    case 2 : $fieldName = 'level';     break;
                    case 3 : $fieldName = 'info';
                        $param = unserialize($param);
                        break;
                    // case 4 : $fieldName = 'date';      break;
                }

                if($fieldName)
                    $info[$fieldName] = $param;
            }
            $result[] = $info;
        }

        return $result;
    }

    private function simpleFormattedData($data) {
        $datetime = $this->datetime();
        $eof = "\n";
        $message  = "{$eof}=========Start============{$eof}";
        $message .= "{$eof}". $datetime ."{$eof}";    // дата
        $message .= "{$eof}". var_export($data, true) . "{$eof}";   // данные
        $message .= "{$eof}==========End============={$eof}";
        return $message;
    }

}
