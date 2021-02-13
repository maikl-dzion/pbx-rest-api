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
  $Id: trunks.php, Tue 04 Sep 2018 09:54:59 AM EDT, nicolas@issabel.com
*/

class trunks extends rest {

    protected $table      = "trunks";
    protected $id_field   = 'trunkid';
    protected $name_field = 'name';
    protected $dest_field = "";
    protected $list_fields = array('tech','channelid');
    protected $extension_field = '';

    protected $field_map = array(
        'tech'               => 'tech',
        'channelid'          => 'trunk_name',
        'usercontext'        => 'user_context',
        'maxchans'           => 'maximum_channels',
        'outcid'             => 'outbound_callerid',
        'dialoutprefix'      => 'dialout_prefix',
        'continue'           => 'continue_if_busy',
    );

    public function post($f3, $from_child = 0) {

        $db = $f3->get('DB');
        $input = json_decode($f3->get('BODY'),true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ошибка при получении данных', 422);
        }

        $lastId = 1;
        $last = $this->db->exec("SELECT $this->id_field FROM {$this->table} ORDER BY {$this->id_field} DESC LIMIT 1");
        if(!empty($last[0][$this->id_field])) {
            $lastId = $last[0][$this->id_field];
            $lastId++;
        }

        $fields = $this->field_map;
        $fields[$this->id_field]   = '';
        $fields[$this->name_field] = '';
        $input[$this->id_field] = $lastId;

        $resp = $this->sqlInsertPrepare($fields, $input);

        if(!empty($resp)) {

            $sql = $resp['sql'];
            $data = $resp['data'];

            $query = "INSERT INTO {$this->table}  {$sql} ";
            // lg([$query, $data]);

            $db->exec($query, $data);

        } else {

        }

        // $this->applyChanges($input);
        // $this->applyChanges($input);
        // $loc    = $f3->get('REALM');
        // header("Location: $loc/". $EXTEN, true, 201);

        die();

    }

}


//        $sql = "
//		INSERT INTO `trunks`
//		  (`trunkid`,
//		   `name`,
//		   `tech`,
//		   `outcid`,
//		   `keepcid`,
//		   `maxchans`,
//		   `failscript`,
//		   `dialoutprefix`,
//		   `channelid`,
//		   `usercontext`,
//		   `provider`,
//		   `disabled`,
//		   `continue`)
//		VALUES (
//			'$trunknum',
//			'" . $db->escapeSimple($name) . "',
//			'" . $db->escapeSimple($tech) . "',
//			'" . $db->escapeSimple($outcid) . "',
//			'" . $db->escapeSimple($keepcid) . "',
//			'" . $db->escapeSimple($maxchans) . "',
//			'" . $db->escapeSimple($failtrunk) . "',
//			'" . $db->escapeSimple($dialoutprefix) . "',
//			'" . $db->escapeSimple($channelid) . "',
//			'" . $db->escapeSimple($usercontext) . "',
//			'" . $db->escapeSimple($provider) . "',
//			'" . $db->escapeSimple($disabletrunk) . "',
//			'" . $db->escapeSimple($continue) . "'
//		)";