<?php

/* Copyright (C) 2013	Regis Houssin	<regis.houssin@capnetworks.com>
 * Copyright (C) 2013	Herve Prot		<herve.prot@symeos.com>
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

namespace datatables\schemas;

use datatables\Schema;

class DefaultSchema extends Schema {
    /* ______________________________________________________________________ */

    public function __construct() {
        global $langs, $object;

        // variable to be used inside closure object
        $schema = $this;

        foreach ($object->fk_extrafields->longList as $key => $aRow) {
			
			//print $aRow;

            $field = $object->fk_extrafields->fields->$aRow;

            if (empty($field->enable))
                continue;

            $classname = get_class($object);

            // Editable element
            $editable = false; // Editable by default
            if (!empty($field->list->editable)) {
                $editable = array(
                    "type" => $field->type,
                    "name" => $aRow,
                    "classname" => $classname,
                    "validate" => $field->validate,
                    "width" => (!empty($field->list->editable->width) ? $field->list->editable->width : '150px'),
                    "height" => (!empty($field->list->editable->height) ? $field->list->editable->height : '14px')
                );
            }

            // Render element
            $render = false; // Render by default
            if (!empty($field->render) || !empty($field->action)) {
                $classname = (!empty($field->class) ? $field->class : $classname);
                $rendertype = (!empty($field->render->type) ? $field->render->type : $field->type);
                if ($rendertype != 'Text') {
                    $render = $this->element('Render' . ucfirst($rendertype), array($field, $aRow, $classname));
                }
            }

            // Footer element
            $footer = ''; // Footer by default
            if ($field->list->searchable !== false) {
                if (!empty($field->values)) {
                    $footer = $this->element('FilterSelect', array(object2array($field->values)));
                } else {
                    $footer = $this->element('FilterInput', array($langs->trans('Search') . ' {:label}'));
                }
            }

            $this->push($aRow, array(
               // 'label' => (!empty($field->label) ? $langs->trans($field->label) : ($field->label === false ? (string) $this->element($rendertype, array('checkall', 1, 'checkall')) : '')), //no label by default
                'label' => (!empty($field->label) ? $langs->trans($field->label) : ''),
                'default' => (!empty($field->default) ? $field->default : ''),
                'class' => (!empty($field->list->cssclass) ? $field->list->cssclass : ''),
                'width' => (!empty($field->list->width) ? $field->list->width : false),
                'type' => (!empty($field->list->static) ? 'static' : 'dynamic'), // static (no movable and never hidden)
                'searchable' => (is_bool($field->list->searchable) === true ? $field->list->searchable : true), // True by default
                'sortable' => (is_bool($field->list->sortable) === true ? $field->list->sortable : true), // True by default
                'visible' => (is_bool($field->list->visible) === true ? $field->list->visible : true), // True by default
                'editable' => $editable,
                'render' => $render,
                'footer' => $footer
            ));
        }
    }

}

?>