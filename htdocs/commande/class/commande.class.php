<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
 * Copyright (C) 2010-2012 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2011      Jean Heimburger      <jean@tiaris.info>
 * Copyright (C) 2011      Herve Prot           <herve.prot@symeos.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU  *General Public License as published by
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
 * \file       htdocs/commande/class/commande.class.php
 * \ingroup    commande
 * \brief      Fichier des classes de commandes
 */
include_once DOL_DOCUMENT_ROOT . '/core/class/commonorder.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/margin/lib/margins.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/abstractinvoice.class.php';

/**
 *  Class to manage customers orders
 */
class Commande extends AbstractInvoice {

	public $element = 'commande';
	public $fk_element = 'fk_commande';
	protected $ismultientitymanaged = 1; // 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	var $id;
	var $socid;  // Id client
	var $client;  // Objet societe client (a charger par fetch_client)
	var $ref;
	var $ref_client;
	var $ref_ext;
	var $ref_int;
	var $contactid;
	var $fk_project;
	var $Status;  // -1=Canceled, 0=Draft, 1=Validated, (2=Accepted/On process not managed for customer orders), 3=Closed (Sent/Received, billed or not)
	var $facturee;  // Facturee ou non
	var $brouillon;
	var $cond_reglement_code;
	var $mode_reglement_code;
	var $availability_code;
	var $demand_reason_code;
	var $fk_delivery_address;
	var $adresse;
	var $date; // Date commande
	var $date_commande;  // Date commande (deprecated)
	var $date_livraison; // Date livraison souhaitee
	var $fk_remise_except;
	var $remise_percent;
	var $total_ht;   // Total net of tax
	var $total_ttc;   // Total with tax
	var $total_tva;   // Total VAT
	var $total_localtax1;   // Total Local tax 1
	var $total_localtax2;   // Total Local tax 2
	var $remise_absolue;
	var $modelpdf;
	var $info_bits;
	var $rang;
	var $special_code;
	var $source;   // Origin of order
	var $note; // deprecated
	var $note_private;
	var $note_public;
	var $extraparams = array();
	var $origin;
	var $origin_id;
	var $linked_objects = array();
	var $user_author_id;
	// Pour board
	var $nbtodo;
	var $nbtodolate;

	/**
	 * 	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db = '') {
		parent::__construct($db);

		$this->no_save[] = 'thirdparty';
		$this->no_save[] = 'line';

		$this->fk_extrafields = new ExtraFields($db);
		$this->fk_extrafields->fetch(get_class($this));

		$this->remise = 0;
		$this->remise_percent = 0;

		$this->products = array();
	}

	/**
	 *  Returns the reference to the following non used Order depending on the active numbering module
	 *  defined into COMMANDE_ADDON
	 *
	 *  @param	Societe		$soc  	Object thirdparty
	 *  @return string      		Order free reference
	 */
	function getNextNumRef($soc) {
		global $db, $langs, $conf;
		$langs->load("order");

		$dir = DOL_DOCUMENT_ROOT . "/commande/core/modules/commande";

		if (!empty($conf->global->COMMANDE_ADDON)) {
			$file = $conf->global->COMMANDE_ADDON . ".php";

			// Chargement de la classe de numerotation
			$classname = $conf->global->COMMANDE_ADDON;

			$result = include_once $dir . '/' . $file;
			if ($result) {
				$obj = new $classname();
				$numref = "";
				$numref = $obj->getNextValue($soc, $this);

				if ($numref != "") {
					return $numref;
				} else {
					dol_print_error($db, "Commande::getNextNumRef " . $obj->error);
					return "";
				}
			} else {
				print $langs->trans("Error") . " " . $langs->trans("Error_COMMANDE_ADDON_NotDefined");
				return "";
			}
		} else {
			print $langs->trans("Error") . " " . $langs->trans("Error_COMMANDE_ADDON_NotDefined");
			return "";
		}
	}

	/**
	 * 	Validate order
	 *
	 * 	@param		User	$user     		User making status change
	 * 	@param		int		$idwarehouse	Id of warehouse to use for stock decrease
	 * 	@return  	int						<=0 if OK, >0 if KO
	 */
	function valid($user, $idwarehouse = 0) {
		global $conf, $langs;
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$error = 0;

		if (!$user->rights->commande->valider) {
			$this->error = 'Permission denied';
			dol_syslog(get_class($this) . "::valid " . $this->error, LOG_ERR);
			return -1;
		}

		$now = dol_now();

		// Definition du nom de module de numerotation de commande
		$soc = new Societe($this->db);
		$soc->load($this->client->id);

		$this->ref = $this->getNextNumRef($soc);
		$this->Status = "VALIDATED";
		$this->record();

		// Class of company linked to order
		$result = $soc->set_as_client();

		// If stock is incremented on validate order, we must increment it
		if ($result >= 0 && !empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1) {
			require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
			$langs->load("agenda");

			// Loop on each line
			$cpt = count($this->lines);
			for ($i = 0; $i < $cpt; $i++) {
				if ($this->lines[$i]->fk_product > 0) {
					$mouvP = new MouvementStock($this->db);
					// We decrement stock of product (and sub-products)
					$result = $mouvP->livraison($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("OrderValidatedInSpeedealing", $num));
					if ($result < 0) {
						$error++;
					}
				}
			}
		}


		// Appel des triggers
		include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		$interface = new Interfaces($this->db);
		$result = $interface->run_triggers('ORDER_VALIDATE', $this, $user, $langs, $conf);
		if ($result < 0) {
			$error++;
			$this->errors = $interface->errors;
		}
		// Fin appel triggers

		return 1;

		// Protection
		//        if ($this->statut == 1)
		//        {
		//            dol_syslog(get_class($this)."::valid no draft status", LOG_WARNING);
		//            return 0;
		//        }
		//
			//        if (! $user->rights->commande->valider)
		//        {
		//            $this->error='Permission denied';
		//            dol_syslog(get_class($this)."::valid ".$this->error, LOG_ERR);
		//            return -1;
		//        }
		//
				//        $now=dol_now();
		//
				//        $this->db->begin();
		//
				//        // Definition du nom de module de numerotation de commande
		//        $soc = new Societe($this->db);
		//        $soc->fetch($this->socid);
		//
				//        // Class of company linked to order
		//        $result=$soc->set_as_client();
		//
				//        // Define new ref
		//        if (! $error && (preg_match('/^[\(]?PROV/i', $this->ref)))
		//        {
		//            $num = $this->getNextNumRef($soc);
		//        }
		//        else
		//        {
		//            $num = $this->ref;
		//        }
		//
				//        // Validate
		//        $sql = "UPDATE ".MAIN_DB_PREFIX."commande";
		//        $sql.= " SET ref = '".$num."',";
		//        $sql.= " fk_statut = 1,";
		//        $sql.= " date_valid='".$this->db->idate($now)."',";
		//        $sql.= " fk_user_valid = ".$user->id;
		//        $sql.= " WHERE rowid = ".$this->id;
		//
				//        dol_syslog(get_class($this)."::valid() sql=".$sql);
		//        $resql=$this->db->query($sql);
		//        if (! $resql)
		//        {
		//            dol_syslog(get_class($this)."::valid Echec update - 10 - sql=".$sql, LOG_ERR);
		//            dol_print_error($this->db);
		//            $error++;
		//        }
		//
				//        if (! $error)
		//        {
		//            // If stock is incremented on validate order, we must increment it
		//            if ($result >= 0 && ! empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
		//            {
		//                require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		//                $langs->load("agenda");
		//
					//                // Loop on each line
		//                $cpt=count($this->lines);
		//                for ($i = 0; $i < $cpt; $i++)
		//                {
		//                    if ($this->lines[$i]->fk_product > 0)
		//                    {
		//                        $mouvP = new MouvementStock($this->db);
		//                        // We decrement stock of product (and sub-products)
		//                        $result=$mouvP->livraison($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("OrderValidatedInSpeedealing",$num));
		//                        if ($result < 0) { $error++; }
		//                    }
		//                }
		//            }
		//        }
		//
							//        if (! $error)
		//        {
		//            $this->oldref='';
		//
							//            // Rename directory if dir was a temporary ref
		//            if (preg_match('/^[\(]?PROV/i', $this->ref))
		//            {
		//                // On renomme repertoire ($this->ref = ancienne ref, $numfa = nouvelle ref)
		//                // afin de ne pas perdre les fichiers attaches
		//                $comref = dol_sanitizeFileName($this->ref);
		//                $snum = dol_sanitizeFileName($num);
		//                $dirsource = $conf->commande->dir_output.'/'.$comref;
		//                $dirdest = $conf->commande->dir_output.'/'.$snum;
		//                if (file_exists($dirsource))
		//                {
		//                    dol_syslog(get_class($this)."::valid() rename dir ".$dirsource." into ".$dirdest);
		//
							//                    if (@rename($dirsource, $dirdest))
		//                    {
		//                        $this->oldref = $comref;
		//
							//                        dol_syslog("Rename ok");
		//                        // Suppression ancien fichier PDF dans nouveau rep
		//                        dol_delete_file($conf->commande->dir_output.'/'.$snum.'/'.$comref.'*.*');
		//                    }
		//                }
		//            }
		//        }
		//
							//        // Set new ref and current status
		//        if (! $error)
		//        {
		//            $this->ref = $num;
		//            $this->statut = 1;
		//        }
		//
							//        if (! $error)
		//        {
		//            // Appel des triggers
		//            include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//            $interface=new Interfaces($this->db);
		//            $result=$interface->run_triggers('ORDER_VALIDATE',$this,$user,$langs,$conf);
		//            if ($result < 0) { $error++; $this->errors=$interface->errors; }
		//            // Fin appel triggers
		//        }
		//
							//        if (! $error)
		//        {
		//            $this->db->commit();
		//            return 1;
		//        }
		//        else
		//        {
		//            $this->db->rollback();
		//            $this->error=$this->db->lasterror();
		//            return -1;
		//        }
	}

	/**
	 * 	Set draft status
	 *
	 * 	@param	User	$user			Object user that modify
	 * 	@param	int		$idwarehouse	Id warehouse to use for stock change.
	 * 	@return	int						<0 if KO, >0 if OK
	 */
	function set_draft($user, $idwarehouse = -1) {
		global $conf, $langs;

		$error = 0;

		// Protection
		if ($this->Status == "DRAFT")
			return 0;

		if (!$user->rights->commande->valider) {
			$this->error = 'Permission denied';
			return -1;
		}

		$this->Status = "DRAFT";
		$this->record();


		//        $sql = "UPDATE ".MAIN_DB_PREFIX."commande";
		//        $sql.= " SET fk_statut = 0";
		//        $sql.= " WHERE rowid = ".$this->id;

		dol_syslog(get_class($this) . "::set_draft", LOG_DEBUG);
		// If stock is decremented on validate order, we must reincrement it
		if (!empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1) {
			require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
			$langs->load("agenda");

			$num = count($this->lines);
			for ($i = 0; $i < $num; $i++) {
				if ($this->lines[$i]->fk_product > 0) {
					$mouvP = new MouvementStock($this->db);
					// We increment stock of product (and sub-products)
					$result = $mouvP->reception($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("OrderBackToDraftInSpeedealing", $this->ref));
					if ($result < 0) {
						$error++;
					}
				}
			}

			if (!$error) {
				return $result;
			} else {
				$this->error = $mouvP->error;
				return $result;
			}
		}

		return 1;
	}

	/**
	 * 	Tag the order as validated (opened)
	 * 	Function used when order is reopend after being closed.
	 *
	 * 	@param      User	$user       Object user that change status
	 * 	@return     int         		<0 if KO, 0 if nothing is done, >0 if OK
	 */
	function set_reopen($user) {
		global $conf, $langs;
		$error = 0;

		if ($this->Status != "TO_BILL" && $this->Status != "PROCESSED")
			return 0;

		$this->Status = "VALIDATED";
		$this->record();

		// Appel des triggers
		include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		$interface = new Interfaces($this->db);
		$result = $interface->run_triggers('BILL_REOPEN', $this, $user, $langs, $conf);
		if ($result < 0) {
			$error++;
			$this->errors = $interface->errors;
		}
		// Fin appel triggers

		return 1;

		//        if ($this->statut != 3) {
		//            return 0;
		//        }
		//
									//        $this->db->begin();
		//
									//        $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'commande';
		//        $sql.= ' SET fk_statut=1, facture=0';
		//        $sql.= ' WHERE rowid = ' . $this->id;
		//
									//        dol_syslog("Commande::set_reopen sql=" . $sql);
		//        $resql = $this->db->query($sql);
		//        if ($resql) {
		//            // Appel des triggers
		//            include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//            $interface = new Interfaces($this->db);
		//            $result = $interface->run_triggers('BILL_REOPEN', $this, $user, $langs, $conf);
		//            if ($result < 0) {
		//                $error++;
		//                $this->errors = $interface->errors;
		//            }
		//            // Fin appel triggers
		//        } else {
		//            $error++;
		//            $this->error = $this->db->error();
		//            dol_print_error($this->db);
		//        }
		//
									//        if (!$error) {
		//            $this->statut = 1;
		//            $this->billed = 0;
		//            $this->facturee = 0; // deprecated
		//
									//            $this->db->commit();
		//            return 1;
		//        } else {
		//            $this->db->rollback();
		//            return -1;
		//        }
	}

	/**
	 *  Close order
	 *
	 * 	@param      User	$user       Objet user that close
	 * 	@return		int					<0 if KO, >0 if OK
	 */
	function cloture($user) {
		global $conf, $langs;

		$error = 0;
		if ($user->rights->commande->cloturer) {
			$this->Status = "TO_BILL";
			$this->record();
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('ORDER_CLOSE', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}
		return -1;

		//        if ($user->rights->commande->valider) {
		//            $this->db->begin();
		//
										//            $now = dol_now();
		//
										//            $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'commande';
		//            $sql.= ' SET fk_statut = 3,';
		//            $sql.= ' fk_user_cloture = ' . $user->id . ',';
		//            $sql.= ' date_cloture = ' . $this->db->idate($now);
		//            $sql.= ' WHERE rowid = ' . $this->id . ' AND fk_statut > 0';
		//
										//            if ($this->db->query($sql)) {
		//                // Appel des triggers
		//                include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//                $interface = new Interfaces($this->db);
		//                $result = $interface->run_triggers('ORDER_CLOSE', $this, $user, $langs, $conf);
		//                if ($result < 0) {
		//                    $error++;
		//                    $this->errors = $interface->errors;
		//                }
		//                // Fin appel triggers
		//
										//                if (!$error) {
		//                    $this->statut = 3;
		//
										//                    $this->db->commit();
		//                    return 1;
		//                } else {
		//                    $this->db->rollback();
		//                    return -1;
		//                }
		//            } else {
		//                $this->error = $this->db->lasterror();
		//                dol_syslog($this->error, LOG_ERR);
		//
										//                $this->db->rollback();
		//                return -1;
		//            }
		//        }
	}

	/**
	 * 	Cancel an order
	 * 	If stock is decremented on order validation, we must reincrement it
	 *
	 * 	@param	User	$user			Object user
	 * 	@param	int		$idwarehouse	Id warehouse to use for stock change.
	 * 	@return	int						<0 if KO, >0 if OK
	 */
	function cancel($idwarehouse = -1) {
		global $conf, $user, $langs;

		$error = 0;

		dol_syslog("Commande::cancel", LOG_DEBUG);

		// If stock is decremented on validate order, we must reincrement it
		if (!empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1) {
			require_once DOL_DOCUMENT_ROOT . '/product/stock/class/mouvementstock.class.php';
			$langs->load("agenda");

			$num = count($this->lines);
			for ($i = 0; $i < $num; $i++) {
				if ($this->lines[$i]->fk_product > 0) {
					$mouvP = new MouvementStock($this->db);
					// We increment stock of product (and sub-products)
					$result = $mouvP->reception($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("OrderCanceledInSpeedealing", $this->ref));
					if ($result < 0) {
						$error++;
					}
				}
			}
		}

		if ($error > 0) {
			return -1;
		} else {
			$this->Status = "CANCELED";
			$this->record();
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('ORDER_CANCEL', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers

			return 1;
		}

		//		$this->db->begin();
		//
	//		$sql = "UPDATE ".MAIN_DB_PREFIX."commande";
		//		$sql.= " SET fk_statut = -1";
		//		$sql.= " WHERE rowid = ".$this->id;
		//		$sql.= " AND fk_statut = 1";
		//
	//		dol_syslog("Commande::cancel sql=".$sql, LOG_DEBUG);
		//		if ($this->db->query($sql))
		//		{
		//			// If stock is decremented on validate order, we must reincrement it
		//			if (! empty($conf->stock->enabled) && $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER == 1)
		//			{
		//				require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';
		//				$langs->load("agenda");
		//
			//				$num=count($this->lines);
		//				for ($i = 0; $i < $num; $i++)
		//				{
		//					if ($this->lines[$i]->fk_product > 0)
		//					{
		//						$mouvP = new MouvementStock($this->db);
		//						// We increment stock of product (and sub-products)
		//						$result=$mouvP->reception($user, $this->lines[$i]->fk_product, $idwarehouse, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("OrderCanceledInSpeedealing",$this->ref));
		//						if ($result < 0) {
		//							$error++;
		//						}
		//					}
		//				}
		//			}
		//
					//			if (! $error)
		//			{
		//				// Appel des triggers
		//				include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//				$interface=new Interfaces($this->db);
		//				$result=$interface->run_triggers('ORDER_CANCEL',$this,$user,$langs,$conf);
		//				if ($result < 0) {
		//					$error++; $this->errors=$interface->errors;
		//				}
		//				// Fin appel triggers
		//			}
		//
					//			if (! $error)
		//			{
		//				$this->statut=-1;
		//				$this->db->commit();
		//				return 1;
		//			}
		//			else
		//			{
		//				$this->error=$mouvP->error;
		//				$this->db->rollback();
		//				return -1;
		//			}
		//		}
		//		else
		//		{
		//			$this->error=$this->db->error();
		//			$this->db->rollback();
		//			dol_syslog($this->error, LOG_ERR);
		//			return -1;
		//		}
	}

	/**
	 * 	Create order
	 * 	Note that this->ref can be set or empty. If empty, we will use "(PROV)"
	 *
	 * 	@param		User	$user 		Objet user that make creation
	 * 	@param		int		$notrigger	Disable all triggers
	 * 	@return 	int					<0 if KO, >0 if OK
	 */
	function create($user, $notrigger = 0) {
		global $conf, $langs, $mysoc, $user;
		$error = 0;

		// Clean parameters
		$this->Status = "DRAFT";  // On positionne en mode brouillon la commande

		dol_syslog("Commande::create user=" . $user->id);

		// Check parameters

		$soc = new Societe($this->db);
		$result = $soc->fetch($this->socid);

		unset($this->socid);

		if ($result < 0) {
			$this->error = "Failed to fetch company";
			dol_syslog("Commande::create " . $this->error, LOG_ERR);
			return -2;
		}
		if (!empty($conf->global->COMMANDE_REQUIRE_SOURCE) && $this->source < 0) {
			$this->error = $langs->trans("ErrorFieldRequired", $langs->trans("Source"));
			dol_syslog("Commande::create " . $this->error, LOG_ERR);
			return -1;
		}
		$this->client = new stdClass();
		$this->client->id = $soc->id;
		$this->client->name = $soc->name;
		$this->client->country_code = $soc->country_code;
		$this->client->email = $soc->email;
		$this->fetch_thirdparty();

		// author
		$this->author = new stdClass();
		$this->author->id = $user->id;
		$this->author->name = $user->login;

		// $date_commande is deprecated
		$date = ($this->date_commande ? $this->date_commande : $this->date);
		$this->date = $this->date_commande = $date;

		// Calcul of ref
		$this->ref = $this->getNextNumRef($soc);

		$now = dol_now();

		dol_syslog("Commande::create");
		//        echo '<pre>'.print_r($this, true).'</pre>';die;
		$this->record();

		if (!$notrigger) {
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('ORDER_CREATE', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}

		return $this->id;
		//        if ($resql)
		//        {
		//            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'commande');
		//
								//            if ($this->id)
		//            {
		//                $fk_parent_line=0;
		//                $num=count($this->lines);
		//
									//                /*
		//                 *  Insertion du detail des produits dans la base
		//                 */
		//                for ($i=0;$i<$num;$i++)
		//                {
		//                    // Reset fk_parent_line for no child products and special product
		//                    if (($this->lines[$i]->product_type != 9 && empty($this->lines[$i]->fk_parent_line)) || $this->lines[$i]->product_type == 9) {
		//                        $fk_parent_line = 0;
		//                    }
		//
										//                    $result = $this->addline(
		//                        $this->id,
		//                        $this->lines[$i]->desc,
		//                        $this->lines[$i]->subprice,
		//                        $this->lines[$i]->qty,
		//                        $this->lines[$i]->tva_tx,
		//                        $this->lines[$i]->localtax1_tx,
		//                        $this->lines[$i]->localtax2_tx,
		//                        $this->lines[$i]->fk_product,
		//                        $this->lines[$i]->remise_percent,
		//                        $this->lines[$i]->info_bits,
		//                        $this->lines[$i]->fk_remise_except,
		//                        'HT',
		//                        0,
		//                        $this->lines[$i]->date_start,
		//                        $this->lines[$i]->date_end,
		//                        $this->lines[$i]->product_type,
		//                        $this->lines[$i]->rang,
		//                        $this->lines[$i]->special_code,
		//                        $fk_parent_line,
		//                        $this->lines[$i]->fk_fournprice,
		//                        $this->lines[$i]->pa_ht,
		//                    	$this->lines[$i]->label
		//                    );
		//                    if ($result < 0)
		//                    {
		//                        $this->error=$this->db->lasterror();
		//                        dol_print_error($this->db);
		//                        $this->db->rollback();
		//                        return -1;
		//                    }
		//                    // Defined the new fk_parent_line
		//                    if ($result > 0 && $this->lines[$i]->product_type == 9) {
		//                        $fk_parent_line = $result;
		//                    }
		//                }
		//
							//                // Mise a jour ref
		//                $sql = 'UPDATE '.MAIN_DB_PREFIX."commande SET ref='(PROV".$this->id.")' WHERE rowid=".$this->id;
		//                if ($this->db->query($sql))
		//                {
		//                    if ($this->id)
		//                    {
		//                        $this->ref="(PROV".$this->id.")";
		//
									//                        // Add object linked
		//                        if (is_array($this->linked_objects) && ! empty($this->linked_objects))
		//                        {
		//                        	foreach($this->linked_objects as $origin => $origin_id)
		//                        	{
		//                        		$ret = $this->add_object_linked($origin, $origin_id);
		//                        		if (! $ret)
		//                        		{
		//                        			dol_print_error($this->db);
		//                        			$error++;
		//                        		}
		//
												//                        		// TODO mutualiser
		//                        		if ($origin == 'propal' && $origin_id)
		//                        		{
		//                        			// On recupere les differents contact interne et externe
		//                        			$prop = new Propal($this->db, $this->socid, $origin_id);
		//
													//                        			// On recupere le commercial suivi propale
		//                        			$this->userid = $prop->getIdcontact('internal', 'SALESREPFOLL');
		//
													//                        			if ($this->userid)
		//                        			{
		//                        				//On passe le commercial suivi propale en commercial suivi commande
		//                        				$this->add_contact($this->userid[0], 'SALESREPFOLL', 'internal');
		//                        			}
		//
														//                        			// On recupere le contact client suivi propale
		//                        			$this->contactid = $prop->getIdcontact('external', 'CUSTOMER');
		//
														//                        			if ($this->contactid)
		//                        			{
		//                        				//On passe le contact client suivi propale en contact client suivi commande
		//                        				$this->add_contact($this->contactid[0], 'CUSTOMER', 'external');
		//                        			}
		//                        		}
		//                        	}
		//                        }
		//                    }
		//
															//                    if (! $notrigger)
		//                    {
		//                        // Appel des triggers
		//                        include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//                        $interface=new Interfaces($this->db);
		//                        $result=$interface->run_triggers('ORDER_CREATE',$this,$user,$langs,$conf);
		//                        if ($result < 0) { $error++; $this->errors=$interface->errors; }
		//                        // Fin appel triggers
		//                    }
		//
															//                    $this->db->commit();
		//                    return $this->id;
		//                }
		//                else
		//                {
		//                    $this->db->rollback();
		//                    return -1;
		//                }
		//            }
		//        }
		//        else
		//        {
		//            dol_print_error($this->db);
		//            $this->db->rollback();
		//            return -1;
		//        }
	}

	/**
	 * 	Update order
	 * 	Note that this->ref can be set or empty. If empty, we will use "(PROV)"
	 *
	 * 	@param		User	$user 		Objet user that make creation
	 * 	@param		int		$notrigger	Disable all triggers
	 * 	@return 	int					<0 if KO, >0 if OK
	 */
	function update($user, $notrigger = 0) {
		global $conf, $langs, $mysoc;
		$error = 0;

		// Clean parameters

		dol_syslog("Commande::update user=" . $user->id);

		// Check parameters

		$soc = new Societe($this->db);
		$result = $soc->load($this->socid);
		unset($this->socid);
		if ($result < 0) {
			$this->error = "Failed to fetch company";
			dol_syslog("Commande::update " . $this->error, LOG_ERR);
			return -2;
		}
		if (!empty($conf->global->COMMANDE_REQUIRE_SOURCE) && $this->source < 0) {
			$this->error = $langs->trans("ErrorFieldRequired", $langs->trans("Source"));
			dol_syslog("Commande::update " . $this->error, LOG_ERR);
			return -1;
		}
		$this->client = new stdClass();
		$this->client->id = $soc->id;
		$this->client->name = $soc->name;
		$this->client->country_code = $soc->country_code;
		$this->client->email = $soc->email;
		$this->fetch_thirdparty();

		// $date_commande is deprecated
		$date = ($this->date_commande ? $this->date_commande : $this->date);
		$this->date = $this->date_commande = $date;

		$now = dol_now();

		dol_syslog("Commande::update");
		//        echo '<pre>'.print_r($this, true).'</pre>';die;
		$this->record();

		if (!$notrigger) {
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('ORDER_UPDATE', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}

		return $this->id;
	}

	/**
	 * 	Load an object from its id and create a new one in database
	 *
	 * 	@param		int			$socid			Id of thirdparty
	 * 	@return		int							New id of clone
	 */
	function createFromClone($socid = 0) {
		global $conf, $user, $langs, $hookmanager;

		$error = 0;

		$this->db->begin();

		// Load source object
		$objFrom = dol_clone($this);

		// Change socid if needed
		if (!empty($socid) && $socid != $this->socid) {
			$objsoc = new Societe($this->db);

			if ($objsoc->fetch($socid) > 0) {
				$this->socid = $objsoc->id;
				$this->cond_reglement_id = (!empty($objsoc->cond_reglement_id) ? $objsoc->cond_reglement_id : 0);
				$this->mode_reglement_id = (!empty($objsoc->mode_reglement_id) ? $objsoc->mode_reglement_id : 0);
				$this->fk_project = '';
				$this->fk_delivery_address = '';
			}

			// TODO Change product price if multi-prices
		}

		$this->id = 0;
		$this->statut = 0;

		// Clear fields
		$this->user_author_id = $user->id;
		$this->user_valid = '';
		$this->date_creation = '';
		$this->date_validation = '';
		$this->ref_client = '';

		// Create clone
		$result = $this->create($user);
		if ($result < 0)
			$error++;

		if (!$error) {
			// Hook of thirdparty module
			if (is_object($hookmanager)) {
				$parameters = array('objFrom' => $objFrom);
				$action = '';
				$reshook = $hookmanager->executeHooks('createFrom', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0)
					$error++;
			}

			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('ORDER_CLONE', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}

		// End
		if (!$error) {
			$this->db->commit();
			return $this->id;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *  Load an object from a proposal and create a new order into database
	 *
	 *  @param      Object			$object 	        Object source
	 *  @return     int             					<0 if KO, 0 if nothing done, 1 if OK
	 */
	function createFromProposal($object) {
		global $conf, $user, $langs, $hookmanager;

		$error = 0;

		// Signed proposal
		if ($object->statut == 2) {
			$this->date_commande = dol_now();
			$this->source = 0;

			$num = count($object->lines);
			for ($i = 0; $i < $num; $i++) {
				$line = new OrderLine($this->db);

				$line->libelle = $object->lines[$i]->libelle;
				$line->label = $object->lines[$i]->label;
				$line->desc = $object->lines[$i]->desc;
				$line->price = $object->lines[$i]->price;
				$line->subprice = $object->lines[$i]->subprice;
				$line->tva_tx = $object->lines[$i]->tva_tx;
				$line->localtax1_tx = $object->lines[$i]->localtax1_tx;
				$line->localtax2_tx = $object->lines[$i]->localtax2_tx;
				$line->qty = $object->lines[$i]->qty;
				$line->fk_remise_except = $object->lines[$i]->fk_remise_except;
				$line->remise_percent = $object->lines[$i]->remise_percent;
				$line->fk_product = $object->lines[$i]->fk_product;
				$line->info_bits = $object->lines[$i]->info_bits;
				$line->product_type = $object->lines[$i]->product_type;
				$line->rang = $object->lines[$i]->rang;
				$line->special_code = $object->lines[$i]->special_code;
				$line->fk_parent_line = $object->lines[$i]->fk_parent_line;

				$this->lines[$i] = $line;
			}

			$this->socid = $object->socid;
			$this->fk_project = $object->fk_project;
			$this->cond_reglement_id = $object->cond_reglement_id;
			$this->mode_reglement_id = $object->mode_reglement_id;
			$this->availability_id = $object->availability_id;
			$this->demand_reason_id = $object->demand_reason_id;
			$this->date_livraison = $object->date_livraison;
			$this->fk_delivery_address = $object->fk_delivery_address;
			$this->contact_id = $object->contactid;
			$this->ref_client = $object->ref_client;
			$this->note = $object->note;
			$this->note_public = $object->note_public;

			$this->origin = $object->element;
			$this->origin_id = $object->id;

			// Possibility to add external linked objects with hooks
			$this->linked_objects[$this->origin] = $this->origin_id;
			if (is_array($object->other_linked_objects) && !empty($object->other_linked_objects)) {
				$this->linked_objects = array_merge($this->linked_objects, $object->other_linked_objects);
			}

			$ret = $this->create($user);

			if ($ret > 0) {
				// Actions hooked (by external module)
				$hookmanager->initHooks(array('orderdao'));
				$parameters = array('objFrom' => $object);
				$action = '';
				$reshook = $hookmanager->executeHooks('createFrom', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0)
					$error++;

				if (!$error) {
					// Ne pas passer par la commande provisoire
					if ($conf->global->COMMANDE_VALID_AFTER_CLOSE_PROPAL == 1) {
						$this->fetch($ret);
						$this->valid($user);
					}
					return 1;
				}
				else
					return -1;
			}
			else
				return -1;
		}
		else
			return 0;
	}

	/**
	 * 	Add line into array
	 * 	$this->client must be loaded
	 *
	 * 	@param		int				$idproduct			Product Id
	 * 	@param		double			$qty				Quantity
	 * 	@param		double			$remise_percent		Product discount relative
	 * 	@param    	timestamp		$date_start         Start date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 * 	@param    	timestamp		$date_end           End date of the line - Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	 * 	@return    	void
	 *
	 * 	TODO	Remplacer les appels a cette fonction par generation objet Ligne
	 * 			insere dans tableau $this->products
	 */
	function add_product($idproduct, $qty, $remise_percent = 0, $date_start = '', $date_end = '') {
		global $conf, $mysoc;

		if (!$qty)
			$qty = 1;

		if ($idproduct > 0) {
			$prod = new Product($this->db);
			$prod->fetch($idproduct);

			$tva_tx = get_default_tva($mysoc, $this->client, $prod->id);
			$localtax1_tx = get_localtax($tva_tx, 1, $this->client);
			$localtax2_tx = get_localtax($tva_tx, 2, $this->client);
			// multiprix
			if ($conf->global->PRODUIT_MULTIPRICES && $this->client->price_level)
				$price = $prod->multiprices[$this->client->price_level];
			else
				$price = $prod->price;

			$line = new OrderLine($this->db);

			$line->fk_product = $idproduct;
			$line->desc = $prod->description;
			$line->qty = $qty;
			$line->subprice = $price;
			$line->remise_percent = $remise_percent;
			$line->tva_tx = $tva_tx;
			$line->localtax1_tx = $localtax1_tx;
			$line->localtax2_tx = $localtax2_tx;
			$line->ref = $prod->ref;
			$line->libelle = $prod->libelle;
			$line->product_desc = $prod->description;

			// Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
			// Save the start and end date of the line in the object
			if ($date_start) {
				$line->date_start = $date_start;
			}
			if ($date_end) {
				$line->date_end = $date_end;
			}

			$this->lines[] = $line;

			/** POUR AJOUTER AUTOMATIQUEMENT LES SOUSPRODUITS a LA COMMANDE
			  if (! empty($conf->global->PRODUIT_SOUSPRODUITS))
			  {
			  $prod = new Product($this->db);
			  $prod->fetch($idproduct);
			  $prod -> get_sousproduits_arbo ();
			  $prods_arbo = $prod->get_each_prod();
			  if(count($prods_arbo) > 0)
			  {
			  foreach($prods_arbo as $key => $value)
			  {
			  // print "id : ".$value[1].' :qty: '.$value[0].'<br>';
			  if(! in_array($value[1],$this->products))
			  $this->add_product($value[1], $value[0]);

			  }
			  }

			  }
			 * */
		}
	}

	/**
	 * 	Get object and lines from database
	 *
	 * 	@param      int			$id       		Id of object to load
	 * 	@param		string		$ref			Ref of object
	 * 	@param		string		$ref_ext		External reference of object
	 * 	@param		string		$ref_int		Internal reference of other object
	 * 	@return     int         				>0 if OK, <0 if KO, 0 if not found
	 */
	function fetch($id, $ref = '', $ref_ext = '', $ref_int = '') {
		global $conf;
		return parent::fetch($id);

		// Check parameters
		if (empty($id) && empty($ref) && empty($ref_ext) && empty($ref_int))
			return -1;

		$sql = 'SELECT c.rowid, c.date_creation, c.ref, c.fk_soc, c.fk_user_author, c.fk_statut';
		$sql.= ', c.amount_ht, c.total_ht, c.total_ttc, c.tva as total_tva, c.localtax1 as total_localtax1, c.localtax2 as total_localtax2, c.fk_cond_reglement, c.fk_mode_reglement, c.fk_availability, c.fk_input_reason';
		$sql.= ', c.date_commande';
		$sql.= ', c.date_livraison';
		$sql.= ', c.fk_projet, c.remise_percent, c.remise, c.remise_absolue, c.source, c.facture as billed';
		$sql.= ', c.note as note_private, c.note_public, c.ref_client, c.ref_ext, c.ref_int, c.model_pdf, c.fk_adresse_livraison, c.extraparams';
		$sql.= ', p.code as mode_reglement_code, p.libelle as mode_reglement_libelle';
		$sql.= ', cr.code as cond_reglement_code, cr.libelle as cond_reglement_libelle, cr.libelle_facture as cond_reglement_libelle_doc';
		$sql.= ', ca.code as availability_code';
		$sql.= ', dr.code as demand_reason_code';
		$sql.= ' FROM ' . MAIN_DB_PREFIX . 'commande as c';
		$sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_payment_term as cr ON (c.fk_cond_reglement = cr.rowid)';
		$sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_paiement as p ON (c.fk_mode_reglement = p.id)';
		$sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_availability as ca ON (c.fk_availability = ca.rowid)';
		$sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_input_reason as dr ON (c.fk_input_reason = ca.rowid)';
		$sql.= " WHERE c.entity = " . $conf->entity;
		if ($id)
			$sql.= " AND c.rowid=" . $id;
		if ($ref)
			$sql.= " AND c.ref='" . $this->db->escape($ref) . "'";
		if ($ref_ext)
			$sql.= " AND c.ref_ext='" . $this->db->escape($ref_ext) . "'";
		if ($ref_int)
			$sql.= " AND c.ref_int='" . $this->db->escape($ref_int) . "'";

		dol_syslog(get_class($this) . "::fetch sql=" . $sql, LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			$obj = $this->db->fetch_object($result);
			if ($obj) {
				$this->id = $obj->rowid;
				$this->ref = $obj->ref;
				$this->ref_client = $obj->ref_client;
				$this->ref_ext = $obj->ref_ext;
				$this->ref_int = $obj->ref_int;
				$this->socid = $obj->fk_soc;
				$this->statut = $obj->fk_statut;
				$this->user_author_id = $obj->fk_user_author;
				$this->total_ht = $obj->total_ht;
				$this->total_tva = $obj->total_tva;
				$this->total_localtax1 = $obj->total_localtax1;
				$this->total_localtax2 = $obj->total_localtax2;
				$this->total_ttc = $obj->total_ttc;
				$this->date = $this->db->jdate($obj->date_commande);
				$this->date_commande = $this->db->jdate($obj->date_commande);
				$this->remise = $obj->remise;
				$this->remise_percent = $obj->remise_percent;
				$this->remise_absolue = $obj->remise_absolue;
				$this->source = $obj->source;
				$this->facturee = $obj->billed;   // deprecated
				$this->billed = $obj->billed;
				$this->note = $obj->note_private; // deprecated
				$this->note_private = $obj->note_private;
				$this->note_public = $obj->note_public;
				$this->fk_project = $obj->fk_projet;
				$this->modelpdf = $obj->model_pdf;
				$this->mode_reglement_id = $obj->fk_mode_reglement;
				$this->mode_reglement_code = $obj->mode_reglement_code;
				$this->mode_reglement = $obj->mode_reglement_libelle;
				$this->cond_reglement_id = $obj->fk_cond_reglement;
				$this->cond_reglement_code = $obj->cond_reglement_code;
				$this->cond_reglement = $obj->cond_reglement_libelle;
				$this->cond_reglement_doc = $obj->cond_reglement_libelle_doc;
				$this->availability_id = $obj->fk_availability;
				$this->availability_code = $obj->availability_code;
				$this->demand_reason_id = $obj->fk_input_reason;
				$this->demand_reason_code = $obj->demand_reason_code;
				$this->date_livraison = $this->db->jdate($obj->date_livraison);
				$this->fk_delivery_address = $obj->fk_adresse_livraison;

				$this->extraparams = (array) json_decode($obj->extraparams, true);

				$this->lines = array();

				if ($this->statut == 0)
					$this->brouillon = 1;

				$this->db->free();

				/*
				 * Lines
				 */
				$result = $this->fetch_lines();
				if ($result < 0) {
					return -3;
				}
				return 1;
			} else {
				$this->error = 'Order with id ' . $id . ' not found sql=' . $sql;
				dol_syslog(get_class($this) . '::fetch ' . $this->error);
				return 0;
			}
		} else {
			dol_syslog(get_class($this) . '::fetch Error rowid=' . $id, LOG_ERR);
			$this->error = $this->db->error();
			return -1;
		}
	}

//    function fetch_thirdparty() {
//
//        $soc = new Societe($db);
//        $soc->fetch($this->socid);
//
//        $this->thirdparty = new stdClass();
//        $this->thirdparty->name = $soc->name;
//        $this->thirdparty->address = $soc->address;
//        $this->thirdparty->zip = $soc->zip;
//        $this->thirdparty->town = $soc->town;
//        $this->thirdparty->country_code = $soc->country_id;
//        $this->thirdparty->phone = $soc->phone;
//        $this->thirdparty->email = $soc->email;
//        $this->thirdparty->url = $soc->url;
//        $this->thirdparty->idprof1 = $soc->idprof1;
//        $this->thirdparty->idprof2 = $soc->idprof2;
//        $this->thirdparty->idprof3 = $soc->idprof3;
//        $this->thirdparty->idprof4 = $soc->idprof4;
//        $this->thirdparty->url = $soc->url;
//        $this->thirdparty->url = $soc->url;
//        $this->thirdparty->url = $soc->url;
//        $this->thirdparty->url = $soc->url;
//    }

	/**
	 * 	Adding line of fixed discount in the order in DB
	 *
	 * 	@param     int	$idremise			Id de la remise fixe
	 * 	@return    int          			>0 si ok, <0 si ko
	 */
	function insert_discount($idremise) {
		global $langs;

		include_once DOL_DOCUMENT_ROOT . '/core/lib/price.lib.php';
		include_once DOL_DOCUMENT_ROOT . '/core/class/discount.class.php';

		$this->db->begin();

		$remise = new DiscountAbsolute($this->db);
		$result = $remise->fetch($idremise);

		if ($result > 0) {
			if ($remise->fk_facture) { // Protection against multiple submission
				$this->error = $langs->trans("ErrorDiscountAlreadyUsed");
				$this->db->rollback();
				return -5;
			}

			$line = new OrderLine($this->db);

			$line->fk_commande = $this->id;
			$line->fk_remise_except = $remise->id;
			$line->desc = $remise->description; // Description ligne
			$line->tva_tx = $remise->tva_tx;
			$line->subprice = -$remise->amount_ht;
			$line->price = -$remise->amount_ht;
			$line->fk_product = 0;  // Id produit predefini
			$line->qty = 1;
			$line->remise = 0;
			$line->remise_percent = 0;
			$line->rang = -1;
			$line->info_bits = 2;

			$line->total_ht = -$remise->amount_ht;
			$line->total_tva = -$remise->amount_tva;
			$line->total_ttc = -$remise->amount_ttc;

			$result = $line->insert();
			if ($result > 0) {
				$result = $this->update_price(1);
				if ($result > 0) {
					$this->db->commit();
					return 1;
				} else {
					$this->db->rollback();
					return -1;
				}
			} else {
				$this->error = $line->error;
				$this->db->rollback();
				return -2;
			}
		} else {
			$this->db->rollback();
			return -2;
		}
	}

	/**
	 * 	Load array lines
	 *
	 * 	@param		int		$only_product	Return only physical products
	 * 	@return		int						<0 if KO, >0 if OK
	 */
	function fetch_lines($only_product = 0) {

		return $this->lines;
		//        $sql = 'SELECT l.rowid, l.fk_product, l.fk_parent_line, l.product_type, l.fk_commande, l.label as custom_label, l.description, l.price, l.qty, l.tva_tx,';
		//        $sql.= ' l.localtax1_tx, l.localtax2_tx, l.fk_remise_except, l.remise_percent, l.subprice, l.fk_product_fournisseur_price as fk_fournprice, l.buy_price_ht as pa_ht, l.rang, l.info_bits, l.special_code,';
		//        $sql.= ' l.total_ht, l.total_ttc, l.total_tva, l.total_localtax1, l.total_localtax2, l.date_start, l.date_end,';
		//        $sql.= ' p.ref as product_ref, p.description as product_desc, p.fk_product_type, p.label as product_label';
		//        $sql.= ' FROM ' . MAIN_DB_PREFIX . 'commandedet as l';
		//        $sql.= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON (p.rowid = l.fk_product)';
		//        $sql.= ' WHERE l.fk_commande = ' . $this->id;
		//        if ($only_product)
		//            $sql .= ' AND p.fk_product_type = 0';
		//        $sql .= ' ORDER BY l.rang';
		//
	//        dol_syslog("Commande::fetch_lines sql=" . $sql, LOG_DEBUG);
		//        $result = $this->db->query($sql);
		//        if ($result) {
		//            $num = $this->db->num_rows($result);
		//
	//            $i = 0;
		//            while ($i < $num) {
		//                $objp = $this->db->fetch_object($result);
		//
	//                $line = new OrderLine($this->db);
		//
	//                $line->rowid = $objp->rowid;    // \deprecated
		//                $line->id = $objp->rowid;
		//                $line->fk_commande = $objp->fk_commande;
		//                $line->commande_id = $objp->fk_commande;   // \deprecated
		//                $line->label = $objp->custom_label;
		//                $line->desc = $objp->description;    // Description ligne
		//                $line->product_type = $objp->product_type;
		//                $line->qty = $objp->qty;
		//                $line->tva_tx = $objp->tva_tx;
		//                $line->localtax1_tx = $objp->localtax1_tx;
		//                $line->localtax2_tx = $objp->localtax2_tx;
		//                $line->total_ht = $objp->total_ht;
		//                $line->total_ttc = $objp->total_ttc;
		//                $line->total_tva = $objp->total_tva;
		//                $line->total_localtax1 = $objp->total_localtax1;
		//                $line->total_localtax2 = $objp->total_localtax2;
		//                $line->subprice = $objp->subprice;
		//                $line->fk_remise_except = $objp->fk_remise_except;
		//                $line->remise_percent = $objp->remise_percent;
		//                $line->price = $objp->price;
		//                $line->fk_product = $objp->fk_product;
		//                $line->fk_fournprice = $objp->fk_fournprice;
		//                $marginInfos = getMarginInfos($objp->subprice, $objp->remise_percent, $objp->tva_tx, $objp->localtax1_tx, $objp->localtax2_tx, $line->fk_fournprice, $objp->pa_ht);
		//                $line->pa_ht = $marginInfos[0];
		//                $line->marge_tx = $marginInfos[1];
		//                $line->marque_tx = $marginInfos[2];
		//                $line->rang = $objp->rang;
		//                $line->info_bits = $objp->info_bits;
		//                $line->special_code = $objp->special_code;
		//                $line->fk_parent_line = $objp->fk_parent_line;
		//
	//                $line->ref = $objp->product_ref;  // TODO deprecated
		//                $line->product_ref = $objp->product_ref;
		//                $line->libelle = $objp->product_label;  // TODO deprecated
		//                $line->product_label = $objp->product_label;
		//                $line->product_desc = $objp->product_desc;   // Description produit
		//                $line->fk_product_type = $objp->fk_product_type; // Produit ou service
		//
	//                $line->date_start = $this->db->jdate($objp->date_start);
		//                $line->date_end = $this->db->jdate($objp->date_end);
		//
	//                $this->lines[$i] = $line;
		//
	//                $i++;
		//            }
		//            $this->db->free($result);
		//
	//            return 1;
		//        } else {
		//            $this->error = $this->db->error();
		//            dol_syslog('Commande::fetch_lines: Error ' . $this->error, LOG_ERR);
		//            return -3;
		//        }
	}

	/**
	 * 	Return number of line with type product.
	 *
	 * 	@return		int		<0 if KO, Nbr of product lines if OK
	 */
	function getNbOfProductsLines() {
		$nb = 0;
		foreach ($this->lines as $line) {
			if ($line->fk_product_type == 0)
				$nb++;
		}
		return $nb;
	}

	/**
	 * 	Load array this->expeditions of nb of products sent by line in order
	 *
	 * 	@param      int		$filtre_statut      Filter on status
	 * 	@return     int                			<0 if KO, Nb of lines found if OK
	 *
	 * 	TODO deprecated, move to Shipping class
	 */
	function loadExpeditions($filtre_statut = -1) {
		$num = 0;
		$this->expeditions = array();

		$sql = 'SELECT cd.rowid, cd.fk_product,';
		$sql.= ' sum(ed.qty) as qty';
		$sql.= ' FROM ' . MAIN_DB_PREFIX . 'expeditiondet as ed,';
		if ($filtre_statut >= 0)
			$sql.= ' ' . MAIN_DB_PREFIX . 'expedition as e,';
		$sql.= ' ' . MAIN_DB_PREFIX . 'commandedet as cd';
		$sql.= ' WHERE';
		if ($filtre_statut >= 0)
			$sql.= ' ed.fk_expedition = e.rowid AND';
		$sql.= ' ed.fk_origin_line = cd.rowid';
		$sql.= ' AND cd.fk_commande =' . $this->id;
		if ($filtre_statut >= 0)
			$sql.=' AND e.fk_statut = ' . $filtre_statut;
		$sql.= ' GROUP BY cd.rowid, cd.fk_product';
		//print $sql;

		dol_syslog("Commande::loadExpeditions sql=" . $sql, LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			$num = $this->db->num_rows($result);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($result);
				$this->expeditions[$obj->rowid] = $obj->qty;
				$i++;
			}
			$this->db->free();
			return $num;
		} else {
			$this->error = $this->db->lasterror();
			dol_syslog("Commande::loadExpeditions " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * Returns a array with expeditions lines number
	 *
	 * @return	int		Nb of shipments
	 *
	 * TODO deprecated, move to Shipping class
	 */
	function nb_expedition() {
		$sql = 'SELECT count(*)';
		$sql.= ' FROM ' . MAIN_DB_PREFIX . 'expedition as e';
		$sql.= ', ' . MAIN_DB_PREFIX . 'element_element as el';
		$sql.= ' WHERE el.fk_source = ' . $this->id;
		$sql.= " AND el.fk_target = e.rowid";
		$sql.= " AND el.targettype = 'shipping'";

		$resql = $this->db->query($sql);
		if ($resql) {
			$row = $this->db->fetch_row($resql);
			return $row[0];
		}
		else
			dol_print_error($this->db);
	}

	/**
	 * 	Return a array with sendings by line
	 *
	 * 	@param      int		$filtre_statut      Filtre sur statut
	 * 	@return     int                 		0 si OK, <0 si KO
	 *
	 * 	TODO  deprecated, move to Shipping class
	 */
	function livraison_array($filtre_statut = -1) {
		$delivery = new Livraison($this->db);
		$deliveryArray = $delivery->livraison_array($filtre_statut);
		return $deliveryArray;
	}

	/**
	 * 	Return a array with the pending stock by product
	 *
	 * 	@param      int		$filtre_statut      Filtre sur statut
	 * 	@return     int                 		0 si OK, <0 si KO
	 *
	 * 	TODO		FONCTION NON FINIE A FINIR
	 */
	function stock_array($filtre_statut = -1) {
		$this->stocks = array();

		// Tableau des id de produit de la commande
		$array_of_product = array();

		// Recherche total en stock pour chaque produit
		// TODO $array_of_product est défini vide juste au dessus !!
		if (count($array_of_product)) {
			$sql = "SELECT fk_product, sum(ps.reel) as total";
			$sql.= " FROM " . MAIN_DB_PREFIX . "product_stock as ps";
			$sql.= " WHERE ps.fk_product IN (" . join(',', $array_of_product) . ")";
			$sql.= ' GROUP BY fk_product ';
			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num) {
					$obj = $this->db->fetch_object($result);
					$this->stocks[$obj->fk_product] = $obj->total;
					$i++;
				}
				$this->db->free();
			}
		}
		return 0;
	}

	/**
	 * 	Applique une remise relative
	 *
	 * 	@param     	User		$user		User qui positionne la remise
	 * 	@param     	float		$remise		Discount (percent)
	 * 	@return		int 					<0 if KO, >0 if OK
	 */
	function set_remise($user, $remise) {
		$remise = trim($remise) ? trim($remise) : 0;

		if ($user->rights->commande->creer) {
			$remise = price2num($remise);

			$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'commande';
			$sql.= ' SET remise_percent = ' . $remise;
			$sql.= ' WHERE rowid = ' . $this->id . ' AND fk_statut = 0 ;';

			if ($this->db->query($sql)) {
				$this->remise_percent = $remise;
				$this->update_price(1);
				return 1;
			} else {
				$this->error = $this->db->error();
				return -1;
			}
		}
	}

	/**
	 * 		Applique une remise absolue
	 *
	 * 		@param     	User		$user 		User qui positionne la remise
	 * 		@param     	float		$remise		Discount
	 * 		@return		int 					<0 if KO, >0 if OK
	 */
	function set_remise_absolue($user, $remise) {
		$remise = trim($remise) ? trim($remise) : 0;

		if ($user->rights->commande->creer) {
			$remise = price2num($remise);

			$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'commande';
			$sql.= ' SET remise_absolue = ' . $remise;
			$sql.= ' WHERE rowid = ' . $this->id . ' AND fk_statut = 0 ;';

			dol_syslog("Commande::set_remise_absolue sql=$sql");

			if ($this->db->query($sql)) {
				$this->remise_absolue = $remise;
				$this->update_price(1);
				return 1;
			} else {
				$this->error = $this->db->error();
				return -1;
			}
		}
	}

	/**
	 * 	Set the order date
	 *
	 * 	@param      User		$user       Object user making change
	 * 	@param      timestamp	$date		Date
	 * 	@return     int         			<0 if KO, >0 if OK
	 */
	function set_date($user, $date) {
		if ($user->rights->commande->creer) {
			$this->date = $date;
			$this->date_commande = $date;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * 	Set the planned delivery date
	 *
	 * 	@param      User			$user        		Objet utilisateur qui modifie
	 * 	@param      timestamp		$date_livraison     Date de livraison
	 * 	@return     int         						<0 si ko, >0 si ok
	 */
	function set_date_livraison($user, $date_livraison) {
		if ($user->rights->commande->creer) {
			$this->date_livraison = $date_livraison;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * 	Set availability
	 *
	 * 	@param      User	$user		Object user making change
	 * 	@param      int		$code			code of availability delay
	 * 	@return     int           		<0 if KO, >0 if OK
	 */
	function set_availability($user, $code) {
		if ($user->rights->commande->creer) {
			$this->availability_code = $code;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * 	Set source of demand
	 *
	 * 	@param      User	$user		  	Object user making change
	 * 	@param      int		$code				code of source
	 * 	@return     int           			<0 if KO, >0 if OK
	 */
	function set_demand_reason($user, $code) {
		if ($user->rights->commande->creer) {
			$this->demand_reason_code = $code;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 *  Return list of orders (eventuelly filtered on a user) into an array
	 *
	 *  @param      int		$brouillon      0=non brouillon, 1=brouillon
	 *  @param      User	$user           Objet user de filtre
	 *  @return     int             		-1 if KO, array with result if OK
	 */
	function liste_array($brouillon = 0, $user = '') {
		global $conf;

		$ga = array();

		$sql = "SELECT s.nom, s.rowid, c.rowid, c.ref";
		$sql.= " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "commande as c";
		$sql.= " WHERE c.entity = " . $conf->entity;
		$sql.= " AND c.fk_soc = s.rowid";
		if ($brouillon)
			$sql.= " AND c.fk_statut = 0";
		if ($user)
			$sql.= " AND c.fk_user_author <> " . $user->id;
		$sql .= " ORDER BY c.date_commande DESC";

		$result = $this->db->query($sql);
		if ($result) {
			$numc = $this->db->num_rows($result);
			if ($numc) {
				$i = 0;
				while ($i < $numc) {
					$obj = $this->db->fetch_object($result);

					$ga[$obj->rowid] = $obj->ref;
					$i++;
				}
			}
			return $ga;
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

	/**
	 * 	Change le delai de livraison
	 *
	 * 	@param      int		$availability_id	Id du nouveau mode
	 * 	@return     int         				>0 if OK, <0 if KO
	 */
	function availability($availability_id) {
		dol_syslog('Commande::availability(' . $availability_id . ')');
		if ($this->statut >= 0) {
			$sql = 'UPDATE ' . MAIN_DB_PREFIX . 'commande';
			$sql .= ' SET fk_availability = ' . $availability_id;
			$sql .= ' WHERE rowid=' . $this->id;
			if ($this->db->query($sql)) {
				$this->availability_id = $availability_id;
				return 1;
			} else {
				dol_syslog('Commande::availability Erreur ' . $sql . ' - ' . $this->db->error(), LOG_ERR);
				$this->error = $this->db->lasterror();
				return -1;
			}
		} else {
			dol_syslog('Commande::availability, etat facture incompatible', LOG_ERR);
			$this->error = 'Etat commande incompatible ' . $this->statut;
			return -2;
		}
	}

	/**
	 * 	Change la source de la demande
	 *
	 *  @param      int		$demand_reason_code	code of new demand
	 *  @return     int        			 		>0 if ok, <0 if ko
	 */
	function setDemandReason($demand_reason_code) {
		if ($user->rights->commande->creer) {
			$this->demand_reason_code = $demand_reason_code;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * 	Set customer ref
	 *
	 * 	@param      User	$user           User that make change
	 * 	@param      string	$ref_client     Customer ref
	 * 	@return     int             		<0 if KO, >0 if OK
	 */
	function set_ref_client($user, $ref_client) {
		if ($user->rights->commande->creer) {
			$this->ref_client = $ref_client;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * Classify the order as invoiced
	 *
	 * @return     int     <0 if ko, >0 if ok
	 */
	function classifyBilled() {
		global $conf, $user, $langs;

		$this->Status = "PROCESSED";
		$this->record();

		// Appel des triggers
		include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		$interface = new Interfaces($this->db);
		$result = $interface->run_triggers('ORDER_CLASSIFY_BILLED', $this, $user, $langs, $conf);
		if ($result < 0) {
			$error++;
			$this->errors = $interface->errors;
		}
		// Fin appel triggers

		return 1;

		//        $this->db->begin();
		//
	//        $sql = 'UPDATE ' . MAIN_DB_PREFIX . 'commande SET facture = 1';
		//        $sql.= ' WHERE rowid = ' . $this->id . ' AND fk_statut > 0';
		//
	//        dol_syslog(get_class($this) . "::classifyBilled sql=" . $sql, LOG_DEBUG);
		//        if ($this->db->query($sql)) {
		//            // Appel des triggers
		//            include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//            $interface = new Interfaces($this->db);
		//            $result = $interface->run_triggers('ORDER_CLASSIFY_BILLED', $this, $user, $langs, $conf);
		//            if ($result < 0) {
		//                $error++;
		//                $this->errors = $interface->errors;
		//            }
		//            // Fin appel triggers
		//
	//            if (!$error) {
		//                $this->facturee = 1; // deprecated
		//                $this->billed = 1;
		//
	//                $this->db->commit();
		//                return 1;
		//            } else {
		//                $this->error = $this->db->error();
		//                dol_syslog(get_class($this) . "::classifyBilled " . $this->error, LOG_ERR);
		//                $this->db->rollback();
		//                return -2;
		//            }
		//        } else {
		//            $this->error = $this->db->error();
		//            dol_syslog(get_class($this) . "::classifyBilled Error " . $this->error, LOG_ERR);
		//            $this->db->rollback();
		//            return -1;
		//        }
	}

	/**
	 * Classify the order as invoiced
	 *
	 * @return     int     <0 if ko, >0 if ok
	 */
	function classer_facturee() {
		return $this->classifyBilled();
	}

	/**
	 * 	Delete the customer order
	 *
	 * 	@param	User	$user		User object
	 * 	@param	int		$notrigger	1=Does not execute triggers, 0= execuete triggers
	 * 	@return	int					<=0 if KO, >0 if OK
	 */
	function delete($user, $notrigger = 0) {
		global $conf, $langs;
		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

		$error = 0;

		if (!$error && !$notrigger) {
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('ORDER_DELETE', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}

		// On efface le repertoire de pdf provisoire
		$comref = dol_sanitizeFileName($this->ref);
		if ($conf->commande->dir_output) {
			$dir = $conf->commande->dir_output . "/" . $comref;
			$file = $conf->commande->dir_output . "/" . $comref . "/" . $comref . ".pdf";
			if (file_exists($file)) { // We must delete all files before deleting directory
				dol_delete_preview($this);

				if (!dol_delete_file($file, 0, 0, 0, $this)) { // For triggers
					$this->db->rollback();
					return 0;
				}
			}
			if (file_exists($dir)) {
				if (!dol_delete_dir_recursive($dir)) {
					$this->error = $langs->trans("ErrorCanNotDeleteDir", $dir);
					$this->db->rollback();
					return 0;
				}
			}
		}

		$this->deleteDoc();
		return 1;


		//        $this->db->begin();
		//
		//        if (! $error && ! $notrigger)
		//        {
		//        	// Appel des triggers
		//        	include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		//        	$interface=new Interfaces($this->db);
		//        	$result=$interface->run_triggers('ORDER_DELETE',$this,$user,$langs,$conf);
		//        	if ($result < 0) {
		//        		$error++; $this->errors=$interface->errors;
		//        	}
		//        	// Fin appel triggers
		//        }
		//
		//        if (! $error)
		//        {
		//        	// Delete order details
		//        	$sql = 'DELETE FROM '.MAIN_DB_PREFIX."commandedet WHERE fk_commande = ".$this->id;
		//        	dol_syslog(get_class($this)."::delete sql=".$sql);
		//        	if (! $this->db->query($sql) )
		//        	{
		//        		dol_syslog(get_class($this)."::delete error", LOG_ERR);
		//        		$error++;
		//        	}
		//
			//        	// Delete order
		//        	$sql = 'DELETE FROM '.MAIN_DB_PREFIX."commande WHERE rowid = ".$this->id;
		//        	dol_syslog(get_class($this)."::delete sql=".$sql, LOG_DEBUG);
		//        	if (! $this->db->query($sql) )
		//        	{
		//        		dol_syslog(get_class($this)."::delete error", LOG_ERR);
		//        		$error++;
		//        	}
		//
				//        	// Delete linked object
		//        	$res = $this->deleteObjectLinked();
		//        	if ($res < 0) $error++;
		//
				//        	// Delete linked contacts
		//        	$res = $this->delete_linked_contact();
		//        	if ($res < 0) $error++;
		//
				//        	// On efface le repertoire de pdf provisoire
		//        	$comref = dol_sanitizeFileName($this->ref);
		//        	if ($conf->commande->dir_output)
		//        	{
		//        		$dir = $conf->commande->dir_output . "/" . $comref ;
		//        		$file = $conf->commande->dir_output . "/" . $comref . "/" . $comref . ".pdf";
		//        		if (file_exists($file))	// We must delete all files before deleting directory
		//        		{
		//        			dol_delete_preview($this);
		//
					//        			if (! dol_delete_file($file,0,0,0,$this)) // For triggers
		//        			{
		//        				$this->db->rollback();
		//        				return 0;
		//        			}
		//        		}
		//        		if (file_exists($dir))
		//        		{
		//        			if (! dol_delete_dir_recursive($dir))
		//        			{
		//        				$this->error=$langs->trans("ErrorCanNotDeleteDir",$dir);
		//        				$this->db->rollback();
		//        				return 0;
		//        			}
		//        		}
		//        	}
		//        }
		//
							//        if (! $error)
		//        {
		//        	dol_syslog(get_class($this)."::delete $this->id by $user->id", LOG_DEBUG);
		//        	$this->db->commit();
		//        	return 1;
		//        }
		//        else
		//        {
		//            $this->error=$this->db->lasterror();
		//            dol_syslog(get_class($this)."::delete ".$this->error, LOG_ERR);
		//            $this->db->rollback();
		//            return -1;
		//        }
	}

	/**
	 * 	Load indicators for dashboard (this->nbtodo and this->nbtodolate)
	 *
	 * 	@param		User	$user   Object user
	 * 	@return     int     		<0 if KO, >0 if OK
	 */
	function load_board($user) {
		global $conf, $user;

		$now = dol_now();

		$this->nbtodo = $this->nbtodolate = 0;
		$clause = " WHERE";

		$sql = "SELECT c.rowid, c.date_creation as datec, c.fk_statut";
		$sql.= " FROM " . MAIN_DB_PREFIX . "commande as c";
		if (!$user->rights->societe->client->voir && !$user->societe_id) {
			$sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON c.fk_soc = sc.fk_soc";
			$sql.= " WHERE sc.fk_user = " . $user->id;
			$clause = " AND";
		}
		$sql.= $clause . " c.entity = " . $conf->entity;
		//$sql.= " AND c.fk_statut IN (1,2,3) AND c.facture = 0";
		$sql.= " AND ((c.fk_statut IN (1,2)) OR (c.fk_statut = 3 AND c.facture = 0))"; // If status is 2 and facture=1, it must be selected
		if ($user->societe_id)
			$sql.=" AND c.fk_soc = " . $user->societe_id;

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$this->nbtodo++;
				if ($obj->fk_statut != 3 && $this->db->jdate($obj->datec) < ($now - $conf->commande->client->warning_delay))
					$this->nbtodolate++;
			}
			return 1;
		}
		else {
			$this->error = $this->db->error();
			return -1;
		}
	}

	/**
	 * 	Return source label of order
	 *
	 * 	@return     string      Label
	 */
	function getLabelSource() {
		global $langs;

		$label = $langs->trans('OrderSource' . $this->source);

		if ($label == 'OrderSource')
			return '';
		return $label;
	}

	/**
	 * 	Return status label of Order
	 *
	 * 	@param      int		$mode       0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
	 * 	@return     string      		Libelle
	 */
	function getLibStatut($mode) {
		return $this->LibStatut($this->statut, $this->facturee, $mode);
	}

	/**
	 * 	Return label of status
	 *
	 * 	@param		int		$statut      	Id statut
	 *  @param      int		$facturee    	if invoiced
	 * 	@param      int		$mode        	0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
	 *  @return     string					Label of status
	 */
	function LibStatut($statut, $facturee, $mode) {
		global $langs;
		//print 'x'.$statut.'-'.$facturee;
		if ($mode == 0) {
			if ($statut == -1)
				return $langs->trans('StatusOrderCanceled');
			if ($statut == 0)
				return $langs->trans('StatusOrderDraft');
			if ($statut == 1)
				return $langs->trans('StatusOrderValidated');
			if ($statut == 2)
				return $langs->trans('StatusOrderSentShort');
			if ($statut == 3 && (!$facturee && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return $langs->trans('StatusOrderToBill');
			if ($statut == 3 && ($facturee || !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return $langs->trans('StatusOrderProcessed');
		}
		elseif ($mode == 1) {
			if ($statut == -1)
				return $langs->trans('StatusOrderCanceledShort');
			if ($statut == 0)
				return $langs->trans('StatusOrderDraftShort');
			if ($statut == 1)
				return $langs->trans('StatusOrderValidatedShort');
			if ($statut == 2)
				return $langs->trans('StatusOrderSentShort');
			if ($statut == 3 && (!$facturee && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return $langs->trans('StatusOrderToBillShort');
			if ($statut == 3 && ($facturee || !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return $langs->trans('StatusOrderProcessed');
		}
		elseif ($mode == 2) {
			if ($statut == -1)
				return img_picto($langs->trans('StatusOrderCanceled'), 'statut5') . ' ' . $langs->trans('StatusOrderCanceledShort');
			if ($statut == 0)
				return img_picto($langs->trans('StatusOrderDraft'), 'statut0') . ' ' . $langs->trans('StatusOrderDraftShort');
			if ($statut == 1)
				return img_picto($langs->trans('StatusOrderValidated'), 'statut1') . ' ' . $langs->trans('StatusOrderValidatedShort');
			if ($statut == 2)
				return img_picto($langs->trans('StatusOrderSent'), 'statut3') . ' ' . $langs->trans('StatusOrderSentShort');
			if ($statut == 3 && (!$facturee && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return img_picto($langs->trans('StatusOrderToBill'), 'statut7') . ' ' . $langs->trans('StatusOrderToBillShort');
			if ($statut == 3 && ($facturee || !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return img_picto($langs->trans('StatusOrderProcessed'), 'statut6') . ' ' . $langs->trans('StatusOrderProcessedShort');
		}
		elseif ($mode == 3) {
			if ($statut == -1)
				return img_picto($langs->trans('StatusOrderCanceled'), 'statut5');
			if ($statut == 0)
				return img_picto($langs->trans('StatusOrderDraft'), 'statut0');
			if ($statut == 1)
				return img_picto($langs->trans('StatusOrderValidated'), 'statut1');
			if ($statut == 2)
				return img_picto($langs->trans('StatusOrderSentShort'), 'statut3');
			if ($statut == 3 && (!$facturee && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return img_picto($langs->trans('StatusOrderToBill'), 'statut7');
			if ($statut == 3 && ($facturee || !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return img_picto($langs->trans('StatusOrderProcessed'), 'statut6');
		}
		elseif ($mode == 4) {
			if ($statut == -1)
				return img_picto($langs->trans('StatusOrderCanceled'), 'statut5') . ' ' . $langs->trans('StatusOrderCanceled');
			if ($statut == 0)
				return img_picto($langs->trans('StatusOrderDraft'), 'statut0') . ' ' . $langs->trans('StatusOrderDraft');
			if ($statut == 1)
				return img_picto($langs->trans('StatusOrderValidated'), 'statut1') . ' ' . $langs->trans('StatusOrderValidated');
			if ($statut == 2)
				return img_picto($langs->trans('StatusOrderSentShort'), 'statut3') . ' ' . $langs->trans('StatusOrderSent');
			if ($statut == 3 && (!$facturee && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return img_picto($langs->trans('StatusOrderToBill'), 'statut7') . ' ' . $langs->trans('StatusOrderToBill');
			if ($statut == 3 && ($facturee || !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return img_picto($langs->trans('StatusOrderProcessed'), 'statut6') . ' ' . $langs->trans('StatusOrderProcessed');
		}
		elseif ($mode == 5) {
			if ($statut == -1)
				return $langs->trans('StatusOrderCanceledShort') . ' ' . img_picto($langs->trans('StatusOrderCanceled'), 'statut5');
			if ($statut == 0)
				return $langs->trans('StatusOrderDraftShort') . ' ' . img_picto($langs->trans('StatusOrderDraft'), 'statut0');
			if ($statut == 1)
				return $langs->trans('StatusOrderValidatedShort') . ' ' . img_picto($langs->trans('StatusOrderValidated'), 'statut1');
			if ($statut == 2)
				return $langs->trans('StatusOrderSentShort') . ' ' . img_picto($langs->trans('StatusOrderSent'), 'statut3');
			if ($statut == 3 && (!$facturee && empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return $langs->trans('StatusOrderToBillShort') . ' ' . img_picto($langs->trans('StatusOrderToBill'), 'statut7');
			if ($statut == 3 && ($facturee || !empty($conf->global->WORKFLOW_BILL_ON_SHIPMENT)))
				return $langs->trans('StatusOrderProcessedShort') . ' ' . img_picto($langs->trans('StatusOrderProcessed'), 'statut6');
		}
	}

	/**
	 * 	Return clicable link of object (with eventually picto)
	 *
	 * 	@param      int			$withpicto      Add picto into link
	 * 	@param      int			$option         Where point the link (0=> main card, 1,2 => shipment)
	 * 	@param      int			$max          	Max length to show
	 * 	@param      int			$short			Use short labels
	 * 	@return     string          			String with URL
	 */
	function getNomUrl($withpicto = 0, $option = 0, $max = 0, $short = 0) {
		global $conf, $langs;

		$result = '';

		if (!empty($conf->expedition->enabled) && ($option == 1 || $option == 2))
			$url = DOL_URL_ROOT . '/expedition/shipment.php?id=' . $this->id;
		else
			$url = DOL_URL_ROOT . '/commande/commande.php?id=' . $this->id;

		if ($short)
			return $url;

		$linkstart = '<a href="' . $url . '">';
		$linkend = '</a>';

		$picto = 'order';
		$label = $langs->trans("ShowOrder") . ': ' . $this->ref;

		if ($withpicto)
			$result.=($linkstart . img_object($label, $picto) . $linkend);
		if ($withpicto && $withpicto != 2)
			$result.=' ';
		$result.=$linkstart . $this->ref . $linkend;
		return $result;
	}

	/**
	 * 	Charge les informations d'ordre info dans l'objet commande
	 *
	 * 	@param  int		$id       Id of order
	 * 	@return	void
	 */
	function info($id) {
		$sql = 'SELECT c.rowid, date_creation as datec, tms as datem,';
		$sql.= ' date_valid as datev,';
		$sql.= ' date_cloture as datecloture,';
		$sql.= ' fk_user_author, fk_user_valid, fk_user_cloture';
		$sql.= ' FROM ' . MAIN_DB_PREFIX . 'commande as c';
		$sql.= ' WHERE c.rowid = ' . $id;
		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result)) {
				$obj = $this->db->fetch_object($result);
				$this->id = $obj->rowid;
				if ($obj->fk_user_author) {
					$cuser = new User($this->db);
					$cuser->fetch($obj->fk_user_author);
					$this->user_creation = $cuser;
				}

				if ($obj->fk_user_valid) {
					$vuser = new User($this->db);
					$vuser->fetch($obj->fk_user_valid);
					$this->user_validation = $vuser;
				}

				if ($obj->fk_user_cloture) {
					$cluser = new User($this->db);
					$cluser->fetch($obj->fk_user_cloture);
					$this->user_cloture = $cluser;
				}

				$this->date_creation = $this->db->jdate($obj->datec);
				$this->date_modification = $this->db->jdate($obj->datem);
				$this->date_validation = $this->db->jdate($obj->datev);
				$this->date_cloture = $this->db->jdate($obj->datecloture);
			}

			$this->db->free($result);
		} else {
			dol_print_error($this->db);
		}
	}

	/**
	 *  Initialise an instance with random values.
	 *  Used to build previews or test instances.
	 * 	id must be 0 if object instance is a specimen.
	 *
	 *  @return	void
	 */
	function initAsSpecimen() {
		global $user, $langs, $conf;

		dol_syslog(get_class($this) . "::initAsSpecimen");

		// Charge tableau des produits prodids
		$prodids = array();
		$sql = "SELECT rowid";
		$sql.= " FROM " . MAIN_DB_PREFIX . "product";
		$sql.= " WHERE entity IN (" . getEntity('product', 1) . ")";
		$resql = $this->db->query($sql);
		if ($resql) {
			$num_prods = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num_prods) {
				$i++;
				$row = $this->db->fetch_row($resql);
				$prodids[$i] = $row[0];
			}
		}

		// Initialise parametres
		$this->id = 0;
		$this->ref = 'SPECIMEN';
		$this->specimen = 1;
		$this->socid = 1;
		$this->date = time();
		$this->date_lim_reglement = $this->date + 3600 * 24 * 30;
		$this->cond_reglement_code = 'RECEP';
		$this->mode_reglement_code = 'CHQ';
		$this->availability_code = 'DSP';
		$this->demand_reason_code = 'SRC_00';
		$this->note_public = 'This is a comment (public)';
		$this->note = 'This is a comment (private)';
		// Lines
		$nbp = 5;
		$xnbp = 0;
		while ($xnbp < $nbp) {
			$line = new OrderLine($this->db);

			$line->desc = $langs->trans("Description") . " " . $xnbp;
			$line->qty = 1;
			$line->subprice = 100;
			$line->price = 100;
			$line->tva_tx = 19.6;
			if ($xnbp == 2) {
				$line->total_ht = 50;
				$line->total_ttc = 59.8;
				$line->total_tva = 9.8;
				$line->remise_percent = 50;
			} else {
				$line->total_ht = 100;
				$line->total_ttc = 119.6;
				$line->total_tva = 19.6;
				$line->remise_percent = 0;
			}
			$prodid = rand(1, $num_prods);
			$line->fk_product = $prodids[$prodid];

			$this->lines[$xnbp] = $line;

			$this->total_ht += $line->total_ht;
			$this->total_tva += $line->total_tva;
			$this->total_ttc += $line->total_ttc;

			$xnbp++;
		}
	}

	/**
	 * 	Charge indicateurs this->nb de tableau de bord
	 *
	 * 	@return     int         <0 si ko, >0 si ok
	 */
	function load_state_board() {
		global $conf, $user;

		$this->nb = array();
		$clause = "WHERE";

		$sql = "SELECT count(co.rowid) as nb";
		$sql.= " FROM " . MAIN_DB_PREFIX . "commande as co";
		$sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON co.fk_soc = s.rowid";
		if (!$user->rights->societe->client->voir && !$user->societe_id) {
			$sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON s.rowid = sc.fk_soc";
			$sql.= " WHERE sc.fk_user = " . $user->id;
			$clause = "AND";
		}
		$sql.= " " . $clause . " co.entity = " . $conf->entity;

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$this->nb["orders"] = $obj->nb;
			}
			return 1;
		} else {
			dol_print_error($this->db);
			$this->error = $this->db->error();
			return -1;
		}
	}

	public function getExtraFieldLabel($field) {
		global $langs;
		if (!empty($field) && !empty($this->$field)) // for avoid error
			return $langs->trans($this->fk_extrafields->fields->{$field}->values->{$this->$field}->label);
	}

	/**
	 * 	Change payment terms
	 *
	 * 	@param	string $code_reglement_code 	Code of new payment term
	 * 	@return int						>0 si ok, <0 si ko
	 */
	function setPaymentTerms($cond_reglement_code) {
		if ($this->Status == "DRAFT") {
			$this->cond_reglement_code = $cond_reglement_code;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * 	Change payment methods
	 *
	 * 	@param	string $mode_reglement_code 	Code of new payment method
	 * 	@return int						>0 si ok, <0 si ko
	 */
	function setPaymentMethods($mode_reglement_code) {
		if ($this->Status == "DRAFT") {
			$this->mode_reglement_code = $mode_reglement_code;
			$this->record();
			return 1;
		}
		return -2;
	}

	/**
	 * 	Change availability
	 *
	 * 	@param	string $availability_code 	Code of new availability
	 * 	@return int						>0 si ok, <0 si ko
	 */
	function setAvailability($availability_code) {
		if ($this->Status == "DRAFT") {
			$this->availability_code = $availability_code;
			$this->record();
			return 1;
		}
		return -2;
	}

	/*
	 * Graph comptes by status
	 *
	 */

	function graphPieStatus($json = false) {
		global $user, $conf, $langs;

		//$color = array(-1 => "#A51B00", 0 => "#CCC", 1 => "#000", 2 => "#FEF4AE", 3 => "#666", 4 => "#1f17c1", 5 => "#DE7603", 6 => "#D40000", 7 => "#7ac52e", 8 => "#1b651b", 9 => "#66c18c", 10 => "#2e99a0");

		if ($json) { // For Data see viewgraph.php
			$langs->load("orders");

			$params = array('group' => true);
			$result = $this->getView("count_status", $params);
			$filter = false;

			//print_r($result);
			$i = 0;

			foreach ($result->rows as $aRow) {
				if ($filter)
					$key = $aRow->key[1];
				else
					$key = $aRow->key;
				$label = $langs->trans($this->fk_extrafields->fields->Status->values->$key->label);
				if (empty($label))
					$label = $langs->trans($aRow->key);

				if ($i == 0) { // first element
					$output[$i] = new stdClass();
					$output[$i]->name = $label;
					$output[$i]->y = $aRow->value;
					$output[$i]->sliced = true;
					$output[$i]->selected = true;
				}
				else
					$output[$i] = array($label, $aRow->value);
				$i++;
			}
			return $output;
		} else {
			$total = 0;
			$i = 0;
			?>
			<div
				id="pie-status" style="min-width: 100px; height: 280px; margin: 0 auto"></div>
			<script type="text/javascript">
				$(document).ready(function() {
					(function($) { // encapsulate jQuery

						$(function() {
							var seriesOptions = [],
									yAxisOptions = [],
									seriesCounter = 0,
									colors = Highcharts.getOptions().colors;

							$.getJSON('<?php echo DOL_URL_ROOT . '/core/ajax/viewgraph.php'; ?>?json=graphPieStatus&class=<?php echo get_class($this); ?>&callback=?', function(data) {

								seriesOptions = data;

								createChart();

							});


							// create the chart when all data is loaded
							function createChart() {
								var chart;

								chart = new Highcharts.Chart({
									chart: {
										renderTo: "pie-status",
										//defaultSeriesType: "bar",
										margin: 0,
										plotBackgroundColor: null,
										plotBorderWidth: null,
										plotShadow: false
									},
									legend: {
										layout: "vertical", backgroundColor: Highcharts.theme.legendBackgroundColor || "#FFFFFF", align: "left", verticalAlign: "bottom", x: 0, y: 20, floating: true, shadow: true,
										enabled: false
									},
									title: {
										text: null
									},
									tooltip: {
										enabled: true,
										pointFormat: '{series.name}: <b>{point.percentage}%</b>',
										percentageDecimals: 2
									},
									navigator: {
										margin: 30
									},
									plotOptions: {
										pie: {
											allowPointSelect: true,
											cursor: 'pointer',
											dataLabels: {
												enabled: true,
												color: '#FFF',
												connectorColor: '#FFF',
												distance: 30,
												formatter: function() {
													return '<b>' + this.point.name + '</b><br> ' + Math.round(this.percentage) + ' %';
												}
											}
										}
									},
									series: [{
											type: "pie",
											name: "<?php echo $langs->trans("Quantity"); ?>",
											size: 100,
											data: seriesOptions
										}]
								});
							}

						});
					})(jQuery);
				});
			</script>
			<?php
		}
	}

	/*
	 * Graph comptes by status
	 *
	 */

	function graphBarStatus($json = false) {
		global $user, $conf, $langs;

		$langs->load("orders");

		if ($json) { // For Data see viewgraph.php
			$keystart[0] = $_GET["name"];
			$keyend[0] = $_GET["name"];
			$keyend[1] = new stdClass();

			$params = array('group' => true, 'group_level' => 2, 'startkey' => $keystart, 'endkey' => $keyend);
			$result = $this->getView("count_status", $params);

			foreach ($this->fk_extrafields->fields->Status->values as $key => $aRow) {
				//print_r($aRow);exit;
				$label = $langs->trans($key);
				if ($aRow->enable) {
					$tab[$key]->label = $label;
					$tab[$key]->value = 0;
				}
			}

			foreach ($result->rows as $aRow) // Update counters from view
				$tab[$aRow->key[1]]->value+=$aRow->value;

			foreach ($tab as $aRow)
				$output[] = array($aRow->label, $aRow->value);

			return $output;
		} else {
			$total = 0;
			$i = 0;
			?>
			<div id="bar-status" style="min-width: 100px; height: 280px; margin: 0 auto"></div>
			<script type="text/javascript">
				$(document).ready(function() {
					(function($) { // encapsulate jQuery

						$(function() {
							var seriesOptions = [],
									yAxisOptions = [],
									seriesCounter = 0,
									names = [<?php
			$params = array('group' => true, 'group_level' => 1);
			$result = $this->getView("commercial_status", $params);

			if (count($result->rows)) {
				foreach ($result->rows as $aRow) {
					if ($i == 0)
						echo "'" . $aRow->key[0] . "'";
					else
						echo ",'" . $aRow->key[0] . "'";
					$i++;
				}
			}
			?>],
									colors = Highcharts.getOptions().colors;
							$.each(names, function(i, name) {

								$.getJSON('<?php echo DOL_URL_ROOT . '/core/ajax/viewgraph.php'; ?>?json=graphBarStatus&class=<?php echo get_class($this); ?>&name=' + name.toString() + '&callback=?', function(data) {

									seriesOptions[i] = {
										name: name,
										data: data
									};

									// As we're loading the data asynchronously, we don't know what order it will arrive. So
									// we keep a counter and create the chart when all the data is loaded.
									seriesCounter++;

									if (seriesCounter == names.length) {
										createChart();
									}
								});
							});


							// create the chart when all data is loaded
							function createChart() {
								var chart;

								chart = new Highcharts.Chart({
									chart: {
										renderTo: 'bar-status',
										defaultSeriesType: "column",
										zoomType: "x",
										marginBottom: 30
									},
									credits: {
										enabled: false
									},
									xAxis: {
										categories: [<?php
			$i = 0;
			foreach ($this->fk_extrafields->fields->Status->values as $key => $aRow) {
				$label = $langs->trans($aRow->label);
				if (empty($label))
					$label = $langs->trans($key);
				if ($aRow->enable) {
					if ($i == 0)
						echo "'" . $label . "'";
					else
						echo ",'" . $label . "'";

					$i++;
				}
			}
			?>],
										maxZoom: 1
												//labels: {rotation: 90, align: "left"}
									},
									yAxis: {
										title: {text: "Total"},
										allowDecimals: false,
										min: 0
									},
									title: {
										//text: "<?php echo $langs->trans("SalesRepresentatives"); ?>"
										text: null
									},
									legend: {
										layout: 'vertical',
										align: 'right',
										verticalAlign: 'top',
										x: -5,
										y: 5,
										floating: true,
										borderWidth: 1,
										backgroundColor: Highcharts.theme.legendBackgroundColor || '#FFFFFF',
										shadow: true
									},
									tooltip: {
										enabled: true,
										formatter: function() {
											//return this.point.name + ' : ' + this.y;
											return '<b>' + this.x + '</b><br/>' +
													this.series.name + ': ' + this.y;
										}
									},
									series: seriesOptions
								});
							}

						});
					})(jQuery);
				});
			</script>
			<?php
		}
	}

	public function getLinkedObject() {

		$objects = array();

		// Object stored in $this->linked_objects;
		foreach ($this->linked_objects as $obj) {
			switch ($obj->type) {
				case 'propal':
					$classname = 'Propal';
					dol_include_once('propal/class/propal.class.php');
					break;
			}
			$tmp = new $classname($this->db);
			$tmp->fetch($obj->id);
			$objects[$obj->type][] = $tmp;
		}

		// Objects that refer current propal in their $linked_objects variable.
		$res = $this->getView('listLinkedObjects', array('key' => $this->id));
		if (count($res->rows) > 0) {
			foreach ($res->rows as $r) {
				$classname = $r->value->class;
				if ($classname == 'Facture')
					require_once(DOL_DOCUMENT_ROOT . '/facture/class/facture.class.php');
				$obj = new $classname($this->db);
				$obj->fetch($r->value->id);
				$objects[strtolower($classname)][] = $obj;
			}
		}

		return $objects;
	}

	public function printLinkedObjects() {

		global $langs;

		$objects = $this->getLinkedObject();

		// Displaying linked propals
		if (isset($objects['propal'])) {
			$this->printLinkedObjectsType('propal', $objects['propal']);
		}

		// Displaying linked invoices
		if (isset($objects['facture'])) {
			$this->printLinkedObjectsType('facture', $objects['facture']);
		}
	}

	public function printLinkedObjectsType($type, $data) {

		global $langs;

		$title = 'LinkedObjects';
		if ($type == 'propal')
			$title = 'LinkedProposals';
		else if ($type == 'facture')
			$title = 'LinkedInvoices';


		print start_box($langs->trans($title), "six", $this->fk_extrafields->ico, false);
		print '<table id="tablelines" class="noborder" width="100%">';
		print '<tr>';
		print '<th align="left">' . $langs->trans('Ref') . '</th>';
		print '<th align="left">' . $langs->trans('Date') . '</th>';
		print '<th align="left">' . $langs->trans('PriceHT') . '</th>';
		print '<th align="left">' . $langs->trans('Status') . '</th>';
		print '</tr>';
		foreach ($data as $p) {
			print '<tr>';
			print '<td>' . $p->getNomUrl(1) . '</td>';
			print '<td>' . dol_print_date($p->date) . '</td>';
			print '<td>' . price($p->total_ht) . '</td>';
			print '<td>' . $p->getExtraFieldLabel('Status') . '</td>';
			print '</tr>';
		}
		print '</table>';
		print end_box();
	}

	public function showLinkedObjects() {
		global $langs;

		print start_box($langs->trans("LinkedObjects"), $this->fk_extrafields->ico);

		print '<table class="display dt_act" id="listlinkedobjects" >';
		// Ligne des titres

		print '<thead>';
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
		//        $obj->aoColumns[$i]->fnRender = $this->datatablesFnRender("ref", "url");
		$i++;
		print'<th class="essential">';
		print $langs->trans('Date');
		print'</th>';
		$obj->aoColumns[$i] = new stdClass();
		$obj->aoColumns[$i]->mDataProp = "date";
		$obj->aoColumns[$i]->sDefaultContent = "";
		$obj->aoColumns[$i]->fnRender = $this->datatablesFnRender("date", "date");
		$i++;
		print'<th class="essential">';
		print $langs->trans('PriceHT');
		print'</th>';
		$obj->aoColumns[$i] = new stdClass();
		$obj->aoColumns[$i]->mDataProp = "total_ht";
		$obj->aoColumns[$i]->sDefaultContent = "";
		$obj->aoColumns[$i]->fnRender = $this->datatablesFnRender("total_ht", "price");
		$i++;
		print'<th class="essential">';
		print $langs->trans('Status');
		print'</th>';
		$obj->aoColumns[$i] = new stdClass();
		$obj->aoColumns[$i]->mDataProp = "Status";
		$obj->aoColumns[$i]->sDefaultContent = "";
		$obj->aoColumns[$i]->fnRender = $this->datatablesFnRender("Status", "status");

		$i++;
		print '</tr>';
		print '</thead>';
		print'<tfoot>';
		print'</tfoot>';
		print'<tbody>';
		print'</tbody>';
		print "</table>";

		$obj->iDisplayLength = $max;
		$obj->aaSorting = array(array(1, 'asc'));
		$obj->sAjaxSource = DOL_URL_ROOT . "/core/ajax/listdatatables.php?json=listLinkedObjects&class=" . get_class($this) . "&key=" . $this->id;
		$this->datatablesCreate($obj, "listlinkedobjects", true);
		print end_box();
	}

	public function addInPlace($obj) {

		global $user;

		// Converting date to timestamp
		$date = explode('/', $this->date);
		$this->date = $obj->date = dol_mktime(0, 0, 0, $date[1], $date[0], $date[2]);

		// Generating next ref
		$this->ref = $obj->ref = $this->getNextNumRef();

		// Setting author of propal
		$this->author = new stdClass();
		$this->author->id = $user->id;
		$this->author->name = $user->login;
	}

	public function fetch_thirdparty() {

		$thirdparty = new Societe($this->db);
		$thirdparty->load($this->client->id);
		$this->thirdparty = $thirdparty;
	}

	public function show($id) {

		global $langs;

		require_once(DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php');
		$commande = new Commande($this->db);

		print start_box($langs->trans("Orders"), $this->fk_extrafields->ico);

		print '<table class="display dt_act" id="listcommandes" >';
		// Ligne des titres

		print '<thead>';
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
		$obj->aoColumns[$i]->fnRender = $commande->datatablesFnRender("ref", "url");
		$i++;
		print'<th class="essential">';
		print $langs->trans('Date');
		print'</th>';
		$obj->aoColumns[$i] = new stdClass();
		$obj->aoColumns[$i]->mDataProp = "date";
		$obj->aoColumns[$i]->sDefaultContent = "";
		$obj->aoColumns[$i]->fnRender = $commande->datatablesFnRender("date", "date");
		$i++;
		print'<th class="essential">';
		print $langs->trans('PriceHT');
		print'</th>';
		$obj->aoColumns[$i] = new stdClass();
		$obj->aoColumns[$i]->mDataProp = "total_ht";
		$obj->aoColumns[$i]->sDefaultContent = "";
		$obj->aoColumns[$i]->fnRender = $commande->datatablesFnRender("total_ht", "price");
		$i++;
		print'<th class="essential">';
		print $langs->trans('Status');
		print'</th>';
		$obj->aoColumns[$i] = new stdClass();
		$obj->aoColumns[$i]->mDataProp = "Status";
		$obj->aoColumns[$i]->sDefaultContent = "";
		$obj->aoColumns[$i]->fnRender = $commande->datatablesFnRender("Status", "status");

		$i++;
		print '</tr>';
		print '</thead>';
		print'<tfoot>';
		print'</tfoot>';
		print'<tbody>';
		print'</tbody>';
		print "</table>";

		$obj->iDisplayLength = $max;
		$obj->sAjaxSource = DOL_URL_ROOT . "/core/ajax/listdatatables.php?json=listBySociete&class=" . get_class($this) . "&key=" . id;
		$this->datatablesCreate($obj, "listcommandes", true);
		print end_box();
	}

}

/**
 *  \class      OrderLine
 *  \brief      Classe de gestion des lignes de commande
 */
class OrderLine extends nosqlDocument {

	var $db;
	var $error;
	var $oldline;
	// From llx_commandedet
	var $rowid;
	var $fk_parent_line;
	var $fk_facture;
	var $label;
	var $description;  // Description ligne
	var $fk_product;  // Id produit predefini
	var $product_type = 0; // Type 0 = product, 1 = Service
	var $qty; // Quantity (example 2)
	var $tva_tx;   // VAT Rate for product/service (example 19.6)
	var $localtax1_tx;   // Local tax 1
	var $localtax2_tx;   // Local tax 2
	var $subprice; // U.P. HT (example 100)
	var $remise_percent; // % for line discount (example 20%)
	var $fk_remise_except;
	var $rang = 0;
	var $fk_fournprice;
	var $pa_ht;
	var $marge_tx;
	var $marque_tx;
	var $info_bits = 0;  // Bit 0: 	0 si TVA normal - 1 si TVA NPR
	// Bit 1:	0 ligne normale - 1 si ligne de remise fixe
	var $special_code = 0;
	var $total_ht;   // Total HT  de la ligne toute quantite et incluant la remise ligne
	var $total_tva;   // Total TVA  de la ligne toute quantite et incluant la remise ligne
	var $total_localtax1;   // Total local tax 1 for the line
	var $total_localtax2;   // Total local tax 2 for the line
	var $total_ttc;   // Total TTC de la ligne toute quantite et incluant la remise ligne
	// Ne plus utiliser
	var $remise;
	var $price;
	// From llx_product
	var $ref; // deprecated
	var $libelle;   // deprecated
	var $product_ref;
	var $product_label;  // Label produit
	var $product_desc;   // Description produit
	// Added by Matelli (See http://matelli.fr/showcases/patchs-dolibarr/add-dates-in-order-lines.html)
	// Start and end date of the line
	var $date_start;
	var $date_end;
	var $skip_update_total; // Skip update price total for special lines

	/**
	 *      Constructor
	 *
	 *      @param     DoliDB	$db      handler d'acces base de donnee
	 */

	function __construct($db = '') {
		parent::__construct($db);
	}

	/**
	 *  Load line order
	 *
	 *  @param  int		$rowid          Id line order
	 *  @return	int						<0 if KO, >0 if OK
	 */
	function fetch($rowid) {
		return parent::fetch($rowid);
		//        $sql = 'SELECT cd.rowid, cd.fk_commande, cd.fk_parent_line, cd.fk_product, cd.product_type, cd.label as custom_label, cd.description, cd.price, cd.qty, cd.tva_tx, cd.localtax1_tx, cd.localtax2_tx,';
		//        $sql.= ' cd.remise, cd.remise_percent, cd.fk_remise_except, cd.subprice,';
		//        $sql.= ' cd.info_bits, cd.total_ht, cd.total_tva, cd.total_localtax1, cd.total_localtax2, cd.total_ttc, cd.fk_product_fournisseur_price as fk_fournprice, cd.buy_price_ht as pa_ht, cd.rang, cd.special_code,';
		//        $sql.= ' p.ref as product_ref, p.label as product_libelle, p.description as product_desc,';
		//        $sql.= ' cd.date_start, cd.date_end';
		//        $sql.= ' FROM '.MAIN_DB_PREFIX.'commandedet as cd';
		//        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON cd.fk_product = p.rowid';
		//        $sql.= ' WHERE cd.rowid = '.$rowid;
		//        $result = $this->db->query($sql);
		//        if ($result)
		//        {
		//            $objp = $this->db->fetch_object($result);
		//            $this->rowid            = $objp->rowid;
		//            $this->fk_commande      = $objp->fk_commande;
		//            $this->fk_parent_line   = $objp->fk_parent_line;
		//            $this->label            = $objp->custom_label;
		//            $this->description             = $objp->description;
		//            $this->qty              = $objp->qty;
		//            $this->price            = $objp->price;
		//            $this->subprice         = $objp->subprice;
		//            $this->tva_tx           = $objp->tva_tx;
		//            $this->localtax1_tx		= $objp->localtax1_tx;
		//            $this->localtax2_tx		= $objp->localtax2_tx;
		//            $this->remise           = $objp->remise;
		//            $this->remise_percent   = $objp->remise_percent;
		//            $this->fk_remise_except = $objp->fk_remise_except;
		//            $this->fk_product       = $objp->fk_product;
		//            $this->product_type     = $objp->product_type;
		//            $this->info_bits        = $objp->info_bits;
		//            $this->total_ht         = $objp->total_ht;
		//            $this->total_tva        = $objp->total_tva;
		//            $this->total_localtax1  = $objp->total_localtax1;
		//            $this->total_localtax2  = $objp->total_localtax2;
		//            $this->total_ttc        = $objp->total_ttc;
		//			$this->fk_fournprice	= $objp->fk_fournprice;
		//			$marginInfos			= getMarginInfos($objp->subprice, $objp->remise_percent, $objp->tva_tx, $objp->localtax1_tx, $objp->localtax2_tx, $this->fk_fournprice, $objp->pa_ht);
		//			$this->pa_ht			= $marginInfos[0];
		//			$this->marge_tx			= $marginInfos[1];
		//			$this->marque_tx		= $marginInfos[2];
		//            $this->special_code		= $objp->special_code;
		//            $this->rang             = $objp->rang;
		//
        //            $this->ref				= $objp->product_ref;      // deprecated
		//            $this->product_ref		= $objp->product_ref;
		//            $this->libelle			= $objp->product_libelle;  // deprecated
		//            $this->product_label	= $objp->product_libelle;
		//            $this->product_desc     = $objp->product_desc;
		//
        //            $this->date_start       = $this->db->jdate($objp->date_start);
		//            $this->date_end         = $this->db->jdate($objp->date_end);
		//
        //            $this->db->free($result);
		//        }
		//        else
		//        {
		//            dol_print_error($this->db);
		//        }
	}

	/**
	 * 	Delete line in database
	 *
	 * 	@return	 int  <0 si ko, >0 si ok
	 */
	function delete() {
		global $conf, $user, $langs;

		$error = 0;
		$this->deleteDoc();
		//
		//        $sql = 'DELETE FROM '.MAIN_DB_PREFIX."commandedet WHERE rowid='".$this->rowid."';";
		//
        //        dol_syslog("OrderLine::delete sql=".$sql);
		//        $resql=$this->db->query($sql);
		//        if ($resql)
		//        {
		// Appel des triggers
		include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
		$interface = new Interfaces($this->db);
		$result = $interface->run_triggers('LINEORDER_DELETE', $this, $user, $langs, $conf);
		if ($result < 0) {
			$error++;
			$this->errors = $interface->errors;
		}
		// Fin appel triggers

		return 1;
		//        }
		//        else
		//        {
		//            $this->error=$this->db->lasterror();
		//            dol_syslog("OrderLine::delete ".$this->error, LOG_ERR);
		//            return -1;
		//        }
	}

	/**
	 * 	Insert line into database
	 *
	 * 	@param      int		$notrigger		1 = disable triggers
	 * 	@return		int						<0 if KO, >0 if OK
	 */
	function insert($notrigger = 0) {
		global $langs, $conf, $user;

		$error = 0;

		dol_syslog("OrderLine::insert rang=" . $this->rang);

		// Clean parameters
		if (empty($this->tva_tx))
			$this->tva_tx = 0;
		if (empty($this->localtax1_tx))
			$this->localtax1_tx = 0;
		if (empty($this->localtax2_tx))
			$this->localtax2_tx = 0;
		if (empty($this->total_localtax1))
			$this->total_localtax1 = 0;
		if (empty($this->total_localtax2))
			$this->total_localtax2 = 0;
		if (empty($this->rang))
			$this->rang = 0;
		if (empty($this->remise))
			$this->remise = 0;
		if (empty($this->remise_percent))
			$this->remise_percent = 0;
		if (empty($this->info_bits))
			$this->info_bits = 0;
		if (empty($this->special_code))
			$this->special_code = 0;
		if (empty($this->fk_parent_line))
			$this->fk_parent_line = 0;

		if (empty($this->pa_ht))
			$this->pa_ht = 0;

		// si prix d'achat non renseigne et utilise pour calcul des marges alors prix achat = prix vente
		if ($this->pa_ht == 0) {
			if ($this->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1))
				$this->pa_ht = $this->subprice * (1 - $this->remise_percent / 100);
		}

		// Check parameters
		if ($this->product_type < 0)
			return -1;
		$this->record();

		dol_syslog(get_class($this) . "::insert", LOG_DEBUG);

		if (!$notrigger) {
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('LINEORDER_INSERT', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}

		return 1;
	}

	/**
	 * 	Update the line object into db
	 *
	 * 	@param      int		$notrigger		1 = disable triggers
	 * 	@return		int		<0 si ko, >0 si ok
	 */
	function update($notrigger = 0) {
		global $conf, $langs, $user;

		$error = 0;

		// Clean parameters
		if (empty($this->tva_tx))
			$this->tva_tx = 0;
		if (empty($this->localtax1_tx))
			$this->localtax1_tx = 0;
		if (empty($this->localtax2_tx))
			$this->localtax2_tx = 0;
		if (empty($this->qty))
			$this->qty = 0;
		if (empty($this->total_localtax1))
			$this->total_localtax1 = 0;
		if (empty($this->total_localtax2))
			$this->total_localtax2 = 0;
		if (empty($this->marque_tx))
			$this->marque_tx = 0;
		if (empty($this->marge_tx))
			$this->marge_tx = 0;
		if (empty($this->remise))
			$this->remise = 0;
		if (empty($this->remise_percent))
			$this->remise_percent = 0;
		if (empty($this->info_bits))
			$this->info_bits = 0;
		if (empty($this->special_code))
			$this->special_code = 0;
		if (empty($this->product_type))
			$this->product_type = 0;
		if (empty($this->fk_parent_line))
			$this->fk_parent_line = 0;
		if (empty($this->pa_ht))
			$this->pa_ht = 0;

		// si prix d'achat non renseigné et utilisé pour calcul des marges alors prix achat = prix vente
		if ($this->pa_ht == 0) {
			if ($this->subprice > 0 && (isset($conf->global->ForceBuyingPriceIfNull) && $conf->global->ForceBuyingPriceIfNull == 1))
				$this->pa_ht = $this->subprice * (1 - $this->remise_percent / 100);
		}
		$this->record();

		//		$this->db->begin();
		//
        //		// Mise a jour ligne en base
		//		$sql = "UPDATE ".MAIN_DB_PREFIX."commandedet SET";
		//		$sql.= " description='".$this->db->escape($this->desc)."'";
		//		$sql.= " , label=".(! empty($this->label)?"'".$this->db->escape($this->label)."'":"null");
		//		$sql.= " , tva_tx=".price2num($this->tva_tx);
		//		$sql.= " , localtax1_tx=".price2num($this->localtax1_tx);
		//		$sql.= " , localtax2_tx=".price2num($this->localtax2_tx);
		//		$sql.= " , qty=".price2num($this->qty);
		//		$sql.= " , subprice=".price2num($this->subprice)."";
		//		$sql.= " , remise_percent=".price2num($this->remise_percent)."";
		//		$sql.= " , price=".price2num($this->price)."";					// TODO A virer
		//		$sql.= " , remise=".price2num($this->remise)."";				// TODO A virer
		//		if (empty($this->skip_update_total))
		//		{
		//			$sql.= " , total_ht=".price2num($this->total_ht)."";
		//			$sql.= " , total_tva=".price2num($this->total_tva)."";
		//			$sql.= " , total_ttc=".price2num($this->total_ttc)."";
		//			$sql.= " , total_localtax1=".price2num($this->total_localtax1);
		//			$sql.= " , total_localtax2=".price2num($this->total_localtax2);
		//		}
		//		$sql.= " , fk_product_fournisseur_price=".(! empty($this->fk_fournprice)?$this->fk_fournprice:"null");
		//		$sql.= " , buy_price_ht='".price2num($this->pa_ht)."'";
		//		$sql.= " , info_bits=".$this->info_bits;
		//        $sql.= " , special_code=".$this->special_code;
		//		$sql.= " , date_start=".(! empty($this->date_start)?"'".$this->db->idate($this->date_start)."'":"null");
		//		$sql.= " , date_end=".(! empty($this->date_end)?"'".$this->db->idate($this->date_end)."'":"null");
		//		$sql.= " , product_type=".$this->product_type;
		//		$sql.= " , fk_parent_line=".(! empty($this->fk_parent_line)?$this->fk_parent_line:"null");
		//		if (! empty($this->rang)) $sql.= ", rang=".$this->rang;
		//		$sql.= " WHERE rowid = ".$this->rowid;
		//
        	//		dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
		//		$resql=$this->db->query($sql);
		//		if ($resql)
		//		{
		if (!$notrigger) {
			// Appel des triggers
			include_once DOL_DOCUMENT_ROOT . '/core/class/interfaces.class.php';
			$interface = new Interfaces($this->db);
			$result = $interface->run_triggers('LINEORDER_UPDATE', $this, $user, $langs, $conf);
			if ($result < 0) {
				$error++;
				$this->errors = $interface->errors;
			}
			// Fin appel triggers
		}

		//			$this->db->commit();
		return 1;
		//		}
		//		else
		//		{
		//			$this->error=$this->db->error();
		//			dol_syslog(get_class($this)."::update Error ".$this->error, LOG_ERR);
		//			$this->db->rollback();
		//			return -2;
		//		}
	}

	/**
	 * 	Update totals of order into database
	 *
	 * 	@return		int		<0 if ko, >0 if ok
	 */
	function update_total() {
		$this->db->begin();

		// Clean parameters
		if (empty($this->total_localtax1))
			$this->total_localtax1 = 0;
		if (empty($this->total_localtax2))
			$this->total_localtax2 = 0;

		// Mise a jour ligne en base
		$sql = "UPDATE " . MAIN_DB_PREFIX . "commandedet SET";
		$sql.= " total_ht='" . price2num($this->total_ht) . "'";
		$sql.= ",total_tva='" . price2num($this->total_tva) . "'";
		$sql.= ",total_localtax1='" . price2num($this->total_localtax1) . "'";
		$sql.= ",total_localtax2='" . price2num($this->total_localtax2) . "'";
		$sql.= ",total_ttc='" . price2num($this->total_ttc) . "'";
		$sql.= " WHERE rowid = " . $this->rowid;

		dol_syslog("OrderLine::update_total sql=$sql");

		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = $this->db->error();
			dol_syslog("OrderLine::update_total Error " . $this->error, LOG_ERR);
			$this->db->rollback();
			return -2;
		}
	}

}
?>
