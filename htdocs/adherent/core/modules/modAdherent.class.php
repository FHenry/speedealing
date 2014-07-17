<?php
/* Copyright (C) 2003,2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Jean-Louis Bergamo   <jlb@j1b.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
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
 *      \defgroup   member     Module foundation
 *      \brief      Module to manage members of a foundation
 *		\file       htdocs/core/modules/modAdherent.class.php
 *      \ingroup    member
 *      \brief      File descriptor or module Member
 */

/**
 *  Classe de description et activation du module Adherent
 */
class modAdherent extends DolibarrModules {

    /**
	 *   Constructor.
     */
	function modAdherent() {
		parent::__construct();

		$this->numero = 310;

		$this->family = "hr";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion des adhérents d'une association";
		$this->version = 'speedealing';						// 'experimental' or 'dolibarr' or version
		$this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
		$this->special = 0;
		$this->picto = 'user';

        // Data directories to create when module is enabled
		$this->dirs = array("/adherent/temp");

        // Config pages
        //-------------
		$this->config_page_url = array("adherent.php@adherent");

        // Dependances
        //------------
		$this->depends = array();
		$this->requiredby = array('modMailmanSpip');
		$this->langfiles = array("members", "companies");

        // Constantes
        //-----------
		$this->const = array();
		$this->const[2] = array("MAIN_SEARCHFORM_ADHERENT", "yesno", "1", "Show form for quick member search");
		$this->const[3] = array("ADHERENT_MAIL_RESIL", "texte", "Votre adhésion vient d'être résiliée.\r\nNous espérons vous revoir très bientôt", "Mail de résiliation");
		$this->const[4] = array("ADHERENT_MAIL_VALID", "texte", "Votre adhésion vient d'être validée. \r\nVoici le rappel de vos coordonnées (toute information erronée entrainera la non validation de votre inscription) :\r\n\r\n%INFOS%\r\n\r\n", "Mail de validation");
		$this->const[5] = array("ADHERENT_MAIL_VALID_SUBJECT", "chaine", "Votre adhésion a été validée", "Sujet du mail de validation");
		$this->const[6] = array("ADHERENT_MAIL_RESIL_SUBJECT", "chaine", "Résiliation de votre adhésion", "Sujet du mail de résiliation");
		$this->const[21] = array("ADHERENT_MAIL_FROM", "chaine", "", "From des mails");
		$this->const[22] = array("ADHERENT_MAIL_COTIS", "texte", "Bonjour %PRENOM%,\r\nCet email confirme que votre cotisation a été reçue\r\net enregistrée", "Mail de validation de cotisation");
		$this->const[23] = array("ADHERENT_MAIL_COTIS_SUBJECT", "chaine", "Reçu de votre cotisation", "Sujet du mail de validation de cotisation");
		$this->const[25] = array("ADHERENT_CARD_HEADER_TEXT", "chaine", "%ANNEE%", "Texte imprimé sur le haut de la carte adhérent");
		$this->const[26] = array("ADHERENT_CARD_FOOTER_TEXT", "chaine", "Association AZERTY", "Texte imprimé sur le bas de la carte adhérent");
		$this->const[27] = array("ADHERENT_CARD_TEXT", "texte", "%FULLNAME%\r\nID: %ID%\r\n%EMAIL%\r\n%ADDRESS%\r\n%ZIP% %TOWN%\r\n%COUNTRY%", "Text to print on member cards");
		$this->const[28] = array("ADHERENT_MAILMAN_ADMINPW", "chaine", "", "Mot de passe Admin des liste mailman");
		$this->const[31] = array("ADHERENT_BANK_USE_AUTO", "yesno", "", "Insertion automatique des cotisations dans le compte banquaire");
		$this->const[32] = array("ADHERENT_BANK_ACCOUNT", "chaine", "", "ID du Compte banquaire utilise");
		$this->const[33] = array("ADHERENT_BANK_CATEGORIE", "chaine", "", "ID de la catégorie banquaire des cotisations");
		$this->const[34] = array("ADHERENT_ETIQUETTE_TYPE", "chaine", "L7163", "Type of address sheets");
		$this->const[35] = array("ADHERENT_ETIQUETTE_TEXT", 'texte', "%FULLNAME%\n%ADDRESS%\n%ZIP% %TOWN%\n%COUNTRY%", "Text to print on member address sheets");

        // Boxes
        //-------
		$this->boxes = array();
		$r = 0;
		$this->boxes[$r][1] = "box_members.php";

        // Permissions
        //------------
		$this->rights = array();
		$this->rights_class = 'adherent';
		$r = 0;
		
		//$this->rights[$r]->id = 121; // id de la permission
		//$this->rights[$r]->desc = 'Lire les societes'; // libelle de la permission
		//$this->rights[$r]->default = true;
		//$this->rights[$r]->perm = array('lire');
		
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 71;
		$this->rights[$r]->desc = 'Read members\' card';
		$this->rights[$r]->default = 1;
		$this->rights[$r]->perm = array('lire');

        $r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 72;
		$this->rights[$r]->desc = 'Create/modify members (need also user module permissions if member linked to a user)';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('creer');

        $r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 74;
		$this->rights[$r]->desc = 'Remove members';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('supprimer');

        $r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 76;
		$this->rights[$r]->desc = 'Export members';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('export');

        $r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 75;
		$this->rights[$r]->desc = 'Setup types and attributes of members';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('configurer');

        $r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 78;
		$this->rights[$r]->desc = 'Read subscriptions';
		$this->rights[$r]->default = 1;
		$this->rights[$r]->perm = array('cotisation','lire');

        $r++;
		$this->rights[$r] = new stdClass();
		$this->rights[$r]->id = 79;
		$this->rights[$r]->desc = 'Create/modify/remove subscriptions';
		$this->rights[$r]->default = 0;
		$this->rights[$r]->perm = array('cotisation','creer');
		
		// Menu
		//--------
		
		$r = 0;
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:members";
		$this->menus[$r]->type = "top";
		$this->menus[$r]->position = 15;
		$this->menus[$r]->url = "/adherent/index.php";
		$this->menus[$r]->langs = "members";
		$this->menus[$r]->perms = '$user->rights->adherent->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "Members";

		$r++;
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:members0";
		$this->menus[$r]->position = 0;
		$this->menus[$r]->url = "/adherent/list.php";
		$this->menus[$r]->langs = "members";
		$this->menus[$r]->perms = '$user->rights->adherent->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "Members";
		$this->menus[$r]->fk_menu = "menu:members";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:subscriptions";
		$this->menus[$r]->position = 1;
		$this->menus[$r]->url = "/adherent/index.php";
		$this->menus[$r]->langs = "compta";
		$this->menus[$r]->perms = '$user->rights->adherent->cotisation->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "Subscriptions";
		$this->menus[$r]->fk_menu = "menu:members";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:memberstatic";
		$this->menus[$r]->position = 2;
		$this->menus[$r]->url = "/adherent/index.php";
		$this->menus[$r]->langs = "compta";
		$this->menus[$r]->perms = '$user->rights->adherent->cotisation->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "Statistics";
		$this->menus[$r]->fk_menu = "menu:subscriptions";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:subscriptionslist";
		$this->menus[$r]->position = 1;
		$this->menus[$r]->url = "/adherent/cotisations.php";
		$this->menus[$r]->langs = "compta";
		$this->menus[$r]->perms = '$user->rights->adherent->cotisation->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "List";
		$this->menus[$r]->fk_menu = "menu:subscriptions";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:memberscategoriesshort";
		$this->menus[$r]->position = 3;
		$this->menus[$r]->url = "/categories/index.php?type=3";
		$this->menus[$r]->langs = "categories";
		$this->menus[$r]->perms = '$user->rights->categorie->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled && $conf->categorie->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "MembersCategoriesShort";
		$this->menus[$r]->fk_menu = "menu:members";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:memberstypes";
		$this->menus[$r]->position = 5;
		$this->menus[$r]->url = "/adherent/type.php";
		$this->menus[$r]->langs = "members";
		$this->menus[$r]->perms = '$user->rights->adherent->configurer';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "MembersTypes";
		$this->menus[$r]->fk_menu = "menu:members";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:newmember";
		$this->menus[$r]->position = 0;
		$this->menus[$r]->url = "/adherent/fiche.php?action=create";
		$this->menus[$r]->langs = "members";
		$this->menus[$r]->perms = '$user->rights->adherent->creer';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "NewMember";
		$this->menus[$r]->fk_menu = "menu:members0";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:list18";
		$this->menus[$r]->position = 1;
		$this->menus[$r]->url = "/adherent/list.php";
		$this->menus[$r]->langs = "members";
		$this->menus[$r]->perms = '$user->rights->adherent->lire';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "List";
		$this->menus[$r]->fk_menu = "menu:members0";
		$r++;
		
		$this->menus[$r] = new stdClass();
		$this->menus[$r]->_id = "menu:cardmemberedit";
		$this->menus[$r]->position = 10;
		$this->menus[$r]->url = "/adherent/card.php";
		$this->menus[$r]->langs = "members";
		$this->menus[$r]->perms = '$user->rights->adherent->configurer';
		$this->menus[$r]->enabled = '$conf->adherent->enabled';
		$this->menus[$r]->usertype = 2;
		$this->menus[$r]->title = "CardMember";
		$this->menus[$r]->fk_menu = "menu:members";
		
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
