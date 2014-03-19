<?php

/* Copyright (C) 2003-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2004      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2013 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2012      Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2011-2013 Herve Prot           <herve.prot@symeos.com>
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

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * 	Class to describe module customer orders
 */
class modCommande extends DolibarrModules {

	/**
	 *   Constructor.
	 */
	function __construct() {
		global $conf;

		parent::__construct();
		$this->numero = 25;

		$this->family = "crm";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion des commandes clients";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = 'speedealing';

		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->special = 0;
		$this->picto = 'order';

		// Data directories to create when module is enabled
		$this->dirs = array("/commande/temp");

		// Config pages
		$this->config_page_url = array("commande.php@commande");

		// Dependancies
		$this->depends = array("modSociete");
		$this->requiredby = array("modExpedition");
		$this->conflictwith = array();
		$this->langfiles = array('orders', 'bills', 'companies', 'products', 'deliveries');

		// Constantes
		$this->const = array();
		$r = 0;

		$this->const[$r][0] = "COMMANDE_ADDON_PDF";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "einstein";
		$this->const[$r][3] = 'Nom du gestionnaire de generation des commandes en PDF';
		$this->const[$r][4] = 0;

		$r++;
		$this->const[$r][0] = "COMMANDE_ADDON";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "mod_commande_marbre";
		$this->const[$r][3] = 'Nom du gestionnaire de numerotation des commandes';
		$this->const[$r][4] = 0;

		$r++;
		$this->const[$r][0] = "COMMANDE_ADDON_PDF_ODT_PATH";
		$this->const[$r][1] = "chaine";
		$this->const[$r][2] = "DOL_DATA_ROOT/doctemplates/orders";
		$this->const[$r][3] = "";
		$this->const[$r][4] = 0;

		// Boites
		$this->boxes = array();
		$this->boxes[0][1] = "box_commandes.php";

		// Permissions
		$this->rights = array();
		$this->rights_class = 'commande';

		$r = 0;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 81;
		$this->rights[$r]->desc = 'Lire les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('lire');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 82;
		$this->rights[$r]->desc = 'Creer/modifier les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('creer');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 84;
		$this->rights[$r]->desc = 'Valider les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('valider');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 86;
		$this->rights[$r]->desc = 'Envoyer les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('order_advance', 'send');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 87;
		$this->rights[$r]->desc = 'Cloturer les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('cloturer');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 88;
		$this->rights[$r]->desc = 'Annuler les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('annuler');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 89;
		$this->rights[$r]->desc = 'Supprimer les commandes clients';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('supprimer');

		$r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 1421;
		$this->rights[$r]->desc = 'Exporter les commandes clients et attributs';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('commande', 'export');

		// Main menu entries
		$this->menu = array();   // List of menus to add
		$r = 0;

		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:commandes";
		$this->menus[$r]->type = "top";
		$this->menus[$r]->position = 41;
		$this->menus[$r]->langs = "orders";
		$this->menus[$r]->perms = '$user->rights->commande->lire || $user->rights->intervention->lire';
		$this->menus[$r]->enabled = '$conf->commande->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "Orders";
		$r++;

		/*$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:newcommande";
		$this->menus[$r]->position = 0;
		$this->menus[$r]->url = "/commande/fiche.php?action=create";
		$this->menus[$r]->langs = "orders";
		$this->menus[$r]->perms = '$user->rights->commande->creer';
		$this->menus[$r]->enabled = '$conf->commande->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "NewOrder";
		$this->menus[$r]->fk_menu = "menu:commandes";
		$r++;*/

		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:orderslist";
		$this->menus[$r]->position = 1;
		$this->menus[$r]->url = "#!/orders";
		$this->menus[$r]->langs = "orders";
		$this->menus[$r]->perms = '$user->rights->commande->lire';
		$this->menus[$r]->enabled = '$conf->commande->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "List";
		$this->menus[$r]->fk_menu = "menu:commandes";
		$r++;

		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:ordersstats";
		$this->menus[$r]->position = 2;
		$this->menus[$r]->url = "/commande/stats/index.php";
		$this->menus[$r]->langs = "orders";
		$this->menus[$r]->perms = '$user->rights->commande->lire';
		$this->menus[$r]->enabled = '$conf->commande->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "OrdersStatistics";
		$this->menus[$r]->fk_menu = "menu:commandes";
		$r++;

		// Exports
		//--------
//		$r=0;
//
//		$r++;
//		$this->export_code[$r]=$this->rights_class.'_'.$r;
//		$this->export_label[$r]='CustomersOrdersAndOrdersLines';	// Translation key (used only if key ExportDataset_xxx_z not found)
//		$this->export_permission[$r]=array(array("commande","commande","export"));
//		$this->export_fields_array[$r]=array('s.rowid'=>"IdCompany",'s.nom'=>'CompanyName','s.address'=>'Address','s.cp'=>'Zip','s.ville'=>'Town','s.fk_pays'=>'Country','s.tel'=>'Phone','s.siren'=>'ProfId1','s.siret'=>'ProfId2','s.ape'=>'ProfId3','s.idprof4'=>'ProfId4','c.rowid'=>"Id",'c.ref'=>"Ref",'c.ref_client'=>"RefCustomer",'c.fk_soc'=>"IdCompany",'c.date_creation'=>"DateCreation",'c.date_commande'=>"OrderDate",'c.amount_ht'=>"Amount",'c.remise_percent'=>"GlobalDiscount",'c.total_ht'=>"TotalHT",'c.total_ttc'=>"TotalTTC",'c.facture'=>"Billed",'c.fk_statut'=>'Status','c.note'=>"Note",'c.date_livraison'=>'DeliveryDate','cd.rowid'=>'LineId','cd.label'=>"Label",'cd.description'=>"LineDescription",'cd.product_type'=>'TypeOfLineServiceOrProduct','cd.tva_tx'=>"LineVATRate",'cd.qty'=>"LineQty",'cd.total_ht'=>"LineTotalHT",'cd.total_tva'=>"LineTotalVAT",'cd.total_ttc'=>"LineTotalTTC",'p.rowid'=>'ProductId','p.ref'=>'ProductRef','p.label'=>'ProductLabel');
//		$this->export_entities_array[$r]=array('s.rowid'=>"company",'s.nom'=>'company','s.address'=>'company','s.cp'=>'company','s.ville'=>'company','s.fk_pays'=>'company','s.tel'=>'company','s.siren'=>'company','s.ape'=>'company','s.idprof4'=>'company','s.siret'=>'company','c.rowid'=>"order",'c.ref'=>"order",'c.ref_client'=>"order",'c.fk_soc'=>"order",'c.date_creation'=>"order",'c.date_commande'=>"order",'c.amount_ht'=>"order",'c.remise_percent'=>"order",'c.total_ht'=>"order",'c.total_ttc'=>"order",'c.facture'=>"order",'c.fk_statut'=>"order",'c.note'=>"order",'c.date_livraison'=>"order",'cd.rowid'=>'order_line','cd.label'=>"order_line",'cd.description'=>"order_line",'cd.product_type'=>'order_line','cd.tva_tx'=>"order_line",'cd.qty'=>"order_line",'cd.total_ht'=>"order_line",'cd.total_tva'=>"order_line",'cd.total_ttc'=>"order_line",'p.rowid'=>'product','p.ref'=>'product','p.label'=>'product');
//		$this->export_dependencies_array[$r]=array('order_line'=>'cd.rowid','product'=>'cd.rowid'); // To add unique key if we ask a field of a child to avoid the DISTINCT to discard them
//
//		$this->export_sql_start[$r]='SELECT DISTINCT ';
//		$this->export_sql_end[$r]  =' FROM ('.MAIN_DB_PREFIX.'commande as c, '.MAIN_DB_PREFIX.'societe as s, '.MAIN_DB_PREFIX.'commandedet as cd)';
//		$this->export_sql_end[$r] .=' LEFT JOIN '.MAIN_DB_PREFIX.'product as p on (cd.fk_product = p.rowid)';
//		$this->export_sql_end[$r] .=' WHERE c.fk_soc = s.rowid AND c.rowid = cd.fk_commande';
//		$this->export_sql_end[$r] .=' AND c.entity = '.$conf->entity;
	}

	/**
	 * 		Function called when module is enabled.
	 * 		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 * 		It also creates data directories
	 *
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init() {
		return $this->_init();
	}

	/**
	 * 		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 * 		Data directories are not deleted
	 *
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove() {
		return $this->_remove();
	}

}

?>
