<?php

/* Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Eric Seigne          <erics@rycks.com>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
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

/**
 * 	    \file       htdocs/contact/list.php
 *      \ingroup    societe
 * 		\brief      Page to list all contacts
 */
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

$langs->load("companies");
$langs->load("suppliers");

// Security check
$contactid = GETPOST('id', 'alpha');
if ($user->societe_id)
	$socid = $user->societe_id;
$result = restrictedArea($user, 'contact', $contactid, '');

$type = GETPOST("type");
$view = GETPOST("view");

$sall = GETPOST("contactname");
$userid = GETPOST('userid', 'int');

$langs->load("companies");
$titre = (!empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("ListOfContacts") : $langs->trans("ListOfContactsAddresses"));
if ($type == "c") {
	$titre.='  (' . $langs->trans("ThirdPartyCustomers") . ')';
	$urlfiche = "fiche.php";
} else if ($type == "p") {
	$titre.='  (' . $langs->trans("ThirdPartyProspects") . ')';
	$urlfiche = "prospect/fiche.php";
} else if ($type == "f") {
	$titre.=' (' . $langs->trans("ThirdPartySuppliers") . ')';
	$urlfiche = "fiche.php";
} else if ($type == "o") {
	$titre.=' (' . $langs->trans("OthersNotLinkedToThirdParty") . ')';
	$urlfiche = "";
}

$object = new Contact($db);
$soc = new Societe($db);

/*
 * View
 */

$title = (!empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("Contacts") : $langs->trans("ContactsAddresses"));
llxHeader('', $title, 'EN:Module_Third_Parties|FR:Module_Tiers|ES:M&oacute;dulo_Empresas');

print_fiche_titre($title);
print '<div class="with-padding">';

/*
 * Barre d'actions
 *
 */

print '<p class="button-height right">';
print '<a class="button icon-star" href="' . strtolower(get_class($object)) . '/fiche.php?action=create">' . $langs->trans("NewContact") . '</a>';
print "</p>";

$i = 0;
$obj = new stdClass();
print '<table class="display dt_act" id="list_contacts" >';
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
print $langs->trans("Lastname");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "name";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("name", "url");
$i++;
/* print'<th class="essential">';
  print $langs->trans("Firstname");
  print'</th>';
  $obj->aoColumns[$i]->mDataProp = "firstname";
  $obj->aoColumns[$i]->bUseRendered = false;
  $obj->aoColumns[$i]->bSearchable = true;
  $obj->aoColumns[$i]->sDefaultContent = "";
  $i++; */
print'<th class="essential">';
print $langs->trans("PostOrFunction");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "poste";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans('Company');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "societe";
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->fnRender = $soc->datatablesFnRender("societe.name", "url", array('id' => "societe.id"));
$i++;
print'<th class="essential">';
print $langs->trans('Phone');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "phone_pro";
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans('Mobile');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "phone_mobile";
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
print'<th class="essential">';
print $langs->trans('EMail');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "email";
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("email", "email");
$i++;
/* print'<th class="essential">';
  print $langs->trans('DateModificationShort');
  print'</th>';
  $obj->aoColumns[$i]->mDataProp = "tms";
  $obj->aoColumns[$i]->sClass = "center";
  $obj->aoColumns[$i]->sDefaultContent = "";
  $obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("tms", "date");
  //$obj->aoColumns[$i]->sClass = "edit";
  $i++; */
print'<th class="essential">';
print $langs->trans('Categories');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "Tag";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("Tag", "tag");
$i++;
print'<th class="essential">';
print $langs->trans("Status");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "Status";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->sWidth = "100px";
$obj->aoColumns[$i]->editable = true;
$obj->aoColumns[$i]->sDefaultContent = $object->fk_extrafields->fields->Status->default;
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("Status", "status");
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

$url = "contact/fiche.php";
$obj->aoColumns[$i]->fnRender = 'function(obj) {
	var ar = [];
	ar[ar.length] = "<a href=\"' . $url . '?id=";
	ar[ar.length] = obj.aData._id.toString();
	ar[ar.length] = "&action=edit&backtopage=' . $_SERVER['PHP_SELF'] . '\" class=\"sepV_a\" title=\"' . $langs->trans("Edit") . '\"><img src=\"img/action_edit.png\" alt=\"\" /></a>";
	var str = ar.join("");
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
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Name") . '" /></th>';
$i++;
//print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Firstname") . '" /></th>';
//$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Poste") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Company") . '" /></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
/* print'<th id="' . $i . '"></th>';
  $i++; */
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Category") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Status") . '" /></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
print'</tr>';
print'</tfoot>';
print'<tbody>';
print'</tbody>';

print "</table>";

//$obj->bServerSide = true;
if ($_GET["disable"])
	//$obj->sAjaxSource = "core/ajax/listdatatables.php?json=listDisable&class=" . get_class($object);
	$obj->aoAjaxData = '[{name :"class",value:"'. get_class($object).'"},
			{"name": "query", "value": "{\"$or\":[{\"Status\": \"ST_DISABLE\"},{\"Status\": \"ST_NEVER\"}]}"}]';
else
	$obj->aoAjaxData = '[{name :"class",value:"'. get_class($object).'"},
			{"name": "query", "value": "{\"Status\": \"ST_ENABLE\"}"}]';

/*if (!$user->rights->societe->client->voir)
	$obj->aoAjaxData = '[{name :"class",value:"'. get_class($object).'"},
			{"name": "query", "value": "{\"Status\":{\"$ne\":\"DONE\"},\"$or\":[{\"usertodo.id\":\"'.$user->id.'\"},{\"author.id\":\"'.$user->id.'\"}]}"}]';
	$obj->sAjaxSource = $_SERVER["PHP_SELF"] . "?json=list&class=" . get_class($object) . "&key=" . $user->id . "&disable=" . ($_GET["disable"] ? "true" : "false");
*/
$object->datatablesCreate($obj, "list_contacts", true, true);

print '</div>'; // end

llxFooter();
?>