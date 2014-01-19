<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Simon TOSSER         <simon@kornog-computing.com>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2010      Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2010-2012 Herve Prot           <herve.prot@symeos.com>
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

// For best performance, it's preferred to use "class_exists + require" instead "require_once"

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/agenda/lib/agenda.lib.php';
if (!class_exists('Agenda'))
	require DOL_DOCUMENT_ROOT . '/agenda/class/agenda.class.php';
if (!class_exists('Contact'))
	require DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
if (!class_exists('User'))
	require DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
if (!class_exists('FormActions'))
	require DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
if (!empty($conf->propal->enabled) && !class_exists('Propal'))
	require DOL_DOCUMENT_ROOT . '/propal/class/propal.class.php';
if (!empty($conf->project->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/core/lib/project.lib.php';
	if (!class_exists('User'))
		require DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
}
if (!empty($conf->lead->enabled)) {
	dol_include_once("/lead/class/lead.class.php");
	dol_include_once("/lead/lib/lead.lib.php");
}


$langs->load("companies");
$langs->load("commercial");
$langs->load("other");
$langs->load("bills");
$langs->load("orders");
$langs->load("agenda");

$action = GETPOST('action', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$contactid = GETPOST('contactid', 'alpha');

// Security check
$socid = GETPOST('socid', 'alpha');
$id = GETPOST('id', 'alpha');
if ($id)
	$id = new MongoId($id);

if ($user->societe_id)
	$socid = $user->societe_id;

if ($socid)
	$socid = new MongoId($socid);

if ($contactid)
	$contactid = new MongoId($contactid);

$result = restrictedArea($user, 'agenda', $id, 'actioncomm', 'myactions', '', 'id');

$error = GETPOST("error");
$mesg = '';

$object = new Agenda($db);
$contact = new Contact($db);
//var_dump($_POST);


/*
 * Action creation de l'action
 */
if ($action == 'add_action') {
	$error = 0;

	/* 	if (empty($backtopage)) {
	  $backtopage = DOL_URL_ROOT . '/agenda/index.php';
	  } */

	if ($cancel) {
		if ($backtopage)
			header("Location: " . $backtopage);
		else
			Header("Location: " . DOL_URL_ROOT . '/agenda/list.php?idmenu=menu:myagendaListTODO');
		exit;
	}

	$fulldayevent = $_POST["fullday"];

	// Clean parameters
	//$datep = dol_mktime($fulldayevent ? '00' : $_POST["aphour"], $fulldayevent ? '00' : $_POST["apmin"], 0, $_POST["apmonth"], $_POST["apday"], $_POST["apyear"]);
	//$datef = dol_mktime($fulldayevent ? '23' : $_POST["p2hour"], $fulldayevent ? '59' : $_POST["p2min"], $fulldayevent ? '59' : '0', $_POST["p2month"], $_POST["p2day"], $_POST["p2year"]);

	$datep = date("c", strtotime(str_replace("/", "-", $_POST["datep"])));
	$datef = date("c", strtotime(str_replace("/", "-", $_POST["datef"])));

	/*
	  echo '<pre>'.print_r($datep, true).'</pre>';
	  echo '<pre>'.print_r($datef, true).'</pre>';
	  die();
	 */
	// Check parameters
	if (!$datef && $_POST["percentage"] == 100) {
		$error++;
		$action = 'create';
		$mesg = '<div class="error">' . $langs->trans("ErrorFieldRequired", $langs->trans("DateEnd")) . '</div>';
	}

	// Initialisation objet cactioncomm
	if (!$_POST["actioncode"]) {
		$error++;
		$action = 'create';
		$mesg = '<div class="error">' . $langs->trans("ErrorFieldRequired", $langs->trans("Type")) . '</div>';
	} else {
		//$result = $cactioncomm->fetch($_POST["actioncode"]);
		$object->type_code = $_POST["actioncode"];
	}
	/*
	  if ($cactioncomm->type == 2) { //ACTION
	  if ($_POST["percentage"] == 100)
	  $datef = dol_now();
	  else
	  $datef = '';
	  } */

	// Check parameters
	if (!$datef && $_POST["percentage"] == 100) {
		$error = 1;
		$action = 'create';
		$mesg = '<div class="error">' . $langs->trans("ErrorFieldRequired", $langs->trans("DateEnd")) . '</div>';
	}

	// Initialisation objet actioncomm
	$object->type_id = null;
	$object->type_code = $_POST["actioncode"];
	$object->fulldayevent = $_POST["fullday"] ? 1 : 0;
	$object->location = isset($_POST["location"]) ? $_POST["location"] : '';
	$object->label = trim($_POST["label"]);
	if (!$_POST["label"]) {
		if ($_POST["actioncode"] == 'AC_RDV' && $contact->getFullName($langs)) {
			$object->label = $langs->transnoentitiesnoconv("TaskRDVWith", $contact->getFullName($langs));
		} else {
			if ($langs->trans("Action" . $object->type_code) != "Action" . $object->type_code) {
				$object->label = $langs->transnoentitiesnoconv("Action" . $object->type_code) . "\n";
			}
			else
				$object->label = $cactioncomm->libelle;
		}
	}
	$object->fk_project = isset($_POST["projectid"]) ? $_POST["projectid"] : 0;
	$object->fk_lead = isset($_POST["leadid"]) ? $_POST["leadid"] : 0;
	$object->propalrowid = isset($_POST["propalid"]) ? $_POST["propalid"] : 0;
	$object->type = $cactioncomm->type;
	$object->fk_task = isset($_POST["fk_task"]) ? $_POST["fk_task"] : 0;
	$object->datep = $datep;
	$object->datef = $datef;
	$object->Status = GETPOST('status');
	if ($object->type_code == "AC_RDV") //ACTION
		$object->durationp = $object->datef - $object->datep;
	else {
		$object->durationp = strtotime('1970-01-01 ' . $_POST["durationp"] . ":00" . 'GMT');
		$object->datef = date("c", strtotime($object->datep) + $object->durationp);
	}

	/*
	  if ($cactioncomm->type == 1) { //RDV
	  // RDV
	  if ($object->datef && $object->datef < dol_now('tzref')) {
	  $object->percentage = 100;
	  } else {
	  $object->percentage = 0;
	  }
	  } else {
	  $object->percentage = isset($_POST["percentage"]) ? $_POST["percentage"] : 0;
	  }
	  $object->duree = (($_POST["dureehour"] * 60) + $_POST["dureemin"]) * 60;
	 */
	$object->author->id = $user->id;
	$object->author->name = $user->name;
	$object->usermod = null;

	//$object->usertodo = $_POST["affectedto"];

	$object->usertodo = new stdClass();
	if (strlen($_POST["affectedto"]) > 0)
		$object->usertodo->id = GETPOST("affectedto");

	/*
	  $userdone = new User($db);
	  if ($_POST["doneby"] > 0) {
	  $userdone->fetch($_POST["doneby"]);
	  }
	  $object->userdone = $userdone;
	 *
	 */

	if (strlen($_POST["doneby"]) > 0)
		$object->userdone->id = GETPOST("doneby");

	$object->notes = array();
	$object->notes[0] = new stdClass();
	$object->notes[0]->title = "Notes";
	$object->notes[0]->edit = true;
	$object->notes[0]->note = trim($_POST["note"]);

	$object->contact = new stdClass();

	$object->societe = new stdClass();
	if ($socid) {
		$object->societe->id = $socid;
	}

	// Check parameters
	if ($cactioncomm->type == 1 && ($datef == '')) { //RDV
		$error++;
		$action = 'create';
		$mesg = '<div class="error">' . $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("DateEnd")) . '</div>';
	}
	/*
	  if ($datea && $_POST["percentage"] == 0) {
	  $error++;
	  $action = 'create';
	  $mesg = '<div class="error">' . $langs->trans("ErrorStatusCantBeZeroIfStarted") . '</div>';
	  }
	 */
	if (!$_POST["datep"] && !$_POST["datef"]) {
		$error++;
		$action = 'create';
		$mesg = '<div class="error">' . $langs->trans("ErrorFieldRequired", $langs->transnoentitiesnoconv("Date")) . '</div>';
	}

	//echo '<pre>'.print_r($_POST, true).'</pre>';
	//echo '<pre>'.print_r($object, true).'</pre>';
	//die();

	if (!$error) {

		// On cree l'action
		$idaction = $object->update($user);

		if (!empty($idaction)) {
			if (!$object->error) {
				if (!empty($backtopage)) {
					dol_syslog("Back to " . $backtopage);
					Header("Location: " . $backtopage);
				}
				else
				//Header("Location: " . DOL_URL_ROOT . '/agenda/fiche.php?id=' . $idaction);
					Header("Location: " . DOL_URL_ROOT . '/agenda/list.php?idmenu=menu:myagendaListTODO');
				exit;
			} else {
				// Si erreur
				$id = $idaction;
				$langs->load("errors");
				$error = $langs->trans($object->error);
			}
		}
	}
}

/*
 * Action cloturer l'action
 */
if (GETPOST("action") == 'close') {
	$object->load($id);

	if ($user->rights->agenda->myactions->write || $user->rights->agenda->allactions->write) {
		$object->Status = "DONE";
		$object->percentage = 100;
		$object->set("Status", "DONE");
		$object->set("percentage", 100);
		$object->set("userdone", array('id' => $user->id, 'name' => $user->name));

		Header("Location: " . DOL_URL_ROOT . '/agenda/fiche.php?id=' . $id);
		exit;
	}
}

/*
 * Action suppression de l'action
 */
if ($action == 'confirm_delete' && GETPOST("confirm") == 'yes') {
	$object->fetch($id);

	if ($user->rights->agenda->myactions->delete || $user->rights->agenda->allactions->delete) {
		$result = $object->delete();

		if ($result >= 0) {
			Header("Location: /agenda/listactions.php");
			exit;
		} else {
			$mesg = $object->error;
		}
	}
}

/*
 * Action mise a jour de l'action
 */
if ($action == 'update') {
	if (!$_POST["cancel"]) {
		$fulldayevent = $_POST["fullday"];

		// Clean parameters
		if ($_POST["aphour"] == -1)
			$_POST["aphour"] = '0';
		if ($_POST["apmin"] == -1)
			$_POST["apmin"] = '0';
		if ($_POST["p2hour"] == -1)
			$_POST["p2hour"] = '0';
		if ($_POST["p2min"] == -1)
			$_POST["p2min"] = '0';
		//if ($_POST["adhour"] == -1) $_POST["adhour"]='0';
		//if ($_POST["admin"] == -1) $_POST["admin"]='0';

		$object->fetch($id);

		//$datep = dol_mktime($fulldayevent ? '00' : $_POST["aphour"], $fulldayevent ? '00' : $_POST["apmin"], 0, $_POST["apmonth"], $_POST["apday"], $_POST["apyear"]);
		//$datef = dol_mktime($fulldayevent ? '23' : $_POST["p2hour"], $fulldayevent ? '59' : $_POST["p2min"], $fulldayevent ? '59' : '0', $_POST["p2month"], $_POST["p2day"], $_POST["p2year"]);

		$object->label = $_POST["label"];

		$object->datep = date("c", strtotime(str_replace("/", "-", $_POST["datep"])));
		$object->datef = date("c", strtotime(str_replace("/", "-", $_POST["datef"])));
		//$object->date        = $datea;
		//$object->dateend     = $datea2;
		$object->percentage = $_POST["percentage"];
		$object->fulldayevent = $_POST["fullday"] ? 1 : 0;
		$object->location = isset($_POST["location"]) ? $_POST["location"] : '';
		$object->fk_project = $_POST["projectid"];
		$object->fk_lead = $_POST["leadid"];
		$object->propalrowid = $_POST["propalid"];
		$object->notes = array();
		$object->notes[0] = new stdClass();
		$object->notes[0]->title = "Notes";
		$object->notes[0]->edit = true;
		$object->notes[0]->note = trim($_POST["note"]);
		$object->fk_task = $_POST["fk_task"];
		//$object->type = $cactioncomm->type;
		$object->Status = $_POST["status"];
		if ($object->type_code == "AC_RDV") //ACTION
			$object->durationp = $object->datef - $object->datep;
		else {
			$object->durationp = strtotime('1970-01-01 ' . $_POST["durationp"] . ":00" . 'GMT');
			$object->datef = date("c", strtotime($object->datep) + $object->durationp);
		}

		/*
		  if ($object->type == 2) //ACTION
		  $object->durationp = !empty($_POST["duration"]) ? $_POST["duration"] * 3600 : 3600;

		  if ($object->type == 1 && !$datef && $object->percentage == 100) {
		  $error = $langs->trans("ErrorFieldRequired", $langs->trans("DateEnd"));
		  $action = 'edit';
		  }
		 */
		// Users
		//$object->usertodo = $_POST["affectedto"];

		$object->usertodo = new stdClass();
		if (strlen($_POST["affectedto"]) > 0)
			$object->usertodo->id = GETPOST("affectedto");

		$object->userdone = new stdClass();
		$object->userdone->id = $_POST["doneby"];

		$object->contact = new stdClass();
		if ($contactid) {
			$object->contact->id = $contactid;
		}

		$object->societe = new stdClass();

		if ($socid) {
			$object->societe->id = $socid;
		}

		//debug($object);
		//die();

		if (!$error) {
			$result = $object->update($user);

			if ($result < 0) {
				$langs->load("errors");
				$error = $langs->trans($object->error);
				$action = 'edit';
			}
		}
	}

	if ($result < 0) {
		$langs->load("errors");
		$mesg = '<div class="error">' . $langs->trans($object->error) . '</div>';
	} else {
		if (!empty($backtopage))
			header("Location: " . $backtopage);
		else
			Header("Location: " . DOL_URL_ROOT . '/agenda/list.php?idmenu=menu:myagendaListTODO');
		exit;
	}
}


/*
 * View
 */


llxHeader('', $langs->trans("Agenda"));


$form = new Form($db);
$htmlactions = new FormActions($db);


if ($action == 'create') {

	$contact = new Contact($db);

	if (GETPOST("contactid")) {
		$result = $contact->fetch(GETPOST("contactid"));
		if ($result < 0)
			dol_print_error($db, $contact->error);
	}

	$object->fk_task = GETPOST("fk_task") ? GETPOST("fk_task") : 0;

	if (GETPOST("actioncode") == 'AC_RDV')
		$title = $langs->trans("AddActionRendezVous");
	else
		$title = $langs->trans("AddAnAction");

	print_fiche_titre($title);
	print '<div class="with-padding">';
	print '<div class="columns">';

	print column_start();
	//print start_box($title, "twelve", $object->fk_extrafields->ico, false);

	print "\n" . '<script type="text/javascript" language="javascript">';
	print 'jQuery(document).ready(function () {
                     function setdatefields()
                     {
                            if (jQuery("#fullday:checked").val() == null)
                            {
                                jQuery(".fulldaystarthour").attr(\'disabled\', false);
                                jQuery(".fulldaystartmin").attr(\'disabled\', false);
                                jQuery(".fulldayendhour").attr(\'disabled\', false);
                                jQuery(".fulldayendmin").attr(\'disabled\', false);
                            }
                            else
                            {
                                jQuery(".fulldaystarthour").attr(\'disabled\', true);
                                jQuery(".fulldaystartmin").attr(\'disabled\', true);
                                jQuery(".fulldayendhour").attr(\'disabled\', true);
                                jQuery(".fulldayendmin").attr(\'disabled\', true);
                                jQuery(".fulldaystarthour").val("00");
                                jQuery(".fulldaystartmin").val("00");
                                //jQuery(".fulldayendhour").val("00");
                                //jQuery(".fulldayendmin").val("00");
                                jQuery(".fulldayendhour").val("23");
                                jQuery(".fulldayendmin").val("59");
                        }
                    }
                    setdatefields();
                    jQuery("#fullday").change(function() {
                        setdatefields();
                    });
                    jQuery("#selectcomplete").change(function() {
                        if (jQuery("#selectcomplete").val() == 100)
                        {
                            if (jQuery("#doneby").val() <= 0) jQuery("#doneby").val(\'' . $user->id . '\');
                        }
                        if (jQuery("#selectcomplete").val() == 0)
                        {
                            jQuery("#doneby").val(-1);
                        }
                   });
                   jQuery("#actioncode").change(function() {
                        if (jQuery("#actioncode").val() == \'AC_RDV\') jQuery("#dateend").addClass("fieldrequired");
                        else jQuery("#dateend").removeClass("fieldrequired");
                   });
               })';
	print '</script>' . "\n";
	print "\n" . '<script type="text/javascript" language="javascript">';
	print 'jQuery(document).ready(function () {
                     function settype()
                     {
                            var typeselected=jQuery("#actioncode option:selected").val();
                            var type=typeselected.split("_");
                            if (type[1] == "RDV")
                            {
                                $("#jqfullday").css("display","table-row");
                                $("#jqech").hide();
                                $("#jqstart").show();
                                $("#jqend").css("display","table-row");
                                $("#jqloc").css("display","table-row");
                                $(".fulldaystarthour").show();
                                $(".fulldaystartmin").show();
                                $(".fulldayendhour").show();
                                $(".fulldayendmin").show();
                                jQuery(".fulldaystartmin").val("00");
                                jQuery(".fulldayendmin").val("00");
                                $("#jqduration").hide();
                            }
                            else
                            {
                                $("#jqfullday").css("display","none");
                                $("#jqech").show();
                                $("#jqstart").hide();
                                $("#jqend").css("display","none");
                                $("#jqloc").css("display","none");
                                $(".fulldayendhour").hide();
                                $(".fulldayendmin").hide();
                                jQuery(".fulldaystartmin").val("00");
                                $("#jqduration").show();
                            }
                    }
                    settype();
                    jQuery("#actioncode").change(function() {
                        settype();
                    });
               })';
	print '</script>' . "\n";
	/*  print "\n".'<script type="text/javascript" language="javascript">';
	  print 'jQuery(document).ready(function () {
	  function setday()
	  {
	  if ($("#ap").val()!="")
	  {
	  $("#p2").val($("#ap").val());
	  }
	  }
	  setday();
	  jQuery("#p2").click(function() {
	  setday();
	  });
	  })';
	  print '</script>'."\n";
	  print "\n".'<script type="text/javascript" language="javascript">';
	  print 'jQuery(document).ready(function () {
	  function sethour()
	  {
	  var hour=parseInt($(".fulldaystarthour option:selected").val());
	  hour=hour+1;
	  var strhour=hour.toString(10)
	  if(strhour.length==1)
	  $(".fulldayendhour").val(0+strhour);
	  else
	  $(".fulldayendhour").val(strhour);
	  }
	  sethour();
	  jQuery(".fulldaystarthour").click(function() {
	  sethour();
	  });
	  })';
	  print '</script>'."\n"; */

	print '<form name="formaction" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="add_action">';
	if ($backtopage)
		print '<input type="hidden" name="backtopage" value="' . ($backtopage != '1' ? $backtopage : $_SERVER["HTTP_REFERER"]) . '">';

	dol_htmloutput_mesg($mesg);

	print '<div class="tabBar">';
	print '<table class="border" width="100%">';

	// Type d'action actifs
	print '<tr><td width="30%"><span class="fieldrequired">' . $langs->trans("Type") . '</span></b></td><td>';
	/* if (GETPOST("actioncode"))
	  {
	  print '<input type="hidden" name="actioncode" value="'.GETPOST("actioncode").'">'."\n";
	  $cactioncomm->fetch(GETPOST("actioncode"));
	  print $cactioncomm->getNomUrl();
	  $object->type=split("_",$cactioncomm->code);
	  }
	  else
	  { */
	//$htmlactions->select_type_actions($object->type_code, "actioncode");
	print $object->select_fk_extrafields("type_code", "actioncode");
	//}
	print '</td></tr>';

	// Title
	print '<tr id="jqtitle"><td>' . $langs->trans("Title") . '</td><td><input type="text" name="label" size="60" value="' . GETPOST('label') . '"></td></tr>';

	// Full day
	//print '<tr id="jqfullday"><td>' . $langs->trans("EventOnFullDay") . '</td><td><input type="checkbox" id="fullday" name="fullday" ' . (GETPOST('fullday') ? ' checked="checked"' : '') . '></td></tr>';
	// Date start
	//print $object->datep;
	$datep = date("c", $object->datep->sec);
	//$datep = $object->datep->sec;
	if (GETPOST('datep', 'alpha'))
		$datep = GETPOST('datep', 'alpha');
	print '<tr><td width="30%" nowrap="nowrap"><span class="fieldrequired" id="jqech">' . $langs->trans("DateEchAction") . '</span><span class="fieldrequired" id="jqstart">' . $langs->trans("DateActionStart") . '</span></td><td>';

	print '<input id="dateptimepicker" name="datep" />';
	print '</td></tr>';
	print '<script>
            $(document).ready(function () {
                // create DateTimePicker from input HTML element
                $("#dateptimepicker").kendoDateTimePicker({
                    value:new Date()
                });
            });
        </script>';

	// Date end
	$datef = date("c", $object->datef->sec);
	//$datep = $object->datep->sec;
	if (GETPOST('datef', 'alpha'))
		$datef = GETPOST('datef', 'alpha');
	print '<tr id="jqend"><td>' . $langs->trans("DateActionEnd") . '</td><td>';

	print '<input id="dateftimepicker" name="datef" />';
	print '</td></tr>';
	print '<script>
            $(document).ready(function () {
                // create DateTimePicker from input HTML element
                $("#dateftimepicker").kendoDateTimePicker({
                    value:new Date()
                });
            });
        </script>';

	print '<tr id="jqduration"><td>' . $langs->trans("Duration") . '</td><td colspan="3">';
	//<input type="text" name="duration" size="3" value="' . (empty($object->durationp) ? 1 : $object->durationp / 3600) . '">
	//$form->select_duration('duration', $object->durationp, 0, array('stepMinutes' => 30));

	print '<input id="durationp" name="durationp" value="01:00" />';
	print '<script>
                $(document).ready(function() {
                    // create TimePicker from input HTML element
                    $("#durationp").kendoTimePicker();
                });
            </script>';

	print '</td></tr>';


	// Status
	print '<tr><td width="10%">' . $langs->trans("Status") . ' / ' . $langs->trans("Percentage") . '</td>';
	print '<td>';
	//$percent=-1;
	$percent = 0;
	if (isset($_GET['percentage']) || isset($_POST['percentage'])) {
		$percent = GETPOST('percentage');
	} else {
		if (GETPOST("afaire") == 1)
			$percent = 0;
		if (GETPOST("afaire") == 2)
			$percent = 100;
	}
	print $object->select_fk_extrafields("Status", "status");
	print '</td></tr>';

	// Location
	print '<tr id="jqloc"><td>' . $langs->trans("Location") . '</td><td colspan="3"><input type="text" name="location" size="50" value="' . $object->location . '"></td></tr>';

	print '</table>';

	print '<br><br>';

	print '<table class="border" width="100%">';

	// Affected by
	$var = false;
	print '<tr><td width="30%" nowrap="nowrap">' . $langs->trans("ActionAffectedTo") . '</td><td>';
	print $object->select_fk_extrafields("usertodo", 'affectedto');
	/*print '<input id="usertodo" name="affectedto">';
	?><script>
		$(document).ready(function() {
			var multiselect = $("#usertodo").kendoMultiSelect({
				placeholder: "Utilisateurs...",
				dataTextField: "name",
				dataValueField: "id",
				autoBind: false,
				dataSource: {
					type: "json",
					serverFiltering: true,
					transport: {
						read: {
							url: "api/user/select",
						}
					}
				}
			}).data("kendoMultiSelect");

			$("#button").on("click", function() {
				this.form.elements["affectedto"].value = multiselect.value();
			});
		});
	</script><?php*/
	//
	//
	//$form->select_users(GETPOST("affectedto") ? GETPOST("affectedto") : ($object->usertodo->id > 0 ? $object->usertodo : $user), 'affectedto', 1);
	print '</td></tr>';

	// Realised by
	//print '<tr><td nowrap>' . $langs->trans("ActionDoneBy") . '</td><td>';
	//print $object->select_fk_extrafields("userdone", 'doneby');
	//print '</td></tr>';

	print '</table>';
	print '<br><br>';
	print '<table class="border" width="100%">';

	// Societe, contact
	print '<tr><td width="30%" nowrap="nowrap">' . $langs->trans("ActionOnCompany") . '</td><td>';
	if (!empty($socid)) {
		$societe = new Societe($db);
		$societe->load($socid);
		if ($societe->_id)
			print $societe->getNomUrl(1);
		else { // Is a contact
			$object->contact->id = $socid;
			$socid = $societe->societe->id;
			$societe->load($socid);
			print $societe->getNomUrl(1);
		}
		print '<input type="hidden" name="socid" value="' . $socid . '">';
	} else {
		print $object->select_fk_extrafields("societe", 'socid');
	}
	print '</td></tr>';

	// If company is forced, we propose contacts (may be contact is also forced)
	if (!empty($socid)) {
		$object->societe->id = $socid;
		print '<tr><td nowrap>' . $langs->trans("ActionOnContact") . '</td><td>';
		print $object->select_fk_extrafields("contact", 'contactid');
		//$form->select_contacts(GETPOST('socid', 'int'), GETPOST('contactid'), 'contactid', 1);
		print '</td></tr>';
	}

	// Lead
	if ($conf->lead->enabled && GETPOST("leadid")) {
		// Affaire associe
		$langs->load("lead");

		print '<tr><td valign="top">' . $langs->trans("Lead") . '</td><td>';
		$numlead = select_leads($societe->id, GETPOST("leadid") ? GETPOST("leadid") : $leadid, 'leadid');
		if ($numlead == 0) {
			print ' &nbsp; <a href="../../lead/fiche.php?socid=' . $societe->id . '&action=create">' . $langs->trans("AddLead") . '</a>';
		}
		print '</td></tr>';
	}

	// Project
	if (!empty($conf->projet->enabled)) {
		// Projet associe
		$langs->load("project");

		print '<tr><td valign="top">' . $langs->trans("Project") . '</td><td>';
		$numproject = select_projects((!empty($societe->id) ? $societe->id : 0), GETPOST("projectid") ? GETPOST("projectid") : '', 'projectid');
		if ($numproject == 0) {
			print ' &nbsp; <a href="' . DOL_DOCUMENT_ROOT . '/projet/fiche.php?socid=' . $societe->id . '&action=create">' . $langs->trans("AddProject") . '</a>';
		}
		print '</td></tr>';
	}

	// PropalID
	if (GETPOST("propalid")) {
		// Object linked
		$propal = new Propal($db);
		$propal->fetch(GETPOST("propalid"));

		print '<tr><td valign="top">' . $langs->trans("LinkedObject") . '</td><td>';
		print '<input type="hidden" name="propalid" value="' . $propal->id . '">';
		print $propal->getNomUrl(1);
		print '</td></tr>';
	}

	if (GETPOST("datep") && preg_match('/^([0-9][0-9][0-9][0-9])([0-9][0-9])([0-9][0-9])$/', GETPOST("datep"), $reg)) {
		$object->datep = dol_mktime(0, 0, 0, $reg[2], $reg[3], $reg[1]);
	}

	add_row_for_calendar_link();

	// Description
	print '<tr><td valign="top">' . $langs->trans("Description") . '</td><td>';
	print '<textarea id="note" name="note">' . $object->notes[0]->note . '</textarea>';
	print '<script>
		$(document).ready(function() {
        $("#note").kendoEditor({
		encoded: false,
            tools: [
               "bold",
                            "italic",
                            "underline",
                            "strikethrough",
                            "justifyLeft",
                            "justifyCenter",
                            "justifyRight",
                            "justifyFull",
                            "createLink",
                            "unlink",
                            "createTable",
                            "addColumnLeft",
                            "addColumnRight",
                            "addRowAbove",
                            "addRowBelow",
                            "deleteRow",
                            "deleteColumn",
                            "foreColor",
                            "backColor"
            ]
        });
		});</script>';
	//require_once(DOL_DOCUMENT_ROOT . "/core/class/doleditor.class.php");
	//$doleditor = new DolEditor('note', (GETPOST('note') ? GETPOST('note') : $object->notes), '', 280, 'dolibarr_notes', 'In', true, true, $conf->fckeditor->enabled, ROWS_7, 90);
	//$doleditor->Create();
	print '</td></tr>';

	print '</table>';

	print '<br><center>';
	print '<input type="submit" class="button" id="button" value="' . $langs->trans("Add") . '">';
	print ' &nbsp; &nbsp; ';
	print '<input type="submit" class="button" name="cancel" value="' . $langs->trans("Cancel") . '">';
	print "</center>";

	print "</form>";

	print "</div>";

	print column_end();
}

// View or edit
if ($id) {
	if ($error) {
		dol_htmloutput_errors($error);
	}
	if ($mesg) {
		dol_htmloutput_mesg($mesg);
	}

	$result = $object->fetch($id);
	if ($result < 0) {
		dol_print_error($db, $object->error);
		exit;
	}

	$societe = new Societe($db);
	/*  if ($object->societe->id) {
	  $result = $societe->fetch($object->societe->id);
	  }
	  $object->societe = $societe;

	  if ($object->author->id > 0) {
	  $tmpuser = new User($db);
	  $res = $tmpuser->fetch($object->author->id);
	  $object->author = $tmpuser;
	  }
	  if ($object->usermod->id > 0) {
	  $tmpuser = new User($db);
	  $res = $tmpuser->fetch($object->usermod->id);
	  $object->usermod = $tmpuser;
	  }
	  if ($object->usertodo->id > 0) {
	  $tmpuser = new User($db);
	  $res = $tmpuser->fetch($object->usertodo->id);
	  $object->usertodo = $tmpuser;
	  }
	  if ($object->userdone->id > 0) {
	  $tmpuser = new User($db);
	  $res = $tmpuser->fetch($object->userdone->id);
	  $object->userdone = $tmpuser;
	  }
	 */

	/* if ($object->contact->id) {
	  $result = $contact->fetch($object->contact->id, $user);
	  }
	  $object->contact = $contact; */

	print_fiche_titre($langs->trans("Event") . " " . $object->label);
	print '<div class="with-padding">';
	print '<div class="columns">';

	print column_start();

	/*
	 * Affichage onglets
	 */

	dol_fiche_head($head, 'card', $langs->trans("Action"), 0, 'action');

	$now = dol_now();
	$delay_warning = $conf->global->MAIN_DELAY_ACTIONS_TODO * 24 * 60 * 60;

	// Confirmation suppression action
	if ($action == 'delete') {
		$ret = $form->form_confirm("/agenda/fiche.php?id=" . $id, $langs->trans("DeleteAction"), $langs->trans("ConfirmDeleteAction"), "confirm_delete", '', '', 1);
		if ($ret == 'html')
			print '<br>';
	}

	if ($action == 'edit') {
		print "\n" . '<script type="text/javascript" language="javascript">';
		print 'jQuery(document).ready(function () {
                         function setdatefields()
                         {
                                if (jQuery("#fullday:checked").val() == null)
                                {
                                    jQuery(".fulldaystarthour").attr(\'disabled\', false);
                                    jQuery(".fulldaystartmin").attr(\'disabled\', false);
                                    jQuery(".fulldayendhour").attr(\'disabled\', false);
                                    jQuery(".fulldayendmin").attr(\'disabled\', false);
                                }
                                else
                                {
                                    jQuery(".fulldaystarthour").attr(\'disabled\', true);
                                    jQuery(".fulldaystartmin").attr(\'disabled\', true);
                                    jQuery(".fulldayendhour").attr(\'disabled\', true);
                                    jQuery(".fulldayendmin").attr(\'disabled\', true);
                                    jQuery(".fulldaystarthour").val("00");
                                    jQuery(".fulldaystartmin").val("00");
                                    //jQuery(".fulldayendhour").val("00");
                                    //jQuery(".fulldayendmin").val("00");
                                    jQuery(".fulldayendhour").val("23");
                                    jQuery(".fulldayendmin").val("59");
                            }
                        }
                        setdatefields();
                        jQuery("#fullday").change(function() {
                            setdatefields();
                        });
                   })';
		print '</script>' . "\n";
		print "\n" . '<script type="text/javascript" language="javascript">';
		print 'jQuery(document).ready(function () {
                     function settype()
                     {
                            var typeselected=jQuery("#actioncode").val();
                            var type=typeselected.split("_");
                            if (type[1] == "RDV")
                            {
                                $("#jqfullday").css("display","table-row");
                                $("#jqech").hide();
                                $("#jqstart").show();
                                $("#jqend").css("display","table-row");
                                $("#jqloc").css("display","table-row");
                                $(".fulldaystarthour").show();
                                $(".fulldaystartmin").show();
                                $(".fulldayendhour").show();
                                $(".fulldayendmin").show();
                                jQuery(".fulldaystartmin").val("00");
                                jQuery(".fulldayendmin").val("00");
                                $("#jqduration").hide();
                            }
                            else
                            {
                                $("#jqfullday").css("display","none");
                                $("#jqech").show();
                                $("#jqstart").hide();
                                $("#jqend").css("display","none");
                                $("#jqloc").css("display","none");
                                $(".fulldayendhour").hide();
                                $(".fulldayendmin").hide();
                                jQuery(".fulldaystartmin").val("00");
                                $("#jqduration").show();
                            }
                    }
                    settype();
                    jQuery("#actioncode").change(function() {
                        settype();
                    });
               })';
		print '</script>' . "\n";

		// Fiche action en mode edition
		print '<form name="formaction" action="' . $_SERVER["PHP_SELF"] . '" method="post">';
		print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
		print '<input type="hidden" name="action" value="update">';
		print '<input type="hidden" name="id" value="' . $id . '">';
		print '<input type="hidden" name="ref_ext" value="' . $object->ref_ext . '">';
		print '<input type="hidden" id="actioncode" name="actioncode" value="' . $object->type_code . '">';
		if ($backtopage)
			print '<input type="hidden" name="backtopage" value="' . ($backtopage != '1' ? $backtopage : $_SERVER["HTTP_REFERER"]) . '">';

		print '<table class="border" width="100%">';

		// Ref
		print '<tr><td width="30%">' . $langs->trans("Ref") . '</td><td colspan="3">' . $object->id . '</td></tr>';

		// Type
		print '<tr><td class="fieldrequired">' . ($object->type == 2 ? $langs->trans("Action") : $langs->trans("Event")) . '</td><td colspan="3">' . $object->print_fk_extrafields("type_code") . '</td></tr>';

		// Title
		print '<tr><td>' . $langs->trans("Title") . '</td><td colspan="3"><input type="text" name="label" size="50" value="' . $object->label . '"></td></tr>';

		// Full day
		//print '<tr id="jqfullday"><td>' . $langs->trans("EventOnFullDay") . '</td><td><input type="checkbox" id="fullday" name="fullday" ' . (GETPOST('fullday') ? ' checked="checked"' : '') . '></td></tr>';
		// Date start
		//print $object->datep;
		$datep = date("c", $object->datep->sec);
		//$datep = $object->datep->sec;
		if (GETPOST('datep', 'alpha'))
			$datep = GETPOST('datep', 'alpha');
		print '<tr><td width="30%" nowrap="nowrap"><span class="fieldrequired" id="jqech">' . $langs->trans("DateEchAction") . '</span><span class="fieldrequired" id="jqstart">' . $langs->trans("DateActionStart") . '</span></td><td>';

		print '<input id="dateptimepicker" name="datep" />';

		print '<script>
            $(document).ready(function () {
                // create DateTimePicker from input HTML element
                $("#dateptimepicker").kendoDateTimePicker({
                    value:new Date("' . $datep . '")
                });
            });
        </script>';


		//if (GETPOST("afaire") == 1)
		//	$form->select_date($datep, 'datep', 1, 1, 0, "action", 1, 1, 0, 0, 'fulldayend', array('stepMinutes' => 30));
		//else if (GETPOST("afaire") == 2)
		//	$form->select_date($datep, 'datep', 1, 1, 1, "action", 1, 1, 0, 0, 'fulldayend', array('stepMinutes' => 30));
		//  $html->select_date($datep,'datep',0,0,1,"action",1,1,0,0,'fulldaystart');
		//$form->select_date($datep, 'datep', 1, 1, 0, "action", 1, 1, 0, 0, 'fulldaystart', array('stepMinutes' => 30));
		print '</td></tr>';

		// Date end
		//$datef = date("c", $object->datef->sec);


		$datef = date("c", $object->datef->sec);
		//$datep = $object->datep->sec;
		if (GETPOST('datef', 'alpha'))
			$datef = GETPOST('datef', 'alpha');
		print '<tr id="jqend"><td>' . $langs->trans("DateActionEnd") . '</td><td>';

		print '<input id="dateftimepicker" name="datef" />';
		print '</td></tr>';
		print '<script>
            $(document).ready(function () {
                // create DateTimePicker from input HTML element
                $("#dateftimepicker").kendoDateTimePicker({
                    value:new Date("' . $datef . '")
                });
            });
        </script>';



		//if (GETPOST("afaire") == 1)
		//	$form->select_date($datef, 'datef', 1, 1, 1, "action", 1, 1, 0, 0, 'fulldayend', array('stepMinutes' => 30));
		//else if (GETPOST("afaire") == 2)
		//	$form->select_date($datef, 'datef', 1, 1, 1, "action", 1, 1, 0, 0, 'fulldayend', array('stepMinutes' => 30));
		//else
		//	$form->select_date($datef, 'datef', 1, 1, 0, "action", 1, 1, 0, 0, 'fulldayend', array('stepMinutes' => 30));
		//print '</td></tr>';

		/* print '<tr id="jqend"><td>' . $langs->trans("DateActionEnd") . '</td><td>';
		  print $object->select_fk_extrafields('datef', 'datef');
		  print '</td></tr>'; */

		// duration task
		print '<tr id="jqduration"><td>' . $langs->trans("Duration") . '</td><td colspan="3">';
		//<input type="text" name="duration" size="3" value="' . (empty($object->durationp) ? 1 : $object->durationp / 3600) . '">
		//$form->select_duration('duration', $object->durationp, 0, array('stepMinutes' => 30));

		print '<input id="durationp" name="durationp" value="' . gmdate("H:i", $object->durationp) . '" />';
		print '<script>
                $(document).ready(function() {
                    // create TimePicker from input HTML element
                    $("#durationp").kendoTimePicker();
                });
            </script>';

		print '</td></tr>';

		// Status
		print '<tr><td nowrap>' . $langs->trans("Status") . '</td><td colspan="3">';
		print $object->select_fk_extrafields('Status', 'status');
		print '</td></tr>';

		// Percentage
		if ($object->type_code != 'AC_RDV') {
			// Status
			print '<tr><td nowrap>' . $langs->trans("Percentage") . '</td><td colspan="3" style="height: 60px">';
			$percent = GETPOST("percentage") ? GETPOST("percentage") : $object->percentage;
			//print $htmlactions->form_select_status_action('formaction', $percent, 1);
			print '<p class="inline-medium-label button-height" style="padding-left: 0px; margin-top:10px">';
			print '<input type="text"  size="2" class="input demo-slider mid-margin-right" value="' . $percent . '" id="demo-slider1" name="percentage">';
			print '</p>';
			print '</td></tr>';
			print '<script type="text/javascript" >
                jQuery(document).ready(function(){
                    jQuery(".demo-slider").slider({
                        "hideInput": false
                    });
                });
                </script>';
		}

		if ($object->type_code == "AC_RDV") { //RDV
			// Location
			print '<tr><td>' . $langs->trans("Location") . '</td><td colspan="3"><input type="text" name="location" size="50" value="' . $object->location . '"></td></tr>';
		}

		print '</table><br><br><table class="border" width="100%">';

		// Input by
		$var = false;
		print '<tr><td width="30%" nowrap="nowrap">' . $langs->trans("ActionAskedBy") . '</td><td colspan="3">';
		print $object->print_fk_extrafields("author");
		print '</td></tr>';

		// Affected to
		print '<tr><td nowrap="nowrap">' . $langs->trans("ActionAffectedTo") . '</td><td colspan="3">';
		print $object->select_fk_extrafields("usertodo", 'affectedto');
		/*print '<input id="usertodo" name="affectedto">';
		?><script>
			$(document).ready(function() {
				var multiselect = $("#usertodo").kendoMultiSelect({
					placeholder: "Utilisateurs...",
					dataTextField: "name",
					dataValueField: "id",
					autoBind: false,
					dataSource: {
						type: "json",
						serverFiltering: true,
						transport: {
							read: {
								url: "api/user/select",
							}
						}
					},
					value: <?php echo json_encode($object->usertodo); ?>
					//{name: "admin", id: "user:admin"},
					//{name: "demo", id: "user:demo"}

				}).data("kendoMultiSelect");

				$("#button").on("click", function() {
					this.form.elements["affectedto"].value = multiselect.value();
				});
			});
		</script><?php*/
		print '</td></tr>';

		// Realised by
		//print '<tr><td nowrap="nowrap">' . $langs->trans("ActionDoneBy") . '</td><td colspan="3">';
		//print $object->select_fk_extrafields("userdone", 'doneby');
		//print '</td></tr>';

		print '</table><br><br>';

		print '<table class="border" width="100%">';

		// Company
		print '<tr><td width="30%">' . $langs->trans("ActionOnCompany") . '</td>';
		print '<td>';
		print $object->select_fk_extrafields('societe', 'socid');
		print '</td>';

		// Contact
		print '<td>' . $langs->trans("Contact") . '</td><td width="30%">';
		print $object->select_fk_extrafields('contact', 'contactid');
		//print $form->selectarray("contactid", (empty($object->societe->id) ? array() : $object->societe->contact_array()), $object->contact->id, 1);
		print '</td></tr>';

		// Lead
		if ($conf->lead->enabled) {
			// Lead associe
			$langs->load("lead");

			print '<tr><td valign="top">' . $langs->trans("Lead") . '</td><td colspan="3">';
			$numlead = select_leads($object->societe->id, $object->fk_lead, 'leadid');
			if ($numlead == 0) {
				print ' &nbsp; <a href="../../lead/fiche.php?socid=' . $object->societe->id . '&action=create">' . $langs->trans("AddLead") . '</a>';
			}
			print '</td></tr>';
		}

		// Project
		if ($conf->projet->enabled) {
			// Projet associe
			$langs->load("project");

			print '<tr><td valign="top">' . $langs->trans("Project") . '</td><td colspan="3">';
			$numprojet = select_projects($object->societe->id, $object->fk_project, 'projectid');
			if ($numprojet == 0) {
				print ' &nbsp; <a href="../../projet/fiche.php?socid=' . $object->societe->id . '&action=create">' . $langs->trans("AddProject") . '</a>';
			}
			print '</td></tr>';
		}

		// Object linked
		if (!empty($object->fk_element) && !empty($object->elementtype)) {
			print '<tr><td>' . $langs->trans("LinkedObject") . '</td>';
			print '<td colspan="3">' . $object->getElementUrl($object->fk_element, $object->elementtype, 1) . '</td></tr>';
		}

		// Description
		print '<tr><td valign="top">' . $langs->trans("Description") . '</td><td colspan="3">';
		print '<textarea id="note" name="note">' . $object->notes[0]->note . '</textarea>';
		print '<script>
		$(document).ready(function() {
		$("#note").kendoEditor({
		encoded: false,
            tools: [
                "bold",
                            "italic",
                            "underline",
                            "strikethrough",
                            "justifyLeft",
                            "justifyCenter",
                            "justifyRight",
                            "justifyFull",
                            "createLink",
                            "unlink",
                            "createTable",
                            "addColumnLeft",
                            "addColumnRight",
                            "addRowAbove",
                            "addRowBelow",
                            "deleteRow",
                            "deleteColumn",
                            "foreColor",
                            "backColor"
            ]
        });
		});</script>';
		print '</td></tr>';

		print '</table><br>';

		print '<center><input type="submit" class="button" id="button" name="edit" value="' . $langs->trans("Save") . '">';
		print ' &nbsp; &nbsp; <input type="submit" class="button" name="cancel" value="' . $langs->trans("Cancel") . '">';
		print '</center>';

		print '</form>';
		print '</div>';

		print column_end();
	} else {
		/**
		 * Mode View
		 */
		print '<table class="border" width="100%">';

		// Ref
		print '<tr><td width="30%">' . $langs->trans("Ref") . '</td><td colspan="3">';
		print $form->showrefnav($object, 'id', '', ($user->societe_id ? 0 : 1), 'id', 'ref', '');
		print '</td></tr>';

		// Type
		print '<tr><td>' . $langs->trans("Type") . '</td><td colspan="3">' . $object->print_fk_extrafields("type_code") . '</td></tr>';

		// Title
		print '<tr><td>' . $langs->trans("Title") . '</td><td colspan="3">' . $object->label . '</td></tr>';

		// Full day event
		print '<tr><td>' . $langs->trans("EventOnFullDay") . '</td><td colspan="3">' . yn($object->fulldayevent) . '</td></tr>';

		// Date start
		print '<tr><td width="30%">' . $langs->trans("DateActionStart") . '</td><td colspan="2">';
		if (!$object->fulldayevent)
			print dol_print_date(date("c", $object->datep->sec), 'dayhour');
		else
			print dol_print_date(date("c", $object->datep->sec), 'day');
		if ($object->percentage == 0 && date("c", $object->datep->sec) && date("c", $object->datep->sec) < ($now - $delay_warning))
			print img_warning($langs->trans("Late"));
		print '</td>';
		//if($object->type==1) //RDV
		//{
		print '<td rowspan="4" align="center" valign="middle" width="180">' . "\n";
		print '<form name="listactionsfiltermonth" action="' . DOL_URL_ROOT . '/comm/action/index.php" method="POST">';
		print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
		print '<input type="hidden" name="action" value="show_month">';
		print '<input type="hidden" name="year" value="' . dol_print_date(date("c", $object->datep->sec), '%Y') . '">';
		print '<input type="hidden" name="month" value="' . dol_print_date(date("c", $object->datep->sec), '%m') . '">';
		print '<input type="hidden" name="day" value="' . dol_print_date(date("c", $object->datep->sec), '%d') . '">';
		//print '<input type="hidden" name="day" value="'.dol_print_date($object->datep,'%d').'">';
		print img_picto($langs->trans("ViewCal"), 'object_calendar') . ' <input type="submit" style="width: 120px" class="button" name="viewcal" value="' . $langs->trans("ViewCal") . '">';
		print '</form>' . "\n";
		print '<form name="listactionsfilterweek" action="' . DOL_URL_ROOT . '/comm/action/index.php" method="POST">';
		print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
		print '<input type="hidden" name="action" value="show_week">';
		print '<input type="hidden" name="year" value="' . dol_print_date(date("c", $object->datep->sec), '%Y') . '">';
		print '<input type="hidden" name="month" value="' . dol_print_date(date("c", $object->datep->sec), '%m') . '">';
		print '<input type="hidden" name="day" value="' . dol_print_date(date("c", $object->datep->sec), '%d') . '">';
		//print '<input type="hidden" name="day" value="'.dol_print_date($object->datep,'%d').'">';
		print img_picto($langs->trans("ViewCal"), 'object_calendarweek') . ' <input type="submit" style="width: 120px" class="button" name="viewweek" value="' . $langs->trans("ViewWeek") . '">';
		print '</form>' . "\n";
		print '<form name="listactionsfilterday" action="' . DOL_URL_ROOT . '/comm/action/index.php" method="POST">';
		print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
		print '<input type="hidden" name="action" value="show_day">';
		print '<input type="hidden" name="year" value="' . dol_print_date(date("c", $object->datep->sec), '%Y') . '">';
		print '<input type="hidden" name="month" value="' . dol_print_date(date("c", $object->datep->sec), '%m') . '">';
		print '<input type="hidden" name="day" value="' . dol_print_date(date("c", $object->datep->sec), '%d') . '">';
		//print '<input type="hidden" name="day" value="'.dol_print_date($object->datep,'%d').'">';
		print img_picto($langs->trans("ViewCal"), 'object_calendarday') . ' <input type="submit" style="width: 120px" class="button" name="viewday" value="' . $langs->trans("ViewDay") . '">';
		print '</form>' . "\n";
		print '</td>';
		print '</tr>';
		$var = !$var;

		// Date end
		print '<tr><td>' . $langs->trans("DateActionEnd") . '</td><td colspan="2">';
		if (!$object->fulldayevent)
			print dol_print_date(date("c", $object->datef->sec), 'dayhour');
		else
			print dol_print_date(date("c", $object->datef->sec), 'day');
		if ($object->percentage > 0 && $object->percentage < 100 && $object->datef && $object->datef < ($now - $delay_warning))
			print img_warning($langs->trans("Late"));
		print '</td></tr>';

		// Status
		print '<tr><td nowrap>' . $langs->trans("Status") . ' / ' . $langs->trans("Percentage") . '</td><td colspan="2">';
		print $object->getLibStatus();
		print '</td></tr>';

		// Percentage
		if ($object->type_code != 'AC_RDV') {
			print '<tr><td nowrap>' . $langs->trans("Percentage") . '</td><td colspan="2">';
			print '<span style="width: 100px" class="progress anthracite thin">';
			print '<span style="width: ' . $object->percentage . '%" class="progress-bar"></span>';
			print '</span>';
			print '</td></tr>';
		}
		$var = !$var;

		// Location
		print '<tr><td>' . $langs->trans("Location") . '</td><td colspan="2">' . $object->location . '</td></tr>';

		print '</table><br><br><table class="border" width="100%">';

		// Input by
		$var = false;
		print '<tr><td width="30%" nowrap="nowrap">' . $langs->trans("ActionAskedBy") . '</td><td colspan="3">';
		print $object->print_fk_extrafields('author');
		print '</td></tr>';

		// Affecte a
		print '<tr><td nowrap="nowrap">' . $langs->trans("ActionAffectedTo") . '</td><td colspan="3">';
		print $object->print_fk_extrafields('usertodo');
		print '</td></tr>';

		// Done by
		print '<tr><td nowrap="nowrap">' . $langs->trans("ActionDoneBy") . '</td><td colspan="3">';
		print $object->print_fk_extrafields('userdone');
		print '</td></tr>';

		print '</table><br><br><table class="border" width="100%">';

		// Third party - Contact
		print '<tr><td width="30%">' . $langs->trans("ActionOnCompany") . '</td><td>' . /* ($object->societe->id ? $object->societe->getNomUrl(1) : $langs->trans("None")) */ "";
		/*
		  if ($object->societe->id && $object->type_code == 'AC_TEL') {
		  if ($object->societe->fetch($object->societe->id)) {
		  print "<br>" . dol_print_phone($object->societe->tel);
		  }
		  } */
		if (!empty($object->societe->id)) {
			$societe->id = $object->societe->id;
			$societe->name = $object->societe->name;
			print $societe->getNomUrl(1);
		} else {
			print $langs->trans("None");
		}

		print '</td>';
		print '<td>' . $langs->trans("Contact") . '</td>';
		print '<td>';
		if (!empty($object->contact->id)) {
			$contact->id = $object->contact->id;
			$contact->name = $object->contact->name;
			print $contact->getNomUrl(1);
		} else {
			print $langs->trans("None");
		}

		print '</td></tr>';
		$var = !$var;

		// Lead
		if ($conf->lead->enabled && $object->fk_lead) {
			print '<tr ' . $bc[$var] . '><td valign="top" id="label">' . $langs->trans("Lead") . '</td><td colspan="1" id="value">';
			if ($object->fk_lead) {
				$lead = new Lead($db);
				$lead->fetch($object->fk_lead);
				print $lead->getNomUrl(1);
			}
			print '</td></tr>';
			$var = !$var;
		}

		// Project
		if ($conf->projet->enabled) {
			print '<tr><td valign="top">' . $langs->trans("Project") . '</td><td colspan="3">';
			if ($object->fk_project) {
				$project = new Project($db);
				$project->fetch($object->fk_project);
				print $project->getNomUrl(1);
			}
			print '</td></tr>';
			$var = !$var;
		}

		// Object linked
		if (!empty($object->fk_element) && !empty($object->elementtype)) {
			print '<tr><td>' . $langs->trans("LinkedObject") . '</td>';
			print '<td colspan="3">' . $object->getElementUrl($object->fk_element, $object->elementtype, 1) . '</td></tr>';
		}

		print '</table>';
	}

	print "</div>\n";


	/*
	 * Barre d'actions
	 *
	 */


	if ($action != 'edit') {
		print '<div class="tabsAction">';
		print '<div class="button-height">';
		print '<span class="button-group">';

		if ($user->rights->agenda->allactions->write ||
				(($object->author->id == $user->id || $object->usertodo->id == $user->id) && $user->rights->agenda->myactions->write)) {
			//print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?action=create&fk_task=' . $object->id . ($object->socid ? "&socid=" . $object->socid : "") . ($object->contact->id ? "&contactid=" . $object->contact->id : "") . ($object->fk_lead ? "&leadid=" . $object->fk_lead : "") . ($object->fk_project ? "&projectid=" . $object->fk_project : "") . "&backtopage=" . DOL_URL_ROOT . '/agenda/fiche.php?id=' . $object->id . '">' . $langs->trans("AddAction") . '</a>';
			print '<a class="button icon-pencil" href="' . $_SERVER['PHP_SELF'] . '?action=edit&id=' . $object->id . '">' . $langs->trans("Modify") . '</a>';
			if ($object->percentage < 100)
				print '<a class="button icon-tick" href="' . $_SERVER['PHP_SELF'] . '?action=close&id=' . $object->id . '">' . $langs->trans("Close") . '</a>';
			else
				print '<a class="button icon-tick disabled" href="#" title="' . $langs->trans("NotAllowed") . '">' . $langs->trans("Close") . '</a>';
		}
		else {
			print '<a class="button icon-pencil disabled" href="#" title="' . $langs->trans("NotAllowed") . '">' . $langs->trans("Modify") . '</a>';
			print '<a class="button icon-tick disabled" href="#" title="' . $langs->trans("NotAllowed") . '">' . $langs->trans("Close") . '</a>';
		}

		if ($user->rights->agenda->allactions->delete ||
				(($object->author->id == $user->id || $object->usertodo->id == $user->id) && $user->rights->agenda->myactions->delete)) {
			print '<a class="button icon-trash red-gradient" href="' . $_SERVER['PHP_SELF'] . '?action=delete&id=' . $object->id . '">' . $langs->trans("Delete") . '</a>';
		} else {
			print '<a class="button icon-trash red-gradient disabled" href="#" title="' . $langs->trans("NotAllowed") . '">' . $langs->trans("Delete") . '</a>';
		}

		print '</span>';
		print '</div>';
		print '</div>';

		print column_end();

		print column_start("six");
		print $object->show_notes(true);
		print column_end();
	}
}

print "</div>";
print "</div>";

llxFooter();

/**
 *  Ajoute une ligne de tableau a 2 colonnes pour avoir l'option synchro calendrier
 *
 *  @return     int     Retourne le nombre de lignes ajoutees
 */
function add_row_for_calendar_link() {
	global $conf, $langs, $user;
	$nbtr = 0;

	// Lien avec calendrier si module active
	// TODO external module
	if (!empty($conf->webcalendar->enabled)) {
		if ($conf->global->PHPWEBCALENDAR_SYNCRO != 'never') {
			$langs->load("other");

			print '<tr><td width="25%" nowrap>' . $langs->trans("AddCalendarEntry", "Webcalendar") . '</td>';

			if (!$user->webcal_login) {
				print '<td><input type="checkbox" disabled name="add_webcal">';
				print ' ' . $langs->transnoentities("ErrorWebcalLoginNotDefined", "<a href=\"" . DOL_URL_ROOT . "/user/fiche.php?id=" . $user->id . "\">" . $user->login . "</a>");
				print '</td>';
				print '</tr>';
				$nbtr++;
			} else {
				if ($conf->global->PHPWEBCALENDAR_SYNCRO == 'always') {
					print '<input type="hidden" name="add_webcal" value="on">';
				} else {
					print '<td><input type="checkbox" name="add_webcal"' . (($conf->global->PHPWEBCALENDAR_SYNCRO == 'always' || $conf->global->PHPWEBCALENDAR_SYNCRO == 'yesbydefault') ? ' checked' : '') . '></td>';
					print '</tr>';
					$nbtr++;
				}
			}
		}
	}

	// TODO external module
	if (!empty($conf->phenix->enabled)) {
		if ($conf->global->PHPPHENIX_SYNCRO != 'never') {
			$langs->load("other");

			print '<tr><td width="25%" nowrap>' . $langs->trans("AddCalendarEntry", "Phenix") . '</td>';

			if (!$user->phenix_login) {
				print '<td><input type="checkbox" disabled name="add_phenix">';
				print ' ' . $langs->transnoentities("ErrorPhenixLoginNotDefined", "<a href=\"" . DOL_URL_ROOT . "/user/fiche.php?id=" . $user->id . "\">" . $user->login . "</a>");
				print '</td>';
				print '</tr>';
				$nbtr++;
			} else {
				if ($conf->global->PHPPHENIX_SYNCRO == 'always') {
					print '<input type="hidden" name="add_phenix" value="on">';
				} else {
					print '<td><input type="checkbox" name="add_phenix"' . (($conf->global->PHPPHENIX_SYNCRO == 'always' || $conf->global->PHPPHENIX_SYNCRO == 'yesbydefault') ? ' checked' : '') . '></td>';
					print '</tr>';
					$nbtr++;
				}
			}
		}
	}

	return $nbtr;
}
?>
