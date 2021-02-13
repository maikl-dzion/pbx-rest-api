<?php

class CustomErrorHandler extends \Exception {

   public function __construct() {

   }

   public function error($message, $code = 500) {
       $pos = [
           'line' => __LINE__,
           'file' => __FILE__,
           'func' => __FUNCTION__,
           'method' => __METHOD__,
       ];
       $jsonData = json_encode(['info' => $message, 'pos' => $pos]);
       throw new Exception($jsonData, $code);
   }
}