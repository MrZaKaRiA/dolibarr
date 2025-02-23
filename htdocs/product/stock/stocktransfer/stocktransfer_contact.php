<?php
/* Copyright (C) 2005      Patrick Rouillon     <patrick@rouillon.net>
 * Copyright (C) 2005-2016 Destailleur Laurent  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2011-2015 Philippe Grand       <philippe.grand@atoo-net.com>
 * Copyright (C) 2021  Gauthier VERDOL <gauthier.verdol@atm-consulting.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/product/stock/stocktransfer/stocktransfer_contact.php
 *       \ingroup    propal
 *       \brief      Tab to manage contacts/addresses of proposal
 */

// Load Dolibarr environment
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/stocktransfer/class/stocktransfer.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/stocktransfer/lib/stocktransfer_stocktransfer.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Load translation files required by the page
$langs->loadLangs(array('facture', 'orders', 'sendings', 'companies', 'stocks'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$lineid = GETPOSTINT('lineid');

$action = GETPOST('action', 'alpha');

$object = new StockTransfer($db);

// Load object
//include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php'; // Must be 'include', not 'include_once'.
if ($id > 0 || !empty($ref)) {
	$ret = $object->fetch($id, $ref);
	if ($ret == 0) {
		$langs->load("errors");
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
		$error++;
	} elseif ($ret < 0) {
		setEventMessages($object->error, $object->errors, 'errors');
		$error++;
	}
}
if (!$error) {
	$object->fetch_thirdparty();
} else {
	header('Location: '.dol_buildpath('/stocktransfer/stocktransfer_list.php', 1));
	exit;
}

// Security check
if ($user->socid) {
	$socid = $user->socid;
}


$permissiontoread = $user->hasRight('stocktransfer', 'stocktransfer', 'read');
$permissiontoadd = $user->hasRight('stocktransfer', 'stocktransfer', 'write'); // Used by the include of actions_addupdatedelete.inc.php and actions_lineupdown.inc.php
$permissionnote = $user->hasRight('stocktransfer', 'stocktransfer', 'write'); // Used by the include of actions_setnotes.inc.php
$permissiontodelete = $user->rights->stocktransfer->stocktransfer->delete || ($permissiontoadd && isset($object->status) && $object->status < $object::STATUS_TRANSFERED);
$permissiondellink = $user->hasRight('stocktransfer', 'stocktransfer', 'write'); // Used by the include of actions_dellink.inc.php
$upload_dir = $conf->stocktransfer->multidir_output[isset($object->entity) ? $object->entity : 1];

// Security check - Protection if external user
//if ($user->socid > 0) accessforbidden();
//if ($user->socid > 0) $socid = $user->socid;
//$isdraft = (($object->statut == $object::STATUS_DRAFT) ? 1 : 0);
//$result = restrictedArea($user, 'stocktransfer', $object->id, '', '', 'fk_soc', 'rowid', $isdraft);
//$result = restrictedArea($user, 'stocktransfer', $object->id, '', 'stocktransfer');

if (!$permissiontoread || ($action === 'create' && !$permissiontoadd)) {
	accessforbidden();
}


/*
 * Add a new contact
 */

if ($action == 'addcontact' && $user->hasRight('stocktransfer', 'stocktransfer', 'write')) {
	if ($object->id > 0) {
		$contactid = (GETPOSTINT('userid') ? GETPOSTINT('userid') : GETPOSTINT('contactid'));
		$result = $object->add_contact($contactid, GETPOST("typecontact") ? GETPOST("typecontact") : GETPOST("type"), GETPOST("source"));
	}

	if ($result >= 0) {
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
		exit;
	} else {
		if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
			$langs->load("errors");
			setEventMessages($langs->trans("ErrorThisContactIsAlreadyDefinedAsThisType"), null, 'errors');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
} elseif ($action == 'swapstatut' && $user->hasRight('stocktransfer', 'stocktransfer', 'write')) { // Toggle the status of a contact
	if ($object->id > 0) {
		$result = $object->swapContactStatus(GETPOST('ligne'));
	}
} elseif ($action == 'deletecontact' && $user->hasRight('stocktransfer', 'stocktransfer', 'write')) { // Deletes a contact
	$result = $object->delete_contact($lineid);

	if ($result >= 0) {
		header("Location: ".$_SERVER['PHP_SELF']."?id=".$object->id);
		exit;
	} else {
		dol_print_error($db);
	}
}


/*
 * View
 */

llxHeader('', $langs->trans('ModuleStockTransferName'), 'EN:Commercial_Proposals|FR:Proposition_commerciale|ES:Presupuestos');

$form = new Form($db);
$formcompany = new FormCompany($db);
$formother = new FormOther($db);

if ($object->id > 0) {
	$head = stocktransferPrepareHead($object);
	print dol_get_fiche_head($head, 'contact', $langs->trans("StockTransfer"), -1, 'stock');


	// Proposal card

	$linkback = '<a href="'.DOL_URL_ROOT.'/comm/propal/list.php?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';


	$morehtmlref = '<div class="refidno">';
	// Thirdparty
	$morehtmlref .= empty($object->thirdparty) ? '' : $object->thirdparty->getNomUrl(1, 'customer');
	if (!getDolGlobalInt('MAIN_DISABLE_OTHER_LINK') && $object->thirdparty->id > 0) {
		$morehtmlref .= ' (<a href="'.DOL_URL_ROOT.'/commande/list.php?socid='.$object->thirdparty->id.'&search_societe='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherOrders").'</a>)';
	}
	// Project
	if (isModEnabled('project')) {
		$langs->load("projects");
		if (!empty($object->fk_project)) {
			$morehtmlref .= '<br>';
			$proj = new Project($db);
			$proj->fetch($object->fk_project);
			$morehtmlref .= $proj->getNomUrl(1);
			if ($proj->title) {
				$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
			}
		}
	}
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref, '', 0, '', '', 1);

	print dol_get_fiche_end();

	$user->rights->stocktransfer->write = $user->hasRight('stocktransfer', 'stocktransfer', 'write');
	// Contacts lines (modules that overwrite templates must declare this into descriptor)
	$dirtpls = array_merge($conf->modules_parts['tpl'], array('/core/tpl'));
	foreach ($dirtpls as $reldir) {
		$res = @include dol_buildpath($reldir.'/contacts.tpl.php');
		if ($res) {
			break;
		}
	}
}

// End of page
llxFooter();
$db->close();
