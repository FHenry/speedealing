<?php

/* Copyright (C) 2012-2013	Regis Houssin	<regis.houssin@capnetworks.com>
 * Copyright (C) 2012-2013	Herve Prot		<herve.prot@symeos.com>
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
 * 	 \file       htdocs/user/class/usergroup.class.php
 * 	 \brief      File of class to manage user groups
 */
require_once DOL_DOCUMENT_ROOT . '/core/class/nosqlDocument.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
if (!empty($conf->ldap->enabled))
	require_once DOL_DOCUMENT_ROOT . '/core/class/ldap.class.php';

/**
 * 	\class      UserGroup
 * 	\brief      Class to manage user groups
 */
class UserDatabase extends nosqlDocument {

	public $element = 'usergroup';
	public $table_element = 'usergroup';
	protected $ismultientitymanaged = 1; // 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	var $couchAdmin;
	var $couchdb;
	var $id;   // Group id
	var $nom;   // Name of group
	var $globalgroup; // Global group
	var $note;   // Note on group
	var $datec;   // Creation date of group
	var $datem;   // Modification date of group
	var $members = array(); // Array of users
	var $membersRoles = array(); // Array of groups
	private $_tab_loaded = array();  // Array of cache of already loaded permissions

	/**
	 *    Constructor de la classe
	 *
	 *    @param   DoliDb  $db     Database handler
	 */

	function __construct($db = '') {
		$this->db = $db;

		parent::__construct($db);

		$fk_extrafields = new ExtraFields($db);
		$this->fk_extrafields = $fk_extrafields->load("extrafields:" . get_class($this), true); // load and cache

		$this->couchAdmin = new couchAdmin($this->couchdb);

		return 0;
	}

	/**
	 * 	Charge un objet group avec toutes ces caracteristiques (excpet ->members array)
	 *
	 * 	@param      int		$id     name of the data to fetch
	 * 	@return		int				<0 if KO, >0 if OK
	 */
	function fetch($id) {
		global $conf, $langs;

		$this->couchdb->useDatabase($id);
		$this->values = $this->couchdb->getDatabaseInfos();

		$this->couchAdmin = new couchAdmin($this->couchdb);

		$members = $this->couchAdmin->getDatabaseReaderUsers();

		$membersRoles = $this->couchAdmin->getDatabaseReaderRoles();

		if (!empty($members)) {
			foreach ($members as $aRow) {
				try {
					$user = new User($this->db);
					$user->load("user:" . $aRow);
				} catch (Exception $e) {
					// User NOT FOUND
					$user->email = $aRow;
					$user->name = $aRow;
					$user->_id = "org.couchdb.user:" . $aRow;
					$user->Firstname = "Unknown";
					$user->Lastname = "Unknown";
					$user->Status = "DISABLE";
				}
				$this->members[] = clone $user;
			}
		}

		$group = new stdClass();

		if (!empty($membersRoles)) {
			foreach ($membersRoles as $aRow) {
				$group->id = $aRow;
				$this->membersRoles[] = clone $group;
			}
		}

		$membersAdmin = $this->couchAdmin->getDatabaseAdminUsers();
		//$membersRolesAdmin = $this->couchAdmin->getDatabaseAdminRoles();
		//$this->membersRoles = array_merge($this->membersRoles, $membersRolesAdmin);

		if (!empty($membersAdmin)) {
			foreach ($membersAdmin as $aRow) {
				try {
					$user = $this->couchAdmin->getUser($aRow);
				} catch (Exception $e) {
					// User NOT FOUND
					$user->email = $aRow;
					$user->name = $aRow;
					$user->_id = "org.couchdb.user:" . $aRow;
					$user->Firstname = "Unknown";
					$user->Lastname = "Unknown";
					$user->Status = "DISABLE";
					$user->admin = true;
				}
				$user->admin = true;
				$this->members[] = clone $user;
			}
		}

		/* foreach ($membersRolesAdmin as $aRow) {
		  $group->Administrator = true;
		  $group->id = $aRow;
		  $this->membersRoles[] = clone $group;
		  } */

		$this->id = $this->values->db_name;
		return 1;
	}

	/**
	 * 	Return array of groups objects for a particular user
	 *
	 * 	@param		int		$userid 	User id to search
	 * 	@return		array     			Array of groups objects
	 */
	function listGroupsForUser($userid) {
		global $conf, $user;

		$ret = array();

		$sql = "SELECT g.rowid, ug.entity as usergroup_entity";
		$sql.= " FROM " . MAIN_DB_PREFIX . "usergroup as g,";
		$sql.= " " . MAIN_DB_PREFIX . "usergroup_user as ug";
		$sql.= " WHERE ug.fk_usergroup = g.rowid";
		$sql.= " AND ug.fk_user = " . $userid;
		if (!empty($conf->multicompany->enabled) && $conf->entity == 1 && $user->admin && !$user->entity) {
			$sql.= " AND g.entity IS NOT NULL";
		} else {
			$sql.= " AND g.entity IN (0," . $conf->entity . ")";
		}
		$sql.= " ORDER BY g.nom";

		dol_syslog(get_class($this) . "::listGroupsForUser sql=" . $sql, LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			while ($obj = $this->db->fetch_object($result)) {
				$newgroup = new UserGroup($this->db);
				$newgroup->fetch($obj->rowid);
				$newgroup->usergroup_entity = $obj->usergroup_entity;

				$ret[] = $newgroup;
			}

			$this->db->free($result);

			return $ret;
		} else {
			$this->error = $this->db->lasterror();
			dol_syslog(get_class($this) . "::listGroupsForUser " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 * 	Return array of users id for group
	 *
	 * 	@return		array of users
	 */
	function listUsersForGroup() {
		global $conf, $user;

		$ret = array();

		$sql = "SELECT u.rowid, ug.entity as usergroup_entity";
		$sql.= " FROM " . MAIN_DB_PREFIX . "user as u,";
		$sql.= " " . MAIN_DB_PREFIX . "usergroup_user as ug";
		$sql.= " WHERE ug.fk_user = u.rowid";
		$sql.= " AND ug.fk_usergroup = " . $this->id;
		if (!empty($conf->multicompany->enabled) && $conf->entity == 1 && $user->admin && !$user->entity) {
			$sql.= " AND u.entity IS NOT NULL";
		} else {
			$sql.= " AND u.entity IN (0," . $conf->entity . ")";
		}
		dol_syslog(get_class($this) . "::listUsersForGroup sql=" . $sql, LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			while ($obj = $this->db->fetch_object($result)) {
				$newuser = new User($this->db);
				$newuser->fetch($obj->rowid);
				$newuser->usergroup_entity = $obj->usergroup_entity;

				$ret[] = $newuser;
			}

			$this->db->free($result);

			return $ret;
		} else {
			$this->error = $this->db->lasterror();
			dol_syslog(get_class($this) . "::listUsersForGroup " . $this->error, LOG_ERR);
			return -1;
		}
	}

	/**
	 *    Ajoute un droit a l'utilisateur
	 *
	 *    @param      int		$rid         id du droit a ajouter
	 *    @param      string	$allmodule   Ajouter tous les droits du module allmodule
	 *    @param      string	$allperms    Ajouter tous les droits du module allmodule, perms allperms
	 *    @return     int         			 > 0 if OK, < 0 if KO
	 */
	function addrights($rid, $allmodule = '', $allperms = '') {
		global $conf;

		dol_syslog(get_class($this) . "::addrights $rid, $allmodule, $allperms");
		$err = 0;
		$whereforadd = '';

		$this->db->begin();

		if ($rid) {
			// Si on a demande ajout d'un droit en particulier, on recupere
			// les caracteristiques (module, perms et subperms) de ce droit.
			$sql = "SELECT module, perms, subperms";
			$sql.= " FROM " . MAIN_DB_PREFIX . "rights_def";
			$sql.= " WHERE id = '" . $rid . "'";
			$sql.= " AND entity = " . $conf->entity;

			$result = $this->db->query($sql);
			if ($result) {
				$obj = $this->db->fetch_object($result);
				$module = $obj->module;
				$perms = $obj->perms;
				$subperms = $obj->subperms;
			} else {
				$err++;
				dol_print_error($this->db);
			}

			// Where pour la liste des droits a ajouter
			$whereforadd = "id=" . $rid;
			// Ajout des droits induits
			if ($subperms)
				$whereforadd.=" OR (module='$module' AND perms='$perms' AND (subperms='lire' OR subperms='read'))";
			else if ($perms)
				$whereforadd.=" OR (module='$module' AND (perms='lire' OR perms='read') AND subperms IS NULL)";

			// Pour compatibilite, si lowid = 0, on est en mode ajout de tout
			// TODO A virer quand sera gere par l'appelant
			if (substr($rid, -1, 1) == 0)
				$whereforadd = "module='$module'";
		}
		else {
			// Where pour la liste des droits a ajouter
			if ($allmodule)
				$whereforadd = "module='$allmodule'";
			if ($allperms)
				$whereforadd = " AND perms='$allperms'";
		}

		// Ajout des droits de la liste whereforadd
		if ($whereforadd) {
			//print "$module-$perms-$subperms";
			$sql = "SELECT id";
			$sql.= " FROM " . MAIN_DB_PREFIX . "rights_def";
			$sql.= " WHERE $whereforadd";
			$sql.= " AND entity = " . $conf->entity;

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num) {
					$obj = $this->db->fetch_object($result);
					$nid = $obj->id;

					$sql = "DELETE FROM " . MAIN_DB_PREFIX . "usergroup_rights WHERE fk_usergroup = $this->id AND fk_id=" . $nid;
					if (!$this->db->query($sql))
						$err++;
					$sql = "INSERT INTO " . MAIN_DB_PREFIX . "usergroup_rights (fk_usergroup, fk_id) VALUES ($this->id, $nid)";
					if (!$this->db->query($sql))
						$err++;

					$i++;
				}
			}
			else {
				$err++;
				dol_print_error($this->db);
			}
		}

		if ($err) {
			$this->db->rollback();
			return -$err;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *    Retire un droit a l'utilisateur
	 *
	 *    @param      int		$rid         id du droit a retirer
	 *    @param      string	$allmodule   Retirer tous les droits du module allmodule
	 *    @param      string	$allperms    Retirer tous les droits du module allmodule, perms allperms
	 *    @return     int         			 > 0 if OK, < 0 if OK
	 */
	function delrights($rid, $allmodule = '', $allperms = '') {
		global $conf;

		$err = 0;
		$wherefordel = '';

		$this->db->begin();

		if ($rid) {
			// Si on a demande supression d'un droit en particulier, on recupere
			// les caracteristiques module, perms et subperms de ce droit.
			$sql = "SELECT module, perms, subperms";
			$sql.= " FROM " . MAIN_DB_PREFIX . "rights_def";
			$sql.= " WHERE id = '" . $rid . "'";
			$sql.= " AND entity = " . $conf->entity;

			$result = $this->db->query($sql);
			if ($result) {
				$obj = $this->db->fetch_object($result);
				$module = $obj->module;
				$perms = $obj->perms;
				$subperms = $obj->subperms;
			} else {
				$err++;
				dol_print_error($this->db);
			}

			// Where pour la liste des droits a supprimer
			$wherefordel = "id=" . $rid;
			// Suppression des droits induits
			if ($subperms == 'lire' || $subperms == 'read')
				$wherefordel.=" OR (module='$module' AND perms='$perms' AND subperms IS NOT NULL)";
			if ($perms == 'lire' || $perms == 'read')
				$wherefordel.=" OR (module='$module')";

			// Pour compatibilite, si lowid = 0, on est en mode suppression de tout
			// TODO A virer quand sera gere par l'appelant
			if (substr($rid, -1, 1) == 0)
				$wherefordel = "module='$module'";
		}
		else {
			// Where pour la liste des droits a supprimer
			if ($allmodule)
				$wherefordel = "module='$allmodule'";
			if ($allperms)
				$wherefordel = " AND perms='$allperms'";
		}

		// Suppression des droits de la liste wherefordel
		if ($wherefordel) {
			//print "$module-$perms-$subperms";
			$sql = "SELECT id";
			$sql.= " FROM " . MAIN_DB_PREFIX . "rights_def";
			$sql.= " WHERE $wherefordel";
			$sql.= " AND entity = " . $conf->entity;

			$result = $this->db->query($sql);
			if ($result) {
				$num = $this->db->num_rows($result);
				$i = 0;
				while ($i < $num) {
					$obj = $this->db->fetch_object($result);
					$nid = $obj->id;

					$sql = "DELETE FROM " . MAIN_DB_PREFIX . "usergroup_rights";
					$sql.= " WHERE fk_usergroup = $this->id AND fk_id=" . $nid;
					if (!$this->db->query($sql))
						$err++;

					$i++;
				}
			}
			else {
				$err++;
				dol_print_error($this->db);
			}
		}

		if ($err) {
			$this->db->rollback();
			return -$err;
		} else {
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *  Charge dans l'objet group, la liste des permissions auquels le groupe a droit
	 *
	 *  @param      string	$moduletag	 	Name of module we want permissions ('' means all)
	 * 	@return		int						<0 if KO, >0 if OK
	 */
	function getrights($moduletag = '') {
		global $conf;

		if ($moduletag && isset($this->_tab_loaded[$moduletag]) && $this->_tab_loaded[$moduletag]) {
			// Le fichier de ce module est deja charge
			return;
		}

		if ($this->all_permissions_are_loaded) {
			// Si les permissions ont deja ete chargees, on quitte
			return;
		}

		/*
		 * Recuperation des droits
		 */
		$sql = "SELECT r.module, r.perms, r.subperms ";
		$sql.= " FROM " . MAIN_DB_PREFIX . "usergroup_rights as u, " . MAIN_DB_PREFIX . "rights_def as r";
		$sql.= " WHERE r.id = u.fk_id";
		$sql.= " AND r.entity = " . $conf->entity;
		$sql.= " AND u.fk_usergroup = " . $this->id;
		$sql.= " AND r.perms IS NOT NULL";
		if ($moduletag)
			$sql.= " AND r.module = '" . $this->db->escape($moduletag) . "'";

		dol_syslog(get_class($this) . '::getrights sql=' . $sql, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$obj = $this->db->fetch_object($resql);

				$module = $obj->module;
				$perms = $obj->perms;
				$subperms = $obj->subperms;

				if ($perms) {
					if ($subperms) {
						$this->rights->$module->$perms->$subperms = 1;
					} else {
						$this->rights->$module->$perms = 1;
					}
				}

				$i++;
			}
			$this->db->free($resql);
		}

		if ($moduletag == '') {
			// Si module etait non defini, alors on a tout charge, on peut donc considerer
			// que les droits sont en cache (car tous charges) pour cet instance de group
			$this->all_permissions_are_loaded = 1;
		} else {
			// Si module defini, on le marque comme charge en cache
			$this->_tab_loaded[$moduletag] = 1;
		}

		return 1;
	}

	/**
	 *        Delete a database
	 *
	 *        @return     <0 if KO, > 0 if OK
	 */
	function delete() {
		try {
			$this->couchdb->useDatabase($this->id);
			$this->couchdb->deleteDatabase();
			return 1;
		} catch (Exception $e) {
			dol_print_error('', $e->getMessage());
			return -1;
		}
	}

	/**
	 *        Compact a database
	 *
	 *        @return     <0 if KO, > 0 if OK
	 */
	function compact() {
		try {
			$this->couchdb->useDatabase($this->id);
			$this->couchdb->compactDatabase();
			return 1;
		} catch (Exception $e) {
			dol_print_error('', $e->getMessage());
			return -1;
		}
	}

	/**
	 *        Compact views
	 *
	 *        @return     <0 if KO, > 0 if OK
	 */
	function compactView() {
		try {
			$this->couchdb->useDatabase($this->id);
			$this->couchdb->compactAllViews();
			return 1;
		} catch (Exception $e) {
			dol_print_error('', $e->getMessage());
			return -1;
		}
	}

	/**
	 *        Purge deleted documents
	 *
	 *        @return     <0 if KO, > 0 if OK
	 */
	function purgeDatabase() {
		try {
			$this->couchdb->useDatabase($this->id);
			$this->couchdb->purgeDatabase();
			return 1;
		} catch (Exception $e) {
			dol_print_error('', $e->getMessage());
			return -1;
		}
	}

	/**
	 *        Write data in memory to disk
	 *
	 *        @return     <0 if KO, > 0 if OK
	 */
	function commit() {
		try {
			$this->couchdb->useDatabase($this->id);
			$this->couchdb->ensureFullCommit();
			return 1;
		} catch (Exception $e) {
			dol_print_error('', $e->getMessage());
			return -1;
		}
	}

	/**
	 * 	Create a database
	 *
	 * 	@param		int		$notrigger	0=triggers enabled, 1=triggers disabled
	 * 	@return     int					<0 if KO, >=0 if OK
	 */
	function create() {
		$this->couchdb->useDatabase($this->id);
		$this->couchdb->createDatabase();
		return 1;
	}

}

?>
