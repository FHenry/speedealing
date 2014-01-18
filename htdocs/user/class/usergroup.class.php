<?php

/* Copyright (c) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (c) 2005-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (c) 2005-2011 Regis Houssin        <regis.houssin@capnetworks.com>
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
require_once(DOL_DOCUMENT_ROOT . "/core/class/nosqlDocument.class.php");
require_once(DOL_DOCUMENT_ROOT . "/core/class/extrafields.class.php");
require_once(DOL_DOCUMENT_ROOT . "/user/class/userdatabase.class.php");

if ($conf->ldap->enabled)
    require_once (DOL_DOCUMENT_ROOT . "/core/class/ldap.class.php");

/**
 * 	\class      UserGroup
 * 	\brief      Class to manage user groups
 */
class UserGroup extends nosqlDocument {

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
    var $databases = array(); // Array of databases
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

        return 0;
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
     * 	Charge un objet group avec toutes ces caracteristiques (excpet ->members array)
     *
     * 	@param      int		$id     id du groupe a charger
     * 	@return		int				<0 if KO, >0 if OK
     */
    function load($id, $loaddb = false) {
        global $conf;

        parent::load($id);

        if ($loaddb) {
            $database = new UserDatabase($this->db);
            try {
                $result = $database->couchdb->listDatabases();
            } catch (Exception $exc) {
                print $exc->getMessage();
            }

            foreach ($result as $aRow) {
                if ($aRow[0] != "_") { // Not _users and _replicator
                    try {
                        $database->fetch($aRow);
                        $info = $database->values;
                        $secu = $database->couchAdmin->getSecurity();

                        foreach ($secu as $key => $type) {
                            if (in_array($this->values->name, $type->roles)) {
                                if ($key == "admins")
                                    $info->Administrator = true;

                                $this->databases[] = $info;
                            }
                        }
                    } catch (Exception $exc) {
                        print $exc->getMessage();
                    }
                }
            }
        }

        return 1;
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
     *  Initialise an instance with random values.
     *  Used to build previews or test instances.
     * 	id must be 0 if object instance is a specimen.
     *
     *  @return	void
     */
    function initAsSpecimen() {
        global $conf, $user, $langs;

        // Initialise parametres
        $this->id = 0;
        $this->ref = 'SPECIMEN';
        $this->specimen = 1;

        $this->nom = 'DOLIBARR GROUP SPECIMEN';
        $this->note = 'This is a note';
        $this->datec = time();
        $this->datem = time();
        $this->members = array($user->id); // Members of this group is just me
    }

}

?>
