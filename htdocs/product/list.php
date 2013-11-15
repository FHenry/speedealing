<?php

/* Copyright (C) 2001-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2011      Philippe Grand       <philippe.grand@atoo-net.com>
 * Copyright (C) 2011-2012 Herve Prot           <herve.prot@symeos.com>
 * Copyright (C) 2011      Patrick Mary         <laube@hotmail.fr>
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

/* Specific to download the price list file */
if (isset($_GET["action"]) && $_GET["action"] == "builddoc") {
	define('NOTOKENRENEWAL', 1); // Disables token renewal

	function llxHeader() {
		
	}

	if (!defined('NOREQUIREMENU'))
		define('NOREQUIREMENU', '1');
	if (!defined('NOREQUIREHTML'))
		define('NOREQUIREHTML', '1');
	if (!defined('NOREQUIREAJAX'))
		define('NOREQUIREAJAX', '1');
}

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

$type = GETPOST("type", "alpha");
$action = GETPOST('action', 'alpha');

$canvas = GETPOST("canvas");
$objcanvas = '';
if (!empty($canvas)) {
	require_once DOL_DOCUMENT_ROOT . '/core/class/canvas.class.php';
	$objcanvas = new Canvas($db, $action);
	$objcanvas->getCanvas('product', 'list', $canvas);
}

// Security check
if ($type == 'PRODUCT')
	$result = restrictedArea($user, 'produit', '', '', '', '', '', $objcanvas);
else if ($type == 'SERVICE')
	$result = restrictedArea($user, 'service', '', '', '', '', '', $objcanvas);
else
	$result = restrictedArea($user, 'produit|service', '', '', '', '', '', $objcanvas);

$object = new Product($db);
/*
 * View
 */

llxHeader('', $langs->trans("ProductsAndServices"), '', '', '', '');

$title = $langs->trans("ProductsAndServices");

if (!empty($type)) {
	if ($type == "SERVICE") {
		$title = $langs->trans("Services");
	} else {
		$title = $langs->trans("Products");
	}
}

if ($action == 'builddoc') {
// Build export file
	$result = $object->exportPrice($_GET['price_level']);
	if ($result < 0) {
		setEventMessage($object->error, "errors");
	} else {
		setEventMessage($langs->trans("FileSuccessfullyBuilt"));
		$object->downloadTempFile($result);
	}
}

if ($action == 'import') {
// Build export file
	$result = $object->importPrice("price_level_import_UGAP.csv");
	if ($result < 0) {
		setEventMessage($object->error, "errors");
	} else {
		setEventMessage($langs->trans("FileSuccessfullyBuilt"));
	}
}

print_fiche_titre($title);
/* ?>
  <div class="dashboard">
  <div class="columns">
  <div class="four-columns twelve-columns-mobile graph">
  <?php $object->graphPieStatus(); ?>
  </div>

  <div class="eight-columns twelve-columns-mobile new-row-mobile graph">
  <?php $object->graphBarStatus(); ?>
  </div>
  </div>
  </div>
  <?php */
print '<div class="with-padding">';

//print start_box($titre,"twelve","16-Companies.png");

/*
 * Barre d'actions
 *
 */

print '<div class="margin-bottom button-group right compact children-tooltip">';
if ($type == "SERVICE" || empty($type))
	print '<a class="button icon-star" href="' . strtolower(get_class($object)) . '/fiche.php?action=create&type=SERVICE">' . $langs->trans("NewService") . '</a> ';
if ($type == "PRODUCT" || empty($type))
	print '<a class="button icon-star" href="' . strtolower(get_class($object)) . '/fiche.php?action=create&type=PRODUCT">' . $langs->trans("NewProduct") . '</a>';

if ($user->rights->produit->export)
	print '<button class="button icon-outbox" onclick="openExportPrice();" title="ExportPriceLevels" ></button>';

if ($user->rights->produit->import)
	print '<a class="button icon-inbox" href="' . $_SERVER["PHP_SELF"] . '?action=import&type=' . $_GET["type"] . '" title="ImportPriceLevels" ></a>';

print "</div>";

if ($user->rights->produit->export)
	print "<script>
	function openExportPrice()
		{
			var cancelled = false;

			$.modal.prompt('Please enter value list price : ', function(value)
			{
				if (value.length == 0)
				{
					$(this).getModalContentBlock().message('Please enter a correct value', { append: false, classes: ['red-gradient'] });
					return false;
				}

				window.location.href = '" . $_SERVER['PHP_SELF'] . "?action=builddoc&type=" . $_GET["type"] . "&price_level='+value;

			}, function()
			{
			});
		};
		</script>";

$i = 0;
$obj = new stdClass();
print '<table class="display dt_act" id="product" >';
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
print $title;
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "ref";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->bSearchable = true;
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("ref", "url");
$i++;
print'<th class="essential">';
print $langs->trans('Label');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "label";
$obj->aoColumns[$i]->sDefaultContent = "";
$i++;
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
print $langs->trans('SellingPrice');
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "price.pu_ht";
$obj->aoColumns[$i]->sDefaultContent = "";
$obj->aoColumns[$i]->sClass = "fright";
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("price.pu_ht", "price");
$i++;
print'<th class="essential">';
print $langs->trans("Date");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "updatedAt";
$obj->aoColumns[$i]->sClass = "center";
$obj->aoColumns[$i]->bUseRendered = false;
$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("updatedAt", "date");
$i++;
if (empty($type)) {
	print'<th class="essential">';
	print $langs->trans("Type");
	print'</th>';
	$obj->aoColumns[$i] = new stdClass();
	$obj->aoColumns[$i]->mDataProp = "type";
	$obj->aoColumns[$i]->sClass = "center";
	$obj->aoColumns[$i]->sWidth = "60px";
	$obj->aoColumns[$i]->sDefaultContent = "PRODUCT";
	$obj->aoColumns[$i]->fnRender = $object->datatablesFnRender("type", "status");
	$i++;
}
print'<th class="essential">';
print $langs->trans("Status");
print'</th>';
$obj->aoColumns[$i] = new stdClass();
$obj->aoColumns[$i]->mDataProp = "Status";
$obj->aoColumns[$i]->sClass = "dol_select center";
$obj->aoColumns[$i]->sWidth = "100px";
$obj->aoColumns[$i]->sDefaultContent = "ST_NEVER";
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

$url = "product/fiche.php";
$obj->aoColumns[$i]->fnRender = 'function(obj) {
	var ar = [];
	ar[ar.length] = "<a href=\"' . $url . '?id=";
	ar[ar.length] = obj.aData._id.toString();
	ar[ar.length] = "&action=edit&backtopage=' . $_SERVER['PHP_SELF'] . '\" class=\"sepV_a\" title=\"' . $langs->trans("Edit") . '\"><img src=\"img/action_edit.png\" alt=\"\" /></a>";
	var str = ar.join("");
	return str;
	}';
//ar[ar.length] = "<a href=\"\"";
//ar[ar.length] = " class=\"delEnqBtn\" title=\"' . $langs->trans("Delete") . '\"><img src=\"img/action_delete.png\" alt=\"\" /></a>";
print'</tr>';
print'</thead>';
print'<tfoot>';
/* input search view */
$i = 0; //Doesn't work with bServerSide
print'<tr>';
print'<th id="' . $i . '"></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Product") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search Label") . '" /></th>';
$i++;
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search category") . '" /></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
if (empty($type)) {
	print'<th id="' . $i . '"></th>';
	$i++;
}
print'<th id="' . $i . '"><input type="text" placeholder="' . $langs->trans("Search status") . '" /></th>';
$i++;
print'<th id="' . $i . '"></th>';
$i++;
print'</tr>';
print'</tfoot>';
print'<tbody>';
print'</tbody>';
print "</table>";

if (empty($type))
	$type = "PRODUCT";
//	$obj->sAjaxSource = DOL_URL_ROOT . "/core/ajax/listdatatables.php?json=listType&class=" . get_class($object) . "&key=" . $type;
//$obj->bServerSide = true;
//$obj->sDom = 'C<\"clear\">lfrtip';

$obj->aoAjaxData = '[{name :"class",value:"' . get_class($object) . '"},
			{"name": "query", "value": "{\"type\": \"' . $type . '\"}"}]';

$object->datatablesCreate($obj, "product", true, true);

//print end_box();
print '</div>'; // end

llxFooter();
?>
