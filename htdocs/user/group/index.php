<?php

/* Copyright (C) 2002-2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2011-2012 Herve Prot           <herve.prot@symeos.com>
 *
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

require '../../main.inc.php';
if (!class_exists('UserGroup'))
    require DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';

$langs->load("users");

$object = new UserGroup($db);

/*
 * View
 */

// FIXME use an ajax response file for avoid reload all classes and increase performance

if ($_GET['json'] == "list") {
    $output = array(
        "sEcho" => intval($_GET['sEcho']),
        "iTotalRecords" => 0,
        "iTotalDisplayRecords" => 0,
        "aaData" => array()
    );

    try {
        $result1 = $object->mongodb->find();
        $result2 = $user->mongodb->aggregate(array(array('$unwind' => '$roles'), array('$group' => array('_id' => '$roles', 'count' => array('$sum' => 1)))));
    } catch (Exception $exc) {
        print $exc->getMessage();
    }

    $iTotal = $object->mongodb->count();
    $output["iTotalRecords"] = $iTotal;
    $output["iTotalDisplayRecords"] = $iTotal;

    $result = array();
    if (!empty($result1)) {
        foreach ($result1 as $aRow) {
            $aRow = (object) $aRow;
            $result[$aRow->name] = $aRow;
        }
    }

    if (!empty($result2)) {
        foreach ($result2 as $aRow) {
            foreach ($aRow as $row) {
				print_r($row);
                $row = (object) $row;
                if ($row->_id) {
                    //print_r($row);
                    $result[$row->_id]->nb = $row->count;
                }
            }
        }
    }

    if (!empty($result)) {
        foreach ($result as $aRow) {
            $output["aaData"][] = $aRow;
        }
    }

    header('Content-type: application/json');
    //echo $_GET["callback"] . '(' . json_encode($output) . ');';
    echo json_encode($output);
    exit;
}

/*
 * View
 */

llxHeader();

$title = $langs->trans("ListOfGroups");

print_fiche_titre($title);
print '<div class="with-padding">';
print '<div class="columns">';
print column_start();

/*
 * Barre d'actions
 *
 */

if ($user->admin) {
    print '<p class="button-height right">';
    print '<span class="button-group">';
    print '<a class="button icon-star" href="user/group/fiche.php?action=create">' . $langs->trans("CreateGroup") . '</a>';
    print "</span>";
    print "</p>";
}


$i = 0;
$obj = new stdClass();

print '<table class="display dt_act" id="group" >';
// Ligne des titres
print'<thead>';
print'<tr>';
print'<th class="essential">';
print $langs->trans("Group");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "name";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->fnRender = 'function(obj) {
	var ar = [];
	ar[ar.length] = "<img src=\"img/ico/icSw2/' . $object->fk_extrafields->ico . '\" border=\"0\" alt=\"' . $langs->trans("See " . get_class($object)) . ' : ";
	ar[ar.length] = obj.aData.name.toString();
	ar[ar.length] = "\" title=\"' . $langs->trans("See " . get_class($object)) . ' : ";
	ar[ar.length] = obj.aData.name.toString();
	ar[ar.length] = "\"></a> <a href=\"' . DOL_URL_ROOT . '/user/group/fiche.php?id=";
	ar[ar.length] = obj.aData._id.toString();
	ar[ar.length] = "\">";
	ar[ar.length] = obj.aData.name.toString();
	ar[ar.length] = "</a>";
	var str = ar.join("");
	return str;
}';
$i++;
print'<th class="essential">';
print $langs->trans('NbUsers');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "nb";
$obj->aoColumns[$i]->sDefaultContent = 0;
$obj->aoColumns[$i]->sClass = "fright";
$i++;
print'</tr>';
print'</thead>';
print'<tfoot>';
print'</tfoot>';
print'<tbody>';
print'</tbody>';

print "</table>";

$obj->sDom = 'l<fr>t<\"clear\"rtip>';
$obj->sAjaxSource = $_SERVER['PHP_SELF'] . '?json=list';

$obj->aaSorting = array(array(0, "asc"));

$object->datatablesCreate($obj, "group", true);



print column_end();
print '<div>';

llxFooter();
?>