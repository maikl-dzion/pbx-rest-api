<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Issabel version 4.0                                                  |
  | http://www.issabel.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2018 Issabel Foundation                                |
  +----------------------------------------------------------------------+
  | This program is free software: you can redistribute it and/or modify |
  | it under the terms of the GNU General Public License as published by |
  | the Free Software Foundation, either version 3 of the License, or    |
  | (at your option) any later version.                                  |
  |                                                                      |
  | This program is distributed in the hope that it will be useful,      |
  | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
  | GNU General Public License for more details.                         |
  |                                                                      |
  | You should have received a copy of the GNU General Public License    |
  | along with this program.  If not, see <http://www.gnu.org/licenses/> |
  +----------------------------------------------------------------------+
  | The Initial Developer of the Original Code is Issabel LLC            |
  +----------------------------------------------------------------------+
  $Id: ivrs.php, Tue 04 Sep 2018 09:53:35 AM EDT, nicolas@issabel.com
*/

class ivrs extends rest {

    protected $table      = "ivr_details";
    // protected $table      = "pbx_ivr";

    protected $id_field        = 'id';
    protected $name_field      = 'description';
    protected $extension_field = 'extension';
    protected $config_fields   = [];
    protected $dest_field      = 'CONCAT("pbx-ivr-",id,",s,1")';

    function __construct($f3) {

        $mgrpass    = $f3->get('MGRPASS');

        $this->ami   = new asteriskmanager();
        $this->conn  = $this->ami->connect("localhost","admin", $mgrpass);
        if(!$this->conn) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 502 Service Unavailable', true, 502);
            die();
        }

        $this->db  = $f3->get('DB');

        $this->config_fields = $this->getTableFields($this->table);

        // Use always CORS header, no matter the outcome
        $f3->set('CORS.origin','*');
        //header("Access-Control-Allow-Origin: *");

        // If not authorized it will die out with 403 Forbidden
        $localauth = new authorize();
        $localauth->authorized($f3);

        try {

            $this->data = new DB\SQL\Mapper($this->db, $this->table);

            if($this->dest_field <> '')
                $this->data->destination = $this->dest_field;

        } catch(Exception $e) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
            die();
        }

    }

    public function get($f3, $from_child = 0)
    {

        $db = $f3->get('DB');
        $rows = array();

        $query  = "SELECT * FROM {$this->table}";

        // $query .= "LEFT JOIN devices dev ON u.extension = dev.id LEFT JOIN sip ON u.extension=sip.id WHERE sip.keyword='secret' ";

        if (!$f3->get('PARAMS.id')) {

            $rows = $db->exec($query);

            foreach ($rows as $key => $item) {
                $id = $item['id'];
                $rows[$key]['entries_list'] = $this->getEntries($id, $db);
            }

        } else {

            $id = $f3->get('PARAMS.id');
            $query .= " WHERE {$this->id_field} =:id ";
            $rows = $db->exec($query, [':id' => $id]);

            if(!empty($rows[0])) {
                $rows = $rows[0];
                $rows['entries_list'] = $this->getEntries($id, $db);
            }
        }

        // final json output
        $final = array();
        $final['results'] = $rows;
        header('Content-Type: application/json;charset=utf-8');
        echo json_encode($final);
        die();
    }

    public function post($f3, $from_child = 0) {

        $db = $f3->get('DB');
        $input = json_decode($f3->get('BODY'),true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 422 Unprocessable Entity', true, 422);
            die();
        }

        if($f3->get('PARAMS.id') != '') {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
            die();
        }

        $newName = $input['name'];
        $query   = "SELECT {$this->id_field} FROM {$this->table} WHERE name='{$newName}' LIMIT 1 ";
        $rows    = $db->exec($query);

        if(!empty($rows)) {
            throw Exception('Это имя уже используется', 404);
        } else {
            $this->create_ivr($f3, $input, 'INSERT');
            $this->applyChanges($input);
        }

        // $this->applyChanges($input);
        // $loc    = $f3->get('REALM');
        // header("Location: $loc/". $EXTEN, true, 201);

        die();

    }

    public function put($f3, $from_child = 0) {

        $db = $f3->get('DB');
        $input = json_decode($f3->get('BODY'),true);
        // lg($input);

        if (json_last_error() !== JSON_ERROR_NONE) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 422 Unprocessable Entity', true, 422);
            die();
        }

//        if($f3->get('PARAMS.id') != '') {
//            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);
//            die();
//        }

        // lg($input);
//        $newName = $input['name'];
//        $query   = "SELECT {$this->id_field} FROM {$this->table} WHERE name='{$newName}' LIMIT 1 ";
//        $rows    = $db->exec($query);
//
//        if(!empty($rows)) {
//            throw Exception('Это имя уже используется', 404);
        // } else {
            $this->create_ivr($f3, $input, 'UPDATE');
        // }

        // $this->applyChanges($input);
        // $loc    = $f3->get('REALM');
        // header("Location: $loc/". $EXTEN, true, 201);

        die();

    }

    private function create_ivr($f3, $post, $method = 'INSERT') {

        $db = $f3->get('DB');
        $EXTEN = ($method=='INSERT') ? $post['name'] : $f3->get('PARAMS.id');

        $tableFields =  $this->getTableFields($this->table);

        if($method=='INSERT') {

            $resp = $this->sqlInsertPrepare($tableFields, $post);
            if(empty($resp)) {
                throw new Exception("Отсутствуют необходимые данные для создания меню", 402);
            }

            $sql   = $resp['sql'];
            $data  = $resp['data'];
            $query = "INSERT INTO {$this->table}  {$sql} ";

            try {

                $db->exec($query, $data);
                $ivrId = $db->getLastInstertId();

            } catch(\PDOException $e) {
                $errorMessage = $e->getMessage();
                throw new Exception("Не удалось создать новое голосовое меню ({$errorMessage})", 402);
            }

        } else {

            $ivrId = $EXTEN;

            $fldconfig     = array();
            $fldconfigval  = array();
            
            foreach($post as $key => $value) {

                if( ($key == 'id') || (!isset($tableFields[$key])) )
                 continue;

                $fldconfig[] = "`$key`=?";
                $fldconfigval[] = $value;
            }

            $fldconfigval[]  = $EXTEN;
            $allconfigfields = implode(",",$fldconfig);

            // Update queues_config table
            $query = 'UPDATE '.$this->table.' SET '.$allconfigfields.' WHERE id =?';
            // lg([$query, $fldconfigval]);

            try {
                $db->exec($query, $fldconfigval);
            } catch(\PDOException $e) {
                $errorMessage = $e->getMessage();
                throw new Exception("Не удалось создать новое голосовое меню ({$errorMessage})", 402);
                //header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
                //die();
            }

//            // Update queues_details table
//            $db->exec("DELETE FROM queues_details WHERE id=?",array($EXTEN));
//
//            foreach($flddetails as $keyword=>$data) {
//                $query = 'INSERT INTO queues_details (id,keyword,data) VALUES (?,?,?)';
//                try {
//                    $db->exec($query,array($EXTEN,$keyword,$data));
//                } catch(\PDOException $e) {
//                    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
//                    die();
//                }
//            }


        }

        if($ivrId) {
            $this->ivrSaveEntries($db, $ivrId, $post);
        }

    }

    protected function ivrSaveEntries($db, $ivrId, $input){

        if(empty($input['entries_list']))
            return false;

        $table   = 'ivr_entries';
        $entries = $input['entries_list'];

        $db->exec("DELETE FROM {$table} WHERE ivr_id = ?", [$ivrId]);

        $query = "INSERT INTO {$table} 
                  (ivr_id, selection, dest, ivr_ret)
                  VALUES (:ivr_id, :selection, :dest, :ivr_ret)";

        foreach ($entries as $key => $item) {
            $item['ivr_id'] = $ivrId;
            $db->exec($query, $item);
        }

        return true;
    }

    protected function getEntries($id, $db) {
        $query  = "SELECT * FROM ivr_entries WHERE ivr_id = {$id} ";
        $entries = $db->exec($query, [':ivr_id' => $id]);
        return $entries;
    }

}




/********************
foreach($vals as $key => $value) {
    $vals[$key] = $db->escapeSimple($value);
}

if ($vals['id']) {
    $sql = 'REPLACE INTO ivr_details (id, name, description, announcement,
				directdial, invalid_loops, invalid_retry_recording,
				invalid_destination, invalid_recording,
				retvm, timeout_time, timeout_recording,
				timeout_retry_recording, timeout_destination, timeout_loops,
				timeout_append_announce, invalid_append_announce, timeout_ivr_ret, invalid_ivr_ret)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $foo = $db->query($sql, $vals);
    if($db->IsError($foo)) {
        die_freepbx(print_r($vals,true).' '.$foo->getDebugInfo());
    }
} else {
    unset($vals['id']);
    $sql = 'INSERT INTO ivr_details (name, description, announcement,
				directdial, invalid_loops, invalid_retry_recording,
				invalid_destination,  invalid_recording,
				retvm, timeout_time, timeout_recording,
				timeout_retry_recording, timeout_destination, timeout_loops,
				timeout_append_announce, invalid_append_announce, timeout_ivr_ret, invalid_ivr_ret)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $foo = $db->query($sql, $vals);
    if($db->IsError($foo)) {
        die_freepbx(print_r($vals,true).' '.$foo->getDebugInfo());
    }
    $sql = ( ($amp_conf["AMPDBENGINE"]=="sqlite3") ? 'SELECT last_insert_rowid()' : 'SELECT LAST_INSERT_ID()');
    $vals['id'] = $db->getOne($sql);
    if ($db->IsError($foo)){
        die_freepbx($foo->getDebugInfo());
    }
}

return $vals['id'];
 *******************/

