<?php

/* Copyright (C) 2003-2006	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2005		Marc Barilley			<marc@ocebo.com>
 * Copyright (C) 2005-2013	Regis Houssin			<regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani		<acianfa@free.fr>
 * Copyright (C) 2010-2012	Juanjo Menent			<jmenent@2byte.es>
 * Copyright (C) 2011		Philippe Grand			<philippe.grand@atoo-net.com>
 * Copyright (C) 2012		Christophe Battarel		<christophe.battarel@altairis.fr>
 * Copyright (C) 2012		Marcos García			<marcosgdf@gmail.com>
 * Copyright (C) 2012		David Moothen			<dmoothen@gmail.com>
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

if (!empty($_GET["json"]) && !defined('NOTOKENRENEWAL'))
	define('NOTOKENRENEWAL', '1'); // Disables token renewal

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formorder.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/core/modules/commande/modules_commande.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
if (!empty($conf->propal->enabled))
	require DOL_DOCUMENT_ROOT . '/propal/class/propal.class.php';
if (!empty($conf->projet->enabled)) {
	require DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
	require DOL_DOCUMENT_ROOT . '/core/lib/project.lib.php';
}


/* Loading langs ************************************************************ */


$langs->load('orders');
$langs->load('sendings');
$langs->load('companies');
$langs->load('bills');
$langs->load('propal');
$langs->load('deliveries');
$langs->load('products');
if (!empty($conf->margin->enabled))
	$langs->load('margins');

//$product = new Product($db);
//$res = $product->getView('list', array('startkey' => 'd', 'endkey' => 'dZ'));
//echo '<pre>' . print_r($res, true) . '</pre>';die;


/* Post params ************************************************************** */


$id = GETPOST('id', 'alpha');
$action = (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$confirm = GETPOST('confirm');
$lineid = GETPOST('lineid', 'alpha');
$origin = GETPOST('origin', 'alpha');
$originid = (GETPOST('originid', 'alpha') ? GETPOST('originid', 'alpha') : GETPOST('origin_id', 'alpha')); // For backward compatibility

$title = $langs->trans('Order');

$object = new Commande($db);
$soc = new Societe($db);
if (!empty($id)) {
	$object->fetch($id);
	$object->fetch_thirdparty();
	//print_r($object->thirdparty);
	$soc->load($object->client->id);
}

//echo '<pre>' . print_r($object->getLinkedObject(), true) . '</pre>';die;
// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('ordercard'));


if (!empty($_GET['json'])) {
	$output = array(
		"sEcho" => intval($_GET['sEcho']),
		"iTotalRecords" => 0,
		"iTotalDisplayRecords" => 0,
		"aaData" => array()
	);

//    $keystart[0] = $user->id;
//    $keyend[0] = $user->id;
//    $keyend[1] = new stdClass();

	/* $params = array('startkey' => array($user->id, mktime(0, 0, 0, date("m") - 1, date("d"), date("Y"))),
	  'endkey' => array($user->id, mktime(0, 0, 0, date("m") + 1, date("d"), date("Y")))); */

	try {
		$result = $object->getView($_GET["json"], array('key' => $id));
	} catch (Exception $exc) {
		print $exc->getMessage();
	}

	$iTotal = count($result->rows);
	$output["iTotalRecords"] = $iTotal;
	$output["iTotalDisplayRecords"] = $iTotal;
	$i = 0;
	if (count($result->rows))
		foreach ($result->rows as $aRow) {
			$output["aaData"][] = $aRow->value;
		}

	header('Content-type: application/json');
	echo json_encode($output);
	exit;
}

/* Actions ****************************************************************** */


if ($action == 'add' && $user->rights->commande->creer) {

	$datecommande = dol_mktime(12, 0, 0, GETPOST('remonth'), GETPOST('reday'), GETPOST('reyear'));
	$datelivraison = dol_mktime(12, 0, 0, GETPOST('liv_month'), GETPOST('liv_day'), GETPOST('liv_year'));

	$object->socid = GETPOST('socid');
	$object->datec = $datecommande;
	$object->note = GETPOST('note');
	$object->note_public = GETPOST('note_public');
	$object->source = GETPOST('source_id');
	$object->fk_project = GETPOST('projectid');
	$object->ref_client = GETPOST('ref_client');
	$object->modelpdf = GETPOST('model');
	$object->cond_reglement_code = GETPOST('cond_reglement_code');
	$object->mode_reglement_code = GETPOST('mode_reglement_code');
	$object->availability_code = GETPOST('availability_code');
	$object->demand_reason_code = GETPOST('demand_reason_code');
	$object->date_livraison = $datelivraison;
	$object->fk_delivery_address = GETPOST('fk_address');
	$object->contactid = GETPOST('contactidp');

	// If creation from another object of another module (Example: origin=propal, originid=1)
	if ($_POST['origin'] && $_POST['originid']) {
		// Parse element/subelement (ex: project_task)
		$element = $subelement = $_POST['origin'];
		if (preg_match('/^([^_]+)_([^_]+)/i', $_POST['origin'], $regs)) {
			$element = $regs[1];
			$subelement = $regs[2];
		}

		// For compatibility
		if ($element == 'order') {
			$element = $subelement = 'commande';
		}
		if ($element == 'propal') {
			$element = 'propal/propal';
			$subelement = 'propal';
		}
		if ($element == 'contract') {
			$element = $subelement = 'contrat';
		}

		$object->origin = $_POST['origin'];
		$object->origin_id = $_POST['originid'];

		// Possibility to add external linked objects with hooks
//        $object->linked_objects[$object->origin] = $object->origin_id;
		$object->linked_objects[] = array('type' => $object->origin, 'id' => $object->origin_id);
		if (is_array($_POST['other_linked_objects']) && !empty($_POST['other_linked_objects'])) {
			$object->linked_objects = array_merge($object->linked_objects, $_POST['other_linked_objects']);
		}

		$id = $object->create($user);

		if (!(empty($id))) {
			dol_include_once('/' . $element . '/class/' . $subelement . '.class.php');

			$classname = ucfirst($subelement);
			$srcobject = new $classname($db);

			dol_syslog("Try to find source object origin=" . $object->origin . " originid=" . $object->origin_id . " to add lines");
			$result = $srcobject->fetch($object->origin_id);
			if (!empty($result)) {

				// Hooks
				$parameters = array('objFrom' => $srcobject);
				$reshook = $hookmanager->executeHooks('createFrom', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
				if ($reshook < 0)
					$error++;
			}
			else {
				$mesg = $srcobject->error;
				$error++;
			}
		} else {
			$mesg = $object->error;
			$error++;
		}
	} else {
		$id = $object->create($user);
	}

	if (!empty($id)) {
		header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $id);
		exit;
	}
} else if ($action == 'update' && $user->rights->commande->creer) {

	$datecommande = dol_mktime(12, 0, 0, GETPOST('remonth'), GETPOST('reday'), GETPOST('reyear'));
	$datelivraison = dol_mktime(12, 0, 0, GETPOST('liv_month'), GETPOST('liv_day'), GETPOST('liv_year'));

	$object->socid = GETPOST('socid');
	$object->datec = $datecommande;
	$object->note = GETPOST('note');
	$object->note_public = GETPOST('note_public');
	$object->source = GETPOST('source_id');
	$object->fk_project = GETPOST('projectid');
	$object->ref_client = GETPOST('ref_client');
	$object->modelpdf = GETPOST('model');
	$object->cond_reglement_code = GETPOST('cond_reglement_code');
	$object->mode_reglement_code = GETPOST('mode_reglement_code');
	$object->availability_code = GETPOST('availability_code');
	$object->demand_reason_code = GETPOST('demand_reason_code');
	$object->date_livraison = $datelivraison;
	$object->fk_delivery_address = GETPOST('fk_address');
	$object->contactid = GETPOST('contactidp');

	$id = $object->update();
	if (!empty($id)) {
		header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $id);
		exit;
	}
} else if ($action == 'confirm_delete' && $confirm == 'yes' && $user->rights->commande->supprimer) {
	$result = $object->delete($user);
	if ($result > 0) {
		header('Location: list.php');
		exit;
	} else {
		$mesg = '<div class="error">' . $object->error . '</div>';
	}
} else if ($action == 'remove_file') {
	$langs->load("other");
	$comref = dol_sanitizeFileName($object->ref);
	$file = $conf->commande->dir_output . '/' . GETPOST('file'); // Do not use urldecode here ($_GET and $_REQUEST are already decoded by PHP).
	$ret = dol_delete_file($file);
} else if ($action == 'builddoc') { // In get or post
	/*
	 * Generate order document
	 * define into /core/modules/commande/modules_commande.php
	 */

	// Sauvegarde le dernier modele choisi pour generer un document
	if ($_REQUEST['model']) {
		$object->setDocModel($user, $_REQUEST['model']);
	}

	// Define output language
	$outputlangs = $langs;
	$newlang = '';
	if ($conf->global->MAIN_MULTILANGS && empty($newlang) && !empty($_REQUEST['lang_id']))
		$newlang = $_REQUEST['lang_id'];
	if ($conf->global->MAIN_MULTILANGS && empty($newlang))
		$newlang = $object->client->default_lang;
	if (!empty($newlang)) {
		$outputlangs = new Translate();
		$outputlangs->setDefaultLang($newlang);
	}

	$object->modelpdf = "bl"; // TODO Automatic mode
	$result = commande_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);

	if ($result <= 0) {
		dol_print_error($db, $result);
		exit;
	} else {
		header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . (empty($conf->global->MAIN_JUMP_TAG) ? '' : '#builddoc'));
		exit;
	}
} else if ($action == 'confirm_validate' && $confirm == 'yes' && $user->rights->commande->valider) {
	$idwarehouse = GETPOST('idwarehouse');

	// Check parameters
	if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1)) {
		if (!$idwarehouse || $idwarehouse == -1) {
			$error++;
			$mesgs[] = '<div class="error">' . $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("Warehouse")) . '</div>';
			$action = '';
		}
	}

	if (!$error) {
		$result = $object->valid($user, $idwarehouse);
		if ($result >= 0) {
			// Define output language
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && !empty($_REQUEST['lang_id']))
				$newlang = $_REQUEST['lang_id'];
			if ($conf->global->MAIN_MULTILANGS && empty($newlang))
				$newlang = $object->client->default_lang;
			if (!empty($newlang)) {
				$outputlangs = new Translate();
				$outputlangs->setDefaultLang($newlang);
			}
			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
				commande_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
		}
	}
}


else if ($action == 'confirm_modif' && $user->rights->commande->creer) {
	$idwarehouse = GETPOST('idwarehouse');

	// Check parameters
	if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1)) {
		if (!$idwarehouse || $idwarehouse == -1) {
			$error++;
			$mesgs[] = '<div class="error">' . $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("Warehouse")) . '</div>';
			$action = '';
		}
	}

	if (!$error) {
		$result = $object->set_draft($user, $idwarehouse);
		if ($result >= 0) {
			// Define output language
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && !empty($_REQUEST['lang_id']))
				$newlang = $_REQUEST['lang_id'];
			if ($conf->global->MAIN_MULTILANGS && empty($newlang))
				$newlang = $object->client->default_lang;
			if (!empty($newlang)) {
				$outputlangs = new Translate();
				$outputlangs->setDefaultLang($newlang);
			}
			if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
				$ret = $object->fetch($object->id); // Reload to get new records
				commande_pdf_create($db, $object, $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
			}
		}
	}
} else if ($action == 'confirm_cancel' && $confirm == 'yes' && $user->rights->commande->valider) {
	$idwarehouse = GETPOST('idwarehouse');

	// Check parameters
	if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1)) {
		if (!$idwarehouse || $idwarehouse == -1) {
			$error++;
			$mesgs[] = '<div class="error">' . $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("Warehouse")) . '</div>';
			$action = '';
		}
	}

	if (!$error) {
		$result = $object->cancel($idwarehouse);
	}
} else if ($action == 'confirm_shipped' && $confirm == 'yes' && $user->rights->commande->cloturer) {
	$result = $object->cloture($user);
	if ($result < 0)
		$mesgs = $object->errors;
}

// Reopen a closed order
else if ($action == 'reopen' && $user->rights->commande->creer) {
	if ($object->Status == "TO_BILL" || $object->Status == "PROCESSED") {
		$result = $object->set_reopen($user);
		if ($result > 0) {
			header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id);
			exit;
		} else {
			$mesg = '<div class="error">' . $object->error . '</div>';
		}
	}
} else if ($action == 'classifybilled' && $user->rights->commande->creer) {
	$ret = $object->classifyBilled();
}


/*
 * Add file in email form
 */
if (GETPOST('addfile')) {
	// Set tmp user directory TODO Use a dedicated directory for temp mails files
	$vardir = $conf->user->dir_output . "/" . $user->id;
	$upload_dir_tmp = $vardir . '/temp';

	dol_add_file_process($upload_dir_tmp, 0, 0);
	$action = 'presend';
}

/*
 * Remove file in email form
 */
if (GETPOST('removedfile')) {
	require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

	// Set tmp user directory
	$vardir = $conf->user->dir_output . "/" . $user->id;
	$upload_dir_tmp = $vardir . '/temp';

	// TODO Delete only files that was uploaded from email form
	dol_remove_file_process(GETPOST('removedfile'), 0);
	$action = 'presend';
}

/*
 * Send mail
 */
if ($action == 'send' && !GETPOST('addfile') && !GETPOST('removedfile') && !GETPOST('cancel')) {
	$langs->load('mails');

	if ($object->id > 0) {
		//        $ref = dol_sanitizeFileName($object->ref);
		//        $file = $conf->commande->dir_output . '/' . $ref . '/' . $ref . '.pdf';
		//        if (is_readable($file))
		//        {
		if (GETPOST('sendto')) {
			// Le destinataire a ete fourni via le champ libre
			$sendto = GETPOST('sendto');
			$sendtoid = 0;
		} elseif (GETPOST('receiver') != '-1') {
			// Recipient was provided from combo list
			if (GETPOST('receiver') == 'thirdparty') { // Id of third party
				$sendto = $object->client->email;
				$sendtoid = 0;
			} else { // Id du contact
				$sendto = $object->client->contact_get_property(GETPOST('receiver'), 'email');
				$sendtoid = GETPOST('receiver');
			}
		}

		if (dol_strlen($sendto)) {
			$langs->load("commercial");

			$from = GETPOST('fromname') . ' <' . GETPOST('frommail') . '>';
			$replyto = GETPOST('replytoname') . ' <' . GETPOST('replytomail') . '>';
			$message = GETPOST('message');
			$sendtocc = GETPOST('sendtocc');
			$deliveryreceipt = GETPOST('deliveryreceipt');

			if ($action == 'send') {
				if (dol_strlen(GETPOST('subject')))
					$subject = GETPOST('subject');
				else
					$subject = $langs->transnoentities('Order') . ' ' . $object->ref;
				$actiontypecode = 'AC_COM';
				$actionmsg = $langs->transnoentities('MailSentBy') . ' ' . $from . ' ' . $langs->transnoentities('To') . ' ' . $sendto . ".\n";
				if ($message) {
					$actionmsg.=$langs->transnoentities('MailTopic') . ": " . $subject . "\n";
					$actionmsg.=$langs->transnoentities('TextUsedInTheMessageBody') . ":\n";
					$actionmsg.=$message;
				}
				$actionmsg2 = $langs->transnoentities('Action' . $actiontypecode);
			}

			// Create form object
			include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
			$formmail = new FormMail($db);

			$attachedfiles = $formmail->get_attached_files();
			$filepath = $attachedfiles['paths'];
			$filename = $attachedfiles['names'];
			$mimetype = $attachedfiles['mimes'];

			// Send mail
			require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
			$mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt);
			if ($mailfile->error) {
				$mesg = '<div class="error">' . $mailfile->error . '</div>';
			} else {
				$result = $mailfile->sendfile();
				if ($result) {
					$mesg = $langs->trans('MailSuccessfulySent', $mailfile->getValidAddress($from, 2), $mailfile->getValidAddress($sendto, 2)); // Must not contains "

					$error = 0;

					// Initialisation donnees
					$object->sendtoid = $sendtoid;
					$object->actiontypecode = $actiontypecode;
					$object->actionmsg = $actionmsg;
					$object->actionmsg2 = $actionmsg2;
					$object->fk_element = $object->id;
					$object->elementtype = $object->element;

					// Appel des triggers
					include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
					$interface = new Interfaces($db);
					$result = $interface->run_triggers('ORDER_SENTBYMAIL', $object, $user, $langs, $conf);
					if ($result < 0) {
						$error++;
						$this->errors = $interface->errors;
					}
					// Fin appel triggers

					if ($error) {
						dol_print_error($db);
					} else {
						// Redirect here
						// This avoid sending mail twice if going out and then back to page
						header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&mesg=' . urlencode($mesg));
						exit;
					}
				} else {
					$langs->load("other");
					$mesg = '<div class="error">';
					if ($mailfile->error) {
						$mesg.=$langs->trans('ErrorFailedToSendMail', $from, $sendto);
						$mesg.='<br>' . $mailfile->error;
					} else {
						$mesg.='No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS';
					}
					$mesg.='</div>';
				}
			}
			/*            }
			  else
			  {
			  $langs->load("other");
			  $mesg='<div class="error">'.$langs->trans('ErrorMailRecipientIsEmpty').' !</div>';
			  $action='presend';
			  dol_syslog('Recipient email is empty');
			  } */
		} else {
			$langs->load("errors");
			$mesg = '<div class="error">' . $langs->trans('ErrorCantReadFile', $file) . '</div>';
			dol_syslog('Failed to read file: ' . $file);
		}
	} else {
		$langs->load("other");
		$mesg = '<div class="error">' . $langs->trans('ErrorFailedToReadEntity', $langs->trans("Order")) . '</div>';
		dol_syslog($langs->trans('ErrorFailedToReadEntity', $langs->trans("Order")));
	}
}




/* View ********************************************************************* */

$form = new Form($db);
$formfile = new FormFile($db);
$formorder = new FormOrder($db);

llxHeader('', $title);
print_fiche_titre($title . " " . $object->ref_client);


$formconfirm = null;

if ($action == 'delete') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteOrder'), $langs->trans('ConfirmDeleteOrder'), 'confirm_delete', '', 0, 1);
} else if ($action == 'ask_deleteline') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '&lineid=' . $lineid, $langs->trans('DeleteProductLine'), $langs->trans('ConfirmDeleteProductLine'), 'confirm_deleteline', '', 0, 1);
} else if ($action == 'validate') {
	// on verifie si l'objet est en numerotation provisoire
	$numref = $object->ref;
	$text = $langs->trans('ConfirmValidateOrder', $numref);
	if (!empty($conf->notification->enabled)) {
		require_once DOL_DOCUMENT_ROOT . '/core/class/notify.class.php';
		$notify = new Notify($db);
		$text.='<br>';
		$text.=$notify->confirmMessage('NOTIFY_VAL_ORDER', $object->socid);
	}
	$formquestion = array();
	if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1)) {
		$langs->load("stocks");
		require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
		$formproduct = new FormProduct($db);
		$formquestion = array(
			//'text' => $langs->trans("ConfirmClone"),
			//array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
			//array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
			array('type' => 'other', 'name' => 'idwarehouse', 'label' => $langs->trans("SelectWarehouseForStockDecrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse'), 'idwarehouse', '', 1)));
	}

	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('ValidateOrder'), $text, 'confirm_validate', $formquestion, 0, 1, 220);
} else if ($action == 'modify') {
	$text = $langs->trans('ConfirmUnvalidateOrder', $object->ref);
	$formquestion = array();
	if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1)) {
		$langs->load("stocks");
		require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
		$formproduct = new FormProduct($db);
		$formquestion = array(
			//'text' => $langs->trans("ConfirmClone"),
			//array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
			//array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
			array('type' => 'other', 'name' => 'idwarehouse', 'label' => $langs->trans("SelectWarehouseForStockIncrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse'), 'idwarehouse', '', 1)));
	}

	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('UnvalidateOrder'), $text, 'confirm_modif', $formquestion, "yes", 1, 220);
} else if ($action == 'cancel') {
	$text = $langs->trans('ConfirmCancelOrder', $object->ref);
	$formquestion = array();
	if (!empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) && $object->hasProductsOrServices(1)) {
		$langs->load("stocks");
		require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
		$formproduct = new FormProduct($db);
		$formquestion = array(
			//'text' => $langs->trans("ConfirmClone"),
			//array('type' => 'checkbox', 'name' => 'clone_content',   'label' => $langs->trans("CloneMainAttributes"),   'value' => 1),
			//array('type' => 'checkbox', 'name' => 'update_prices',   'label' => $langs->trans("PuttingPricesUpToDate"),   'value' => 1),
			array('type' => 'other', 'name' => 'idwarehouse', 'label' => $langs->trans("SelectWarehouseForStockIncrease"), 'value' => $formproduct->selectWarehouses(GETPOST('idwarehouse'), 'idwarehouse', '', 1)));
	}

	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('Cancel'), $text, 'confirm_cancel', $formquestion, 0, 1);
} else if ($action == 'shipped') {
	$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('CloseOrder'), $langs->trans('ConfirmCloseOrder'), 'confirm_shipped', '', 0, 1);
}


print $formconfirm;

print '<div class="with-padding" >';
print '<div class="columns" >';


/* Create View */


if (($action == 'create' || $action == 'edit') && $user->rights->commande->creer) {

	$objectsrc = null;
	if (GETPOST('origin') && GETPOST('originid')) {
		// Parse element/subelement (ex: project_task)
		$element = $subelement = GETPOST('origin');
		if (preg_match('/^([^_]+)_([^_]+)/i', GETPOST('origin'), $regs)) {
			$element = $regs[1];
			$subelement = $regs[2];
		}

		if ($element == 'project') {
			$projectid = GETPOST('originid');
		} else {
			// For compatibility
			if ($element == 'order' || $element == 'commande') {
				$element = $subelement = 'commande';
			}
			if ($element == 'propal') {
				$element = 'propal/propal';
				$subelement = 'propal';
			}
			if ($element == 'contract') {
				$element = $subelement = 'contrat';
			}

			dol_include_once('/' . $element . '/class/' . $subelement . '.class.php');

			$classname = ucfirst($subelement);
			$objectsrc = new $classname($db);
			$objectsrc->fetch(GETPOST('originid'));
			if (empty($objectsrc->lines) && method_exists($objectsrc, 'fetch_lines'))
				$objectsrc->fetch_lines();
			$objectsrc->fetch_thirdparty();

			$projectid = (!empty($objectsrc->fk_project) ? $object->fk_project : '');
			$ref_client = (!empty($objectsrc->ref_client) ? $objectsrc->ref_client : '');

			$soc = $objectsrc->thirdparty;
			$cond_reglement_code = (!empty($objectsrc->cond_reglement_code) ? $objectsrc->cond_reglement_code : (!empty($soc->cond_reglement_code) ? $soc->cond_reglement_code : 'RECEP'));
			$mode_reglement_code = (!empty($objectsrc->mode_reglement_code) ? $objectsrc->mode_reglement_code : (!empty($soc->mode_reglement_code) ? $soc->mode_reglement_code : 'TIP'));
			$availability_code = (!empty($objectsrc->availability_code) ? $objectsrc->availability_code : (!empty($soc->availability_code) ? $soc->availability_code : 'AV_NOW'));
			$demand_reason_code = (!empty($objectsrc->demand_reason_code) ? $objectsrc->demand_reason_code : (!empty($soc->demand_reason_code) ? $soc->demand_reason_code : 'SRC_EMAIL'));
			$remise_percent = (!empty($objectsrc->remise_percent) ? $objectsrc->remise_percent : (!empty($soc->remise_percent) ? $soc->remise_percent : 0));
			$remise_absolue = (!empty($objectsrc->remise_absolue) ? $objectsrc->remise_absolue : (!empty($soc->remise_absolue) ? $soc->remise_absolue : 0));
			$dateinvoice = empty($conf->global->MAIN_AUTOFILL_DATE) ? -1 : 0;

			$note_private = (!empty($objectsrc->note) ? $objectsrc->note : (!empty($objectsrc->note_private) ? $objectsrc->note_private : ''));
			$note_public = (!empty($objectsrc->note_public) ? $objectsrc->note_public : '');

			$socid = (!empty($objectsrc->client->id) ? $objectsrc->client->id : $object->client->id);

			// Object source contacts list
			//$srccontactslist = $objectsrc->liste_contact(-1,'external',1);
		}
	}

	//print start_box($title, $object->fk_extrafields->ico);
	print column_start();
	print '<form name="crea_commande" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
	print '<input type="hidden" name="action" value="' . ($action == 'create' ? 'add' : 'update') . '">';
	print '<input type="hidden" name="id" value="' . $id . '">';
	print '<input type="hidden" name="origin" value="' . $origin . '">';
	print '<input type="hidden" name="originid" value="' . $originid . '">';

	print '<table class="border" width="100%">';

	// Reference
	print '<tr><td class="fieldrequired">' . $langs->trans('Ref') . '</td><td colspan="2">' . ($action == 'edit' ? $object->ref : $langs->trans("Draft")) . '</td></tr>';

	// Reference client
	print '<tr><td>' . $langs->trans('RefCustomer') . '</td><td colspan="2">';
	print '<input type="text" name="ref_client" value="' . $ref_client . '"></td>';
	print '</tr>';


	// Client
	print '<tr><td class="fieldrequired">' . $langs->trans('Customer') . '</td><td colspan="2">' . $form->select_company($socid, "socid") . '</td></tr>';

	// Contact de la commande
//    print "<tr><td>".$langs->trans("DefaultContact").'</td><td colspan="2">';
//    $form->select_contacts($soc->id,$setcontact,'contactidp',1,$srccontactslist);
//    print '</td></tr>';
	// Date
	print '<tr><td class="fieldrequired">' . $langs->trans('Date') . '</td><td colspan="2">';
	$form->select_date($object->date, 're', '', '', '', "crea_commande", 1, 1);
	print '</td></tr>';

	// Date de livraison
	print "<tr><td>" . $langs->trans("DeliveryDate") . '</td><td colspan="2">';
	if ($action == 'edit') {
		$datedelivery = $object->date_livraison;
	} else if (!empty($conf->global->DATE_LIVRAISON_WEEK_DELAY)) {
		$datedelivery = time() + ((7 * $conf->global->DATE_LIVRAISON_WEEK_DELAY) * 24 * 60 * 60);
	} else {
		$datedelivery = empty($conf->global->MAIN_AUTOFILL_DATE) ? -1 : 0;
	}
	$form->select_date($datedelivery, 'liv_', '', '', '', "crea_commande", 1, 1);
	print "</td></tr>";

	// Conditions de reglement
	print '<tr><td nowrap="nowrap">' . $langs->trans('PaymentConditionsShort') . '</td><td colspan="2">';
	print $object->select_fk_extrafields('cond_reglement_code', 'cond_reglement_code', $cond_reglement_code);
	print '</td></tr>';

	// Delivery delay
	print '<tr><td>' . $langs->trans('AvailabilityPeriod') . '</td><td colspan="2">';
	print $object->select_fk_extrafields('availability_code', 'availability_code', $availability_code);
	print '</td></tr>';

	// Mode de reglement
	print '<tr><td nowrap="nowrap">' . $langs->trans('PaymentMode') . '</td><td colspan="2">';
	print $object->select_fk_extrafields('mode_reglement_code', 'mode_reglement_code', $mode_reglement_code);
	print '</td></tr>';

	// What trigger creation
	print '<tr><td>' . $langs->trans('Source') . '</td><td colspan="2">';
	print $object->select_fk_extrafields('demand_reason_code', 'demand_reason_code', $demand_reason_code);
	print '</td></tr>';

	// Project
	if (!empty($conf->projet->enabled)) {
		$projectid = 0;
		if ($origin == 'project')
			$projectid = ($originid ? $originid : 0);
		print '<tr><td>' . $langs->trans('Project') . '</td><td colspan="2">';
		$numprojet = select_projects($soc->id, $projectid);
		if ($numprojet == 0) {
			print ' &nbsp; <a href="' . DOL_URL_ROOT . '/projet/fiche.php?socid=' . $soc->id . '&action=create">' . $langs->trans("AddProject") . '</a>';
		}
		print '</td></tr>';
	}

	// Other attributes
	$parameters = array('objectsrc' => $objectsrc, 'colspan' => ' colspan="3"');
	$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action); // Note that $action and $object may have been modified by hook
	if (empty($reshook) && !empty($extrafields->attribute_label)) {
		foreach ($extrafields->attribute_label as $key => $label) {
			$value = (isset($_POST["options_" . $key]) ? $_POST["options_" . $key] : $object->array_options["options_" . $key]);
			print "<tr><td>" . $label . '</td><td colspan="3">';
			print $extrafields->showInputField($key, $value);
			print '</td></tr>' . "\n";
		}
	}

	// Template to use by default
	print '<tr><td>' . $langs->trans('Model') . '</td>';
	print '<td colspan="2">';
	include_once DOL_DOCUMENT_ROOT . '/commande/core/modules/commande/modules_commande.php';
	$liste = ModelePDFCommandes::liste_modeles($db);
	print $form->selectarray('model', $liste, $conf->global->COMMANDE_ADDON_PDF);
	print "</td></tr>";

	// Note publique
	print '<tr>';
	print '<td class="border" valign="top">' . $langs->trans('NotePublic') . '</td>';
	print '<td valign="top" colspan="2">';
	$doleditor = new DolEditor('note_public', $note_public, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
	print $doleditor->Create(1);
	print '</td></tr>';

	// Note privee
	if (!$user->societe_id) {
		print '<tr>';
		print '<td class="border" valign="top">' . $langs->trans('NotePrivate') . '</td>';
		print '<td valign="top" colspan="2">';
		$doleditor = new DolEditor('note', $note_private, '', 80, 'dolibarr_notes', 'In', 0, false, true, ROWS_3, 70);
		print $doleditor->Create(1);
		print '</td></tr>';
	}

	if (is_object($objectsrc)) {
		// TODO for compatibility
		if ($_GET['origin'] == 'contrat') {
			// Calcul contrat->price (HT), contrat->total (TTC), contrat->tva
			$objectsrc->remise_absolue = $remise_absolue;
			$objectsrc->remise_percent = $remise_percent;
			$objectsrc->update_price(1);
		}

		print "\n<!-- " . $classname . " info -->";
		print "\n";
		print '<input type="hidden" name="amount"         value="' . $objectsrc->total_ht . '">' . "\n";
		print '<input type="hidden" name="total"          value="' . $objectsrc->total_ttc . '">' . "\n";
		print '<input type="hidden" name="tva"            value="' . $objectsrc->total_tva . '">' . "\n";
		print '<input type="hidden" name="origin"         value="' . $objectsrc->element . '">';
		print '<input type="hidden" name="originid"       value="' . $objectsrc->id . '">';

		$newclassname = $classname;
		if ($newclassname == 'Propal')
			$newclassname = 'CommercialProposal';
		print '<tr><td>' . $langs->trans($newclassname) . '</td><td colspan="2">' . $objectsrc->getNomUrl(1) . '</td></tr>';
		print '<tr><td>' . $langs->trans('TotalHT') . '</td><td colspan="2">' . price($objectsrc->total_ht) . '</td></tr>';
		print '<tr><td>' . $langs->trans('TotalVAT') . '</td><td colspan="2">' . price($objectsrc->total_tva) . "</td></tr>";
		if ($mysoc->country_code == 'ES') {
			if ($mysoc->localtax1_assuj == "1") { //Localtax1 RE
				print '<tr><td>' . $langs->transcountry("AmountLT1", $mysoc->country_code) . '</td><td colspan="2">' . price($objectsrc->total_localtax1) . "</td></tr>";
			}

			if ($mysoc->localtax2_assuj == "1") { //Localtax2 IRPF
				print '<tr><td>' . $langs->transcountry("AmountLT2", $mysoc->country_code) . '</td><td colspan="2">' . price($objectsrc->total_localtax2) . "</td></tr>";
			}
		}
		print '<tr><td>' . $langs->trans('TotalTTC') . '</td><td colspan="2">' . price($objectsrc->total_ttc) . "</td></tr>";
	}

	print '</table>';

	// Button "Create Draft"
	print '<br><center><input type="submit" class="button" name="bouton" value="' . ($action == 'edit' ? $langs->trans('Modify') : $langs->trans('CreateDraft')) . '"></center>';

	print '</form>';
	print column_end();
} else {
	print '<br>';

	/* Default View */
	print column_start("six");

	//print '<fieldset class="fieldset">';

	dol_fiche_head();

	print '<table class="table responsive-table">';

	// Ref
	print '<tr><td width="30%">' . $langs->trans('Ref') . '</td>';
	print '<td colspan="3">';
	print $object->ref;
	print '</td>';
	print '</tr>';

	// Societe
	print '<tr><td>' . $langs->trans('Company') . '</td>';
	print '<td colspan="3">' . $soc->getNomUrl(1) . '</td>';
	print '</tr>';

	// Ref commande client
//    print '<tr><td>' . $langs->trans('RefCustomer') . '</td>';
//    print '<td colspan="3">' . $object->ref_client . '</td>';
//    print '</tr>';
	print '<tr><td>' . $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "text") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "text");
	print '</td>';
	print '</tr>';



	// Date
//    print '<tr><td>' . $langs->trans('Date') . '</td>';
//    print '<td colspan="3">' . ($object->date ? dol_print_date($object->date, 'daytext') : '&nbsp;') . '</td>';
//    print '</tr>';
	print '<tr><td>' . $form->editfieldkey("Date", 'datec', $object->datec, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "datepicker") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("Date", 'datec', $object->datec, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "datepicker");
	print '</td>';
	print '</tr>';

	// Delivery date planed
//    print '<tr><td>' . $langs->trans('DateDeliveryPlanned') . '</td>';
//    print '<td colspan="3">' . ($object->date_livraison ? dol_print_date($object->date_livraison, 'daytext') : '&nbsp;') . '</td>';
//    print '</tr>';
	//$object->date_livraison = date("c", $object->date_livraison->sec);
	print '<tr><td>' . $form->editfieldkey("DateDeliveryPlanned", 'date_livraison', $object->date_livraison, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "datepicker") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("DateDeliveryPlanned", 'date_livraison', $object->date_livraison, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "datepicker");
	print '</td>';
	print '</tr>';

	// Terms of payment
//    print '<tr><td>' . $langs->trans('PaymentConditionsShort') . '</td>';
//    print '<td colspan="3">' . $object->getExtraFieldLabel('cond_reglement_code') . '</td>';
//    print '</tr>';
	print '<tr><td>' . $form->editfieldkey("PaymentConditionsShort", 'cond_reglement_code', $object->cond_reglement_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("PaymentConditionsShort", 'cond_reglement_code', $object->cond_reglement_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select");
	print '</td>';
	print '</tr>';

	// Mode of payment
//    print '<tr><td>' . $langs->trans('PaymentMode') . '</td>';
//    print '<td colspan="3">' . $object->getExtraFieldLabel('mode_reglement_code') . '</td>';
//    print '</tr>';
	print '<tr><td>' . $form->editfieldkey("PaymentMode", 'mode_reglement_code', $object->mode_reglement_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("PaymentMode", 'mode_reglement_code', $object->mode_reglement_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select");
	print '</td>';
	print '</tr>';

	// Availability
//    print '<tr><td>' . $langs->trans('AvailabilityPeriod') . '</td>';
//    print '<td colspan="3">' . $object->getExtraFieldLabel('availability_code') . '</td>';
//    print '</tr>';
	print '<tr><td>' . $form->editfieldkey("AvailabilityPeriod", 'availability_code', $object->availability_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("AvailabilityPeriod", 'availability_code', $object->availability_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select");
	print '</td>';
	print '</tr>';

	// Source
//    print '<tr><td>' . $langs->trans('Source') . '</td>';
//    print '<td colspan="3">' . $object->getExtraFieldLabel('demand_reason_code') . '</td>';
//    print '</tr>';
	print '<tr><td>' . $form->editfieldkey("Source", 'demand_reason_code', $object->demand_reason_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("Source", 'demand_reason_code', $object->demand_reason_code, $object, $user->rights->commande->creer && $object->Status == "DRAFT", "select");
	print '</td>';
	print '</tr>';

	// Margin Infos
	if (!empty($conf->margin->enabled)) {
		print '<td valign="top" width="50%" rowspan="4">';
		$object->displayMarginInfos();
		print '</td>';
	}
	print '</tr>';

	// Statut
//    print '<tr><td>' . $langs->trans('Status') . '</td>';
//    print '<td colspan="2">' . $object->getExtraFieldLabel('Status') . '</td>';
//    print '</tr>';
	$status = $object->Status;
	print '<tr><td>' . $form->editfieldkey("Status", 'Status', $object->Status, $object, $user->rights->commande->creer && !$object->fk_extrafields->fields->Status->values->$status->notEditable, "select") . '</td>';
	print '<td td colspan="5">';
	print $form->editfieldval("Status", 'Status', $object->Status, $object, $user->rights->commande->creer && !$object->fk_extrafields->fields->Status->values->$status->notEditable, "select");
	print '</td>';
	print '</tr>';

	print '</table>';

	/*
	 * Boutons actions
	 */
	if ($action != 'presend') {
		if ($user->societe_id == 0 && $action <> 'editline') {
			print '<div class="tabsAction">';
			print '<div class="button-height">';
			print '<span class="button-group">';

			if ($user->rights->commande->creer) {
				print '<a class="button icon-pencil" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=edit">' . $langs->trans("Modify") . '</a>' . "\n";
			}

			// Ship
			$numshipping = 0;
			if (!empty($conf->expedition->enabled)) {
				$numshipping = $object->nb_expedition();

				if ($object->statut > 0 && $object->statut < 3 && $object->getNbOfProductsLines() > 0) {
					if (($conf->expedition_bon->enabled && $user->rights->expedition->creer) || ($conf->livraison_bon->enabled && $user->rights->expedition->livraison->creer)) {
						if ($user->rights->expedition->creer) {
							print '<a class="butAction" href="' . DOL_URL_ROOT . '/expedition/shipment.php?id=' . $object->id . '">' . $langs->trans('ShipProduct') . '</a>';
						} else {
							print '<a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("NotAllowed")) . '">' . $langs->trans('ShipProduct') . '</a>';
						}
					} else {
						$langs->load("errors");
						print '<a class="butActionRefused" href="#" title="' . dol_escape_htmltag($langs->trans("ErrorModuleSetupNotComplete")) . '">' . $langs->trans('ShipProduct') . '</a>';
					}
				}
			}

			// Validate
			if (($object->Status == "DRAFT" || $object->Status == "AUTO") && $user->rights->commande->valider) {
				print '<a class="button icon-tick" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=validate">' . $langs->trans('Validate') . '</a>';
			}

			// Clone
//            if ($user->rights->commande->creer) {
//                print '<p class="button-height right">';
//                print '<a class="button icon-pages" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=clone">' . $langs->trans("ToClone") . '</a>';
//                print "</p>";
//            }
			// Classify billed
			if ($object->Status == "TO_BILL" && $user->rights->commande->cloturer) {
				print '<a class="button icon-tick" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=classifybilled">' . $langs->trans('ClassifyBilled') . '</a>';
			}
			// Create bill and Classify billed
			if (!empty($conf->facture->enabled) && $object->Status == "TO_BILL") {
				if ($user->rights->facture->creer && empty($conf->global->WORKFLOW_DISABLE_CREATE_INVOICE_FROM_ORDER)) {
					print '<a class="button icon-folder" href="' . DOL_URL_ROOT . '/facture/fiche.php?action=create&amp;origin=' . $object->element . '&amp;originid=' . $object->id . '&amp;socid=' . $object->client->id . '">' . $langs->trans("CreateBill") . '</a>';
				}
				if ($user->rights->commande->creer && $object->statut > 2 && empty($conf->global->WORKFLOW_DISABLE_CLASSIFY_BILLED_FROM_ORDER) && empty($conf->global->WORsKFLOW_BILL_ON_SHIPMENT)) {
					print '<a class="button icon-drawer" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=classifybilled">' . $langs->trans("ClassifyBilled") . '</a>';
				}
			}

			// Send
			if ($object->Status != "DRAFT" && $object->Status != "CANCELED") {
				if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) || $user->rights->commande->order_advance->send)) {
					print '<a class="button icon-mail" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=presend&amp;mode=init">' . $langs->trans('SendByMail') . '</a>';
				} else {
					print '<a class="button icon-mail" href="#">' . $langs->trans('SendByMail') . '</a>';
				}
			}
			// Delete order
			if ($user->rights->commande->supprimer) {
				if ($numshipping == 0) {
					print '<a class="button icon-trash red-gradient" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete">' . $langs->trans("Delete") . '</a>';
				} else {
					print '<a class="butActionRefused" href="#" title="' . $langs->trans("ShippingExist") . '">' . $langs->trans("Delete") . '</a>';
				}
			}

			print "</span>";
			print "</div>";
			print '</div>';
		}
	}


	dol_fiche_end();
	print column_end();

	// Print Addresses
	print column_start("six");
	print $object->showAddresses();
	
	$titre = $langs->trans("Documents");
	print start_box($titre, "icon-object-documents");
	/* ?><input type="file" name="files" id="files" />
	  <script>
	  $(document).ready(function() {
	  $("#files").kendoUpload({
	  multiple: true,
	  async: {
	  saveUrl: "api/societe/file/<?php echo $object->id; ?>",
	  removeUrl: "api/societe/file/<?php echo $object->id; ?>",
	  removeVerb: "DELETE",
	  autoUpload: true
	  },
	  error: function(e) {
	  // log error
	  console.log(e);
	  },
	  complete: function() {
	  document.location.reload();
	  },
	  localization: {
	  select: "Ajouter fichiers"
	  }
	  });

	  });
	  </script>
	  <?php */
	print '<h5 class="green">Fichiers</h5>
					<ul class="files-icons">';

	foreach ($object->files as $aRow) {
		print '<li>';
		print '<span class="icon file-' . substr($aRow->name, strpos($aRow->name, ".") + 1) . '"></span>';
		print '<div class="controls">
					<span class="button-group compact children-tooltip">
						<a href="api/commande/file/' . $object->id . '/' . $aRow->name . '" class="button icon-eye" target="_blank" title="Ouvrir"></a>
						<a href="api/commande/file/' . $object->id . '/' . $aRow->name . '?download=1" class="button icon-download" title="Télécharger"></a>
						<a href="api/commande/file/remove/' . $object->id . '/' . $aRow->name . '" class="button icon-trash" title="Supprimer"></a>
					</span>
					</div>';
		print '<a href="api/commande/file/' . $object->id . '/' . $aRow->name . '">' . $aRow->name . '</a>';
		print '</li>';
	}

	print '</ul>';

	print end_box();
	
	print column_end();

	// Print Notes
	print column_start("six");
	print $object->show_notes();
	print column_end();

	// Print Total
	print column_start("six", "new-row");
	print $object->showAmounts();
	print column_end();

	if (!empty($conf->global->MAIN_DISABLE_CONTACTS_TAB)) {
		$blocname = 'contacts';
		$title = $langs->trans('ContactsAddresses');
		include DOL_DOCUMENT_ROOT . '/core/tpl/bloc_showhide.tpl.php';
	}

	if (!empty($conf->global->MAIN_DISABLE_NOTES_TAB)) {
		$blocname = 'notes';
		$title = $langs->trans('Notes');
		include DOL_DOCUMENT_ROOT . '/core/tpl/bloc_showhide.tpl.php';
	}

	/*
	 * Documents generes
	 *
	 */
	$comref = dol_sanitizeFileName($object->ref);
	$file = $conf->commande->dir_output . '/' . $comref . '/' . $comref . '.pdf';
	$relativepath = $comref . '/' . $comref . '.pdf';
	$filedir = $conf->commande->dir_output . '/' . $comref;
	$urlsource = $_SERVER["PHP_SELF"] . "?id=" . $object->id;
	$genallowed = $user->rights->commande->creer;
	$delallowed = $user->rights->commande->supprimer;

	print column_start("six");
	$somethingshown = $formfile->show_documents('commande', $comref, $filedir, $urlsource, $genallowed, $delallowed, $object->modelpdf, 1, 0, 0, 28, 0, '', '', '', $soc->default_lang);
	print column_end();

	// Lines
	print column_start();
	$object->showLines();
	print column_end();

//	print start_box($langs->trans('OrderLines'), "twelve", $object->fk_extrafields->ico, false);
//	print '<table id="tablelines" class="noborder" width="100%">';
//
//	$nbLines = count($object->lines);
//
//	// Show object lines
//	if (!empty($object->lines))
//		$ret = $object->printObjectLines($action, $mysoc, $soc, $lineid, 1);
//
//	// Form to add new line
//
//	if ($object->Status == "DRAFT" && $user->rights->commande->creer) {
//		if ($action != 'editline') {
//			$var = true;
//
//			if ($conf->global->MAIN_FEATURES_LEVEL > 1) {
//				// Add free or predefined products/services
//				$object->formAddObjectLine(1, $mysoc, $soc);
//			} else {
//				// Add free products/services
//				$object->formAddFreeProduct(1, $mysoc, $soc);
//
//				// Add predefined products/services
//				if (!empty($conf->product->enabled) || !empty($conf->service->enabled)) {
//					$var = !$var;
//					$object->formAddPredefinedProduct(1, $mysoc, $soc);
//				}
//			}
//
//			$parameters = array();
//			$reshook = $hookmanager->executeHooks('formAddObjectLine', $parameters, $object, $action);	// Note that $action and $object may have been modified by hook
//		}
//	}
//	print '</table>';
//	print end_box();
//

	if ($action != 'presend') {
		/*
		 * Linked object block
		 */
		//$somethingshown = $object->showLinkedObjectBlock();
//        $object->printLinkedObjects();

		print column_start("six");
		$object->showLinkedObjects();
		print column_end();

		// Print History
		print column_start("six");
		print $object->show_history();
		print column_end();

//        print '</td><td valign="top" width="50%">';
		// List of actions on element
//				include_once DOL_DOCUMENT_ROOT.'/core/class/html.formactions.class.php';
//				$formactions=new FormActions($db);
//				$somethingshown=$formactions->showactions($object,'order',$socid);
//        print '</td></tr></table>';
	}

	/*
	 * Action presend
	 *
	 */
	if ($action == 'presend') {
		$ref = dol_sanitizeFileName($object->ref);
		include_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
		$fileparams = dol_most_recent_file($conf->commande->dir_output . '/' . $ref);
		$file = $fileparams['fullname'];

		// Build document if it not exists
		if (!$file || !is_readable($file)) {
			// Define output language
			$outputlangs = $langs;
			$newlang = '';
			if ($conf->global->MAIN_MULTILANGS && empty($newlang) && !empty($_REQUEST['lang_id']))
				$newlang = $_REQUEST['lang_id'];
			if ($conf->global->MAIN_MULTILANGS && empty($newlang))
				$newlang = $object->client->default_lang;
			if (!empty($newlang)) {
				$outputlangs = new Translate();
				$outputlangs->setDefaultLang($newlang);
			}

			$result = commande_pdf_create($db, $object, GETPOST('model') ? GETPOST('model') : $object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
			if ($result <= 0) {
				dol_print_error($db, $result);
				exit;
			}
			$fileparams = dol_most_recent_file($conf->commande->dir_output . '/' . $ref);
			$file = $fileparams['fullname'];
		}

		print '<br>';
		print_titre($langs->trans('SendOrderByMail'));

		// Cree l'objet formulaire mail
		include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
		$formmail = new FormMail($db);
		$formmail->fromtype = 'user';
		$formmail->fromid = $user->id;
		$formmail->fromname = $user->getFullName($langs);
		$formmail->frommail = $user->email;
		$formmail->withfrom = 1;
		$formmail->withto = GETPOST('sendto') ? GETPOST('sendto') : 1;
		$formmail->withtosocid = $soc->id;
		$formmail->withtocc = 1;
		$formmail->withtoccsocid = 0;
		$formmail->withtoccc = $conf->global->MAIN_EMAIL_USECCC;
		$formmail->withtocccsocid = 0;
		$formmail->withtopic = $langs->trans('SendOrderRef', '__ORDERREF__');
		$formmail->withfile = 2;
		$formmail->withbody = 1;
		$formmail->withdeliveryreceipt = 1;
		$formmail->withcancel = 1;
		// Tableau des substitutions
		$formmail->substit['__ORDERREF__'] = $object->ref;
		$formmail->substit['__SIGNATURE__'] = $user->signature;
		$formmail->substit['__PERSONALIZED__'] = '';
		// Tableau des parametres complementaires
		$formmail->param['action'] = 'send';
		$formmail->param['models'] = 'order_send';
		$formmail->param['orderid'] = $object->id;
		$formmail->param['returnurl'] = $_SERVER["PHP_SELF"] . '?id=' . $object->id;

		// Init list of files
		if (GETPOST("mode") == 'init') {
			$formmail->clear_attached_files();
			$formmail->add_attached_files($file, basename($file), dol_mimetype($file));
		}

		// Show form
		$formmail->show_form();

		print '<br>';
	}
}

print '</div>';
print '</div>';
llxFooter();
?>
