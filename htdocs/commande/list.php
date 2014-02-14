<?php

/* Copyright (C) 2001-2005 Rodolphe Quiedeville   <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur    <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo  <marc@ocebo.com>
 * Copyright (C) 2005-2012 Regis Houssin          <regis.houssin@capnetworks.com>
 * Copyright (C) 2012      Juanjo Menent          <jmenent@2byte.es>
 * Copyright (C) 2012      David Moothen          <dmoothen@websitti.fr>
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


require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

$langs->load('orders');
$langs->load('deliveries');
$langs->load('companies');

$object = new Commande($db);
$societe = new Societe($db);

/*
 * View
 */

$title = $langs->trans('Orders');
llxHeader('', $title);
print_fiche_titre($title);
/* ?>
  <div class="dashboard">
  <div class="columns">
  <div class="four-columns twelve-columns-mobile graph">
  <?php //$object->graphPieStatus(); ?>
  </div>

  <div class="eight-columns twelve-columns-mobile new-row-mobile graph">
  <?php //$object->graphBarStatus(); ?>
  </div>
  </div>
  </div>
  <?php */
print '<div class="with-padding" >';

/*
 * Barre d'actions
 *
 */

if ($user->rights->commande->creer) {
	print '<p class="button-height right">';
	print '<span class="button-group">';
	print '<a class="button icon-star" href="commande/commande.php?action=create">' . $langs->trans("NewOrder") . '</a>';
	print "</span>";
	print "</p>";
}


$i = 0;
$obj = new stdClass();

//print $object->datatablesEdit("listorders", $langs->trans("NewOrder"));

print '<table class="display dt_act" id="listorders" >';
// Ligne des titres
print'<thead>';
print'<tr>';
print'<th>';
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "_id";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = false;
$obj->aoColumns[$i]->bVisible = false;
$i++;
print'<th class="essential">';
print $langs->trans("Ref");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "ref";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("ref", "url");
$i++;
print'<th class="essential">';
print $langs->trans('Company');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "client.name";
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->fnRender = $societe->datatablesFnRender("client.name", "url", array('id' => "client.id"));
$i++;
print'<th class="essential">';
print $langs->trans("RefCustomer");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "ref_client";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->editable = true;
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;

$contact = new Contact();
print'<th class="essential">';
print $langs->trans('Contact');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "contact.name";
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->fnRender = $contact->datatablesFnRender("contact.name", "url", array('id' => "contact.id"));
$i++;
print'<th class="essential">';
print $langs->trans('Date');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "date_livraison";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->sDefaultContent = "";
//$obj->aoColumns[$i]->sType = "date";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("date_livraison", "datetime");
$obj->aoColumns[$i]->editable = true;
$i++;
print'<th class="essential">';
print $langs->trans('AmountHT');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "total_ht";
$obj->aoColumns[$i]->sClass = "fright";
$obj->aoColumns[$i]->sDefaultContent = "";
//$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("total_ht", "price");
//$obj->aoColumns[$i]->editable = true;
$i++;
//print'<th class="essential">';
//print $langs->trans('Contact');
//print'</th>';
//$obj->aoColumns[$i]->mDataProp = "contact.name";
//$obj->aoColumns[$i]->sDefaultContent = "";
//$obj->aoColumns[$i]->fnRender = $contact->datatablesFnRender("contact.name", "url", array('id' => "contact.id"));
//$i++;
//print'<th class="essential">';
//  print $langs->trans('Author');
//  print'</th>';
//  $obj->aoColumns[$i] = new stdClass();
//  $obj->aoColumns[$i]->mDataProp = "author";
//  $obj->aoColumns[$i]->sDefaultContent = "";
//  $obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("author.name", "url", array('id' => "author.id"));
//  $i++;
print'<th class="essential">';
print $langs->trans("Status");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "Status";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->sDefaultContent = "DRAFT";
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("Status", "status");
$obj->aoColumns[$i]->editable = true;
$i++;
print'<th class="essential">';
print $langs->trans('Action');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "";
$obj->aoColumns[$i]->sClass = "center content_actions";
$obj->aoColumns[$i]->sWidth = "60px";
$obj->aoColumns[$i]->bSortable = false;
$obj->aoColumns[$i]->sDefaultContent = "";

$url = "commande/fiche.php";
$obj->aoColumns[$i]->fnRender = 'function(obj) {
  var ar = [];
  ar[ar.length] = "<a href=\"' . $url . '?id=";
  ar[ar.length] = obj.aData._id.toString();
  ar[ar.length] = "&action=edit&backtopage=' . $_SERVER['PHP_SELF'] . '\" class=\"sepV_a\" title=\"' . $langs->trans("Edit") . '\"><img src=\"img/action_edit.png\" alt=\"\" /></a>";';
if ($user->rights->commande->supprimer)
	$obj->aoColumns[$i]->fnRender.='ar[ar.length] = "<a href=\"\"";
  ar[ar.length] = " class=\"delEnqBtn\" title=\"' . $langs->trans("Delete") . '\"><img src=\"/img/action_delete.png\" alt=\"\" /></a>";
  var str = ar.join("");
  return str;
  }';
else
	$obj->aoColumns[$i]->fnRender.='var str = ar.join("");
  return str;
  }';

print'</tr>';
print'</thead>';
print'<tfoot>';
/* input search view */
$i = 0; //Doesn't work with bServerSide
print'<tr>';
print'<th id="' . $i . '"></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Ref") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Company") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search RefCustomer") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Date") . '" /></th>';
$i++;
//print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search DateEnd") . '" /></th>';
//$i++;
//print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search author") . '" /></th>';
//$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Status") . '" /></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
print'</tr>';
print'</tfoot>';
print'<tbody>';
print'</tbody>';

print "</table>";

$obj->aaSorting = array(array(5, 'desc'));
//$obj->bServerSide = true;
//if ($all) {
//    if ($type == "DONE")
//        $obj->sAjaxSource = "core/ajax/listdatatables.php?json=actionsDONE&class=" . get_class($object);
//    else
//        $obj->sAjaxSource = "core/ajax/listdatatables.php?json=actionsTODO&class=" . get_class($object);
//} else {
//    if ($type == "DONE")
//        $obj->sAjaxSource = $_SERVER["PHP_SELF"] . "?json=listDONEByUser";
//    else
//        $obj->sAjaxSource = $_SERVER["PHP_SELF"] . "?json=listTODOByUser";
//
  //}
//$obj->sAjaxSource = $_SERVER["PHP_SELF"] . "?json=list";


$query = "";
$query = '[{name :"class",value:"' . get_class($object) . '"},
	{"name": "query", "value": "{\"entity\":\"'.$user->entity.'\"}"}]';

$obj->aoAjaxData = $query;

$object->datatablesCreate($obj, "listorders", true, true);

//echo $object->showList($query, array(5,'desc'));

llxFooter();
?>