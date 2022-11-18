<?php
include("../includes/common-files.php");
$a->authenticate();
$general_head = getSettingRow('general_head');
$head = $db->fetch_array_by_query("Select * from item_location where id=" . $general_head['value']);
$general_subhead = getSettingRow('general_subhead');
$sub_head = $db->fetch_array_by_query("select * from item_sublocation where id=" . $general_subhead['value']);
//echo ADMIN_URL;
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'checkSubItems') {
	$item = $_REQUEST['item_id'];
	$db->select("Select * from sub_item where item_id = " . $item);
	$subItems = $db->fetch_all();
	if (count($subItems) > 0) {
		echo "yes";
	} else {
		echo "no";
	}
	exit();
}

function getBalance($id, $type)
{
	global $db;

	if ($type == 'ledger') {
		$ledger_row = $db->fetch_array_by_query("select * from ledger where id=" . $id);
		$tr_row = $db->fetch_array_by_query("SELECT SUM(case when type='credit' then amount else 0 end) as credit, SUM(case when type='debit' then amount else 0 end) as debit from transactions where ledger_id=" . $id);
	} else {
		$ledger_row = $db->fetch_array_by_query("select * from sub_ledgers where id=" . $id);
		$tr_row = $db->fetch_array_by_query("SELECT SUM(case when type='credit' then amount else 0 end) as credit, SUM(case when type='debit' then amount else 0 end) as debit from transactions where sub_ledger_id=" . $id);
	}

	if ($ledger_row['opening_balance'] > 0) {
		$total = $ledger_row['opening_balance'];
	}


	if ($ledger_row['balance_type'] == 'debit') {
		$total = -abs(floatval($total));
	}
	$total = $tr_row['credit'] - $tr_row['debit'] + $total;

	if ($total > 0) {
		$total = array('balance' => number_format(abs($total)), 'type' => "(Cr)", 'amount' => abs($total));
	} else if ($total < 0) {
		$total = array('balance' => number_format(abs($total)), 'type' => "(Dr)", 'amount' => abs($total));
	} else {
		$total = array('balance' => floatval($total), 'type' => "(Cr)", 'amount' => abs($total));
	}

	return $total;
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getBalance') {
	if ($_REQUEST['type'] == 'ledger') {
		$balance = getBalance($_REQUEST['ledger_id'], $_REQUEST['type']);
	} else {
		$balance = getBalance($_REQUEST['subledger'], $_REQUEST['type']);
	}
	echo json_encode($balance);
	exit();
}

if (isset($_REQUEST['reg-id']) && $_REQUEST['reg-id'] != '') {
	$reg = explode('-', $_REQUEST['reg-id']);
	$_REQUEST['agent'] = $reg[1];
}



if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getWalkInSubLedgers' && intval($_REQUEST['id']) > 0) {
	$db->Select("Select * from sub_ledgers where ledger_id = " . $_REQUEST['id']);
	$sub_ledgers = $db->fetch_all();
	$sub_ledger_op .= '<option value="0"> Select Sub Ledgers </option>';
	foreach ($sub_ledgers as $sub) {
		$sub_ledger_op .= '<option data-credit="' . $sub['credit_limit_active'] . '" data-cvalue="' . $sub['credit_limit'] . '" value="' . $sub['id'] . '">' . $sub['name'] . '</option>';
	}
	echo $sub_ledger_op;
	exit();
}

function checkUserloc($needle, $type)
{
	global $auth_row;
	$haystack = '';
	if ($type == 'head') :
		$haystack = explode(',', $auth_row['head_id']);
	elseif ($type == 'subhead') :
		$haystack = explode(',', $auth_row['subhead_id']);
	endif;
	$check = false;
	if ($auth_row['super_admin'] == 'no') {
		foreach ($haystack as $value) {
			if ($needle == $value) {
				$check = true;
				break;
			}
		}
	} else {
		$check = true;
	}
	return $check;
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'GetDistributers') {
	$data = $_REQUEST['data'];
	$head = $_REQUEST['head'];
	$customer = $_REQUEST['order_customer'];
	$sub_head = $_REQUEST['sub_head'];
	$html = '';

	if ($data == 'distributer') {
		$db->Select("select * from ledger where active_distributer='yes' and FIND_IN_SET('" . $head . "',distributer_head) and FIND_IN_SET('" . $sub_head . "',distributer_subhead) and FIND_IN_SET(" . getCompanyId() . ",company_id)");
		$html .= '<option value="0"> Select Distributer </option>';
	} else if ($data == 'agent') {
		//$db->Select("select * from ledger where agent='yes'  and FIND_IN_SET(".getCompanyId().",company_id)");		
		$db->Select("select * from ledger where  FIND_IN_SET(" . getCompanyId() . ",company_id)");
		$html .= '<option value="0"> Select Customer </option>';
	} else {
		$db->Select("select * from ledger where active_walk_in_customer = 'yes' or active_customer='yes' and FIND_IN_SET(" . getCompanyId() . ",company_id)");
		$html .= '<option value="0"> Select Customer </option>';
	}
	$ledgers = $db->fetch_all();
	foreach ($ledgers as $ledger) {
		$select = '';
		if ($ledger['id'] == $customer) {
			$select = 'selected';
		}
		$html .= '<option ' . $select . ' data-cvalue="' . $ledger['credit_limit'] . '" data-credit="' . $ledger['credit_limit_acitve'] . '" data-active-walk-in="' . $ledger['active_walk_in_customer'] . '" value="' . $ledger['id'] . '">' . $ledger['name'] . '</option>';
	}
	echo $html;
	exit();
}


function count_head($head)
{
	$total_head = 0;
	foreach (explode(',', $head) as $key => $value) {

		$total_head = $total_head + 1;
	}
	return $total_head;
}

function count_subhead($subhead)
{
	$total_subhead = 0;
	foreach (explode(',', $subhead) as $key => $value) {

		$total_subhead = $total_subhead + 1;
	}
	return $total_subhead;
}

function getContractArticles($subitem, $purchaseDetail, $row)
{
	global $db;
	$sql = "select ar.* from s_contract_inventory as sci ,s_contract_transaction as sct,sale_contract as sc ,sub_item as sb,article as ar  where sci.contract_id = sc.id and sci.c_transaction_id = sct.id and sci.sub_item_id = sb.id and sci.article_id = ar.id and sc.contract_type = '" . $row['sale_type'] . "'";
	$customer_contract = $db->fetch_array_by_query("Select * from sale_contract where customer_id = " . $row['customer_id'] . " and location_id = " . $purchaseDetail['location'] . " and project_id=" . $purchaseDetail['sub_location'] . " and locked ='no'");
	if ($customer_contract) {
		$sql .= ' and sc.customer_id = ' . $row['customer_id'];
	} else {
		$sql .= ' and sc.customer_id = 0';
	}

	$sql .= " and sct.brand_id = " . $purchaseDetail['brand'] . " and sb.code='" . $subitem . "' and sct.quality_id = " . $purchaseDetail['quality'] . " and sct.item_id = " . $purchaseDetail['item_id'] . " and sc.location_id = " . $purchaseDetail['location'] . " and sc.sub_location_id = " . $purchaseDetail['sub_location'] . " and sc.locked='no'";
	$db->Select($sql);
}

function getcontractValues($purchaseDetail, $row)
{
	global $db;
	$sql = "select sb.code from s_contract_inventory as sci ,s_contract_transaction as sct,sale_contract as sc ,sub_item as sb where sci.contract_id = sc.id and sci.c_transaction_id = sct.id and sb.id = sci.sub_item_id and sc.contract_type = '" . $row['sale_type'] . "'";
	$customer_contract = $db->fetch_array_by_query("Select * from sale_contract where customer_id = " . $row['customer_id'] . " and location_id = " . $purchaseDetail['location'] . " and project_id=" . $purchaseDetail['sub_location'] . " and locked ='no'");
	if ($customer_contract) {
		$sql .= ' and sc.customer_id = ' . $row['customer_id'];
	} else {
		$sql .= ' and sc.customer_id = 0';
	}
	$sql .= " and sct.brand_id = " . $purchaseDetail['brand'] . " and sct.quality_id = " . $purchaseDetail['quality'] . " and sct.item_id = " . $purchaseDetail['item_id'] . " and sc.location_id = " . $purchaseDetail['location'] . " and sc.sub_location_id = " . $purchaseDetail['sub_location'] . " and sc.locked='no' group by sb.code";
	$db->Select($sql);
}
//Select ar.* from article as ar where ar.id in (Select qi.article_id from quotation_inventory as qi where )
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getArticles') {
	$code = $_REQUEST['code'];
	$item_id = $_REQUEST['item'];
	$brand = $_REQUEST['brand'];
	$customer = $_REQUEST['customer'];
	$finishing_type = $_REQUEST['finishing_type'];
	$sale_type = $_REQUEST['sale_type'];
	if ($sale_type == 'customer') {
		$type = 'retail_sale';
	} else if ($sale_type == 'company') {
		$type = 'company_sale';
	} elseif ($sale_type == 'whole_sale') {
		$type = 'whole_sale';
	}
	$quality = $_REQUEST['quality'];
	$location = $_REQUEST['location_id'];
	$sub_location = $_REQUEST['project_id'];
	if ($_REQUEST['type'] == 'journal') {
		$sql = "SELECT ar.* FROM quotation_inventory as qi right join article ar on ar.id = qi.article_id right join sub_item as sb on qi.sub_item_id = sb.id  where qi.quality_id=" . $quality . " and qi.item_id=" . $item_id . " and sb.code='" . $code . "'";

		if (intval($finishing_type) > 0) {
			$sql .= " and sb.finishing_type = " . $finishing_type;
		}
		$sql .= " and sb.finishing_type = " . $finishing_type . " and sb.brand_id = " . $brand . " and location_id = " . $location . " and sub_location_id= " . $sub_location . " group by qi.article_id";
		$db->Select($sql);
	} else {
		$sql = "select ar.* from s_contract_inventory as sci ,s_contract_transaction as sct,sale_contract as sc ,sub_item as sb,article as ar  where sci.contract_id = sc.id and sci.c_transaction_id = sct.id and sci.sub_item_id = sb.id and sci.article_id = ar.id and sc.contract_type = '" . $type . "' and sc.customer_id = " . $customer;
		if (intval($finishing_type) > 0) {
			$sql .= " and sct.finishing_type = " . $finishing_type;
		}
		$sql .= " and sct.brand_id = " . $brand . " and sb.code='" . $code . "' and sct.quality_id = " . $quality . " and sct.item_id = " . $item_id . " and sc.location_id = " . $location . " and sct.finishing_type = " . $finishing_type . " and sc.sub_location_id = " . $sub_location . " and sc.locked='no'";
		$db->Select($sql);
	}
	$articles = $db->fetch_all();
	$article_li .= '<option value="0"> Select Article </option>';
	foreach ($articles as $article) {
		$article_li .= '<option value="' . $article['id'] . '">' . $article['name'] . '</option>';
	}
	echo $article_li;
	exit();
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getBrands') {
	$item_id = $_REQUEST['item'];

	$db->Select("select itb.* from item_brand as itb right join sub_item as sb on (sb.brand_id = itb.id) where sb.item_id = " . $item_id . " and FIND_IN_SET('" . getCompanyId() . "',itb.company_id)  group by itb.name");
	$brands = $db->fetch_all();
	$brand_li .= '<option value="0"> Select Brands </option>';
	foreach ($brands as $brand) {
		$brand_li .= '<option value="' . $brand['id'] . '">' . $brand['name'] . '</option>';
	}
	echo $brand_li;
	exit();
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getQualities') {
	$item_id = $_REQUEST['item'];
	$brand = $_REQUEST['brand'];
	$location_id = $_REQUEST['location_id'];
	$project_id = $_REQUEST['project_id'];
	$finishing_type = $_REQUEST['finishing_type'];
	// $db->Select("Select qi.quality_id as q_id from quotation_inventory as qi left join sub_item as sbi on (qi.item_id = sbi.item_id and qi.sub_item_id = sbi.id) where qi.item_id = ".$item_id." and sbi.brand_id = ".$brand." and location_id = ".$location_id." and sub_location_id = ".$project_id." group by qi.quality_id");
	$db->select('SELECT id as q_id FROM item_quality where  FIND_IN_SET("' . getCompanyId() . '",company_id)');
	$qualities = $db->fetch_all();
	$quality_li .= '<option value="0"> Select Quality </option>';
	foreach ($qualities as $quality) {
		$qualiti = $db->fetch_array_by_query("select * from item_quality where id=" . $quality['q_id']);
		$quality_li .= '<option value="' . $quality['q_id'] . '">' . $qualiti['name'] . '</option>';
	}
	echo $quality_li;
	exit();
}


if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getSubledger') {
	$sql = 'select * from sub_ledgers where';
	$check = '';
	if ($_REQUEST['name'] != '') {
		$sql .= " name LIKE '%" . $_REQUEST['name'] . "%'";
	}

	if ($_REQUEST['name'] != '' && $_REQUEST['id_card'] != '') {
		$sql .= ' or ';
	}
	if ($_REQUEST['id_card'] != '') {
		$id_card = str_replace('-', '', $_REQUEST['id_card']);
		$sql .= " id_card LIKE '%" . $id_card . "%'";
	}
	if ($_REQUEST['id_card'] != '' && $_REQUEST['phone'] != '') {
		$sql .= ' or ';
	}
	if ($_REQUEST['phone'] != '') {
		$phone = str_replace('-', '', $_REQUEST['phone']);
		$sql .= " mobile LIKE '%" . $phone . "%'";
	}
	$db->select($sql);
	$sub_ledgers = $db->fetch_all();
	$html = '';
	$i = 1;
	if ($sub_ledgers) {
		foreach ($sub_ledgers as $sub) {
			$html .= '<tr>';
			$html .= '<td>' . $i . '</td>';
			$html .= '<td><input type="hidden" class="walk_in_name" value="' . $sub['name'] . '" >' . $sub['name'] . '</td>';
			$html .= '<td>' . $sub['id_card'] . '</td>';
			$html .= '<td>' . $sub['mobile'] . '</td>';
			$html .= '<td><input type="hidden" class="walk_in_id" value="' . $sub['id'] . '" ><input type="checkbox" onclick="checkSubledger(this)" class="walk_in_check"></td>';
			$html .= '</tr>';
			$i++;
		}
	} else {
		$html .= '<tr><td colspan="5">No Sub Ledger</td></tr>';
	}

	echo $html;
	exit();
}

function getalternateUnitSpan($quantity, $item_id)
{
	global $db;
	$result = '';
	$item = $db->fetch_array_by_query("select * from item where id=" . $item_id);
	$item_unit = $db->fetch_array_by_query("select * from item_unit where id=" . $item['unit_id']);
	$alternate_unit = $db->fetch_array_by_query("select * from item_unit where id=" . $item['alternative_unit_id']);
	if (!empty($item) && $item['alternative_unit_id'] != 0) {
		$alternate_conversion = $quantity / $item['conversion'];
		$Kg_conversion = $quantity / $item['kg_conversion'];
		$result .= " ( ";
		$result .= round($alternate_conversion, 2) . " " . $alternate_unit['name'];

		if ($item['conversion'] > 0 && $item['kg_conversion'] > 0) {
			$result .= " & ";
		}

		if (floatval($item['kg_conversion']) > 0) {
			$result .= round($Kg_conversion, 2) . " Kg ";
		}
		$result .= " )";
		return $result;
	} else {
		return $result;
	}
}
function GetSubitemQuantity($head, $sub_head, $sub_item_id, $article, $quality, $section, $warehoue_id)
{
	global $db;
	$tables = array('quotation_inventory', 'sales_inventory');
	$quotation_q = 0;
	$sales_q = 0;
	foreach ($tables as $table) {
		$sql = "Select * from " . $table . " where sub_item_id=" . $sub_item_id;
		if ($article != '') {
			$sql .= " and article_id = " . $article;
		}
		if ($quality != '' && $quality != '0') {
			$sql .= ' and quality_id = ' . $quality;
		}
		if ($warehoue_id != '') {
			$sql .= " and warehouse_id = " . $warehoue_id;
		}
		if ($section != '') {
			$sql .= " and section_id = " . $section;
		}
		$sql .= " and location_id=" . $head . " and sub_location_id=" . $sub_head . " and company_id=" . getCompanyId();

		$db->select($sql);
		$subItems_inventory = $db->fetch_all();
		foreach ($subItems_inventory as $subItem_inv) {
			if ($table == 'quotation_inventory') {
				$quotation_q += $subItem_inv['quantity'];
			} else if ($table == 'sales_inventory') {
				$sales_q += $subItem_inv['quantity'];
			}
		}
	}
	$quantity = $quotation_q - $sales_q;
	if (intval($quantity) <= 0) {
		$quantity = 0;
	}
	return $quantity;
}


if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getQuantity') {
	$quantity = 0;
	if (isset($_REQUEST['head']) && $_REQUEST['head'] != ''  && isset($_REQUEST['sub_head']) && $_REQUEST['sub_head'] != '') {
		$sub_item_id = $_REQUEST['Sub_item_id'];
		$head = $_REQUEST['head'];
		$subhead = $_REQUEST['sub_head'];
		$quality = $_REQUEST['Sub_item_quality'];
		$article = $_REQUEST['Sub_item_article'];
		$quantity = GetSubitemQuantity($head, $subhead, $sub_item_id, $article, $quality, '', '');
	}
	echo $quantity;
	exit();
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'CheckIngSubItem') {
	$SubItemCode = $_REQUEST['SubItemCode'];
	$SubItemArticle = $_REQUEST['SubItemArticle'];
	$itemId = $_REQUEST['ItemID'];
	$itemrow = $db->fetch_array_by_query("select * from item where id=" . $itemId);
	$sql = "Select * from sub_item where code = '" . $SubItemCode . "' and item_id=" . $itemId;
	if ($itemrow['article'] == 'yes') {
		$sql .= ' and article = ' . $SubItemArticle;
	} else {
		$sql .= ' and article = 0';
	}
	$subItem = $db->fetch_array_by_query($sql);
	if ($subItem) {
		$imgs = json_decode($subItem['image'], true);
		echo json_encode(array("id" => $subItem['id'], "subItemimgs" => $imgs));
	} else {
		echo json_encode(array("fail"));
	}
	exit();
}
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'verified_save') {
	unset($_REQUEST['command']);
	$sub_ledger = array(
		'name' => $_REQUEST['name'],
		'mobile' => str_replace('-', '', $_REQUEST['mobile']),
		'id_card' => str_replace('-', '', $_REQUEST['id_card']),
		'ledger_id' => $_REQUEST['main_ledger_id'],
		'created_at' => time(),
		'company_id' => getCompanyId()
	);
	$phone_number = str_replace('-', '', $_REQUEST['mobile']);
	$id_card = str_replace('-', '', $_REQUEST['id_card']);
	$get_sub_ledger = $db->fetch_array_by_query("select * from sub_ledgers where id_card=" . $id_card . " or mobile = " . $phone_number . " and company_id=" . getCompanyId());
	if (!empty($get_sub_ledger)) {
		$result = $db->update($get_sub_ledger['id'], $sub_ledger, 'sub_ledgers');
		echo json_encode(array("id" => $_REQUEST['sub_ledger_id'], "status" => 'update'));
	} else {
		$result = $db->insert($sub_ledger, 'sub_ledgers');
		echo json_encode(array("id" => $result, "status" => 'insert'));
	}
	exit();
}
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'checkWalkInCustomer' && intval($_REQUEST['led_id']) > 0) {
	$ledg_row = $db->fetch_array_by_query("Select * from ledger where id = " . $_REQUEST['led_id'] . " and active_walk_in_customer = 'yes' and company_id=" . getCompanyId());
	$data_arr = [];
	if (!empty($ledg_row)) {
		$data_arr['ledger_id'] = $ledg_row['id'];
		$data_arr['status'] = 'yes';
		echo json_encode($data_arr);
	} else {
		$data_arr['status'] = 'no';
		echo json_encode($data_arr);
	}
	exit();
}
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'checkSubledger' && intval($_REQUEST['serch_subledger']) != '') {

	$sub_ledg_row = $db->fetch_array_by_query("Select * from sub_ledgers where (mobile = '" . str_replace('-', '', $_REQUEST['serch_subledger']) . "' or id_card = '" . str_replace('-', '', $_REQUEST['serch_subledger']) . "') and company_id=" . getCompanyId());
	$data_arr = [];
	if (!empty($sub_ledg_row)) {
		$data_arr['id'] = $sub_ledg_row['id'];
		$data_arr['name'] = $sub_ledg_row['name'];
		$data_arr['mobile'] = $sub_ledg_row['mobile'];
		$data_arr['id_card'] = $sub_ledg_row['id_card'];
		$data_arr['ledger_id'] = $sub_ledg_row['ledger_id'];
		$data_arr['status'] = 'yes';
		echo json_encode($data_arr);
	} else {
		$data_arr['status'] = 'no';
		echo json_encode($data_arr);
	}
	exit();
}

function Quantity($query)
{
	global $db;
	$quantity = 0;
	$db->select($query);
	$quotation = $db->fetch_all();
	foreach ($quotation as $q) {
		$quantity += $q['quantity'];
	}
	return $quantity;
}
// if (isset($_REQUEST['pr_no']) && ($_REQUEST['pr_no'] != '')) {
// 	$purchase_reciept = $db->fetch_array_by_query("select * from purchase_reciept where id=".intval($_REQUEST['pr_no'])." and company_id=".getCompanyId());
// 	$po_row['purch_rc_no'] = $purchase_reciept['pr_no'];
// }
// if (isset($_REQUEST['pr_no']) && ($_REQUEST['pr_no'] != '')) {
// 	$db->select("select * from reciept_detail where pr_id=".intval($_REQUEST['pr_no'])." and company_id=".getCompanyId()); 
// 	$purchase_reciept_details = $db->fetch_all();
// }

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'get_sub_loc') {
	$db->select('select * from item_sublocation where location_id=' . $_REQUEST['item_loc'] . ' and FIND_IN_SET(' . getCompanyId() . ',company_id)');
	$sub_locations = $db->fetch_all();
	$sub_loc .= "<option selected disabled>Select Sub Location</option>";
	foreach ($sub_locations as $sub_location) {
		$sub_loc .= "<option value='" . $sub_location['id'] . "'>" . $sub_location['name'] . "</option>";
	}
	echo $sub_loc;
	exit();
}

$db->select("select * from item_location where company_id=" . getCompanyId() . " order by id desc");
$locations = $db->fetch_all();

if (isset($_REQUEST['so_id']) && intval($_REQUEST['so_id']) > 0) {
	$id = $_REQUEST['so_id'];
	$pi_row = $db->fetch_array_by_query("Select * from sale_order where id=" . $id);
}

// if(isset($_REQUEST['agent']) && intval($_REQUEST['agent']) > 0 ){
// 	$_REQUEST['agent'] = $_REQUEST['agent'];
// }else if(!isset($_REQUEST['agent']) && $pi_row['sale_type'] == 'agent'){
// 	$_REQUEST['agent'] = $pi_row['sales_person'];
// }else{
// 	$_REQUEST['agent'] = '';
// }

if (isset($_POST['data_id'])) {
	$db->select('SELECT * FROM item where company_id=' . getCompanyId() . " order by id desc");
	$jsonResult = $db->fetch_all();
	echo json_encode($jsonResult);
}
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getSection') {
	$warehouse_id = intval($_REQUEST['warehouse_id']);
	$item_id = intval($_REQUEST['item_id']);
	$sections_li = '';
	$item_row = $db->fetch_array_by_query("Select * from item where id=" . $item_id);
	if ($item_row['section'] == 'yes') {
		$db->select("select * from sections where company_id=" . getCompanyId() . " and warehouse_id=" . $warehouse_id . " order by id desc");
		$sections = $db->fetch_all();
		if ($sections) {
			$sections_li .= '<option value="0"> Select Sections </option>';
			foreach ($sections as $section) {
				$sections_li .= '<option value="' . $section['id'] . '">' . $section['name'] . '</option>';
			}
		} else {
			$sections_li .= '<option value="0"> No Record Found </option>';
		}
	} else {
		$sections_li .= '<option value="0"> No Record Found </option>';
	}
	echo $sections_li;
	exit();
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'getSection') {
	$warehouse_id = intval($_REQUEST['warehouse_id']);
	$item_id = intval($_REQUEST['item_id']);
	$sections_li = '';
	$item_row = $db->fetch_array_by_query("Select * from item where id=" . $item_id);
	if ($item_row['section'] == 'yes') {
		$db->select("select * from sections where company_id=" . getCompanyId() . " and warehouse_id=" . $warehouse_id . " order by id desc");
		$sections = $db->fetch_all();
		if ($sections) {
			$sections_li .= '<option value="0"> Select Sections </option>';
			foreach ($sections as $section) {
				$sections_li .= '<option value="' . $section['id'] . '">' . $section['name'] . '</option>';
			}
		} else {
			$sections_li .= '<option value="0"> No Record Found </option>';
		}
	} else {
		$sections_li .= '<option value="0"> No Record Found </option>';
	}
	echo $sections_li;
	exit();
}

if (isset($_POST['purchaseInvoice'])) {
	$arr = array();
	$arr['company_id'] = getCompanyId();
	$arr['user_id'] = getUSerId();
	$arr['created_at'] = time();
	$arr['narration'] = $_POST['narration'];
	$arr['sale_invoice_series'] = $_POST['purch_invoice_series'];
	$arr['invoice_date'] = strtotime($_POST['invoice_date']);
	$arr['invoice_day'] = $_POST['invoice_day'];
	$arr['location_id'] = intval($_POST['cr_location']);
	$arr['payment_type'] = $_REQUEST['payment_type'];
	$arr['sale_type'] = $_REQUEST['sale_type'];
	$arr['sub_location_id'] = intval($_POST['cr_sublocation']);
	$arr['branch_id'] = intval($_POST['branch']);
	$arr['project_id'] = intval($_REQUEST['project_ledger']);
	$arr['sale_invoice_no'] = InvoiceNo();
	if ($_POST['commission_check'] == '') {
		$arr['commission'] = 'no';
		$arr['sales_person'] = 0;
		$arr['total_commission'] = 0;
	} else {
		$arr['commission'] = $_POST['commission_check'];
		$arr['sales_person'] = intval($_POST['sales_person']);
		$arr['total_commission'] = $_POST['total_commission'];
	}
	if (isset($_REQUEST['so_id']) && intval($_REQUEST['so_id']) > 0) {
		$arr['order_id'] = $_REQUEST['so_id'];
	}
	$arr['discount_mode'] = $_POST['discount_mode'];
	$arr['ledger_party'] = $_POST['ledger_party'];
	$arr['total_amount'] = $_POST['total'];
	$arr['total_discount'] = intval($_POST['total_discount']);
	$arr['total_net'] = intval($_POST['total_net']);
	$arr['prev_old'] = 'no';
	$arr['discount_check'] = $_POST['discount_check'];
	$arr['total_quantity'] = $_POST['tot_quantity'];
	if ($_POST['walk_in'] > 0) {
		$arr['sub_ledger_id'] = $_POST['walk_in'];
	} else {
		$arr['sub_ledger_id'] = 0;
	}

	if ($_POST['discount_check'] == '') {
		$arr['discount_check'] = 'no';
	}

	if (isset($_REQUEST['loading_ledger']) && $_REQUEST['loading_ledger'] != '') {
		$arr['loading_ledger'] = $_POST['loading_ledger'];
		$arr['loading_quantity'] = $_POST['loading_quantity'];
		$arr['loading_rate'] = $_POST['loading_rate'];
		$arr['loading_charges'] = $_POST['loading_amount'];
	} else {
		$arr['loading_charges'] = 0;
		$arr['loading_quantity'] = 0;
		$arr['loading_rate'] = 0;
	}

	if (isset($_REQUEST['unloading_ledger']) && $_REQUEST['unloading_ledger'] != '') {
		$arr['unloading_ledger'] = $_POST['unloading_ledger'];
		$arr['unloading_quantity'] = $_POST['unloading_quantity'];
		$arr['unloading_rate'] = $_POST['unloading_rate'];
		$arr['unloading_charges'] = $_POST['unloading_amount'];
	} else {
		$arr['unloading_ledger'] = 0;
		$arr['unloading_charges'] = 0;
		$arr['unloading_quantity'] = 0;
		$arr['unloading_rate'] = 0;
	}

	if (isset($_REQUEST['other_amount']) && $_REQUEST['other_amount'] != '') {
		$arr['other_charges'] = $_POST['other_amount'];
		$arr['other_ledger'] = $_POST['other_ledger'];
		$arr['other_quantity'] = $_POST['other_quantity'];
		$arr['other_rate'] = $_POST['other_rate'];
	} else {
		$arr['other_ledger'] = 0;
		$arr['other_charges'] = 0;
		$arr['other_quantity'] = 0;
		$arr['other_rate'] = 0;
	}

	if (isset($_REQUEST['freight_amount']) && $_REQUEST['freight_amount'] != '') {
		$arr['freight_ledger'] = $_POST['freight_ledger'];
		$arr['freight_charges'] = $_POST['freight_amount'];
		$arr['single_vehicle'] = $_POST['single_vehicle'];
		$arr['single_receipt'] = $_POST['single_receipt'];
		$arr['single_bilty'] = $_POST['single_bilty'];
	} else {
		$arr['freight_ledger'] = 0;
		$arr['freight_charges'] = 0;
		$arr['single_vehicle'] = 0;
		$arr['single_receipt'] = 0;
		$arr['single_bilty'] = 0;
	}

	$arr['other_charges'] = $_POST['other_amount'];
	$arr['grand_total'] = intval($_POST['total']);
	if (isset($_REQUEST['ledger_party']) && $_REQUEST['ledger_party'] == 'single_party') {
		$arr['third_party'] = $_POST['third_party_single'];
	}
	if (isset($_REQUEST['sale_type']) && $_REQUEST['sale_type'] == 'company') {
		$arr['customer_id'] = $_POST['dr_project'];
		$arr['debit_head'] = $_POST['debit_head'];
		$arr['debit_subhead'] = $_POST['debit_subhead'];
	} else {
		$arr['customer_id'] = $_POST['customer_id'];
	}
	if (count($_POST) && (isset($_POST['img']))) {
		foreach ($_POST['img'] as $img) {
			if (strpos($img, 'data:image/jpeg;base64,') === 0) {
				$img = str_replace('data:image/jpeg;base64,', '', $img);
				$ext = '.jpg';
			}
			if (strpos($img, 'data:image/png;base64,') === 0) {
				$img = str_replace('data:image/png;base64,', '', $img);
				$ext = '.png';
			}
			$img = str_replace(' ', '+', $img);
			$data = base64_decode($img);
			$file_name = rand() . time() . $ext;
			$file = '../site-content/uploads/' . $file_name;
			if (file_put_contents($file, $data)) {
				$images_arr[] = $file_name;
			} else {
				echo "<p>The image could not be saved.</p>";
			}
		}
	}
	$arr['attachment'] = json_encode($images_arr);
	$arr['type'] = 'single';
	$receipt_id = $db->insert($arr, 'sale_invoice');
	$arr['id'] = $receipt_id;
	if (isset($_REQUEST['sale_type']) && $_REQUEST['sale_type'] == 'company') {
		$customer_id = $_POST['dr_project'];
	} else {
		$customer_id = $_POST['customer_id'];
	}

	if ($receipt_id) {
		if (isset($_REQUEST['payment_type']) && $_REQUEST['payment_type'] == 'due' && intval($receipt_id) > 0) {
			foreach ($_REQUEST['installment_amt'] as $index => $amount) {
				$exp = explode('/', $_REQUEST['installment_date'][$index]);
				$new_date = $exp[1] . '/' . $exp[0] . '/' . $exp[2];
				$installment_arr = array();
				$installment_arr['sale_invoice_id'] = $receipt_id;
				$installment_arr['installment_amt'] = $amount;
				$installment_arr['installment_date'] = strtotime($new_date);
				$installment_arr['created_at'] = time();
				$installment_arr['updated_at'] = time();
				$installment_arr['company_id'] = getCompanyId();
				$installment_arr['user_id'] = getUSerId();
				$installment_arr['status'] = 'pending';
				$installemnt_id = $db->insert($installment_arr, 'sale_installment_plan');
			}
		}
		if ($_POST['item_name']) {
			if (isset($_REQUEST['discount_mode']) && $_REQUEST['discount_mode'] != '') {
				$discount_mode = $_REQUEST['discount_mode'];
			}
			if (isset($_REQUEST['ledger_party']) && $_REQUEST['ledger_party'] != '') {
				$ledger_party = $_REQUEST['ledger_party'];
			}
			for ($i = 0; $i < count($_POST['item_name']); $i++) {
				$arr_detail = array();
				$arr_detail['item_id'] = $_POST['item_name'][$i];
				$arr_detail['item_group'] = $_POST['item_group'][$i];
				$arr_detail['finishing_type'] = $_POST['finishing_type'][$i];
				$arr_detail['brand'] = $_POST['brand'][$i];
				$arr_detail['quality'] = $_POST['quality'][$i];
				$arr_detail['quantity'] = $_POST['quantity'][$i];
				$arr_detail['contract'] = $_POST['contract'][$i];
				if (isset($_REQUEST['boxes'][$i]) && $_REQUEST['boxes'][$i] != '') {
					$arr_detail['boxes'] = $_POST['boxes'][$i];
				} else {
					$arr_detail['boxes'] = 0;
				}

				if (isset($_REQUEST['tons'][$i]) && $_REQUEST['tons'][$i] != '') {
					$arr_detail['tons'] = $_POST['tons'][$i];
				} else {
					$arr_detail['tons'] = 0;
				}

				$arr_detail['actual_rate'] = $_POST['rate'][$i];
				$sr_no = $_POST['sr_no'][$i];

				if ($_POST['discount'][$i] == '') {
					$arr_detail['discount'] = '0';
				} else {
					$arr_detail['discount'] = $_POST['discount'][$i];
				}

				if ($_POST['balance_category'][$i] == '') {
					$arr_detail['balance_category'] = 'Rs';
				} else {
					$arr_detail['balance_category'] = $_POST['balance_category'][$i];
				}

				if (isset($_REQUEST['inv_third_party_id'][$i]) && $_REQUEST['inv_third_party_id'][$i] != '') {
					$arr_detail['third_party'] = $_REQUEST['inv_third_party_id'][$i];
				} else {
					$arr_detail['third_party'] = 0;
				}

				if (isset($_REQUEST['serial_no'][$i]) && $_REQUEST['serial_no'][$i] != '') {
					$arr_detail['serial_no'] = $_REQUEST['serial_no'][$i];
				} else {
					$arr_detail['serial_no'] = 0;
				}

				$arr_detail['location'] = intval($_POST['cr_location']);
				$arr_detail['sub_location'] = intval($_POST['cr_sublocation']);
				if (isset($_POST['sub_item'][$i]) && $_POST['sub_item'][$i] != '') {
					$arr_detail['sub_item'] = $_POST['sub_item'][$i];
				}
				if ($_POST['commission_check'] == '') {
					$arr_detail['sales_person'] = 0;
					$arr_detail['commission'] = 0;
				} else {
					$arr_detail['sales_person'] = intval($_POST['sales_person']);
					$arr_detail['commission'] = $_POST['hidden_commission'][$i];
				}
				$arr_detail['discount_mode'] = $discount_mode;
				$arr_detail['party_mode'] = $ledger_party;
				$arr_detail['net_rate'] = $_POST['net_rate'][$i];
				$arr_detail['effective_rate'] = $_POST['eff_net_rate'][$i];
				$arr_detail['amount'] = $_POST['amount'][$i];
				if (isset($_REQUEST['sale_type']) && $_REQUEST['sale_type'] == 'company') {
					$arr_detail['debit_ledger_id'] = intval($_POST['dr_project']);
				} else {
					$arr_detail['debit_ledger_id'] = intval($_POST['customer_id']);
				}
				$arr_detail['created_at'] = time();
				$arr_detail['updated_at'] = time();
				$arr_detail['s_i_id'] = $receipt_id;
				$arr_detail['company_id'] = getCompanyId();
				$arr_detail['user_id'] = getUSerId();
				$arr_detail['total_quantity'] = $_POST['tot_quantity'];
				$sr_no_1 = $_POST['sr_no'][$i];
				$arr_detail['customer_id'] = $customer_id;

				if ($_POST['sr_no'][$i] != 0) {
					$receipt_trans_id = $db->insert($arr_detail, 's_invoice_transaction');
					$result = $receipt_trans_id;
					if (isset($_REQUEST['Sub_item_id' . $sr_no]) && $_REQUEST['Sub_item_id' . $sr_no] != '') {
						foreach ($_REQUEST['Sub_item_id' . $sr_no] as $subItemIndex => $subItemId) {
							$sb_item = $db->fetch_array_by_query("select * from sub_item where id=" . $subItemId);
							$finishing_type = $sb_item["finishing_type"];
							$receipt_inv = array();
							$receipt_inv['receipt_id'] = $receipt_id;
							$receipt_inv['receipt_transaction_id'] = $receipt_trans_id;
							$receipt_inv['item_id'] = $_POST['item_name'][$i];
							$receipt_inv['finishing_type'] = $finishing_type;
							$receipt_inv['brand_id'] = $_POST['brand'][$i];
							$receipt_inv['item_group_id'] = $_POST['item_group'][$i];
							$receipt_inv['rate'] = $_POST['rate'][$i];
							$receipt_inv['sub_item_id'] = $subItemId;
							$receipt_inv['quality_id'] = intval($_POST['quality'][$i]);
							$receipt_inv['color_id'] = intval($_POST['Sub_item_color' . $sr_no][$subItemIndex]);
							$receipt_inv['article_id'] = intval($_POST['Sub_item_article' . $sr_no][$subItemIndex]);
							$receipt_inv['warehouse_id'] = intval($_POST['Sub_item_warehouse' . $sr_no][$subItemIndex]);
							$receipt_inv['section_id'] = intval($_POST['Sub_item_section' . $sr_no][$subItemIndex]);
							$receipt_inv['inv_quantity'] = $_POST['Sub_item_quantity' . $sr_no][$subItemIndex];
							if (isset($_POST['Sub_item_value' . $sr_no][$subItemIndex]) && $_POST['Sub_item_value' . $sr_no][$subItemIndex] != '') {
								$receipt_inv['value'] = $_POST['Sub_item_value' . $sr_no][$subItemIndex];
							} else {
								$receipt_inv['value'] = 0;
							}

							if (isset($_POST['Sub_item_unit' . $sr_no][$subItemIndex]) && $_POST['Sub_item_unit' . $sr_no][$subItemIndex] != '') {
								$receipt_inv['unit'] = $_POST['Sub_item_unit' . $sr_no][$subItemIndex];
							} else {
								$receipt_inv['unit'] = 'sqm';
							}

							if (isset($_POST['Sub_item_boxes' . $sr_no][$subItemIndex]) && $_POST['Sub_item_boxes' . $sr_no][$subItemIndex] != '') {
								$receipt_inv['boxes'] = $_POST['Sub_item_boxes' . $sr_no][$subItemIndex];
							} else {
								$receipt_inv['boxes'] = 0;
							}

							if (isset($_POST['Sub_item_tons' . $sr_no][$subItemIndex]) && $_POST['Sub_item_tons' . $sr_no][$subItemIndex] != '') {
								$receipt_inv['tons'] = $_POST['Sub_item_tons' . $sr_no][$subItemIndex];
							} else {
								$receipt_inv['tons'] = 0;
							}
							$receipt_inv['inv_bilty_no'] = intval($_POST['serial_no'][$i]);
							$receipt_inv['location_id'] = intval($_POST['cr_location']);
							$receipt_inv['sub_location_id'] = intval($_POST['cr_sublocation']);
							if (isset($_REQUEST['sale_type']) && $_REQUEST['sale_type'] == 'company') {
								$receipt_inv['debit_ledger_id'] = intval($_POST['dr_project']);
							} else {
								$receipt_inv['debit_ledger_id'] = intval($_POST['customer_id']);
							}
							$receipt_inv['credit_ledger_id'] = intval($_POST['project_ledger']);
							$receipt_inv['created_at'] = time();
							$receipt_inv['updated_at'] = time();
							$receipt_inv['company_id'] = getCompanyId();
							$receipt_inv['user_id'] = getUSerId();
							$receipt_inventory_id = $db->insert($receipt_inv, 'sale_invoice_inventory');
							$result = $receipt_inventory_id;
						}
					}
				}
			}
			if (isset($receipt_trans_id)) {
				MakeCommissionVoucher($arr);
				$voucher_id = makePurchaseReceiptVoucher($arr, $_POST['project_ledger']);
				$result = makeInvoiceTransactions($voucher_id, $arr, $general_head['value'], $general_subhead['value']);
			}
		}
		if (isset($_REQUEST['sale_type']) && $_REQUEST['sale_type'] == 'company') {
			$customer = $_POST['dr_project'];
		} else {
			$customer = $_POST['customer_id'];
		}
		$qry = "select * from sale_invoice where customer_id = " . $customer;
		if ($_POST['walk_in'] > 0) {
			$qry .= ' and sub_ledger_id = ' . $_POST['walk_in'];
		}
		$db->Select($qry);
		$sales = $db->fetch_all();
		foreach ($sales as $s) {
			if ($s['id'] == $receipt_id) {
				continue;
			}
			$db->update($s['id'], array('payment_status' => 'paid'), 'sale_invoice');
		}
	}
	if ($result) {
		$obj_msg = load_class('InfoMessages');
		$imsg->setMessage('Added Successfully!');
		redirect_header(ADMIN_URL . 'sale/sale-invoice.php');
	} else {
		$obj_msg = load_class('InfoMessages');
		$imsg->setMessage('Error Occur. Please try again later.', 'error');
		redirect_header(ADMIN_URL . 'sale/sale-invoice.php');
	}
}

function makePurchaseReceiptVoucher($receipt_arr, $project_id)
{
	global $db;
	$images_arr = array();
	$voucher_last = $db->fetch_array_by_query("select * from voucher where company_id=" . getCompanyId() . " and type='s_invoice'  ORDER BY ID DESC LIMIT 1");
	$voucher_no = $voucher_last['voucher_no'] + 1;
	$voucher_arr = array();
	$voucher_arr['voucher_series'] = 'si';
	$voucher_arr['voucher_no'] = voucherNumber('s_invoice');
	$voucher_arr['date'] = strtotime($_POST['invoice_date']);
	$voucher_arr['type'] = 's_invoice';
	$voucher_arr['company_id'] = getCompanyId();
	$voucher_arr['user_id'] = getUserId();
	$voucher_arr['created_at'] = strtotime($_POST['invoice_date']);
	$voucher_arr['updated_at'] = time();
	$voucher_arr['total_amount'] = $receipt_arr['grand_total'];
	$voucher_arr['sale_invoice'] = $receipt_arr['id'];
	$voucher_arr['attachment'] = $receipt_arr['attachment'];
	$voucher_arr['narration'] = $_POST['narration'];
	$voucher_id = $db->insert($voucher_arr, 'voucher');
	return $voucher_id;
}

function MakeCommissionVoucher($receipt_arr)
{
	global $db;
	if ((isset($receipt_arr['total_commission']) && intval($receipt_arr['total_commission']) > 0) && $receipt_arr['commission'] == 'yes') {
		$images_arr = array();
		$voucher_last = $db->fetch_array_by_query("select * from voucher where company_id=" . getCompanyId() . " and type='commission' ORDER BY ID DESC LIMIT 1");
		$voucher_no = $voucher_last['voucher_no'] + 1;
		$voucher_arr = array();
		$voucher_arr['voucher_series'] = 'voc';
		$voucher_arr['voucher_no'] = voucherNumber('commission');
		$voucher_arr['date'] = time();
		$voucher_arr['type'] = 'commission';
		$voucher_arr['company_id'] = getCompanyId();
		$voucher_arr['user_id'] = getUserId();
		$voucher_arr['created_at'] = time();
		$voucher_arr['updated_at'] = time();
		$voucher_arr['total_amount'] = $receipt_arr['total_commission'];
		$voucher_arr['sale_invoice'] = $receipt_arr['id'];
		$voucher_arr['attachment'] = $receipt_arr['attachment'];
		$voucher_arr['narration'] = $_POST['narration'];
		$c_voucher_id = $db->insert($voucher_arr, 'voucher');

		$cr_transaction = array();
		$commission_ledger = getSettingRow("commision_ledger");
		$cr_transaction['ledger_id'] = $commission_ledger['value'];
		$cr_transaction['voucher_id'] = $c_voucher_id;
		$cr_transaction['description'] = $receipt_arr['narration'];
		$cr_transaction['type'] = 'debit';
		$cr_transaction['amount'] = intval($_POST['total_commission']);
		$cr_transaction['company_id'] = getCompanyId();
		$cr_transaction['user_id'] = getUSerId();
		$cr_transaction['created_at'] = time();
		$cr_transaction['updated_at'] = time();
		$cr_transaction['location_id'] = intval($_POST['cr_location']);
		$cr_transaction['sub_location_id'] = intval($_POST['cr_sublocation']);
		$cr_transaction_id = $db->insert($cr_transaction, 'transactions');

		$dr_transaction = array();
		$dr_transaction['ledger_id'] = intval($receipt_arr['sales_person']);
		$dr_transaction['voucher_id'] = $c_voucher_id;
		$dr_transaction['description'] = $receipt_arr['narration'];
		$dr_transaction['type'] = 'credit';
		$dr_transaction['amount'] = intval($_POST['total_commission']);
		$dr_transaction['company_id'] = getCompanyId();
		$dr_transaction['user_id'] = getUSerId();
		$dr_transaction['created_at'] = time();
		$dr_transaction['updated_at'] = time();
		$dr_transaction['location_id'] = intval($_POST['cr_location']);
		$dr_transaction['sub_location_id'] = intval($_POST['cr_sublocation']);
		$dr_transaction_id = $db->insert($dr_transaction, 'transactions');

		return true;
	}
}
function makeInvoiceTransactions($voucher_id, $receipt_arr, $head, $subhead)
{
	global $db;
	$db->select("select * from s_invoice_transaction where company_id=" . getCompanyId() . " and s_i_id = " . $receipt_arr['id']);
	$s_invoice_transactions = $db->fetch_all();
	$rate = 0;
	$transaction_arr = array();
	$total_amount =  $receipt_arr['total_amount'];
	$sum_amu = 0;
	$sum_disc = 0;
	foreach ($_REQUEST['amount'] as $amount) {
		$sum_amu += $amount;
	}
	foreach ($_REQUEST['hidden_disc_amt_val'] as $hidden_disc_amt_val) {
		$sum_disc += $hidden_disc_amt_val;
	}


	if ($s_invoice_transactions && ($receipt_arr['discount_mode'] == 'enable')) {
		if ($sum_disc > 0) {
			$discount_ledger = getSettingRow("discount_ledger");
			$transaction_arr = array();
			$transaction_arr['ledger_id'] = $discount_ledger['value'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description'] = $receipt_arr['narration'];
			$transaction_arr['type'] = 'debit';
			$transaction_arr['amount'] = $sum_disc;
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			$transaction_arr['location_id'] = intval($_POST['cr_location']);
			$transaction_arr['sub_location_id'] = intval($_POST['cr_sublocation']);
			$cr_transaction_id = $db->insert($transaction_arr, 'transactions');
		}
	}



	if ($s_invoice_transactions) {
		$amu = $sum_amu;
		if ($sum_disc > 0) {
			$amu = $sum_amu + $sum_disc;
		}
		$transaction_arr = array();
		$transaction_arr['ledger_id'] = $receipt_arr['project_id'];
		$transaction_arr['voucher_id'] = $voucher_id;
		$transaction_arr['description'] = $receipt_arr['narration'];
		$transaction_arr['type'] = 'credit';
		$transaction_arr['amount'] = $amu;
		$transaction_arr['company_id'] = getCompanyId();
		$transaction_arr['user_id'] = getUSerId();
		$transaction_arr['created_at'] = time();
		$transaction_arr['updated_at'] = time();
		$transaction_arr['location_id'] = intval($_POST['cr_location']);
		$transaction_arr['sub_location_id'] = intval($_POST['cr_sublocation']);
		$cr_transaction_id = $db->insert($transaction_arr, 'transactions');
	}
	if ($receipt_arr['sale_type'] == 'company') {
		$transaction_arr = array();
		$transaction_arr['ledger_id'] = $_POST['dr_project'];
		$transaction_arr['voucher_id'] = $voucher_id;
		$transaction_arr['description'] = $receipt_arr['narration'];
		$transaction_arr['type'] = 'debit';
		$transaction_arr['amount'] = $sum_amu;
		$transaction_arr['company_id'] = getCompanyId();
		$transaction_arr['user_id'] = getUSerId();
		$transaction_arr['created_at'] = time();
		$transaction_arr['updated_at'] = time();
		$transaction_arr['location_id'] = intval($_POST['debit_head']);
		$transaction_arr['sub_location_id'] = intval($_POST['debit_subhead']);
		$cr_transaction_id = $db->insert($transaction_arr, 'transactions');
	} else {
		if ($s_invoice_transactions) {
			if ($sum_amu > 0) {
				$transaction_arr['ledger_id'] = $receipt_arr['customer_id'];
				$transaction_arr['voucher_id'] = $voucher_id;
				$transaction_arr['description'] = $receipt_arr['narration'];
				$transaction_arr['type'] = 'debit';
				$transaction_arr['amount'] = $sum_amu;
				$transaction_arr['company_id'] = getCompanyId();
				$transaction_arr['user_id'] = getUSerId();
				$transaction_arr['created_at'] = time();
				$transaction_arr['updated_at'] = time();
				$transaction_arr['sub_ledger_id'] =  $receipt_arr['sub_ledger_id'];
				$transaction_arr['location_id'] = intval($head);
				$transaction_arr['sub_location_id'] = intval($subhead);
				$dr_transaction_id = $db->insert($transaction_arr, 'transactions');
			}
		}
	}
	return true;
}



$page_title = "";
$tab = "Sale Invoice";
?>
<!DOCTYPE html>
<html>

<head>
	<?php include("../includes/common-header.php"); ?>
	<link rel="stylesheet" href="<?php echo BASE_URL; ?>css/voucher.css?v=7.3" type="text/css" />
	<style type="text/css">
		.modal-href {
			display: inline-table;
		}

		.fa {
			font-size: 16px
		}

		.discount,
		#delivery_date {
			background: white !important;
			color: black !important;
			border: 1px solid #bbbbbb !important;
			padding: 7px 7px !important
		}

		@media only screen and (max-width: 600px) and (min-width: 300px) {
			.modal-href {
				display: inherit !important;
			}

			.table .form-control {
				width: 200px;
			}
		}

		@media only screen and (min-width: 1450px) {
			.modal-href {
				display: block !important;
				margin: 16px auto;
			}
		}

		@media only screen and (min-width: 1450px) {
			.modal-href {
				display: block !important;
				margin: 16px auto;
			}
		}

		.loding_div {
			position: fixed;
			top: 0px;
			bottom: 0px;
			left: 0px;
			right: 0px;
			overflow-x: auto;
			text-align: center;
			overflow-y: auto;
			z-index: 1050;
			/* background: rgba(0,0,0,0.5); */
		}

		.loding_div img {
			width: 13%;
			overflow: auto;
			top: 33%;
			bottom: 50%;
			position: absolute;
			left: 50%;
			right: 50%;
		}

		.walk_in_text_show {
			margin-top: 25px
		}

		@media only screen and (min-width:786px) {
			.modal-rate {
				width: 90%;
			}
		}

		@media only screen and (min-width:787px) {
			.vl1 .form-control {
				width: 80%;
			}
		}

		@media only screen and (min-width:787px) {
			#sliderModal .vl1 .row {
				padding-left: 10% !important;
			}
		}

		.commission_check {
			margin-top: 32px !important
		}

		.vl {
			border-left: 4px solid green;
			height: 170px;
		}

		.radio-inline {
			margin: 0 !important
		}

		.vl1 {
			border-radius: 4px;
			border-bottom: 4px solid green;
			width: 100%;
			border-right: 4px solid green;
			border-left: 4px solid green;
		}

		@media only screen and (max-width:480px) {
			button {
				width: 98% !important;
				display: block;
			}

			.vl {
				border-left: 4px solid green;
				height: 20px !important;
				margin-left: 50%;
			}
		}

		.radio-inline {
			margin: 0 !important
		}

		@media screen and (min-width: 1370px) and (max-width: 1600px) {
			.radio-inline {
				margin-left: 10px !important
			}

		}

		@media (min-width:768px) and (max-width:992px) {

			.table-responsive>.table>tbody>tr>td,
			.table-responsive>.table>tbody>tr>th,
			.table-responsive>.table>tfoot>tr>td,
			.table-responsive>.table>tfoot>tr>th,
			.table-responsive>.table>thead>tr>td,
			.table-responsive>.table>thead>tr>th {
				white-space: nowrap;
			}

			.table-responsive>.table {
				margin-bottom: 0;

			}

			.table-responsive {
				width: 100% !important;
				margin-bottom: 15px !important;
				overflow-y: hidden !important;
				-ms-overflow-style: -ms-autohiding-scrollbar;
				border: 1px solid #ddd !important;
			}

			.table {
				width: 2000px !important;
				max-width: inherit !important !important
			}

		}

		.lb_top {
			padding-top: 10px;
		}

		.P_b {
			border-bottom-left-radius: 0px !important
		}

		.P_b {
			border-top-left-radius: 0px !important
		}

		.pv_b {
			border-bottom-right-radius: 0px !important
		}

		.pv_b {
			border-top-right-radius: 0px !important
		}

		.chosen-container-single {
			width: 0px
		}

		.input-group .input-group-addon.pq {
			width: 20%;
		}

		.foot,
		.foot>tr,
		.foot>tr>td {
			border: none !important;
		}

		.charges_row {
			background-color: #f9f9f9
		}

		.border-t0 {
			border-bottom: 0 !important;
		}

		.border-b0 {
			vertical-align: middle !important;
			border-bottom: 0 !important
		}

		@media (min-width:992px) and (max-width:1200px) {

			.table-responsive>.table>tbody>tr>td,
			.table-responsive>.table>tbody>tr>th,
			.table-responsive>.table>tfoot>tr>td,
			.table-responsive>.table>tfoot>tr>th,
			.table-responsive>.table>thead>tr>td,
			.table-responsive>.table>thead>tr>th {
				white-space: nowrap;

			}

			.table-responsive>.table {
				margin-bottom: 0;

			}

			.table-responsive {
				width: 100% !important;
				margin-bottom: 15px !important;
				overflow-y: hidden !important;
				-ms-overflow-style: -ms-autohiding-scrollbar;
				border: 1px solid #ddd !important;
			}

			.table {
				width: 2000px !important;
				max-width: inherit !important !important
			}
		}
	</style>
</head>

<body class="skin-green-light sidebar-mini">
	<div class="wrapper">
		<?php include("../includes/header.php"); ?>
		<div class="content-wrapper">
			<section class="content-header">
				<h1>
					<?php echo ucfirst($tab); ?>
					<span class="small">Add</span>
				</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo ADMIN_URL; ?>"><i class="fa fa-dashboard"></i> Home</a></li>
					<li class="active"><?php echo $page_title; ?></li>
				</ol>
			</section>
			<?php $db->select("SELECT * FROM `ledger` where active_project='yes' and company_id=" . getCompanyId() . " order by id desc");
			$ledgers = $db->fetch_all(); ?>
			<form method="post" enctype="multipart/form-data" id='invoiceForm' name="form" autocomplete="off">
				<input type="hidden" name="command" value="add">
				<input type="hidden" class="order_customer" value="<?php echo $pi_row['customer_id']; ?>">
				<div class="append_image"></div>
				<section class="content">
					<div class="row">
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="row clearfix">
								<div class="span12">
									<?php echo $imsg->getMessage(); ?>
								</div>
							</div>
							<div class="box box-danger">
								<div class="box-body">
									<div class=" lb_top">
										<div class="col-lg-2 col-md-4">
											<div class="form-group">
												<label> Sale Invoice No: </label>
												<div class="input-group">
													<span class="input-group-addon pq">SI</span>
													<input type="hidden" name="purch_invoice_series" value="<?php echo "SI" ?>">
													<input type='hidden' name='sub_ledg' class='sub_ledg'>
													<input id="msg" type="text" class="form-control" placeholder=" Invoice No." name="purch_invoice_no" value="<?php echo InvoiceNo() ?>" readonly>
												</div>
											</div>
										</div>
										<?php
										$date =  strtotime(date('d-m-Y'));
										$day_value = date('l', $date); ?>
										<div class="col-lg-2">
											<div class="form-group">
												<label> Date & Day: </label>
												<div class="input-group">
													<span class="input-group-addon" style="width: 50%;padding: 0;">
														<input type="text" class="form-control  quotation-date" placeholder=" Date " value="<?php echo date('d-m-Y') ?>" name="invoice_date" autocomplete="off" readonly>
													</span><input type="text" class="form-control input-group-addon" placeholder=" Day" id="weekDay" name="invoice_day" value="<?php echo $day_value ?>" readonly>
												</div>
											</div>
										</div>
										<div class=" col-lg-2 col-md-2  location_div">
											<div class="form-group">
												<label> Head </label>
												<select class="transporter location_select proj_location chosen-select location_id" onchange="get_sub_location(this)" name="cr_location" id="location_id" required="true">
													<option selected>Select Head</option>
													<?php
													if (isset($_REQUEST['so_id']) && intval($_REQUEST['so_id']) > 0) {
														$db->select("Select * from item_location where id = " . $pi_row['location_id']);
													} else {
														$db->select('SELECT * FROM item_location where FIND_IN_SET(' . getCompanyId() . ',company_id)');
													}
													$locationResults = $db->fetch_all();
													$heads = explode(',', $auth_row['head_id']);
													foreach ($locationResults as $locationResult) {
														$select = "";
														if (!checkUserloc($locationResult['id'], 'head')) {
															continue;
														}
														if (!empty($pi_row)) {
															if ($pi_row['location_id'] == $locationResult['id']) {
																$select = 'selected';
															}
														} else {
															if ($locationResult['id'] == $heads[0]) {
																$select = 'selected';
															}
														}
													?>
														<option <?php echo $select; ?> value="<?php echo $locationResult['id'] ?>"><?php echo $locationResult['name'] ?></option>
													<?php }  ?>
												</select>
											</div>
										</div>
										<div class=" col-lg-2 col-md-2 location_div">
											<div class="form-group">
												<label> Sub Head </label>
												<select name="cr_sublocation" class=" form-control chosen-select sub_location" id="sub_location_id" style="z-index:99999">
													<option value="">Select Sub Head</option>
													<?php
													if (isset($_REQUEST['so_id']) && intval($_REQUEST['so_id']) > 0) {
														$db->select("Select * from item_sublocation where id = " . $pi_row['sub_location_id']);
													} else {
														$db->select('SELECT * FROM item_sublocation where FIND_IN_SET(' . getCompanyId() . ',company_id) and location_id=' . $heads[0]);
													}
													$sublocationResults = $db->fetch_all();
													$subheads = explode(',', $auth_row['subhead_id']);
													foreach ($sublocationResults as $sublocationResult) {
														$select = "";
														if (!checkUserloc($sublocationResult['id'], 'subhead')) {
															continue;
														}
														if (!empty($pi_row)) {
															if ($pi_row['sub_location_id'] == $sublocationResult['id']) {
																$select = 'selected';
															}
														} else {
															if ($sublocationResult['id'] == $subheads[0]) {
																$select = 'selected';
															}
														}
													?>
														<option <?php echo $select; ?> value="<?php echo $sublocationResult['id'] ?>"><?php echo $sublocationResult['name'] ?></option>
													<?php } ?>
												</select>
											</div>
										</div>

										<div class="col-md-3">
											<label> Branch </label>
											<select class="chosen-select " name="branch" required="true">
												<option selected>Select Branch</option>
												<?php
												$db->select("SELECT * FROM branches");
												$branch_data = $db->fetch_all();
												// $select = "";
												foreach ($branch_data as $branch) { ?>
													<option value="<?php echo $branch['id'] ?>"><?php echo $branch['branch_name'] ?></option>
												<?php } ?>
											</select>
										</div>

										<div class="col-lg-2 col-md-2 no-gutter" style="margin-top: 20px;">
											<div class="col-lg-2 col-md-2">
												<label><input type="checkbox" onclick="<?php if (isset($_REQUEST) && $_REQUEST['agent'] != '') { ?> return false <?php } else { ?> EnableCommission(this)<?php } ?>" name="commission_check" class="commission_check" value="yes" <?php if (isset($_REQUEST) && $_REQUEST['agent'] != '') {
																																																																						echo "checked";
																																																																					} ?>></label>
											</div>
											<div class="col-lg-10 col-md-10 sales_person_div">
												<div class="form-group">
													<label> Sales Person </label>
													<select <?php if (isset($_REQUEST) && $_REQUEST['agent'] != '') {
															} else { ?> disabled <?php } ?> class="form-control chosen-transporter sales_person" name="sales_person" required="true" onchange="checkWalkInCustomer(this)">
														<?php
														if (isset($_REQUEST['agent'])) {
															$sales_person_ledger = $db->fetch_array_by_query("select * from sub_ledgers where id = " . $_REQUEST['agent']);
														} else {
															$sales_person = getSettingRow("sales_person_ledger");
															$sales_person_ledger = $db->fetch_array_by_query("select * from ledger where id = " . $sales_person['value']);
														}

														?>
														<option selected value="<?php echo $sales_person_ledger['id']; ?>"> <?php echo $sales_person_ledger['name']; ?></option>
													</select>
												</div>
											</div>
										</div>
										<div class="col-lg-2 col-md-2 no-gutter" style="margin-top: 20px;">
											<div class="form-group col-sm-9 no-gutter">
												<label>Payment Mode</label>
												<select name="payment_type" onchange="PaymentType(this)" class="form-control chosen-select payment_type">
													<option value="cash">Cash</option>
													<option value="due">Due</option>
													<option value="adjustment">Adjustment</option>
												</select>
											</div>
										</div>
										<div class="clearfix"></div>
									</div>
									<div class="col-sm-12 no-gutter">
										<div class="col-md-9" style="padding-left:0">
											<div class="col-md-2 ">
												<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content;opacity: 1"> Sale: </h4>
												<div class="form-group">
													<label>Sale Type</label>
													<select name="sale_type" onchange="CompanyTransactions(this)" class="form-control chosen-select sale_type" id="c-type">
														<option <?php if ($pi_row['sale_type'] == 'retail_sale') {
																	echo "selected";
																} ?> value="customer">Retail</option>
														<option <?php if ($pi_row['sale_type'] == 'whole_sale') {
																	echo "selected";
																} ?> value="whole_sale">Whole Sale</option>
														<option <?php if ($pi_row['sale_type'] == 'company_sale') {
																	echo "selected";
																} ?> value="company">Company</option>
														<option <?php if ($pi_row['sale_type'] == 'distributer') {
																	echo "selected";
																} ?> value="distributer">Distributer</option>
														<option <?php if ($pi_row['sale_type'] == 'project') {
																	echo "selected";
																} ?> value="project">Project</option>
														<option <?php if ($pi_row['sale_type'] == 'agent') {
																	echo "selected";
																} ?> value="agent">Agent</option>
													</select>
												</div>
											</div>
											<div class="col-md-7 customer_div" <?php if ($pi_row['sale_type'] == 'company_sale') { ?>style="display: none" <?php } ?>>
												<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content;"> Debit : </h4>
												<div class=" customer form-group col-md-4 col-xs-12 no-gutter" style="padding-left: 0PX">
													<label> Customer </label>
													<select class="form-control chosen-transporter customer_id inv_transporter_id" name="customer_id" required="true" onchange="checkWalkInCustomer(this)">
														<option value="0"> Select Customer </option>
														<?php
														if ($pi_row['sale_type'] == 'distributer') {
															$db->Select("Select * from ledger where active_distributer = 'yes' and FIND_IN_SET('" . $pi_row['location_id'] . "',distributer_head) and FIND_IN_SET('" . $pi_row['sub_location_id'] . "',distributer_subhead)");
														} else {
															$db->select("select * from ledger where (active_customer='yes' || active_walk_in_customer='yes') and FIND_IN_SET(" . getCompanyId() . ",company_id)");
														}

														$transporters = $db->fetch_all();
														$select = "";
														foreach ($transporters as $transporter) {
															$select = '';
															if ($transporter['id'] == $pi_row['customer_id']) {
																$select = 'selected';
															}
														?>
															<option data-credit="<?php echo  $transporter['credit_limit_acitve']; ?>" data-cvalue="<?php echo  $transporter['credit_limit']; ?>" data-active-walk-in="<?php echo $transporter['active_walk_in_customer']; ?>" <?php echo $select; ?> value="<?php echo $transporter['id']; ?>"> <?php echo $transporter['name']; ?></option>
														<?php } ?>
													</select>
												</div>
												<div class="col-md-4 walk_in_button_show" <?php if ($pi_row['sub_ledger_id'] <= 0 || empty($pi_row)) { ?> style="display:none" <?php } ?>>
													<label>Sub Ledger</label>
													<div class="input-group">
														<select class="chosen-select walk_in" onchange="getBalance(this,'subledger')" name="walk_in">
															<option value="0">Select Sub Ledger</option>
															<?php
															$db->Select("Select * from sub_ledgers where ledger_id = " . $pi_row['customer_id']);
															$sub_ledgers = $db->fetch_all();
															foreach ($sub_ledgers as $s_ledger) {
															?>
																<option data-credit="<?php echo  $transporter['credit_limit_acitve']; ?>" data-cvalue="<?php echo  $s_ledger['credit_limit']; ?>" <?php if ($s_ledger['id'] == $pi_row['sub_ledger_id']) {
																																																		echo "selected";
																																																	} ?> value="<?php echo $s_ledger['id']; ?>"><?php echo $s_ledger['name']; ?></option>
															<?php
															}
															?>
														</select>
														<span class="input-group-addon no-gutter" style="border:none">
															<a class="btn btn-default" href="<?php echo ADMIN_URL . 'add-sub-ledger.php'; ?>"><i class="fa fa-plus"></i></a>
														</span>
													</div>
												</div>
												<div class="db_balance">
													<?php
													if (isset($pi_row)) {
														if ($pi_row['sub_ledger_id'] != '') {
															$balance = getBalance($pi_row['sub_ledger_id'], 'sub_ledgers');
														} else {

															$balance = getBalance($pi_row['customer_id'], 'ledger');
														}
													}
													?>
													<input type="text" class="ledger_balance" value="<?php echo $balance['balance'] . ' ' . $balance['type']; ?>">
												</div>
											</div>
											<div class="col-md-4 hidden no-gutter">
												<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content;"> Credit : </h4>
												<div class="col-md-6 no-gutter">
													<div class="form-group col-xs-12 no-gutter" style="padding-left: 0PX">
														<label> Project </label>
														<select class="form-control chosen-select" name="project_ledger" required="true">
															<?php
															$ledger_row = getSettingRow("sales_ledger");
															$sales_ledger = $db->fetch_array_by_query("Select * from ledger where id=" . $ledger_row['value']);
															?>
															<option selected value="<?php echo $sales_ledger['id']; ?>"> <?php echo $sales_ledger['name']; ?></option>
														</select>
													</div>
												</div>
												<div class="single-party-div col-md-6">
													<div class="form-group col-md-12 col-xs-12 no-gutter">
														<label> Third Party </label>
														<select class="form-control chosen_party" name="third_party_single" required="true">
															<option value="0" selected=""> Select Third Party </option>
															<?php
															$db->select("select * from ledger where third_party='yes' and company_id=" . getCompanyId() . ' order by id desc');
															$third_parties = $db->fetch_all();
															foreach ($third_parties as $third_partie) {
																if ($third_partie['id'] == $po_row['third_party']) {
																	$select = "selected";
																} else {
																	$select = "";
																} ?>
																<option value="<?php echo $third_partie['id']; ?>" <?php echo $select; ?>> <?php echo $third_partie['name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
											</div>
											<div class="col-md-7 company_sale_div <?php if ($pi_row['sale_type'] != 'company_sale') { ?>hidden<?php } ?>  ">
												<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content;"> Debit : </h4>
												<div class="col-md-4 no-gutter">
													<div class="form-group col-xs-12 no-gutter" style="padding-left: 0PX">
														<label>Project </label>
														<select class="form-control chosen-select" name="dr_project" required="true">
															<?php
															$sales_ledger = $db->fetch_array_by_query("Select * from ledger where id=2253");
															?>
															<option selected value="<?php echo $sales_ledger['id']; ?>"> <?php echo $sales_ledger['name']; ?></option>
														</select>
													</div>
												</div>
												<div class="single-party-div col-md-8">
													<div class="form-group col-md-6 col-xs-6">
														<label> Head </label>
														<select class="form-control chosen-select debit_head" onchange="getDebitSubLoc(this)" name="debit_head" required="true">
															<option value="0" selected=""> Select Head </option>
															<?php
															$db->select('select * from item_location where FIND_IN_SET(' . getCompanyId() . ',company_id) order by id desc');
															$locs = $db->fetch_all();
															foreach ($locs as $loc) {
															?>
																<option <?php if ($pi_row['debit_head'] == $loc['id']) {
																			echo "selected";
																		} ?> value="<?php echo $loc['id']; ?>" <?php echo $select; ?>> <?php echo $loc['name']; ?></option>
															<?php } ?>
														</select>
													</div>
													<div class="form-group col-md-6 col-xs-6 no-gutter">
														<label> Sub Head </label>
														<select class="form-control chosen-select debit_subhead" name="debit_subhead" required="true">
															<option value="0" selected=""> Select Sub Head </option>
															<?php
															$db->select('select * from item_sublocation where FIND_IN_SET(' . getCompanyId() . ',company_id) and location_id = ' . $pi_row['debit_head'] . ' order by id desc');
															$sublocs = $db->fetch_all();
															foreach ($sublocs as $sloc) {
															?>
																<option <?php if ($pi_row['debit_subhead'] == $sloc['id']) {
																			echo "selected";
																		} ?> value="<?php echo $sloc['id']; ?>" <?php echo $select; ?>> <?php echo $sloc['name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
											</div>
											<div class="col-md-3 hidden install_div">
												<button class="btn btn-primary" style="margin-top:66px" type="button" data-toggle="modal" data-target=".Installments">Installments</button>
											</div>
											<div class="clearfix"></div>
										</div>
										<!-- </div> -->
										<div class="col-sm-3 <?php if (!empty($pi_row)) {
																	echo 'hidden';
																} ?> " style="float:right">
											<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content; margin-top:0px;margin-bottom:5px"> Select Modes: </h4>
											<div class="col-md-12">
												<input type="hidden" name="vehicle_mode" value="single_vehicle">
												<div class="hidden">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content;"> Vehicles </h5>
													<label class="radio-inline"><input type="radio" class="vehicle_mode" id="single_veh" value="single_vehicle" name="vehicle_mode"> Single Vehicle</label>
													<label class="radio-inline"><input type="radio" class="vehicle_mode" id="multiple_veh" name="vehicle_mode" value="multiple_vehicle" checked> Multiple Vehicle</label>
												</div>
												<div class="discount_with_vehicle col-md-6 no-gutter">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content; margin-top:5px;margin-bottom:5px"> Discount </h5>
													<label class="radio-inline"><input <?php if ($pi_row['discount_mode'] == 'enable') {
																							echo 'checked';
																						} ?> type="radio" value="enable" id="enable_dis" name="discount_mode"> Enable</label>
													<br>
													<label class="radio-inline"><input <?php if ($pi_row['discount_mode'] == 'disable') {
																							echo 'checked';
																						} else if (empty($pi_row)) {
																							echo "checked";
																						} ?> type="radio" name="discount_mode" id="disable_dis" value="disable"> Disabled</label>
												</div>
												<div class="col-md-6 hidden no-gutter">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content; margin-top:5px;margin-bottom:5px"> Third Party </h5>
													<label class="radio-inline"><input type="radio" value="single_party" id="single_party_check" name="ledger_party" checked=""> Single </label>
													<br>
													<label class="radio-inline"><input type="radio" name="ledger_party" value="multiple_party"> Multiple </label>
												</div>
												<div class="col-md-6 hidden no-gutter">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content; margin-top:5px;margin-bottom:5px"> Commission </h5>
													<label class="radio-inline"><input type="radio"> Enable </label>
													<!-- value="enable" name="commission_check" -->
													<br>
													<label class="radio-inline"><input type="radio"> Disable </label>
													<!-- name="commission_check" value="disable" -->
												</div>
											</div>
										</div>
									</div>
									<div class="clearfix" style="margin-bottom: 15px"></div>
									<div class="table-responsive www">
										<div class="loding_div" style="display: none;">
											<img src="https://salessgc.com/images/loading.gif">
										</div>
										<table class="table table-striped pq_table">
											<thead>
												<tr class="title_bg">
													<th rowspan="2" class="border-b0">Sr #</th>
													<th rowspan="2" class="border-b0">Item Group</th>
													<th rowspan="2" class="border-b0">Item Name</th>
													<th rowspan="2" class="border-b0">Brand</th>
													<th rowspan="2" class="border-b0">Finishing Type</th>
													<th rowspan="2" class="border-b0">Quality</th>
													<th class="border-t0" colspan="2">Quantity</th>
													<th colspan="<?php if ($pi_row['discount_mode'] == "enable") {
																		echo '3';
																	} else if ($pi_row['discount_mode'] == "disable" || empty($pi_row)) {
																		echo "2";
																	} ?>" class="border-b0 rate_td">Rate</th>
													<th rowspan="2" class="hidden commission_heading border-b0">Commission</th>
													<th rowspan="2" class="border-b0">Amount</th>
													<?php if (!isset($_REQUEST['so_id'])) { ?>
														<th rowspan="2" class="border-b0">Action</th>
													<?php }  ?>
												</tr>
												<tr class="title_bg">
													<th>Sq.M</th>
													<th>Boxes</th>
													<th class="border-b0">Actual</th>
													<th class="<?php if ($pi_row['discount_mode'] == "disable") {
																	echo 'hidden';
																} else if (empty($pi_row)) {
																	echo "hidden";
																} ?>  discount_heading border-b0">Discount</th>
													<th class="border-b0">Effective</th>
													<th class="hidden">Tons</th>
												</tr>
											</thead>
											<tbody class="pq_body w-sm">
												<?php
												$db->select("SELECT * FROM s_order_transaction WHERE order_id='" . $id . "' and company_id=" . getCompanyId() . " ORDER BY id ASC");
												$purchaseDetails = $db->fetch_all();
												// print_r($purchaseDetails);
												$total_tons = 0;
												$total_boxes = 0;
												if ($purchaseDetails) {
													foreach ($purchaseDetails as $purchaseDetail) {
														$sr_no = $sr_no + 1;
														$total_tons += $purchaseDetail['tons'];
														$total_boxes += $purchaseDetail['boxes'];
														$itemSymbol = $db->fetch_array_by_query("SELECT iu.*, iu.symbol FROM item_unit as iu ,s_invoice_transaction as pi,item as u where u.id=" . $purchaseDetail['item_id'] . " and u.unit_id = iu.id and iu.company_id=" . getCompanyId() . " group by id");
														$item = $db->fetch_array_by_query("select * from item where id=" . $purchaseDetail['item_id']);
														$alternate_unit = $db->fetch_array_by_query("Select * from item_unit where id=" . $item['alternative_unit_id']);
														$contrat = $db->fetch_array_by_query("select * from s_contract_transaction as pt, sale_contract as p where p.company_id=" . getCompanyId() . " and pt.item_id=" . $purchaseDetail['item_id'] . " and p.id=pt.p_c_id and (case when p.project_mode='single_project' THEN p.project_id=" . $purchaseDetail['sub_location'] . " and p.location_id=" . $purchaseDetail['location'] . " ELSE pt.project_id=" . $purchaseDetail['sub_location'] . " and pt.location_id=" . $purchaseDetail['location'] . " END ) order by p.id desc LIMIT 1");

												?>
														<tr class="pq_row">
															<td class="sr_no_tab1 sr_no pq_dr_no"><?php echo $sr_no ?></td>
															<input type="hidden" name="sr_no[]" class="input_sr" value="<?php echo $sr_no ?>">
															<input type="hidden" name="sub_item[]" class="sub_item" value="<?php echo $purchaseDetail['sub_item']; ?>">
															<input type="hidden" class="alternative_unit" value="<?php echo $alternate_unit['name']; ?>">
															<input type="hidden" class="main_item_unit" value="<?php echo $itemSymbol['name']; ?>">
															<input type="hidden" class="conversionToAlternativeUnit" value="<?php echo $item['conversion']; ?>">
															<input type="hidden" class="conversionToKg" value="<?php echo $item['kg_conversion']; ?>">
															<input type="hidden" class="c_sp_commision" value="<?php echo $contrat['commission']; ?>">
															<input type="hidden" class="c_sp_category" value="<?php echo $contrat['balance_category']; ?>">
															<?php
															if ($pi_row['ledger_party'] == "multiple_party") {
															?>
																<td class="multiple_party_append">
																	<select class="form-control chosen_party" name="inv_third_party_id[]">
																		<option value="0"> Select Third Party </option>
																		<?php $db->select("select * from ledger where third_party='yes' and company_id=" . getCompanyId() . " order by id desc");
																		$third_parties = $db->fetch_all();
																		foreach ($third_parties as $third_partie) {
																			if ($third_partie['id'] == $purchaseDetail['third_party']) {
																				$select = "selected";
																			} else {
																				$select = "";
																			} ?>
																			<option value="<?php echo $third_partie['id']; ?>" <?php echo $select ?>><?php echo $third_partie['name']; ?></option><?php } ?>
																	</select>
																</td>
															<?php } else { ?>
																<td class="hidden multiple_party_append"></td>
															<?php } ?>

															<?php if ($pi_row['vehicle_mode'] == "single_vehicle") { ?>
																<td class="serial_no_appened">
																	<input type="text" name="serial_no[]" class="form-control serial" value="<?php echo $purchaseDetail['serial_no'] ?>">
																</td>
															<?php } else { ?>
																<td class="hidden serial_no_appened"></td>
															<?php } ?>
															<td class="group_chosen">
																<input type="hidden" name="dr_location[]" value="<?php echo $head['id']; ?>">
																<input type="hidden" name="dr_sublocation[]" value="<?php echo $sub_head['id']; ?>">
																<div style="width: 100% !important" class="input-group">
																	<select name="item_group[]" onchange="group_item(this)" class="chosen-select form-control">
																		<option> Select Item Group</option>
																		<?php
																		$db->select('SELECT * FROM item_group where FIND_IN_SET(' . getCompanyId() . ',company_id) order by id desc');
																		$group_results = $db->fetch_all();
																		foreach ($group_results as $group_result) {
																			if ($group_result['id'] == $purchaseDetail['item_group']) {
																				$select = "selected";
																			} else {
																				$select = "";
																			} ?>
																			<option id='<?php echo ($group_result['id']) ?>' value='<?php echo ($group_result['id']) ?>' <?php echo $select ?>> <?php echo $group_result['name'] ?>
																			</option>
																		<?php } ?>
																	</select>
																	<input type="hidden" name="contract[]" class="contract" value="<?php echo $purchaseDetail['contract'] ?>">
																</div>
															</td>
															<td class="brand_qual_modal">
																<div class="chosenWidth chosen">
																	<div class="input-group">
																		<select class="form-control chosen-select  check_item selecteditem" name="item_name[]" placeholder=" Enter Item Name" onchange="GetBrands(this)">
																			<option> Select Item</option>
																			<?php
																			$db->select("SELECT * FROM item where FIND_IN_SET(" . getCompanyId() . ",company_id) order by id desc");
																			$optionResults = $db->fetch_all();
																			foreach ($optionResults as $optionResult) {
																				if ($optionResult['id'] == $purchaseDetail['item_id']) {
																					$select = "selected";
																				} else {
																					$select = "";
																				} ?>
																				<option id='<?php echo ($optionResult['id']) ?>' value='<?php echo ($optionResult['id']) ?>' <?php echo $select ?>> <?php echo $optionResult['name'] ?> </option>
																			<?php } ?>
																		</select>
																		<span class="input-group-btn">
																			<button class="btn btn-default" type="button" onclick="showItemModal()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
																		</span>
																	</div>
																</div>
																<?php $item_id = $purchaseDetail['item_id'];
																$checkItem = $db->fetch_array_by_query("select * from item where FIND_IN_SET(" . getCompanyId() . ",company_id) and id=" . $item_id);
																?>
																<!-- Brand Material Toggle -->
																<div class="brand_md_button">
																</div>

																<?php if ($purchaseDetail['sub_item'] == 'yes') { ?>
																	<a data-toggle="modal" data-target="<?php echo ('.SubItemModal') . $sr_no; ?>" class=" md-btn" data-keyboard="false" data-backdrop="static"> Sub Item Specification</a>
																<?php }  ?>
																<!-- End Brand Material  -->
																<?php if ($purchaseDetail['sub_item'] == 'yes') { ?>

																	<div class="modal fade SubItemModal<?php echo $sr_no; ?>" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
																		<div class="modal-dialog modal-lg" role="document" style="width: 90%">
																			<div class="modal-content">
																				<div class="modal-header">
																					<button type="button" class="close" data-dismiss="modal" aria-label="Close">
																						<span aria-hidden="true">&times;</span>
																					</button>
																					<h2 class="modal-title" id="exampleModalLabel"> Sub Items Specification</h2>
																				</div>
																				<div class="row clearfix">
																					<div class="span12">
																						<?php echo $imsg->getMessage(); ?>
																					</div>
																				</div>
																				<div class="modal-body">
																					<div class="box box-danger">
																						<div class="box-body text-left">
																							<div class="table-responsive">
																								<table class="table table-striped table-bordered SubItemTable">
																									<thead>
																										<tr>
																											<th>Code</th>
																											<th class="article_td" <?php if ($checkItem['article'] == 'no') {
																																		echo 'style="display:none"';
																																	} ?>>Article</th>
																											<th class="quality_td" style="display:none" <?php if ($checkItem['quality'] == 'no') {
																																							echo '';
																																						} ?>>Quality</th>
																											<th class="color_td" <?php if ($checkItem['color'] == 'no') {
																																		echo 'style="display:none"';
																																	} ?>>Color</th>
																											<?php if (intval($pi_row['purchase_id']) > 0) { ?>
																												<th class="warehouse_td" <?php if ($checkItem['warehouse'] == 'no') {
																																				echo 'style="display:none"';
																																			} ?>>Warehouse
																												</th>
																												<th class="section_td" <?php if ($checkItem['section'] == 'no') {
																																			echo 'style="display:none"';
																																		} ?>>Section</th>
																											<?php } ?>
																											<th style="width: 45%" colspan="3">Quantity</th>
																											<th colspan="3">Available Quantity</th>
																											<th>Images</th>
																										</tr>
																										<tr>
																											<th></th>
																											<th class="article_td" <?php if ($checkItem['article'] == 'no') {
																																		echo 'style="display:none"';
																																	} ?>></th>
																											<th class="quality_td" style="display:none" <?php if ($checkItem['quality'] == 'no') {
																																							echo '';
																																						} ?>></th>
																											<th class="color_td" <?php if ($checkItem['color'] == 'no') {
																																		echo 'style="display:none"';
																																	} ?>></th>
																											<th>Value</th>
																											<th>Meters</th>
																											<th>Boxes</th>
																											<th class="hidden">Ton</th>
																											<th>Sq.M</th>
																											<th>Boxes</th>
																											<th>Availability</th>
																											<th></th>
																										</tr>
																									</thead>
																									<tbody class="SubItemBody">
																										<?php
																										$db->Select("Select * from sale_order_inventory where o_transaction_id=" . $purchaseDetail['id'] . " and order_id=" . $pi_row['id'] . " and inv_quantity > 0 and sub_item_id > 0 and company_id=" . getCompanyId() . " order by id ASC ");
																										$subItem_inventories = $db->fetch_all();
																										$total_quantity = 0;
																										$inv_boxes = 0;
																										$inv_tons = 0;
																										foreach ($subItem_inventories as $subItem_inventory) {
																											$inv_boxes += $subItem_inventory['boxes'];
																											$inv_tons += $subItem_inventory['tons'];
																											$total_quantity += $subItem_inventory['inv_quantity'];
																											$subItemrow = $db->fetch_array_by_query("select * from sub_item where id=" . $subItem_inventory['sub_item_id']);
																										?>
																											<tr class="SubItemRow">

																												<td>
																													<?php
																													$articlerow = $db->fetch_array_by_query("select * from article where id=" . $subItem_inventory['article_id']);
																													if ($purchaseDetail['brand'] && $purchaseDetail['quality'] != '' && $purchaseDetail['contract'] == 'yes') {
																														getcontractValues($purchaseDetail, $pi_row);
																													} else if ($purchaseDetail['brand'] && $purchaseDetail['quality'] != '' && $purchaseDetail['contract'] == 'no') {
																														$db->select("select * from sub_item as sb right join quotation_inventory as pii on (pii.item_id = sb.item_id and pii.quality_id = " . $purchaseDetail['quality'] . ") where sb.item_id=" . $purchaseDetail['item_id'] . " and sb.brand_id = " . $purchaseDetail['brand'] . " and sb.finishing_type = " . $purchaseDetail['finishing_type'] . " and sb.company_id=" . getCompanyId() . " and pii.location_id = " . $purchaseDetail['location'] . " and pii.sub_location_id = " . $purchaseDetail['sub_location'] . " group by sb.code");
																													}
																													$sub_items = $db->fetch_all();
																													$subcode = $db->fetch_array_by_query("select * from sub_item where id=" . $subItem_inventory['sub_item_id']);
																													?>
																													<div class="form-group">
																														<select class=" SubItemCode chosen-select chosen-code  form-control">
																															<option value="0"> Select Subitem code</option>
																															<?php
																															foreach ($sub_items as $sub) {
																																$select = '';
																																if ($subcode['code']  ==  $sub['code']) {
																																	$select = 'selected';
																																}
																															?>
																																<option <?php echo $select; ?> value="<?php echo $sub['code']; ?>"><?php echo $sub['code']; ?></option>
																															<?php
																															}
																															?>
																														</select>
																														<input name="Sub_item_id<?php echo $sr_no; ?>[]" value="<?php echo $subItemrow['id']; ?>" type="hidden" class="SubITemID">
																													</div>
																												</td>
																												<td class="article_td" <?php if ($checkItem['article'] == 'no') {
																																			echo 'style="display:none"';
																																		} ?>>
																													<div class="form-group">
																														<?php
																														$obj_article = load_class('itemarticle');
																														if ($purchaseDetail['brand'] && $purchaseDetail['quality'] != '' && $purchaseDetail['contract'] == 'yes') {
																															getContractArticles($subcode['code'], $purchaseDetail, $pi_row);
																														} elseif ($purchaseDetail['brand'] && $purchaseDetail['quality'] != '' && $purchaseDetail['contract'] == 'no') {
																															$db->Select("SELECT ar.* FROM quotation_inventory as qi right join article ar on ar.id = qi.article_id right join sub_item as sb on qi.sub_item_id = sb.id  where qi.quality_id=" . $purchaseDetail['quality'] . " and qi.item_id=" . $purchaseDetail['item_id'] . " and sb.code='" . $subcode['code'] . "' and sb.brand_id = " . $purchaseDetail['brand'] . " and location_id = " . $purchaseDetail['location'] . " and sub_location_id= " . $purchaseDetail['sub_location'] . " group by qi.article_id");
																														}
																														$articles = $db->fetch_all();
																														?>
																														<select name="Sub_item_article<?php echo $sr_no; ?>[]" onchange="CheckSubItem(this)" class="chosen-select form-control SubItemArticle chosen_article">
																															<option value="0">Select Articles</option>
																															<?php
																															foreach ($articles as $article) {
																																$select = '';
																																if ($subItem_inventory['article_id'] == $article['id']) {
																																	$select = 'selected';
																																}
																															?>
																																<option <?php echo $select; ?> value="<?php echo $article['id']; ?>"><?php echo $article['name']; ?></option>
																															<?php } ?>
																														</select>
																													</div>
																												</td>
																												<td class="quality_td" style="display:none" <?php if ($checkItem['quality'] == 'no') {
																																								echo '';
																																							} ?>>
																													<div class="form-group">
																														<?php
																														$obj_quality = load_class('itemquality');
																														$qualitys = $obj_quality->getAllRecords();
																														?>
																														<select onchange="getSubwareHouseITemQuantity(this)" name="Sub_item_quality<?php echo $sr_no; ?>[]" class="form-control chosen-select md_quality chosen_quality selectquality inv_quality">
																															<option value="0">Select Quality</option>
																															<?php
																															foreach ($qualitys as $quality) {
																																$select = '';
																																if ($quality['id'] == $subItem_inventory['quality_id']) {
																																	$select = 'selected';
																																}
																															?>
																																<option <?php echo $select; ?> value="<?php echo $quality['id']; ?>"><?php echo $quality['name']; ?></option>
																															<?php
																															}
																															?>
																														</select>
																													</div>
																												</td>
																												<td class="color_td" <?php if ($checkItem['color'] == 'no') {
																																			echo 'style="display:none"';
																																		} ?>>
																													<div class="form-group">
																														<?php
																														$obj_color = load_class('itemcolor');
																														$colors = $obj_color->getAllRecords();
																														?>
																														<select name="Sub_item_color<?php echo $sr_no; ?>[]" class="form-control chosen-select md_color chosen_color selectcolor inv_color">
																															<option value="0">Select color</option>
																															<?php
																															foreach ($colors as $color) {
																																$select = '';
																																if ($color['id'] == $subItem_inventory['color_id']) {
																																	$select = 'selected';
																																}
																															?>
																																<option <?php echo $select; ?> value="<?php echo $color['id']; ?>"><?php echo $color['name']; ?></option>
																															<?php
																															}
																															?>
																														</select>
																													</div>
																												</td>
																												<td>
																													<?php
																													$itemUnit = $db->fetch_array_by_query("Select * from item_unit where id=" . $checkItem['unit_id']);
																													?>
																													<div class="input-group table-inputs">
																														<input type="number" onkeyup="QuantityAdd(this,'<?php echo 'SubItemModal' . $sr_no; ?>')" class="sub-item-quantity form-control" value="<?php echo $subItem_inventory['value']; ?>" name="Sub_item_value<?php echo $sr_no; ?>[]" placeholder="Enter Quantity">
																														<span class="pd_right input-group-addon no-gutter">
																															<select name="Sub_item_unit<?php echo $sr_no; ?>[]" onchange="QuantityValue('<?php echo 'SubItemModal' . $sr_no; ?>',this)" class="showQ form-control" style="width: auto">
																																<option <?php if ($subItem_inventory['unit'] == 'sqm') {
																																			echo "selected";
																																		} ?> value="sqm">Sqm</option>
																																<option <?php if ($subItem_inventory['unit'] == 'boxes') {
																																			echo "selected";
																																		} ?> value="boxes">Boxes</option>
																																<!-- <option <?php if ($subItem_inventory['unit'] == 'ton') {
																																					echo "selected";
																																				} ?> value="ton">ton</option> -->
																															</select>
																														</span>
																													</div>
																												</td>
																												<td class="quantity_td">
																													<div class=" input-group">
																														<input type="number" name="Sub_item_quantity<?php echo $sr_no; ?>[]" readonly value="<?php echo $subItem_inventory['inv_quantity'];	?>" class="form-control inv_quantity">
																													</div>
																												</td>
																												<td class="boxes_td">
																													<div class="input-group">
																														<input name="Sub_item_boxes<?php echo $sr_no; ?>[]" type="number" readonly value="<?php echo $subItem_inventory['boxes']; ?>" class="form-control inv_boxes">
																													</div>
																												</td>
																												<td class="number_td hidden">
																													<div class="input-group">
																														<input name="Sub_item_tons<?php echo $sr_no; ?>[]" type="number" readonly value="<?php if ($subItem_inventory['tons'] > 0) {
																																																				echo $subItem_inventory['tons'];
																																																			} ?>" class="form-control inv_tons">
																													</div>
																												</td>
																												<td>
																													<?php
																													if (intval($pi_row['purchase_id']) > 0) {
																														$sec_id = $subItem_inventory['section_id'];
																														$warehouse_id = $subItem_inventory['warehouse_id'];
																													} else {
																														$sec_id = '';
																														$warehouse_id = '';
																													}
																													$avail_q = GetSubitemQuantity($subItem_inventory['location_id'], $subItem_inventory['sub_location_id'], $subItem_inventory['sub_item_id'], $subItem_inventory['article_id'], $subItem_inventory['quality_id'], $sec_id, $warehouse_id);
																													?>
																													<span style="font-weight: bold" class="available_quantity"><?php echo $avail_q; ?>
																													</span>
																												</td>
																												<td>
																													<span style="font-weight: bold" class="available_quantity_boxes"><?php echo round($avail_q / $checkItem['conversion'], 2); ?></span>
																												</td>
																												<td>
																													<span style="font-weight:bold" class="availability">
																														<?php
																														if (floatval($avail_q) > 0) {
																															echo 'Available';
																														} else {
																															echo 'Unavailable';
																														}
																														?>
																													</span>
																												</td>
																												<td>
																													<div class="col-sm-12">
																														<?php
																														$images = json_decode($subItemrow['image'], true);
																														if (count($images) > 0) {
																															foreach ($images as $img) {
																														?>
																																<div class="col-sm-6" style="padding-left: 0">
																																	<a href="<?php echo BASE_URL . '/admin/itemImages/' . $img ?>" target="blank">
																																		<img style="width:100%;height:50px;" src="<?php echo BASE_URL . '/admin/itemImages/' . $img ?>">
																																	</a>
																																</div>
																															<?php
																															}
																														} else {
																															?>
																															<h4>Sub Item Images</h4>
																														<?php
																														}
																														?>
																													</div>
																												</td>
																											</tr>
																										<?php } ?>
																									</tbody>
																									<tfoot>
																										<tr>
																											<td></td>
																											<td class="article_td" <?php if ($checkItem['article'] == 'no') {
																																		echo 'style="display:none"';
																																	} ?>></td>
																											<td class="quality_td" style="display:none" <?php if ($checkItem['quality'] == 'no') {
																																							echo '';
																																						} ?>></td>
																											<td class="color_td" <?php if ($checkItem['color'] == 'no') {
																																		echo 'style="display:none"';
																																	} ?>></td>
																											<td>Total</td>
																											<td class="quantity_td"><span class="quantity_span"><b class="quantity_b"><?php echo $total_quantity; ?></b></span> <input type="hidden" value="<?php echo $total_quantity; ?>" class="total_auantity"></td>
																											<td class="boxes_td"><span class="boxes_span"><b class="boxes_b"><?php echo $inv_boxes; ?></b></span> <input type="hidden" value="<?php echo $inv_boxes; ?>" class="total_boxes"></td>
																											<td class="tons_td hidden"><span class="tons_span"><b class="tons_b"><?php echo $inv_tons; ?></b></span> <input type="hidden" value="<?php echo $inv_tons; ?>" class="total_tons"></td>
																											<td></td>
																										</tr>
																									</tfoot>
																								</table>
																							</div>
																							<div class="clearfix"></div>
																							<div class="brand-quality-button" style="margin:10px">
																								<button type="button" class="btn btn-primary pull-right" onclick="saveSubItemSpecifications('<?php echo 'SubItemModal' . $sr_no; ?>')"> Save </button>
																							</div>
																						</div>
																					</div>
																				</div>
																			</div>
																		</div>
																	</div>
																<?php }  ?>
															</td>
															<?php
															$transe_id = $purchaseDetail['id'];
															$db->select("SELECT * FROM sale_invoice_inventory  where receipt_transaction_id=" . $transe_id . " and receipt_id=" . $pi_row['id'] . " and sub_item_id <=0 and inv_quantity > 0 and company_id=" . getCompanyId() . " order by id ASC");
															$receipt_inventories = $db->fetch_all(); ?>
															<td>
																<div class="chosenWidth chosen">
																	<div class="form-group">
																		<select class="form-control chosen-select item_brand" name="brand[]" placeholder=" Enter Item Name" onchange="getQuality(this)">
																			<option> Select Brand</option>
																			<?php
																			$db->select("select itb.* from item_brand as itb right join sub_item as sb on (sb.brand_id = itb.id) where sb.item_id = " . $checkItem['id'] . " and FIND_IN_SET('" . getCompanyId() . "',itb.company_id)  group by itb.id");
																			$brands = $db->fetch_all();
																			foreach ($brands as $brand) { ?>
																				<option <?php if ($purchaseDetail['brand'] == $brand['id']) {
																							echo "selected";
																						} ?> id='<?php echo ($brand['id']) ?>' value='<?php echo ($brand['id']) ?>'> <?php echo $brand['name'] ?> </option>
																			<?php } ?>
																		</select>
																	</div>
																</div>
															</td>
															<td>
																<div class="chosenWidth chosen">
																	<div class="form-group">
																		<select class="form-control chosen-select " name="finishing_type[]" placeholder=" Enter Finishing Type">
																			<option value="0"> Select Finishing Type</option>
																			<?php
																			$db->select('SELECT * FROM finishing_type where FIND_IN_SET(' . getCompanyId() . ',company_id)');
																			$f_types = $db->fetch_all();
																			foreach ($f_types as $f_type) {
																				if ($f_type['id'] == $purchaseDetail['finishing_type']) {
																					$f_select = "selected";
																				} else {
																					$f_select = "";
																				}
																			?>
																				<option id='<?php echo ($f_type['id']) ?>' value='<?php echo ($f_type['id']) ?>' <?php echo $f_select; ?>> <?php echo $f_type['name'] ?> </option>
																			<?php } ?>
																		</select>
																	</div>
																</div>
															</td>
															<td>
																<div class="chosenWidth chosen">
																	<div class="form-group">
																		<select class="form-control chosen-select item_quality" name="quality[]" placeholder=" Enter Item Name" onchange="chosenValue(this)">
																			<option> Select Quality</option>
																			<?php
																			// $db->select("Select qi.quality_id as q_id from quotation_inventory as qi left join sub_item as sbi on (qi.item_id = sbi.item_id and qi.sub_item_id = sbi.id) where qi.item_id = ".$checkItem['id']." and sbi.brand_id = ".$purchaseDetail['brand']." and location_id = ".$purchaseDetail['location']." and sub_location_id = ".$purchaseDetail['sub_location']." group by qi.quality_id");
																			$db->select('SELECT id as q_id FROM item_quality where  FIND_IN_SET("' . getCompanyId() . '",company_id)');
																			$qualities = $db->fetch_all();
																			foreach ($qualities as $quality) {
																				$qualiti = $db->fetch_array_by_query("select * from item_quality where id=" . $quality['q_id']);
																			?>
																				<option <?php if ($purchaseDetail['quality'] == $qualiti['id']) {
																							echo "selected";
																						} ?> id='<?php echo ($quality['q_id']) ?>' value='<?php echo ($quality['q_id']) ?>'> <?php echo $qualiti['name'] ?> </option>
																			<?php } ?>
																		</select>
																	</div>
																</div>
															</td>
															<td>
																<div class="form-group table_inputs">
																	<input type="number" readonly value="<?php echo $purchaseDetail['quantity'] ?>" onkeyup="calculate_quantity(this)" name="quantity[]" class="form-control check_quantity quantity" placeholder="Quantity">

																</div>
															</td>
															<td>
																<div class="form-group table_inputs">
																	<input type="number" readonly value="<?php echo $purchaseDetail['boxes']; ?>" name="boxes[]" class="form-control boxes" placeholder="Boxes">

																</div>
															</td>
															<td class="hidden">
																<div class="form-group table_inputs">
																	<input type="number" readonly value="<?php echo $purchaseDetail['tons']; ?>" name="tons[]" class="form-control tons" placeholder="Tons">
																</div>
															</td>
															<td class="mat_modal">
																<div class="form-group">
																	<input type="number" name="rate[]" class="form-control rate" onkeyup="calculate_balance(this)" placeholder="Rate" value="<?php echo $purchaseDetail['actual_rate'] ?>" readonly>
																</div>
																<p class="hidden"> Net Rate : <span class="net_rate_area"><?php echo $purchaseDetail['net_rate'] ?></span></p>
																<p class="hidden"> eff Rate : <span class="eff_net_rate_area"> <?php echo $purchaseDetail['effective_rate'] ?></span></p>
																<div class="appened_discount_hidden"><input type="hidden" class="discount" value="0">
																</div>
															</td>
															<?php if ($pi_row['discount_mode'] == 'enable' && $purchaseDetail['discount_mode'] == "enable") { ?>
																<td class="enable_multiple_discount">
																	<div class="input-group table_inputs">
																		<input type="number" name="discount[]" class="form-control inv_discount discount" onkeyup="calculate_discount(this)" placeholder=" Enter Discount " required="true" value="<?php echo $purchaseDetail['discount']; ?>">
																		<span style="padding: 0px;" class="pd_right input-group-addon">
																			<select onchange="calculate(this)" class="discount_type form-control" name="balance_category[]" style="width: auto;">
																				<option <?php if ($purchaseDetail['balance_category'] == "Rs") {
																							echo "selected";
																						} ?> value="Rs">No</option>
																				<option <?php if ($purchaseDetail['balance_category'] == "%") {
																							echo "selected";
																						} ?> value="%">%</option>
																			</select>
																		</span>
																	</div>
																	<input type="hidden" class="check_percentage" value="no" name="check_percentage[]">
																	<div class="individusl_discount">
																	</div>
																</td>
															<?php } else { ?> <td class="hidden enable_multiple_discount"><input type="hidden" class="discount" name="discount[]" value="0"></td> <?php } ?>
															<td>
																<div class="form-group">
																	<input type="hidden" name="net_rate[]" class="net_rate net_rate_area" value="<?php echo $purchaseDetail['net_rate'] ?>">
																	<input type="number" name="eff_net_rate[]" readonly class=" form-control eff_net_rate" value="<?php echo round($purchaseDetail['effective_rate'], 2); ?>">
																</div>
															</td>
															<?php if ($pi_row['commission'] == 'yes') { ?>
																<td class="commission_td">
																	<div class="input-group">
																		<input class="hidden_commission commission form-control" readonly placeholder="Commission" value="<?php echo $purchaseDetail['commission']; ?>" name="hidden_commission[]">
																		<span class="input-group-addon"><b>PKR</b></span>
																	</div>
																</td>
															<?php } else { ?>
																<td class="hidden commission_td"></td>
															<?php } ?>
															<td>
																<div class="input-group">
																	<span class="input-group-addon"><b>PKR</b></span>
																	<input readonly type="number" name="amount[]" class="form-control input-group check_amount amount" placeholder="Amount" value="<?php echo round($purchaseDetail['amount'], 2); ?>">
																	<input class="hidden_amount" type="hidden" name="">
																	<?php
																	if ($purchaseDetail['balance_category'] == 'Rs') {
																		$disc_amt = $purchaseDetail['discount'] * $purchaseDetail["quantity"];
																	}
																	if ($purchaseDetail['balance_category'] == '%') {
																		$disc = ($purchaseDetail['discount'] * $purchaseDetail['actual_rate']) / 100;
																		$disc_amt = $disc * $purchaseDetail["quantity"];
																	}
																	?>
																	<input class="hidden_discount" type="hidden" value="<?php echo $disc_amt; ?>">
																</div>
																<div class="hidden_disc_amt"> <?php if ($pi_row['discount_mode'] == 'enable' && $purchaseDetail['discount_mode'] == "enable") {
																									echo 'Discount: ' . $disc_amt;
																								} ?></div>
																<input type="hidden" class="hidden_disc_amt_val" value="<?php echo $disc_amt; ?>" name="hidden_disc_amt_val[]">
															</td>
															<?php if (!isset($_REQUEST['so_id'])) { ?>
																<td>
																	<a disabled class="btn btn-primary add_row" onclick="add_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
																	<a disabled class="btn btn-danger" class="remove_row" onclick="remove_row(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i>
																	</a>
																</td>
															<?php } ?>
														</tr>
													<?php }
												} else { ?>
													<tr class="pq_row">
														<td class="sr_no_tab1 sr_no pq_dr_no">1</td>
														<input type="hidden" name="sr_no[]" class="input_sr" value="1">
														<input type="hidden" name="sub_item[]" class="sub_item" value="no">
														<!-- <input type="hidden" class="alternative_unit"> -->
														<input type="hidden" class="main_item_unit">
														<input type="hidden" class="conversionToAlternativeUnit">
														<!-- <input type="hidden" class="conversionToKg"> -->
														<input type="hidden" class="c_sp_commision">
														<input type="hidden" class="c_sp_category">
														<td class="hidden multiple_party_append"></td>
														<td class="group_chosen">
															<input type="hidden" name="dr_location[]" value="<?php echo $head['id']; ?>">
															<input type="hidden" name="dr_sublocation[]" value="<?php echo $sub_head['id']; ?>">
															<div style="width: 100% !important" class="input-group">
																<select name="item_group[]" onchange="group_item(this)" class="chosen-select form-control item_group">
																	<option> Select Item Group</option>
																	<?php
																	$db->select('SELECT * FROM item_group');
																	$group_results = $db->fetch_all();
																	foreach ($group_results as $group_result) { ?>
																		<option id='<?php echo ($group_result['id']) ?>' value='<?php echo ($group_result['id']) ?>'> <?php echo $group_result['name'] ?>
																		</option>
																	<?php } ?>
																</select>
															</div>
															<input type="hidden" name="contract[]" class="contract" value="<?php echo 'no'; ?>">
														</td>
														<td class="brand_qual_modal">
															<div class="chosenWidth chosen">
																<div class="form-group">
																	<select class="form-control chosen-select  check_item selecteditem" name="item_name[]" placeholder=" Enter Item Name" onchange="GetBrands(this)">
																		<option value="0" selected=""> Select Item</option>
																		<?php
																		$db->select('SELECT * FROM item where company_id=' . getCompanyId());
																		$optionResults = $db->fetch_all();
																		foreach ($optionResults as $optionResult) { ?>
																			<option id='<?php echo ($optionResult['id']) ?>' value='<?php echo ($optionResult['id']) ?>'> <?php echo $optionResult['name'] ?> </option>
																		<?php } ?>
																	</select>
																</div>
															</div>
															<div class="brand_md_button"></div>
														</td>
														<td>
															<div class="chosenWidth chosen">
																<div class="input-group">
																	<select class="form-control chosen-select item_brand" onchange="getQuality(this)" name="brand[]" placeholder=" Enter Item Name">
																		<option> Select Brand</option>
																		<?php
																		$db->select('SELECT * FROM item_brand where FIND_IN_SET("' . getCompanyId() . '",company_id)');
																		$brands = $db->fetch_all();
																		foreach ($brands as $brand) { ?>
																			<option id='<?php echo ($brand['id']) ?>' value='<?php echo ($brand['id']) ?>'> <?php echo $brand['name'] ?> </option>
																		<?php } ?>
																	</select>
																</div>
															</div>
														</td>
														<td>
															<div class="chosenWidth chosen">
																<div class="form-group">
																	<select class="form-control chosen-select finishing_type" name="finishing_type[]" placeholder=" Enter Finishing Type">
																		<option value="0"> Select Finishing Type</option>
																		<?php
																		$db->select('SELECT * FROM finishing_type where FIND_IN_SET(' . getCompanyId() . ',company_id)');
																		$f_types = $db->fetch_all();
																		foreach ($f_types as $f_type) {
																		?>
																			<option id='<?php echo ($f_type['id']) ?>' value='<?php echo ($f_type['id']) ?>'> <?php echo $f_type['name'] ?> </option>
																		<?php } ?>
																	</select>
																</div>
															</div>
														</td>
														<td>
															<div class="chosenWidth chosen">
																<div class="input-group">
																	<select class="form-control chosen-select item_quality" name="quality[]" placeholder=" Enter Item Name" onchange="chosenValue(this)">
																		<option> Select Quality</option>
																		<?php
																		$db->select('SELECT * FROM item_quality where  FIND_IN_SET("' . getCompanyId() . '",company_id)');
																		$qualities = $db->fetch_all();
																		foreach ($qualities as $quality) { ?>
																			<option id='<?php echo ($quality['id']) ?>' value='<?php echo ($quality['id']) ?>'> <?php echo $quality['name'] ?> </option>
																		<?php } ?>
																	</select>
																</div>
															</div>
														</td>
														<td>
															<div class="form-group">
																<input readonly type="number" value="0" onkeyup="calculate_quantity(this)" name="quantity[]" class="form-control check_quantity quantity" placeholder="Quantity">
															</div>
														</td>
														<td>
															<div class="form-group">
																<input readonly type="number" name="boxes[]" class="form-control boxes" placeholder="Boxes">
															</div>
														</td>
														<td class="hidden">
															<div class="form-group">
																<input readonly type="number" name="tons[]" class="form-control tons" placeholder="Tons">
															</div>
														</td>
														<td>
															<div class="form-group">
																<input readonly type="number" name="rate[]" class="form-control rate" onkeyup="calculate_balance(this)" placeholder="Rate">
															</div>
															<p class="hidden"> Net Rate : <span class="net_rate_area">0</span></p>
															<p class="hidden"> eff Rate : <span class="eff_net_rate_area"> 0</span></p>
														</td>
														<td class="hidden appened_discount_hidden"><input type="hidden" class="discount" value="0"></td>
														<td class="hidden enable_multiple_discount"></td>
														<td>
															<div class="form-group">
																<input type="number" name="eff_net_rate[]" readonly="" placeholder="Effective Rate" class=" form-control eff_net_rate">
																<input type="hidden" name="net_rate[]" class="net_rate net_rate_area">
															</div>
														</td>
														<td class="hidden commission_td"></td>
														<td>
															<div class="input-group">
																<span class="input-group-addon"><b>PKR</b></span>
																<input type="number" readonly name="amount[]" class="form-control input-group check_amount amount" placeholder="Amount">
																<input class="hidden_amount" type="hidden" name="">
																<input class="hidden_discount" type="hidden">
															</div>
															<div class="hidden_disc_amt"></div>
															<input type="hidden" class="hidden_disc_amt_val" name="hidden_disc_amt_val[]">

														</td>
														<td>
															<a class="btn btn-primary add_row" onclick="add_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
															<a class="btn btn-danger" class="remove_row" onclick="remove_row(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i>
															</a>
														</td>
													</tr>
												<?php } ?>
											</tbody>
											<tfoot>
												<tr class="footer_bg">
													<td></td>
													<td style="text-align: center;" colspan="5"> Gross Total </td>
													<input type="hidden" class="tot_quantity" name="tot_quantity" value="<?php echo $po_row['total_quantity'] ?>">
													<input type="hidden" class="tot_tons_inp" value="0">
													<input type="hidden" class="tot_boxes_inp" value="0">
													<td class="tot_quantity"><?php echo $po_row['total_quantity']; ?></td>
													<td class="tot_boxes"><?php echo $total_boxes; ?></td>
													<td class="tot_tons hidden"><?php echo $total_tons ?></td>
													<td></td>
													<td class="discount_heading <?php if ($pi_row['discount_mode'] == "disable") {
																					echo 'hidden';
																				} else if (empty($pi_row)) {
																					echo "hidden";
																				} ?> "><span id="total_discount_txt">0</span></td>
													<td></td>
													<td class="commission_heading hidden"></td>
													<td>
														<input type="hidden" class="form-control" id="grand_total" name="grand_total" autocomplete="off" placeholder="Grand Total" value="<?php echo $po_row['grand_total'] ?>">
														<span class="grand_total hidden" style="text-align: right;"> <?php echo $po_row['grand_total']; ?></span>
														<input type="hidden" class="form-control" id="net" name="total_net" value="<?php echo $po_row['total_net'] ?>"><span class="net hidden" style="text-align: right;"> <?php echo $po_row['total_net'] ?> </span>
														<input type="hidden" class="form-control" id="total" name="total" placeholder=" Gross Total" value="<?php echo $po_row['total_amount'] ?>">
														<span class="gross-total total" style="text-align: right;"> <?php echo $po_row['total_amount'] ?> </span>
														<input type="hidden" id="total_discount" name="total_discount">
														<input type="hidden" class="total_commission" name="total_commission">
													</td>
													<?php if (!isset($_REQUEST['so_id'])) { ?>
														<td></td>
													<?php } ?>
												</tr>
											</tfoot>
										</table>
									</div>
									<div class="container-fluid" style="padding: 20px">
										<div class="col-md-6">
											<div class="form-group">
												<textarea name="narration" placeholder="Memo" class="form-control" style="border-radius: 1.375rem !important;padding:17px"> <?php echo $po_row['narration']; ?></textarea>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group" style="border:2px #d2d6de dotted;border-radius:15px;"><input type="file" class="text-center" name="attachment[]" style="margin: 0px auto; padding:27px" id="inp_file" multiple="">
												<?php if ($po_row['attachment'] != '') { ?>
													<div id="lightgallery">
														<?php
														$attachments = json_decode($po_row['attachment']);
														$i = 0;
														foreach ($attachments as $attachment) {
															$i++; ?>
															<button onclick="delImage('<?php echo $attachment ?>',<?php echo $po_row['id'] ?>,<?php echo $i ?>)" style="position: absolute;" type="button" class="img-<?php echo $i ?>">X</button>
															<a href="<?php echo $url . $attachment; ?>" class="img img-<?php echo $i ?>">
																<img src="<?php echo $url . $attachment; ?>" data-src="<?php echo $url . $attachment; ?>" style='height:100px; width:100px; padding: 10px; position: relative; margin-top: 20px;'>
															</a>
														<?php } ?>
													</div>
												<?php } ?>
											</div>
										</div>
										<div class="clearfix"></div>
										<div class="main_button" style="text-align:right; padding-bottom: 10px ;margin: 10px">
											<input type="submit" name="purchaseInvoice" value="Save Sale Invoice" class="btn add_btn btn-primary">
											<button type="button" class="btn btn-danger"> Close Sale Invoice </button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>
				<div class="modal fade Installments" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
					<div class="modal-dialog modal-md" role="document" style="width:40%">
						<div class="modal-content  modal-rate" style="width: 100%; margin: 0 auto">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-label="Close">
									<span aria-hidden="true">&times;</span>
								</button>
								<h2 class="modal-title" id="exampleModalLabel"> Sale Installment Plan </h2>
							</div>
							<div class="row clearfix"></div>
							<div class="modal-body">
								<div class="box box-danger">
									<div class="box-body">
										<div class="col-sm-12 no-gutter text-left">
											<h4>Invoice No : <b style="border-bottom: 3px solid #00a65a;width:fit-content; margin-top:5px;margin-bottom:5px">SI - <?php echo InvoiceNo(); ?></b></h4>
											<div class="col-sm-4 no-gutter">
												<h4>Total Amount : <b class="total_amount_b"></b></h4>
											</div>
											<div class="col-sm-4 no-gutter">
												<h4>Remaining : <b class="total_amount_b"></b></h4>
											</div>
											<div class="col-sm-4 no-gutter">
												<h4>Total Paid : <b> 0 Rs /- </b></h4>
											</div>
											<div class="clearfix"></div>
											<input type="hidden" class="remaing_amt" value="">
											<input type="hidden" name="invoice_id" class="invoice_id" value="">
										</div>
										<div class="clearfix"></div>
										<div class="table-responsive" style="margin-top: 15px">
											<table class="table table-striped">
												<thead>
													<tr>
														<th>Sr#</th>
														<th>Amount</th>
														<th>Date</th>
														<th>Actions</th>
													</tr>
												</thead>
												<tbody class="invoice_body">
													<tr>
														<td class="sr_no">
															1
														</td>
														<td>
															<div class="form-group">
																<input type="number" placeholder="Installment Amount" name="installment_amt[]" class="form-control installment_amt">
																<input type="hidden" class="installment_status" value="pending">
															</div>
														</td>
														<td>
															<div class="form-group">
																<input type="text" autocomplete="off" placeholder="Installment Date" name="installment_date[]" class="form-control installment_date">
															</div>
														</td>

														<td>
															<button type="button" onclick="AddInstallmentRow('Installments',this)" class="btn btn-primary"><i class="fa fa-plus-circle"></i></button>
															<button type="button" onclick="RemoveIRow(this)" class="btn btn-danger"><i class="fa fa-minus-circle"></i></button>
														</td>
													</tr>
												</tbody>
												<tfoot>
												</tfoot>
											</table>
										</div>
										<div class="Installment_btn text-right" style="margin:10px">
											<button type="button" data-dismiss="modal" class="btn btn-danger">Close</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
		<!--  Add Item location modal -->
		<div class="modal fade" id="add_item_Group" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h2 class="modal-title" id="exampleModalLabel">Add Item Location</h2>
					</div>
					<div class="row clearfix">
						<div class="span12">
							<?php echo $imsg->getMessage(); ?>
						</div>
					</div>
					<div class="modal-body">
						<form method="post" id="form1">
							<div class="box box-danger">
								<div class="box-body">
									<div class="row">
										<div class="col-sm-6">
											<div class="form-group">
												<label for="name">Item Location:</label>
												<input type="text" class="form-control" placeholder="Enter Item location" name="name">
											</div>
										</div>
										<div class="col-sm-6">
											<div class="form-group">
												<label for="project_location">Project Location:</label>
												<select class="form-control" name="project_location">
													<option>Select Project Location </option>
													<?php
													$db->select("select * from ledger where active_project='yes' order by id desc");
													$project_rows = $db->fetch_all();
													foreach ($project_rows as $project_row) { ?>
														<option value="<?php echo $project_row['id'] ?>"> <?php echo $project_row['name']; ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
									</div>
									<div class="ledger-button">
										<button type="button" class="btn btn-primary pull-left" name="command" value="Add" onclick="addLocation()">Save Location</button>
										<button type="button" class="btn btn-danger pull-right" data-dismiss="modal">Close</button>
									</div>
								</div>
								<div class="ledger-loading" style='display:none; '>
									<div>&nbsp;</div>
									<img src="<?php echo BASE_URL . 'images/loading.gif' ?>" style="height: 100px;">
								</div>
								<div class="clearfix"></div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
		<!-- subitem Material  -->
		<div class="modal fade SubItemModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document" style="width: 90%">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h2 class="modal-title" id="exampleModalLabel"> Sub Items Specification</h2>
					</div>
					<div class="row clearfix">
						<div class="span12">
							<?php echo $imsg->getMessage(); ?>
						</div>
					</div>
					<div class="modal-body">
						<div class="box box-danger">
							<div class="box-body text-left">
								<div class="table-responsive">
									<div>
										<input type="hidden" class="head">
										<input type="hidden" class="sub_head">
									</div>
									<table class="table table-striped table-bordered SubItemTable">
										<thead>
											<tr>
												<th>Code</th>
												<th class="article_td">Article</th>
												<th class="quality_td">Quality</th>
												<!-- <th class="warehouse_td">Warehouse</th>
												<th class="section_td">Section</th> -->
												<th class="color_td">Color</th>
												<th colspan="3" style="width: 42%">Quantity</th>
												<th colspan="3">Available Quantity</th>
												<th>Image</th>
												<th>Action</th>
											</tr>
											<tr>
												<th></th>
												<th class="article_td"></th>
												<th class="quality_td"></th>
												<th class="color_td"></th>
												<th>Value</th>
												<th>Meters</th>
												<th>Boxes</th>
												<th class="hidden">Ton</th>
												<th>Sq.M</th>
												<th>Boxes</th>
												<th>Availability</th>
												<th></th>
												<th></th>
											</tr>
										</thead>
										<tbody class="SubItemBody">
											<tr class="SubItemRow">
												<td>
													<div class="form-group">
														<select onchange="getArticles(this,'journal')" class=" SubItemCode chosen-code chosen-select form-control">
															<option value="0"> Select Subitem code</option>
														</select>
														<h4 class="SubItemCodeText"></h4>
														<input type="hidden" class="SubITemID">
													</div>
												</td>
												<td class="article_td">
													<div class="form-group">
														<?php
														$obj_article = load_class('itemarticle');
														$articles = $obj_article->getAllRecords();
														?>
														<select onchange="CheckSubItem(this)" class="chosen-select form-control SubItemArticle chosen_article">
															<option value="0">Select Articles</option>
															<?php
															foreach ($articles as $article) {
															?>
																<option value="<?php echo $article['id']; ?>"><?php echo $article['name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="quality_td">
													<div class="form-group">
														<?php
														$obj_quality = load_class('itemquality');
														$qualitys = $obj_quality->getAllRecords();
														?>
														<select onchange="getSubwareHouseITemQuantity(this)" class="form-control chosen-select md_quality chosen_quality selectquality inv_quality">
															<option value="0">Select Quality</option>
															<?php
															foreach ($qualitys as $quality) {
															?>
																<option value="<?php echo $quality['id']; ?>"><?php echo $quality['name']; ?></option>
															<?php
															}
															?>
														</select>
													</div>
												</td>
												<td class="color_td">
													<div class="form-group">
														<?php
														$obj_color = load_class('itemcolor');
														$colors = $obj_color->getAllRecords();
														?>
														<select onchange="getSubwareHouseITemQuantity(this)" class="form-control chosen-select md_color chosen_color selectcolor inv_color">
															<option value="0">Select color</option>
															<?php
															foreach ($colors as $color) {
															?>
																<option value="<?php echo $color['id']; ?>"><?php echo $color['name']; ?></option>
															<?php
															}
															?>
														</select>
													</div>
												</td>
												<td>
													<div class="input-group qty-span">
														<input type="number" class="form-control sub-item-quantity" placeholder="Enter Quantity">
														<span class="input-group-addon no-gutter">
															<select class="showQ form-control sub-item-unit" style="width: auto">
																<option value="sqm">Sqm</option>
																<option value="boxes">Boxes</option>
																<!-- <option value="ton">Ton</option> -->
															</select>
														</span>
													</div>
												</td>
												<td class="quantity_td">
													<div class="form-group">
														<input type="number" readonly value="0" class="form-control inv_quantity">
													</div>
												</td>
												<td class="boxes_td">
													<div class="form-group">
														<input type="number" readonly value="0" class="form-control inv_boxes">
													</div>
												</td>
												<td class="number_td hidden">
													<div class="form-group">
														<input type="number" readonly value="0" class="form-control inv_tons">
													</div>
												</td>
												<td>
													<span style="font-weight: bold" class="available_quantity">0</span>
												</td>
												<td>
													<span style="font-weight: bold" class="available_quantity_boxes">0</span>
												</td>
												<td>
													<span style="font-weight: bold" class="availability">Unavailable</span>
												</td>


												<!-- <td class="warehouse_td">
													<div class="form-group">
														<?php
														$obj_warehouse = load_class('warehouse');
														$warehouses = $obj_warehouse->getAllRecords();
														?>
														<select onchange="getSectionSubITemQuantity(this)" class="form-control chosen-select md_warehouse chosen_warehouse selectwarehouse inv_warehouse">
															<option value="0">Select Warehouse</option>
															<?php
															foreach ($warehouses as $warehouse) {
															?>
															<option value="<?php echo $warehouse['id']; ?>"><?php echo $warehouse['name']; ?></option>
															<?php
															}
															?>
														</select>
														<h5 class="WarehouseSubItemQuantityText"></h5>
													</div>
												</td>
												<td class="section_td">
													<div class="form-group">
														<?php
														$db->select("Select * from section where FIND_IN_SET(" . getCompanyId() . ",company_id)");
														$sections = $db->fetch_all();
														?>
														<select class="form-control chosen-select md_section chosen_section selectsection inv_section">
															<option value="0">Select Sections</option>
															<?php
															foreach ($sections as $section) {
															?>
															<option value="<?php echo $section['id']; ?>"><?php echo $section['name']; ?></option>
															<?php
															}
															?>
														</select>
														<h5 class="SectionSubItemQuantityText"></h5>
													</div>
												</td> -->
												<td class="img_td">
													<div class="col-sm-12">
														<h4>Sub Item Images</h4>
													</div>
												</td>
												<td>
													<button class="btn btn-primary" onclick="AddSubItemRow(this)" type="button"><i class="fa fa-plus-circle"></i></button>
													<button class="btn removeRowBtn btn-danger" type="button"><i class="fa fa-minus-circle"></i></button>
												</td>
											</tr>
										</tbody>
										<tfoot>
											<tr>
												<td></td>
												<td class="article_td"></td>
												<td class="quality_td"></td>
												<td class="color_td"></td>
												<td>Total</td>
												<td class="quantity_td"><span class="quantity_span"><b class="quantity_b">0</b></span> <input type="hidden" class="total_auantity"></td>
												<td class="boxes_td"><span class="boxes_span"><b class="boxes_b">0</b></span> <input type="hidden" class="total_boxes"></td>
												<td class="tons_td hidden"><span class="tons_span"><b class="tons_b">0</b></span> <input type="hidden" class="total_tons"></td>
												<td></td>
												<td></td>
												<td></td>
											</tr>
										</tfoot>
									</table>
									<div class="hidden">
										<input type="hidden" name="inv_brand[]">
										<input type="hidden" name="inv_quality[]">
										<input type="hidden" name="inv_color[]">
										<input type="hidden" name="inv_article[]">
										<input type="hidden" name="inv_warehouse[]">
										<input type="hidden" name="inv_section[]">
										<input type="hidden" name="inv_finishingType[]">
										<input type="hidden" name="old_imgs[]">
									</div>
								</div>
								<div class="clearfix"></div>
								<div class="brand-quality-button" style="margin:10px"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- End Brand Material  -->
		<!-- Sub ledger model -->
		<div class="modal fade sub_ledger_model" id='sub_ledger_model' role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-md" role="document">
				<div class="modal-body" style="padding: 0px !important">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal">×</button>
							<h4 class="modal-title">Information</h4>
						</div>
						<div class="modal-body">
							<form id="sub_ledger_form">
								<?php $subledger = $db->fetch_array_by_query("Select * from sub_ledgers where id=" . $pi_row['sub_ledger_id']); ?>
								<input type="hidden" name="sub_ledger_id" class="sub_ledger_id">
								<input type="hidden" name="main_ledger_id" class="main_ledger_id">
								<div class="col-xs-12 form-group no-gutter">
									<div class="col-md-4">
										<div class="form-group">
											<label>Name</label>
											<input type="text" onkeyup="getSubledger()" class="form-control sub_ledger_name" placeholder="Name" required="required">
										</div>
									</div>
									<div class="col-xs-4">
										<div class="form-group">
											<label>Id Card</label>
											<!-- //onfocusout="checkSubledger(this)" -->
											<input type="text" onkeyup="getSubledger()" class="form-control sub_ledger_id_card" placeholder="Id Card" maxlength="15" name="sub_ledger_id_card" required="required">
										</div>
									</div>
									<div class="col-xs-4 form-group">
										<label for="email">Mobile (Messaging):</label>
										<div class="input-group">
											<!-- onfocusout="checkSubledger(this)" -->
											<span style="background: #e8ebef" class="input-group-addon"><b>+92</b></span>
											<input type="text" onkeyup="getSubledger()" class="form-control sub_ledger_mobile" placeholder="Mobile No" maxlength="11" name="sub_ledger_mobile" required="required">
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="col-xs-12 hidden">
										<button type="button" class="btn btn-primary pull-left" onclick="insertSubLedger(this)">Verified Save</button>
									</div>
								</div>
								<div class="clearfix"></div>
								<div class="col-xs-12" style="margin-top: 15px">
									<div class="table-responsive">
										<table class="table table-striped ">
											<thead>
												<tr>
													<th>Sr#</th>
													<th>Name</th>
													<th>Id Card</th>
													<th>Phone Number</th>
													<th>Check</th>
												</tr>
											</thead>
											<tbody>
												<?php if ($pi_row) { ?>
													<tr>
														<td>1</td>
														<td><input type="hidden" class="walk_in_name" value="<?php echo $subledger['name']; ?>"><?php echo $subledger['name']; ?></td>
														<td><?php echo $subledger['id_card'] ?></td>
														<td><?php echo $subledger['mobile']; ?></td>
														<td>
															<input type="hidden" class="walk_in_id" value="<?php echo $subledger['id']; ?>">
															<input type="checkbox" checked onclick="checkSubledger(this)" class="walk_in_check">
														</td>
													</tr>
												<?php } else { ?>
													<tr>
														<td colspan="5">No Sub Ledger</td>
													</tr>
												<?php } ?>
											</tbody>
										</table>
									</div>
								</div>
								<div class="clearfix"></div>
							</form>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default pull-left" data-dismiss="modal">Close</button>
						</div>
					</div>
					<div class="clearfix"></div>
				</div>
			</div>
		</div>
		<!-- End sub ledger model -->
		<?php include("../includes/item-popup.php"); ?>
		<?php include("../includes/footer.php"); ?>
		<div class='control-sidebar-bg'></div>
	</div>
	<?php include("../includes/footer-jsfiles.php"); ?>
	<?php include("../includes/popups-validation.php"); ?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.10/jquery.mask.js"></script>
	<script type="text/javascript">
		$('.sub_ledger_id_card').keyup(function() {
			$('input[name="sub_ledger_id_card"]').mask('00000-0000000-0')
		});
		$('.sub_ledger_mobile').keyup(function() {
			$('input[name="sub_ledger_mobile"]').mask('0000000000')
		});
		$(document).ready(function() {
			$('.rate').each(function(index, el) {
				calculate_balance(el);
			});
			$(".installment_date").datepicker({
				format: 'd/m/yyyy'
			});
			$(".chosen-project").chosen({
				width: '100%'
			});
		});
		$('input[type=radio][name=discount_mode]').change(function() {
			if (this.value == 'enable') {
				var multiple_discount = '<div class="input-group table_inputs"><input type="number" name="discount[]" class="form-control inv_discount discount" onkeyup="calculate_discount(this)" placeholder=" Enter Discount " required="true" value="0"><span style="padding: 0px;" class="pd_right input-group-addon"><select onchange="calculate(this)" class="discount_type form-control" name="balance_category[]" style="width: auto;"><option selected value="Rs">No</option><option value="%">%</option></select></span></div><input type="hidden" class="check_percentage" value="no" name="check_percentage[]"><div class="individusl_discount"><input type="hidden" class="hidden_discount_amt" ><span></span></div>';
				$('.enable_multiple_discount').html(multiple_discount);
				$('.enable_multiple_discount').removeClass('hidden');
				$('.rate').trigger("onkeyup");
				$('.rate_td').removeAttr("colspan");
				$('.rate_td').attr("colspan", "3");
				$('.discount_heading').removeClass('hidden');
				$('.appened_discount_hidden').html("");
			} else if (this.value == 'disable') {
				var hidden_discount = '<input type="hidden" class="discount" value="0">';
				$('.appened_discount_hidden').html(hidden_discount);
				$('.enable_multiple_discount').html("");
				$('.enable_multiple_discount').addClass('hidden');
				$('.rate_td').removeAttr("colspan");
				$('.rate_td').attr("colspan", "2");
				$('.discount_heading').addClass('hidden');
				$('.rate').trigger("onkeyup");
			}
		});

		$(document).ready(function() {
			$(`.chosen-supplier`).prop('disabled', true).trigger("chosen:updated");
			$("#discount_checkbox").prop("disabled", true);
			$('.chosen_party').chosen({
				width: "100%"
			});
			$('.chosen_brand').chosen({
				width: "100%"
			});
			$('.chosen_quality').chosen({
				width: "100%"
			});
			$('.chosen_color').chosen({
				width: "100%"
			});
			$('.chosen-discount').chosen({
				width: "100%"
			});
			// $('.discount_with_vehicle').hide();
			var single_party_mode = '<th style="width:5%">Sr #</th><th style="width:15%">Item Group</th><th style="width:15%">Item Name</th><th style="width:15%">Quantity</th><th style="width:20%">Rate</th><th style="width:15%">Amount</th><th style="width:15%">Action</th>';
			$('.change_head').html(single_party_mode);
			$('#discount_checkbox').click(function() {
				if ($(this).prop("checked") == false) {
					$('.dis_rate').addClass('hidden');
					$("#discount").val(0);
					$("#discount").trigger('onkeyup');
					grand();
				} else if ($(this).prop("checked") == true) {
					$('.dis_rate').removeClass('hidden');
					$("#discount").trigger('onkeyup');
					$("#discount").val(0);
					grand();
				}
				calculateTotalEff();
			});
		});

		function showlocationModal() {
			$("#add_item_Group").modal('show');
		}

		$("#form1").validate({
			rules: {
				name: "required"
			},
			messages: {
				name: "Please Enter Unit Location"
			},
			highlight: function(element, errorClass) {
				$(element).addClass('errorInput');
				$(element).parent().next().find("." + errorClass).removeClass("checked");
			},
			unhighlight: function(element) {
				$(element).removeClass('errorInput');
			}
		});

		var form1 = $("#form1");

		function addLocation() {
			if (form1.valid()) {
				$(".ledger-loading").show();
				$(".ledger-button").hide();
				$.ajax({
					url: "<?php echo ADMIN_URL; ?>ajax/location.php",
					type: "POST",
					data: {
						'form-data': form1.serialize()
					}
				}).done(function(msg) {
					$.ajax({
						url: "<?php echo ADMIN_URL; ?>ajax/location.php",
						type: "POST",
						data: {
							'command': 'add_location'
						}
					}).done(function(result) {
						res = $.parseJSON(result);
						$(".location_select").html('');
						$(".location_select").html(res.html);
						$('.location_select').trigger("chosen:updated");
						$("#add_item_Group").modal('hide');
						$(".ledger-loading").hide();
						$(".ledger-button").show();
					}).fail(function(jqXHR, textStatus) {
						alert("Request failed: " + textStatus);
					});
				}).fail(function(jqXHR, textStatus) {
					alert("Request failed: " + textStatus);
				});
			}
		}

		$(document).ready(function() {
			$("#invoiceForm").validate({
				rules: {
					loading_amount: "required",
					unloading_amount: "required",
					other_amount: "required"
				},
				messages: {
					loading_amount: "Please Enter Loading Amount",
					unloading_amount: "Please Enter Unloading Amount",
					other_amount: "Please Enter Other Amount"
				},
				highlight: function(element, errorClass) {
					$(element).addClass('errorInput');
					$(element).parent().next().find("." + errorClass).removeClass("checked");
				},
				unhighlight: function(element) {
					$(element).removeClass('errorInput');
				},
				submitHandler: function(form) {
					if (check_Entries() !== false) {
						$(".chosen-transporter").prop("disabled", false);
						$(".carriage").prop("disabled", false);
						$(".material").prop("disabled", false);
						form.submit();
					}
				}
			})
		});

		function check_Entries() {
			result = true;
			var project = $(".project_id option:selected").val();
			if (project == "" || project == '0') {
				alert("Please Select Project");
				return result = false;
			}

			var location = $(".location_id option:selected").val();
			if (location == "" || location == '0') {
				alert("Please Select Location");
				return result = false;
			}

			if ($(".sale_type").val() == 'company') {
				var location = $(".debit_head option:selected").val();
				if (location == "" || location == '0') {
					alert("Please Select Debit Location");
					return result = false;
				}

				var slocation = $(".debit_subhead option:selected").val();
				if (slocation == "" || slocation == '0') {
					alert("Please Select Debit Sub Location");
					return result = false;
				}
			} else {
				var Vendor = $('.inv_transporter_id option:selected').val();
				if (Vendor == "" || Vendor == '0') {
					alert("Please Select Customer");
					return result = false;
				}
			}

			var loading_led = $('.loading_ledger option:selected').val();
			if (loading_led == "" || loading_led == '0') {
				alert("Please Select Loading Ledger");
				return result = false;
			}
			var unloading_led = $('.unloading_ledger option:selected').val();
			if (unloading_led == "" || unloading_led == '0') {
				alert("Please Select Unloading Ledger");
				return result = false;
			}
			var other_led = $('.other_ledger option:selected').val();
			if (other_led == "" || other_led == '0') {
				alert("Please Select Other Ledger");
				return result = false;
			}

			$(".check_quantity").each(function(i) {
				quantity = parseFloat($(this).val());
				if (quantity == 0 || isNaN(quantity)) {
					alert("Please Enter Quantity");
					return result = false;
				}
			});

			$(".check_rate").each(function(i) {
				rate = parseFloat($(this).val());
				if (rate == 0 || isNaN(rate)) {
					alert("Please Enter Rate");
					return result = false;
				}
			});

			$(".check_amount").each(function(i) {
				amount = parseFloat($(this).val());
				if (amount == 0 || isNaN(amount)) {
					alert("Please Check Amount");
					return result = false;
				}
			});

			$(".check_item").each(function(i) {
				var selectItem = parseInt($(this).val());
				if (selectItem == "" || selectItem == 0) {
					alert("Please Select Item ");
					return result = false;
				}
			});
			return result;
		}

		function showItemModal() {
			$("#add_item").modal('show');
		}

		function addVendor() {
			$("#addVendor").modal('show');
		}

		function chosenValue(val_this) {
			$(".loding_div ").show();
			var project_id = parseInt($('.location_div .sub_location').val());
			var location_id = parseInt($('.location_div .location_id').val());
			var data_id = "";
			var data_id = $(val_this).parent().parent().parent().parent().find(".check_item").val();
			if (project_id == "0" || project_id == null || isNaN(project_id)) {
				alert("Kindly select Sub location.THANKS");
			} else if (location_id == "0" || location_id == null || isNaN(location_id)) {
				alert("Kindly Select Locatoin.THANKS");
			} else {
				$.ajax({
					url: '<?php echo ADMIN_URL; ?>ajax/data.php',
					method: 'POST',
					data: {
						data_id
					},
				}).done(function(units) {
					checkContract(val_this);
					checkSubItems(val_this, project_id, location_id);
					units = JSON.parse(units);
					units.item_unit.forEach(function(unit) {
						$(val_this).parent().parent().parent().parent().find('.qty_symbol').html("");
						$(val_this).parent().parent().parent().parent().find('.main_item_unit').val(unit.symbol);
						$(val_this).parent().parent().parent().parent().find('.qty_symbol').html(unit.symbol);
						$(val_this).parent().parent().parent().parent().find('.qty_symbol_per').html(unit.symbol);
					});
					$(val_this).parent().parent().parent().parent().find(".conversionToAlternativeUnit").val("");
					$(val_this).parent().parent().parent().parent().find(".conversionToAlternativeUnit").val(units.alternative_unit.conversion);

				});
			}
		}

		function CheckSalesPersonContract(val_this) {
			var item_id = $(val_this).val();
			var project_id = $('.sub_location').val();
			var location_id = $('.location_id').val();
			var sales_person = $('.sales_person').val();
			var location_id = $('.location_id').val();
			$.ajax({
				url: 'check-sales-person-contract.php',
				type: 'post',
				data: {
					item_id,
					location_id,
					project_id,
					sales_person
				},
				success: function(data) {
					res = $.parseJSON(data);
					$(val_this).parent().parent().parent().parent().find(".c_sp_commision").val(res.commission);
					$(val_this).parent().parent().parent().parent().find(".c_sp_category").val(res.balance_category)
					$(val_this).parent().parent().parent().parent().find(".c_sp_quantity").val(res.quantity)
				}

			});
		}

		function checkSubItems(val_this, project_id, location_id) {
			sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
			var item_id = $(val_this).parent().parent().parent().parent().find(".check_item").val();
			var brand_id = $(val_this).parent().parent().parent().parent().find(".item_brand").val();
			var quality_id = $(val_this).parent().parent().parent().parent().find(".item_quality").val();
			var finishing_type = $(val_this).parent().parent().parent().parent().find(".finishing_type").val();
			$.ajax({
				url: 'add-sale-invoice.php',
				Type: 'post',
				data: {
					item_id,
					command: 'checkSubItems'
				},
				success: function(result) {
					result = result.trim();
					if (result == 'yes') {
						ShowSubItemModal(val_this);
						$.ajax({
							url: "<?php echo ADMIN_URL; ?>ajax/get-item-specifications.php",
							type: "POST",
							data: {
								'item': 'Add_item',
								item_id,
								brand_id,
								finishing_type,
								quality_id,
								project_id,
								location_id
							},
						}).done(function(result) {
							res = $.parseJSON(result);
							if (res.sub_item == 'yes') {
								// for item quality
								// if(res.item_quality != 'no'){
								// 	$(val_this).parent().parent().parent().parent().find('.quality_td').show();
								$(val_this).parent().parent().parent().parent().find('.selectquality').html("");
								$(val_this).parent().parent().parent().parent().find('.selectquality').html(res.item_quality);
								$(val_this).parent().parent().parent().parent().find('.chosen_quality').trigger("chosen:updated");
								// }else{
								// 	$(val_this).parent().parent().parent().parent().find('.selectquality').val("").trigger("chosen:updated");
								$(val_this).parent().parent().parent().parent().find('.quality_td').hide();
								// }

								// for item color		
								if (res.item_color != 'no') {
									$(val_this).parent().parent().parent().parent().find('.color_td').show();
									$(val_this).parent().parent().parent().parent().find('.selectcolor').html("");
									$(val_this).parent().parent().parent().parent().find('.selectcolor').html(res.item_color);
									$(val_this).parent().parent().parent().parent().find('.chosen_color').trigger("chosen:updated");
								} else {
									$(val_this).parent().parent().parent().parent().find('.selectcolor').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.color_td').hide();
								}


								if (res.article != 'no') {
									$(val_this).parent().parent().parent().parent().find('.article_td').show();
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').chosen({
										width: '100%'
									});
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').html("");
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').html(res.article);
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').trigger("chosen:updated");
								} else {
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.article_td').hide();
								}


								if (res.item_color != 'no') {
									$(val_this).parent().parent().parent().parent().find('.color_td').show();
									$(val_this).parent().parent().parent().parent().find('.selectcolor').html("");
									$(val_this).parent().parent().parent().parent().find('.selectcolor').html(res.item_color);
									$(val_this).parent().parent().parent().parent().find('.chosen_color').trigger("chosen:updated");
								} else {
									$(val_this).parent().parent().parent().parent().find('.selectcolor').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.color_td').hide();
								}

								// for item warehouse	
								if (res.warehouse != 'no') {
									$(val_this).parent().parent().parent().parent().find('.warehouse_td').show();
									$(val_this).parent().parent().parent().parent().find('.selectwarehouse').html("");
									$(val_this).parent().parent().parent().parent().find('.selectwarehouse').html(res.warehouse);
									$(val_this).parent().parent().parent().parent().find('.chosen_warehouse').trigger("chosen:updated");
								} else {
									$(val_this).parent().parent().parent().parent().find('.selectwarehouse').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.warehouse_td').hide();
								}

								if (res.item_unit != '') {
									$(val_this).parent().parent().parent().find(".qty_symbol").text(res.item_unit);
								}

								// forSubItemCode	
								if (res.SubItemCode != '') {
									$(val_this).parent().parent().parent().parent().find('.SubItemCode').html("");
									$(val_this).parent().parent().parent().parent().find('.SubItemCode').html(res.SubItemCode);
									$(val_this).parent().parent().parent().parent().find('.SubItemCode').trigger("chosen:updated");
								}

								// for item section			
								if (res.section != 'no') {
									$(val_this).parent().parent().parent().parent().find('.section_td').show();
									$(val_this).parent().parent().parent().parent().find('.selectsection').html("");
									$(val_this).parent().parent().parent().parent().find('.selectsection').html(res.section);
									$(val_this).parent().parent().parent().parent().find('.chosen_section').trigger("chosen:updated");
								} else {
									$(val_this).parent().parent().parent().parent().find('.selectsection').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.section_td').hide();
								}
								GetContractSubitems(val_this);
								$(".loding_div ").hide();
								$('.SubItemModal' + sr_no).modal('show');
							}
						}).fail(function(jqXHR, textStatus) {
							alert("Request  are failed:" + textStatus);
						});
					} else {
						alert("No Sub Items");
					}
				}
			});
		}

		function ShowSubItemModal(val_this) {
			$('.chosen_brand').chosen('destroy');
			$('.chosen-code').chosen('destroy');
			$('.chosen_quality').chosen('destroy');
			$('.chosen_color').chosen('destroy');
			$('.chosen_article').chosen('destroy');
			$('.chosen_warehouse').chosen('destroy');
			$('.chosen_finishingType').chosen('destroy');
			$('.chosen_section').chosen('destroy');
			var location_id = $(".location_div .location_id").val();
			var sublocation_id = $(".location_div .sub_location").val();
			var item_id = $(val_this).parent().parent().parent().parent().find(".check_item").val()
			var item_group = $(val_this).parent().parent().parent().parent().find(".item_group option:selected").text();
			var item = $(val_this).parent().parent().parent().parent().find(".check_item option:selected").text();
			sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
			$(val_this).parent().parent().parent().parent().find(".sub_item").val("yes");
			if ($('.SubItemModal' + sr_no).html() != undefined) {

				$('.SubItemModal' + sr_no).find(".head").val(location_id);
				$('.SubItemModal' + sr_no).find(".sub_head").val(sublocation_id);
				modal_no = 'SubItemModal' + sr_no;
				$('.SubItemModal' + sr_no, ).modal('show');
			} else {
				modal_no = 'SubItemModal' + sr_no;
				modal = $(".SubItemModal:first").clone();
				var getSaleItemQuantity = 'getSaleItemQuantity(' + location_id + ',' + sublocation_id + ',' + item_id + ',"' + modal_no + '",this)';
				$(val_this).parent().parent().parent().parent().find('.brand_qual_modal .modal').remove();
				$(val_this).parent().parent().parent().parent().find('.brand_qual_modal').append(modal);
				$(val_this).parent().parent().parent().parent().find('.SubItemModal').addClass('SubItemModal' + sr_no);
				$('.SubItemModal' + sr_no).removeClass('SubItemModal');
				$('.SubItemModal' + sr_no).find(".head").val(location_id);
				$('.SubItemModal' + sr_no).find(".sub_head").val(sublocation_id);
				$('.SubItemModal' + sr_no).find(".modal-title").text(item_group + '( ' + item + ' )');
				$('.SubItemModal' + sr_no).find('.inv_section').attr('onchange', getSaleItemQuantity);
				$('.SubItemModal' + sr_no).find('.removeRowBtn').attr('onclick', 'removeSubItemrow(this,"' + modal_no + '")');
				ChangeSubItemModalNAmes(modal_no, sr_no);
				$('.SubItemModal' + sr_no).find('.showQ').attr('onchange', 'QuantityValue("' + modal_no + '",this)');
				$('.SubItemModal' + sr_no).find('.sub-item-quantity').attr('onkeyup', 'QuantityAdd(this,"' + modal_no + '")');
				$('.SubItemModal' + sr_no + " .brand-quality-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveSubItemSpecifications("' + modal_no + '")> Save </button>');
				specificationsBtn();

				// $('.SubItemModal'+sr_no).modal('show');
			}
			$('.chosen_brand').chosen({
				width: '100%'
			});
			$('.chosen_quality').chosen({
				width: '100%'
			});
			$('.chosen_color').chosen({
				width: '100%'
			});
			$('.chosen-code').chosen({
				width: '100%'
			});
			$('.chosen_article').chosen({
				width: '100%'
			});
			$('.chosen_warehouse').chosen({
				width: '100%'
			});
			$('.chosen_finishingType').chosen({
				width: '100%'
			});
			$('.chosen_section').chosen({
				width: '100%'
			});
		}


		function getArticles(val_this, type) {
			var code = $(val_this).val();
			var customer = $(".inv_transporter_id").val();
			var item = $(val_this).closest(".brand_qual_modal").find(".check_item").val();
			var brand = $(val_this).closest(".brand_qual_modal").parent().find(".item_brand").val();
			var quality = $(val_this).closest(".brand_qual_modal").parent().find(".item_quality").val();
			var finishing_type = $(val_this).closest(".brand_qual_modal").parent().find(".finishing_type").val();
			var project_id = $('.sub_location').val();
			var location_id = $('.location_id').val();
			var sale_type = $(".sale_type").val();
			$.ajax({
				url: 'add-sale-invoice.php',
				data: {
					code,
					item,
					brand,
					project_id,
					sale_type,
					customer,
					quality,
					finishing_type,
					type,
					location_id,
					command: 'getArticles'
				},
				type: 'POST',
				success: function(data) {
					$(val_this).parent().parent().parent().find(".SubItemArticle").html(data).trigger("chosen:updated");
					CheckSubItem(val_this);
				}
			})
		}

		function GetContractSubitems(val_this) {
			var item_id = $(val_this).parent().parent().parent().parent().find(".check_item").val();
			var brand = $(val_this).closest('tr').find(".item_brand").val();
			var sale_type = $(".sale_type").val();
			var quality = $(val_this).closest('tr').find(".item_quality").val();
			var finishing_type = $(val_this).closest('tr').find(".finishing_type").val();
			var customer = $(".inv_transporter_id").val();
			var project_id = $('.sub_location').val();
			var location_id = $('.location_id').val();
			var sr = $(val_this).closest('tr').find(".pq_dr_no").html();
			$.ajax({
				url: '<?php echo ADMIN_URL ?>ajax/get-item-specifications.php',
				type: 'post',
				data: {
					sale_type,
					item_id,
					brand,
					quality,
					customer,
					project_id,
					finishing_type,
					location_id,
					command: 'getContractSubitems'
				},
				success: function(subitems) {
					$tr = $(val_this).closest("tr");
					res = $.parseJSON(subitems);
					if (res.codes != '' && res.articles != '') {
						$(".SubItemModal" + sr).closest('tr').find(".contract").val("yes");
						$(".SubItemModal" + sr).find(".SubItemTable tbody tr:first .chosen-code").removeAttr("onchange");
						$(".SubItemModal" + sr).find(".SubItemTable tbody tr:first .chosen-code").attr("onchange", "getArticles(this,'contract')");
						$(".SubItemModal" + sr).find(".SubItemTable tbody tr:first .chosen-code").html(res.codes).trigger("chosen:updated");
						$(".SubItemModal" + sr).find(".SubItemTable tbody tr:first .chosen_article").html(res.articles).trigger("chosen:updated");
					}
				}
			});
		}



		function saveSubItemSpecifications(modal_no) {
			var SubItemsQuantity = parseFloat($("." + modal_no).find(".total_auantity").val()).toFixed(2);
			var SubItemsBoxes = parseFloat($("." + modal_no).find(".total_boxes").val()).toFixed(2);
			var SubItemstons = parseFloat($("." + modal_no).find(".total_tons").val()).toFixed(2);
			$("." + modal_no).parent().parent().find(".quantity").val(SubItemsQuantity).trigger('keyup');
			$("." + modal_no).parent().parent().find(".boxes").val(SubItemsBoxes);
			$("." + modal_no).parent().parent().find(".tons").val(SubItemstons);
			calculateCommission(modal_no)
			$("." + modal_no).modal("hide");
			calculateTotalBoxes();
			calculateTotalTons();
			getTotalCredit();
		}

		function specificationsBtn() {
			$(".pq_table tbody tr").each(function() {
				var sr = $(this).find(".sr_no").html();
				if ($(this).find(".SubItemModal" + sr).html() != undefined) {
					modal_no = 'SubItemModal' + sr;
					$(this).find(".brand_md_button").html('<a data-toggle="modal" data-target=".' + modal_no + '"class="md-btn specification-toggle" data-keyboard="false" data-backdrop="static"> SubItem Specifications </a>');
				}
			});
		}

		function GetBrands(val_this) {
			var item = $(val_this).val();
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				data: {
					item,
					command: 'getBrands'
				},
				success: function(data) {
					$(val_this).closest('tr').find(".item_brand").html(data).trigger('chosen:updated');
				}
			});
		}

		function getQuality(val_this) {
			console.log("old quality");
			return;
			var item = $(val_this).closest('tr').find(".check_item").val();
			var finishing_type = $(val_this).closest('tr').find(".finishing_type").val();
			var brand = $(val_this).val();
			var project_id = $('.sub_location').val();
			var location_id = $('.location_id').val();
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				data: {
					item,
					brand,
					project_id,
					location_id,
					finishing_type,
					command: 'getQualities'
				},
				success: function(data) {
					$(val_this).closest('tr').find(".item_quality").html(data).trigger('chosen:updated');
				}
			});
		}

		function ChangeSubItemModalNAmes(modal_no, sr) {
			$("." + modal_no + " .SubItemCode").attr("name", "Sub_item_code" + sr + '[]');
			$("." + modal_no + " .SubITemID").attr("name", "Sub_item_id" + sr + '[]');
			$("." + modal_no + " .chosen_article").attr("name", "Sub_item_article" + sr + '[]');
			$("." + modal_no + " .inv_warehouse").attr("name", "Sub_item_warehouse" + sr + '[]');
			$("." + modal_no + " .inv_section").attr("name", "Sub_item_section" + sr + '[]');
			$("." + modal_no + " .inv_color").attr("name", "Sub_item_color" + sr + '[]');
			$("." + modal_no + " .inv_quality").attr("name", "Sub_item_quality" + sr + '[]');
			$("." + modal_no + " .sub-item-quantity").attr("name", "Sub_item_value" + sr + '[]');
			$("." + modal_no + " .sub-item-unit").attr("name", "Sub_item_unit" + sr + '[]');
			$("." + modal_no + " .inv_quantity").attr("name", "Sub_item_quantity" + sr + '[]');
			$("." + modal_no + " .inv_boxes").attr("name", "Sub_item_boxes" + sr + '[]');
			$("." + modal_no + " .inv_tons").attr("name", "Sub_item_tons" + sr + '[]');
		}

		function checkContract(val_this) {
			var item_id = $(val_this).parent().parent().parent().parent().find(".check_item").val();
			var project_id = $('.sub_location').val();
			var location_id = $('.location_id').val();
			var sale_type = $(".sale_type").val();
			var sales_person = 0;
			if (sale_type == 'agent') {
				sales_person = $(".sales_person").val();
			}
			var customer = $(".inv_transporter_id").val();
			var brand = $(val_this).closest('tr').find(".item_brand").val();
			var finishing_type = $(val_this).closest('tr').find(".finishing_type").val();
			var quality = $(val_this).closest('tr').find(".item_quality").val();
			$.ajax({
				url: '<?php echo ADMIN_URL; ?>ajax/check-sale-contract.php',
				method: 'POST',
				data: {
					item_id,
					project_id,
					brand,
					sale_type,
					sales_person,
					quality,
					finishing_type,
					customer,
					location_id
				}
			}).done(function(result) {
				res = $.parseJSON(result);
				$(val_this).parent().parent().parent().parent().find('.rate').val(res.actual_rate);
				if ($(".commission_check").is(":checked")) {
					$(val_this).parent().parent().parent().parent().find('.c_sp_commision').val(res.commission);
					$(val_this).parent().parent().parent().parent().find('.c_sp_category').val(res.category);
				}
				if (res == false) {
					alert("Please Add the Contract againt this item. 'THANKS'");
				} else if (res.error) {
					alert("Your Contract Date is Over");
				}
			}).fail(function(jqXHR, textStatus) {
				alert("Request  are failed:" + textStatus);
			});
		}

		function showRateModal(val_this) {
			sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
			$('.chosen-supplier').chosen('destroy');
			if ($('.rate_modal' + sr_no).html() != undefined) {
				modal_no = 'rate_modal' + sr_no;
				$('.rate_modal' + sr_no, ).modal('show');
			} else {
				modal_no = 'rate_modal' + sr_no;
				$('.specification-toggle').remove();
				modal = $(".rate_modal:first").clone();
				$(val_this).parent().parent().parent().parent().find('.mat_modal').append(modal);
				$(val_this).parent().parent().parent().parent().find('.rate_modal').addClass('rate_modal' + sr_no);
				$('.rate_modal' + sr_no).removeClass('rate_modal');
				changeModalName(modal_no, sr_no);
				$('.rate_modal' + sr_no + " .rate-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveRate("' + modal_no + '","' + sr_no + '")> Save invoice </button>');
				$('.rate_modal' + sr_no, ).modal('show');
				$(val_this).parent().parent().parent().parent().find(".invoice_md_button").append('<a data-toggle="modal" data-target=".' + modal_no + '"class=" md-btn" data-keyboard="false" data-backdrop="static">receipt</a>');
			}
			$('.chosen-supplier').chosen({
				width: '100%'
			});
		}

		function getDelivery(val_this) {
			if ($(val_this).is(":checked") == true) {
				$(val_this).next('input.deliveryValue').val("exfactory");
				$(val_this).parents(".show_mt_modal").show();
				$(val_this).parent().parent().parent().find(`.supplier-select`).prop('disabled', false).trigger("chosen:updated");
				$(val_this).parent().parent().parent().find('.mat_modal').append('<input type="hidden" name="material[]" class="material_status" value="yes">');
			} else if ($(val_this).is(":checked") == false) {
				$(val_this).next('input.deliveryValue').val("exdelivery");
				$(val_this).parents(".show_mt_modal").hide();
				$(val_this).parent().parent().parent().find(`.supplier-select`).prop('disabled', true).trigger("chosen:updated");
				$(val_this).parent().parent().parent().find('.material_status').remove('.material_status');
			}
		}

		function showCarrMat(val_this) {
			material_status = $(val_this).parent().parent().parent().parent().parent().find(".delivery_term").val();
			sr_no = $(val_this).parent().parent().parent().parent().parent().find(".sr_no").html();
			if ($('.carr_mat_modal' + sr_no).html() != undefined) {
				$('.costCenterModal' + sr_no + " .carr-mat-button").html('');
				modal_no = 'carr_mat_modal' + sr_no;
				$('.carr_mat_modal' + sr_no, ).modal('show');
			} else {
				if (material_status == 'exfactory') {
					modal_no = 'carr_mat_modal' + sr_no;
					modal = $(".carr_mat_modal:first").clone();
					changeCarrMat(modal_no, sr_no);
					$(val_this).parent().parent().parent().parent().parent().find('.mat_modal').append(modal);
					$(val_this).parent().parent().parent().parent().find('.carr_mat_modal').addClass('carr_mat_modal' + sr_no);
					$('.carr_mat_modal' + sr_no).removeClass('carr_mat_modal');
					$('.carr_mat_modal' + sr_no + " .carr-mat-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveCarMAt("' + modal_no + '")>Save</button>');
					$('.carr_mat_modal' + sr_no, ).modal('show');
				} else {
					$(".carr_mat_modal").modal('show');
				}
			}
		}

		function calculate_carr_rate(val_this) {
			carriage = parseInt($(val_this).parent().parent().parent().find('.carriage').val());
			material = parseInt($(val_this).parent().parent().parent().find('.material').val());
			total_rate = carriage + material;
			$(val_this).parent().parent().parent().find('.rate').val(total_rate);
			$(val_this).parent().parent().parent().find('.rate').trigger("keyup");
		}

		function changeModalName(modal, sr_no) {
			$('.' + modal_no + " .inv_delivery_no").attr('name', "inv_delivery_no" + sr_no + "[]");
			$('.' + modal_no + " .inv_supplier_no").attr('name', "inv_supplier_no" + sr_no + "[]");
			$('.' + modal_no + " .inv_veh_no").attr('name', "inv_veh_no" + sr_no + "[]");
			$('.' + modal_no + " .inv_bilty_no").attr('name', "inv_bilty_no" + sr_no + "[]");
			$('.' + modal_no + " .inv_receipt_no").attr('name', "inv_receipt_no" + sr_no + "[]");
			$('.' + modal_no + " .inv_quantity").attr('name', "inv_quantity" + sr_no + "[]");
		}

		function changeBrandName(modal, sr_no) {
			$('.' + modal_no + " .inv_brand").attr('name', "inv_brand" + sr_no + "[]");
			$('.' + modal_no + " .inv_quality").attr('name', "inv_quality" + sr_no + "[]");
			$('.' + modal_no + " .inv_color").attr('name', "inv_color" + sr_no + "[]");
			$('.' + modal_no + " .inv_section").attr('name', "inv_section" + sr_no + "[]");
			$('.' + modal_no + " .inv_article").attr('name', "inv_article" + sr_no + "[]");
			$('.' + modal_no + " .inv_finishingType").attr('name', "inv_finishingType" + sr_no + "[]");
			$('.' + modal_no + " .inv_warehouse").attr('name', "inv_warehouse" + sr_no + "[]");
			$('.' + modal_no + " .inv_item_rate").attr('name', "inv_rate" + sr_no + "[]");
			$('.' + modal_no + " .inv_quantity").attr('name', "inv_quantity" + sr_no + "[]");
		}

		function changeCarrMat(modal, sr_no) {
			$('.' + modal_no + " .inv_material").attr('name', "inv_material" + sr_no + "[]");
			$('.' + modal_no + " .inv_carriage").attr('name', "inv_carriage" + sr_no + "[]");
		}

		function invoice_total_quantity(val_this) {
			var total_quantity = 0;
			$(val_this).parent().parent().parent().find('.invoice_row .md_quantity').each(function(val) {
				total_quantity += parseFloat($(this).val());
				$(val_this).parent().parent().parent().parent().parent().find('.total_quantity').html(total_quantity);
				$(val_this).parent().parent().parent().parent().parent().find('.inp_total_quantity').val(total_quantity);
			});
		}


		function getSubwareHouseITemQuantity(val_this) {
			var Sub_item_id = $(val_this).parent().parent().parent().find(".SubITemID").val();
			var Sub_item_article = $(val_this).parent().parent().parent().find(".SubItemArticle").val();
			var Sub_item_quality = $(val_this).parent().parent().parent().find(".md_quality").val();
			var head = $(".location_id").val();
			var conversion = $(val_this).closest('.brand_qual_modal').parent().find(".conversionToAlternativeUnit").val();
			var sub_head = $(".sub_location").val();
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				data: {
					head,
					sub_head,
					Sub_item_id,
					Sub_item_article,
					Sub_item_quality,
					command: 'getQuantity'
				},
				success: function(result) {
					$(val_this).parent().parent().parent().find(".available_quantity").text('');
					$(val_this).parent().parent().parent().find(".available_quantity").text(result);
					$(val_this).parent().parent().parent().find(".available_quantity_boxes").text('');
					$(val_this).parent().parent().parent().find(".available_quantity_boxes").text((result / conversion).toFixed(2));
					if (parseFloat(result) > 0) {
						$(val_this).parent().parent().parent().find(".availability").text('');
						$(val_this).parent().parent().parent().find(".availability").text('Available');
					} else {
						$(val_this).parent().parent().parent().find(".availability").text('');
						$(val_this).parent().parent().parent().find(".availability").text('Unavailable');
					}
				}
			}).fail(function(jqXHR, textStatus) {
				$(val_this).parent().parent().parent().find(".available_quantity").text('0');
				$(val_this).parent().parent().parent().find(".available_quantity_boxes").text('0');
				$(val_this).parent().parent().parent().find(".availability").text('Unavailable');
			});
		}

		function add_invoice_row(val_this) {
			$('.chosen_party').chosen('destroy');
			invoice_clone_row = $(val_this).parent().parent().parent().find('.invoice_row:last');
			$(val_this).parent().parent().parent().parent().find(".invoice_body").append($(invoice_clone_row).clone());
			$('.chosen-transporter').chosen('destroy');
			if ($(invoice_clone_row).find('.showCharges').is(":checked") == true) {
				$(val_this).parent().parent().parent().find('.invoice_row:last .showCharges').prop("disabled", true);
			}
			trans_val = $(val_this).parent().parent().parent().find('.invoice_row:last .chosen-transporter').find("option:selected").val();
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_quantity').val(0);
			$(val_this).parent().parent().parent().find('.invoice_row:last .chosen-transporter').val(trans_val).prop('disabled', true).trigger("chosen:updated");
			$('.chosen_party').chosen({
				width: '100%'
			});
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_veh').val('');
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_bilty').val('');
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_receipt').val('');
		}

		function remove_invoice_row(val_this) {
			val = $(val_this).parent().parent().parent().parent().find('.invoice_row .md_quantity');
			$(val_this).parent().parent('tr.invoice_row').remove();
			invoice_total_quantity(val);
		}

		function getSubledger() {
			var name = $('#sub_ledger_model .sub_ledger_name').val();
			var id_card = $('#sub_ledger_model .sub_ledger_id_card').val();
			var phone = $('#sub_ledger_model .sub_ledger_mobile').val();
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				data: {
					name,
					id_card,
					phone,
					command: 'getSubledger'
				},
				success: function(subledger) {
					$("#sub_ledger_model .table tbody").html("")
					$("#sub_ledger_model .table tbody").html(subledger)
				}
			})
		}

		function saveRate(modal_no, val_this) {
			$('.' + modal_no).modal('hide');
			total_quantity_value = parseInt($('.' + modal_no).find('.inp_total_quantity').val());
			var deliveryStatus = $('.' + modal_no).find('.delivery_term').val();
			if (deliveryStatus == "exfactory") {
				$('.' + modal_no).parent().find('.show_mt_modal').show();
			}
			$('.' + modal_no).parent().parent().find('.quantity').val(total_quantity_value);
			$('.' + modal_no).parent().parent().find('.quantity').val(total_quantity_value).trigger("keyup");
		}

		function showBrandModal(val_this) {
			$('.chosen_brand').chosen('destroy');
			$('.chosen_quality').chosen('destroy');
			$('.chosen_color').chosen('destroy');
			$('.chosen_article').chosen('destroy');
			$('.chosen_warehouse').chosen('destroy');
			$('.chosen_finishingType').chosen('destroy');
			$('.chosen_section').chosen('destroy');
			var location_id = $(".location_div .location_id").val();
			var sublocation_id = $(".location_div .sub_location").val();
			$(val_this).parent().parent().parent().find('.no_brand_quantity_color').html('');
			var item_id = $(val_this).val();
			sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
			if ($('.brand_quality_modal' + sr_no).html() != undefined) {
				modal_no = 'brand_quality_modal' + sr_no;
				$('.brand_quality_modal' + sr_no, ).modal('show');
			} else {
				modal_no = 'brand_quality_modal' + sr_no;
				modal = $(".brand_quality_modal:first").clone();
				var getSaleItemQuantity = 'getSaleItemQuantity(' + location_id + ',' + sublocation_id + ',' + item_id + ',"' + modal_no + '",this)';
				$(val_this).parent().parent().parent().parent().find('.brand_qual_modal').append(modal);
				$(val_this).parent().parent().parent().parent().find('.brand_quality_modal').addClass('brand_quality_modal' + sr_no);
				$('.brand_quality_modal' + sr_no).removeClass('brand_quality_modal');
				changeBrandName(modal_no, sr_no);
				$('.brand_quality_modal' + sr_no).find(".purchase-img").removeAttr("name");
				$('.brand_quality_modal' + sr_no).find(".purchase-img").attr("name", "purchaseImg" + sr_no + '[]');
				$('.brand_quality_modal' + sr_no).find('.head_inp').val(location_id);
				$('.brand_quality_modal' + sr_no).find('.subhead_inp').val(sublocation_id);
				$('.brand_quality_modal' + sr_no).find('.inv_finishingType').removeAttr('onchange');
				$('.brand_quality_modal' + sr_no).find('.inv_finishingType').attr('onchange', getSaleItemQuantity);
				$('.brand_quality_modal' + sr_no).find('.inv_finishingType').removeAttr('onkeyup');
				$('.brand_quality_modal' + sr_no).find('.inv_quantity').attr('onkeyup', 'QuantityAdd(this,"' + modal_no + '")');
				$('.brand_quality_modal' + sr_no + " .brand-quality-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveBrandQuatity("' + modal_no + '")> Save </button>');
				$('.brand_quality_modal' + sr_no).modal('show');
				$(val_this).parent().parent().parent().parent().find(".brand_md_button").append('<a data-toggle="modal" data-target=".' + modal_no + '"class="md-btn specification-toggle" data-keyboard="false" data-backdrop="static"> Specification </a>');
			}
			$('.chosen_brand').chosen({
				width: '100%'
			});
			$('.chosen_quality').chosen({
				width: '100%'
			});
			$('.chosen_color').chosen({
				width: '100%'
			});
			$('.chosen_article').chosen({
				width: '100%'
			});
			$('.chosen_warehouse').chosen({
				width: '100%'
			});
			$('.chosen_finishingType').chosen({
				width: '100%'
			});
			$('.chosen_section').chosen({
				width: '100%'
			});
		}

		function saveBrandQuatity(modal_no) {
			$('.' + modal_no).modal('hide');
		}

		function saveCarMAt(modal_no) {
			$('.' + modal_no).modal('hide');
			carriage_value = parseFloat($('.' + modal_no).find('.md_carriage').val());
			material_value = parseFloat($('.' + modal_no).find('.md_material').val());
			rate_value = carriage_value + material_value;
			if (carriage_value != "" && material_value != "") {
				$('.' + modal_no).parent().find('.material_mod').val("yes");
				$('.' + modal_no).parent().find('.carriage_modal_val').val(carriage_value);
				$('.' + modal_no).parent().find('.material_modal_val').val(material_value);
			}
			$('.' + modal_no).parent().parent().find('.rate').val(rate_value);
			val = $('.' + modal_no).parent().parent().find('.rate').val();
			$('.' + modal_no).parent().parent().find('.rate').val(rate_value).trigger("keyup");
		}

		$(document).ready(function() {
			var date_input = $('.del_date');
			var container = $('.bootstrap-iso form').length > 0 ? $('.bootstrap-iso form').parent() : "body";
			date_input.datepicker({
				format: 'dd-mm-yyyy',
				container: container,
				todayHighlight: true,
				autoclose: true,
			});
		})

		$(document).ready(function() {
			var date_input = $('.rate-validity');
			var container = $('.bootstrap-iso form').length > 0 ? $('.bootstrap-iso form').parent() : "body";
			date_input.datepicker({
				format: 'dd-mm-yyyy',
				container: container,
				todayHighlight: true,
				autoclose: true,
			});
		})

		function goBack() {
			window.history.back();
		}

		$(document).ready(function() {
			$('.chosen-select').chosen({
				width: "100%"
			});
			$('.chosen-transporter').chosen({
				width: "100%"
			});
			$('.chosen-supplier').chosen({
				width: "100%"
			});
		})

		function group_item(val_this) {
			var group_id = "";
			group_id = $(val_this).val();
			if (group_id) {
				$.ajax({
					type: 'POST',
					url: '<?php echo ADMIN_URL; ?>ajax/item_name.php',
					dataType: "html",
					data: 'group_id=' + group_id,
					success: function(option) {
						$(val_this).parent().parent().parent().find('.selecteditem').html("");
						$(val_this).parent().parent().parent().find('.selecteditem').append(option);
						$(val_this).parent().parent().parent().find('.selecteditem').trigger("chosen:updated");
					}
				});
			}
		};

		function projectlocation(val_this) {
			var project_id = "";
			project_id = $(val_this).val();
			if (project_id) {
				$.ajax({
					type: 'POST',
					url: "<?php echo ADMIN_URL; ?>ajax/location.php",
					dataType: "html",
					data: 'project_id=' + project_id,
					success: function(option) {
						$(val_this).parent().parent().find('.proj_location').html("");
						$(val_this).parent().parent().find('.proj_location').append(option);
						$(val_this).parent().parent().find('.proj_location').trigger("chosen:updated");
					}
				});
			}
		};

		function calculate(val) {
			var discount = 0;
			var net = 0;
			var rate = 0;
			if ($(val).closest('tr').find('.discount_type').val() == 'Rs') {
				rate = parseFloat($(val).closest('tr').find('.rate').val());
				discount = parseFloat($(val).closest('tr').find('.discount').val());
				disc = rate - discount;
				$(val).closest('tr').find('.net_rate_area').text(disc);
				parseFloat($(val).closest('tr').find('.net_rate').val(disc));
				qty = parseFloat($(val).closest('tr').find('.quantity').val());
				total_amt = qty * rate;
				amount = disc * qty;
				total_discount = total_amt - amount;
				rem_amount = total_amt - total_discount;
				$(val).closest('tr').find('.amount').val(rem_amount.toFixed(2));
				$(val).closest('tr').find('.hidden_disc_amt_val').val(Number(total_discount));
				$(val).closest('tr').find('.hidden_disc_amt').text('');
				$(val).closest('tr').find('.hidden_discount').val(total_discount);
				$(val).closest('tr').find(".individusl_discount").text("");
			}
			if ($(val).closest('tr').find('.discount_type').val() == '%') {
				rate = parseFloat($(val).closest('tr').find('.rate').val());
				discount = parseFloat($(val).closest('tr').find('.discount').val());
				amount = discount * rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				$(val).closest('tr').find('.net_rate_area').text(net_rate);
				parseFloat($(val).closest('tr').find('.net_rate').val(net_rate));
				qty = parseFloat($(val).closest('tr').find('.quantity').val());
				total_amt = rate * qty;
				amount = net_rate * qty;
				total_discount = total_amt - amount;
				individual = rate - net_rate;
				$(val).closest('tr').find(".individusl_discount").text(individual);
				$(val).closest('tr').find('.amount').val(rem_amount.toFixed(2));
				$(val).closest('tr').find('.hidden_disc_amt_val').val(Number(total_discount));
				$(val).closest('tr').find('.hidden_disc_amt').text('');
				$(val).closest('tr').find('.hidden_discount').val(total_discount);
			}
			$('.discount').trigger('onkeyup');
			total = 0;
			var discount_amount = 0;
			$('.amount').each(function() {
				total += parseFloat($(this).val());
				$('#total').val(total);
				$('.total').text(total);
				discount_amount = $('#discount').val();
				if (discount_amount == 0 || isNaN(discount_amount)) {
					$('#net').val(total);
					$('.net').text(total);
					grand();
				}
			})
			var tot_quantity = 0;
			$('.quantity').each(function() {
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			})
			calculateTotalDiscount();
			calculateTotalBoxes();
			calculateTotalTons();
			getTotalCredit();
		}

		function calculate_quantity(val_this) {
			var alternate_unit = $(val_this).parent().parent().parent().find(".alternative_unit").val();
			var item_unit = $(val_this).parent().parent().parent().find(".main_item_unit").val();
			var conversion = $(val_this).parent().parent().parent().find(".conversionToAlternativeUnit").val();
			var kg_conversion = $(val_this).parent().parent().parent().find(".conversionToKg").val();
			$(val_this).parent().parent().parent().find('.rate').trigger("keyup");
			$('#discount').trigger('onkeyup');
		}

		function calculate_balance(val) {
			var net = 0;
			if ($(val).closest('tr').find('.discount_type').val() == '%') {
				rate = parseFloat($(val).closest('tr').find('.rate').val());
				discount = parseFloat($(val).closest('tr').find('.discount').val());
				amount = discount * rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				individual = rate - net_rate;
				$(val).closest('tr').find(".individusl_discount span").text(individual);
				$(val).closest('tr').find('.net_rate_area').text(net_rate);
				parseFloat($(val).closest('tr').find('.net_rate').val(net_rate));
				qty = parseFloat($(val).closest('tr').find('.quantity').val());
				total_amt = rate * qty;
				amount = net_rate * qty;
				total_discount = total_amt - amount;
				$(val).closest('tr').find('.amount').val(amount.toFixed(2));
				$(val).closest('tr').find('.hidden_discount').val(total_discount);
			} else {
				rate = parseFloat($(val).closest('tr').find('.rate').val());
				discount = parseFloat($(val).closest('tr').find('.discount').val());
				disc = rate - discount;
				$(val).closest('tr').find('.net_rate_area').text(disc);
				parseFloat($(val).closest('tr').find('.net_rate').val(disc));
				qty = parseFloat($(val).closest('tr').find('.quantity').val());
				total_amt = qty * rate;
				amount = disc * qty;
				total_discount = total_amt - amount;
				$(val).closest('tr').find('.amount').val(amount.toFixed(2));
				$(val).closest('tr').find('.hidden_discount').val(total_discount);
				$(val).closest('tr').find(".individusl_discount").text("");
			}
			$('.discount').trigger('onkeyup');
			total = 0;
			var discount_amount = 0;
			$('.amount').each(function() {
				total += parseFloat($(this).val());
				$('#total').val(total);
				$('.total').text(total);
				discount_amount = $('#discount').val();
				if (discount_amount == 0 || isNaN(discount_amount)) {
					$('#net').val(total);
					$('.net').text(total);
					grand();
				}
			});
			var tot_quantity = 0;
			$('.quantity').each(function() {
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			});
			// calculateTotalEff();
			calculateTotalDiscount();
			calculateTotalBoxes();
			calculateTotalTons();
		}

		function calculate_discount(val) {
			var net = 0;
			var sr_no = $(val).closest('tr').find('.input_sr').val();
			// $('.discount').trigger('onkeyup');
			if ($(val).closest('tr').find('.discount_type').val() == '%') {
				rate = parseFloat($(val).closest('tr').find('.rate').val());
				discount = parseFloat($(val).closest('tr').find('.inv_discount').val());
				amount = discount * rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				individual = rate - net_rate;
				$(val).closest('tr').find(".individusl_discount").text(individual);
				$(val).closest('tr').find('.net_rate_area').text(net_rate);
				parseFloat($(val).closest('tr').find('.net_rate').val(net_rate));
				qty = parseInt($(val).closest('tr').find('.quantity').val());
				total_amt = rate * qty;
				amount = net_rate * qty;
				total_discount = total_amt - amount;
				rem_amount = total_amt - total_discount;
				$(val).closest('tr').find('.amount').val(rem_amount.toFixed(2));
				$(val).closest('tr').find('.hidden_disc_amt_val').val(Number(total_discount));
				$(val).closest('tr').find('.hidden_discount').val(total_discount);
			} else {
				rate = parseFloat($(val).closest('tr').find('.rate').val());
				discount = parseFloat($(val).closest('tr').find('.inv_discount').val());
				disc = rate - discount;
				$(val).closest('tr').find('.net_rate_area').text(disc);
				parseFloat($(val).closest('tr').find('.net_rate').val(disc));
				qty = parseFloat($(val).closest('tr').find('.quantity').val());
				total_amt = qty * rate;
				amount = disc * qty;
				total_discount = total_amt - amount;
				rem_amount = total_amt - total_discount;
				$(val).closest('tr').find('.amount').val(rem_amount.toFixed(2));
				$(val).closest('tr').find('.hidden_disc_amt_val').val(Number(total_discount));
				$(val).closest('tr').find('.hidden_discount').val(total_discount);
				$(val).closest('tr').find(".individusl_discount").text("");
			}
			total = 0;
			var discount_amount = 0;
			$('.amount').each(function() {
				total += parseFloat($(this).val());
				$('#total').val(total);
				$('.total').text(total);
				discount_amount = $('#discount').val();
				if (discount_amount == 0 || isNaN(discount_amount)) {
					$('#net').val(total);
					$('.net').val(total);
					grand();
				}
			})
			var tot_quantity = 0;
			$('.quantity').each(function() {
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			})
			calculateTotalDiscount();
			modal = "SubItemModal" + sr_no;
			calculateCommission(modal);
			calculateTotalBoxes();
			calculateTotalTons();
		}

		function netCharges(val) {
			var net = 0;
			total = parseFloat($("#total").val());
			discount = parseFloat($(val).val());
			if (net == '' || isNaN(net)) {
				net == 0;
			};
			net = total - discount;
			$('#net').val(net);
			$('.net').text(net);
			grand();
		}
		<?php if (isset($_REQUEST) && $_REQUEST['agent'] != '') { ?>
			AgentSelected();

		<?php } ?>

		function EnableCommission(this_val) {
			if ($(this_val).is(":checked")) {
				$(".sales_person_div select").chosen("destroy").removeAttr("disabled");
				$(".commission_td").removeClass("hidden");
				$(".commission_heading").removeClass("hidden")
				$(".commission_td").html('<div class="input-group"><input class="hidden_commission commission form-control" readonly placeholder="Commission" value="0" name="hidden_commission[]"><span class="input-group-addon"><b>PKR</b></span></div>');
				AgentSelected();
			} else {
				$(".sales_person_div select").chosen("destroy").attr("disabled", 'disabled');
				$(".commission_td").addClass("hidden");
				$(".commission_heading").addClass("hidden");
				$(".commission_td").html('');
				AgentSelected();
			}
			$(".sales_person_div select").chosen({
				width: '100%'
			})
		}

		function AgentSelected() {
			if ($('.commission_check').is(":checked")) {
				$('#c-type').chosen('destroy');
				$('#c-type').html('');
				$('#c-type').html('<option value="agent" selected>Agent</option>');
				$('#c-type').chosen({
					width: "100%"
				});
				GetCustomers();
			} else {
				$('#c-type').chosen('destroy');
				$('#c-type').html('');
				$('#c-type').html('<option value="project">Project</option><option value="retail_sale">Retail Sale</option><option value="whole_sale">Whole Sale</option><option value="company_sale">Company Sale</option><option value="distributer">Distributer</option>');
				$('#c-type').chosen({
					width: "100%"
				});
				GetCustomers();

				// $('.inv_transporter_id').chosen('destroy');
				// $('.inv_transporter_id').html('');
				// $('.inv_transporter_id').html('<option value="0" selected>Select Customer</option>');






			}
			// 		$('.contract_type').html(data);
			//    $('.contract_type').html('');
		}

		function getSection(val_this) {
			var item_id = $(val_this).closest('tr').find(".check_item ").val();
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'POST',
				data: {
					item_id,
					warehouse_id: $(val_this).val(),
					command: 'getSection'
				},
				success: function(data) {
					if (data != '') {
						$(val_this).parent().parent().find(".inv_section").chosen("destroy");
						$(val_this).parent().parent().find(".inv_section").html("");
						$(val_this).parent().parent().find(".inv_section").html(data);
						$(val_this).parent().parent().find(".inv_section").chosen({
							width: '100%'
						});
					}
				}
			});
		}

		function grand() {
			var grd_total = 0;
			var net = 0;
			var net = parseFloat($('#net').val());
			var l_charges = parseFloat($('#loading_charges').val());
			var u_charges = parseFloat($('#unloading_charges').val());
			var o_charges = parseFloat($('#other_charges').val());
			var f_charges = parseFloat($('#freight_charge').val());
			if (net == '' || isNaN(net)) {
				net == 0;
				net = parseFloat($("#total").val());
			};

			if (l_charges == '' || isNaN(l_charges)) {
				l_charges = 0;
			};

			if (u_charges == '' || isNaN(u_charges)) {
				u_charges = 0;
			};
			if (o_charges == '' || isNaN(o_charges)) {
				o_charges = 0;
			};
			if (f_charges == '' || isNaN(f_charges)) {
				f_charges = 0;
			};
			grd_total = parseFloat(net + l_charges + u_charges + o_charges + f_charges);

			$("#grand_total").val(grd_total);
			$(".grand_total").text(grd_total);
			calculateTotalEff();
		}

		function calculateTotalDiscount() {
			total_discount = 0;
			$(".hidden_discount").each(function() {
				if (parseFloat($(this).val()) > 0) {
					total_discount += parseFloat($(this).val());
				}
			});
			$("#total_discount").val(total_discount);
			$("#total_discount_txt").text(Math.floor(total_discount));
		}

		function calculateTotalcommission() {
			total_commission = 0;
			$(".hidden_commission").each(function() {
				if (parseFloat($(this).val()) > 0) {
					total_commission += parseFloat($(this).val());
				}
			});
			$(".total_commission").val(total_commission);
			$("tfoot .commission_heading").text(total_commission);
		}

		function add_row(val_this) {
			$(".chosen-select").chosen('destroy');
			$(".chosen_brand").chosen('destroy');
			$(".chosen_party").chosen('destroy');
			$(".chosen-project").chosen('destroy');
			$(".pq_body").append($('.pq_row:first').clone());
			$('.pq_row:last').find('.quantity').val('');
			SR_NO = $('.pq_row:last').find('.sr_no').text();
			$('.pq_row:last').find('.rate_modal' + SR_NO).remove();
			$('.pq_row:last').find('.carr_mat_modal' + SR_NO).remove();
			$('.pq_row:last').find('.brand_quality_modal' + SR_NO).remove();
			$('.pq_row:last').find('.SubItemModal' + SR_NO).remove();
			$('.pq_row:last').find('.chosen-select').val('');
			$('.pq_row:last').find('.show_mt_modal').hide();
			$('.pq_row:last').find('.eff_net_rate').val("0");
			$('.pq_row:last').find('.eff_net_rate_area').html("");
			$('.pq_row:last').find('.rate').val('');
			$('.pq_row:last').find('.discount').val('0');
			$('.pq_row:last').find('.net_rate').val('');
			$('.pq_row:last').find('.serial').val("");
			$('.pq_row:last').find('.hidden_commission').val('');
			$('.pq_row:last').find('.material_status').remove();
			$('.pq_row:last').find('.net_rate_area').text('');
			$('.pq_row:last').find('.qty_symbol').text('unit');
			$('.pq_row:last').find('.amount').val('');
			$('.pq_row:last').find('.material_mod').val("no");
			$('.pq_row:last').find('.md-btn').remove();
			$('.pq_row:last').find('.qty_symbol_per').text('unit');
			$('.pq_row:last').find('.hidden_discount').val('');
			$('.pq_row:last').find('.individusl_discount span').text('');
			$('.pq_row:last').find('.hidden_disc_amt_val').val('');
			$('.pq_row:last').find('.hidden_disc_amt').text('');
			rowCount = $('.pq_table >tbody >tr').length;
			$('.pq_body tr:last .pq_dr_no').text(rowCount + 1 - 1);
			$('.pq_body tr:last .input_sr').val(rowCount + 1 - 1);
			$(".chosen-select").chosen({
				width: '100%'
			});
			$(".chosen-project").chosen({
				width: '100%'
			});
			$(".chosen_brand").chosen({
				width: '100%'
			});
			$(".chosen_party").chosen({
				width: '100%'
			});
		};

		function remove_row(val) {
			$(val).parent().parent('tr').remove();
			total = 0;
			var discount_amount = 0;
			$('.amount').each(function() {
				total += Number($(this).val());
				$('#total').val(total);
				$('.total').text(total);
			})
			discount_amount = $('#discount').val();
			if (discount_amount == 0 || isNaN(discount_amount)) {
				$('#net').val(total);
				$('.net').text(total);
				grand();
			}
			var tot_quantity = 0;
			$('.quantity').each(function() {
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			})
		}

		function addItem() {
			if (ite.valid()) {
				$(".item-loading").show();
				$(".item-button").hide();
				$.ajax({
					url: "<?php echo ADMIN_URL; ?>ajax/inventory-popup.php",
					type: "POST",
					data: {
						'item-data': ite.serialize()
					}
				}).done(function(msg) {
					$.ajax({
						url: "<?php echo ADMIN_URL; ?>ajax/inventory-popup.php",
						type: "POST",
						data: {
							'command': 'Add_item'
						}
					}).done(function(result) {
						res = $.parseJSON(result);
						$(".selecteditem").html('');
						$(".selecteditem").html(res.html);
						$('.selecteditem').trigger("chosen:updated");
						$("#add_item").modal('hide');
						$(".item-loading").hide();
						$(".item-button").show();
					}).fail(function(jqXHR, textStatus) {
						alert("Request  are failed: " + textStatus);
						$("#add_item").modal('hide');
						$(".item-loading").hide();
						$(".item-button").show();
					});
				}).fail(function(jqXHR, textStatus) {
					alert("Request is failed: " + textStatus);
					$("#add_item").modal('hide');
					$(".item-loading").hide();
					$(".item-button").show();
				});
			}
		}

		function get_sub_location(val_this) {
			$.ajax({
					url: 'add-sale-invoice.php',
					type: 'POST',
					data: {
						item_loc: $(val_this).val(),
						command: 'get_sub_loc'
					},
				})
				.done(function(data) {
					$('.sub_location').chosen('destroy');
					$('.sub_location').html('');
					$('.sub_location').html(data);
					$('.sub_location').chosen({
						width: '100%'
					});

				})
		}

		function fileChange(e) {
			$.each(e.target.files, function(index, value) {
				var file = value;
				if (file.type == "image/jpeg" || file.type == "image/png") {
					var reader = new FileReader();
					reader.onload = function(readerEvent) {
						var image = new Image();
						image.onload = function(imageEvent) {
							var max_size = 500;
							var w = image.width;
							var h = image.height;
							if (w > h) {
								if (w > max_size) {
									h *= max_size / w;
									w = max_size;
								}
							} else {
								if (h > max_size) {
									w *= max_size / h;
									h = max_size;
								}
							}
							var canvas = document.createElement('canvas');
							canvas.width = w;
							canvas.height = h;
							canvas.getContext('2d').drawImage(image, 0, 0, w, h);
							if (file.type == "image/jpeg") {
								var dataURL = canvas.toDataURL("image/jpeg", 1.0);
							} else {
								var dataURL = canvas.toDataURL("image/png");
							}
							$(".append_image").append("<input type='hidden' name='img[]' value=" + dataURL + ">");
						}
						image.src = readerEvent.target.result;
					}
					reader.readAsDataURL(file);
				} else {
					document.getElementById('inp_file').value = '';
					alert('Please only select images in JPG- or PNG-format.');
				}
			});
		}
		document.getElementById('inp_file').addEventListener('change', fileChange, false);

		if (window.history.replaceState) {
			window.history.replaceState(null, null, window.location.href);
		}

		function calculateTotalEff() {
			var current_value = 0;
			var total_value = 0;
			if ($("#discount_checkbox").prop("checked") == false) {
				total_value = parseInt($(".gross-total").text());
				$(".discount_checkbox").val();
				if ($(".chr_loading_amount").val() != '' || $(".chr_unloading_amount").val() != '' || $(".chr_other_amount").val() != '' || $('.chr_freight_amount').val() != '') {
					current_value = parseInt($(".grand_total").text());
				} else {
					current_value = parseInt($(".net").text());
				}
				calculateeftv(total_value, current_value);
			} else {
				total_value = parseInt($(".gross-total").text());
				if ($(".chr_loading_amount").val() != '' || $(".chr_unloading_amount").val() != '' || $(".chr_other_amount").val() != '' || $('.chr_freight_amount').val() != '') {
					current_value = parseInt($(".grand_total").text());
					console.log(current_value);
				} else {
					current_value = parseInt($(".net").text());
					console.log(current_value);
				}
				calculateeftv(total_value, current_value);
			}
		}

		function getSaleItemQuantity(head, sub_head, item, modal, val_this) {
			var finishing_type = $(val_this).val();
			var this_row = $(val_this).parent().parent().parent();
			var quality = this_row.find(".inv_quality").val();
			var warehouse = this_row.find(".inv_warehouse").val();
			var section = this_row.find(".inv_section").val();
			var article = this_row.find(".inv_article").val();
			var color = this_row.find(".inv_color").val();
			var brand = this_row.find(".inv_brand").val();
			var finishing_type = this_row.find('.inv_finishingType').val();
			$.ajax({
				url: '<?php echo ADMIN_URL; ?>ajax/itemval.php',
				type: 'post',
				data: 'item_id=' + item + '&loc_id=' + head + '&quality_id=' + quality + '&warehouse_id=' + warehouse + '&section_id=' + section + '&finishing_type=' + finishing_type + '&sub_location_id=' + sub_head + '&article_id=' + article + '&brand_id=' + brand + '&color_id=' + color
			}).done(function(data) {
				data = data.trim();
				data = JSON.parse(data)
				this_row.find(".quantity_span").text(data[0]);
				this_row.find(".inv_item_rate").val(data[1]);
			});
		}

		function QuantityAdd(val_this, modal_no) {
			var alternate_unit = $("." + modal_no).parent().parent().find(".alternative_unit").val();
			var item_unit = $("." + modal_no).parent().parent().find(".main_item_unit").val();
			var conversion = $("." + modal_no).parent().parent().find(".conversionToAlternativeUnit").val();
			var kg_conversion = $("." + modal_no).parent().parent().find(".conversionToKg").val();
			var quantity = 0;
			$("." + modal_no).find('.SubItemTable tbody tr').each(function() {
				quantity += parseFloat($(this).find(".inv_quantity").val());
			});
			// text ='';
			// if(alternate_unit !='' && conversion !=0){
			// 	conversionToAlternateUnit = parseFloat($(val_this).val())/parseFloat(conversion);
			// 	conversionToAlternateUnit = conversionToAlternateUnit.toFixed(2);
			// 	conversionToKg = parseFloat($(val_this).val())/parseFloat(kg_conversion);
			// 	conversionToKg = conversionToKg.toFixed(2);
			// 	text +="( "
			// 	text +=conversionToAlternateUnit+" "+alternate_unit
			// 	if(kg_conversion >0 && conversionToKg >0 ){
			// 		text +=" & "
			// 		text +=conversionToKg+" Kg";
			// 	}
			// 	text +=" )"
			// 	$(val_this).parent().parent().find(".alterNateUnitSpan").text(text).removeClass('text-danger').css({"font-size":"14px"}).parent().css({"margin-top":"10px"});
			// }
			$("." + modal_no).find(".SubItemTable tfoot .quantity_span .quantity_b").text(quantity);
			$("." + modal_no).find(".SubItemTable tfoot .quantity_td input[type=hidden]").val(quantity);
			$(val_this).parent().find(".showQ").change();
		}

		function calculateCommission(modal) {
			if ($(".commission_check").is(":checked")) {
				$row = $("." + modal).parent().parent();
				var c_commision = parseFloat($row.find(".c_sp_commision").val());
				var c_category = $row.find(".c_sp_category").val();
				var rate = parseInt($row.find(".eff_net_rate").val());
				sp_commission = 0;
				$("." + modal).find("table tbody tr").each(function() {
					quantity = parseFloat($(this).find(".inv_quantity").val());
					commission = 0;
					if (c_category == '%') {
						sp_net = rate * (quantity);
						commission = (c_commision / 100) * sp_net;
					} else {
						commission = c_commision
					}
					sp_commission += parseFloat(commission);
				});
				$row.find(".hidden_commission").val(sp_commission);
			}
			calculateTotalcommission();

		}

		function AddSaleItemRow(val_this) {
			$(".chosen_quality").chosen("destroy");
			$(".chosen_warehouse").chosen("destroy");
			$(".chosen_section").chosen("destroy");
			$(".chosen_brand").chosen("destroy");
			$(".chosen_article").chosen("destroy");
			$(".chosen_color").chosen("destroy");
			$(".chosen_finishingType ").chosen("destroy");
			var sale_row = $(".sale_item_table .sale_item_body .sale_item_row:first").clone();
			$(".sale_item_table .sale_item_body").append(sale_row);
			$(".sale_item_table .sale_item_body .sale_item_row:last").find("select").val(0);
			$(".sale_item_row:last").find(".inv_quantity").val('');
			$(".sale_item_row:last").find(".quantity_span").text('');
			$(".chosen_quality").chosen({
				width: '100%'
			});
			$(".chosen_warehouse").chosen({
				width: '100%'
			});
			$(".chosen_section").chosen({
				width: '100%'
			});
			$(".chosen_article").chosen({
				width: '100%'
			});
			$(".chosen_brand").chosen({
				width: '100%'
			});
			$(".chosen_color").chosen({
				width: '100%'
			});
			$(".chosen_finishingType ").chosen({
				width: '100%'
			});
		}

		function RemoveSaleItemRow(val_this) {
			$(val_this).closest('tr').remove();
		}

		function calculateeftv(total_value, current_value) {
			var amount = 0;
			var total_perc = 0;
			var amount_perc = 0;
			var quantity_single = 0;
			var eff_rate = 0;
			console.log(total_value);
			console.log(current_value);
			$(".amount").each(function(index, el) {
				amount = parseFloat($(el).val());
				total_perc = amount * 100 / total_value;
				amount_per = total_perc / 100 * parseFloat(current_value);
				quantity_single = parseFloat($(el).parent().parent().parent().find(".quantity").val());
				eff_rate = amount_per / quantity_single;
				$(el).parent().parent().parent().find(".eff_net_rate_area").html(eff_rate);
				$(el).parent().parent().parent().find(".eff_net_rate").val(eff_rate);
			});
		}

		<?php
		if ($_REQUEST['vehicle'] == 'single_vehicle') { ?>
			// $(document).ready(function(){
			var veh = $('input[type=radio][name=vehicle_mode]:checked').val();
			$("#single_veh").trigger('click');
			$("#single_veh").prop('checked', true);
			// });
		<?php } ?>

		function vendor(val_this) {
			if ($(val_this).is(':checked')) {
				$(val_this).parent().parent().find('.chosen-select').chosen('destroy');
				$(val_this).parent().parent().find('.vendor_project').chosen('destroy');
				$(val_this).parent().parent().find('.expense_project').removeClass('chosen-select').hide();
				$(val_this).parent().parent().find('.expense_project').attr('name', '');
				$(val_this).parent().parent().find('.vendor_project').show();
				$(val_this).parent().parent().find('.vendor_project').addClass("chosen-select").attr('name', 'cr_project[]');
				$(val_this).parent().parent().find('.chosen-select').chosen({
					width: '100%'
				});
				vendorSetting(val_this, 'yes');
			} else {
				$(val_this).parent().parent().find('.chosen-select').chosen('destroy');
				$(val_this).parent().parent().find('.vendor_project').removeClass("chosen-select").hide();
				$(val_this).parent().parent().find('.vendor_project').attr('name', '');
				$(val_this).parent().parent().find('.expense_project').show();
				$(val_this).parent().parent().find('.expense_project').addClass("chosen-select").attr('name', 'cr_project[]');
				$(val_this).parent().parent().find('.chosen-select').chosen({
					width: '100%'
				});
				vendorSetting(val_this, 'no');
			}

		}
		$(".vendor_project").prop("disabled", true);
		$('.vendor_project').hide();

		function vendorSetting(val_this, status) {
			$.ajax({
				url: 'add-multiple-invoice.php',
				type: 'post',
				dataType: 'json',
				data: {
					command: 'get_expense_head',
					status: status
				},
				success: function(result) {
					$(val_this).parent().parent().parent().find('.proj_location').chosen('destroy');
					$(val_this).parent().parent().parent().find('.proj_location').html('');
					$(val_this).parent().parent().parent().find('.proj_location').html(result.location);
					if (status == 'no') {
						$(val_this).parent().parent().parent().find('.proj_location').val('');
					}
					$(val_this).parent().parent().parent().find('.proj_location').chosen({
						width: '100%'
					});
					$(val_this).parent().parent().parent().find('.sub_location').chosen('destroy');
					$(val_this).parent().parent().parent().find('.sub_location').html('');
					$(val_this).parent().parent().parent().find('.sub_location').html(result.sub_location);
					$(val_this).parent().parent().parent().find('.sub_location').chosen({
						width: '100%'
					});
				}
			});
		}

		function get_single_sub_location(val_this) {
			$.ajax({
					url: 'add-multiple-invoice.php',
					type: 'POST',
					data: {
						item_loc: $(val_this).val(),
						command: 'get_sub_loc'
					},
				})
				.done(function(data) {
					$(val_this).parent().parent().find('.sub_location').chosen('destroy');
					$(val_this).parent().parent().find('.sub_location').html('');
					$(val_this).parent().parent().find('.sub_location').html(data);
					$(val_this).parent().parent().find('.sub_location').chosen({
						width: '100%'
					});

				})
		}

		function CheckSubItem(val_this) {
			var SubItemCode = $(val_this).parent().parent().parent().find(".SubItemCode").val();
			var SubItemArticle = $(val_this).parent().parent().parent().find(".SubItemArticle").val();
			var ItemID = $(val_this).closest(".brand_qual_modal").find(".check_item").val();
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				dataType: 'json',
				data: {
					SubItemCode,
					SubItemArticle,
					ItemID,
					command: 'CheckIngSubItem'
				},
				success: function(sub_item) {
					if (sub_item[0] != 'fail') {
						var html = ''
						// if(sub_item!=''){
						// 	sub_item.subItemimgs.forEach(function(images){
						// 	html += '<div class="col-sm-6" style="padding-left:0"><a href="<?php echo BASE_URL . "admin/itemImages/" ?>'+images+'" target="blank" ><img style="width:100%;height:50px" src="<?php echo BASE_URL . "admin/itemImages/" ?>'+images+'"></a></div>';
						// 	});
						// }
						$(val_this).parent().parent().parent().find(".SubITemID").val(sub_item.id);
						$(val_this).parent().parent().parent().find(".img_td div ").html(" ");
						$(val_this).parent().parent().parent().find(".img_td div ").html(html);
					} else {
						// alert("Sorry! No Sub Item Found");
						$(val_this).parent().parent().parent().find(".SubITemID").val('');
						$(val_this).parent().parent().parent().find(".img_td div").html('<h4>No Sub Item Images</h4>');
					}
					getSubwareHouseITemQuantity(val_this);
					getSubITemRate($(val_this).closest(".brand_qual_modal"), val_this);
				}
			}).fail(function(jqXHR, textStatus) {
				alert("Sorry! No Sub Item Found");
				$(val_this).parent().parent().parent().find(".SubItemCodeText").text('');
				$(val_this).parent().parent().parent().find(".SubITemID").val('');
			});
		}

		function AddSubItemRow(val_this) {
			$(".chosen-select").chosen("destroy");
			var cloneRow = $(val_this).parent().parent().clone();
			$(val_this).parent().parent().parent().append(cloneRow);
			$(val_this).parent().parent().parent().find("tr:last input").val('');
			$(val_this).parent().parent().parent().find("tr:last .chosen_article").val('');
			$(val_this).parent().parent().parent().find("tr:last .chosen-code ").val('');
			$(val_this).parent().parent().parent().find("tr:last .sub-item-unit").val('sqm');
			$(val_this).parent().parent().parent().find("tr:last h4").text('');
			$(val_this).parent().parent().parent().find("tr:last h5").html('');
			$(".chosen-select").chosen({
				width: '100%'
			});

		}

		function removeSubItemrow(val_this, modal) {
			$(val_this).closest('tr').remove();
			quantity = 0
			$("." + modal).find("table tbody tr").each(function() {
				quantity += parseFloat($(this).find(".inv_quantity").val());
			});
			$("." + modal).find("table tfoot .quantity_td .total_auantity").val(quantity);
			$("." + modal).find("table tfoot .quantity_td .quantity_span .quantity_b").text(quantity);
		}

		function checkWalkInCustomer(val_this) {
			$('.walk_in').val('0');
			if ($(val_this).find("option:selected").attr("data-active-walk-in") == 'yes') {
				getWalkInCustomers($(val_this).val());
				$('.walk_in_button_show').show();
				$('.walk_in_button_show .walk_in').chosen("destroy");
				$('.walk_in_button_show .walk_in').prop('disabled', false);
				$('.walk_in_button_show .walk_in').chosen({
					width: '100%'
				});
				getBalance('', 'subledger');
			} else {
				$('.walk_in_button_show').hide();
				$('.walk_in_button_show .walk_in').chosen("destroy");
				$('.walk_in_button_show .walk_in').prop('disabled', true);
				getBalance(val_this, 'ledger');
			}
		}


		function getBalance(val, type) {
			if (type == 'ledger') {
				var ledger_id = $(val).val();
			} else {
				var subledger = $(val).val();
			}
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				data: {
					type,
					ledger_id,
					subledger,
					command: 'getBalance'
				},
				success: function(balance) {
					bal = JSON.parse(balance);
					$(".db_balance .ledger_balance").val(bal['amount'] + ' ' + bal['type']);
				}
			});
		}

		function getTotalCredit() {
			if (parseInt($(".walk_in").val()) > 0) {
				val_db = $(".walk_in");
				val = $(".walk_in").val();
			} else {
				val_db = $(".customer_id");
				val = $(".customer_id").val();
			}


			ledger_balance_arr = $(".customer").parent().find(".db_balance .ledger_balance").val();
			invoice_am = $("input[name=total_net]").val();
			credit = $(val_db).find('option[value=' + val + ']').data('credit');
			c_value = parseInt($(val_db).find('option[value=' + val + ']').data('cvalue'));
			ledger_balance_arr = ledger_balance_arr.split(" ");
			number = Math.abs(parseInt(ledger_balance_arr[0]));
			if (c_value != '' && parseInt(c_value) > 0 && ledger_balance_arr[1] == "(Dr)") {
				if (c_value > 0 && number > 0) {
					c_value = -number + c_value;
				} else if (number == 0) {
					c_value = c_value;
				}
				if (parseInt(invoice_am) > parseInt(c_value)) {
					alert("Sorry! Credit Limit Reached");
					$(".add_btn").hide();
				} else {
					$(".add_btn").show();
				}
			} else if (c_value != '' && parseInt(c_value) > 0 && ledger_balance_arr[1] == "(Cr)") {
				cr_lm = parseInt(number) + parseInt(c_value);
				if ((invoice_am) > cr_lm) {
					alert("Sorry! Credit Limit Reached");
					$(".add_btn").hide();
				} else {
					$(".add_btn").show();
				}
			} else if ($(".credit_am_check").val() == 'no') {
				$(".add_btn").hide();
			} else {
				$(".add_btn").show();
			}
		}


		function checkSubledger(val_this) {
			$(".walk_in_check").prop("checked", false);
			$('.walk_in').val('');
			var walk_in_id = $(val_this).parent().find(".walk_in_id").val();
			$('.walk_in').val(walk_in_id);
			$('#sub_ledger_model .sub_ledger_id').val(walk_in_id);
			$(val_this).prop("checked", true);
			$('.sub_ledger_model').modal('hide');
			var sub_ledger_name = $(val_this).closest('tr').find('.walk_in_name').val();
			if (sub_ledger_name != '') {
				$('.walk_in_text_show').parent().show();
				$('.walk_in_text_show').html('<b> ( ' + sub_ledger_name + ' )</b>');
			} else {
				$('.walk_in_text_show').parent().hide();
				$('.walk_in_text_show').html('');
			}

			// $.ajax({
			// 	url : 'add-sale-invoice.php',
			// 	Type : 'post',
			// 	dataType : 'json',
			// 	data : {serch_subledger:$(val_this).val(),command:'checkSubledger'},
			// 	success : function(response){
			// 		if (response.status=='yes') {
			// 			$('#sub_ledger_model .sub_ledger_name').val(response.name);
			// 			$('#sub_ledger_model .sub_ledger_id_card').val(response.id_card);
			// 			$('#sub_ledger_model .sub_ledger_mobile').val(response.mobile);
			// 			$('#sub_ledger_model .sub_ledger_id').val(response.id);
			// 			$('.walk_in').val(response.id);	
			// 		}
			// 		getSubledger();
			// 	}
			// })
		}

		function insertSubLedger() {
			$('.walk_in').val('');
			name = $('#sub_ledger_model .sub_ledger_name').val();
			id_card = $('#sub_ledger_model .sub_ledger_id_card').val();
			mobile = $('#sub_ledger_model .sub_ledger_mobile').val();
			sub_ledger_id = $('#sub_ledger_model .sub_ledger_id').val();
			main_ledger_id = $('#sub_ledger_model .main_ledger_id').val();
			$('.sub_ledg').val(0);
			$.ajax({
				url: 'add-sale-invoice.php',
				Type: 'post',
				dataType: 'json',
				data: {
					name: name,
					mobile: mobile,
					id_card: id_card,
					sub_ledger_id: sub_ledger_id,
					main_ledger_id: main_ledger_id,
					command: 'verified_save'
				},
				success: function(response) {
					$('.walk_in').val(response.id);
					if (response.status == 'insert') {
						alert("Added Successfully");
						getSubledger();
					}
					if (response.status == 'update') {
						alert("Updated Successfully");
						getSubledger();
					}
				}
			})

		}


		function CompanyTransactions(val_this) {
			if ($(val_this).val() == 'company') {
				$(".company_sale_div").removeClass("hidden");
				$(".customer_div").hide();
			} else {
				$(".company_sale_div").addClass("hidden");
				$(".customer_div").show();
			}
			GetCustomers();
		}

		function PaymentType(val_this) {
			var total_amount = $(".grand_total").text();
			if ($(val_this).val() == 'due') {
				if (parseInt(total_amount) > 0) {
					$(".Installments").find(".total_amount_b").text(total_amount);
					$(".Installments").find(".remaing_amt").val(total_amount);
					$(".install_div").removeClass("hidden");
					$(".Installments").modal("show");
				} else {
					alert("Please Check Entries");
				}
			} else {
				$(".install_div").addClass("hidden");
			}
		}

		function AddInstallmentRow(modal, val) {
			total_amount = $("." + modal).find(".remaing_amt").val();
			var amt = 0;
			$("." + modal).find("table tbody tr").each(function() {
				amt += parseInt($(this).find(".installment_amt").val());
			});
			rem_amt = total_amount - amt;
			if (rem_amt > 0) {
				$("." + modal).find("table tbody").append($(val).parent().parent().clone());
				rowCount = $("." + modal).find("table tbody tr").length;
				$("." + modal).find("table tbody tr:last .sr_no").text(rowCount + 1 - 1);
				$("." + modal).find("table tbody tr:last .btn-danger").removeClass("hidden");
				$("." + modal).find("table tbody tr:last .btn-danger").attr('onclick', '');
				$("." + modal).find("table tbody tr:last .btn-danger").attr('onclick', 'RemoveRow(this)');
				$("." + modal).find("table tbody tr:last .installment_amt").val('')
				$("." + modal).find("table tbody tr:last .installment_status").val('pending')
				$("." + modal).find("table tbody tr:last input").removeAttr('readonly');
				$("." + modal).find("table tbody tr:last .status_td .form-group").html('')
				$("." + modal).find("table tbody tr:last .installment_amt").val(rem_amt)
				$("." + modal).find("table tbody tr:last .installment_date").val('');
				$("." + modal).find("table tbody tr:last .insta_date").addClass('installment_date');
			}
			$(".installment_date").datepicker({
				format: 'd/m/yyyy'
			});
		}

		function RemoveInstallmentRow(modal, val) {
			$(val_this).closest('tr').remove();
		}

		function QuantityValue(modal, val_this) {
			var quantity = $(val_this).parent().parent().find(".sub-item-quantity").val();
			var alt_unit = $('.' + modal).parent().parent().find(".conversionToAlternativeUnit").val();
			if ($(val_this).val() == 'boxes') {
				QuntityToBoxes(quantity, val_this, alt_unit);
			} else if ($(val_this).val() == 'sqm') {
				QuntityToSqm(quantity, val_this, alt_unit);
			} else if ($(val_this).val() == 'ton') {
				QuntityToton(quantity, val_this, alt_unit);
			}
			var quantity = 0;
			var boxes = 0;
			var tons = 0;
			$("." + modal).find('.SubItemTable tbody tr').each(function() {
				quantity += parseFloat($(this).find(".inv_quantity").val());
				boxes += parseFloat($(this).find(".inv_boxes").val());
				tons += parseFloat($(this).find(".inv_tons").val());
			});
			$("." + modal).find(".SubItemTable tfoot .quantity_span .quantity_b").text(quantity);
			$("." + modal).find(".SubItemTable tfoot .quantity_td input[type=hidden]").val(quantity);
			$("." + modal).find(".SubItemTable tfoot .boxes_span .boxes_b").text(boxes);
			$("." + modal).find(".SubItemTable tfoot .boxes_td input[type=hidden]").val(boxes);
			$("." + modal).find(".SubItemTable tfoot .tons_span .tons_b").text(tons);
			$("." + modal).find(".SubItemTable tfoot .tons_td input[type=hidden]").val(tons);
		}

		function QuntityToBoxes(q, val_this, alt_unit) {
			quantity = parseFloat(q).toFixed(2);

			var box = (parseFloat(quantity) * parseFloat(alt_unit)).toFixed(2);
			$(val_this).closest("tr").find(".boxes_td input").val(quantity);
			$(val_this).closest("tr").find(".quantity_td input").val(box);
			$(val_this).closest("tr").find(".number_td input").val(box / 1000);
		}

		function QuntityToSqm(q, val_this, alt_unit) {
			quantity = parseFloat(q).toFixed(2);
			var box = (parseFloat(quantity) / parseFloat(alt_unit)).toFixed(2);
			$(val_this).closest("tr").find(".boxes_td input").val(box);
			$(val_this).closest("tr").find(".quantity_td input").val(quantity);
			$(val_this).closest("tr").find(".number_td input").val(quantity / 1000);
		}

		function QuntityToton(q, val_this, alt_unit) {
			quantity = parseFloat(q).toFixed(2);
			var box = ((parseFloat(quantity) * 1000) / parseFloat(alt_unit)).toFixed(2);
			$(val_this).closest("tr").find(".boxes_td input").val(box);
			$(val_this).closest("tr").find(".quantity_td input").val(quantity * 1000);
			$(val_this).closest("tr").find(".number_td input").val(quantity);
		}

		function calculateTotalBoxes() {
			total_boxes = 0;
			$(".pq_table tbody .boxes").each(function() {
				if (parseFloat($(this).val()) > 0) {
					total_boxes += parseFloat($(this).val());
				}
			});
			if (total_boxes > 0) {
				total_boxes = total_boxes.toFixed(2);
			}
			$(".tot_boxes_inp").val(total_boxes);
			$(".tot_boxes").text(total_boxes);
		}

		function calculateTotalTons() {
			total_tons = 0;
			$(".pq_table tbody .tons").each(function() {
				if (parseFloat($(this).val()) > 0) {
					total_tons += parseFloat($(this).val());
				}
			});
			if (total_boxes > 0) {
				total_tons = total_tons.toFixed(2);
			}

			$(".tot_tons_inp").val(total_tons);
			$(".tot_tons").text(total_tons);
		}

		function getDebitSubLoc(val_this) {
			$.ajax({
					url: 'add-sale-invoice.php',
					type: 'POST',
					data: {
						item_loc: $(val_this).val(),
						command: 'get_sub_loc'
					},
				})
				.done(function(data) {
					$('.debit_subhead').chosen('destroy');
					$('.debit_subhead').html('');
					$('.debit_subhead').html(data);
					$('.debit_subhead').chosen({
						width: '100%'
					});

				})
		}

		function RemoveRow(val) {
			$(val).closest('tr').remove();
		}

		function GetCustomers() {
			$.ajax({
				url: 'add-sale-invoice.php',
				datatype: 'post',
				data: {
					data: $(".sale_type").val(),
					order_customer: $(".order_customer").val(),
					head: $(".location_id").val(),
					sub_head: $(".sub_location").val(),
					command: "GetDistributers"
				},
				success: function(distributers) {
					$(".inv_transporter_id").html(distributers).trigger("chosen:updated");
				}
			});
		}


		function getWalkInCustomers(id) {
			$.ajax({
				url: 'add-sale-invoice.php',
				type: 'post',
				data: {
					id,
					command: 'getWalkInSubLedgers'
				},
				success: function(subledgers) {
					$(".walk_in").html('')
					$(".walk_in").html(subledgers).trigger("chosen:updated");
				}
			});
		}

		function getSubITemRate(val, val_this) {
			var item_id = $(val).find(".check_item").val();
			var brand = $(val).parent().find(".item_brand").val();
			var quality = $(val).parent().find(".item_quality").val();
			var finishing_type = $(val).parent().find(".finishing_type").val();
			var sale_type = $(".contract_type ").val();
			var location_id = $(".location_select ").val();
			var project_id = $(".sub_location").val();
			var customer_id = $(".inv_transporter_id").val();
			var subitem_id = $(val_this).closest('tr').find(".SubITemID").val()
			var sub_ledger = $(".walk_in").val();
			$.ajax({
				url: '<?php echo ADMIN_URL; ?>ajax/get-sale-order.php',
				data: {
					subitem_id,
					article_id: $(val_this).val(),
					sale_type,
					item_id,
					brand,
					quality,
					sub_ledger,
					customer_id,
					finishing_type,
					project_id,
					location_id,
					command: 'getSubITemRate'
				},
				type: 'post',
				success: function(rate) {
					rate = $.parseJSON(rate);
					$(val).parent().find(".rate").val(rate.rate);
				}
			});
		}
	</script>
</body>

</html>