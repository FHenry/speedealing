<?php

/* Copyright (C) 2012      Herve Prot               <herve.prot@symeos.com>
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

define('NOREQUIREUSER', 1);
define('NOLOGIN', 1);
//define('NOREQUIRECONF', 1);
define('NOREQUIRESOC', 1);

require_once "../../../main.inc.php";
require_once DOL_DOCUMENT_ROOT . "/core/class/html.formother.class.php";
$langs->load("companies");
$langs->load("customers");
$langs->load("suppliers");
$langs->load("commercial");
/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */

$flush = $_GET["flush"];
if ($flush) {
	// reset old value

	$dir = DOL_DOCUMENT_ROOT . "/langs";

	$files1 = scandir($dir);

	//print_r($files1);



	foreach ($files1 as $aRow) {
		if ($aRow != "." && $aRow != ".." && is_dir($dir . "/" . $aRow)) {
			$dir_lang = scandir($dir . "/" . $aRow);
			foreach ($dir_lang as $row) {
				if ($row != "." && $row != "..") {
					include $dir . "/" . $aRow . "/" . $row;
					$name = substr($row, 0, strpos($row, '.'));
					//print json_encode($$name);
					// get session to nodejs server
					$opts = array(
						'http' => array(
							'method' => 'POST',
							'header' => "Content-type: application/json\r\n" .
							"Cookie: SpeedSession=" . $_COOKIE["SpeedSession"] . "\r\n",
							'content' => json_encode($$name)
						)
					);


					$context = stream_context_create($opts);

					$dir_new = str_replace("_", "-", $aRow); 
					//Utilisation du contexte dans l'appel
					$session = json_decode(file_get_contents(
									'http://' . $conf->nodejs->host . ":" . $conf->nodejs->port . '/migrate/langs?lang=' . $dir_new . "&file=" . $name, false, $context));
				}
			}
		}
	}

	exit;

	foreach ($result->rows AS $aRow) {
		$value = $aRow->value;

		//print_r($aRow);exit;

		if (isset($value->class)) {

			//correct class
			if ($value->class == 'extrafields')
				$value->class = 'ExtraFields';
			if ($value->class == 'system')
				$value->class = 'Conf';


			// Get the class collection
			$class = $value->class;
			$c_class = $mongodb->$class;

			// _id
			if ($class != 'User' && $class != 'ExtraFields' && $class != 'Conf' && $class != 'Dict' && $class != 'menu' && $class != 'UserGroup' && $class != 'DolibarrModules') {
				unset($value->_id);
			}
			//$value->id = $value->_id;
			//clean specific value for couchdb
			unset($value->class);
			unset($value->_rev);
			unset($value->_deleted_conflicts);
			unset($value->_conflicts);



			// Insert this new document into mongo class collection
			$c_class->save($value);
			unset($c_class);
			$i++;
		}
	}

	//print_r($result);

	print "Migration couchdb vers mongoDB terminée : " . $i;
}
?>