<?php
	include("includes/common-files.php");
	$a->authenticate();
	// ini_set('display_errors', 1);
	// ini_set('display_startup_errors', 1);
	// error_reporting(E_ALL);
	// add location and sub location


	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'checkSubItems'){
		$item = $_REQUEST['item_id'];
		$db->select("Select * from sub_item where item_id = ".$item);
		$subItems = $db->fetch_all();
		if(count($subItems) > 0){
			echo "yes";
		}else{
			echo "no";
		}
		exit();
	}
	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getSubledger'){
		$sql = 'select * from sub_ledgers where';
		$check = '';
		if($_REQUEST['name'] !=''){
			$sql.=" name LIKE '%".$_REQUEST['name']."%'";
		}

		if($_REQUEST['name'] !='' && $_REQUEST['id_card']!=''){
			$sql.=' or ';
		}
		if($_REQUEST['id_card'] !=''){
			$id_card = str_replace('-','', $_REQUEST['id_card']);
			$sql.=" id_card LIKE '%".$id_card."%'";
		}
		if($_REQUEST['id_card'] !='' && $_REQUEST['phone']!=''){
			$sql.=' or ';
		}
		if($_REQUEST['phone'] !=''){
			$phone = str_replace('-','', $_REQUEST['phone']);
			$sql.=" mobile LIKE '%".$phone."%'";
		}
		$db->select($sql);
		$sub_ledgers=$db->fetch_all();
		$html ='';
		$i = 1;
		if($sub_ledgers){
			foreach($sub_ledgers as $sub){
				$html .='<tr>';
				$html .='<td>'.$i.'</td>';
				$html .='<td><input type="hidden" class="walk_in_name" value="'.$sub['name'].'" >'.$sub['name'].'</td>';
				$html .='<td>'.$sub['id_card'].'</td>';
				$html .='<td>'.$sub['mobile'].'</td>';
				$html .='<td><input type="hidden" class="walk_in_id" value="'.$sub['id'].'" ><input type="checkbox" onclick="checkSubledger(this)" class="walk_in_check"></td>';
				$html .='</tr>';
				$i++;
			}
		}else{
			$html .='<tr><td colspan="4">No Sub Ledger</td></tr>';
		}

		echo $html ;
		exit();
	}

	function GetSubitemQuantity($head,$sub_head,$sub_item_id,$article,$quality,$section,$warehoue_id){
		global $db;
		$tables = array('quotation_inventory','sales_inventory');
		$quotation_q = 0;
		$sales_q = 0;
		foreach($tables as $table){
			$sql = "Select * from ".$table." where sub_item_id=".$sub_item_id;
			if($article != ''){
				$sql .= " and article_id = ".$article;
			}
			if($quality!='' && $quality!='0'){
				$sql .= ' and quality_id = '.$quality;
			}
			if($warehoue_id !=''){
				$sql .= " and warehouse_id = ".$warehoue_id;
			}
			if($section!=''){
				$sql .= " and section_id = ".$section;
			}
			$sql .= " and location_id=".$head." and sub_location_id=".$sub_head." and company_id=".getCompanyId();
			$db->select($sql);
			$subItems_inventory = $db->fetch_all();
			foreach($subItems_inventory as $subItem_inv ){
				if($table == 'quotation_inventory'){
					$quotation_q += $subItem_inv['quantity'];
				}else if($table == 'sales_inventory'){
					$sales_q += $subItem_inv['quantity'];
				}
			}
		}
		$quantity = $quotation_q - $sales_q;
		if($quantity < 0){
			$quantity = 0;
		}
		return $quantity;
	}


	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getQuantity'){
		$quantity = 0;
		if(isset($_REQUEST['head']) && $_REQUEST['head'] !=''  && isset($_REQUEST['sub_head']) && $_REQUEST['sub_head'] !=''){
			$sub_item_id = $_REQUEST['Sub_item_id'];
			$head = $_REQUEST['head'];
			$subhead = $_REQUEST['sub_head'];
			$quality = $_REQUEST['Sub_item_quality'];
			$article = $_REQUEST['Sub_item_article'];
			$quantity = GetSubitemQuantity($head,$subhead,$sub_item_id,$article,$quality,'','');
		}
		echo $quantity;
		exit();
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'CheckIngSubItem'){
		$SubItemCode = $_REQUEST['SubItemCode'];
		$SubItemArticle = $_REQUEST['SubItemArticle'];
		$itemId = $_REQUEST['ItemID'];
		$subItem = $db->fetch_array_by_query("Select * from sub_item where code = '".$SubItemCode."' and article = ".$SubItemArticle." and item_id=".$itemId);
		$article = $db->fetch_array_by_query("Select * from article where id=".$subItem['article']." and FIND_IN_SET(".getCompanyId().",company_id)");
		if($subItem){
			$sub_item_code = $subItem['code'].$article['name'];
			echo json_encode(array("id"=>$subItem['id'],"subItemName"=>$sub_item_code));
		}
		exit();
	}
	if (isset($_REQUEST['command']) && $_REQUEST['command']=='verified_save') {
		unset($_REQUEST['command']);
		$sub_ledger = array(
			'name' => $_REQUEST['name'],
			'mobile' => str_replace('-','', $_REQUEST['mobile']),
			'id_card' => str_replace('-','', $_REQUEST['id_card']),
			'ledger_id' => $_REQUEST['main_ledger_id'],
			'created_at' => time(), 
			'company_id' => getCompanyId()
		);
		$phone_number = str_replace('-','', $_REQUEST['mobile']);
		$id_card = str_replace('-','', $_REQUEST['id_card']);
		$get_sub_ledger = $db->fetch_array_by_query("select * from sub_ledgers where id_card=".$id_card." or mobile = ".$phone_number." and company_id=".getCompanyId());
		if (!empty($get_sub_ledger)) {
			$result = $db->update($get_sub_ledger['id'],$sub_ledger,'sub_ledgers');
			echo json_encode(array("id"=>$_REQUEST['sub_ledger_id'],"status"=>'update'));
		}else{
			$result = $db->insert($sub_ledger,'sub_ledgers');
			echo json_encode(array("id"=>$result,"status"=>'insert'));
		}
		exit();
	}
	if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'checkWalkInCustomer' && intval($_REQUEST['led_id']) > 0) {
		$ledg_row = $db->fetch_array_by_query("Select * from ledger where id = ".$_REQUEST['led_id']." and active_walk_in_customer = 'yes' and company_id=".getCompanyId());
		$data_arr = [];
		if (!empty($ledg_row)) {
			$data_arr['ledger_id'] = $ledg_row['id'];
			$data_arr['status'] = 'yes';
			echo json_encode($data_arr);
		}else{
			$data_arr['status'] = 'no';
			echo json_encode($data_arr);
		}
		exit();
	}
	if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'checkSubledger' && intval($_REQUEST['serch_subledger']) !='') {

		$sub_ledg_row = $db->fetch_array_by_query("Select * from sub_ledgers where (mobile = '".str_replace('-','', $_REQUEST['serch_subledger'])."' or id_card = '".str_replace('-','', $_REQUEST['serch_subledger'])."') and company_id=".getCompanyId());
		$data_arr = [];
		if (!empty($sub_ledg_row)) {
			$data_arr['id'] = $sub_ledg_row['id'];
			$data_arr['name'] = $sub_ledg_row['name'];
			$data_arr['mobile'] = $sub_ledg_row['mobile'];
			$data_arr['id_card'] = $sub_ledg_row['id_card'];
			$data_arr['ledger_id'] = $sub_ledg_row['ledger_id'];
			$data_arr['status'] = 'yes';
			echo json_encode($data_arr);
		}else{
			$data_arr['status'] = 'no';
			echo json_encode($data_arr);
		}
		exit();
	}

	function Quantity($query){
		global $db;
		$quantity = 0;
		$db->select($query);
		$quotation = $db->fetch_all();
		foreach($quotation as $q){
			$quantity += $q['quantity'];
		}
		 return $quantity;
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command']=='get_expense_head'){
		$setting = getSettingRow('purchase_expense_ledger');
		$db->select("Select * from item_location where company_id=".getCompanyId());
		$locations = $db->fetch_all();
		$general_subhead = getSettingRow('general_subhead');
		$general_head = getSettingRow('general_head');
		if($_REQUEST['status'] == 'yes'){
			$sublocation = $db->fetch_array_by_query("Select * from item_sublocation where id=".$general_subhead['value']." and company_id=".getCompanyId());
			$location_op = '';
			$loc_name = $db->fetch_array_by_query("Select * from item_location where id=".$general_head['value']." and company_id=".getCompanyId());
			$location_op .= "<option value='".$general_head['value']."' >".$loc_name['name']."</option>";
			$sublocation_op='<option value="'.$general_subhead['value'].'">'.$sublocation['name'].'</option>';
			$arr=array(
				'location'=>$location_op,
				'sub_location'=>$sublocation_op
			);
		}else if($_REQUEST['status'] == 'no'){
			$location_op .= "<option>Select Location</option>";
			foreach ($locations as $location) {
				if($general_head['value'] == $location['id']){
					$select = 'selected';
				}else{
					$select = '';
						}
				$location_op .= "<option value='".$location['id']."' " .$select." >".$location['name']."</option>";
			}
			$sublocation_op = '';
			$arr=array(
				'location'=>$location_op,
				'sub_location'=>$sublocation_op
			);
		}
		echo json_encode($arr);
		exit();
	}
	if (isset($_REQUEST['pr_no']) && ($_REQUEST['pr_no'] != '')) {
		$purchase_reciept = $db->fetch_array_by_query("select * from purchase_reciept where id=".intval($_REQUEST['pr_no'])." and company_id=".getCompanyId());
		$po_row['purch_rc_no'] = $purchase_reciept['pr_no'];
	}
	if (isset($_REQUEST['pr_no']) && ($_REQUEST['pr_no'] != '')) {
		$db->select("select * from reciept_detail where pr_id=".intval($_REQUEST['pr_no'])." and company_id=".getCompanyId()); 
		$purchase_reciept_details = $db->fetch_all();
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'get_sub_loc') {
		$db->select('select * from item_sublocation where location_id='.$_REQUEST['item_loc'].' and company_id='.getCompanyId());
		$sub_locations = $db->fetch_all();
		$sub_loc .= "<option selected disabled>Select Sub Location</option>";
		foreach ($sub_locations as $sub_location) {
			$sub_loc .= "<option value='".$sub_location['id']."'>".$sub_location['name']."</option>";
			}
			echo $sub_loc;
		exit();
	}

	$db->select("select * from item_location where company_id=".getCompanyId()." order by id desc");
	$locations=$db->fetch_all();

	if(isset($_REQUEST['po']) && $_REQUEST['po']!=''){
		$id= $_REQUEST['po'];
		$po_row=$db->fetch_array_by_query('select * from purchase_order where id='.$id);
	}
	if(isset($_POST['data_id'])){
		$db->select('SELECT * FROM item where company_id='.getCompanyId()." order by id desc");
		$jsonResult = $db->fetch_all();
		echo json_encode($jsonResult);
	}
	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getSection'){
		$warehouse_id = intval($_REQUEST['warehouse_id']);
		$item_id = intval($_REQUEST['item_id']);
		$sections_li ='';
		$item_row = $db->fetch_array_by_query("Select * from item where id=".$item_id);
		if($item_row['section'] == 'yes'){
			$db->select("select * from sections where company_id=".getCompanyId()." and warehouse_id=".$warehouse_id." order by id desc");
			$sections=$db->fetch_all();
			if ($sections){
				$sections_li.='<option value="0"> Select Sections </option>';
				foreach ($sections as $section){
		 			$sections_li.='<option value="'.$section['id'].'">'.$section['name'].'</option>';
				}
			} else {
				$sections_li.='<option value="0"> No Record Found </option>';
			}
		}else{
			$sections_li.='<option value="0"> No Record Found </option>';
		}
		echo $sections_li;
		exit();
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getSection'){
		$warehouse_id = intval($_REQUEST['warehouse_id']);
		$item_id = intval($_REQUEST['item_id']);
		$sections_li ='';
		$item_row = $db->fetch_array_by_query("Select * from item where id=".$item_id);
		if($item_row['section'] == 'yes'){
			$db->select("select * from sections where company_id=".getCompanyId()." and warehouse_id=".$warehouse_id." order by id desc");
			$sections=$db->fetch_all();
			if ($sections){
				$sections_li.='<option value="0"> Select Sections </option>';
				foreach ($sections as $section){
		 			$sections_li.='<option value="'.$section['id'].'">'.$section['name'].'</option>';
				}
			} else {
				$sections_li.='<option value="0"> No Record Found </option>';
			}
		}else{
			$sections_li.='<option value="0"> No Record Found </option>';
		}
		echo $sections_li;
		exit();
	}



	

	function check_voucher_no(){
		global $db;
		$purchase_receipt_last = $db->fetch_array_by_query("SELECT * FROM sale_invoice where company_id=".getCompanyId()." ORDER BY ID DESC LIMIT 1");
		$receipt_no = $purchase_receipt_last['purch_invoice_no']+1;
		return $receipt_no;
	}
	$receipt_ar = check_voucher_no();
	if(isset($_POST['purchaseInvoice'])){
		$receipt_row = $db->fetch_array_by_query("select * from sale_invoice where sale_invoice_no =".$_POST['purch_invoice_no']." and company_id=".getCompanyId());
		if($receipt_row){
			$receipt_last = $db->fetch_array_by_query("select * from sale_invoice where company_id=".getCompanyId()." order by id desc limit 1");
			$_POST['sale_invoice_no'] = $receipt_last['sale_invoice_no']+1;
		}
		$arr = array();
		$arr['company_id'] = getCompanyId();
		$arr['user_id'] = getUSerId();
		$arr['created_at'] =time();
		$arr['narration'] = $_POST['narration'];
		$arr['sale_invoice_series'] = $_POST['purch_invoice_series'];
		$arr['invoice_date'] = strtotime($_POST['invoice_date']);
		$arr['invoice_day'] = $_POST['invoice_day'];
		$arr['location_id'] = intval($_POST['cr_location']);
		$arr['sub_location_id'] = intval($_POST['cr_sublocation']);
		$arr['project_id'] = intval($_REQUEST['project_ledger']);
		$arr['sale_invoice_no'] = $_POST['sale_invoice_no'];
		$arr['discount_mode'] = $_POST['discount_mode'];
		$arr['ledger_party'] = $_POST['ledger_party'];
		$arr['total_amount'] = $_POST['total'];
		$arr['total_discount'] = intval($_POST['total_discount']);
		$arr['total_net'] = intval($_POST['total_net']);
		$arr['discount_check'] = $_POST['discount_check'];
		$arr['total_quantity'] = $_POST['tot_quantity'];
		if($_POST['walk_in'] > 0){
			$arr['sub_ledger_id'] = $_POST['walk_in'];
		}
		
		if($_POST['discount_check']==''){
			$arr['discount_check'] ='no';
		}

		if(isset($_REQUEST['loading_ledger']) && $_REQUEST['loading_ledger']!='' ){
			$arr['loading_ledger'] = $_POST['loading_ledger'];
			$arr['loading_quantity'] = $_POST['loading_quantity'];
			$arr['loading_rate'] = $_POST['loading_rate'];
			$arr['loading_charges'] = $_POST['loading_amount'];
		}else{
			$arr['loading_charges'] =0;
			$arr['loading_quantity'] = 0;
			$arr['loading_rate'] = 0;
		}

		if(isset($_REQUEST['unloading_ledger']) && $_REQUEST['unloading_ledger']!='' ){
			$arr['unloading_ledger'] = $_POST['unloading_ledger'];	
			$arr['unloading_quantity'] = $_POST['unloading_quantity'];
			$arr['unloading_rate'] = $_POST['unloading_rate'];
			$arr['unloading_charges'] = $_POST['unloading_amount'];
		}else{
			$arr['unloading_ledger'] =0;
			$arr['unloading_charges'] =0;
			$arr['unloading_quantity'] = 0;
			$arr['unloading_rate'] = 0;
		}

		if(isset($_REQUEST['other_amount']) && $_REQUEST['other_amount']!='' ){
			$arr['other_charges'] = $_POST['other_amount'];
			$arr['other_ledger'] = $_POST['other_ledger'];
			$arr['other_quantity'] = $_POST['other_quantity'];
			$arr['other_rate'] = $_POST['other_rate'];
		}else{
			$arr['other_ledger'] =0;
			$arr['other_charges'] =0;
			$arr['other_quantity'] = 0;
			$arr['other_rate'] = 0;
		}

		if(isset($_REQUEST['freight_amount']) && $_REQUEST['freight_amount']!='' ){
			$arr['freight_ledger'] = $_POST['freight_ledger'];
			$arr['freight_charges'] = $_POST['freight_amount'];
			$arr['single_vehicle'] = $_POST['single_vehicle'];
			$arr['single_receipt'] = $_POST['single_receipt'];
			$arr['single_bilty'] = $_POST['single_bilty'];
		}else{
			$arr['freight_ledger'] =0;
			$arr['freight_charges'] =0;
			$arr['single_vehicle'] = 0;
			$arr['single_receipt'] = 0;
			$arr['single_bilty'] = 0;
		}

		$arr['other_charges'] = $_POST['other_amount'];
		$arr['grand_total'] = intval($_POST['total']);
		if(isset($_REQUEST['ledger_party']) && $_REQUEST['ledger_party']=='single_party' ){
			$arr['third_party'] = $_POST['third_party_single'];	
		}
		$arr['customer_id'] = $_POST['customer_id'];
		if(count($_POST) && (isset($_POST['img']))){
			foreach($_POST['img'] as $img){
				if (strpos($img, 'data:image/jpeg;base64,') === 0){
					$img = str_replace('data:image/jpeg;base64,', '', $img);
					$ext = '.jpg';
				}
				if (strpos($img, 'data:image/png;base64,') === 0) {
					$img = str_replace('data:image/png;base64,', '', $img);
					$ext = '.png';
				}
				$img = str_replace(' ', '+', $img);
				$data = base64_decode($img);
				$file_name =rand().time().$ext;
				$file = '../site-content/uploads/'.$file_name;
				if (file_put_contents($file, $data)){
					$images_arr [] = $file_name;
				} else {
					echo "<p>The image could not be saved.</p>";
				}
			}
		}
		$arr['attachment'] = json_encode($images_arr);
		$arr['type'] = 'single';
		$receipt_id = $db->insert($arr,'sale_invoice');
		$arr['id'] = $receipt_id;
		$customer_id=$_POST['customer_id'];
		// die();

		if($receipt_id){
			if($_POST['item_name']){
				if(isset($_REQUEST['discount_mode']) && $_REQUEST['discount_mode']!='' ){
					$discount_mode=$_REQUEST['discount_mode'];
				}
				if(isset($_REQUEST['ledger_party']) && $_REQUEST['ledger_party']!='' ){
					$ledger_party=$_REQUEST['ledger_party'];
				}
				for ($i = 0; $i < count($_POST['item_name']); $i++){
					$arr_detail = array();
					$arr_detail['item_id'] = $_POST['item_name'][$i];
					$arr_detail['item_group'] = $_POST['item_group'][$i];
					$arr_detail['quantity'] = $_POST['quantity'][$i];
					$arr_detail['actual_rate'] = $_POST['rate'][$i];
					$sr_no = $_POST['sr_no'][$i];

					if($_POST['discount'][$i]==''){
						$arr_detail['discount'] ='0';
					}else{
						$arr_detail['discount'] = $_POST['discount'][$i];
					}

					if($_POST['balance_category'][$i]==''){
						$arr_detail['balance_category'] ='Rs';
					}else{
						$arr_detail['balance_category'] = $_POST['balance_category'][$i];
					}

					if(isset($_REQUEST['inv_third_party_id'][$i]) && $_REQUEST['inv_third_party_id'][$i]!='' ){
						$arr_detail['third_party']=$_REQUEST['inv_third_party_id'][$i];
					}
					else {
						$arr_detail['third_party']=0;
					}
					
					if(isset($_REQUEST['serial_no'][$i]) && $_REQUEST['serial_no'][$i]!='' ){
						$arr_detail['serial_no']=$_REQUEST['serial_no'][$i];
					}
					else {
						$arr_detail['serial_no']=0;
					}
					
					$arr_detail['location'] = intval($_POST['cr_location']);
					$arr_detail['sub_location'] = intval($_POST['cr_sublocation']);
					if(isset($_POST['sub_item'][$i]) && $_POST['sub_item'][$i] !=''){
						$arr_detail['sub_item'] = $_POST['sub_item'][$i];
					}
					$arr_detail['discount_mode'] = $discount_mode;
					$arr_detail['party_mode'] = $ledger_party;
					$arr_detail['net_rate'] = $_POST['net_rate'][$i];
					$arr_detail['effective_rate'] = $_POST['eff_net_rate'][$i];
					$arr_detail['amount'] = $_POST['amount'][$i];
					$arr_detail['debit_ledger_id'] = intval($_POST['customer_id']);
					$arr_detail['created_at'] =time();
					$arr_detail['updated_at'] =time();
					$arr_detail['s_i_id'] = $receipt_id;
					$arr_detail['company_id'] = getCompanyId();
					$arr_detail['user_id'] = getUSerId();
					$arr_detail['total_quantity'] = $_POST['tot_quantity'];
					$sr_no_1 = $_POST['sr_no'][$i];
					$arr_detail['customer_id'] =$customer_id;

					if($_POST['sr_no'][$i]!=0){
						$receipt_trans_id = $db->insert($arr_detail,'s_invoice_transaction');
						$result=$receipt_trans_id;
						if(isset($_REQUEST['Sub_item_id'.$sr_no]) && $_REQUEST['Sub_item_id'.$sr_no] != '' &&  isset($_REQUEST['Sub_item_article'.$sr_no]) && $_REQUEST['Sub_item_article'.$sr_no] != ''){								
							foreach($_REQUEST['Sub_item_id'.$sr_no] as $subItemIndex => $subItemId){
								$receipt_inv=array();
								$receipt_inv['receipt_id']=$receipt_id;
								$receipt_inv['receipt_transaction_id']=$receipt_trans_id;
								$receipt_inv['item_id']=$_POST['item_name'][$i];
								$receipt_inv['item_group_id']=$_POST['item_group'][$i];
								$receipt_inv['rate']=$_POST['rate'][$i];
								$receipt_inv['sub_item_id']=$subItemId;
								$receipt_inv['quality_id']=intval($_POST['Sub_item_quality'.$sr_no][$subItemIndex]);
								$receipt_inv['color_id']=intval($_POST['Sub_item_color'.$sr_no][$subItemIndex]);
								$receipt_inv['article_id']=intval($_POST['Sub_item_article'.$sr_no][$subItemIndex]);
								$receipt_inv['warehouse_id']=intval($_POST['Sub_item_warehouse'.$sr_no][$subItemIndex]);
								$receipt_inv['section_id']=intval($_POST['Sub_item_section'.$sr_no][$subItemIndex]);
								$receipt_inv['inv_quantity']=intval($_POST['Sub_item_quantity'.$sr_no][$subItemIndex]);
								$receipt_inv['inv_bilty_no'] = intval($_POST['serial_no'][$i]);
								$receipt_inv['location_id'] = intval($_POST['cr_location']);
								$receipt_inv['sub_location_id'] = intval($_POST['cr_sublocation']);
								$receipt_inv['debit_ledger_id']=intval($_POST['customer_id']);
								$receipt_inv['credit_ledger_id']=intval($_POST['project_ledger']);
								$receipt_inv['created_at']=time();
								$receipt_inv['updated_at']=time();
								$receipt_inv['company_id']=getCompanyId();
								$receipt_inv['user_id']=getUSerId();
								$receipt_inventory_id = $db->insert($receipt_inv,'sale_invoice_inventory');
								$result=$receipt_inventory_id;
							}
						}

						if(isset($receipt_trans_id)){
							$voucher_id = makePurchaseReceiptVoucher($arr, $_POST['project_ledger']);
							$result = makeInvoiceTransactions($voucher_id, $arr,$_POST['dr_location'][$i],$_POST['dr_sublocation'][$i]);
						}




						// 	$receipt_inv=array();
						// 	$total_quantity=$_POST['total_quantity'];
						// 	$receipt_inv['receipt_id']=$receipt_id;
						// 	// $receipt_inv['freight_id']=$_POST['freight_ledger'];
						// 	$receipt_inv['receipt_transaction_id']=$receipt_trans_id;
						// 	$receipt_inv['amount'] = $_POST['amount'][$i];
						// 	$receipt_inv['total_amount'] = $_POST['total'];
						// 	// $receipt_inv['inv_bilty_no']=intval($_POST['single_bilty']);
						// 	// $receipt_inv['inv_veh_no']=intval($_POST['single_vehicle']);
						// 	// $receipt_inv['inv_receipt_no']=intval($_POST['single_receipt']);
						// 	// $receipt_inv['inv_delivery_no']='exdelivery';
						// 	// $receipt_inv['inv_supplier_no']=0;
						// 	$receipt_inv['brand_id']=intval($brand);
						// 	$receipt_inv['quality_id']=intval($quality);
						// 	$receipt_inv['color_id']=intval($color);
						// 	$receipt_inv['article_id']=intval($article);
						// 	$receipt_inv['warehouse_id']=intval($warehouse);
						// 	$receipt_inv['section_id']=intval($section);
						// 	$receipt_inv['images'] = json_encode($file_arr);
						// 	$receipt_inv['location_id'] = intval($_POST['location_id']);
						// 	$receipt_inv['sub_location_id'] = intval($_POST['sub_location_id']);
						// 	$receipt_inv['finishing_type']=intval($finishingtype);
						// 	$receipt_inv['item_id'] = $_POST['item_name'][$i];
						// 	$receipt_inv['item_group_id'] = $_POST['item_group'][$i];
						// 	$receipt_inv['quantity'] = $_POST['quantity'][$i];
						// 	$receipt_inv['rate'] = $_POST['rate'][$i];
						// 	$receipt_inv['s_type'] = 'sale';
						// 	$receipt_inv['created_at']=time();
						// 	$receipt_inv['updated_at']=time();
						// 	$receipt_inv['company_id']=getCompanyId();
						// 	$receipt_inv['user_id']=getUSerId();
							
						// 	$receipt_inventory_id = $db->insert($receipt_inv,'sale_invoice_inventory');
							
						// 	$result=$receipt_inventory_id;
						// // }
					}
				}
			}
		}
		if($result){
			$obj_msg = load_class('InfoMessages');
			$imsg->setMessage('Added Successfully!');
			redirect_header(ADMIN_URL.'sale/sale-invoice.php');
		}else{
			$obj_msg = load_class('InfoMessages');
			$imsg->setMessage('Error Occur. Please try again later.', 'error');
			redirect_header(ADMIN_URL.'sale/sale-invoice.php');
		}
	}

	function makePurchaseReceiptVoucher($receipt_arr, $project_id){
		global $db;
		$images_arr =array();
		$voucher_last = $db->fetch_array_by_query("select * from voucher where company_id=".getCompanyId()." and type='s_invoice'  ORDER BY ID DESC LIMIT 1");
		$voucher_no= $voucher_last['voucher_no']+1;
		$voucher_arr = array();
		$voucher_arr['voucher_series'] ='si';
		$voucher_arr['voucher_no'] = voucherNumber('s_invoice');
		$voucher_arr['date'] = time();
		$voucher_arr['type'] = 's_invoice';
		$voucher_arr['company_id'] = getCompanyId();
		$voucher_arr['user_id'] = getUserId();
		$voucher_arr['created_at'] = time();
		$voucher_arr['updated_at'] = time();
		$voucher_arr['total_amount'] = $receipt_arr['grand_total'];
		$voucher_arr['sale_invoice'] = $receipt_arr['id'];
		$voucher_arr['attachment'] = $receipt_arr['attachment'];
		$voucher_arr['narration'] =$_POST['narration'];
		$voucher_id = $db->insert($voucher_arr,'voucher');
		return $voucher_id;
	}

	function makeInvoiceTransactions($voucher_id,$receipt_arr,$head,$subhead){
		global $db;
		$db->select("select * from s_invoice_transaction where company_id=".getCompanyId()." and s_i_id = ".$receipt_arr['id']);
		$s_invoice_transactions = $db->fetch_all();
		$rate= 0;
		$transaction_arr = array();
		if($s_invoice_transactions){
			$transaction_arr['ledger_id'] = $receipt_arr['customer_id'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description']=$receipt_arr['narration'];
			$transaction_arr['type'] = 'debit';
			$transaction_arr['amount'] = $receipt_arr['total_amount'];
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			$transaction_arr['location_id'] = intval($head);
			$transaction_arr['sub_location_id'] = intval($subhead);
			$dr_transaction_id = $db->insert($transaction_arr,'transactions');
			
		}
			
		if($s_invoice_transactions){
			$transaction_arr = array();
			$transaction_arr['ledger_id'] = $receipt_arr['project_id'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description']=$receipt_arr['narration'];
			$transaction_arr['type'] = 'credit';
			$transaction_arr['amount'] = $receipt_arr['total_amount'];
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			$transaction_arr['location_id'] = intval($_POST['cr_location']);
			$transaction_arr['sub_location_id'] = intval($_POST['cr_sublocation']);
			$cr_transaction_id = $db->insert($transaction_arr,'transactions');

		}
			//debit Project
			
		
		//debit costcenter
		/*$cost_cent_row=$db->fetch_array_by_query("select * from cost_center_linked where voucher_type='pv' and type_head='subhead' and ledger_id= ".$project_id);
		$cost_transaction=array();
		$cost_transaction['voucher_id'] = $voucher_id;
		$cost_transaction['ledger_id'] = $cost_cent_row['ledger_id'];
		$cost_transaction['cost_cat_id'] = $cost_cent_row['cost_category'];
		$cost_transaction['cost_center_id'] = $cost_cent_row['cost_center'];
		$cost_transaction['transaction_id'] = $transaction_id;
		$cost_transaction['cost'] = $amount;
		$cost_transaction['type']='debit';
		$cost_transaction['company_id']=getCompanyId();
		$cost_transaction['user_id']=getUSerId();
		$cost_transaction['created_at'] = time();
		$cost_transaction['updated_at'] = time();
		$cost_transaction['location_id'] = intval($loc_id);
		$cost_transaction['sub_location_id'] = intval($sub_loc_id);
		$cost_categroy_transactions = $db->insert($cost_transaction,'cost_categroy_transactions');*/



		//Unloading Ledger
		if(isset($_REQUEST['unloading_ledger']) && intval($_REQUEST['unloading_ledger']) > 0 && 
		isset($_REQUEST['unloading_amount']) && intval($_REQUEST['unloading_amount']) > 0 ){
			$transaction_arr = array();
			$transaction_arr['ledger_id'] = $_REQUEST['unloading_ledger'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description']=$receipt_arr['narration'];
			$transaction_arr['type'] = 'credit';
			$transaction_arr['amount'] = $receipt_arr['unloading_charges'];
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			
			$transaction_id = $db->insert($transaction_arr,'transactions');
		}

		//Loading Ledger
		if(isset($_REQUEST['loading_ledger']) && intval($_REQUEST['loading_ledger']) > 0 && 
		isset($_REQUEST['loading_amount']) && intval($_REQUEST['loading_amount']) > 0 ){
			$transaction_arr = array();
			$transaction_arr['ledger_id'] = $_REQUEST['loading_ledger'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description']=$receipt_arr['narration'];
			$transaction_arr['type'] = 'credit';
			$transaction_arr['amount'] = $receipt_arr['loading_charges'];
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			
			$transaction_id = $db->insert($transaction_arr,'transactions');
		}

		// Other Ledger
		if(isset($_REQUEST['other_ledger']) && intval($_REQUEST['other_ledger'] ) > 0 && 
		isset($_REQUEST['other_amount']) && intval($_REQUEST['other_amount'] ) > 0 ){
			$transaction_arr = array();
			$transaction_arr['ledger_id'] = $_REQUEST['other_ledger'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description']=$receipt_arr['narration'];
			$transaction_arr['type'] = 'credit';
			$transaction_arr['amount'] = $receipt_arr['other_charges'];
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			
			$transaction_id = $db->insert($transaction_arr,'transactions');
		}

		//Freight Loading
		if(isset($_REQUEST['freight_ledger']) && intval($_REQUEST['freight_ledger'] ) > 0 && 
		isset($_REQUEST['freight_amount']) && intval($_REQUEST['freight_amount'] ) > 0 ){
			$transaction_arr = array();
			$transaction_arr['ledger_id'] = $_REQUEST['freight_ledger'];
			$transaction_arr['voucher_id'] = $voucher_id;
			$transaction_arr['description']=$receipt_arr['narration'];
			$transaction_arr['type'] = 'credit';
			$transaction_arr['amount'] = $receipt_arr['freight_charges'];
			$transaction_arr['company_id'] = getCompanyId();
			$transaction_arr['user_id'] = getUSerId();
			$transaction_arr['created_at'] = time();
			$transaction_arr['updated_at'] = time();
			
			$transaction_id = $db->insert($transaction_arr,'transactions');
		}
		return true;
	}
	$page_title="";
	$tab ="Sale Invoice";
	$general_head = getSettingRow('general_head');
	$head = $db->fetch_array_by_query("Select * from item_location where id=".$general_head['value']); 
	$general_subhead = getSettingRow('general_subhead');
	$sub_head = $db->fetch_array_by_query("select * from item_sublocation where id=".$general_subhead['value']);
?>
<!DOCTYPE html>
<html>
<head>
<?php include("includes/common-header.php");?>
<link rel="stylesheet" href="<?php echo BASE_URL;?>css/voucher.css?v=7.3" type="text/css"/>
<style type="text/css">
.modal-href{display: inline-table;}
.fa{font-size: 16px}
.rate ,.discount,#delivery_date{background: white !important;color: black !important; border:1px solid #bbbbbb !important; padding: 7px 7px !important}
@media only screen and (max-width: 600px) and (min-width: 300px)  {.modal-href{display:inherit !important;}
	.table .form-control{width: 200px;}
}
@media only screen and (min-width: 1450px){.modal-href{display:block !important;margin: 16px auto;}
}
@media only screen and (min-width: 1450px)  {
	.modal-href{display:block !important;
		margin: 16px auto;
	}
}

@media only screen and (min-width:786px)  {
	.modal-rate{width: 90%;}
}
@media only screen and (min-width:787px) { .vl1 .form-control{width: 80%;}
}
@media only screen and (min-width:787px) {#sliderModal .vl1 .row{padding-left: 10% !important;}
}
.vl {border-left: 4px solid green;height: 170px;}
.radio-inline{margin: 0 !important}
.vl1 {border-radius: 4px;border-bottom: 4px solid green;width: 100%;border-right: 4px solid green;border-left: 4px solid green;}
@media only screen and (max-width:480px) {button { width: 98% !important;display:block;	}
	.vl {border-left: 4px solid green;height: 20px !important;margin-left: 50%;	}
}
.radio-inline{margin: 0 !important}
@media screen and (min-width: 1370px) and (max-width: 1600px){
	.radio-inline{margin-left: 10px !important}

} 
@media (min-width:768px) and (max-width:992px) {

	.table-responsive>.table>tbody>tr>td, .table-responsive>.table>tbody>tr>th, .table-responsive>.table>tfoot>tr>td, .table-responsive>.table>tfoot>tr>th, .table-responsive>.table>thead>tr>td, .table-responsive>.table>thead>tr>th {
		white-space: nowrap;
	}
	.table-responsive>.table {
	    margin-bottom: 0;
	    
	}
	.table-responsive {
	    width: 100% !important;
	    margin-bottom: 15px !important;
	    overflow-y: hidden !important;
	    -ms-overflow-style: -ms-autohiding-scrollbar ;
	    border: 1px solid #ddd !important;
	}
	.table {
	    width: 2000px !important;
	    max-width: inherit !important !important
	}

}
.P_b{border-bottom-left-radius:0px !important}
.P_b{border-top-left-radius:0px !important}
.pv_b{border-bottom-right-radius:0px !important}
.pv_b{border-top-right-radius:0px !important}
.chosen-container-single{width: 0px}
.input-group .input-group-addon.pq{width:20%;}
.foot, .foot>tr, .foot>tr>td{border:none !important;}
.charges_row{background-color: #f9f9f9}
 @media (min-width:992px) and (max-width:1200px) {
	.table-responsive>.table>tbody>tr>td, .table-responsive>.table>tbody>tr>th, .table-responsive>.table>tfoot>tr>td, .table-responsive>.table>tfoot>tr>th, .table-responsive>.table>thead>tr>td, .table-responsive>.table>thead>tr>th {
	    white-space: nowrap;
	        
	}
	.table-responsive>.table {
	    margin-bottom: 0;
	    
	}
	.table-responsive {
	    width: 100% !important;
	    margin-bottom: 15px !important;
	    overflow-y: hidden !important;
	    -ms-overflow-style: -ms-autohiding-scrollbar ;
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
		<?php include("includes/header.php");?>
		<div class="content-wrapper">
			<section class="content-header">
				<h1>
				<?php echo ucfirst($tab);?>
					<span class="small">Add</span>
				</h1>
				<ol class="breadcrumb">
					<li><a href="<?php echo ADMIN_URL;?>"><i class="fa fa-dashboard"></i> Home</a></li>
					<li class="active"><?php echo $page_title;?></li>
				</ol>
			</section>
			<?php  $db->select("SELECT * FROM `ledger` where active_project='yes' and company_id=".getCompanyId()." order by id desc");
			$ledgers = $db->fetch_all();?>
			<form method="post" enctype="multipart/form-data" id='invoiceForm' name="form" autocomplete="off">
				<input type="hidden" name="command" value="add" >
				<input type="hidden" name="walk_in" class="walk_in" >
				<div class="append_image"></div>
				<section class="content">
					<div class="row">
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="row clearfix">
								<div class="span12">
									<?php echo $imsg->getMessage();?>
								</div>
							</div>
						</div>
						<div class="box box-danger">
							<div class="box-body">
								<div class="row lb_top">
									<div class="col-lg-2 col-md-4">
										<div class="form-group">
											<label > Sale Invoice No: </label>
											<div class="input-group">
												<span class="input-group-addon pq">SI</span>
												<input type="hidden" name="purch_invoice_series" value="<?php echo "SI" ?>">
												<input type='hidden' name='sub_ledg' class='sub_ledg'>
												<input id="msg" type="text" class="form-control" placeholder=" Invoice No." name="purch_invoice_no" value="<?php echo $receipt_ar ?>" readonly>
											</div>
										</div>
									</div>
									<?php
										$date =  strtotime(date('d-m-Y'));
										$day_value = date('l',$date); ?>
									<div class="col-lg-2">
										<div class="form-group">
											<label> Date & Day: </label>
											<div class="input-group">
												<span class="input-group-addon" style="width: 50%;padding: 0;">
													<input type="text" class="form-control  quotation-date" placeholder=" Date " value="<?php echo date('d-m-Y') ?>" name="invoice_date" autocomplete="off" readonly >
												</span><input type="text" class="form-control input-group-addon" placeholder=" Day" id="weekDay" name="invoice_day" value="<?php echo $day_value ?>" readonly>
											</div>
										</div>
									</div>
									</div>
									<div class="col-sm-12">
										<div class="col-md-9" style="padding-left:0">
											<div class="col-md-8">
												<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content;"> Debit : </h4>
												<div class="form-group col-md-4 col-xs-12 no-gutter" style="padding-left: 0PX">
													<label> Customer </label>
													<select class="form-control chosen-transporter inv_transporter_id" name="customer_id" required="true" onchange="checkWalkInCustomer(this)">
														<option value="0"> Select Customer </option>
														<?php
														$db->select("select * from ledger where (active_customer='yes' || active_walk_in_customer='yes') and company_id=".getCompanyId());
														$transporters = $db->fetch_all();
														$select="";
														foreach ($transporters as $transporter){ 
														?>
														<option value="<?php echo $transporter['id']; ?>"> <?php echo $transporter['name']; ?></option>
														<?php } ?>
													</select>
													
													<button style="display:none" type="button" data-toggle="modal" data-target="#sub_ledger_model"  class="walk_in_button_show btn btn-primary btn-sm">show Walk In</button>
												<span class="walk_in_text_show"></span>


													
												</div>
												<div class="col-md-4 location_div">
													<label> Location </label>
													<select class="transporter location_select proj_location chosen-select location_id" onchange="get_sub_location(this)" name="cr_location" required="true">
														<option selected>Select Location</option>
															<?php
															$db->select('SELECT * FROM item_location where company_id='.getCompanyId()." order by id desc");
															$locationResults = $db->fetch_all();
															$select="";
															
															foreach ($locationResults as $locationResult){?>
														<option value="<?php echo $locationResult['id'] ?>" ><?php echo $locationResult['name'] ?></option>
															<?php } ?>
													</select>
												</div>
												<div class="col-md-4 location_div">
													<div class="form-group">
														<label> Sublocation </label>
														<select name="cr_sublocation" class=" form-control chosen-select sub_location" style="z-index:99999">
														<option value="">Select Sublocation</option>
														</select>
													</div>
												</div>
											</div>
											<div class="col-md-4 no-gutter">	
												<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content;"> Credit : </h4>
												<div class="col-md-6 no-gutter">
													<div class="form-group col-xs-12 no-gutter" style="padding-left: 0PX">
														<label> Project </label>
														<select class="form-control chosen-select inv_transporter_id" name="project_ledger" required="true">
															<?php
															$ledger_row = getSettingRow("sales_ledger");
															$sales_ledger = $db->fetch_array_by_query("Select * from ledger where id=".$ledger_row['value']);
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
															$db->select("select * from ledger where third_party='yes' and company_id=".getCompanyId().' order by id desc');
															$third_parties = $db->fetch_all();
															foreach ($third_parties as $third_partie){
																if($third_partie['id']==$po_row['third_party']) {
																	$select="selected";
																} else {
																	$select="";
																}?>
															<option value="<?php echo $third_partie['id']; ?>" <?php echo $select;?>> <?php echo $third_partie['name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
											</div>
										</div>
										<!-- </div> -->
										<div class="col-sm-3" style="float:right">
											<h4 style="border-bottom: 3px solid #dd4b39;width:fit-content; margin-top:0px;margin-bottom:5px"> Select Modes: </h4>
											<div class="col-md-12">
												<input type="hidden" name="vehicle_mode" value="single_vehicle">
												<div class="hidden">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content;"> Vehicles </h5>
													<label class="radio-inline"><input type="radio" class="vehicle_mode" id="single_veh" value="single_vehicle" name="vehicle_mode" > Single Vehicle</label>
													<label class="radio-inline"><input type="radio" class="vehicle_mode" id="multiple_veh" name="vehicle_mode" value="multiple_vehicle" checked> Multiple Vehicle</label>
												</div>
										<!-- 		<div class="location col-md-6 no-gutter">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content;  margin-top:5px;margin-bottom:5px"> Location </h5>
													<label class="radio-inline" style="margin-right:10px !important;"><input type="radio"   value="multiple" id="multiple_loc" name="location_mode" > Multiple </label>
													<label class="radio-inline"><input type="radio" checked name="location_mode" id="single_loc" value="single" > Single</label>
												</div> -->
												<div class="discount_with_vehicle col-md-6 no-gutter">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content; margin-top:5px;margin-bottom:5px"> Discount </h5>
													<label class="radio-inline"><input type="radio" value="enable" id="enable_dis" name="discount_mode" > Enable</label>
													<label class="radio-inline"><input type="radio" name="discount_mode" id="disable_dis" value="disable" checked> Disabled</label>
												</div>
												<div class="col-md-6 no-gutter">
													<h5 style="border-bottom: 3px solid #00a65a;width:fit-content; margin-top:5px;margin-bottom:5px"> Third Party </h5>
													<label class="radio-inline"><input type="radio" value="single_party" id="single_party_check" name="ledger_party" checked=""> Single </label>
													<label class="radio-inline"><input type="radio" name="ledger_party" value="multiple_party"> Multiple </label>
												</div>
											</div>
										</div>
									</div>
									<div class="clearfix" style="margin-bottom: 15px"></div>
 								</div>
								<div class="table-responsive www">
									<table class="table table-striped pq_table">
										<thead>
											<tr class="title_bg">
												<th>Sr #</th>
												<!-- <th class="hidden serial_no_heading">Serial No</th> -->
												<th class="hidden  third_party_heading">Third Party</th>
												<!-- <th>Project Name</th> -->
												<!-- <th class="location_td">Head</th> -->
												<!-- <th class="location_td">Sub Head</th> -->
												<th>Item Group</th>
												<th >Item Name</th>
												<th>Quantity</th>
												<th>Rate</th>
												<th class="hidden discount_heading">Discount</th>
												<th>Amount</th>
												<th>Action</th>
											</tr>
										</thead>
										<tbody class="pq_body w-sm">
											<tr class="pq_row">
												<td class="sr_no_tab1 sr_no pq_dr_no">1</td>
												<input type="hidden" name="sr_no[]" class="input_sr" value="1">
												<input type="hidden" name="sub_item[]" class="sub_item" value="no">
												<input type="hidden" class="alternative_unit">
												<input type="hidden" class="main_item_unit">
												<input type="hidden" class="conversionToAlternativeUnit">
												<td class="hidden multiple_party_append"></td>
												<!-- <td class="location_td ">
													<select class="transporter chosen-select location_select proj_location location_id" onchange="get_sub_location(this)" name="dr_location[]" required="true">
														<option selected value="<?php echo $head['id']; ?>"><?php echo $head['name']; ?></option>
													</select>
												</td>
												<td class="sub_location_td ">
													<div class="form-group">
														<div class="input-group">
															<select  name="dr_sublocation[]" class="form-control chosen-select sub_location" style="z-index:99999">
																<option selected value="<?php echo $sub_head['id']; ?>"><?php echo $sub_head['name']; ?></option>
															</select>
															<span class="input-group-btn">
																<button class="btn btn-default" type="button"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span> 
														</div>
													</div>
												</td> -->
												<td class="group_chosen">
													<input type="hidden" name="dr_location[]" value="<?php echo $head['id']; ?>">
													<input type="hidden" name="dr_sublocation[]" value="<?php echo $sub_head['id']; ?>">
													<div style="width: 100% !important" class="input-group">
														<select name="item_group[]" onchange="group_item(this)" class="chosen-select form-control">
															<option> select Item Group</option>
															<?php
																$db->select('SELECT * FROM item_group where company_id='.getCompanyId());
																	$group_results = $db->fetch_all();
																	foreach ($group_results as $group_result){?>
																	<option id='<?php echo ($group_result['id']) ?>' value='<?php echo ($group_result['id']) ?>' > <?php echo $group_result['name'] ?>
																	</option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="brand_qual_modal">
													<div class="chosenWidth chosen">
														<div class="input-group">
															<select class="form-control chosen-select  check_item selecteditem" name="item_name[]" placeholder=" Enter Item Name" onchange="chosenValue(this)">
																<option value="0" selected=""> Select Item Name</option>
																<?php
																	$db->select('SELECT * FROM item where company_id='.getCompanyId());
																	$optionResults = $db->fetch_all();
																	foreach ($optionResults as $optionResult){?>
																		<option id='<?php echo ($optionResult['id']) ?>' value='<?php echo ($optionResult['id']) ?>'> <?php echo $optionResult['name'] ?> </option>
																<?php } ?>
															</select>
															<span class="input-group-btn">
																<button class="btn btn-default" type="button" onclick="showItemModal()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
														</div>
													</div>
													<div class="brand_md_button"></div>
												</td>
												<td>
													<div class="input-group table_inputs">
														<input type="number" value="" onkeyup="calculate_quantity(this)" name="quantity[]" class="form-control check_quantity quantity" placeholder="Quantity">
														<span class="pd_right input-group-addon"><b class="qty_symbol">unit</b></span>
													</div>
													<div class="invoice_md_button"></div>
												</td>
												<td class="mat_modal">
													<div class="row">
														<div class="input-group">
															<span class="input-group-btn show_mt_modal" style="display: none;">
																<button class="btn btn-primary" type="button" onclick="showCarrMat(this)"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
															<input type="number" name="rate[]" class="form-control rate" onkeyup="calculate_balance(this)" placeholder="Rate">
															<span class="input-group-addon"><strong>P/</strong><b class="qty_symbol_per">unit</b></span>
														</div>
														<p> Net Rate : <span class="net_rate_area">0</span></p>
														<p> eff Rate : <span class="eff_net_rate_area"> 0</span></p>
														<input type="hidden" name="net_rate[]" class="net_rate net_rate_area">
														<input type="hidden" name="eff_net_rate[]" class="eff_net_rate">
													</div>
													<input type="hidden" name="material_modal[]" class="material_mod" value="no">
												</td>
												<td class="hidden appened_discount_hidden"><input type="hidden" class="discount" value="0"></td>
												<td class="hidden enable_multiple_discount"></td>
												<td >
													<div class="input-group">
														<span class="input-group-addon"><b>PKR</b></span>
														<input type="number" name="amount[]" class="form-control input-group check_amount amount" placeholder="Amount">
														<input class="hidden_amount" type="hidden" name="">
													</div>
												</td>
												<td>
													<a class="btn btn-primary add_row" onclick="add_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
													<a class="btn btn-danger" class="remove_row" onclick="remove_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
													</a>
												</td>
											</tr>
										</tbody>
										<tfoot>
											<tr class="footer_bg">
												<td class="<?php if($po_row['party_mode']=="single_party"){ echo "hidden"; } ?> third_party_heading"></td>
												<td class="serial_td_hiddden <?php if($po_row['vehicle_mode']=="multiple_vehicle"){ echo "hidden"; } ?>"></td>
												
												<td style="text-align: center;"> Gross Total </td>
												<input type="hidden" class="tot_quantity" name="tot_quantity" value="<?php echo $po_row['total_quantity'] ?>">
												<td class="tot_quantity"><?php echo $po_row['total_quantity'] ?></td>
<!-- 												<td class="location_td"></td>
												<td class="location_td"></td> -->
												<td class="discount_heading <?php if($po_row['discount_mode']=="disable"){ echo "hidden"; } ?>"></td>
												<td>
													<input type="hidden" class="form-control" id="grand_total" name="grand_total" autocomplete="off" placeholder="Grand Total" value="<?php echo $po_row['grand_total'] ?>">
													<span class="grand_total hidden" style="text-align: right;"> <?php echo $po_row['grand_total']; ?></span>
													<input type="hidden" class="form-control" id="net" name="total_net" value="<?php echo $po_row['total_net'] ?>"><span class="net hidden" style="text-align: right;"> <?php echo $po_row['total_net'] ?> </span>
													<input type="hidden" class="form-control" id="total" name="total"  placeholder=" Gross Total" value="<?php echo $po_row['total_amount'] ?>">
													<span class="gross-total total" style="text-align: right;"> <?php echo $po_row['total_amount'] ?> </span>
												</td>
												<td class="third_party_td hidden"></td>
												<td></td>
											</tr>
											<!-- <tr style="background-color: #f9f9f9">
												<td class="third_party_heading <?php //if($po_row['party_mode']=="single_party"){ echo "hidden"; } ?>"></td>
												<td class="serial_td_hiddden <?php //if($po_row['vehicle_mode']=="multiple_vehicle"){ echo "hidden"; } ?>"></td>
												<td colspan="2" style="font-size: 18px; font-weight:700; color:#7f9d9d;">
													<label for="net" class="form-check-label"style="padding-right: 0px"><input id="discount_checkbox" type="checkbox" class="form-check-input" value="yes" name="discount_check"<?php //if ($po_row['discount_check']=="yes"){echo "checked"; } ?> <?php //if ($po_row['vehicle_mode']=="multiple_vehicle"){echo "disabled";} ?> >&nbsp;&nbsp;Discount:</label>
												</td>
												<td colspan="2"></td>
												<td class="dis_rate hidden">
													<div class="input-group">
														<input type="number" style="width: 200%" class="form-control" id="discount" onkeyup="netCharges(this)" name="total_discount" autocomplete="off" placeholder="Discount" value="<?php //echo $po_row['total_discount'] ?>">
													</div>
												</td>
												<td class="discount_heading <?php //if($po_row['discount_mode']=="disable"){ echo "hidden"; } ?>"></td>
											</tr> -->
											<!-- <tr class="footer_bg">
												<td class="third_party_heading <?php //if($po_row['party_mode']=="single_party"){ echo "hidden"; } ?>"></td>
												<td class="serial_td_hiddden <?php //if($po_row['vehicle_mode']=="multiple_vehicle"){ echo "hidden"; } ?>"></td>
												<td colspan="2" style="text-align: center;"> Net Total </td>
												<td class="tot_quantity"> <?php //echo $po_row['total_quantity'] ?> </td>
												<td></td>
												<td class="discount_heading <?php //if($po_row['discount_mode']=="disable"){ echo "hidden"; } ?>"></td>
												<td><input type="hidden" class="form-control" id="net" name="total_net" value="<?php// echo $po_row['total_net'] ?>"><span class="net" style="text-align: right;"> <?php //echo $po_row['total_net'] ?> </span></td>
												<td></td>
												<td></td>
												<td></td>
											</tr> -->
										</tfoot>
									</table>
									<table class=" table table-responsive table-striped">
										<?php /*<tfoot>
											<tr class="title_bg">
												<th style="width:5%">&nbsp;</th><th style="width:15%">Charges Type</th><th style="width:15%">Ledgers</th><th style="width:15%">Quantity</th><th style="width:20%">Rate</th><th style="width:15%">Amount</th><th style="width:15%">Action</th>
											</tr>
											<tr class="tr-charge charges_row">
												<td></td>
												<td>
													<select class="form-control charge-select" onchange="chargesRow(this)">
														<option> Please select Mode</option>
														<option value="loading_charg"> Loading Charges</option>
														<option value="unloading_charg"> Unloading Charges</option>
														<option value="other_charg"> Other Charges</option>
													</select>
												</td>
												<td colspan="5"></td>
											</tr>
											<tr class="hidden title_bg single_vehicle_quotation_th"></tr>
											<tr class="hidden single_vehicle_quotation"></tr>
											<tr class="footer_bg">
												<td></td>
												<td colspan="2">
													Grand Total
												</td>
												<td colspan="2"></td>
												<td>
													<input type="hidden" class="form-control" id="grand_total" name="grand_total" autocomplete="off" placeholder="Grand Total" readonly="">
													<span class="grand_total" style="text-align: right;"> 0 </span>
												</td>
												<td></td>
											</tr>
										</tfoot> */?>
										<tfoot>
											<!-- <tr class="title_bg">
												<th style="width:5%">&nbsp;</th><th style="width:15%">Charges Type</th><th style="width:15%">Ledgers</th><th style="width:15%">Quantity</th><th style="width:20%">Rate</th><th style="width:15%">Amount</th><th style="width:15%">Action</th>
											</tr> -->
											<?php if ($po_row['loading_ledger']!="" and $po_row['loading_charges']>0) {?>
												<!-- <tr class="tr-charge charges_row">
													<td></td>
													<td>
														<select class="form-control charge-select" name="loading_charg" onchange="chargesRow(this)">
															<option> Please select Mode</option>
															<option value="loading_charg" selected> Loading Charges</option>
															<option value="unloading_charg"> Unloading Charges</option>
															<option value="other_charg"> Other Charges</option>
														</select>
													</td>
													<td class="after_appened">
														<div class="chosen append">
															<div class="form-group">
																<select class="chosen-select form-control" name="loading_ledger" required="true">
																	<option> Loading Ledger </option>
																	<?php $db->select("select * from ledger where company_id=".getCompanyId()." and loading='yes' order by id desc");$loadings=$db->fetch_all();
																	foreach ($loadings as $loading){
																		if ($loading['id']==$po_row['loading_ledger']){
																			$select="selected";
																		}else {
																			$select="";
																		}
																		?>
																	<option value="<?php echo $loading['id']; ?>" <?php echo $select ?>><?php echo $loading['name'] ?></option>
																	<?php } ?>
																</select>
															</div>
														</div>
													</td>
													<td class="after_appened">
														<input type="number" name="loading_quantity" class="form-control chr_loading_quantity tot_quantity" readonly placeholder="Quantity" value="<?php echo $po_row['loading_quantity'] ?>" onkeyup="calculateCharges()">
													</td>
													<td class="after_appened">
														<div class="">
															<input type="number" name="loading_rate" class="form-control chr_loading_rate" placeholder="Rate" onkeyup="calculateCharges()" value="<?php echo $po_row['loading_rate'] ?>">
														</div>
													</td>
													<td class="after_appened">
														<input type="number" placeholder="Loading Charges" name="loading_amount" class="form-control chr_loading_amount" id="loading_charges" onkeyup="grand()" value="<?php echo $po_row['loading_charges'] ?>">
													</td>
													<td class="after_appened">
														<a class="btn btn-primary" onclick="addCharge(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>&nbsp;<a class="btn btn-danger" class="remove_row" onclick="removeCharge(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i>
														</a>
													</td>
												</tr> -->
											<?php } ?>
											<?php if ($po_row['unloading_ledger']!="" and $po_row['unloading_charges']>0) {?>
												<!-- <tr class="tr-charge charges_row">
													<td></td>
													<td>
														<select class="form-control charge-select" name="unloading_charg" onchange="chargesRow(this)">
															<option> Please select Mode</option>
															<option value="loading_charg"> Loading Charges</option>
															<option value="unloading_charg" selected> Unloading Charges</option>
															<option value="other_charg"> Other Charges</option>
														</select>
													</td>
													<td class="after_appened">
														<div class="chosen append">
															<div class="form-group">
																<select class="chosen-select form-control" name="unloading_ledger">
																	<option> Unloading Ledger </option>
																	<?php $db->select("select * from ledger where company_id=".getCompanyId()." and unloading='yes' order by id desc");$unloadings=$db->fetch_all();
																	 foreach ($unloadings as $unloading){
																	 	if ($unloading['id']==$po_row['unloading_ledger']){
																			$select="selected";
																		}else {
																			$select="";
																		}
																		?>
																	<option value="<?php echo $unloading['id']; ?>" <?php echo $select ?>><?php echo $unloading['name'] ?></option><?php } ?>
																</select>
															</div>
														</div>
													</td>
													<td class="after_appened">
														<input type="number" name="unloading_quantity" class="form-control chr_unloading_quantity tot_quantity" placeholder="Quantity" readonly value="<?php echo $po_row['unloading_quantity'] ?>" onkeyup="calculateCharges()">
													</td>
													<td class="after_appened">
														<div class="">
															<input name="unloading_rate" type="number" class="form-control chr_unloading_rate" placeholder="Rate" onkeyup="calculateCharges()" value="<?php echo $po_row['unloading_rate']?>">
														</div>
													</td>
													<td class="after_appened">
														<input type="number" name="unloading_amount" value="<?php echo $po_row['unloading_charges'] ?>" placeholder="Unloading Charges" class="form-control chr_unloading_amount" id="unloading_charges" onkeyup="grand()">
													</td>
													<td class="after_appened">
														<a class="btn btn-primary" onclick="addCharge(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>&nbsp;<a class="btn btn-danger" class="remove_row" onclick="removeCharge(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i>
														</a>
													</td>
												</tr> -->
											<?php } ?>
											<?php if ($po_row['other_ledger']!="" and $po_row['other_charges']>0) {?>
												<!-- <tr class="tr-charge charges_row">
													<td></td>
													<td>
														<select class="form-control charge-select" name="other_charg" onchange="chargesRow(this)">
															<option> Please select Mode</option>
															<option value="loading_charg"> Loading Charges</option>
															<option value="unloading_charg"> Unloading Charges</option>
															<option value="other_charg" selected> Other Charges</option>
														</select>
													</td>
													<td class="after_appened">
														<div class="chosen append">
															<div class="form-group">
																<select class="chosen-select form-control" name="other_ledger">
																	<option> Other Ledger </option>
																	<?php
																	 $db->select("select * from ledger where company_id=".getCompanyId()." order by id desc");
																	$others=$db->fetch_all();
																	foreach ($others as $other){
																		if ($other['id']==$po_row['other_ledger']){
																			$select="selected";
																		}else {
																			$select="";
																		}
																		?>
																	<option value="<?php echo $other['id']; ?>" <?php echo $select ?>><?php echo $other['name'] ?></option>
																		<?php } ?>
																</select>
															</div>
														</div>
													</td>
													<td class="after_appened">
														<input type="number" name="other_quantity" class="form-control chr_other_quantity tot_quantity" readonly placeholder="Quantity" onkeyup="calculateCharges()" value="<?php echo $po_row['other_quantity'] ?>">
													</td>
													<td class="after_appened">
														<div class="">
															<input type="number" name="other_rate" class="form-control chr_other_rate" placeholder="Rate" onkeyup="calculateCharges()" value="<?php echo $po_row['other_rate'] ?>">
														</div>
													</td>
													<td class="after_appened">
														<input type="number" placeholder="Other Charges" class="form-control chr_other_amount" name="other_amount" id="other_charges" onkeyup="grand()" value="<?php echo $po_row['other_charges'] ?>">
													</td>
													<td class="after_appened">
														<a class="btn btn-primary" onclick="addCharge(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a> &nbsp;<a class="btn btn-danger" class="remove_row" onclick="removeCharge(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i></a>
													</td>
												</tr> -->
											<?php } ?>
											<?php if ($po_row['loading_charges']==0 and $po_row['unloading_charges']==0 and $po_row['other_charges']==0): ?>

											<!-- <tr class="tr-charge charges_row">
												<td></td>
												<td>
													<select class="form-control charge-select" onchange="chargesRow(this)">
														<option> Please select Mode</option>
														<option value="loading_charg"> Loading Charges</option>
														<option value="unloading_charg"> Unloading Charges</option>
														<option value="other_charg"> Other Charges</option>
													</select>
												</td>
												<td colspan="5"></td>
											</tr> -->
											<?php endif ?>
											<!-- <tr class="hidden title_bg single_vehicle_quotation_th"></tr>
											<tr class="hidden single_vehicle_quotation"></tr> -->
											<!-- <tr class="footer_bg">
												<td></td>
												<td colspan="2">
													Grand Total
												</td>
												<td colspan="2"></td>
												<td>
													<input type="hidden" class="form-control" id="grand_total" name="grand_total" autocomplete="off" placeholder="Grand Total" value="<?php echo $po_row['grand_total'] ?>">
													<span class="grand_total" style="text-align: right;"> <?php echo $po_row['grand_total']; ?></span>
												</td>
												<td></td>
											</tr> -->
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
										<?php if ($po_row['attachment'] !=''){ ?>
											<div id="lightgallery">
											<?php
											$attachments = json_decode($po_row['attachment']); 
											$i=0;
											foreach($attachments as $attachment){$i++;?>
												<button onclick="delImage('<?php echo $attachment ?>',<?php echo $po_row['id']?>,<?php echo $i?>)" style="position: absolute;" type="button" class="img-<?php echo $i ?>">X</button>
												<a href="<?php  echo $url.$attachment; ?>" class="img img-<?php echo $i ?>">
												<img src="<?php  echo $url.$attachment; ?>"  data-src="<?php  echo $url.$attachment; ?>" style='height:100px; width:100px; padding: 10px; position: relative; margin-top: 20px;'>
												</a>
											<?php } ?>
											</div>
											<?php } ?>
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="main_button" style="text-align:right; padding-bottom: 10px ;margin: 10px">
										<input type="submit" name="purchaseInvoice" value="Save Purchase Invoice" class="btn btn-primary">
										<button type="button" class="btn btn-danger"> Close Purchase Invoice </button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>
			</form>
			<!-- Rate Supplier Quotation Modal -->
			<div class="modal fade rate_modal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-lg" role="document" style="width: 90%">
					<div class="modal-content  modal-rate" style="width: 100%; margin: 0 auto">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
							<h2 class="modal-title" id="exampleModalLabel"> Invoice Quotation </h2>
						</div>
						<div class="row clearfix">
							<div class="span12">
								<?php echo $imsg->getMessage();?>
							</div>
						</div>
						<div class="modal-body">
							<div class="box box-danger">
								<div class="box-body">
									<div class="table-responsive">
										<table class="table table-striped">
											<thead>
												<tr>
													<th style="width:12%">DLVR Type</th>
													<th style="width:20%">Supplier</th>
													<th style="width:14%">Veh_No</th>
													<th style="width:14%">Bilty_No</th>
													<th style="width:14%">Receipt_No</th>
													<th style="width:14%">Quantity</th>
													<th style="width:14%">Action</th>
												</tr>
											</thead>
											<tbody class="invoice_body">
												<tr class="invoice_row">
													<td>
														<div class="form-check">
															<input class="form-check-input showCharges" onclick="getDelivery(this)" id="delivery"type="checkbox">
															<input type="hidden" class="deliveryValue delivery_term delivery_term_name md_delivery inv_delivery_no" value="exdelivery" name="inv_delivery_no[]">
															<label class="form-check-label"> Ex-Factory </label>
														</div>
													</td>
													<td>
														<select class="supplier-select chosen-supplier md_supplier inv_supplier_no" name="inv_supplier_no[]">
															<option>Select Supplier Name </option>
															<?php
																$db->select("select * from ledger where active_supplier='yes' and company_id=".getCompanyId());
																$suppliers = $db->fetch_all();
																foreach ($suppliers as $supplier){?>
															<option value="<?php echo $supplier['id']; ?>"> <?php echo $supplier['name']; ?></option>
															<?php } ?>
														</select>
													</td>
													<td>
														<input type="text" class="form-control inv_veh md_veh inv_veh_no" name="inv_veh_no[]" placeholder="Enter Vehicle No">
													</td>
													<td>
														<input type="text" class="form-control inv_bilty md_bilty inv_bilty_no" name="inv_bilty_no[]" placeholder="Enter Bilty No">
													</td>
													<td>
														<input type="text" class="form-control inv_receipt md_receipt inv_receipt_no" name="inv_receipt_no[]" placeholder="Enter Receipt No">
													</td>
													<td>
														<input type="text" class="form-control inv_quantity md_quantity" onkeyup="invoice_total_quantity(this)" name="inv_quantity[]" placeholder=" Enter Quantity">
													</td>
													<td>
														<a class="btn btn-primary" onclick="add_invoice_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
														<a class="btn btn-danger" onclick="remove_invoice_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
														</a>
													</td>
												</tr>
											</tbody>
											<tfoot>
												<tr>
													<td></td>
													<td></td>
													<td></td>
													<td>TOT Quantity :
														<span class="total_quantity"></span>
														<input type="hidden" name="total_quantity" class="inp_total_quantity total_quantity_name">
													</td>
												</tr>
											</tfoot>
										</table>
									</div>
									<div class="rate-button" style="margin:10px"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- END OF RATE QUOTATION MODAL -->
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
							<?php echo $imsg->getMessage();?>
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
														$project_rows=$db->fetch_all();
														foreach ($project_rows as $project_row){ ?>
														<option value="<?php echo $project_row['id'] ?>"> <?php echo $project_row['name']; ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
									</div>
									<div class="ledger-button">
										<button type="button" class="btn btn-primary pull-left" name="command" value="Add" onclick="addLocation()" >Save Location</button>
										<button type="button" class="btn btn-danger pull-right" data-dismiss="modal">Close</button>
									</div>
								</div>
								<div class="ledger-loading" style='display:none; '>
									<div>&nbsp;</div>
									<img src="<?php echo BASE_URL.'images/loading.gif'?>" style="height: 100px;">
								</div>
								<div class="clearfix"></div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<!--  Add Vendor Ledger -->
		<div class="modal fade" id="addVendor" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h2 class="modal-title" id="exampleModalLabel"> Add Vendor </h2>
					</div>
					<div class="row clearfix">
						<div class="span12">
							<?php echo $imsg->getMessage();?>
						</div>
					</div>
					<div class="modal-body">
						<form method="post" id="form1">
							<div class="box box-danger">
								<div class="box-body">
									<div class="row">
										<div class="col-sm-12">
											<div class="form-group">
												<label for="name">Vendor Name:</label>
												<input type="text" class="form-control" placeholder="Enter Vendor Name" name="name">
											</div>
										</div>
									</div>
									<div class="ledger-button">
										<button type="button" class="btn btn-primary pull-left" name="command" value="Add"> Save Vendor</button>
										<button type="button" class="btn btn-danger pull-right" data-dismiss="modal">Close</button>
									</div>
								</div>
								<div class="ledger-loading" style='display:none; '>
									<div>&nbsp;</div>
									<img src="<?php echo BASE_URL.'images/loading.gif'?>" style="height: 100px;">
								</div>
								<div class="clearfix"></div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

		<!-- CARR MAT Modal -->
		<div class="modal fade carr_mat_modal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-md" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h2 class="modal-title" id="exampleModalLabel"> Extra Rate Charges</h2>
					</div>
					<div class="row clearfix">
						<div class="span12">
							<?php echo $imsg->getMessage();?>
						</div>
					</div>
					<div class="modal-body">
						<div class="box box-danger">
							<div class="box-body">
								<div class="col-sm-6 col-xs-12">
									<label> Carriage:</label>
									<input type="number" class="form-control md_carriage inv_carriage" placeholder="Enter Carriage" name="inv_carriage[]">
								</div>
								<div class="col-sm-6 col-xs-12">
									<label> Material: </label>
									<input type="number" class="form-control md_material inv_material" placeholder="Enter Materail" name="inv_material[]">
								</div>
								<div class="clearfix"></div>
								<div class="clearfix"></div>
								<div class="carr-mat-button" style="margin:10px"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!-- END OF CARR MAT -->
		<?php /*
		<!-- Brand Material  -->
		<div class="modal fade brand_quality_modal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-lg" role="document" style="width:90%">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
						<h2 class="modal-title" id="exampleModalLabel"> Items Specification</h2>
					</div>
					<div class="row clearfix">
						<div class="span12">
							<?php echo $imsg->getMessage();?>
						</div>
					</div>
					<div class="modal-body">
						<div class="box box-danger">
							<div class="box-body text-left">
								<div class="table-responsive">
									<div class="formLoc">
										<input type="hidden" class="head_inp" value=" ">
										<input type="hidden" class="subhead_inp" value=" ">
									</div>
									<table class="table table-striped sale_item_table">
										<thead>
											<tr>
												<th class="brand_td">Brand</th>
												<th class="quality_td">Quality</th>
												<th class="color_td">Color</th>
												<th class="warehouse_td">Warehouse</th>
												<th class="section_td">Section</th>
												<th class="article_td"> article</th>
												<th class="finishingType_td">Finishing TYpe</th>
												<th>Quantity</th>
												<th>Action</th>
											</tr>
										</thead>
										<tbody class="sale_item_body">
											<tr class="sale_item_row">
												<td class="brand_td">
													<div class="form-group">
														<select class="form-control md_brand chosen_brand selectbrand inv_brand" name="inv_brand[]">
															<option value="0"> Select Brand </option> 
															<?php $db->select("select * from item_brand where company_id=".getCompanyId()." order by id desc");
															$brands = $db->fetch_all();
															foreach($brands as $brand){?>
																<option value="<?php echo $brand['id'] ?>"> <?php echo $brand['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="quality_td">
													<div class="form-group">
														<select class="form-control md_quality chosen_quality selectquality inv_quality" name="inv_quality[]">
															<option value="0"> Select Quality </option> 
															<?php $db->select("select * from item_quality where company_id=".getCompanyId()." order by id desc");
															$qualities = $db->fetch_all();
															foreach($qualities as $quality){ ?>
																<option value="<?php echo $quality['id'] ?>"><?php echo $quality['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="color_td">
													<div class="form-group">
														<select class="form-control md_color chosen_color selectcolor inv_color" name="inv_color[]">
															<option value="0"> Select Color </option> 
															<?php $db->select("select * from item_color where company_id=".getCompanyId()." order by id desc");
															$colors = $db->fetch_all();
															foreach($colors as $color){ ?>
																<option value="<?php echo $color['id'] ?>"><?php echo $color['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="warehouse_td">
													<div class="form-group">
														<select onchange="getSection(this)" class="form-control md_warehouse chosen_warehouse selectwarehouse inv_warehouse" name="inv_warehouse[]">
															<option value="0"> Select Warehouse </option> 
															<?php $db->select("select * from warehouse where company_id=".getCompanyId()." order by id desc");
															$warehouses = $db->fetch_all();
															foreach($warehouses as $warehouse){ ?>
																<option value="<?php echo $warehouse['id'] ?>"><?php echo $warehouse['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="section_td">
													<div class="form-group">
														<select class="form-control md_section chosen_section selectsection inv_section" name="inv_section[]">
															<option value="0"> Select Section </option> 
															<?php $db->select("select * from sections where company_id=".getCompanyId()." order by id desc");
															$sections = $db->fetch_all();
															foreach($sections as $section){ ?>
																<option value="<?php echo $section['id'] ?>"><?php echo $section['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="article_td">
													<div class="form-group">
														<select class="form-control md_article chosen_article selectarticle inv_article" name="inv_article[]">
															<option value="0"> Select Article </option> 
															<?php $db->select("select * from article where company_id=".getCompanyId()." order by id desc");
															$articles = $db->fetch_all();
															foreach($articles as $article){ ?>
																<option value="<?php echo $article['id'] ?>"><?php echo $article['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td class="finishingType_td">
													<div class="form-group">
														<select class="form-control md_finishingType chosen_finishingType selectfinishingType inv_finishingType" name="inv_finishingType[]">
															<option value="0" > Select Finishing Type </option> 
															<?php $db->select("select * from finishing_type where company_id=".getCompanyId()." order by id desc");
															$finishing_types = $db->fetch_all();
															foreach($finishing_types as $finishing_type){ ?>
																<option value="<?php echo $finishing_type['id'] ?>"><?php echo $finishing_type['name'] ?></option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td>
													<div class="form-group">
														<input type="number" placeholder="Quantity" name="inv_quantity[]" class="inv_quantity form-control">
														<span class="quantity_span" style="margin-top:10px"></span>
														<input type="hidden" class="inv_item_rate" name="inv_rate[]">
													</div>
												</td>
												<td>
													<button onclick="AddSaleItemRow()" type="button" class="btn btn-primary"> <i class=" fa fa-plus-circle"></i></button>
													<button onclick="RemoveSaleItemRow(this)" type="button" class="btn btn-danger"> <i class=" fa fa-minus-circle"></i></button>
												</td>
											</tr>
										</tbody>
									</table>
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
		*/?>
		<!-- Brand Material  -->
		<div class="modal fade SubItemModal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" >
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
							<?php echo $imsg->getMessage();?>
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
									<table class="table table-striped SubItemTable">
										<thead>
											<tr>
												<th>Code</th>
												<th>Article</th>
												<th class="quality_td">Quality</th>
												<!-- <th class="warehouse_td">Warehouse</th>
												<th class="section_td">Section</th> -->
												<th class="color_td">Color</th>
												<th>Available Quantity</th>
												<th>Quantity</th>
												<th>Action</th>
											</tr>
										</thead>
										<tbody class="SubItemBody">
											<tr class="SubItemRow">
												<td>
													<div class="form-group">
														<select onchange="getSubwareHouseITemQuantity(this)" class=" SubItemCode chosen-code chosen-select form-control" >
															<option value="0"> Select Subitem code</option>
														</select>
														<h4 class="SubItemCodeText"></h4>
														<input type="hidden" class="SubITemID" >
													</div>
												</td>
												<td>
													<div class="form-group">
														<?php
														$obj_article = load_class('itemarticle');
														$articles = $obj_article->getAllRecords();	
														?>
														<select onchange="CheckSubItem(this)" class="chosen-select form-control SubItemArticle chosen_article">
															<option value="0">Select Articles</option>
															<?php
															foreach($articles as $article){
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
													<div class="input-group">
														<input type="number" value="0" class="form-control available_quantity" readonly >
														<span class="input-group-addon qty_symbol">unit</span>
													</div>
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
														$db->select("Select * from section where FIND_IN_SET(".getCompanyId().",company_id)");
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
												<td>
													<div class="input-group">
														<input type="number" class="form-control inv_quantity" placeholder="Enter Quantity">
														<span class="input-group-addon qty_symbol">unit</span>
													</div>
													<div style="margin-top: 10px"><span class="alterNateUnitSpan"></span></div>
												</td>
												<td>
													<button class="btn btn-primary" onclick="AddSubItemRow(this)" type="button"><i class="fa fa-plus-circle"></i></button>
													<button class="btn btn-danger" onclick="removeSubItemrow(this)" type="button"><i class="fa fa-minus-circle"></i></button>
												</td>
											</tr>
										</tbody>
										<tfoot>
											<tr>
												<td></td>
												<td></td>
												<td class="section_td"></td>
												<!-- <td class="warehouse_td"></td> -->
												<td class="color_td"></td>
												<td class="quality_td"></td>
												<td class="quantity_td"><span class="quantity_span">Total Quantity : <b class="quantity_b">0</b></span> <input type="hidden" class="total_auantity" ></td>
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
							        		<input type="text" onkeyup="getSubledger()" class="form-control sub_ledger_id_card"  placeholder="Id Card" maxlength="15" name="sub_ledger_id_card" required="required"> 
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
									<div class="col-xs-12">
										<button type="button" class="btn btn-primary pull-left"  onclick="insertSubLedger(this)">Verified Save</button>
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
												<tr>
													<td colspan="4">No Sub Ledger</td>
												</tr>
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
		<?php include("includes/item-popup.php");?>
		<?php include("includes/footer.php");?>
		<div class='control-sidebar-bg'></div>
	</div>
	<?php include("includes/footer-jsfiles.php");?>
	<?php include("includes/popups-validation.php");?>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.10/jquery.mask.js"></script>
	<script type="text/javascript">
		$('.sub_ledger_id_card').keyup(function(){
		$('input[name="sub_ledger_id_card"]').mask('00000-0000000-0')});
		$('.sub_ledger_mobile').keyup(function(){
		$('input[name="sub_ledger_mobile"]').mask('0000000000')});
		$(document).ready(function() {
			$('.rate').each(function(index, el) {
				calculate_balance(el);		
			});	
			$(".chosen-project").chosen({width:'100%'});
		});
		$('input[type=radio][name=discount_mode]').change(function(){
			if (this.value == 'enable'){
				var multiple_discount ='<div class="input-group table_inputs"><input type="number" name="discount[]" class="form-control discount" onkeyup="calculate_discount(this)" placeholder=" Enter Discount " required="true" value="0"><span style="padding: 0px;" class="pd_right input-group-addon"><select onchange="calculate(this)" class="discount_type form-control" name="balance_category[]" style="width: auto;"><option selected value="Rs">No</option><option value="%">%</option></select></span></div><input type="hidden" class="check_percentage" value="no" name="check_percentage[]"><div class="individusl_discount"></div>';
				$('.enable_multiple_discount').html(multiple_discount);
				$('.enable_multiple_discount').removeClass('hidden');
				$('.rate').trigger("onkeyup");
				$('.discount_heading').removeClass('hidden');
				$('.appened_discount_hidden').html("");
			} else if (this.value == 'disable') {
				var hidden_discount = '<input type="hidden" class="discount" value="0">';
				$('.appened_discount_hidden').html(hidden_discount);
				$('.enable_multiple_discount').html("");
				$('.enable_multiple_discount').addClass('hidden');
				$('.discount_heading').addClass('hidden');
				$('.rate').trigger("onkeyup");
			}
		});

		$('input[type=radio][name=ledger_party]').change(function() {
			if(this.value=='single_party'){
				var first_single='<div class="form-group col-xs-12 no-gutter"><label> Third Party </label><select class="form-control chosen_party" name="third_party_single"><option value="0"> Select Third Party </option><?php $db->select("select * from ledger where third_party='yes' and company_id=".getCompanyId().' order by id desc');$third_parties = $db->fetch_all(); foreach($third_parties as $third_partie){ ?><option value="<?php echo $third_partie['id']; ?>"> <?php echo $third_partie['name']; ?></option><?php } ?></select></div>';
				$('.single-party-div').html(first_single).show();
				$('.multiple_party_append').addClass('hidden');
				$('.third_party_heading').addClass('hidden');
				$('.multiple_party_append').html("");
				$(".third_party_td").addClass("hidden");
				$('.chosen_party').chosen({width:'100%'});
			} else if (this.value=='multiple_party') {
				$('.single-party-div').html("").hide();
				var multiple_party_mode_td ='<select class="form-control chosen_party" name="inv_third_party_id[]"><option value="0"> Select Third Party </option><?php $db->select("select * from ledger where third_party='yes'");$third_parties = $db->fetch_all();foreach ($third_parties as $third_partie){?><option value="<?php echo $third_partie['id']; ?>"> <?php echo $third_partie['name']; ?></option><?php } ?></select>';
				$(".multiple_party_append").html(multiple_party_mode_td);
				$('.chosen_party').chosen({width: "100%"});
				$(".third_party_td").removeClass("hidden");
				$('.multiple_party_append').removeClass('hidden');
				$('.third_party_heading').removeClass('hidden');
				$('.multiple_party_append').show();
			}
		});

		$("input[type=radio][name=location_mode]").change(function(){
			if(this.value == 'single'){
				$(".location_td").addClass("hidden");
				$(".location_td").find("select").chosen("destroy").attr("disabled","disabled");
				$(".sub_location_td").addClass("hidden");
				$(".sub_location_td").find("select").chosen("destroy").attr("disabled","disabled");
				$(".location_div").find("select").chosen("destroy").removeAttr("disabled").chosen({width:'100%'});
				$(".location_div").removeClass("hidden");
				
			}else if(this.value=='multiple'){
				$(".location_td").removeClass("hidden");
				$(".location_td").find("select").chosen("destroy").removeAttr("disabled").chosen({width:'100%'});
				$(".sub_location_td").removeClass("hidden");
				$(".sub_location_td").find("select").chosen("destroy").removeAttr("disabled").chosen({width:'100%'});
				$(".location_div").find("select").chosen("destroy").attr("disabled","disabled");
				$(".location_div").addClass("hidden");
			}
		});

		$('input[type=radio][name=vehicle_mode]').change(function(){
			if(this.value=='single_vehicle'){
				var singleQuotationRowth='<th style="width:5%">&nbsp;</th><th style="width:15%">Freight Ledger</th><th style="width:15%">Vehicle No</th><th style="width:15%">Bilty No</th><th style="width:20%">Receipt No</th><th style="width:15%">Amount</th><th style="width:15%">&nbsp;</th>';

				var singleQuotationRow='<td></td><td><div class="chosen append"><div class="form-group"><select class="chosen-select form-control" name="freight_ledger" required="true"><option> Freight Ledger </option><?php $db->select("select * from ledger where company_id=".getCompanyId()." and freight='yes' order by id desc");$freights=$db->fetch_all(); foreach ($freights as $freight){?><option value="<?php echo $freight['id']; ?>"><?php echo $freight['name'] ?></option><?php } ?></select></div></div></td><td><input type="text" class="form-control vehicle" name="single_vehicle" placeholder="Enter Vehicle No"></td><td><input type="text" class="form-control bilty" name="single_bilty" placeholder="Enter Bilty No"></td><td><input type="text" class="form-control receipt" name="single_receipt" placeholder="Enter Receipt No"></td><td><input type="number" class="form-control chr_freight_amount " name="freight_amount" id="freight_charge" placeholder="freight Amount" onkeyup="grand()"></td>';
				var serial_no ='<input type="text" name="serial_no[]" class="form-control serial" placeholder="Serial No">';
				$('.discount_with_vehicle').show();
				$('.single_vehicle_quotation').removeClass('hidden');
				$('.single_vehicle_quotation_th').removeClass('hidden');
				$('.serial_no_appened').removeClass('hidden');
				$('.serial_no_heading').removeClass('hidden');
				$('.serial_td_hiddden').removeClass('hidden');
				$('.single_vehicle_quotation').html(singleQuotationRow);
				$('.single_vehicle_quotation_th').html(singleQuotationRowth);
				$('.serial_no_appened').html(serial_no);
				$('.chosen-select').chosen({width: "100%"});
				$("#discount_checkbox").prop("disabled", false);
			}
			else{
				$('.discount_with_vehicle').hide();
				$('.single_vehicle_quotation').addClass('hidden');
				$('.single_vehicle_quotation_th').addClass('hidden');
				$('.serial_no_appened').addClass('hidden');
				$('.serial_no_heading').addClass('hidden');
				$('.serial_td_hiddden').addClass('hidden');
				$('.serial_no_appened').html("");
				$('.single_vehicle_quotation').html("");
				$('.single_vehicle_quotation_th').html("");
				$("#discount_checkbox").prop("disabled", true);
			}
		});

		$("input[type=radio][name=location_mode]").change(function(){
			if(this.value == 'single'){
				$(".location_td").addClass("hidden");
				$(".location_td").find("select").chosen("destroy").attr("disabled","disabled");
				$(".sub_location_td").addClass("hidden");
				$(".sub_location_td").find("select").chosen("destroy").attr("disabled","disabled");
				$(".location_div").find("select").chosen("destroy").removeAttr("disabled").chosen({width:'100%'});
				$(".location_div").removeClass("hidden");
				$("input[value=multiple]").removeAttr("checked");
				$(this).attr('checked',true);
				
			}else if(this.value=='multiple'){
				$(".location_td").removeClass("hidden");
				$(".location_td").find("select").chosen("destroy").removeAttr("disabled").chosen({width:'100%'});
				$(".sub_location_td").removeClass("hidden");
				$(".sub_location_td").find("select").chosen("destroy").removeAttr("disabled").chosen({width:'100%'});
				$(".location_div").find("select").chosen("destroy").attr("disabled","disabled");
				$(".location_div").addClass("hidden");
				$("input[value=single]").removeAttr("checked");
				$(this).attr('checked',true);
			}
		});

		function calculateCharges(){
			var loading_quantity,loading_rate,loading_amount,unloading_quantity,unloading_rate,unloading_amount,other_quantity,other_rate,other_amount=0;
			loading_quantity = parseFloat($('.chr_loading_quantity').val());
			loading_rate = parseFloat($('.chr_loading_rate').val());
			loading_amount = loading_quantity * loading_rate;
			$('.chr_loading_amount').val(loading_amount);

			unloading_quantity = parseFloat($('.chr_unloading_quantity').val());
			unloading_rate = parseFloat($('.chr_unloading_rate').val());
			unloading_amount = unloading_quantity * unloading_rate;
			$('.chr_unloading_amount').val(unloading_amount);

			other_quantity = parseFloat($('.chr_other_quantity').val());
			other_rate = parseFloat($('.chr_other_rate').val());
			other_amount = other_quantity * other_rate;
			$('.chr_other_amount').val(other_amount);
			grand();
			calculateTotalEff();
		}

		function chargesRow(val_this){
			var charge=$(val_this).val();
			var loading_tds='<td></td><td><select class="form-control charge-select" name="loading_charg" onchange="chargesRow(this)"><option> Please select Mode</option><option value="loading_charg" selected> Loading Charges</option><option value="unloading_charg"> Unloading Charges</option><option value="other_charg"> Other Charges</option></select></td><td class="after_appened"><div class="chosen append"><div class="form-group"><select class=" loading_ledger chosen-select form-control" name="loading_ledger" required="true"><option value="0" selected=""> Loading Ledger </option><?php $db->select("select * from ledger where company_id=".getCompanyId()." and loading='yes' order by id desc");$loadings=$db->fetch_all(); foreach ($loadings as $loading){?><option value="<?php echo $loading['id']; ?>"><?php echo $loading['name'] ?></option><?php } ?></select></div></div></td><td class="after_appened"><input type="number" name="loading_quantity" class="form-control chr_loading_quantity tot_quantity" readonly placeholder="Quantity" value="" onkeyup="calculateCharges()"></td><td class="after_appened"><div class=""><input type="number" name="loading_rate" class="form-control chr_loading_rate" placeholder="Rate" onkeyup="calculateCharges()"></div></td><td class="after_appened"><input type="number" placeholder="Loading Charges" name="loading_amount" class="form-control chr_loading_amount" id="loading_charges" onkeyup="grand()"></td><td class="after_appened"><a class="btn btn-primary" onclick="addCharge(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>&nbsp;<a class="btn btn-danger" class="remove_row" onclick="removeCharge(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i></a></td>';

			var unloading_tds='<td></td><td><select class="form-control charge-select" name="unloading_charg" onchange="chargesRow(this)"><option> Please select Mode</option><option value="loading_charg"> Loading Charges</option><option value="unloading_charg" selected> Unloading Charges</option><option value="other_charg"> Other Charges</option></select></td><td class="after_appened"><div class="chosen append"><div class="form-group"><select class="chosen-select form-control unloading_ledger" name="unloading_ledger"><option value="0" selected=""> Unloading Ledger </option><?php $db->select("select * from ledger where company_id=".getCompanyId()." and unloading='yes' order by id desc");$unloadings=$db->fetch_all(); foreach ($unloadings as $unloading){?><option value="<?php echo $unloading['id']; ?>"><?php echo $unloading['name'] ?></option><?php } ?></select></select></div></div></td><td class="after_appened"><input type="number" name="unloading_quantity" class="form-control chr_unloading_quantity tot_quantity" placeholder="Quantity" readonly value="" onkeyup="calculateCharges()"></td><td class="after_appened"><div class=""><input name="unloading_rate" type="number" class="form-control chr_unloading_rate" placeholder="Rate" onkeyup="calculateCharges()"></div></td><td class="after_appened"><input type="number" name="unloading_amount" value="" placeholder="Unloading Charges" class="form-control chr_unloading_amount" id="unloading_charges" onkeyup="grand()"></td><td class="after_appened"><a class="btn btn-primary" onclick="addCharge(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>&nbsp;<a class="btn btn-danger" class="remove_row" onclick="removeCharge(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i></a></td>';

			var others_tds='<td></td><td><select class="form-control charge-select" name="other_charg" onchange="chargesRow(this)"><option> Please select Mode</option><option value="loading_charg"> Loading Charges</option><option value="unloading_charg"> Unloading Charges</option><option value="other_charg" selected> Other Charges</option></select></td><td class="after_appened"><div class="chosen append"><div class="form-group"><select class="chosen-select form-control other_ledger" name="other_ledger"><option value="0" selected=""> Other Ledger </option><?php $db->select("select * from ledger where company_id=".getCompanyId()." order by id desc");$others=$db->fetch_all(); foreach ($others as $other){?><option value="<?php echo $other['id']; ?>"><?php echo $other['name'] ?></option><?php } ?></select></div></div></td><td class="after_appened"><input type="number" name="other_quantity" class="form-control chr_other_quantity tot_quantity" readonly placeholder="Quantity" onkeyup="calculateCharges()"></td><td class="after_appened"><div class=""><input type="number" name="other_rate" class="form-control chr_other_rate" placeholder="Rate" onkeyup="calculateCharges()"></div></td><td class="after_appened"><input type="number" placeholder="Other Charges" class="form-control chr_other_amount" name="other_amount" id="other_charges" onkeyup="grand()"></td><td class="after_appened"><a class="btn btn-primary" onclick="addCharge(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a> &nbsp;<a class="btn btn-danger" class="remove_row" onclick="removeCharge(this)"><i class="fa fa-minus-circle" aria-hidden="true"></i></a></td>';

			if (charge == "loading_charg"){
				$(val_this).parents('.charges_row').html(loading_tds);
			}

			if (charge == "unloading_charg"){
				$(val_this).parents('.charges_row').html(unloading_tds);
			}

			if (charge == "other_charg"){
				$(val_this).parents('.charges_row').html(others_tds);
			}
			$('.check_quantity').trigger("onkeyup");
			$('.chosen-select').chosen({width:'100%'});
		}

		function addCharge(val_this){
			var charge_append='<tr class="tr-charge charges_row"><td></td><td><select class="form-control charge-select" onchange="chargesRow(this)"><option> Please select Mode</option><option value="loading_charg"> Loading Charges</option><option value="unloading_charg"> Unloading Charges</option><option value="other_charg"> Other Charges</option></select></td><td colspan="5"></td></tr>';
			$(val_this).parents('.charges_row').after($(charge_append).clone());
			$('.charges_row:last').find('.after_appened').remove();
		}

		function removeCharge(val_this) {
			$(val_this).parent().parent('tr').remove();
		}

		$(document).ready(function(){
			$(`.chosen-supplier`).prop('disabled', true).trigger("chosen:updated");
			$("#discount_checkbox").prop("disabled", true);
			$('.chosen_party').chosen({width:"100%"});
			$('.chosen_brand').chosen({width:"100%"});
			$('.chosen_quality').chosen({width:"100%"});
			$('.chosen_color').chosen({width:"100%"});
			$('.chosen-discount').chosen({width:"100%"});
			// $('.discount_with_vehicle').hide();
			var single_party_mode='<th style="width:5%">Sr #</th><th style="width:15%">Item Group</th><th style="width:15%">Item Name</th><th style="width:15%">Quantity</th><th style="width:20%">Rate</th><th style="width:15%">Amount</th><th style="width:15%">Action</th>';
			$('.change_head').html(single_party_mode);
			$('#discount_checkbox').click(function(){
				if($(this).prop("checked") == false){
					$('.dis_rate').addClass('hidden');
					$("#discount").val(0);
					$("#discount").trigger('onkeyup');
					grand();
				}
				else if($(this).prop("checked") == true){
					$('.dis_rate').removeClass('hidden');
					$("#discount").trigger('onkeyup');
					$("#discount").val(0);
					grand();
				}
				calculateTotalEff();
			});
		});

		function showlocationModal(){
			$("#add_item_Group").modal('show');
		}

		$("#form1").validate({
			rules: {
				name:"required"
			},
			messages:{
				name:"Please Enter Unit Location"
			},
			highlight: function(element, errorClass) {
				$(element).addClass('errorInput');
				$(element).parent().next().find("." + errorClass).removeClass("checked");
			},unhighlight: function(element) {
				$(element).removeClass('errorInput');
			}
		});

		var form1 =$("#form1");
		function addLocation(){
			if(form1.valid()){
				$(".ledger-loading").show();
				$(".ledger-button").hide();
				$.ajax({
					url: "<?php echo ADMIN_URL; ?>ajax/location.php",
					type: "POST",
					data: {'form-data':form1.serialize()}
				}).done(function(msg) {
					$.ajax({
						url: "<?php echo ADMIN_URL; ?>ajax/location.php",
						type: "POST",
						data: {'command':'add_location'}
					}).done(function(result){
						res = $.parseJSON(result);
						$(".location_select").html('');
						$(".location_select").html( res.html );
						$('.location_select').trigger("chosen:updated");
					 	$("#add_item_Group").modal('hide');
					 	$(".ledger-loading").hide();
					 	$(".ledger-button").show();
					}).fail(function(jqXHR, textStatus) {
					  alert( "Request failed: " + textStatus );
					});
				}).fail(function(jqXHR, textStatus) {
				  alert( "Request failed: " + textStatus );
				});
			}
		}

		$(document).ready(function(){
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
				},unhighlight: function(element) {
					$(element).removeClass('errorInput');
				},
				submitHandler: function(form) {
					if (check_Entries()!==false){
						$(".chosen-transporter").prop("disabled",false);
						$(".carriage").prop("disabled",false);
						$(".material").prop("disabled",false);
						form.submit();
					}
				}
			})
		});

		function check_Entries(){
			result= true;
			var project = $(".project_id option:selected").val();
			if (project=="" || project=='0'){
				alert("Please Select Project");
					return result = false;
			}

			var location=$(".location_id option:selected").val();
			if (location=="" || location=='0'){
				alert("Please Select Location");
					return result=false;
			}
			
			var Vendor=$('.inv_transporter_id option:selected').val();
			if (Vendor=="" || Vendor=='0'){
				alert("Please Select Vendor");
					return result=false;
			}

			var loading_led=$('.loading_ledger option:selected').val();
			if (loading_led=="" || loading_led=='0'){
				alert("Please Select Loading Ledger");
					return result=false;
			}
			var unloading_led=$('.unloading_ledger option:selected').val();
			if (unloading_led=="" || unloading_led=='0'){
				alert("Please Select Unloading Ledger");
					return result=false;
			}
			var other_led=$('.other_ledger option:selected').val();
			if (other_led=="" || other_led=='0'){
				alert("Please Select Other Ledger");
				return result=false;
			}

			$(".check_quantity").each(function( i ) {
				quantity=parseFloat($(this).val());
				if(quantity==0 || isNaN(quantity)){
					alert("Please Enter Quantity");
					return result=false;
				}
			});

			$(".check_rate").each(function( i ) {
				rate=parseFloat($(this).val());
				if(rate==0 || isNaN(rate)){
					alert("Please Enter Rate");
					return result=false;
				}
			});

			$(".check_amount").each(function( i ) {
				amount=parseFloat($(this).val());
				if(amount==0 || isNaN(amount)){
					alert("Please Check Amount");
					return result=false;
				}
			});

			$(".check_item").each(function( i ) {
				var selectItem = parseInt($(this).val());
				if (selectItem=="" || selectItem==0){
					alert("Please Select Item ");
					return result=false;
				}
			});
			return result;
		}

		function showItemModal(){
			$("#add_item").modal('show');
		}

		function addVendor(){
			$("#addVendor").modal('show');
		}

		// function chosenValue(val_this){
		// 	var project_id=$('.location_div .sub_location').val();
		// 	var location_id=$('.location_div .location_id').val();
		// 	console.log(project_id + "yes");
		// 	console.log(location_id +"no");
		// 	var data_id = "";
		// 	var data_id = $(val_this).val();
		// 	if(project_id=="0" || project_id== null){
		// 		alert("Kindly select Project.THANKS");
				
		// 	}else if(location_id=="0" || location_id == null){
		// 		alert("Kindly Select Locatoin.THANKS");
		// 	} else {
		// 		$.ajax({
		// 		url: 'ajax/data.php',
		// 		method: 'POST',
		// 		data: {data_id}
		// 		}).done(function(units){
		// 			checkContract(val_this);
		// 			// if ($('#multiple_veh').is(":checked")){
		// 			// 	showRateModal(val_this);
		// 			// } else if ($('#single_veh').is(":checked")==true){
		// 				showBrandModal(val_this);
		// 				var item_id=$(val_this).val();
		// 				$.ajax({
		// 					url: "ajax/get-item-brand.php",
		// 					type: "POST",
		// 					data: {'item':'Add_item',item_id}
		// 				}).done(function(result) {
		// 					// for brand
		// 					res = $.parseJSON(result);
		// 					if(res.item_brand != 'no'){
		// 						$(val_this).parent().parent().parent().parent().find('.brand_td').show();	
		// 						$(val_this).parent().parent().parent().parent().find('.selectbrand').chosen({width:'100%'});
		// 						$(val_this).parent().parent().parent().parent().find('.selectbrand').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectbrand').html(res.item_brand);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_brand').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectbrand').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.brand_td').hide();	
		// 					}


		// 					// for item quality
		// 					if(res.item_quality != 'no'){
		// 						$(val_this).parent().parent().parent().parent().find('.quality_td').show();	
		// 						$(val_this).parent().parent().parent().parent().find('.selectquality').chosen({width:'100%'});
		// 						$(val_this).parent().parent().parent().parent().find('.selectquality').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectquality').html(res.item_quality);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_quality').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectquality').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.quality_td').hide();	
		// 					}

		// 					// for item color		
		// 					if(res.item_color != 'no'){		
		// 						$(val_this).parent().parent().parent().parent().find('.color_td').show();	
		// 						$(val_this).parent().parent().parent().parent().find('.selectcolor').chosen({width:'100%'});		
		// 						$(val_this).parent().parent().parent().parent().find('.selectcolor').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectcolor').html(res.item_color);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_color').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectcolor').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.color_td').hide();	
		// 					}

		// 					// for item article	
		// 					if(res.article != 'no'){		
		// 						$(val_this).parent().parent().parent().parent().find('.article_td').show();	
		// 						$(val_this).parent().parent().parent().parent().find('.selectarticle').chosen({width:'100%'});
		// 						$(val_this).parent().parent().parent().parent().find('.selectarticle').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectarticle').html(res.article);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_article').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectarticle').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.article_td').hide();	
		// 					}


		// 					// for item warehouse	
		// 					if(res.warehouse != 'no'){		
		// 						$(val_this).parent().parent().parent().parent().find('.warehouse_td').show();					
		// 						$(val_this).parent().parent().parent().parent().find('.selectwarehouse').chosen({width:'100%'});
		// 						$(val_this).parent().parent().parent().parent().find('.selectwarehouse').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectwarehouse').html(res.warehouse);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_warehouse').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectwarehouse').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.warehouse_td').hide();
		// 					}

		// 					// for item section			
		// 					if(res.section != 'no'){ 
		// 						$(val_this).parent().parent().parent().parent().find('.section_td').show();		
		// 						$(val_this).parent().parent().parent().parent().find('.selectsection').chosen({width:'100%'});					
		// 						$(val_this).parent().parent().parent().parent().find('.selectsection').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectsection').html(res.section);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_section').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectsection').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.section_td').hide();
		// 					}

		// 					// for item finishing Type		
		// 					if(res.finishing_type != 'no'){	
		// 						$(val_this).parent().parent().parent().parent().find('.finishingType_td').show();		
		// 						$(val_this).parent().parent().parent().parent().find('.selectfinishingType').chosen({width:'100%'});
		// 						$(val_this).parent().parent().parent().parent().find('.selectfinishingType').html("");
		// 						$(val_this).parent().parent().parent().parent().find('.selectfinishingType').html(res.finishing_type);
		// 						$(val_this).parent().parent().parent().parent().find('.chosen_finishingType').trigger("chosen:updated");
		// 					}else{
		// 						$(val_this).parent().parent().parent().parent().find('.selectfinishingType').chosen("destroy");
		// 						$(val_this).parent().parent().parent().parent().find('.finishingType_td').hide();
		// 					}
		// 				}).fail(function(jqXHR, textStatus) {
		// 					alert( "Request  are failed:" + textStatus );
		// 				});
		// 			// }
		// 			units = JSON.parse(units);
		// 			units.forEach(function(unit){
		// 				$(val_this).parent().parent().parent().parent().find('.qty_symbol').html("");
		// 				$(val_this).parent().parent().parent().parent().find('.qty_symbol').html(unit.symbol);
		// 				$(val_this).parent().parent().parent().parent().find('.qty_symbol_per').html(unit.symbol);
		// 			});
		// 		});
		// 	}
		// }
		function chosenValue(val_this){
			var project_id=$('.location_div .sub_location').val();
			var location_id=$('.location_div .location_id').val();
			console.log(project_id + "yes");
			console.log(location_id +"no");
			var data_id = "";
			var data_id = $(val_this).val();
			if(project_id=="0" || project_id== null){
				alert("Kindly select Sub location.THANKS");
				
			}else if(location_id=="0" || location_id == null){
				alert("Kindly Select Locatoin.THANKS");
			} else {
				$.ajax({
				url: '<?php echo ADMIN_URL; ?>ajax/data.php',
				method: 'POST',
				data: {data_id},
				}).done(function(units){
					checkContract(val_this);
					checkSubItems(val_this);
					var item_id=$(val_this).val();
					$.ajax({
						url: "<?php echo ADMIN_URL; ?>ajax/get-item-brand.php",
						type: "POST",
						data: {'item':'Add_item',item_id,project_id},
					}).done(function(result) {
							// for brand
						res = $.parseJSON(result);

						if(res.sub_item == 'yes'){
							// for item quality
							if(res.item_quality != 'no'){
								$(val_this).parent().parent().parent().parent().find('.quality_td').show();
								$(val_this).parent().parent().parent().parent().find('.selectquality').html("");
								$(val_this).parent().parent().parent().parent().find('.selectquality').html(res.item_quality);
								$(val_this).parent().parent().parent().parent().find('.chosen_quality').trigger("chosen:updated");
							}else{
								$(val_this).parent().parent().parent().parent().find('.quality_td').hide();	
							}

							// for item color		
							if(res.item_color != 'no'){	
								$(val_this).parent().parent().parent().parent().find('.color_td').show();	
								$(val_this).parent().parent().parent().parent().find('.selectcolor').html("");
								$(val_this).parent().parent().parent().parent().find('.selectcolor').html(res.item_color);
								$(val_this).parent().parent().parent().parent().find('.chosen_color').trigger("chosen:updated");
							}else{
								$(val_this).parent().parent().parent().parent().find('.color_td').hide();	
							}

							// for item warehouse	
							if(res.warehouse != 'no'){	
								$(val_this).parent().parent().parent().parent().find('.warehouse_td').show();
								$(val_this).parent().parent().parent().parent().find('.selectwarehouse').html("");
								$(val_this).parent().parent().parent().parent().find('.selectwarehouse').html(res.warehouse);
								$(val_this).parent().parent().parent().parent().find('.chosen_warehouse').trigger("chosen:updated");
							}else{
								$(val_this).parent().parent().parent().parent().find('.warehouse_td').hide();
							}

							if(res.item_unit!=''){
								$(val_this).parent().parent().parent().find(".qty_symbol").text(res.item_unit);
							}

							// forSubItemCode	
							if(res.SubItemCode != ''){	
								console.log(res.SubItemCode);
								$(val_this).parent().parent().parent().parent().find('.SubItemCode').html("");
								$(val_this).parent().parent().parent().parent().find('.SubItemCode').html(res.SubItemCode);
								$(val_this).parent().parent().parent().parent().find('.SubItemCode').trigger("chosen:updated");
							}

							// for item section			
							if(res.section != 'no'){ 
								$(val_this).parent().parent().parent().parent().find('.section_td').show();
								$(val_this).parent().parent().parent().parent().find('.selectsection').html("");
								$(val_this).parent().parent().parent().parent().find('.selectsection').html(res.section);
								$(val_this).parent().parent().parent().parent().find('.chosen_section').trigger("chosen:updated");
							}else{
								$(val_this).parent().parent().parent().parent().find('.section_td').hide();
							}
						}
					}).fail(function(jqXHR, textStatus) {
						alert( "Request  are failed:" + textStatus );
					});
					units = JSON.parse(units);
					units.item_unit.forEach(function(unit){
						$(val_this).parent().parent().parent().parent().find('.qty_symbol').html("");
						$(val_this).parent().parent().parent().parent().find('.main_item_unit').val(unit.symbol);
						$(val_this).parent().parent().parent().parent().find('.qty_symbol').html(unit.symbol);
						$(val_this).parent().parent().parent().parent().find('.qty_symbol_per').html(unit.symbol);
					});
					units.alternative_unit.al_unit.forEach(function(al_unit) {
						$(val_this).parent().parent().parent().parent().find('.alternative_unit').val("");
						$(val_this).parent().parent().parent().parent().find('.alternative_unit').val(al_unit.name);
					});
					$(val_this).parent().parent().parent().parent().find(".conversionToAlternativeUnit").val("");
					$(val_this).parent().parent().parent().parent().find(".conversionToAlternativeUnit").val(units.alternative_unit.conversion);
				});
			}
		}
		function checkSubItems(val_this){
			var item_id = $(val_this).val();
			$.ajax({
				url : 'add-sale-invoice.php',
				Type : 'post',
				data : {item_id,command:'checkSubItems'},
				success : function(result){
					result = result.trim();
					if(result == 'yes'){
						ShowSubItemModal(val_this);
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
		var item_id = $(val_this).val();
		sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
		$(val_this).parent().parent().parent().parent().find(".sub_item").val("yes");
		if($('.SubItemModal'+sr_no).html()!=undefined){
			modal_no = 'SubItemModal'+sr_no;
			$('.SubItemModal'+sr_no,).modal('show');
		} else {
			modal_no = 'SubItemModal'+sr_no;
			modal = $(".SubItemModal:first").clone();
			var getSaleItemQuantity = 'getSaleItemQuantity('+location_id+','+sublocation_id+','+item_id+',"'+modal_no+'",this)';
			$(val_this).parent().parent().parent().parent().find('.brand_qual_modal .modal').remove();
			$(val_this).parent().parent().parent().parent().find('.brand_qual_modal').append(modal);
			$(val_this).parent().parent().parent().parent().find('.SubItemModal').addClass('SubItemModal'+sr_no);
			$('.SubItemModal'+sr_no).removeClass('SubItemModal');
			$('.SubItemModal'+sr_no).find(".head").val(location_id);
			$('.SubItemModal'+sr_no).find(".sub_head").val(sublocation_id);
			$('.SubItemModal'+sr_no).find('.inv_section').attr('onchange',getSaleItemQuantity);
			ChangeSubItemModalNAmes(modal_no,sr_no);
			$('.SubItemModal'+sr_no).find('.inv_quantity').attr('onkeyup','QuantityAdd(this,"'+modal_no+'")');
			$('.SubItemModal'+sr_no+" .brand-quality-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveSubItemSpecifications("'+modal_no+'")> Save </button>');
			specificationsBtn();
			$('.SubItemModal'+sr_no).modal('show');
		}
		$('.chosen_brand').chosen({width:'100%'});
		$('.chosen_quality').chosen({width:'100%'});
		$('.chosen_color').chosen({width:'100%'});
		$('.chosen-code').chosen({width:'100%'});
		$('.chosen_article').chosen({width:'100%'});
		$('.chosen_warehouse').chosen({width:'100%'});
		$('.chosen_finishingType').chosen({width:'100%'});
		$('.chosen_section').chosen({width:'100%'});
	}		


	function saveSubItemSpecifications(modal_no){
		var SubItemsQuantity = $("."+modal_no).find(".total_auantity").val();
		$("."+modal_no).parent().parent().find(".quantity").val(SubItemsQuantity).trigger('keyup');
		$("."+modal_no).modal("hide");
	}
	function specificationsBtn(){
		$(".pq_table tbody tr").each(function(){
			var sr = $(this).find(".sr_no").html();
			if($(this).find(".SubItemModal"+sr).html() != undefined){
				modal_no = 'SubItemModal'+sr;
				$(this).find(".brand_md_button").html('<a data-toggle="modal" data-target=".'+modal_no+'"class="md-btn specification-toggle" data-keyboard="false" data-backdrop="static"> SubItem Specifications </a>');
			}
		});
	}
	function ChangeSubItemModalNAmes(modal_no,sr){
		$("."+modal_no+" .SubItemCode").attr("name","Sub_item_code"+sr+'[]');
		$("."+modal_no+" .SubITemID").attr("name","Sub_item_id"+sr+'[]');
		$("."+modal_no+" .chosen_article").attr("name","Sub_item_article"+sr+'[]');
		$("."+modal_no+" .inv_warehouse").attr("name","Sub_item_warehouse"+sr+'[]');
		$("."+modal_no+" .inv_section").attr("name","Sub_item_section"+sr+'[]');
		$("."+modal_no+" .inv_color").attr("name","Sub_item_color"+sr+'[]');
		$("."+modal_no+" .inv_quality").attr("name","Sub_item_quality"+sr+'[]');
		$("."+modal_no+" .inv_quantity").attr("name","Sub_item_quantity"+sr+'[]');
	}

		function checkContract(val_this){			
			var item_id = $(val_this).val();
			var project_id=$('.sub_location').val();
			console.log(project_id);
			var location_id=$('.location_id').val();
			$.ajax({
			url: '<?php echo ADMIN_URL;?>ajax/check-sale-contract.php',
			method: 'POST',
			data: {item_id,project_id,location_id}
			}).done(function(result){
				res = $.parseJSON(result);
				$(val_this).parent().parent().parent().parent().find('.rate').val(res.actual_rate);
				if(res==false) {
					alert("Please Add the Contract againt this item. 'THANKS'");
				} else if(res.error){
					alert("Your Contract Date is Over");
				}
			}).fail(function(jqXHR, textStatus) {
				alert( "Request  are failed:" + textStatus);
			});
		}

		function showRateModal(val_this){
			sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
			$('.chosen-supplier').chosen('destroy');
			if($('.rate_modal'+sr_no).html()!=undefined){
				modal_no = 'rate_modal'+sr_no;
				$('.rate_modal'+sr_no,).modal('show');
			}else{
				modal_no = 'rate_modal'+sr_no;
				$('.specification-toggle').remove();
				modal = $(".rate_modal:first").clone();
				$(val_this).parent().parent().parent().parent().find('.mat_modal').append(modal);
				$(val_this).parent().parent().parent().parent().find('.rate_modal').addClass('rate_modal'+sr_no);
				$('.rate_modal'+sr_no).removeClass('rate_modal');
				changeModalName(modal_no,sr_no);
				$('.rate_modal'+sr_no+" .rate-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveRate("'+modal_no+'","'+sr_no+'")> Save invoice </button>');
				$('.rate_modal'+sr_no,).modal('show');
				$(val_this).parent().parent().parent().parent().find(".invoice_md_button").append('<a data-toggle="modal" data-target=".'+modal_no+'"class=" md-btn" data-keyboard="false" data-backdrop="static">receipt</a>');
			}
			$('.chosen-supplier').chosen({width:'100%'});
		}

		function getDelivery(val_this){
			if($(val_this).is(":checked")==true){
				$(val_this).next('input.deliveryValue').val("exfactory");
				$(val_this).parents(".show_mt_modal").show();
				$(val_this).parent().parent().parent().find(`.supplier-select`).prop('disabled', false).trigger("chosen:updated");
				$(val_this).parent().parent().parent().find('.mat_modal').append('<input type="hidden" name="material[]" class="material_status" value="yes">');
			} else if($(val_this).is(":checked")==false){
				$(val_this).next('input.deliveryValue').val("exdelivery");
				$(val_this).parents(".show_mt_modal").hide();
				$(val_this).parent().parent().parent().find(`.supplier-select`).prop('disabled', true).trigger("chosen:updated");
				$(val_this).parent().parent().parent().find('.material_status').remove('.material_status');
			}
		}

		function showCarrMat(val_this){
			material_status= $(val_this).parent().parent().parent().parent().parent().find(".delivery_term").val();
			sr_no = $(val_this).parent().parent().parent().parent().parent().find(".sr_no").html();
			if($('.carr_mat_modal'+sr_no).html()!=undefined){
				$('.costCenterModal'+sr_no+" .carr-mat-button").html('');
				modal_no = 'carr_mat_modal'+sr_no;
				$('.carr_mat_modal'+sr_no,).modal('show');
			}else{
				if(material_status=='exfactory'){
					modal_no = 'carr_mat_modal'+sr_no;
					modal = $(".carr_mat_modal:first").clone();
					changeCarrMat(modal_no,sr_no);
					$(val_this).parent().parent().parent().parent().parent().find('.mat_modal').append(modal);
					$(val_this).parent().parent().parent().parent().find('.carr_mat_modal').addClass('carr_mat_modal'+sr_no);
					$('.carr_mat_modal'+sr_no).removeClass('carr_mat_modal');
					$('.carr_mat_modal'+sr_no+" .carr-mat-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveCarMAt("'+modal_no+'")>Save</button>');
					$('.carr_mat_modal'+sr_no,).modal('show');
				}else{
					$(".carr_mat_modal").modal('show');
				}
			}
		}

		function calculate_carr_rate(val_this){
			carriage=parseInt($(val_this).parent().parent().parent().find('.carriage').val());
			material=parseInt($(val_this).parent().parent().parent().find('.material').val());
			total_rate=carriage+material;
			$(val_this).parent().parent().parent().find('.rate').val(total_rate);
			$(val_this).parent().parent().parent().find('.rate').trigger("keyup");
		}

		function changeModalName(modal,sr_no){
			$('.'+modal_no+" .inv_delivery_no").attr('name',"inv_delivery_no"+sr_no+"[]");
			$('.'+modal_no+" .inv_supplier_no").attr('name',"inv_supplier_no"+sr_no+"[]");
			$('.'+modal_no+" .inv_veh_no").attr('name',"inv_veh_no"+sr_no+"[]");
			$('.'+modal_no+" .inv_bilty_no").attr('name',"inv_bilty_no"+sr_no+"[]");
			$('.'+modal_no+" .inv_receipt_no").attr('name',"inv_receipt_no"+sr_no+"[]");
			$('.'+modal_no+" .inv_quantity").attr('name',"inv_quantity"+sr_no+"[]");
		}

		function changeBrandName(modal,sr_no){
			$('.'+modal_no+" .inv_brand").attr('name',"inv_brand"+sr_no+"[]");
			$('.'+modal_no+" .inv_quality").attr('name',"inv_quality"+sr_no+"[]");
			$('.'+modal_no+" .inv_color").attr('name',"inv_color"+sr_no+"[]");
			$('.'+modal_no+" .inv_section").attr('name',"inv_section"+sr_no+"[]");
			$('.'+modal_no+" .inv_article").attr('name',"inv_article"+sr_no+"[]");
			$('.'+modal_no+" .inv_finishingType").attr('name',"inv_finishingType"+sr_no+"[]");
			$('.'+modal_no+" .inv_warehouse").attr('name',"inv_warehouse"+sr_no+"[]");
			$('.'+modal_no+" .inv_item_rate").attr('name',"inv_rate"+sr_no+"[]");
			$('.'+modal_no+" .inv_quantity").attr('name',"inv_quantity"+sr_no+"[]");
		}

		function changeCarrMat(modal,sr_no){
			$('.'+modal_no+" .inv_material").attr('name',"inv_material"+sr_no+"[]");
			$('.'+modal_no+" .inv_carriage").attr('name',"inv_carriage"+sr_no+"[]");
		}

		function invoice_total_quantity(val_this){
			var total_quantity=0;
			$(val_this).parent().parent().parent().find('.invoice_row .md_quantity').each(function(val){
				total_quantity += parseFloat($(this).val());
				$(val_this).parent().parent().parent().parent().parent().find('.total_quantity').html(total_quantity);
				$(val_this).parent().parent().parent().parent().parent().find('.inp_total_quantity').val(total_quantity);
			});
		}


		function getSubwareHouseITemQuantity(val_this){
			var Sub_item_id = $(val_this).parent().parent().parent().find(".SubITemID").val();
			var Sub_item_article = $(val_this).parent().parent().parent().find(".SubItemArticle").val();
			var Sub_item_quality = $(val_this).parent().parent().parent().find(".md_quality").val();
			var head = $(".head").val();
			var sub_head = $(".sub_head").val();
			$.ajax({
				url : 'add-sale-invoice.php',
				type : 'post',
				data : {head,sub_head,Sub_item_id,Sub_item_article,Sub_item_quality,command:'getQuantity'},
				success : function(result){
					$(val_this).parent().parent().parent().find(".available_quantity").val('');
					$(val_this).parent().parent().parent().find(".available_quantity").val(parseFloat(result));
				}
			});
		}

		function add_invoice_row(val_this){
			$('.chosen_party').chosen('destroy');
			invoice_clone_row=$(val_this).parent().parent().parent().find('.invoice_row:last');
			$(val_this).parent().parent().parent().parent().find(".invoice_body").append($(invoice_clone_row).clone());
			$('.chosen-transporter').chosen('destroy');			
			if($(invoice_clone_row).find('.showCharges').is(":checked")==true){
				$(val_this).parent().parent().parent().find('.invoice_row:last .showCharges').prop("disabled",true);
			}
			trans_val = $(val_this).parent().parent().parent().find('.invoice_row:last .chosen-transporter').find("option:selected").val();
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_quantity').val(0);
			$(val_this).parent().parent().parent().find('.invoice_row:last .chosen-transporter').val(trans_val).prop('disabled', true).trigger("chosen:updated");
			$('.chosen_party').chosen({width:'100%'});
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_veh').val('');
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_bilty').val('');
			$(val_this).parent().parent().parent().find('.invoice_row:last .md_receipt').val('');
		}

		function remove_invoice_row(val_this){
			val=$(val_this).parent().parent().parent().parent().find('.invoice_row .md_quantity');
			$(val_this).parent().parent('tr.invoice_row').remove();
			invoice_total_quantity(val);
		}

		function getSubledger() {
			var name = $('#sub_ledger_model .sub_ledger_name').val();
			var id_card = $('#sub_ledger_model .sub_ledger_id_card').val();
			var phone = $('#sub_ledger_model .sub_ledger_mobile').val();
			$.ajax({
				url : 'add-sale-invoice-jahangir.php',
				type : 'post',
				data : {name,id_card,phone,command : 'getSubledger'},
				success : function(subledger){
					$("#sub_ledger_model .table tbody").html("")
					$("#sub_ledger_model .table tbody").html(subledger)
				}
			})
		}

		function saveRate(modal_no,val_this){
			$('.'+modal_no).modal('hide');
			total_quantity_value=parseInt($('.'+modal_no).find('.inp_total_quantity').val());
			var deliveryStatus=$('.'+modal_no).find('.delivery_term').val();
			if (deliveryStatus=="exfactory"){
				$('.'+modal_no).parent().find('.show_mt_modal').show();
			}
			$('.'+modal_no).parent().parent().find('.quantity').val(total_quantity_value);
			$('.'+modal_no).parent().parent().find('.quantity').val(total_quantity_value).trigger("keyup");
		}

		function showBrandModal(val_this){
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
			if($('.brand_quality_modal'+sr_no).html()!=undefined){
				modal_no = 'brand_quality_modal'+sr_no;
				$('.brand_quality_modal'+sr_no,).modal('show');
			} else {
				modal_no = 'brand_quality_modal'+sr_no;
				modal = $(".brand_quality_modal:first").clone();
				var getSaleItemQuantity = 'getSaleItemQuantity('+location_id+','+sublocation_id+','+item_id+',"'+modal_no+'",this)';
				$(val_this).parent().parent().parent().parent().find('.brand_qual_modal').append(modal);
				$(val_this).parent().parent().parent().parent().find('.brand_quality_modal').addClass('brand_quality_modal'+sr_no);
				$('.brand_quality_modal'+sr_no).removeClass('brand_quality_modal');
				changeBrandName(modal_no,sr_no);
				$('.brand_quality_modal'+sr_no).find(".purchase-img").removeAttr("name");
				$('.brand_quality_modal'+sr_no).find(".purchase-img").attr("name","purchaseImg"+sr_no+'[]');
				$('.brand_quality_modal'+sr_no).find('.head_inp').val(location_id);
				$('.brand_quality_modal'+sr_no).find('.subhead_inp').val(sublocation_id);
				$('.brand_quality_modal'+sr_no).find('.inv_finishingType').removeAttr('onchange');
				$('.brand_quality_modal'+sr_no).find('.inv_finishingType').attr('onchange',getSaleItemQuantity);	
				$('.brand_quality_modal'+sr_no).find('.inv_finishingType').removeAttr('onkeyup');
				$('.brand_quality_modal'+sr_no).find('.inv_quantity').attr('onkeyup','QuantityAdd(this,"'+modal_no+'")');
				$('.brand_quality_modal'+sr_no+" .brand-quality-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveBrandQuatity("'+modal_no+'")> Save </button>');
				$('.brand_quality_modal'+sr_no).modal('show');
				$(val_this).parent().parent().parent().parent().find(".brand_md_button").append('<a data-toggle="modal" data-target=".'+modal_no+'"class="md-btn specification-toggle" data-keyboard="false" data-backdrop="static"> Specification </a>');
			}
			$('.chosen_brand').chosen({width:'100%'});
			$('.chosen_quality').chosen({width:'100%'});
			$('.chosen_color').chosen({width:'100%'});
			$('.chosen_article').chosen({width:'100%'});
			$('.chosen_warehouse').chosen({width:'100%'});
			$('.chosen_finishingType').chosen({width:'100%'});
			$('.chosen_section').chosen({width:'100%'});
		}

		function saveBrandQuatity(modal_no){
			$('.'+modal_no).modal('hide');
		}

		function saveCarMAt(modal_no){
			$('.'+modal_no).modal('hide');
			carriage_value=parseFloat($('.'+modal_no).find('.md_carriage').val());
			material_value=parseFloat($('.'+modal_no).find('.md_material').val());
			rate_value=carriage_value + material_value;
			if (carriage_value!="" && material_value!=""){
				$('.'+modal_no).parent().find('.material_mod').val("yes");
				$('.'+modal_no).parent().find('.carriage_modal_val').val(carriage_value);
				$('.'+modal_no).parent().find('.material_modal_val').val(material_value);
			}
			$('.'+modal_no).parent().parent().find('.rate').val(rate_value);
			val=$('.'+modal_no).parent().parent().find('.rate').val();
			$('.'+modal_no).parent().parent().find('.rate').val(rate_value).trigger("keyup");
		}

		$(document).ready(function(){
			var date_input=$('.del_date');
			var container=$('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";
			date_input.datepicker({
				format: 'dd-mm-yyyy',
				container: container,
				todayHighlight: true,
				autoclose: true,
			});
		})

		$(document).ready(function(){
			var date_input=$('.rate-validity');
			var container=$('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";
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

		$(document).ready(function(){
			$('.chosen-select').chosen({width: "100%"});
			$('.chosen-transporter').chosen({width: "100%"});
			$('.chosen-supplier').chosen({width: "100%"});
		})

		function group_item(val_this){
			var group_id = "";
			group_id = $(val_this).val();
			if(group_id){
				$.ajax({
					type:'POST',
					url:'<?php echo ADMIN_URL; ?>ajax/item_name.php',
					dataType:"html",
					data:'group_id='+group_id,
					success:function(option){
						$(val_this).parent().parent().parent().find('.selecteditem').html("");
						$(val_this).parent().parent().parent().find('.selecteditem').append(option);
						$(val_this).parent().parent().parent().find('.selecteditem').trigger("chosen:updated");
					}
				}); 
			}
		};

		function projectlocation(val_this){
			var project_id = "";
			project_id = $(val_this).val();
			if(project_id){
				$.ajax({
					type:'POST',
					url: "<?php echo ADMIN_URL; ?>ajax/location.php",
					dataType:"html",
					data:'project_id='+project_id,
					success:function(option){
						$(val_this).parent().parent().find('.proj_location').html("");
						$(val_this).parent().parent().find('.proj_location').append(option);
						$(val_this).parent().parent().find('.proj_location').trigger("chosen:updated");
					}
				});
			}
		};

		function calculate(val){
			var discount=0;
			var net=0;
			var rate=0;
			if ($(val).parent().parent().parent().find('.discount_type').val() == 'Rs'){
				rate = parseFloat($(val).parent().parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().parent().find('.discount').val());
				disc = rate - discount;
				$(val).parent().parent().parent().parent().find('.net_rate_area').text(disc);
				parseFloat($(val).parent().parent().parent().parent().find('.net_rate').val(disc));
				qty = parseFloat($(val).parent().parent().parent().parent().find('.quantity').val());
				amount = disc * qty;
				$(val).parent().parent().parent().parent().find(".individusl_discount").text("");
				$(val).parent().parent().parent().parent().find('.amount').val(amount);
			}
			if ($(val).parent().parent().parent().find('.discount_type').val() == '%') {
				rate = parseFloat($(val).parent().parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().parent().find('.discount').val());
				amount = discount*rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				$(val).parent().parent().parent().parent().find('.net_rate_area').text(net_rate);
				parseFloat($(val).parent().parent().parent().parent().find('.net_rate').val(net_rate));
				qty = parseFloat($(val).parent().parent().parent().parent().find('.quantity').val());
				amount = net_rate * qty;
				individual = rate - net_rate;
				$(val).parent().parent().parent().find(".individusl_discount").text(individual);
				$(val).parent().parent().parent().parent().find('.amount').val(amount);
			}
			$('#discount').trigger('onkeyup');
			 total = 0;
			 var discount_amount=0;
			$('.amount').each(function(){
				total += parseFloat($(this).val());
				$('#total').val(total);
				$('.total').text(total);
				discount_amount=$('#discount').val();
				if (discount_amount==0 || isNaN(discount_amount)){
					$('#net').val(total);
					$('.net').text(total);
					grand();
				}
			})
			var tot_quantity=0;
			$('.quantity').each(function(){
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			})
		}

		function calculate_quantity(val_this){
			var alternate_unit = $(val_this).parent().parent().parent().find(".alternative_unit").val();
			var item_unit = $(val_this).parent().parent().parent().find(".main_item_unit").val();
			var conversion = $(val_this).parent().parent().parent().find(".conversionToAlternativeUnit").val();
		 	$(val_this).parent().parent().parent().find('.rate').trigger("keyup");
		 	if(alternate_unit !='' && conversion !=0){
				conversionToAlternateUnit = parseFloat($(val_this).val())/parseFloat(conversion);
				conversionToAlternateUnit = conversionToAlternateUnit.toFixed(2);
				$(val_this).parent().parent().find(".alterNateUnitSpan").text("( "+conversionToAlternateUnit+" "+alternate_unit+" )");
			}
		 	$('#discount').trigger('onkeyup');
		}

		function calculate_balance(val){
			var net=0;
			if ($(val).parent().parent().parent().find('.discount_type').val() == '%') {
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().parent().find('.discount').val());
				amount = discount*rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				individual = rate - net_rate;
				$(val).parent().parent().parent().find(".individusl_discount").text(individual);
				$(val).parent().parent().parent().find('.net_rate_area').text(net_rate);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(net_rate));
				qty = parseFloat($(val).parent().parent().parent().parent().find('.quantity').val());
				amount = net_rate * qty;
				$(val).parent().parent().parent().parent().find('.amount').val(amount);
			}else{
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().parent().find('.discount').val());
				disc = rate - discount;
				$(val).parent().parent().parent().find('.net_rate_area').text(disc);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(disc));
				qty = parseFloat($(val).parent().parent().parent().parent().find('.quantity').val());
				amount = disc * qty;
				$(val).parent().parent().parent().parent().find('.amount').val(amount);
				$(val).parent().parent().parent().find(".individusl_discount").text("");
			}
			$('#discount').trigger('onkeyup');
			total = 0;
			var discount_amount=0;
			$('.amount').each(function(){
				total += parseFloat($(this).val());
				$('#total').val(total);
				$('.total').text(total);
				discount_amount=$('#discount').val();
				if (discount_amount==0 || isNaN(discount_amount)){
					$('#net').val(total);
					$('.net').text(total);
					grand();
				}
			});
			var tot_quantity=0;
			$('.quantity').each(function(){
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			});
			calculateTotalEff();
		}

		function calculate_discount(val){
			var net=0;
			$('#discount').trigger('onkeyup');
			if($(val).parent().parent().parent().find('.discount_type').val() == '%') {
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().find('.discount').val());
				amount = discount*rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				individual = rate - net_rate;
				$(val).parent().parent().parent().find(".individusl_discount").text(individual);
				$(val).parent().parent().parent().find('.net_rate_area').text(net_rate);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(net_rate));
				qty = parseInt($(val).parent().parent().parent().find('.quantity').val());
				amount = net_rate * qty;
				$(val).parent().parent().parent().find('.amount').val(amount);
			}else{
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().find('.discount').val());
				disc = rate - discount;
				$(val).parent().parent().parent().find('.net_rate_area').text(disc);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(disc));
				qty = parseFloat($(val).parent().parent().parent().find('.quantity').val());
				amount = disc * qty;
				$(val).parent().parent().parent().find('.amount').val(amount);
				$(val).parent().parent().parent().find(".individusl_discount").text("");
			}
			total = 0;
			var discount_amount=0;
			$('.amount').each(function(){
				total += parseFloat($(this).val());
				$('#total').val(total);
				$('.total').text(total);
				discount_amount=$('#discount').val();
				if (discount_amount==0 || isNaN(discount_amount)){
					$('#net').val(total);
					$('.net').val(total);
					grand();
				}
			})
			var tot_quantity=0;
			$('.quantity').each(function(){
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			})
		}

		function netCharges(val){
			var net=0;
			total = parseFloat($("#total").val());
			discount = parseFloat($(val).val());
			if(net =='' || isNaN(net)){
				net == 0;
			};
			net = total - discount;
			$('#net').val(net);
			$('.net').text(net);
			grand();
		}

		function getSection(val_this){
			var item_id = $(val_this).closest('tr').find(".check_item ").val();
			$.ajax({
				url:'add-sale-invoice.php',
				type : 'POST',
				data : {item_id,warehouse_id:$(val_this).val(),command:'getSection'},
				success : function(data){
					if(data!=''){
						$(val_this).parent().parent().find(".inv_section").chosen("destroy");
						$(val_this).parent().parent().find(".inv_section").html("");
						$(val_this).parent().parent().find(".inv_section").html(data);
						$(val_this).parent().parent().find(".inv_section").chosen({width:'100%'});
					}
				}
			});
		}

		function grand(){
			var grd_total = 0;
			var net=0;
			var net = parseFloat($('#net').val());
			console.log(net);
			var l_charges = parseFloat($('#loading_charges').val());
			var u_charges = parseFloat($('#unloading_charges').val());
			var o_charges = parseFloat($('#other_charges').val());
			var f_charges = parseFloat($('#freight_charge').val());
			if(net =='' || isNaN(net)){
				net == 0;
				net = parseFloat($("#total").val());
			};

			if(l_charges =='' || isNaN(l_charges)){
				l_charges = 0;
			};

			if(u_charges =='' || isNaN(u_charges)){
				u_charges = 0;
			};
			if(o_charges =='' || isNaN(o_charges)){
				o_charges = 0;
			};
			if(f_charges =='' || isNaN(f_charges)){
				f_charges = 0;
			};
			grd_total = parseFloat(net + l_charges + u_charges + o_charges + f_charges);
			console.log(grd_total);

			$("#grand_total").val(grd_total);
			$(".grand_total").text(grd_total);
			calculateTotalEff();
		}

		function add_row(val_this){
			$(".chosen-select").chosen('destroy');
			$(".chosen_brand").chosen('destroy');
			$(".chosen_party").chosen('destroy');
			$(".chosen-project").chosen('destroy');
			$(".pq_body").append($('.pq_row:first').clone());
			$('.pq_row:last').find('.quantity').val('');
			SR_NO=$('.pq_row:last').find('.sr_no').text();
			$('.pq_row:last').find('.rate_modal'+SR_NO).remove();
			$('.pq_row:last').find('.carr_mat_modal'+SR_NO).remove();
			$('.pq_row:last').find('.brand_quality_modal'+SR_NO).remove();
			$('.pq_row:last').find('.SubItemModal'+SR_NO).remove();
			$('.pq_row:last').find('.chosen-select').val('');
			$('.pq_row:last').find('.show_mt_modal').hide();
			$('.pq_row:last').find('.eff_net_rate').val("0");
			$('.pq_row:last').find('.eff_net_rate_area').html("");
			$('.pq_row:last').find('.rate').val('');
			$('.pq_row:last').find('.discount').val('0');
			$('.pq_row:last').find('.net_rate').val('');
			$('.pq_row:last').find('.serial').val("");
			$('.pq_row:last').find('.material_status').remove();
			$('.pq_row:last').find('.net_rate_area').text('');
			$('.pq_row:last').find('.qty_symbol').text('unit');
			$('.pq_row:last').find('.amount').val('');
			$('.pq_row:last').find('.material_mod').val("no");
			$('.pq_row:last').find('.md-btn').remove();
			$('.pq_row:last').find('.qty_symbol_per').text('unit');
			$('.pq_row:last').find('.hidden_discount').val('');
			$('.pq_row:last').find('.individusl_discount').text('');
			rowCount = $('.pq_table >tbody >tr').length;
			$('.pq_body tr:last .pq_dr_no').text(rowCount+1-1);
			$('.pq_body tr:last .input_sr').val(rowCount+1-1);
			$(".chosen-select").chosen({width:'100%'});
			$(".chosen-project").chosen({width:'100%'});
			$(".chosen_brand").chosen({width:'100%'});
			$(".chosen_party").chosen({width:'100%'});
		};
		
		function remove_row(val){
			$(val).parent().parent('tr').remove();
			total = 0;
			var discount_amount=0;
			$('.amount').each(function(){
				total += Number($(this).val());
				$('#total').val(total);
				$('.total').text(total);
			})
			discount_amount=$('#discount').val();
			if (discount_amount==0 || isNaN(discount_amount)){
				$('#net').val(total);
				$('.net').text(total);
			grand();
			}
			var tot_quantity=0;
			$('.quantity').each(function(){
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			})
		}

		function addItem(){
			if(ite.valid()){
				$(".item-loading").show();
				$(".item-button").hide();
				$.ajax({
					url:"<?php echo ADMIN_URL; ?>ajax/inventory-popup.php",
					type:"POST",
					data:{'item-data':ite.serialize()}
				}).done(function(msg){
					$.ajax({
						url: "<?php echo ADMIN_URL; ?>ajax/inventory-popup.php",
						type: "POST",
						data: {'command':'Add_item'}
					}).done(function(result) {
						res = $.parseJSON(result);
						$(".selecteditem").html('');
						$(".selecteditem").html( res.html );
						$('.selecteditem').trigger("chosen:updated");
						$("#add_item").modal('hide');
						$(".item-loading").hide();
						$(".item-button").show();
					}).fail(function(jqXHR, textStatus) {
						alert( "Request  are failed: " + textStatus );
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

		function get_sub_location(val_this){
			$.ajax({
				url: '../add-multiple-invoice.php',
				type: 'POST',
				data: {item_loc:$(val_this).val(),command:'get_sub_loc'},
			})
			.done(function(data) {
				console.log(data);
				$('.sub_location').chosen('destroy');
				$('.sub_location').html('');
				$('.sub_location').html(data);
				$('.sub_location').chosen({width:'100%'});
				
			})
		}

		function fileChange(e) {
			$.each(e.target.files, function( index, value ) {
				var file = value;
				if (file.type == "image/jpeg" || file.type == "image/png") {
					var reader = new FileReader();  
					reader.onload = function(readerEvent) {
						var image = new Image();
						image.onload = function(imageEvent) {
							var max_size = 500;
							var w = image.width;
							var h = image.height;
							if (w > h) {  if (w > max_size) { h*=max_size/w; w=max_size; }
							} else {  if (h > max_size) { w*=max_size/h; h=max_size; } }
							var canvas = document.createElement('canvas');
							canvas.width = w;
							canvas.height = h;
							canvas.getContext('2d').drawImage(image, 0, 0, w, h);
							if (file.type == "image/jpeg") {
								var dataURL = canvas.toDataURL("image/jpeg", 1.0);
							} else {
								var dataURL = canvas.toDataURL("image/png");
							}
							$(".append_image").append("<input type='hidden' name='img[]' value="+dataURL+">");
						}
						image.src = readerEvent.target.result;
					}
					reader.readAsDataURL(file);
				}else {
					document.getElementById('inp_file').value = ''; 
					alert('Please only select images in JPG- or PNG-format.');  
				}
			});
		}
		document.getElementById('inp_file').addEventListener('change', fileChange, false);
	
		if( window.history.replaceState ){
			window.history.replaceState( null, null, window.location.href );
		}

		function calculateTotalEff(){
			var current_value=0;
			var total_value=0;
			if($("#discount_checkbox").prop("checked") == false){
				total_value = parseInt($(".gross-total").text());
				$(".discount_checkbox").val();
				if($(".chr_loading_amount").val()!='' || $(".chr_unloading_amount").val()!='' || $(".chr_other_amount").val()!='' || $('.chr_freight_amount').val()!=''){
					current_value = parseInt($(".grand_total").text());
				}else{
					current_value = parseInt($(".net").text());
				}
				calculateeftv(total_value,current_value);
			}else{
				total_value = parseInt($(".gross-total").text());
				if($(".chr_loading_amount").val()!='' || $(".chr_unloading_amount").val()!='' || $(".chr_other_amount").val()!='' || $('.chr_freight_amount').val()!=''){
					current_value = parseInt($(".grand_total").text());
				}else{
					current_value = parseInt($(".net").text());
				}
				calculateeftv(total_value,current_value);
			}
		}

		function getSaleItemQuantity(head,sub_head,item,modal,val_this){
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
				url:'<?php echo ADMIN_URL; ?>ajax/itemval.php',
				type : 'post',
				data : 'item_id=' + item + '&loc_id=' + head  + '&quality_id=' + quality + '&warehouse_id=' + warehouse + '&section_id=' +section + '&finishing_type=' + finishing_type + '&sub_location_id=' + sub_head + '&article_id=' +article+ '&brand_id=' +brand + '&color_id=' +color 
			}).done(function(data){
				data = data.trim();
				// console.log(data);
				data = JSON.parse(data)
				this_row.find(".quantity_span").text(data[0]);
				this_row.find(".inv_item_rate").val(data[1]);
			});
		}

		function QuantityAdd(val_this,modal_no) {

			$add_qty = Number($(val_this).val());
			$avail_qty = Number($(val_this).parent().parent().parent().find('.available_quantity').val());
			if($add_qty > $avail_qty){
				$(val_this).val('');
				$(val_this).parent().parent().find('.alterNateUnitSpan').text('');
				$(val_this).parent().parent().find('.alterNateUnitSpan').text('Quantity Exceeding');
				var quantity = 0;
				$("."+modal_no).find('.SubItemTable tbody tr').each(function(){
					quantity += parseFloat($(this).find(".inv_quantity").val());
				});
				$("."+modal_no).find(".SubItemTable tfoot .quantity_span .quantity_b").text(quantity);
				$("."+modal_no).find(".SubItemTable tfoot .quantity_td input[type=hidden]").val(quantity);
				
			}else{
				var alternate_unit = $("."+modal_no).parent().parent().find(".alternative_unit").val();
				var item_unit = $("."+modal_no).parent().parent().find(".main_item_unit").val();
				var conversion = $("."+modal_no).parent().parent().find(".conversionToAlternativeUnit").val();
				var quantity = 0;
				$("."+modal_no).find('.SubItemTable tbody tr').each(function(){
					quantity += parseFloat($(this).find(".inv_quantity").val());
				});
				if(alternate_unit !='' && conversion !=0){
					conversionToAlternateUnit = parseFloat($(val_this).val())/parseFloat(conversion);
					conversionToAlternateUnit = conversionToAlternateUnit.toFixed(2);
					$(val_this).parent().parent().find(".alterNateUnitSpan").text("( "+conversionToAlternateUnit+" "+alternate_unit+" )");
				}
				$("."+modal_no).find(".SubItemTable tfoot .quantity_span .quantity_b").text(quantity);
				$("."+modal_no).find(".SubItemTable tfoot .quantity_td input[type=hidden]").val(quantity);
				}
		}

		function AddSaleItemRow(val_this){
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
			$(".chosen_quality").chosen({width:'100%'});
			$(".chosen_warehouse").chosen({width:'100%'});
			$(".chosen_section").chosen({width:'100%'});
			$(".chosen_article").chosen({width:'100%'});
			$(".chosen_brand").chosen({width:'100%'});
			$(".chosen_color").chosen({width:'100%'});
			$(".chosen_finishingType ").chosen({width:'100%'});
		}

		function RemoveSaleItemRow(val_this){
			$(val_this).closest('tr').remove();
		}

		function calculateeftv(total_value,current_value){
			var amount=0;
			var total_perc=0;
			var amount_perc=0;
			var quantity_single=0;
			var eff_rate=0;
			$(".amount").each(function(index, el) {
				amount = parseFloat($(el).val());
				total_perc = amount*100/total_value;
				amount_per= total_perc/100*parseFloat(current_value);
				quantity_single = parseFloat($(el).parent().parent().parent().find(".quantity").val());
				eff_rate = amount_per/quantity_single;
				$(el).parent().parent().parent().find(".eff_net_rate_area").html(eff_rate);
				$(el).parent().parent().parent().find(".eff_net_rate").val(eff_rate);
			});
		}
		
		<?php
			if($_REQUEST['vehicle']=='single_vehicle'){?>
				// $(document).ready(function(){
					var veh=$('input[type=radio][name=vehicle_mode]:checked').val();
					$("#single_veh").trigger('click');
					$("#single_veh").prop('checked', true);
				// });
		<?php }?>

		function vendor(val_this){
			if($(val_this).is(':checked')){
				$(val_this).parent().parent().find('.chosen-select').chosen('destroy');
				$(val_this).parent().parent().find('.vendor_project').chosen('destroy');
				$(val_this).parent().parent().find('.expense_project').removeClass('chosen-select').hide();
				$(val_this).parent().parent().find('.expense_project').attr('name', '');
				$(val_this).parent().parent().find('.vendor_project').show();
				$(val_this).parent().parent().find('.vendor_project').addClass("chosen-select").attr('name', 'cr_project[]');
				$(val_this).parent().parent().find('.chosen-select').chosen({width:'100%'});
				vendorSetting(val_this, 'yes');
			}else{
				$(val_this).parent().parent().find('.chosen-select').chosen('destroy');
				$(val_this).parent().parent().find('.vendor_project').removeClass("chosen-select").hide();
				$(val_this).parent().parent().find('.vendor_project').attr('name', '');
				$(val_this).parent().parent().find('.expense_project').show();
				$(val_this).parent().parent().find('.expense_project').addClass("chosen-select").attr('name', 'cr_project[]');
				$(val_this).parent().parent().find('.chosen-select').chosen({width:'100%'});
				vendorSetting(val_this, 'no');
			}
			
		}
		$( ".vendor_project" ).prop( "disabled", true );
		$('.vendor_project').hide();
		function vendorSetting(val_this, status){
			$.ajax({
				url : 'add-multiple-invoice.php',
				type : 'post',
				dataType : 'json',
				data : {command : 'get_expense_head', status:status},
				success : function(result){
					$(val_this).parent().parent().parent().find('.proj_location').chosen('destroy');
					$(val_this).parent().parent().parent().find('.proj_location').html('');
					$(val_this).parent().parent().parent().find('.proj_location').html(result.location);
					if(status == 'no'){
						$(val_this).parent().parent().parent().find('.proj_location').val('');
					}
					$(val_this).parent().parent().parent().find('.proj_location').chosen({width:'100%'});
					$(val_this).parent().parent().parent().find('.sub_location').chosen('destroy');
					$(val_this).parent().parent().parent().find('.sub_location').html('');
					$(val_this).parent().parent().parent().find('.sub_location').html(result.sub_location);
					$(val_this).parent().parent().parent().find('.sub_location').chosen({width:'100%'});
				}
			});
		}
		function get_single_sub_location(val_this){
			$.ajax({
				url: 'add-multiple-invoice.php',
				type: 'POST',
				data: {item_loc:$(val_this).val(),command:'get_sub_loc'},
			})
			.done(function(data) {
				console.log(data);
				$(val_this).parent().parent().find('.sub_location').chosen('destroy');
				$(val_this).parent().parent().find('.sub_location').html('');
				$(val_this).parent().parent().find('.sub_location').html(data);
				$(val_this).parent().parent().find('.sub_location').chosen({width:'100%'});
				
			})
		}

		function CheckSubItem(val_this){
			var SubItemCode = $(val_this).parent().parent().parent().find(".SubItemCode").val();
			var SubItemArticle = $(val_this).parent().parent().parent().find(".SubItemArticle").val();
			var ItemID = $(val_this).closest(".brand_qual_modal").find(".check_item").val();
			$.ajax({
				url : 'add-sale-invoice.php',
				Type : 'post',
				dataType : 'json',
				data : {SubItemCode,SubItemArticle,ItemID,command:'CheckIngSubItem'},
				success : function(sub_item){
					if(sub_item != ''){
						// $(val_this).parent().parent().parent().find(".SubItemCodeText").text(sub_item.subItemName);
						$(val_this).parent().parent().parent().find(".SubITemID").val(sub_item.id);
					}else{
						
					}
					getSubwareHouseITemQuantity(val_this);
				}
			}).fail(function(jqXHR, textStatus) {
				alert("Sorry! No Sub Item Found");
				$(val_this).parent().parent().parent().find(".SubItemCodeText").text('');
				$(val_this).parent().parent().parent().find(".SubITemID").val('');
			});
		}

		function AddSubItemRow(val_this){
			$(".chosen-select").chosen("destroy");
			var cloneRow = $(val_this).parent().parent().clone();
			$(val_this).parent().parent().parent().append(cloneRow);
			$(val_this).parent().parent().parent().find("tr:last input").val('');
			$(val_this).parent().parent().parent().find("tr:last select").val('');
			$(val_this).parent().parent().parent().find("tr:last h4").text('');
			$(val_this).parent().parent().parent().find("tr:last h5").html('');
			$(".chosen-select").chosen({width:'100%'});

		}
		function removeSubItemrow(val_this){
			$(val_this).closest('tr').remove();
		}
		function checkWalkInCustomer(val_this) {
			$('.walk_in').val('');
			$.ajax({
				url : 'add-sale-invoice-jahangir.php',
				Type : 'post',
				dataType : 'json',
				data : {led_id:$(val_this).val(),command:'checkWalkInCustomer'},
				success : function(response){
					if (response.status=='yes') {
						$('#sub_ledger_model').modal('show');
						$('#sub_ledger_model .main_ledger_id').val(response.ledger_id);
						$('.walk_in_button_show').show();
					}else{
						$('.walk_in_button_show').hide();
					}
				}
			})
		}
		function checkSubledger(val_this) {
			$(".walk_in_check").prop("checked",false);
			$('.walk_in').val('');
			var walk_in_id = $(val_this).parent().find(".walk_in_id").val();	
			$('.walk_in').val(walk_in_id);	
			$('#sub_ledger_model .sub_ledger_id').val(walk_in_id);
			$(val_this).prop("checked",true);
			$('.sub_ledger_model').modal('hide');
			var sub_ledger_name = $(val_this).closest('tr').find('.walk_in_name').val();
			$('.walk_in_text_show').html('<b>'+sub_ledger_name+'</b>');
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
				url : 'add-sale-invoice.php',
				Type : 'post',
				dataType : 'json',
				data : {name:name,mobile:mobile,id_card:id_card,sub_ledger_id:sub_ledger_id,main_ledger_id:main_ledger_id,command:'verified_save'},
				success : function(response){
					$('.walk_in').val(response.id);
					if(response.status == 'insert'){
						alert("Added Successfully");
						getSubledger();
					}
					if(response.status == 'update'){
						alert("Updated Successfully");
						getSubledger();
					}
				}
			})

		}
	</script>
</body>
</html>