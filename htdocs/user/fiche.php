<?php

/* Copyright (C) 2002-2006	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2002-2003	Jean-Louis Bergamo		<jlb@j1b.org>
 * Copyright (C) 2004-2011	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2004		Eric Seigne				<eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2013	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2005		Lionel Cousteix			<etm_ltd@tiscali.co.uk>
 * Copyright (C) 2011-2013	Herve Prot				<herve.prot@symeos.com>
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
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
if (!empty($conf->ldap->enabled))
	require_once DOL_DOCUMENT_ROOT . '/core/class/ldap.class.php';
if (!empty($conf->adherent->enabled))
	require_once DOL_DOCUMENT_ROOT . '/adherent/class/adherent.class.php';
if (!empty($conf->multicompany->enabled))
	dol_include_once("/multicompany/class/actions_multicompany.class.php");

$id = GETPOST('id');
$action = GETPOST("action");
$group = GETPOST("group", "alpha");
$confirm = GETPOST("confirm");

// Define value to know what current user can do on users
$canadduser = ($user->admin || $user->rights->user->user->creer);
$canreaduser = ($user->admin || $user->rights->user->user->lire);
$canedituser = ($user->admin || $user->id == $id);
$candisableuser = ($user->admin || $user->rights->user->user->supprimer);
$caneditperms = ($user->admin || $user->rights->user->user->creer);
$canreadgroup = $canreaduser;
$caneditgroup = $canedituser;
// Define value to know what current user can do on properties of edited user
if ($id) {
	// $user est le user qui edite, $_GET["id"] est l'id de l'utilisateur edite
	$caneditfield = ((($user->id == $id) && $user->rights->user->self->creer) || (($user->id != $id) && $user->rights->user->user->creer));
	$caneditpassword = ((($user->id == $id) && $user->rights->user->self->password) || (($user->id != $id) && $user->rights->user->user->password));
}

// Security check
$socid = 0;
if ($user->societe_id > 0)
	$socid = $user->societe_id;
$feature2 = 'user';
if ($user->id == $id) {
	$feature2 = '';
	$canreaduser = 1;
} // A user can always read its own card

$result = restrictedArea($user, 'user', $id, '&user', $feature2);
if ($user->id <> $id && !$canreaduser)
	accessforbidden();

$langs->load("users");
$langs->load("companies");
$langs->load("ldap");

$form = new Form($db);
$edituser = new User($db);
$fuser = new User($db);

/**
 * Actions
 */
if ($action == 'add_right' && $caneditperms) {
	try {
		$fuser->load($id);

		// For avoid error in strict mode
		if (!is_object($fuser->rights))
			$fuser->rights = new stdClass();

		$fuser->rights->$_GET['pid'] = true;
		$fuser->record(); // TODO BUG FIX DOESN'T WORK
	} catch (Exception $e) {
		$mesg = $e->getMessage();
		setEventMessage($mesg, 'errors');
	}
	Header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id);
	exit;
}

if ($action == 'remove_right' && $caneditperms) {
	try {
		$fuser->load($id);
		unset($fuser->rights->$_GET['pid']);

		$fuser->record();
	} catch (Exception $e) {
		$mesg = $e->getMessage();
		setEventMessage($mesg, 'errors');
	}
	Header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $id);
	exit;
}

if ($action == 'confirm_disable' && $confirm == "yes" && $candisableuser) {
	if ($id <> $user->id) {
		$edituser->load($id);
		$edituser->setstatus(0);
		Header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $id);
		exit;
	}
}
if ($action == 'confirm_enable' && $confirm == "yes" && $candisableuser) {
	if ($id <> $user->id) {

		$error = 0;

		$edituser->load($id);

		if (!empty($conf->file->main_limit_users)) {
			$nb = $edituser->getNbOfUsers("active", 1);
			if ($nb >= $conf->file->main_limit_users) {
				setEventMessage($langs->trans("YourQuotaOfUsersIsReached"), 'errors');
				$error++;
			}
		}

		if (!$error) {
			$edituser->setstatus(1);
			Header("Location: " . $_SERVER['PHP_SELF'] . '?id=' . $id);
			exit;
		}
	}
}

if ($action == 'confirm_delete' && $confirm == "yes" && $candisableuser) {
	if ($id <> $user->id) {
		$edituser->load($id);
		$result = $edituser->delete(true);
		if ($result < 0) {
			$langs->load("errors");
			setEventMessage($langs->trans("ErrorUserCannotBeDelete"), 'errors');
		} else {
			Header("Location: index.php");
			exit;
		}
	}
}

// Action ajout user
if ((($action == 'add' && $canadduser) || ($action == 'update' && $canedituser)) && !$_POST["cancel"]) {
	$error = 0;
	if (!$_POST["nom"]) {
		setEventMessage($langs->trans("NameNotDefined"), 'errors');
		$error++;
		if ($action == 'add')
			$action = "create"; // Go back to create page
		else
			$action = "edit";
	}
	if (!$_POST["login"]) {
		setEventMessage($langs->trans("LoginNotDefined"), 'errors');
		$error++;
		if ($action == 'add')
			$action = "create"; // Go back to create page
		else
			$action = "edit";
	}
	if (!isValidEMail($_POST["email"])) {
		$langs->load("errors");
		setEventMessage($langs->trans("ErrorBadEMail"), 'errors');
		$error++;
		if ($action == 'add')
			$action = "create"; // Go back to create page
		else
			$action = "edit";
	}

	if (!empty($conf->file->main_limit_users) && $action == 'add') { // If option to limit users is set
		$nb = $edituser->getNbOfUsers("active", 1);
		if ($nb >= $conf->file->main_limit_users) {
			setEventMessage($langs->trans("YourQuotaOfUsersIsReached"), 'errors');
			$action = "create"; // Go back to create page
			$error++;
		}
	}

	if (!$error) {
		//if ($action == "update")
		if ($id)
			$edituser->load($id);
		else
			$edituser->load("user:" . strtolower($_POST["login"]));

		if ($action == "add" && $edituser->name) {
			$edituser->error = 'ErrorLoginAlreadyExists';
			setEventMessage($langs->trans($edituser->error), 'errors');
			$action = "create";
		} else {
			$edituser->lastname = $_POST["nom"];
			$edituser->firstname = $_POST["prenom"];
			$edituser->name = $_POST["login"];
			$edituser->pass = $_POST["password"];
			$edituser->admin = (bool) $_POST["admin"];
			$edituser->phonePro = $_POST["PhonePro"];
			$edituser->fax = $_POST["Fax"];
			$edituser->phoneMobile = $_POST["phoneMobile"];
			$edituser->email = $_POST["email"];
			$edituser->signature = $_POST["signature"];
			$edituser->entity = $_POST["default_entity"];

			if (GETPOST('deletephoto')) {
				$del_photo = $edituser->photo;
				unset($edituser->photo);
			} elseif (!empty($_FILES['photo']['name']))
				$edituser->photo = dol_sanitizeFileName($_FILES['photo']['name']);

			$id = $edituser->update($user, 0, $action);

			if ($id == $user->name)
				dol_delcache("user:" . $id);

			if ($id == $edituser->name) {
				$file_OK = is_uploaded_file($_FILES['photo']['tmp_name']);

				if (GETPOST('deletephoto') && !empty($del_photo)) {
					$edituser->deleteFile($del_photo);
				}

				if ($file_OK) {
					if (image_format_supported($_FILES['photo']['name']) > 0) {
						
						$edituser->storeFile('photo');
					} else {
						$errmsgs[] = "ErrorBadImageFormat";
					}
				}
				Header("Location: " . $_SERVER['PHP_SELF'] . '?id=user:' . $id);
				exit;
			} else {
				$langs->load("errors");
				if (is_array($edituser->errors) && count($edituser->errors))
					setEventMessage(join('<br>', $langs->trans($edituser->errors)), 'errors');
				else
					setEventMessage($langs->trans($edituser->error), 'errors');
				//print $edituser->error;
				if ($action == "add")
					$action = "create"; // Go back to create page
				if ($action == "update")
					$action = "edit"; // Go back to create page
			}
		}
	}
}

// Action ajout groupe utilisateur
if (($action == 'addgroup' || $action == 'removegroup') && $caneditfield) {
	if ($group) {
		$edituser->load($id);

		if ($action == 'addgroup') {
			$edituser->roles[] = $group;
			//$edituser->addRoleToUser($group);
		}
		if ($action == 'removegroup') {
			unset($edituser->roles[array_search($group, $edituser->roles)]);
			$edituser->roles = array_merge($edituser->roles);
			//$edituser->removeRoleFromUser($group);
		}

		$edituser->record($edituser->id == $user->id);

		header("Location: fiche.php?id=" . $id);
		exit;
	}
}



/*
 * View
 */

llxHeader('', $langs->trans("UserCard"));

$form = new Form($db);

if (($action == 'create') || ($action == 'adduserldap')) {

	/*
	 * Affichage fiche en mode creation
	 */

	print_fiche_titre($langs->trans("NewUser"));
	print '<div class="with-padding">';

	print $langs->trans("CreateInternalUserDesc");
	print "<br>";
	print "<br>";

	print '<form action="' . $_SERVER["PHP_SELF"] . '" method="post" name="createuser">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="add">';
	if ($ldap_sid)
		print '<input type="hidden" name="ldap_sid" value="' . $ldap_sid . '">';
	print '<input type="hidden" name="default_entity" value="' . $conf->Couchdb->name . '">';

	print '<table class="border" width="100%">';

	print '<tr>';

	// Nom
	print '<td valign="top" width="160"><span class="fieldrequired">' . $langs->trans("Lastname") . '</span></td>';
	print '<td>';
	if ($ldap_nom) {
		print '<input type="hidden" name="nom" value="' . $ldap_nom . '">';
		print $ldap_nom;
	} else {
		print '<input size="30" type="text" name="nom" value="' . $_POST["nom"] . '">';
	}
	print '</td></tr>';

	// Prenom
	print '<tr><td valign="top">' . $langs->trans("Firstname") . '</td>';
	print '<td>';
	if ($ldap_prenom) {
		print '<input type="hidden" name="prenom" value="' . $ldap_prenom . '">';
		print $ldap_prenom;
	} else {
		print '<input size="30" type="text" name="prenom" value="' . $_POST["prenom"] . '">';
	}
	print '</td></tr>';

	// Login
	print '<tr><td valign="top"><span class="fieldrequired">' . $langs->trans("Login") . '</span></td>';
	print '<td>';
	if ($ldap_login) {
		print '<input type="hidden" name="login" value="' . $ldap_login . '">';
		print $ldap_login;
	} elseif ($ldap_loginsmb) {
		print '<input type="hidden" name="login" value="' . $ldap_loginsmb . '">';
		print $ldap_loginsmb;
	} else {
		print '<input size="20" maxsize="24" type="text" name="login" value="' . $_POST["login"] . '">';
	}
	print '</td></tr>';

	$generated_password = '';
	if (!$ldap_sid) { // ldap_sid is for activedirectory
		require_once(DOL_DOCUMENT_ROOT . "/core/lib/security2.lib.php");
		$generated_password = getRandomPassword('');
	}
	$password = $generated_password;

	// Mot de passe
	print '<tr><td valign="top">' . $langs->trans("Password") . '</td>';
	print '<td>';
	// We do not use a field password but a field text to show new password to use.
	print '<input size="30" maxsize="32" type="text" name="password" value="' . $password . '">';
	print '</td></tr>';

	// Administrateur
	if ($user->admin) {
		print '<tr><td valign="top">' . $langs->trans("Administrator") . '</td>';
		print '<td>';
		print $form->selectyesno('admin', $_POST["admin"], 1);
		print "</td></tr>\n";
	}

	// Type
	print '<tr><td valign="top">' . $langs->trans("Type") . '</td>';
	print '<td>';
	print $form->textwithpicto($langs->trans("Internal"), $langs->trans("InternalExternalDesc"));
	print '</td></tr>';

	// Tel
	print '<tr><td valign="top">' . $langs->trans("PhonePro") . '</td>';
	print '<td>';
	if ($ldap_phone) {
		print '<input type="hidden" name="office_phone" value="' . $ldap_phone . '">';
		print $ldap_phone;
	} else {
		print '<input size="20" type="text" name="office_phone" value="' . $_POST["office_phone"] . '">';
	}
	print '</td></tr>';

	// Tel portable
	print '<tr><td valign="top">' . $langs->trans("PhoneMobile") . '</td>';
	print '<td>';
	if ($ldap_mobile) {
		print '<input type="hidden" name="phoneMobile" value="' . $ldap_mobile . '">';
		print $ldap_mobile;
	} else {
		print '<input size="20" type="text" name="phoneMobile" value="' . $_POST["phoneMobile"] . '">';
	}
	print '</td></tr>';

	// Fax
	print '<tr><td valign="top">' . $langs->trans("Fax") . '</td>';
	print '<td>';
	if ($ldap_fax) {
		print '<input type="hidden" name="Fax" value="' . $ldap_fax . '">';
		print $ldap_fax;
	} else {
		print '<input size="20" type="text" name="Fax" value="' . $_POST["Fax"] . '">';
	}
	print '</td></tr>';

	// EMail
	print '<tr><td valign="top" class="fieldrequired">' . $langs->trans("email") . '</td>';
	print '<td>';
	print '<input size="40" type="text" name="email" value="' . $_POST["email"] . '">';
	print '</td></tr>';

	// Signature
	print '<tr><td valign="top">' . $langs->trans("Signature") . '</td>';
	print '<td>';
	print '<textarea rows="' . ROWS_5 . '" cols="90" name="signature">' . $_POST["signature"] . '</textarea>';
	print '</td></tr>';

	print "</table>\n";

	print '<center><br><input class="button" value="' . $langs->trans("CreateUser") . '" name="create" type="submit"></center>';

	print "</form>";
	print "</div>";
} else {

	/*
	 * Visu et edition
	 */

	if ($id) {
		$fuser = new User($db);
		$fuser->load($id);
		$fuser->getrights('', false);

		// Show tabs
		//$head = user_prepare_head($fuser);

		$title = $langs->trans("User");

		print_fiche_titre($title);
		print '<div class="with-padding">';
		print '<div class="columns">';
		print column_start();

		dol_fiche_head($head, 'user', $title, 0, 'user');

		/*
		 * Confirmation desactivation
		 */
		if ($action == 'disable') {
			$ret = $form->form_confirm($_SERVER["PHP_SELF"] . "?id=$fuser->id", $langs->trans("DisableAUser"), $langs->trans("ConfirmDisableUser", $fuser->login), "confirm_disable", '', 0, 1);
			if ($ret == 'html')
				print '<br>';
		}

		/*
		 * Confirmation activation
		 */
		if ($action == 'enable') {
			$ret = $form->form_confirm($_SERVER["PHP_SELF"] . "?id=$fuser->id", $langs->trans("EnableAUser"), $langs->trans("ConfirmEnableUser", $fuser->login), "confirm_enable", '', 0, 1);
			if ($ret == 'html')
				print '<br>';
		}

		/*
		 * Confirmation suppression
		 */
		if ($action == 'delete') {
			$ret = $form->form_confirm($_SERVER["PHP_SELF"] . "?id=$fuser->id", $langs->trans("DeleteAUser"), $langs->trans("ConfirmDeleteUser", $fuser->login), "confirm_delete", '', 0, 1);
			if ($ret == 'html')
				print '<br>';
		}

		/*
		 * Fiche en mode visu
		 */
		if ($action != 'edit') {

			print '<table class="border" width="100%">';

			// Ref
			print '<tr><td width="25%" valign="top">' . $langs->trans("Ref") . '</td>';
			print '<td colspan="2">';
			print $form->showrefnav($fuser, 'id', '', $user->rights->user->user->lire || $user->admin);
			print '</td>';
			print '</tr>' . "\n";

			$rowspan = 14;
			if ($conf->societe->enabled)
				$rowspan++;
			if ($conf->adherent->enabled)
				$rowspan++;

			// Lastname
			print '<tr><td valign="top">' . $langs->trans("Lastname") . '</td>';
			print '<td>' . $fuser->lastname . '</td>';

			// Photo
			print '<td align="center" valign="middle" width="25%" rowspan="' . $rowspan . '">';
			print $form->showphoto('userphoto', $fuser, 100);
			print '</td>';

			print '</tr>' . "\n";

			// Firstname
			print '<tr><td valign="top">' . $langs->trans("Firstname") . '</td>';
			print '<td>' . $fuser->firstname . '</td>';
			print '</tr>' . "\n";

			// Login
			print '<tr><td valign="top">' . $langs->trans("Login") . '</td>';
			if ($fuser->ldap_sid && $fuser->statut == 0) {
				print '<td class="error">' . $langs->trans("LoginAccountDisableInSpeedealing") . '</td>';
			} else {
				print '<td>' . $fuser->name . '</td>';
			}
			print '</tr>' . "\n";

			// Password
			print '<tr><td valign="top">' . $langs->trans("Password") . '</td>';
			print '<td>';
			print $langs->trans("Hidden");
			print "</td>";
			print '</tr>' . "\n";

			// Administrator
			$name = $fuser->name;
			/* if ($user->admin) {
			  $admins = $fuser->getUserAdmins();
			  if (isset($admins->$name))
			  $fuser->admin = true;
			  else
			  $fuser->admin = false;
			  }
			  else
			  $fuser->admin = false; */

			print '<tr><td valign="top">' . $langs->trans("Administrator") . '</td><td>';
			if ($fuser->admin) {
				print $form->textwithpicto(yn($fuser->admin), $langs->trans("AdministratorDesc"), 1, "admin");
			} else {
				print yn($fuser->admin);
			}
			print '</td></tr>' . "\n";

			// Default entity
			print '<tr><td valign="top">' . $langs->trans("Entity") . '</td><td>';
			print $fuser->entity;
			print '</td></tr>' . "\n";

			// Tel pro
			print '<tr><td valign="top">' . $langs->trans("PhonePro") . '</td>';
			print '<td>' . dol_print_phone($fuser->phonePro, '', 0, 0, 1) . '</td>';
			print '</tr>' . "\n";

			// Tel mobile
			print '<tr><td valign="top">' . $langs->trans("PhoneMobile") . '</td>';
			print '<td>' . dol_print_phone($fuser->phoneMobile, '', 0, 0, 1) . '</td>';
			print '</tr>' . "\n";

			// Fax
			print '<tr><td valign="top">' . $langs->trans("Fax") . '</td>';
			print '<td>' . dol_print_phone($fuser->fax, '', 0, 0, 1) . '</td>';
			print '</tr>' . "\n";

			// EMail
			print '<tr><td valign="top">' . $langs->trans("EMail") . '</td>';
			print '<td>' . dol_print_email($fuser->email, 0, 0, 1) . '</td>';
			print "</tr>\n";

			// Signature
			print '<tr><td valign="top">' . $langs->trans('Signature') . '</td>';
			print '<td>' . $fuser->Signature . '</td>';
			print "</tr>\n";

			// Statut
			$status = $fuser->Status;
			print '<tr><td>' . $form->editfieldkey("Status", 'Status', $fuser->Status, $fuser, $user->admin && !$fuser->fk_extrafields->fields->Status->values->$status->notEditable, "select") . '</td>';
			print '<td>';
			print $form->editfieldval("Status", 'Status', $fuser->Status, $fuser, $user->admin && !$fuser->fk_extrafields->fields->Status->values->$status->notEditable, "select");
			print '</td>';
			print '</tr>';

			print '<tr><td valign="top">' . $langs->trans("LastConnexion") . '</td>';
			print '<td>' . dol_print_date($fuser->datelastlogin, "dayhour") . '</td>';
			print "</tr>\n";

			print '<tr><td valign="top">' . $langs->trans("PreviousConnexion") . '</td>';
			print '<td>' . dol_print_date($fuser->datepreviouslogin, "dayhour") . '</td>';
			print "</tr>\n";


			if (preg_match('/myopenid/', $conf->authmode)) {
				print '<tr><td valign="top">' . $langs->trans("url_openid") . '</td>';
				print '<td>' . $fuser->openid . '</td>';
				print "</tr>\n";
			}
			// Autres caracteristiques issus des autres modules
			// Company / Contact
			if ($conf->societe->enabled) {
				print '<tr><td valign="top">' . $langs->trans("LinkToCompanyContact") . '</td>';
				print '<td>';
				if ($fuser->societe_id > 0) {
					$societe = new Societe($db);
					$societe->fetch($fuser->societe_id);
					print $societe->getNomUrl(1, '');
				} else {
					print $langs->trans("ThisUserIsNot");
				}
				if ($fuser->contact_id) {
					$contact = new Contact($db);
					$contact->fetch($fuser->contact_id);
					if ($fuser->societe_id > 0)
						print ' / ';
					else
						print '<br>';
					print '<a href="' . DOL_URL_ROOT . '/contact/fiche.php?id=' . $fuser->contact_id . '">' . img_object($langs->trans("ShowContact"), 'contact') . ' ' . dol_trunc($contact->getFullName($langs), 32) . '</a>';
				}
				print '</td>';
				print '</tr>' . "\n";
			}

			// Module Adherent
			if ($conf->adherent->enabled) {
				$langs->load("members");
				print '<tr><td valign="top">' . $langs->trans("LinkedToSpeedealingMember") . '</td>';
				print '<td>';
				if ($fuser->fk_member) {
					$adh = new Adherent($db);
					$adh->fetch($fuser->fk_member);
					$adh->ref = $adh->getFullname($langs); // Force to show login instead of id
					print $adh->getNomUrl(1);
				} else {
					print $langs->trans("UserNotLinkedToMember");
				}
				print '</td>';
				print '</tr>' . "\n";
			}

			print "</table>\n";

			/*
			 * Buttons actions
			 */

			print '<div class="tabsAction">';
			print '<span class="button-group">';
			if ($caneditfield) {
				print '<a class="button icon-pencil" href="' . $_SERVER["PHP_SELF"] . '?id=' . $fuser->id . '&amp;action=edit">' . $langs->trans("Modify") . '</a>';
			}

			// Activer
			if ($candisableuser && $fuser->Status != "ENABLE") {
				print '<a class="button icon-lock" href="' . $_SERVER["PHP_SELF"] . '?id=' . $fuser->id . '&amp;action=enable">' . $langs->trans("Reactivate") . '</a>';
			}
			// Desactiver
			if ($candisableuser && $fuser->Status == "ENABLE") {
				print '<a class="button icon-unlock" href="' . $_SERVER["PHP_SELF"] . '?action=disable&amp;id=' . $fuser->id . '">' . $langs->trans("DisableUser") . '</a>';
			}
			// Delete
			if ($user->id <> $id && $candisableuser) {
				print '<a class="button red-gradient icon-trash" href="' . $_SERVER["PHP_SELF"] . '?action=delete&amp;id=' . $fuser->id . '">' . $langs->trans("DeleteUser") . '</a>';
			}

			print "</span></div>";

			print '</div>';

			print column_end();

			/*
			 * Liste des groupes dans lequel est l'utilisateur
			 */

			if ($canreadgroup) {

				print column_start();
				print start_box($langs->trans("ListOfGroups"), "16-Users-2.png");

				// On selectionne les users qui ne sont pas deja dans le groupe
				$exclude = array();

				if (!empty($fuser->roles)) {
					foreach ($fuser->roles as $useringroup) {
						$exclude[] = $useringroup;
					}
				}


				print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $fuser->id . '" method="POST">' . "\n";
				print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
				print '<input type="hidden" name="action" value="addgroup">';
				print '<table class="noborder" width="100%">' . "\n";
				print '<tr class="liste_titre"><td class="liste_titre" width="25%">' . $langs->trans("NonAffectedUsers") . '</td>' . "\n";
				print '<td>';
				print $form->select_dolgroups('', 'group', 1, $exclude, 0, '', '');
				print '</td>';
				//print '<td valign="top">' . $langs->trans("Administrator") . '</td>';
				//print "<td>" . $form->selectyesno('admin', 0, 1);
				//print "</td>\n";
				print '<td><input type="submit" class="button tiny" value="' . $langs->trans("Add") . '">';
				print '</td></tr>' . "\n";
				print '</table></form>' . "\n";
				print '<br>';


				/*
				 * Groupes affectes
				 */
				print '<table class="display" id="group">';
				print '<thead>';
				print '<tr>';
				print '<th>' . $langs->trans("Group") . '</th>';
				$obj->aoColumns[$i] = new stdClass();
				$obj->aoColumns[$i]->mDataProp = "";
				$i++;
				print '<th>' . $langs->trans("Action") . '</th>';
				$obj->aoColumns[$i] = new stdClass();
				$obj->aoColumns[$i]->mDataProp = "";
				$obj->aoColumns[$i]->sClass = "fright content_actions";
				$i++;
				print "</tr>\n";
				print '</thead>';

				print '<tbody>';
				if (!empty($fuser->roles)) {
					$var = True;

					foreach ($fuser->roles as $aRow) {
						$var = !$var;

						$useringroup = new UserGroup($db);
						try {
							$useringroup->load("group:" . $aRow);
						} catch (Exception $e) {
							$useringroup->name = "Deleted";
						}

						print "<tr $bc[$var]>";
						print '<td>';
						print '<a href="' . DOL_URL_ROOT . '/user/group/fiche.php?id=' . $useringroup->id . '">' . img_object($langs->trans("ShowGroup"), "group") . ' ' . $useringroup->name . '</a>';
						if ($useringroup->admin)
							print img_picto($langs->trans("Administrator"), 'star');
						print '</td>';
						print '<td>';
						if ($user->admin) {
							print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $fuser->id . '&amp;action=removegroup&amp;group=' . $useringroup->name . '">';
							print img_delete($langs->trans("RemoveFromGroup"));
						} else {
							print "-";
						}
						print "</td></tr>\n";
					}
				}
				print '<tbody>';
				print "</table>";

				$obj->aaSorting = array(array(0, "asc"));
				$obj->sDom = 'l<fr>t<\"clear\"rtip>';

				$fuser->datatablesCreate($obj, "group");
				print end_box();
				print column_end();

				print column_start();
				print start_box($langs->trans("Permissions"), "16-User-2.png");
				// Search all modules with permission and reload permissions def.

				/*
				 * Ecran ajout/suppression permission
				 */

				if ($user->admin)
					print info_admin($langs->trans("WarningOnlyPermissionOfActivatedModules"));

				$i = 0;
				$obj = new stdClass();

				print '<table class="display dt_act" id="perm_rights">';

				print'<thead>';
				print'<tr>';

				print'<th>';
				print'</th>';
				$obj->aoColumns[$i] = new stdClass();
				$obj->aoColumns[$i]->mDataProp = "id";
				$obj->aoColumns[$i]->sDefaultContent = "";
				$obj->aoColumns[$i]->bVisible = false;
				$i++;

				print'<th class="essential">';
				print $langs->trans("Module");
				print'</th>';
				$obj->aoColumns[$i] = new stdClass();
				$obj->aoColumns[$i]->mDataProp = "name";
				$obj->aoColumns[$i]->sDefaultContent = "";
				$obj->aoColumns[$i]->sWidth = "18em";
				$i++;

				print'<th>';
				print $langs->trans("Permission");
				print'</th>';
				$obj->aoColumns[$i] = new stdClass();
				$obj->aoColumns[$i]->mDataProp = "desc";
				$obj->aoColumns[$i]->sDefaultContent = "";
				$obj->aoColumns[$i]->bVisible = true;
				$i++;

				print'<th class="essential">';
				print $langs->trans("Enabled");
				print'</th>';
				$obj->aoColumns[$i] = new stdClass();
				$obj->aoColumns[$i]->mDataProp = "Status";
				$obj->aoColumns[$i]->sDefaultContent = "false";
				$obj->aoColumns[$i]->sClass = "center";

				print'</tr>';
				print'</thead>';
				$obj->fnDrawCallback = "function(oSettings){
				if ( oSettings.aiDisplay.length == 0 )
				{
				return;
			}
				var nTrs = jQuery('#perm_rights tbody tr');
				var iColspan = nTrs[0].getElementsByTagName('td').length;
				var sLastGroup = '';
				for ( var i=0 ; i<nTrs.length ; i++ )
				{
				var iDisplayIndex = oSettings._iDisplayStart + i;
				var sGroup = oSettings.aoData[ oSettings.aiDisplay[iDisplayIndex] ]._aData['name'];
				if (sGroup!=null && sGroup!='' && sGroup != sLastGroup)
				{
				var nGroup = document.createElement('tr');
				var nCell = document.createElement('td');
				nCell.colSpan = iColspan;
				nCell.className = 'group';
				nCell.innerHTML = sGroup;
				nGroup.appendChild( nCell );
				nTrs[i].parentNode.insertBefore( nGroup, nTrs[i] );
				sLastGroup = sGroup;
			}


			}
			}";

				$i = 0;
				print'<tfoot>';
				print'</tfoot>';
				print'<tbody>';

				$object = new DolibarrModules($db);

				$result = $object->getDefaultRight();

				if (count($result)) {

					foreach ($result as $rows) {
						foreach ($rows['rights'] as $aRow) {
							$aRow = json_decode(json_encode($aRow));
							print'<tr>';

							$object->name = $rows['name'];
							$object->numero = $rows['numero'];
							$object->rights_class = $rows['rights_class'];
							$object->id = $aRow->id;
							$object->perm = $aRow->perm;
							$object->desc = $aRow->desc;
							$object->Status = ($rows['Status'] == true ? "true" : "false");

							print '<td>' . $aRow->id . '</td>';
							print '<td>' . img_object('', $rows['picto']) . " " . $object->getName() . '</td>';
							print '<td>' . $object->getPermDesc() . '<a name="' . $aRow->id . '">&nbsp;</a></td>';
							print '<td>';

							$perm = $aRow->id;
							$perm0 = (string) $object->perm[0];
							$perm1 = $object->perm[1];
							$right_class = $object->rights_class;

							/* if ($caneditperms) { */
							if ($aRow->Status)
								print $object->getLibStatus(); // Enable by default
							elseif (count($object->perm) == 1 && $fuser->rights->$right_class->$perm0) {
								$object->Status = "true";
								print $object->getLibStatus();
							} elseif (count($object->perm) == 2 && $fuser->rights->$right_class->$perm0->$perm1) {
								$object->Status = "true";
								print $object->getLibStatus();
							}

							//print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $fuser->id . '&pid=' . $aRow->value->id . '&amp;action=remove_right#' . $aRow->value->id . '">' . img_edit_remove() . '</a>';
							//else
							//print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $fuser->id . '&pid=' . $aRow->value->id . '&amp;action=add_right#' . $aRow->value->id . '">' . img_edit_add() . '</a>';
							//}
							else {
								print $object->getLibStatus();
							}
							print '</td>';

							print'</tr>';
						}
					}
				}
				print'</tbody>';
				print'</table>';

				$obj->aaSorting = array(array(1, 'asc'));
				$obj->sDom = 'l<fr>t<\"clear\"rtip>';
				$obj->iDisplayLength = 50;

				print $object->datatablesCreate($obj, "perm_rights");


				print end_box();
				print column_end();
			}
		}


		/*
		 * Fiche en mode edition
		 */

		if ($action == 'edit' && ($canedituser || ($user->id == $fuser->id))) {

			print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $fuser->id . '" method="POST" name="updateuser" enctype="multipart/form-data">';
			print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
			print '<input type="hidden" name="action" value="update">';
			print '<table width="100%" class="border">';

			$rowspan = 12;

			if ($conf->societe->enabled)
				$rowspan++;
			if ($conf->adherent->enabled)
				$rowspan++;

			print '<tr><td width="25%" valign="top">' . $langs->trans("Ref") . '</td>';
			print '<td colspan="2">';
			print $fuser->id;
			print '</td>';
			print '</tr>';

			// Lastname
			print "<tr>";
			print '<td valign="top" class="fieldrequired">' . $langs->trans("Lastname") . '</td>';
			print '<td>';
			if ($caneditfield && !$fuser->ldap_sid) {
				print '<input size="30" type="text" class="flat" name="nom" value="' . $fuser->lastname . '">';
			} else {
				print '<input type="hidden" name="nom" value="' . $fuser->lastname . '">';
				print $fuser->lastname;
			}
			print '</td>';
			// Photo
			print '<td align="center" valign="middle" width="25%" rowspan="' . $rowspan . '">';
			print $form->showphoto('userphoto', $fuser);
			if ($caneditfield) {
				if ($fuser->photo)
					print "<br>\n";
				print '<table class="nobordernopadding">';
				if ($fuser->photo)
					print '<tr><td align="center"><input type="checkbox" class="flat" name="deletephoto" id="photodelete"> ' . $langs->trans("Delete") . '<br><br></td></tr>';
				print '<tr><td>' . $langs->trans("PhotoFile") . '</td></tr>';
				print '<tr><td><input type="file" class="flat" name="photo" id="photoinput"></td></tr>';
				print '</table>';
			}
			print '</td>';

			print '</tr>';

			// Firstname
			print '<tr><td valign="top">' . $langs->trans("Firstname") . '</td>';
			print '<td>';
			if ($caneditfield && !$fuser->ldap_sid) {
				print '<input size="30" type="text" class="flat" name="prenom" value="' . $fuser->firstname . '">';
			} else {
				print '<input type="hidden" name="prenom" value="' . $fuser->firstname . '">';
				print $fuser->firstname;
			}
			print '</td></tr>';

			// Login
			print '<tr><td valign="top"><span class="fieldrequired">' . $langs->trans("Login") . '</span></td>';
			print '<td>';
			if (!$user->name) {
				print '<input size="12" maxlength="24" type="text" class="flat" name="login" value="' . $fuser->name . '">';
			} else {
				print '<input type="hidden" name="login" value="' . $fuser->name . '">';
				print $fuser->name;
			}
			print '</td>';
			print '</tr>';

			// Pass
			print '<tr><td valign="top">' . $langs->trans("Password") . '</td>';
			print '<td>';
			if ($caneditpassword) {
				$text = '<input size="12" maxlength="32" type="password" class="flat" name="password" value="' . $fuser->pass . '">';
				if ($dolibarr_main_authentication && $dolibarr_main_authentication == 'http') {
					$text = $form->textwithpicto($text, $langs->trans("SpeedealingInHttpAuthenticationSoPasswordUseless", $dolibarr_main_authentication), 1, 'warning');
				}
			} else {
				$text = preg_replace('/./i', '*', $fuser->password_sha);
			}
			print $text;
			print "</td></tr>\n";

			// Administrator
			$name = $fuser->name;
			/* $admins = $fuser->getUserAdmins();

			  if (isset($admins->$name))
			  $fuser->admin = true;
			  else
			  $fuser->admin = false; */
			print '<tr><td valign="top">' . $langs->trans("Administrator") . '</td>';
			print '<td>';
			if ($user->admin && $user->id != $fuser->id) {  // Don't downgrade ourself
				print $form->selectyesno('admin', $fuser->admin, 1);
			} else {
				$yn = yn($fuser->admin);
				print '<input type="hidden" name="admin" value="' . $fuser->admin . '">';
				print $yn;
			}
			print '</td></tr>';

			// Entity by default
			print '<tr><td width="25%" valign="top">' . $langs->trans("Entity") . '</td>';
			print '<td>';
			print '<input type="text" name="default_entity" value="' . $conf->Couchdb->name . '">';
			print '</td></tr>';

			// Tel pro
			print '<tr><td valign="top">' . $langs->trans("PhonePro") . '</td>';
			print '<td>';
			if ($caneditfield && !$fuser->ldap_sid) {
				print '<input size="20" type="text" name="PhonePro" class="flat" value="' . $fuser->phonePro . '">';
			} else {
				print '<input type="hidden" name="PhonePro" value="' . $fuser->phonePro . '">';
				print $fuser->phonePro;
			}
			print '</td></tr>';

			// Tel mobile
			print '<tr><td valign="top">' . $langs->trans("PhoneMobile") . '</td>';
			print '<td>';
			if ($caneditfield && !$fuser->ldap_sid) {
				print '<input size="20" type="text" name="phoneMobile" class="flat" value="' . $fuser->phoneMobile . '">';
			} else {
				print '<input type="hidden" name="phoneMobile" value="' . $fuser->phoneMobile . '">';
				print $fuser->phoneMobile;
			}
			print '</td></tr>';

			// Fax
			print '<tr><td valign="top">' . $langs->trans("Fax") . '</td>';
			print '<td>';
			if ($caneditfield && !$fuser->ldap_sid) {
				print '<input size="20" type="text" name="Fax" class="flat" value="' . $fuser->fax . '">';
			} else {
				print '<input type="hidden" name="Fax" value="' . $fuser->fax . '">';
				print $fuser->fax;
			}
			print '</td></tr>';

			// EMail
			print '<tr><td valign="top" class="fieldrequired">' . $langs->trans("EMail") . '</td>';
			print '<td>';
			if ($caneditfield) {
				print '<input size="40" type="text" name="email" class="flat" value="' . $fuser->email . '">';
			} else {
				print '<input type="hidden" name="email" value="' . $fuser->email . '">';
				print $fuser->email;
			}
			print '</td></tr>';

			// Signature
			print '<tr><td valign="top">' . $langs->trans("Signature") . '</td>';
			print '<td>';
			print '<textarea name="Signature" rows="5" cols="90">' . dol_htmlentitiesbr_decode($fuser->Signature) . '</textarea>';
			print '</td></tr>';

			// Statut
			print '<tr><td valign="top">' . $langs->trans("Status") . '</td>';
			print '<td>';
			print $fuser->getLibStatus();
			print '</td></tr>';

			// Company / Contact
			if ($conf->societe->enabled) {
				print '<tr><td width="25%" valign="top">' . $langs->trans("LinkToCompanyContact") . '</td>';
				print '<td>';
				if ($fuser->societe_id > 0) {
					$societe = new Societe($db);
					$societe->fetch($fuser->societe_id);
					print $societe->getNomUrl(1, '');
					if ($fuser->contact_id) {
						$contact = new Contact($db);
						$contact->fetch($fuser->contact_id);
						print ' / <a href="' . DOL_URL_ROOT . '/contact/fiche.php?id=' . $fuser->contact_id . '">' . img_object($langs->trans("ShowContact"), 'contact') . ' ' . dol_trunc($contact->getFullName($langs), 32) . '</a>';
					}
				} else {
					print $langs->trans("ThisUserIsNot");
				}
				print '</td>';
				print "</tr>\n";
			}

			// Module Adherent
			if ($conf->adherent->enabled) {
				$langs->load("members");
				print '<tr><td width="25%" valign="top">' . $langs->trans("LinkedToSpeedealingMember") . '</td>';
				print '<td>';
				if ($fuser->fk_member) {
					$adh = new Adherent($db);
					$adh->fetch($fuser->fk_member);
					$adh->ref = $adh->login; // Force to show login instead of id
					print $adh->getNomUrl(1);
				} else {
					print $langs->trans("UserNotLinkedToMember");
				}
				print '</td>';
				print "</tr>\n";
			}

			print '</table>';

			print '<br><center>';
			print '<input value="' . $langs->trans("Save") . '" class="button" type="submit" name="save">';
			print ' &nbsp; ';
			print '<input value="' . $langs->trans("Cancel") . '" class="button" type="submit" name="cancel">';
			print '</center>';

			print '</form>';

			print '</div>';
		}

		$ldap->close;
	}
}

print end_box();
print '</div>';

dol_fiche_end();

llxFooter();
?>
