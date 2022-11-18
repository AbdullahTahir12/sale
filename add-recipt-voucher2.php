<?php
	include('includes/common-files.php');

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'get_sub_loc') {
		$db->select('select * from item_sublocation where location_id='.$_REQUEST['item_loc'].' and company_id='.getCompanyId());
		$sub_locations = $db->fetch_all();
		$sub_loc='';
		$sub_loc .= "<option selected disabled>Select Sub Location</option>";
		foreach ($sub_locations as $sub_location) {
			$sub_loc .= "<option value='".$sub_location['id']."'>".$sub_location['name']."</option>";
			}
			echo $sub_loc;
		exit();
	}
	if(isset($_REQUEST['l_type']) && $_REQUEST['l_type'] == 'form_return') {
		$form_return_ledger = $db->fetch_array_by_query('select * from ledger where id= 3420 and company_id='.getCompanyId());
		$ledger='';
		$ledger .= "<option selected value='".$form_return_ledger['id']."'>".$form_return_ledger['name']."</option>";
		echo json_encode(array('html' => $ledger));
		exit();
	}



	if((isset($_REQUEST['command']) && $_REQUEST['command'] == 'getFormReciept')){
		$adjustable_query ='';
		if((isset($_REQUEST['form_no']) && $_REQUEST['form_no'] !='' ) || (isset($_REQUEST['customer_id'])  && $_REQUEST['customer_id'] !='' ) || (isset($_REQUEST['type_form'])  && $_REQUEST['type_form'] !='')){
		$adjustable_query .= 'select pfi.* ,l.name as c_name, pfd.customer_ledger as customer ,pfd.form_amount as frm_am from pro_form_sale_detail as pfd left join pro_form_sale as pfs on pfd.form_sale_id = pfs.id left join pro_form_inventory as pfi on pfd.frm_id = pfi.id left join ledger as l on pfd.customer_ledger = l.id where (pfs.type="sale" or pfs.type="transfer") and pfs.locked ="no" and l.active_customer ="yes" and pfi.balloting="no" and  l.id!=3419';

		if(isset($_REQUEST['form_no']) && $_REQUEST['form_no'] !=''){
			$adjustable_query.=' and pfd.registration_no="'.$_REQUEST['form_no'].'"';
		}

		if(isset($_REQUEST['customer_id']) && $_REQUEST['customer_id'] !=''){
			$adjustable_query.=' and pfd.customer_ledger='.$_REQUEST['customer_id'];
		}

		if(isset($_REQUEST['type_form']) && intval($_REQUEST['type_form']) >0 ){
			$adjustable_query.=' and pfi.marla="'.$_REQUEST['type_form'].'"';
		}
			$db->Select($adjustable_query);
			$adjustable_forms = $db->fetch_all();
		}
		$adjusted_query = '';
		if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) >0 ){
			$count_rows = $db->fetch_array_by_query("Select * from form_adjustment where booking_id=".$_REQUEST['booking_id']." and status = 'soldout'");
			if($count_rows ==''){
				$db->Select("Select pfi.*,l.id as customer, l.name as c_name from form_adjustment as fd left join pro_form_inventory as pfi on fd.form_id = pfi.id left join ledger as l on fd.customer_id = l.id where fd.booking_id=".$_REQUEST['booking_id']." and fd.status ='balloting'");
				$adjusted_forms = $db->fetch_all();
			}else{
				$adjusted_forms = array();
			}

		}

        echo json_encode(array('adjustable_forms'=>$adjustable_forms,'adjusted_forms'=>$adjusted_forms));
		exit();
		
	}
						

	$location = '';
	$sublocation = '';
	$sale_contract = '';
	if(isset($_REQUEST['command']) && $_REQUEST['command']=='get-cash-flow' ){
		$ledger_id = intval($_REQUEST['ledger_id']);
		$db->select("select * from cost_categroy_transactions where ledger_id=".$ledger_id." and type='debit' and company_id=".getCompanyId()." group by cost_center_id");
		$related_cost_centers = $db->fetch_all();
		$cash_flow_cost_center = '';
		$cash_flow_cost_center .= "<div class='pull-right col-md-2' style='margin-bottom:15px'>";
			$cash_flow_cost_center .= "<label>Ledger Balance</label>";
			$cash_flow_cost_center .= "<div class='form-control balance_show'></div>";
		$cash_flow_cost_center .= "</div>";
		$cash_flow_cost_center .= "<div class='clearfix'></div>";
		$cash_flow_cost_center .= "<div style='background-color:#7f9d9d; color:white;height: 40px;line-height: 40px;margin-bottom: 15px;' class='col-md-5'><div class='col-md-8' style='padding-left:0px;font-size:18px;'><label class='myLabel' for='cost-center'><b>Party Bill</b></label></div><div class='col-md-4' style='font-size:18px;'><label class='myLabel' for='cost-center'><b>Balance</b></label></div></div>";
		$cash_flow_cost_center .= "<div style='background-color:#7f9d9d;color:white;height: 40px;line-height: 40px;margin-bottom: 15px;' class='col-md-5'><div class='col-md-8' style='padding-left:0px;font-size:18px;'><label class='myLabel' for='cost-center'><b>Cash Flow</b></label></div><div class='col-md-4' style='font-size:18px;'><label class='myLabel' for='cost-center'><b>Balance</b></label></div></div>";
		$cash_flow_cost_center .= "<div style='background-color:#7f9d9d;color:white;height: 40px;line-height: 40px;margin-bottom: 15px;font-size:18px;' class='col-md-2'><label class='myLabel' for='cost'><b>Cost</b></label></div>";
		$voucher_id=0;
		$i=0;
		$limit=1;
		$cash_flow_balance = 0;
		$party_bill_balance= 0;
		$cash_flow_cr = 0;
		$party_bill_cr= 0;
		$cash_flow_dr = 0;
		$party_bill_dr= 0;
		$total_itartion = count($related_cost_centers);		
		foreach ($related_cost_centers as $related_cost_center) {	
			$i++;
			if($i>1 && $related_cost_center['voucher_id']==$voucher_id){
				$limit++;
			}else{
				$limit=1;
			}
			$voucher_id = $related_cost_center['voucher_id'];
			$initial = $limit-1;
			
			$row_party_bill = $db->fetch_array_by_query("select * from cost_center_linked where cost_center=".$related_cost_center['cost_center_id']." and type_head='partybill'");

			$cost_center = $db->fetch_array_by_query("select * from cost_center where id=".$related_cost_center['cost_center_id']);
			if($row_party_bill){
				$cash_flow_linked_cost_center = $db->fetch_array_by_query("select * from cost_center_linked where ledger_id=".$row_party_bill['ledger_id']." and company_id=".getCompanyId()." and type_head='cashflow'");	
			}
			
			$party_bill_opening_balance = $db->fetch_array_by_query("select * from opening_balances where cost_center=".$related_cost_center['cost_center_id']." and ledger_id=".intval($ledger_id)." and cost_type='party_bill'");
			if (!empty($cash_flow_linked_cost_center)) {
				$cost_center_linked = $db->fetch_array_by_query("select * from cost_center where id=".$cash_flow_linked_cost_center['cost_center']);
				$cash_flow_opening_balance = $db->fetch_array_by_query("select * from opening_balances where cost_center=".$cash_flow_linked_cost_center['cost_center']." and ledger_id=".intval($cash_flow_linked_cost_center['ledger_id'])." and cost_type='cash_flow'");

			}
			else{
				$cash_flow_linked_cost_center['cost_center'] = 0;
				$cost_center_linked['name'] = 'No Cost Center';
			}
			if($cash_flow_linked_cost_center['cost_center'] != 0){$cost_center_val = getLedgerCostCenterBalance($cash_flow_linked_cost_center['cost_center'], $ledger_id, $db);$disabled = '';
			if(!empty($cash_flow_opening_balance)){
				$string = getCostCenterBalance($cash_flow_linked_cost_center['cost_center'],$db);
				$newstring = substr($string, -4);
				$cash_crdr = substr($newstring, 1, -1);
				$cash_amount = substr($string, 3, -5);
				$cash_flow_amount = str_replace(",","", $cash_amount);
				if($cash_crdr == 'Dr'){
					$cash_flow_total_amount = -($cash_flow_amount);
				}
				if($cash_flow_opening_balance['type'] == 'debit'){
					$cash_flow_opening_balance['opening_balance'] = -($cash_flow_opening_balance['opening_balance']);
				}
				$total = $cash_flow_total_amount + $cash_flow_opening_balance['opening_balance'];
				if($total < 0){
					$cost_center_val['crdr'] = 'Dr';
				}else{
					$cost_center_val['crdr'] = 'Cr';
				}
				$cost_center_val['amount'] = abs($total);
			}
				$party_bill = getLedgerCostCenterBalance($related_cost_center['cost_center_id'], $ledger_id, $db);
				if(!empty($party_bill_opening_balance)){
				if($party_bill['crdr'] == 'Dr'){
					$party_bill['amount'] = -($party_bill['amount']);
				}
				if($party_bill_opening_balance['type'] == 'debit'){
					$party_bill_opening_balance['opening_balance'] = -($party_bill_opening_balance['opening_balance']);
				}
					$total = $party_bill['amount'] + $party_bill_opening_balance['opening_balance'];	
				
				if($total < 0){
					$party_bill['crdr'] = 'Dr';
				}else{
					$party_bill['crdr'] = 'Cr';
				}
				$party_bill['amount'] = abs($total);
			}
			if ($cost_center_val['crdr'] == 'Cr') {
				$cash_flow_cr+=$cost_center_val['amount'];
			}else{
				$cash_flow_dr+=$cost_center_val['amount'];
			}
			if($cash_flow_cr>$cash_flow_dr){
				$cash_flow_crdr = 'Cr';
				$cash_crdr = 'credit';
			}else{
				$cash_flow_crdr = 'Dr';
				$cash_crdr = 'debit';	
			}
			$cash_flow_balance = "Rs ".abs($cash_flow_cr - $cash_flow_dr)." ".$cash_flow_crdr;
			}else{
				$cost_center_val['amount'] = 0;$disabled = 'disabled';
			}
			if ($party_bill['crdr'] == 'Cr') {
				$party_bill_cr+=$party_bill['amount'];
			}
			if($party_bill['crdr'] == 'Dr'){
				$party_bill_dr+=$party_bill['amount'];
			}
			if($party_bill_cr>$party_bill_dr){
				$party_bill_crdr = 'Cr';
			}else{
				$party_bill_crdr = 'Dr';	
			}
			$party_bill_balance = 'Rs '.abs($party_bill_cr - $party_bill_dr).' '.$party_bill_crdr;
			$cash_flow_cost_center .= "<div style='border-right:1px solid black; margin-bottom:0px !important;padding-bottom:15px;padding-right:0px;;padding-left:0px;' class='form-group col-lg-5'>";
			$cash_flow_cost_center .= "<div class='col-md-8'>";
			$cash_flow_cost_center .= "<div ".$disabled.">".$cost_center['name'].' ('.abs($party_bill_opening_balance['opening_balance']).' '.$party_bill_opening_balance['type'].")</div>";
			$cash_flow_cost_center .= "<input type='hidden' class='ccost_center_link' name='ccost_center_link[]' value='".$related_cost_center['cost_center_id']."' ".$disabled.">";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='col-md-4'>";
			$cash_flow_cost_center .= "<div ".$disabled.">Rs ".$party_bill['amount']." ".$party_bill['crdr'];
			$cash_flow_cost_center .= "</div>"; 
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<input type='hidden' class='form-control ccost_category' name='ccost_category[]' value='".$cost_center['parent_id']."' ".$disabled.">";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div style='border-right:1px solid black; margin-bottom:0px !important;padding-bottom:15px;padding-right:0px;padding-left:0px;' class='form-group col-lg-5'>";
			$cash_flow_cost_center .= "<div class='col-md-8'>";
			$cash_flow_cost_center .= "<div  ".$disabled.">".$cost_center_linked['name'].' ('.abs($cash_flow_opening_balance['opening_balance']).' '.$cash_flow_opening_balance['type'].")</div>";
			$cash_flow_cost_center .= "<input type='hidden' class='ccost_center' name='ccost_center[]' value='".$cash_flow_linked_cost_center['cost_center']."' ".$disabled.">";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='col-md-4'>";
			$cash_flow_cost_center .= "<div ".$disabled.">Rs ".$cost_center_val['amount']." ".$cost_center_val['crdr'];
			$cash_flow_cost_center .= "</div>"; 
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<input type='hidden' class='form-control ccost_catagory_cash_flow' name='ccost_catagory_cash_flow[]' value='".$cost_center_linked['parent_id']."' ".$disabled.">";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='form-group col-lg-2' style='margin-bottom:0px'>";
			$cash_flow_cost_center .= "<input type='number' class='form-control ccost_center_cost' name='ccost_center_cost[]'".$disabled." placeholder='Enter Amount'>";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='clearfix'></div>";
		}
			$cash_flow_cost_center .= "<div style='background-color:#7f9d9d; color:#defffe;height: 40px;line-height: 40px;margin-top: 15px; margin-bottom:0px !important;padding-bottom:15px;padding-right:0px;;padding-left:0px;' class='form-group col-lg-5'>";
			$cash_flow_cost_center .= "<div class='col-md-8'>TOTAL (Rs)";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='col-md-4'>";
			$cash_flow_cost_center .= "<div style='font-weight: 700;font-size: 15px;color: #defffe;'>".$party_bill_balance;
			$cash_flow_cost_center .= "</div>"; 
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div style='background-color:#7f9d9d; color:#defffe;height: 40px;line-height: 40px;margin-top: 15px; margin-bottom:0px !important;padding-bottom:15px;padding-right:0px;;padding-left:0px;' class='form-group col-lg-5'>";
			$cash_flow_cost_center .= "<div class='col-md-8'>";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='col-md-4'>";
			$cash_flow_cost_center .= "<div style='font-weight: 700;font-size: 15px;color: #defffe;'>".$cash_flow_balance;
			$cash_flow_cost_center .= "</div>"; 
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div style='background-color:#7f9d9d; color:#defffe;height: 40px;line-height: 40px;margin-top: 15px; margin-bottom:0px !important;padding-bottom:15px;padding-right:0px;;padding-left:0px;' class='form-group col-lg-2'>";
			$cash_flow_cost_center .= "</div>";
			$cash_flow_cost_center .= "<div class='clearfix'></div>";
		echo json_encode(array("cash_flow_cost_center"=>$cash_flow_cost_center));
		exit();
	}
	if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) > 0){
		$db->select("select * from pro_installment_plan where status='pending' and booking_id=".$_REQUEST['booking_id']." and company_id=".getCompanyId());
		$pending_installments = $db->fetch_all();
	}
	if (empty($pending_installments)) {
		$update_payment_booking = array();
		$update_payment_booking['property_status'] = "complete";
		$db->update($_REQUEST['booking_id'],$update_payment_booking,'pro_payment_booking');
	}
	if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) >0 ){
	$booking_id = $_REQUEST['booking_id'];
	$booking_row = $db->fetch_array_by_query("select * from voucher where booking_id=".$booking_id);
	$booking_detail = $db->fetch_array_by_query("select * from pro_payment_booking where id=".$booking_id);
	if($booking_detail){
		$location = $booking_detail['head'];
		$sublocation = $booking_detail['sub_head'];
		$sale_contract = $db->fetch_array_by_query("Select * from pro_frm_sale_contract where location_id=".$booking_detail['head']." and sublocation_id=".$booking_detail['sub_head']);
		// print_r($sale_contract);
	}
	}else{
		$booking_id =0;
	}
	if(isset($_REQUEST['installment_id']) && intval($_REQUEST['installment_id']) ){
		$installment_id = $_REQUEST['installment_id'];
	}else{
		$installment_id =0;
	}
	if(isset($_REQUEST['customer_id']) && intval($_REQUEST['customer_id']) ){
		$customer_id = $_REQUEST['customer_id'];
	}else{
		$customer_id =0;
	}
	if(isset($_REQUEST['property_id']) && intval($_REQUEST['property_id']) ){
		$property_id = $_REQUEST['property_id'];
	}else{
		$property_id =0;
	}
	if(isset($_REQUEST['installment_amount']) && intval($_REQUEST['installment_amount']) ){
		$installment_amount = $_REQUEST['installment_amount'];
	}else{
		$installment_amount ='';
	}
	$a->authenticate();
	$obj_group = load_class('costcenter');
	if(isset($_REQUEST['command']) && $_REQUEST['command']=='add'){
		addRecord($_REQUEST,$db);
	}
	if(isset($_REQUEST['type']) && $_REQUEST['type']!=''){
		$type = $_REQUEST['type'];
	}
	/*function check_voucher_no($type){
		$obj_settings = load_class('preferences');
		global $db;
		$voucher_last = $db->fetch_array_by_query("select * from voucher where company_id=".getCompanyId()." and type='receipt' order by id desc limit 1");
		if($voucher_last){
			$voucher_ar['voucher_no'] = $voucher_last['voucher_no']+1;
			$voucher_ar['series'] = $voucher_last['voucher_series'];
		}
		return $voucher_ar;
	}*/




	function getprevBalance($installment_id, $booking_id){
		global $db;
		$total_amount =  $db->fetch_array_by_query("select sum(installment_amt) as installment_sum from pro_installment_plan where id = ".$installment_id." and booking_id=".$booking_id." and payment_title != 'Last Sub Installment' and company_id=".getCompanyId());
		$paid_amount =  $db->fetch_array_by_query("select sum(transactions.amount) as paid_amount from voucher left join transactions on voucher.id = transactions.voucher_id where voucher.type='receipt' and voucher.payment_id=".$booking_id." and voucher.installment_id = ".$installment_id." and transactions.type='debit' and transactions.company_id=".getCompanyId());
		$remaining_amount = $total_amount['installment_sum'] - $paid_amount['paid_amount'];
		return $remaining_amount;
	}

	$serial_ar = voucherNumber('receipt');
	function addRecord($post,$db){

		$voucher_row = $db->fetch_array_by_query("select * from voucher where voucher_no=".$post['voucher_no']." and type='receipt' and company_id=".getCompanyId());
		if($voucher_row){
			//$voucher_last = $db->fetch_array_by_query("select * from voucher where company_id=".getCompanyId()." and type='receipt' order by id desc limit 1");
			$post['voucher_no'] = voucherNumber('receipt');
		}
		$arr = array();
		$arr['voucher_no'] = $post['voucher_no'];
		$arr['voucher_series'] = $post['voucher_series'];
		$arr['date'] = strtotime($post['date_vc']);
		$arr['reference_no'] = $post['reference_no'];
		$arr['type'] = $post['type'];
		$arr['narration'] = $post['narration'];
		$arr['created_by'] = getUSerId();
		if (isset($post['booking_id'])) {
			$arr['payment_id'] = $post['booking_id'];
			$arr['installment_id'] = $post['installment_id'];
			$arr['total_amount'] = $post['install_total_amount'];
		}
		$arr['company_id'] = getCompanyId();
		$arr['user_id'] = getUSerId();
		$arr['created_at'] =time();
		$arr['updated_at'] = time();
		if(isset($_REQUEST['frm_id']) && intval($_REQUEST['frm_id']) > 0){
			$arr['frm_sale_id'] = $_REQUEST['frm_id']; 	
			$arr['status'] = 'approved';
		}
		if (isset($post['sale_id'])) {
			$arr['sale_invoice'] = $post['sale_id'];
			if(isset($post['s_installment_id'])){
				$arr['s_install_id'] = $post['s_installment_id'];
				$arr['total_amount'] = $post['install_total_amount'];
			}
		}	
		if (count($_POST) && (isset($_POST['img'])) ){ 
			$voucher_row = $db->fetch_array_by_query("select * from voucher where id=".$voucher_id);
			$img = $_POST['img'];
			$images_arr =array();
			if($voucher_row['attachment']!=''){
				$images_arr = json_decode($voucher_row['attachment'],true);
			}
			foreach($_POST['img'] as $img){
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
				$file_name =rand().time().$ext;
				$file = '../site-content/uploads/'.$file_name;
				if (file_put_contents($file, $data)) {
					$images_arr [] = $file_name; 
				} else {
					echo "<p>The image could not be saved.</p>";
				}
			}
		}
		$arr['attachment'] = json_encode($images_arr);
		$voucher_id = $db->insert($arr,'voucher');
		$cost_count=0;
		if($voucher_id){
			foreach($_REQUEST['db_ledger_ids'] as $index=>$ledgers){
				$sr_no = $_REQUEST['dr_sr_no'][$index];
				$transaction_arr=array();
				if($_REQUEST['db_amount'][$index]!=''){
					$transaction_arr['ledger_id'] = $ledgers;
					$transaction_arr['voucher_id'] = $voucher_id;
					$transaction_arr['description']=$_REQUEST['description'][$index];
					$transaction_arr['type'] = 'debit';
					$transaction_arr['amount'] = $_REQUEST['db_amount'][$index];
					if(isset($_REQUEST['sub_ledger_ids'][$index]) && $_REQUEST['sub_ledger_ids'][$index]!=''){
						$transaction_arr['sub_ledger_id'] = intval($_REQUEST['sub_ledger_ids'][$index]);
					}
					$transaction_arr['company_id'] = getCompanyId();
					$transaction_arr['user_id'] = getUSerId();
					$transaction_arr['created_at'] = time();
					$transaction_arr['updated_at'] = time();
					$transaction_arr['instrument_no'] = $_REQUEST['instrument_no'][$index];
					$transaction_arr['bank'] = $_REQUEST['bank'][$index];
					$transaction_arr['bank_date'] = strtotime($_REQUEST['bank_date'][$index]);
					$transaction_arr['payment_mode'] = $_REQUEST['payment_mode'][$index];
					if($_REQUEST['db_amount'][$index]!=0){
						$transaction_id = $db->insert($transaction_arr,'transactions');
						$result = $transaction_id;
						foreach($_REQUEST['cost_categroy'.$sr_no] as $index_cost => $cost_category){
							$_REQUEST['cost_center'.$sr_no][$index_cost];
							$cost_transaction=array();
							$cost_transaction['voucher_id'] = $voucher_id;
							$cost_transaction['ledger_id'] = $ledgers;
							$cost_transaction['cost_cat_id'] = $_REQUEST['cost_categroy'.$sr_no][$index_cost];
							$cost_transaction['cost_center_id'] = $_REQUEST['cost_center'.$sr_no][$index_cost];
							$cost_transaction['transaction_id'] = $transaction_id;
							$cost_transaction['cost'] = intval($_REQUEST['cost_center_cost'.$sr_no][$index_cost]);
							$cost_transaction['created_at'] = time();
							$cost_transaction['updated_at'] = time();
							$cost_transaction['company_id'] = getCompanyId();
							$cost_transaction['user_id'] = getUserId();
							$cost_categroy_transactions = $db->insert($cost_transaction,'cost_categroy_transactions');
						}
						$cost_count++;
					}
				}
			}


			$cost_count=0;
			foreach($_REQUEST['ledger_ids'] as $index=>$ledgers){
				$sr_no = $_REQUEST['cr_sr_no'][$index];
				$transaction_arr=array();
				if($_REQUEST['cr_amount'][$index]!=''){
					$transaction_arr['ledger_id'] = $ledgers;
					$transaction_arr['voucher_id'] = $voucher_id;
					$transaction_arr['description']=$_REQUEST['cr_description'][$index];
					$transaction_arr['type'] = 'credit';
					$transaction_arr['amount'] = $_REQUEST['cr_amount'][$index];
					if(isset($_REQUEST['cr_location_id'][$index]) && $_REQUEST['cr_location_id'][$index]!=''){
						$transaction_arr['location_id'] = intval($_REQUEST['cr_location_id'][$index]);
					}	
					if(isset($_REQUEST['cr_sub_id'][$index]) && $_REQUEST['cr_sub_id'][$index]!=''){
						$transaction_arr['sub_location_id'] = intval($_REQUEST['cr_sub_id'][$index]);
					}
					$transaction_arr['company_id'] = getCompanyId();
					$transaction_arr['user_id'] = getUSerId();
					$transaction_arr['created_at'] = time();
					$transaction_arr['updated_at'] = time();
					if($_REQUEST['cr_amount'][$index]!=0){
						$transaction_id = $db->insert($transaction_arr,'transactions');
						if(isset($_REQUEST['cr_sub_id'][$index]) && $_REQUEST['cr_sub_id'][$index]!=''){
							$row = $db->fetch_array_by_query("select * from cost_center_linked where location_sub_head=".$_REQUEST['cr_sub_id'][$index]." and type_head='cash_flow_single' and company_id=".getCompanyId());
							$credit_cashflow_cost_center = $db->fetch_array_by_query("select * from cost_center where id=".$row ['cost_center']." and company_id=".getCompanyId());
							if(!empty($credit_cashflow_cost_center)){
								$credit_cashflow = array();
								$credit_cashflow['voucher_id'] = $voucher_id;
								$credit_cashflow['ledger_id'] = $ledgers;
								$credit_cashflow['cost_cat_id'] = intval($credit_cashflow_cost_center['parent_id']);
								$credit_cashflow['cost_center_id'] = intval($credit_cashflow_cost_center['id']);
								$credit_cashflow['transaction_id'] = intval($transaction_id);
								$credit_cashflow['cost'] = intval( $_REQUEST['cr_amount'][$index]);
								$credit_cashflow['created_at'] = time();
								$credit_cashflow['type'] = 'credit';
								$credit_cashflow['updated_at'] = time();
								$credit_cashflow['company_id'] = getCompanyId();
								$credit_cashflow['user_id'] = getUserId();
								if(isset($_REQUEST['cr_location_id'][$index]) && $_REQUEST['cr_location_id'][$index] !=''){
									$credit_cashflow['location_id'] = intval($_REQUEST['cr_location_id'][$index]);	
								}
								if(isset($_REQUEST['cr_sub_id'][$index]) && $_REQUEST['cr_sub_id'][$index]!=''){
									$credit_cashflow['sub_location_id'] = intval($_REQUEST['cr_sub_id'][$index]);
								}
								$credit_cashflow_transactions = $db->insert($credit_cashflow,'cost_categroy_transactions');
							}
						}

					if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) ){
					$booking_row = $db->fetch_array_by_query("select * from pro_payment_booking where id=".$_REQUEST['booking_id']);
					if($_REQUEST['frm_id'.$sr_no]!=''){
						foreach($_REQUEST['frm_id'.$sr_no] as $frm_index => $frm_id){
							$db->select("select * from pro_form_sale_detail where frm_id=".$frm_id);
							$form_transfer = $db->fetch_all();
							foreach($form_transfer as $resale){
								$db->query("update pro_form_sale set locked='yes' where id=".$resale['form_sale_id']);
							}
							$form_row = $db->fetch_array_by_query("Select * from pro_form_inventory where id=".$frm_id);
							$sale_contract = $db->fetch_array_by_query("Select * from pro_frm_sale_contract where location_id=".$form_row['location_id']." and sublocation_id=".$form_row['sublocation_id']);
							$form_adj_arr = array();
							$form_adj_arr['form_id'] = $frm_id;
							$form_adj_arr['customer_id'] = intval($_REQUEST['cust_id'.$sr_no][$frm_index]);
							$form_adj_arr['amount'] = intval($_REQUEST['frm_amount'.$sr_no][$frm_index]);
							$form_adj_arr['booking_id'] = intval($_REQUEST['booking_id']);
							$form_adj_arr['adjusted_by'] = intval($booking_row['customer_ledger']);
							$form_adj_arr['status'] = $_REQUEST['status'.$sr_no][$frm_index];
							$form_adj_arr['created_at'] = time();
							$form_adj_arr['updated_at'] = time();
							$form_adj_arr['user_id'] = getUSerId();
							$form_adj_arr['company_id'] = getCompanyId();
							// print_r($form_adj_arr);
							$inserT_log = $db->insert($form_adj_arr,'form_adjustment');

							$array_sale = array();
							$series = proFormSeries('transfer');
							$array_sale['sale_forms'] = '1';
							$array_sale['form_amount'] = intval($_REQUEST['frm_amount'.$sr_no][$frm_index]);
							$array_sale['created_at'] = time(); 
							$array_sale['updated_at'] = time();
							$array_sale['company_id'] = getCompanyId();
							$array_sale['user_id'] = getUserId();
							$array_sale['seller_id'] = intval($_REQUEST['cust_id'.$sr_no][$frm_index]);
							$array_sale['location_id'] = intval($form_row['location_id']);
							$array_sale['sublocation_id'] = intval($form_row['sublocation_id']);
							// $array_sale['customer_ledger'] = $sale_contract['company_return_form_ledger'];
							$array_sale['customer_ledger'] = 3419;
							$array_sale['sale_date'] = time();
							$array_sale['type'] = 'transfer';
							$array_sale = array_merge($array_sale,$series);
							// print_r($array_sale);
							$result_sale = $db->insert($array_sale,"pro_form_sale");
							
							$insert_array = array();
							$insert_array['registration_no'] = $_REQUEST['reg_no'.$sr_no][$frm_index];
							$insert_array['frm_id'] = $frm_id;
							$insert_array['property_type'] = $form_row['marla']." ". $form_row['unit'];
							$insert_array['location_id'] = intval($form_row['location_id']);
							$insert_array['sublocation_id'] = intval($form_row['sublocation_id']);
							// $insert_array['customer_ledger'] = intval($sale_contract['company_return_form_ledger']);
							$insert_array['customer_ledger'] = 3419;
							$insert_array['form_amount'] = $_REQUEST['frm_amount'.$sr_no][$frm_index];
							$insert_array['company_id'] = getCompanyId();
							$insert_array['user_id'] = getUserId();
							$insert_array['created_at'] = time();
							$insert_array['seller_id'] = intval($_REQUEST['cust_id'.$sr_no][$frm_index]);
							$insert_array['updated_at'] = time();
							$insert_array['form_sale_id'] = $result_sale;
							// print_r($insert_array);
							$result_sale_detail = $db->insert($insert_array,'pro_form_sale_detail');
						}
					}
				}
				








						$result = $transaction_id;
						if($_REQUEST['c_cost_active'][$index]=='yes'){
							foreach($_REQUEST['ccost_category'.$sr_no] as $index_cost => $cost_category){
								// if($_REQUEST['ccost_center_cost'.$sr_no][$index_cost]==''){
								// 	continue;
								// }
								if(intval($_REQUEST['ccost_center_cost'.$sr_no][$index_cost])>0){
									$cost_transaction=array();
									$cost_transaction['voucher_id'] = $voucher_id;
									$cost_transaction['ledger_id'] = $ledgers;
									$cost_transaction['cost_cat_id'] = $_REQUEST['ccost_category'.$sr_no][$index_cost];
									$cost_transaction['cost_center_id'] = $_REQUEST['ccost_center_link'.$sr_no][$index_cost];
									$cost_transaction['transaction_id'] = $transaction_id;
									$cost_transaction['cost'] = intval($_REQUEST['ccost_center_cost'.$sr_no][$index_cost]);
									$cost_transaction['created_at'] = time();
									$cost_transaction['type'] = 'credit';
									$cost_transaction['updated_at'] = time();
									$cost_transaction['company_id'] = getCompanyId();
									$cost_transaction['user_id'] = getUserId();
									if(isset($_REQUEST['cr_location_id'][$index]) && $_REQUEST['cr_location_id'][$index] !=''){
										$cost_transaction['location_id'] = intval($_REQUEST['cr_location_id'][$index]);	
									}
									if(isset($_REQUEST['cr_sub_id'][$index]) && $_REQUEST['cr_sub_id'][$index]!=''){
										$cost_transaction['sub_location_id'] = intval($_REQUEST['cr_sub_id'][$index]);
									}
									$cost_categroy_transactions = $db->insert($cost_transaction,'cost_categroy_transactions');
									$cost_transaction_cashflow=array();
									$cost_transaction_cashflow['voucher_id'] = $voucher_id;
									$cost_transaction_cashflow['ledger_id'] = $ledgers;
									$cost_transaction_cashflow['cost_cat_id'] = $_REQUEST['ccost_catagory_cash_flow'.$sr_no][$index_cost];
									$cost_transaction_cashflow['cost_center_id'] = $_REQUEST['ccost_center'.$sr_no][$index_cost];
									$cost_transaction_cashflow['transaction_id'] = $transaction_id;
									$cost_transaction_cashflow['cost'] = intval($_REQUEST['ccost_center_cost'.$sr_no][$index_cost]);
									$cost_transaction_cashflow['created_at'] = time();
									$cost_transaction_cashflow['type'] = 'credit';
									if(isset($_REQUEST['cr_location_id'][$index]) && $_REQUEST['cr_location_id'][$index] !=''){
										$cost_transaction['location_id'] = intval($_REQUEST['cr_location_id'][$index]);
									}
									if(isset($_REQUEST['cr_sub_id'][$index]) && $_REQUEST['cr_sub_id'][$index] !=''){
										$cost_transaction['sub_location_id'] = intval($_REQUEST['cr_sub_id'][$index]);	
									}		
									$cost_transaction_cashflow['updated_at'] = time();
									$cost_transaction_cashflow['company_id'] = getCompanyId();
									$cost_transaction_cashflow['user_id'] = getUserId();
									$cost_categroy_transactions_cashflow = $db->insert($cost_transaction_cashflow,'cost_categroy_transactions');
								}
							}
							$cost_count++;
						}
					}
				}
			}


		if(isset($_REQUEST['charges_ledger']) && $_REQUEST['charges_value']!=''){
				// }
				$transaction_arr['ledger_id'] = $_REQUEST['charges_ledger'][0];
				$transaction_arr['voucher_id'] = $voucher_id;
				$transaction_arr['type'] = 'debit';
				$transaction_arr['charges'] = 'yes';
				$transaction_arr['amount'] = floatval($_REQUEST['charges_value']);
				$transaction_arr['created_at'] = time();
				$transaction_arr['updated_at'] = time();
				$transaction_id = $db->insert($transaction_arr,'transactions');
			}
			if(isset($_REQUEST['credit_charges_ledger']) && $_REQUEST['charges_value']!=''){
				$transaction_arr['ledger_id'] = $_REQUEST['credit_charges_ledger'][0];
				$transaction_arr['voucher_id'] = $voucher_id;
				$transaction_arr['type'] = 'credit';
				$transaction_arr['charges'] = 'yes';
				$transaction_arr['amount'] = floatval($_REQUEST['charges_value']);
				$transaction_arr['created_at'] = time();
				$transaction_arr['updated_at'] = time();
				$transaction_arr['payment_mode'] = $_REQUEST['payment_mode_charges'];
				$transaction_id = $db->insert($transaction_arr,'transactions');
			}



			//booking data
			if($voucher_id){
				if($_REQUEST['booking_id'] > 0 && $_REQUEST['property_id'] > 0){
					$prev_installment_arr =  $_REQUEST['prev_installments'].','.$_REQUEST['installment_id'];
					$prev_installments = explode(',', $prev_installment_arr);
					$amo = $_REQUEST['install_total_amount'];
					$total_installment = $db->fetch_array_by_query("select count(*) as count from pro_installment_plan where status='pending' and booking_id=".$_REQUEST['booking_id']." and company_id=".getCompanyId());
					if($total_installment['count'] > 1){

						foreach($prev_installments as $prev_installment){
							$install = $db->fetch_array_by_query("select * from pro_installment_plan where id = ".$prev_installment." and booking_id=".$_REQUEST['booking_id']." and company_id=".getCompanyId());
							if($install['status'] == 'pending'){
								$prev_bal = 0;
								$prev_bal = getprevBalance($prev_installment, $_REQUEST['booking_id']);
								if($amo >= $prev_bal){
									echo $_REQUEST['installment_id'].'<br>';
									$amo = $amo - $prev_bal;
									$db->query("UPDATE pro_installment_plan SET status = 'paid' WHERE id = ".$prev_installment." and booking_id=".$_REQUEST['booking_id']." and company_id=".getCompanyId());
								}
							}
						}
					}else{


						$total_amount =  $db->fetch_array_by_query("select sum(installment_amt) as installment_sum from pro_installment_plan where booking_id=".$_REQUEST['booking_id']." and payment_title != 'Last Sub Installment' and company_id=".getCompanyId());
						$paid_amount =  $db->fetch_array_by_query("select sum(transactions.amount) as paid_amount from voucher left join transactions on voucher.id = transactions.voucher_id where voucher.type='receipt' and voucher.payment_id=".$_REQUEST['booking_id']." and transactions.type='debit' and transactions.company_id=".getCompanyId());
						$remaining_amount = $total_amount['installment_sum'] - $paid_amount['paid_amount'];



						if($remaining_amount == 0){
							$db->query("UPDATE pro_installment_plan SET status = 'paid' WHERE id = ".$_REQUEST['installment_id']." and booking_id=".$_REQUEST['booking_id']." and company_id=".getCompanyId());
						}
						else if($total_amount['installment_sum'] >= $paid_amount['paid_amount']){
							$db->query("UPDATE pro_installment_plan SET status = 'paid' WHERE id = ".$_REQUEST['installment_id']." and booking_id=".$_REQUEST['booking_id']." and company_id=".getCompanyId());
							$installment_arr = array();
							$installment_arr['booking_id'] = $_REQUEST['booking_id'];
							$installment_arr['payment_title'] = 'Last Sub Installment';
							$installment_arr['installment_date'] = time();
							$installment_arr['installment_amt'] = $remaining_amount;
							$installment_arr['status'] = 'pending';
							$installment_arr['type'] = 'sub_installment';
							$installment_arr['created_at'] = time();
							$installment_arr['updated_at'] = time();
							$installment_arr['company_id'] = getCompanyId();
							$installment_arr['user_id'] = getUserId();
							$db->insert($installment_arr,'pro_installment_plan');
						}

					}
					$obj_msg = load_class('InfoMessages');
					$obj_msg->setMessage('Installment Paid Successfully!');
					redirect_header(ADMIN_URL.'property/show-sale-detail.php?id='.$post['booking_id']);
					
				}
				if(isset($_REQUEST['sale_id']) && ($_REQUEST['sale_id'] > 0)){
					if($voucher_id > 0){
						if(isset($_REQUEST['s_installment_id'])){
							$db->query("update sale_installment_plan set status='paid',updated_at=".time()." where id=".$_REQUEST['s_installment_id']." and sale_invoice_id=".$_REQUEST['sale_id']);
						}
						$obj_msg = load_class('InfoMessages');
						$obj_msg->setMessage('Voucher Created Successfully!');
						redirect_header(ADMIN_URL.'sale/sale-invoice.php');
					}
				}

			}

			if(isset($_REQUEST['fee_credit_ledger']) && intval($_REQUEST['fee_credit_ledger']) > 0){
				if($voucher_id){
					$property_fee_array = array();
					$property_fee_array['booking_id'] = $_REQUEST['booking_id'];
					$property_fee_array['property_fee_voucher_id'] = $_REQUEST['voucher_id'];
					$property_fee_array['paid_voucher_id'] = $voucher_id;
					$property_fee_array['paid_amount'] = $_REQUEST['installment_amount'];
					$property_fee_array['created_at'] = time();
					$property_fee_array['updated_at'] = time();
					$property_fee_array['company_id'] = getCompanyId();
					$property_fee_array['user_id'] = getUSerId();
					$db->insert($property_fee_array, 'p_transfer_fee');
					$obj_msg = load_class('InfoMessages');
					$obj_msg->setMessage('Data Added Successfully!');
					redirect_header(ADMIN_URL.'property/show-sale-detail.php?id='.$post['booking_id']);
				}
			}


			if(isset($_REQUEST['NDC_credit_ledger']) && intval($_REQUEST['NDC_credit_ledger']) > 0){
				if($voucher_id){
					$ndc_array = array();
					$ndc_array['booking_id'] = $_REQUEST['booking_id'];
					$ndc_array['ndc_voucher_id'] = $_REQUEST['voucher_id'];
					$ndc_array['paid_voucher_id'] = $voucher_id;
					$ndc_array['paid_amount'] = $_REQUEST['installment_amount'];
					$ndc_array['created_at'] = time();
					$ndc_array['updated_at'] = time();
					$ndc_array['company_id'] = getCompanyId();
					$ndc_array['user_id'] = getUSerId();
					$db->insert($ndc_array, 'p_ndc');
					$obj_msg = load_class('InfoMessages');
					$obj_msg->setMessage('Data Added Successfully!');
					redirect_header(ADMIN_URL.'property/show-sale-detail.php?id='.$post['booking_id']);
				}
			}


			if($result){
				$obj_msg = load_class('InfoMessages');
				$obj_msg->setMessage('Added Successfully!');
				if(isset($_REQUEST['frm_id']) && intval($_REQUEST['frm_id']) > 0){
					redirect_header(ADMIN_URL.'property/sale-detail.php?id='.$_REQUEST['frm_id']);
				}else{
					redirect_header(ADMIN_URL.'general-voucher.php?type='.$_REQUEST['type']);	
				}
				
			}else{
				$obj_msg = load_class('InfoMessages');
				$obj_msg->setMessage('Try Again');
				if(isset($_REQUEST['frm_id']) && intval($_REQUEST['frm_id']) > 0){
					redirect_header(ADMIN_URL.'property/sale-detail.php?id='.$_REQUEST['frm_id']);
				}else{
					redirect_header(ADMIN_URL.'general-voucher.php?type='.$_REQUEST['type']);	
				}
				
			}
		}
		else{
				$obj_msg = load_class('InfoMessages');
				$obj_msg->setMessage('Try Again!');
				redirect_header(ADMIN_URL.'general-voucher.php?type='.$_REQUEST['type']);
		}

	}
	if(!isset($_REQUEST['type'])){
		$type = 'journal';
	}else{
		$type = $_REQUEST['type'];
	}
	if(isset($_REQUEST['frm_id']) && intval($_REQUEST['frm_id'])>0){
		$sale_row = $db->fetch_array_by_query("select * from pro_form_sale where id=".$_REQUEST['frm_id']." and company_id=".getCompanyId());
		if(!empty($sale_row)){
			$location = $sale_row['location_id'];
			$sublocation = $sale_row['sublocation_id'];
		}
	}
	if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) ){
		$booking_row = $db->fetch_array_by_query("select * from pro_payment_booking where id=".$booking_id);
		if(!empty($booking_row)){
			$location = $booking_row['head'];
			$sublocation = $booking_row['sub_head'];
		}
	}
	if(isset($_REQUEST['sale_id']) && intval($_REQUEST['sale_id']) > 0){
		$sale_invoice_row = $db->fetch_array_by_query("Select * from sale_invoice where id=".$_REQUEST['sale_id']);
		$check_walk_in = $db->fetch_array_by_query("Select * from ledger where id=".$sale_invoice_row['customer_id']);
		$sub_ledger = $db->fetch_array_by_query("select * from sub_ledgers where id=".$sale_invoice_row['sub_ledger_id']);
		if(!empty($sale_invoice_row)){
			$location = $sale_invoice_row['location_id'];
			$sublocation = $sale_invoice_row['sub_location_id'];
		}
	}

	$page_title = "Add ".ucfirst($type)." Voucher";
	$tab = "Accounting Voucher";
?>
<!DOCTYPE html>
<html>
	<head>
		<?php include("includes/common-header.php");?>
		<link rel="stylesheet" href="<?php echo BASE_URL;?>css/voucher.css?v=7.1" type="text/css"/>
<style type="text/css">
.form-control{border-radius: 5px !important}
.modal-href{display: inline-table;}
.fa{
font-size: 16px
}
.modal-dialog{
	width: 90%;
}

@media only screen and (max-width: 600px) and (min-width: 300px)  {
.modal-href{display:inherit !important;}
.table .form-control{width: 200px;}
}

@media only screen and (min-width: 1450px)  {
.modal-href{display:block !important;
margin: 16px auto;
}
}
@media only screen and (min-width:787px)  {
	#sliderModal .vl1 .row{
		padding-left: 10% !important;
	}
}

.vl {
	border-left: 4px solid green;
	height: 170px;
}
.vl1 {
	border-radius: 4px;
	border-bottom: 4px solid green;
	width: 100%;
	border-right: 4px solid green;
	border-left: 4px solid green;
}
.locationChk{margin-top:30px;}

@media only screen and (max-width:480px) {
	button { width: 98% !important;
		display:block;
	}
	.vl {
		border-left: 4px solid green;
		height: 20px !important;
		margin-left: 50%;
	}
}

tfoot .title_bg {text-align: center;color: #4a4a4a;font-weight: 700;font-size: 15px;color: #defffe;}
tfoot .title_bg td{font-size: 20px}
.P_b{border-bottom-left-radius:0px !important}
.P_b{border-top-left-radius:0px !important}
.pv_b{border-bottom-right-radius:0px !important}
.pv_b{border-top-right-radius:0px !important}
</style>
</head>
<body class="skin-green-light sidebar-mini">
	<div class="wrapper">		  
	  	<?php include("includes/header.php");?>
	  	<div class="content-wrapper">
			<section class="content-header">
			  <h1>
				<?php echo ucfirst($tab);?>
				<span class="small"><?php echo $page_title;?></span>
			  </h1>
			  <ol class="breadcrumb">
				<li><a href="<?php echo ADMIN_URL;?>"><i class="fa fa-dashboard"></i> Home</a></li>
				<li class="active"><?php echo $page_title;?></li>
			  </ol>
			</section>
			<form method="post" enctype="multipart/form-data" id='form1' name="form">
				<section class="content">
					<div class="row">
						<div class="col-lg-12 col-sm-12 col-xs-12">
							<div class="row clearfix">
								<div class="span12">
									<?php echo $imsg->getMessage();?>
								</div>
							</div>
							<input type="hidden" name="command" value="add">
							<input type="hidden" name="type" value="<?php echo $type?>">
							<input type="hidden" name="frm_id" value="<?php echo $_REQUEST['frm_id']?>">
							<div class="append_image"></div>
							<div class="box box-danger">
								<div class="box-header">
									<h3 class="box-title pull-left"><?php echo $page_title;?></h3>
									<a onclick="goBack()" class="btn btn-default pull-right">Go Back</a>
								</div>
								<div class=" box-body">
									<?php
									if ($property_id != 0) {
									$property_detail = $db->fetch_array_by_query("select * from pro_inventory where id=".$property_id);
									?>
									<div style="margin: 0px !important" class="row">
										<div class="col-md-5"></div>
										<div style="border:1px solid #3c8dbc;border-radius: 3px;" class="col-md-7">
											<h4><b>Property Details</b></h4>
											<div class="col-md-7 no-gutter">
											<div class="form-group  col-md-4 col-sm-3 col-xs-12">
												<label for="usr">Property No</label>
												<div>
												<?php echo $property_detail['property_no'] ?>	
												</div>
											</div>
											<div class="form-group  col-md-4 col-sm-3 col-xs-12">
												<label for="usr">Floor No</label>
												<div>
												<?php echo ucfirst($property_detail['floor_no']) ?>	
												</div>
											</div>
											<div class="form-group  col-md-4 col-sm-3 col-xs-12">
												<label for="usr">Category</label>
												<div>
												<?php echo ucfirst($property_detail['category']) ?>	
												</div>
											</div>
											</div>
											<div class="col-md-5 no-gutter">
											<div class="form-group  col-md-6 col-sm-3 col-xs-12">
												<label for="usr">Nature</label>
												<div>
												<?php echo ucfirst($property_detail['nature']) ?>	
												</div>
											</div>
											<div class="form-group col-md-6 col-sm-3 col-xs-12">
												<input type="hidden" value="<?php echo $installment_amount; ?>" class="total_amount">
												<label for="usr">Total Amount</label>
												<div class="total_am">
												<span>	
												<?php echo number_format($installment_amount); ?>
												</span>
												<input type="hidden" value="<?php echo $installment_amount; ?>" class="total_inp">
												</div>
											</div>
											</div>

										</div>
									</div>
									<br>
								<?php } ?>
									<div class="form-group  col-md-2 col-sm-6 col-xs-12">
										<label for="usr">Voucher No</label>
										<div class="clearfix"></div>
										<div class="col-sm-5 no-gutter">
											<input type="text" class="form-control pv_b" name="voucher_series" id="voucher_no" value="<?php echo 'rv'?>" readonly>
										</div>
										<div class="col-sm-7 no-gutter">
											<input type="text" class="form-control P_b" name="voucher_no" id="voucher_no" value="<?php echo $serial_ar?>" readonly>
										</div>
									</div>
									<div class="form-group  col-md-2  col-sm-6 col-xs-12 ">
										<label for="reference">Reference</label>
										<input type="text" class="form-control financial_year" placeholder="Reference" name="reference_no">
									</div>
									<?php
								  		$date=strtotime(date('d-m-Y'));
										$day_value=date('l',$date);
									?>	
									<div class="form-group col-md-2  col-sm-6 col-xs-12 ">
										<label for="day">Day</label>
										<input type="text" class="form-control" placeholder="Day"  id="weekDay" value="<?php echo $day_value?>" readonly>
									</div>
									<div class="form-group  col-md-2 col-sm-6 col-xs-12">
										<label for="date">Date</label>
										<input type="text" class="form-control voucher_date" placeholder="Date" value="<?php echo date('d-m-Y') ?>" name="date_vc">
									</div>
									<?php /*<div class="form-group  col-md-2 col-sm-6 col-xs-12">
										<label class="locationChk">
										<input type="checkbox" onclick="ShowLocations(this);" class="showLocation" name="checklocation">
										 &nbspLocation </label>
									</div>*/ ?>
									<input type="hidden" name="payment_mode">
									<!-- <div class="form-group col-md-2 col-sm-6 col-xs-12">
										<label>&nbsp;</label>
										<div class="dropdown">
											<button class="btn btn-primary btn-block dropdown-toggle" data-toggle="dropdown" > Payment Mode <span class="caret"></span></button>
											<ul class="dropdown-menu select-payment" onchange="selectPayment(this)">
												<li><a data-toggle="tab" href="#menu1"> Cash (Default) </a></li>
												<li value="cheque"><a data-toggle="tab" href="#menu2"> Cheque </a></li>
												<li value="bank"><a data-toggle="tab" href="#menu3"> Bank </a></li>
											</ul>
											<script> document.form.select-payment.value=<?php echo $payment_mode ?></script>
										</div>
									</div> -->
									<div class="clearfix"></div>
									<!-- <div class="tab-content">
										<div id="menu1" class="tab-pane fade">
											<h3> Cash Payment </h3>
											<p><strong> That Is Default Set To A Cash </strong></p> -->
											<!-- <div class="form-group col-md-2">
												<label> Cash Amount </label>
												<input type="Number" class="form-control" placeholder="Enter Amount">
											</div> -->
										<!-- </div>
										<div id="menu2" class="tab-pane fade">
											<h3> Cheque Payment </h3>
											<div class="form-group col-md-2">
												<label> Instrument Number </label>
												<input type="Number" class="form-control" placeholder="Enter Amount" name="cheque_no">
											</div>
											<div class="form-group col-md-2">
												<label> Bank Name </label>
												<input type="text" class="form-control" name="bank_name" placeholder="Enter Bank Name">
											</div>
										</div>
										<div id="menu3" class="tab-pane fade">
											<h3> Bank </h3>
											<div class="form-group col-md-2">
												<label> Instrument Number </label>
												<input type="Number" class="form-control" name="cheque_no" placeholder="Enter Amount">
											</div>
											<div class="form-group col-md-2">
												<label>Bank Name </label>
												<input type="text" class="form-control" name="bank_name" placeholder="Enter Bank Name">
											</div>
										</div>
									</div> -->
									<div class="clearfix"></div>
									<?php

										if ($type=="contra" || $type=="receipt" ) {
											
											
												$db->select("select * from ledger where find_in_set(".getCompanyId().",company_id) and cash='yes' and locked='no'");
												$ledgers = $db->fetch_all();	
											
											if($sale_invoice_row!=''){
												$db->select("select * from ledger where find_in_set(".getCompanyId().",company_id) and id=".intval($sale_return_row['customer_id']));
												$debit_ledger = $db->fetch_all();
												$customer_id = $sale_invoice_row['customer_id'];
												$location = $sale_invoice_row['location_id'];
												$sublocation = $sale_invoice_row['sub_location_id'];
												$installment_amount = $sale_invoice_row['grand_total'];
											}elseif($sale_row!=''){
												//echo "select * from ledger where company_id=".getCompanyId()." and id=".$sale_row['customer_ledger']; die();
												$db->select("select * from ledger where find_in_set(".getCompanyId().",company_id) and id=".$sale_row['customer_ledger']." and locked='no'");
												$ledgers_credit = $db->fetch_all();
												$customer_id = $sale_row['customer_ledger'];
												if($sale_row['type']=='sale'){
													$installment_amount = $sale_row['form_amount'];	
												}else if($sale_row['type']=="transfer"){
													$installment_amount = $sale_row['fee_amount'];	
												}
											}else{
												$db->select("select * from ledger where find_in_set(".getCompanyId().",company_id) and locked='no'");
												$ledgers_credit = $db->fetch_all();	
											}
											

										}else if($type=="payment" || $type=="journal" ){
											$db->select("select * from ledger where find_in_set(".getCompanyId().",company_id)");
											$ledgers = $db->fetch_all();
										}
										if($sale_invoice_row!=''){
											$db->select("select * from ledger where find_in_set(".getCompanyId().",company_id) and id=".$sale_invoice_row['customer_id']." and locked='no'");
											$ledgers_credit = $db->fetch_all();
											$customer_id = $sale_invoice_row['customer_id'];
											if(isset($_REQUEST['s_installment_id']) && intval($_REQUEST['s_installment_id'])>0){
												$sale_amt = $db->fetch_array_by_query("select * from sale_installment_plan where id=".$_REQUEST['s_installment_id']." and sale_invoice_id=".$_REQUEST['sale_id']);
												$installment_amount = $sale_amt['installment_amt'];
											}else{
												$paid_amount = $db->fetch_array_by_query("select v.id, v.type, sum(tr.amount) as total_paid from voucher as v, transactions as tr where v.sale_invoice = ".intval($_REQUEST['sale_id'])." and v.type= 'receipt' and v.id = tr.voucher_id and tr.type = 'debit'");
												$installment_amount = $sale_invoice_row['total_amount'] - $paid_amount['total_paid'];
											}
												
										}
 									?>

 									<div class="table-responsive">
 										<table class="table table-striped credit_table ">
											<thead>
												<!-- <tr style="background-color: #3c8dbc">
													<th style="background-color:#3c8dbc;border: 0px !important;color: white;"></th>
													<th style="border: 0px !important;color: white;padding: 9px 0px;text-align: center;" colspan="4" ><span style="color: white;font-weight: lighter;font-size: 22px;background-color: #3c8dbc;padding: 10px 44px 10px 0px; width: 35%;  margin-right: 150px;">
														 Received From (Credit)
													</span> 
													</th>
												</tr> -->











										<?php 
										
										// if(isset($_REQUEST['fee_credit_ledger']) && intval($_REQUEST['fee_credit_ledger']) > 0){
										// $booking_detail = $db->fetch_array_by_query("select * from pro_payment_booking where id=".$_REQUEST['booked_id']);
										// 	if($booking_detail){
										// 		$location = $booking_detail['head'];
										// 		$sublocation = $booking_detail['sub_head'];
										// 	}
										// }
										?>



												<tr class="title_bg">
													<th style="width: 5%"> Sr_No </th>
													<th style="width: 25%"> Credit Ledger </th>	
													<?php if($check_walk_in['active_walk_in_customer'] == 'yes'){ ?>
														<th style="width: 25%"> Sub Ledger </th>	
													<?php } ?>
													
													<th class="location_td " style="width: 15%"> Head </th>
													<th class="location_td " style="width: 15%"> Sub Head </th>
													<?php if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) > 0) { ?><th style="width: 15%;">Against</th><?php } ?>
													<th style="width: 15%"> Amount </th>
													<th style="width:10%"> Action </th>
												</tr>
												<tr class="paid_bg">
													<th colspan="7" class="paid_th">
														<span>
														 	Received From (Credit)
														</span> 
													</th>
												</tr>
											</thead>
											<tbody class="credit_body">
												<tr class="credit_row">
													<td class="crs_sr_no">1</td>
													<input type="hidden" class="cr_sr_no" name="cr_sr_no[]" value="1">
													<td class="chosen">
														<div class="input-group">
															<select name="ledger_ids[]" class="chosen-select cr_select" onchange="chosenClick(this,'credit')">
																<option value="0">Select Ledger</option>
																<?php foreach( $ledgers_credit as $ledger){?>
																	<option value="<?php echo $ledger['id']?>" <?php if($customer_id == $ledger['id']){echo 'selected';} ?>><?php echo $ledger['name']; ?></option>
																<?php } ?>
															</select>
															<span class="input-group-btn">
																<button class="btn btn-default" type="button" onclick="showLedger()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
														</div>
														<div class="current_balance db_balance" style="width:50%">
															<?php echo '0'; ?>
														</div>
													</td>
													<?php if($check_walk_in['active_walk_in_customer'] == 'yes'){ ?>
													<td class="chosen">
														<div class="form-group">
															<select name="sub_ledger_ids[]" class="chosen-select cr_select" onchange="chosenClick(this,'debit')">
																<option  value="<?php echo $sub_ledger['id']?>"><?php echo $sub_ledger['name']; ?></option>
															</select>
														</div>
													</td>
													<?php } ?>
													
													<td class="location_td ">
														<?php
														$db->Select('select * from item_location where FIND_IN_SET('.getCompanyId().',company_id)');
														$locations=$db->fetch_all();
														?>
														<select class="form-control cr_select chosen-select locations col-xs-6 cr_location_id" onchange="get_sub_location(this)" name="cr_location_id[]">
															<option value="">Select Locations</option>
															<?php
																foreach ($locations as $loc) {
															?>
																	<option <?php if($loc['id']==$location){echo 'selected ';}?> value="<?php echo $loc['id']; ?>"><?php echo $loc['name']; ?></option>
															<?php
																}
															?>
														</select>
													</td>
													<td class="location_td ">
														<select class="form-control cr_sub_id cr_select chosen-select  sublocations col-xs-6 " name="cr_sub_id[]" >
															<?php
															//echo "Select * from item_sublocation where location_id=".intval($location)." and company_id=".getCompanyId();
															$db->Select("Select * from item_sublocation where location_id=".intval($location)." and FIND_IN_SET(".getCompanyId().",company_id)");
															$sublocations = $db->fetch_all();
															foreach ($sublocations as $sublocs) {
															?>
															<option <?php if($sublocs['id']==$sublocation){ echo 'selected'; }?> value="<?php echo $sublocs['id']?>"><?php echo $sublocs['name']?></option>
															<?php
															}
															?>

															<option value="">Select Sublocations</option>
														</select>
													</td>
													<?php if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) > 0) { ?>
													<td class="mat_modal">
														<select onchange="againstValue(this)" name="againstOption[]" class="form-control">
															<option value="">Select Option</option>
															<option value="form">Form</option>
															<option value="Purchase">Purchase</option>
														</select>
													</td>
													<?php } ?>
													<td>
														<div class="form-group">
															<div class="input-group">
																<div class="input-group-addon"> PKR </div>
																<?php //onfocusout="ccostActive(this)"?>
																<input type="Number" name="cr_amount[]" class=" form-control cr_amount"  placeholder="Amount 0.00" onkeyup="getTotalCredit()"  value="<?php echo $installment_amount ?>" style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important">
															</div>
														</div>
														<div class="toggle_button"></div>
													</td>
													<td>
														<a class="btn btn-primary" onclick="add_cr_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i>
														</a>
														<a class="btn btn-danger" onclick="remove_cr_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
														</a>
													</td>
												</tr>
											</tbody>
											<tfoot>
												<tr class="title_bg">
													<td><input type="hidden" name="install_total_amount" class="install_total_amount"></td>
													<td class="location_td "></td>
													<?php if($sale_invoice_row!=''  && $check_walk_in['active_walk_in_customer'] == 'yes'){ ?>
													<td></td>
													<?php } ?>
													<td></td>
													<td>TOTAL (Rs)</td>
													<td class="cr_total"></td>
													<td></td>
												</tr>
											</tfoot>
										</table>
 									</div>
<?php /*
 									<!-- Start of the new data -->
									<div class=" form-group col-md-3">
										<label for="date"> Received From (Credit) </label>
										<div class="chosen">
											<div class="input-group">
												<select name="ledger_ids[]" class="chosen-select cr_select" onchange="chosenClick(this,'debit')">
													<option value="0">Select Ledger</option>
													<?php foreach( $ledgers_credit as $ledger){?>
													<option value="<?php echo $ledger['id']?>"><?php echo $ledger['name']; ?></option>
														<?php } ?>
												</select>
												<span class="input-group-btn">
													<button class="btn btn-default" type="button"  onclick="showLedger()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
												</span>
											</div>
											<div class="current_balance db_balance hidden" style="width:50%">
												<?php echo '0'; ?>
											</div>
										</div>
									</div>
									<div class='col-md-2'>
										<label for="date"> Credit Amount </label>
										<div class="form-group">
											<div class="input-group">
												<div class="input-group-addon"> PKR </div>
												<input type="Number" name="cr_amount[]" class=" form-control cr_amount" onfocusout="ccostActive(this)" placeholder="Amount 0.00" style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important">
											</div>
										</div>
									</div>*/?>
		 							<div class="table-responsive" style="margin-top: 10px">
										<table class="table table-striped debit_table">
											<thead style="background-color: #eee">
												<!-- <tr style="background-color: #3c8dbc">
													<th style="background-color:#3c8dbc;border: 0px !important;color: white;"></th>
													<th style="border: 0px !important;color: white;padding: 9px 0px;text-align: center;" colspan="7"><span style="color: white;font-weight: lighter;font-size: 22px;background-color: #3c8dbc;padding: 10px 44px 10px 0px; width: 35%">
														 DEPOSIT TO (DEBIT)
													</span> 
													</th>
												</tr> -->
												<tr class="title_bg">
													<th style="width: 1% "> Sr #. </th>
													<th style="width: 10% "> Payment Mode</th>
													<th style="width: 15% "> Deposit To (Debit) </th>
													<th style="width: 15% "> Bank </th>
													<th style="width: 15% "> Instrument No. </th>
													<th style="width: 15% "> Date </th>
													<th style="width: 20% "> Amount </th>
													<th style="width: 9% "> Action </th>
												</tr>
												<tr class="paid_bg">
													<th colspan="100%" class="paid_th">
														<span>
														 	DEPOSIT TO (DEBIT)
														</span> 
													</th>
												</tr>
											</thead>
												<tbody class="debit_body">
													<tr class="debit_row">
														<td class="sr_no">1</td>
														<td>
															<select name="payment_mode[]" class="dr_ledger_type form-control" onchange="getLedgers(this,'debit')">
																<option value="all">All</option>
																<option value="cash" selected>Cash</option>
																<option value="bank">Bank</option>
																<option value="cheque">Cheque</option>
																<option value="online">Online</option>
																<option value="mobile">Mobile</option>
																<option value="form_return">Form Return</option>
															</select>
														</td>
														<input type="hidden" class="dr_sr_no" name="dr_sr_no[]" value="1">
														<td class="chosen">
															<div class="input-group">
																<select name="db_ledger_ids[]" class="chosen-select app_select db_select" onchange="chosenClick(this,'debit')">
																		<option value="0">Select Ledger</option>


																		<?php foreach( $ledgers as $ledger){?>
																			<option value="<?php echo $ledger['id']?>"><?php echo $ledger['name']; ?></option>
																	<?php } ?>
																</select>
																<span class="input-group-btn">
																	<button class="btn btn-default" type="button" onclick="showLedger()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
																</span>
															</div>
															<div class=" current_bank">
																
															</div>
															<div class="current_balance db_balance" style="width:50%">
																<?php echo '0'; ?>
															</div>
													</td>
													<td>
														<!-- <input type="text" placeholder=" Bank " class="form-control bank_data" name="bank[]" disabled=""> -->


														<input type="text" placeholder=" Bank " class="form-control bank_data" name="bank[]" disabled="">
														<?php $db->select("select * from banks"); 
														$banks = $db->fetch_all();?>
														<select style="display:none" class="form-control bank_data_select" >
														<?php foreach($banks as $bank){ ?>
															<option value="<?php echo $bank['abbr']; ?>"><?php echo $bank['name']; ?></option>
														<?php } ?>
														</select>

													</td>
													<td>
														<input type="Number" placeholder=" Instrument No. " class="form-control instrument_no" name="instrument_no[]" disabled>
													</td>
													<td>
													<input type="text" class="form-control bank_date" placeholder="Date" value="<?php echo date('d-m-Y') ?>" autocomplete="off" name="bank_date[]" disabled>
												</td>
												<td>
													<span class="form-group">
														<span class="input-group">
															<span class="input-group-addon"> PKR </span>
															<input type="Number" name="db_amount[]" class="form-control dr_amount debit_show" placeholder="Amount 0.00" onkeyup="getTotalDebit(this)" style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important" value="<?php echo $installment_amount ?>">
														</span>
													</span>
												</td>
												<td class="hidden">
													<span class="form-group">
														<span class="input-group">
															<span class="input-group-addon"> PKR </span>
															<input type="Number"placeholder="Enter Amount 0.00"  class="form-control" disabled="" style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important">
														</span>
													</span>
												</td>
												<td>
													<a class="btn btn-primary" onclick="add_row()"><i class="fa fa-plus-circle" aria-hidden="true"></i>
													</a>
													<a class="btn btn-danger" onclick="remove_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
													</a>
												</td>
											</tr>
											</tbody>
											<tfoot>
												<tr class="title_bg">
													<td></td>
													<td></td>
													<td></td>
													<td></td>
													<td class="cr_total hidden"> 0.00 </td>
													<td></td>
													<td class="f_total"> Total (RS) </td>
													<td class="dr_total"> 0.00 </td>
													<td></td>
												</tr>
												<tr style="text-align: center; color: rgb(244, 244, 244); background-color: rgb(132, 130, 138); display: table-row;" class="charge hidden">
													<td colspan="1"></td>
													<td> Payment Mode </td>
													<td colspan="2"> Credit Ledger </td>
													<td colspan="2"> Debit Ledger </td>
													<td> Amount </td>
													<td></td>
												</tr>
												<tr>
													<td colspan="1">
														<div class="Show_Payment Charges">
															<div class="form-check">
																<input class="form-check-input showCharges" onclick="getCharges(this)" id="cash" type="checkbox" >
																<label class="form-check-label" for="cash"> Charges </label>
															</div>
														</div>
													</td>
													<td class="charge hidden">
														<select name="payment_mode_charges" class="dr_ledger_type form-control" onchange="getLedgers(this,'credit')">
															<option value="all">All</option>
															<option value="cash" selected>Cash</option>
															<option value="bank">Bank</option>
														</select>
													</td>
													<td colspan="2" class="chosen charge hidden">
														<div class="input-group" style="width: 100%">
															<select name="credit_charges_ledger[]" class="chosen-select form-control crs_select " onchange="chosenClick(this,'debit')">
																	<option value="0">Select Ledger</option>
																	<?php foreach( $ledgers as $ledger){?>
																		<option value="<?php echo $ledger['id']?>"><?php echo $ledger['name']; ?></option>
																<?php } ?>
															</select>
															<span class="input-group-btn">
																<button class="btn btn-default" type="button" onclick="showLedger()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
														</div>
														<div class=" current_bank">
															
														</div>
														<div class="current_balance db_balance" style="width:50%">
															<?php echo '0'; ?>
														</div>
													</td>
													<td colspan="2" class="chosen charge hidden">
														<div class="input-group" style="width: 100%">
															<select name="charges_ledger[]" class="chosen-select cr_select" onchange="chosenClick(this,'debit')">
																<option value="0">Select Ledger</option>
																<?php $db->select("select * from ledger where company_id=".getCompanyId()." and under_group=27");
																$paymentLedger = $db->fetch_all();
																?>
																<?php foreach($paymentLedger as $paymentLedgers){?>
																<option value="<?php echo $paymentLedgers['id']?>"><?php echo $paymentLedgers['name']; ?></option>
																	<?php } ?>
															</select>
															<span class="input-group-btn">
																<button class="btn btn-default" type="button"  onclick="showLedger()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
														</div>
														<div class="current_balance db_balance hidden" style="width:50%">
															<?php echo '0'; ?>
														</div>
													</td>
													<td class="charge hidden">
														<span class="input-group">
															<span class="input-group-addon"> PKR </span>
															<input type="text" class="form-control charge" onkeyup="getTotalDebit(this)" id="chargeValue" name="charges_value" placeholder=" Enter Charges " style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important">
														</span>
													</td>
												</tr>
												<tr style="text-align:center;background-color:#d2d6de;" class="main_footer hidden">
													<td></td>
													<td></td>
													<td></td>
													<td></td>
													<td class="cr_total hidden"> 0.00 </td>
													<td></td>
													<td>TOTAL (Rs)</td>
													<td class="dr_charge_total"> 0.00 </td>
													<td></td>
												</tr>
											</tfoot>
										</table>
										<table class="table table-striped credit_table hidden">
											<thead>
												<tr>
													<th style="background-color:#3c8dbc;border: 0px !important;color: white; width: 5%"></th>
													<th style="border: 0px !important;color: white;padding: 9px 0px;"><span style="color: white;font-weight: lighter;font-size: 18px;background-color: #3c8dbc;padding: 10px 44px 10px 0px; width: 35%">
													<?php if ($type=='payment') {?>
														<?php echo " Paid From Credit ";?>
													<?php }elseif ($type=='receipt') {?>
														<?php echo " Received From Credit ";?>
													<?php }else{
														echo "Credit";
													} ?></span> 
													</th>
													<th style="width: 25%"></th>
													<th style="width: 25%"></th>
													<th style="width: 10%"></th>
													<!-- <th style="width: 14%"></th> -->
													<!-- <th style="width: 10%"></th> -->
												</tr>
											</thead>
											<?php /*
											<tbody class="credit_body">
												<tr class="credit_row">
													<td class="crs_sr_no">1</td>
													<input type="hidden" class="cr_sr_no" name="cr_sr_no[]" value="1">
													
													<td class="chosen">
														<div class="input-group">
															<select name="ledger_ids[]" class="chosen-select cr_select" onchange="chosenClick(this,'debit')">
																<option value="0">Select Ledger</option>
																<?php foreach( $ledgers as $ledger){?>
																	<option value="<?php echo $ledger['id']?>"><?php echo $ledger['name']; ?></option>
																<?php } ?>
															</select>
															<span class="input-group-btn">
															<button class="btn btn-default" type="button" onclick="showLedger()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
														</div>
														<div class="current_balance db_balance" style="width:50%">
															<?php echo '0'; ?>
														</div>
														<!-- <div class="form-check cheque_book" style="display:none">
														<label>Cheque book</label><br>
														<div class="clearfix"></div>
														<div class="col-xs-12 no-gutter">
															<label> Checkbook:</label>
															<br>
															<input type="number" class="form-control checque" name="start" placeholder="Enter Checkbook ">
															<label>End:</label>
															<input type="number" name="end">		
														</div>
													</div> -->
													</td>
<!-- 													<td>
														<textarea class="form-control" name="cr_description[]" style="height:34px" placeholder="Description"></textarea>
													</td> -->
													<td>
														<span class="form-group">
															<span class="input-group">
																<span class="input-group-addon"> PKR </span>
																<input type="Number"placeholder="Enter Amount 0.00" class="form-control" disabled="" style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important">
															</span>
														</span>
													</td>
													<td>
														<span class="form-group">
															<span class="input-group">
																<span class="input-group-addon"> PKR </span>
																<input type="Number"placeholder="Enter Amount 0.00" name="cr_amount[]" class=" form-control cr_amount_hidden" value="0" onfocusout="ccostActive(this)" style="border-top-left-radius: 0px !important;border-bottom-left-radius: 0px !important">
															</span>
														</span>
													</td>
													<!-- <td>
														<input type="Number"placeholder="Enter Chargers" class="form-control">
													</td> -->
													<td>
														<a class="btn btn-primary" onclick="add_cr_row()"><i class="fa fa-plus-circle" aria-hidden="true"></i>
														</a>
														<a class="btn btn-danger" onclick="remove_cr_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
														</a>
													</td>
												</tr>
											</tbody>
											*/?>
											<tfoot>
												<tr class="title_bg">
													<td></td>
													<td>TOTAL (Rs)</td>
													<td class="dr_total"> 0.00 </td>
													<td class="cr_total hidden"> 0.00 </td>
													<td></td>
													<!-- <td></td> -->
												</tr>
											</tfoot>
										</table>
									</div>
									<br>
									<div class="col-md-6">
										<div class="form-group">
											<textarea name="narration" placeholder="Memo" class="form-control" style="border-radius: 1.375rem !important"></textarea>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group" style="border:2px #d2d6de dotted;border-radius:15px;"><input type="file" class="text-center" name="attachment[]" style="margin: 0px auto; padding:17px" id="inp_file" multiple="">
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="main_button" style="text-align: right;">
										<button type="button" onclick="addVoucher()" class="btn btn-primary">Save Voucher</button>
										<button type="button" class="btn btn-danger"> Close Voucher</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section><!-- /.content -->
				<!-- CREDIT COST CENTER MODAL-->
				<div class="modal fade myModal ccostCenterModal"  data-keyboard="false" data-backdrop="static" role="dialog">
					<div class="modal-dialog modal-lg">
						<!-- Modal content-->
						<div class="modal-content ">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal">&times;</button>
								<h4 class="modal-title"> Credit Cost Center</h4>
							</div>
							<div class="modal-body">
								<div class="clearfix"></div>
								<div class="row row_cost_center">
									<input type="hidden" class="totalBalance" value="0">
									<div class="add_cost_voucher">
										<div class=" form-group col-lg-3 ">
											<label class="myLabel" for="cost-category">Cost Category</label>
											<select class="form-control input-group ccost_category" onchange="selectCategory(this)" name="ccost_category[]">
												<?php
												$cost=$obj_group->getAllRecords();
												?>
												<option value="0"> Select Cost Category </option>
												<?php foreach($cost as $costs){ ?>
												<option value="<?php echo $costs['id']?>" class="cost_group"><?php echo $costs['name']?></option>
												<?php } ?>
											</select>
										</div>
										<div class=" form-group col-lg-3">
											<label class="myLabel" for="cost-center"><b>Cost Center</b></label>
											<select class="form-control ccost_center" name="ccost_center[]">
												<?php
													$db->select("select * from cost_center where company_id=".getCompanyId());
													$center=$db->fetch_all();
												?>
												<option value="0"> Select Cost Center </option>
												<?php foreach($center as $centers){ ?>
													<option value="<?php echo $centers['id']?>" class="under_group parent<?php echo $centers['parent_id']?>"><?php echo $centers['name']?>
													</option>
												<?php } ?>
											</select>
										</div>
										<div class="form-group col-lg-3">
											<label  class="myLabel" for="cost"><b>Cost</b></label>
											<input type="text" placeholder=" " class="form-control ccost_center_cost" name="ccost_center_cost[]">
										</div>
										<div class="form-group col-lg-3">
											<label  class="myLabel" style="display: block;" ><b>Action</b></label>
											<button type="button" class="btn btn-primary"  onclick="AddCcostRow(this)"><i style="padding:0px;"class="fa fa-plus-circle" aria-hidden="true"></i></button>
											<button onclick="removeCostRow(this)" type="button" class="btn btn-danger"><i class="fa fa-minus-circle" aria-hidden="true"></i></button>
										</div>
										<div class="clearfix"></div>
									</div>
								</div>
								<div class="clearfix"></div>
								<div class="col-md-4 col-lg-3 ledger-button">
									<div>&nbsp;</div>		
								</div>
								<div class="modal-footer">
								</div>
							</div>
						</div>
					</div>
					<div class="clearfix"></div>
				</div>
			<!--END OF CREDIT COST CENTER MODAL-->
			<!--Cost Center Modal-->
				<div class="modal fade myModal costCenterModal"  data-keyboard="false" data-backdrop="static" role="dialog">
					<div class="modal-dialog modal-lg">
						<!-- Modal content-->
						<div class="modal-content ">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal">&times;</button>
								<h4 class="modal-title"> Debit Cost Center</h4>
							</div>
							<div class="modal-body">
								<div class="clearfix"></div>
								<div class="row row_cost_center">
									<div class="add_cost_voucher">
										<div class=" form-group col-lg-3 ">
											<label class="myLabel" for="cost-category">Cost Category</label>
											<select class="form-control input-group cost_category" onchange="selectCategory(this)" name="cost_categroy[]">
												<?php
												$cost=$obj_group->getAllRecords();
													// $db->select("select * from cost_category");
													// $cost=$db->fetch_all();
												?>
												<option value="0"> Select Cost Category </option>
												<?php foreach($cost as $costs){ ?>
												<option value="<?php echo $costs['id']?>" class="cost_group"><?php echo $costs['name']?></option>
												<?php } ?>
											</select>
										</div>
										<div class=" form-group col-lg-3">
											<label class="myLabel" for="cost-center"><b>Cost Center</b></label>
											<select class="form-control cost_center" name="cost_center[]">
												<?php
													$db->select("select * from cost_center where company_id=".getCompanyId());
													$center=$db->fetch_all();
												?>
												<option value="0"> Select Cost Center </option>
												<?php foreach($center as $centers){?>
													<option value="<?php echo $centers['id']?>" class="under_group parent<?php echo $centers['parent_id']?>"><?php echo $centers['name']?>
													</option>
												<?php } ?>
											</select>
										</div>
										<div class="form-group col-lg-3">
											<label class="myLabel" for="cost"><b>Cost</b></label>
											<input type="text" placeholder=" " class="form-control cost_center_cost" name="cost_center_cost[]">
										</div>
										<div class="form-group col-lg-3">
											<label  class="myLabel" style="display: block;" ><b>Action</b></label>
											<button  type="button" class="btn btn-primary" onclick="AddCostRow(this)" ><i style="padding:0px;"class="fa fa-plus-circle" aria-hidden="true"></i></button>
											<button onclick="removeCostRow(this)" type="button" class="btn btn-danger"><i class="fa fa-minus-circle" aria-hidden="true"></i></button>
										</div>
										<div class="clearfix"></div>
									</div>
								</div>
								<div class="clearfix"></div>
								<div class="col-md-4 col-lg-3 ledger-button">
									<div>&nbsp;</div>
								</div>
								<div class="modal-footer"></div>
							</div>
						</div>
					</div>
					<div class="clearfix"></div>
				</div>
			<!--END  OF COST CENTER MODAL -->
			</form>
			<!--LEDGER MODAL CONTENT-->
			<?php include("includes/ledger.php");?>
			<!-- END OF LEDGER MODAL CONTENT-->

			<!--SETTINGS MODAL CONTENT-->
			<div id="settingModal" class="modal fade" role="dialog">
				<div class="modal-dialog  modal-purchase-ledger">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal">&times;</button>
							<h3 class="modal-title"> Add Settings </h3>
						</div>
						<div class="modal-body">
							<form method="post" enctype="multipart/form-data" id='formLedger'>
								<input type="hidden" name="type" value="<?php echo $type ?>">
								<div class="box box-danger">
									<div class="box-header">
										<h3 class="box-title pull-left"> Cash Settings </h3>
									</div>
									<div class="box-body vl1">
										<div class="" style="margin-bottom:10px">
											<div class="row">
												<div class="col-md-10">
													<select class="form-control">
														<?php 
														$db->select("select name from ledger ");
														$cashLedgers = $db->fetch_all();
														foreach ($cashLedgers as $cashLedger) {?>
															<option> Select An Option </option>
															<option><?php echo $cashLedger['name'] ?></option>
														<?php }?>
													</select>
												</div>
												<!-- <div class="col-sm-5">
													<div class="form-group">
														<label> Ledger Name:</label>
														<input type="text" class="form-control" placeholder="Name" name="name">
													</div>
												</div> -->
												
												<!-- <div class="clearfix"></div> -->
												<!-- <div class="col-md-4 col-lg-2">
													<div class="form-group ledger-button">
														<div>&nbsp;</div>
														<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add"  onclick="addLedger()">Save Ledger</button>
													</div>
												</div> -->
											</div>
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="ledger-loading" style='display:none; '>
										<div>&nbsp;</div>
										<img src="<?php echo BASE_URL.'images/loading.gif'?>" style="height: 100px;">
									</div>
								</div>
							</form>
						</div>
						<div class="clearfix"></div>
					</div>
					<div class="modal-footer"></div>
				</div>
			</div>
			<!-- END OF SETTINGS MODAL CONTENT-->
			<!---- START AGAINST MODAL ---->
			<div class="modal fade form_modal" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
				<div class="modal-dialog modal-md" role="document" style="width: 90%">
					<div class="modal-content  modal-rate" style="width: 100%; margin: 0 auto">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close">
								<span aria-hidden="true">&times;</span>
							</button>
							<h2 class="modal-title" id="exampleModalLabel"> Against Form </h2>
						</div>
						<div class="row clearfix">
							<div class="span12">
								<?php echo $imsg->getMessage();?>
							</div>
						</div>
						<div class="modal-body">
							<div class="box box-danger">
								<div class="box-body">
								<div class="col-md-12">
									<div class="row text-left" style="margin-top:20px">
										<div class="col-md-12 no-gutter">
											<div class="col-md-3">
												<label>Forms</label>
												<!-- <input type="text" class="form-control form_no" placeholder="Enter Form No" > -->
												<select  onchange="SelectCustomer(this)"  class="form-control chosen-select form_no">
													<option value="">Select Form</option>
													<?php 
													// $sql = 'select pfi.* from pro_form_sale_detail as pfd left join pro_form_sale as pfs on pfd.form_sale_id = pfs.id left join pro_form_inventory as pfi on pfd.frm_id = pfi.id left join ledger as l on pfd.customer_ledger = l.id where (pfs.type="sale" or pfs.type="transfer") and pfs.locked ="no" and l.active_customer ="yes"';
													$sql = 'select pfi.*,pfd.customer_ledger as customer  from pro_form_sale_detail as pfd left join pro_form_sale as pfs on pfd.form_sale_id = pfs.id left join pro_form_inventory as pfi on pfd.frm_id = pfi.id left join ledger as l on pfd.customer_ledger = l.id where (pfs.type="sale" or pfs.type="transfer") and pfs.locked ="no"  and pfi.balloting = "no"  and l.id!=3419';
													$db->Select($sql);
													$forms = $db->fetch_all();
													foreach($forms as $form){
													?>
													<!-- <option value="<?php echo $form['registration_no']; ?>"><b><?php echo $form['registration_no'];?></b> ( <?php echo $form['marla'].' '.$form['unit']; ?> ) </option> -->
													<option data-value="<?php echo $form['customer']; ?>" value="<?php echo $form['registration_no']; ?>"><b><?php echo $form['registration_no'];?></b> ( <?php echo $form['marla'].' '.$form['unit']; ?> ) </option>
													<?php
													}
													?>
												</select>
											</div>
											<div class="col-md-3">
												<label>Customer</label>
												<select class="form-control chosen-select customer_form">
													<option value="">Select Customer</option>
													<?php 
													$db->Select("SELECT DISTINCT l.* FROM ledger as l left join pro_form_sale as pfs on pfs.customer_ledger = l.id where (pfs.type='sale' or pfs.type='transfer') and pfs.locked='no' and l.active_customer = 'yes'");
													$customers  = $db->fetch_all();
													foreach($customers as $customer){
													?>
													<option value="<?php echo $customer['id']; ?>"><?php echo $customer['name'];?></option>
													<?php
													}
													?>
												</select>
											</div>
											<div class="col-md-3">
												<label>Type</label>
												<select class="form-control chosen-select form_type">
													<option value="">Select Type</option>
													<option value="5">5 Marla </option>
													<option value="7">7 Marla </option>
													<option value="10">10 Marla </option>
													<option value="20">1 Kanal</option>
												</select>
											</div>
											<div class="col-md-3 btn_div">
											</div>
											<div class="clearfix"></div>
										</div>
									</div>
								</div>
								<br>
								<br>
								<div class="col-md-12 no-gutter">
									<div class="col-md-7">
										<div class="col-md-10">
										<div class="table-responsive" style="margin-top:30px">
											<table class="table adjustable_form table-striped" style="border: 1px solid #ddd;">
												<thead ><tr><th colspan="6">Adjustable Forms</th></tr></thead>
												<thead>
													<tr>
														<th style="width: 5%;text-align: center">Adjust</th>
														<th style="text-align: center">Form No. </th>
														<th style="text-align: center">Owner</th>
														<th style="text-align: center">Type</th>
														<th style="text-align: center">Status</th>
														<th style="text-align: center">Amount</th>	
													</tr>
												</thead>
												<tbody class="receipt_body">
													<tr class="receipt_row">
														<td colspan="6"> Customer Has No Form</td>														
													</tr>
													<tfoot>
														<td></td>
														<td></td>
														<td> Total Amount : </td>
														<td class="sum_of_form_amount"> </td>
														<td> <input type="hidden" class="sum_of_form_amount_inp"></td>
													</tfoot>
												</tbody>
											</table>
										</div>
										</div>
										<div class="col-md-2">
											<div class="buttons_div" style="margin-top:100px">
											</div>
										</div>
									</div>
									<div class="col-md-5" style="padding-left: 0;">
									<div class="table-responsive" style="margin-top:30px">

										<table class="table adjusted_forms table-striped" style="border: 1px solid #ddd;">
											<thead><tr><th colspan="6">Adjusted Forms</th></tr></thead>
											<thead>
												<tr>
													<th style="width: 5%;text-align: center">Revese</th>
													<th style="text-align: center">Form No. </th>
													<th style="text-align: center">Owner</th>
													<th style="text-align: center">Type</th>
													<th style="text-align: center">Status</th>
													<th style="text-align: center">Amount</th>
												</tr>
											</thead>
											<tbody class="receipt_body">
												<tr class="receipt_row">
													<td colspan="6">Select Form To Adjust</td>
												</tr>
												<tfoot>
													<td></td>
													<td></td>
													<td> Total Amount : </td>
													<td class="sum_of_form_amount"> </td>
													<td> <input type="hidden" class="sum_of_form_amount_inp"></td>
												</tfoot>
											</tbody>
										</table>
									</div>
									</div>
								</div>
									<div class="form-button" style="margin:10px;text-align:right"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-----END AGAINST MODAL ----->
		</div>
	</div>
		<?php include("includes/footer.php");?>
		<!-- Add the sidebar's background. This div must be placed
	   immediately after the control sidebar -->
		<div class='control-sidebar-bg'></div>
	</div><!-- ./wrapper -->
	<?php include("includes/footer-jsfiles.php");?>

	<!--Here are script session-->
	<script type="text/javascript">
// 		$(document).ready(function(){
//  var selectedCountry = '';
//  selectedCountry = $("select.dr_ledger_type"). children("option:selected").val("cash");
//  if (selectedCountry == 'cash') {
//  	alert("yes");
//  }
// });

	</script>
<script type="text/javascript">
	function goBack() {
		window.history.back();
	}
</script>
<script>













	function selectPayment(val_this){
		selected = $(val_this).val();
		window.location.href="new-credit-voucher.php?payment="+selected;

	}
	<?php if ($_REQUEST['booking_id'] > 0) { ?>
		getTotalCredit();
	<?php } ?>
	$(document).ready(function(){
		var date_input=$('.bank_date'); //our date input has the name "date"
		var container=$('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";
		date_input.datepicker({
			format: 'dd-mm-yyyy',
			container: container,
			todayHighlight: true,
			autoclose: true,
		});
		$(".showLocation").click();
	})
	function selectCategory(val_this){

		category = $(val_this).val();
		$(".under_group").hide();
		$(".parent"+category).show();
	}

	function saveCostCenter(value,modal_no){
		total=0;
		main_val = parseFloat(value);
		$(modal_no+" .cost_center_cost").each(function(index, el) {
			if($(this).val()!=''){
				total += parseInt($(this).val());
			}
		});
		
		if(main_val!=total){
			alert("Please Enter Correct Amount "+main_val);
		}else {
			val = 0;
			modal_hide_count = 0;
			// $(modal_no+" .cost_category").each(function(index, el) {
			// 		//console.log("yes");
			// 		if(val == parseFloat($(el).val())){
			// 			modal_hide_count++;
			// 			alert("Category selected more than one time");
			// 			return false;
						
			// 		}
			// 		val  = parseFloat($(el).val());

			// });
			//console.log(modal_hide_count);
			if(modal_hide_count==0){
				$(modal_no).modal('hide');									
			}
			

		}
	}

	function saveCcostCenter(value,modal_no){
		total=0;
		main_val = parseFloat(value);
		$(modal_no+" .ccost_center_cost").each(function(index, el) {
			if($(this).val()!=''){
				total += parseInt($(this).val());
			}
		});
		if(main_val!=total){
			alert("Please Enter Correct Amount"+main_val);
		}else {
			val = 0;
			modal_hide_count = 0;
			// $(modal_no+" .ccost_category").each(function(index, el) {
			// 	if(val == parseFloat($(el).val())){
			// 		modal_hide_count++;
			// 		alert("Category selected more than one time");
			// 		return false;
			// 	}
			// 	val  = parseFloat($(el).val());
			// });
			if(modal_hide_count==0){
				$(modal_no).modal('hide');									
			}
		}
	}



		function ccostActive(val_this){
			cost_val = $(val_this).parent().parent().parent().parent().find(".input_cost").val();
			dr_cr_val =  parseFloat($(val_this).val());
			sr_no = $(val_this).parent().parent().parent().parent().find(".crs_sr_no").html();
			$(val_this).parent().parent().find(".input_cost").attr('name', 'c_cost_active[]');
			var loc_text= $(val_this).closest('tr').find(".locations option:selected").text();
			var loc_id=$(val_this).closest('tr').find(".locations").val();
			 $('.ccostCenterModal'+sr_no).remove();	
			if(cost_val=='yes'&& dr_cr_val>0 ){
				$('.costCenterModal'+sr_no).remove();
				$(".hrefc-"+sr_no).remove();
				$(val_this).parent().parent().find('.modal-href').remove();
				modal_no = '.ccostCenterModal'+sr_no;
					modal = $(".ccostCenterModal:first").clone();
					$("#form1").append(modal);
					$(".ccostCenterModal:last").addClass('ccostCenterModal'+sr_no);
					$('.ccostCenterModal'+sr_no).removeClass('ccostCenterModal');
					$('.ccostCenterModal'+sr_no+" .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add" onclick=saveCcostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
					// Next step is to send the amount input total amount to its cost center modal for getting the remaining balance if we add next row
					$('.ccostCenterModal'+sr_no+" .totalBalance").val(dr_cr_val);
					$('.ccostCenterModal'+sr_no).modal('show');
					changeCcName(modal_no,sr_no);
					href_class = "hrefcc-"+sr_no;
					$(val_this).parent().parent().append('<a data-toggle="modal" data-md="'+modal_no+'"   class="modal-href md-btn '+href_class+'" data-keyboard="false" data-backdrop="static">Cost Center</a>');
					// $('.ccostCenterModal'+sr_no).find('.ccost_center_cost').val(dr_cr_val);
					$(".ccostCenterModal"+sr_no).find('.balance_show').append('<b>Rs '+$(val_this).val()+'</b>');
					$('.ccostCenterModal'+sr_no).find(".location_sec").remove();
					$('.ccostCenterModal'+sr_no).find(".modal-body .row .add_cost_voucher").prepend('<div class="col-lg-3 location_sec pull-left"><label>Location</label><input type="text" class="form-control" value="'+loc_text+'" readonly ><input type="hidden" value="'+loc_id+'"></div>')
				/*if($('.ccostCenterModal'+sr_no).html()!=undefined){
					$('.ccostCenterModal'+sr_no+" .ledger-button").html('');
					$('.ccostCenterModal'+sr_no+" .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add" onclick=saveCcostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
					$('.ccostCenterModal'+sr_no).modal('show');
					$('.ccostCenterModal'+sr_no+" .totalBalance").val(dr_cr_val);
				// $('.ccostCenterModal'+sr_no).find('.ccost_center_cost').val(dr_cr_val);
				}else{
					modal_no = '.ccostCenterModal'+sr_no;
					modal = $(".ccostCenterModal:first").clone();
					$("#form1").append(modal);
					$(".ccostCenterModal:last").addClass('ccostCenterModal'+sr_no);
					$('.ccostCenterModal'+sr_no).removeClass('ccostCenterModal');
					$('.ccostCenterModal'+sr_no+" .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add" onclick=saveCcostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
					// Next step is to send the amount input total amount to its cost center modal for getting the remaining balance if we add next row
					$('.ccostCenterModal'+sr_no+" .totalBalance").val(dr_cr_val);
					$('.ccostCenterModal'+sr_no).modal('show');
					changeCcName(modal_no,sr_no);
					href_class = "hrefcc-"+sr_no;
					$(val_this).parent().parent().append('<a data-toggle="modal" data-md="'+modal_no+'"   class="modal-href md-btn '+href_class+'" data-keyboard="false" data-backdrop="static">Cost Center</a>');
					// $('.ccostCenterModal'+sr_no).find('.ccost_center_cost').val(dr_cr_val);
					$(".ccostCenterModal"+sr_no).find('.balance_show').append('<b>Rs '+$(val_this).val()+'</b>');
				}*/
			}else{
				$(val_this).parent().parent().find('.modal-href').remove();
				$('.ccostCenterModal'+sr_no).remove();
			}
		}




	// function ccostActive(val_this){
		/*
		cost_val = $(val_this).parent().parent().parent().parent().find(".input_cost").val();
		dr_cr_val =  parseFloat($(val_this).val());
		sr_no = $(val_this).parent().parent().parent().parent().find(".crs_sr_no").html();
		$(val_this).parent().parent().find(".input_cost").attr('name', 'c_cost_active[]');	
			if(cost_val=='yes'&& dr_cr_val>0 ){
				// $('.ccostCenterModal').remove();
				// 	$(".hrefcc-").remove();
				if($('.ccostCenterModal').html()!=undefined){
					modal_no = '.ccostCenterModal';
					href_class = "hrefcc-";
					$('.ccostCenterModal .ledger-button').html('');
					$(".ccostCenterModal .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add" onclick=saveCcostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
					if($(".modal-href").text()==''){
						$(val_this).parent().parent().append('<a data-toggle="modal" data-md="'+modal_no+'"  class="modal-href md-btn '+href_class+'" data-keyboard="false" data-backdrop="static"> Cost Center </a>');
						
					}
					$('.ccostCenterModal').modal('show');
					
				}else{
					modal_no = '.ccostCenterModal';
					modal = $(".ccostCenterModal:first").clone();
					$("#form1").append(modal);
					$(".ccostCenterModal:last").addClass('ccostCenterModal');
					$('.ccostCenterModal').removeClass('ccostCenterModal');
					$(".ccostCenterModal .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add" onclick=saveCcostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
					$('.ccostCenterModal').modal('show');
					//changeCcName(modal_no,sr_no);
					href_class = "hrefcc-";
					$(val_this).parent().parent().parent().append('<a data-toggle="modal" data-md="'+modal_no+'"   class="modal-href md-btn '+href_class+'" data-keyboard="false" data-backdrop="static">Cost Center</a>');
				}
				/*modal_no = '.ccostCenterModal';
				$('.ccostCenterModal .ledger-button').append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add" onclick=saveCcostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
				$('.ccostCenterModal').modal('show');
				href_class = "hrefcc";
				$(val_this).parent().parent().append('<a data-toggle="modal" data-md="'+modal_no+'"   class="modal-href md-btn '+href_class+'" data-keyboard="false" data-backdrop="static">Cost Center</a>');*/
		// 	}else{
		// 		$('.ccostCenterModal'+sr_no).remove();
		// 	}
		// 	*/
		// }
		

	function costActive(val_this){
		
		cost_val = $(val_this).parent().parent().parent().parent().find(".input_cost").val();
		// console.log(cost_val + 'go');
		dr_cr_val = parseFloat($(val_this).val()); 
		// console.log(dr_cr_val + 'value');
		sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
		// console.log(sr_no + 'sr');
		var loc_text= $(val_this).closest('tr').find(".locations option:selected").text();
		var loc_id=$(val_this).closest('tr').find(".locations").val();
		$(val_this).parent().parent().find(".input_cost").attr('name', 'cost_active[]');
		if(cost_val=='yes' && dr_cr_val>0 ){
			//$('.ccostCenterModal'+sr_no).remove();
			//$(".hrefcc-"+sr_no).remove();
			
			if($('.costCenterModal'+sr_no).html()!=undefined){
				$('.costCenterModal'+sr_no+" .ledger-button").html('');
				$('.costCenterModal'+sr_no+" .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add"  onclick=saveCostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
				$('.costCenterModal'+sr_no).modal('show');	
				$('.costCenterModal'+sr_no).find(".location_sec").remove();
				$('.costCenterModal'+sr_no).find(".modal-body .row .add_cost_voucher").prepend('<div class="col-lg-3 location_sec pull-left"><label>Location</label><input type="text" class="form-control" value="'+loca_text+'" readonly ><input type="hidden" value="'+loc_id+'"></div>')
			}else{
				modal_no = '.costCenterModal'+sr_no;
				modal = $(".costCenterModal:first").clone();
				$("#form1").append(modal);
				$(".costCenterModal:last").addClass('costCenterModal'+sr_no);
				$('.costCenterModal'+sr_no).removeClass('costCenterModal');
				$('.costCenterModal'+sr_no+" .ledger-button").append('<button type="button" class="btn btn-primary pull-right btn-block" name="command" value="Add"  onclick=saveCostCenter('+dr_cr_val+',"'+modal_no+'")>Save</button>');
				$('.costCenterModal'+sr_no,).modal('show');
				changeName(modal_no,sr_no);
				href_class = "hrefc-"+sr_no;
				$(val_this).parent().parent().append('<a data-toggle="modal" data-md="'+modal_no+'"  class="modal-href md-btn '+href_class+'">Cost Center</a>');
				$('.costCenterModal'+sr_no).find(".location_sec").remove();
				$('.costCenterModal'+sr_no).find(".modal-body .row .add_cost_voucher").prepend('<div class="col-lg-3 location_sec pull-left"><label>Location</label><input type="text" class="form-control" value="'+loca_text+'" readonly ><input type="hidden" value="'+loc_id+'"></div>')
			}
		}else{
			$('.costCenterModal'+sr_no).remove();
		}
	}

	function changeName(modal,sr_no){
		$(modal+" .cost_category").attr('name',"cost_categroy"+sr_no+"[]");
		$(modal+" .cost_center").attr('name',"cost_center"+sr_no+"[]");
		$(modal+" .cost_center_cost").attr('name',"cost_center_cost"+sr_no+"[]");
	}

	function changeCcName(modal,sr_no){
		$(modal+" .ccost_category").attr('name',"ccost_category"+sr_no+"[]");
		$(modal+" .ccost_center").attr('name',"ccost_center"+sr_no+"[]");
		$(modal+" .ccost_center_cost").attr('name',"ccost_center_cost"+sr_no+"[]");
		$(modal+" .ccost_catagory_cash_flow").attr('name',"ccost_catagory_cash_flow"+sr_no+"[]");
		$(modal+" .ccost_center_link").attr('name',"ccost_center_link"+sr_no+"[]");
	}

	function getTotalDebit(val_this){
		debit_total = 0;
		$( ".debit_row .dr_amount" ).each(function( index ) {
			if(isNaN(parseFloat($(this).val()))){
				val = '';	
			}else{
				val = parseFloat($(this).val());
			}
			debit_total= debit_total +val;
		});
		if ($(".showCharges").prop('checked')==true && ($("#chargeValue").val()) > 0 ) {
				str1 = parseFloat($("#chargeValue").val());
				debit_charge_total = debit_total + str1;
			}
			else
			{
				str1 = '';
				$("#chargeValue").val('');
				debit_charge_total='';

			}
			var newformat = formatNumber(debit_total);
			$(".dr_total").text(newformat);
		//$(".dr_total").text(debit_total);
		var newchargeformat = formatNumber(debit_charge_total);
			$(".dr_charge_total").text(newchargeformat);
		//$(".dr_charge_total").text(debit_charge_total);
		//$(".cr_amount").val(debit_total);
		// disable_credit(val_this);
		
		
	}

	function getCharges(val_this){
		$(document).ready(function(){
			if($('.showCharges').prop("checked")==true){
				$(".charge").removeClass("hidden");
				$(".main_footer").removeClass("hidden");
				$(".charge").show();
				$(".f_total").text(' SUB TOTAL (Rs) ');
				$(".main_footer").show();
			}
			else if($('.showCharges').prop("checked")==false){
				$(".charge").hide();
				$("#chargeValue").val(0);
				$(".main_footer").hide();
				$(".f_total").text(' TOTAL (Rs) ');
				getTotalDebit();
				$(".dr_charge_total").text(0);
			}
		});
	}


	function getTotalCredit(val_this){
		debit_total = 0;
		$( ".credit_row .cr_amount" ).each(function( index ) {
			if(isNaN(parseFloat($(this).val()))){
				val =0;	
			}else{
				val = parseFloat($(this).val());
			}
			debit_total= debit_total +val;		
		});
		var debit_show=debit_total;
		$(".debit_show").val(debit_show);
		var newformat = formatNumber(debit_total);
		$(".cr_total").text(newformat);
		$('.install_total_amount').val(debit_show);
		getTotalDebit();
		payReciptAmount(debit_total);
	}

	function payReciptAmount(debit){
		var total = $(".total_amount").val();
		var rem = parseInt(total) - parseInt(debit);
		$(".total_am .total_inp").val(rem);
		$(".total_am span").text(rem);
		if(debit < total){
			$(".total_am span ").css({"color":"red"});
		}else if(debit == total){
			$(".total_am span ").css({"color":"black"});
		}
	}

	var form1 = $( "#formLedger" );
	form1.validate({
	  rules: {
		name: "required",
	  },
	  messages: {
		name: "Please Enter  name",
	  },
	  highlight: function(element, errorClass) {
		  $(element).addClass('errorInput');
		  $(element).parent().next().find("." + errorClass).removeClass("checked");
	  },unhighlight: function(element) {
		  $(element).removeClass('errorInput');
	  }
	});

	function showLedger(){
		$("#sliderModal").modal('show');
	}

	function showSetting(){
		$("#settingModal").modal('show');
	}

	function addLedger(){
		if(form1.valid()){
			$(".ledger-loading").show();
			$(".ledger-button").hide();
			 $.ajax({
			  url: "ajax/add-ledger.php",
			  type: "POST",
			  data: {'form-data':form1.serialize()}
			  
			}).done(function(msg) {
			 	$.ajax({
			  		url: "ajax/add-ledger.php",
			  		type: "POST",
			  		data: {'command':'<?php echo $type ?>'}
			  
				}).done(function(result) {					
					res = $.parseJSON(result);
					$(".db_select").html('');
				  	$(".db_select").html( res.html );
				  	$('.db_select').trigger("chosen:updated");
				  	if(res.type=='receipt'){
				  		$(".cr_select").html('');
				  		$(".cr_select").html( res.html_cr );
						$('.cr_select').trigger("chosen:updated");
				  	}
				 	$("#sliderModal").modal('hide');
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
	var form = $( "#form1" );
	form.validate({
	  rules: {
		voucher_no: "required",
		date_vc:"required"
		
	  },
	  messages: {
		voucher_no: "Please Enter Voucher No",
		date_vc:"Please Enter Voucher Date"
	  },
	  highlight: function(element, errorClass) {
		  $(element).addClass('errorInput');
		  $(element).parent().next().find("." + errorClass).removeClass("checked");
	  },unhighlight: function(element) {
		  $(element).removeClass('errorInput');
	  }
	});

	function check_db_cr(){
		dr_amount = 0;
		 result = true;
		$( ".cr_location_id" ).each(function( i ) {
			 if($(this).val()==""){
			 	alert("Please Select Head");
			 }
		});
		$( ".sublocations" ).each(function( i ) { 
			 if($(this).val()==""){
			 	alert("Please Select Sub Head");
			 	result = false;
				return false;
			 }
		}); 
		$( ".dr_amount" ).each(function( i ) {
			dr_amount+=parseFloat($(this).val());
			console.log(dr_amount + 'debit VAlue');
		});
		cr_amount=0;
		$( ".cr_amount" ).each(function( i ) {
			cr_amount+=parseFloat($(this).val());
			console.log(cr_amount + 'credit VAlue');
		});
		$( ".app_select" ).each(function( i ) {
			db_select=parseFloat($(this).val());
			if(isNaN(db_select)){
				result = false;	
				alert("Please Select Ledger");
			}
			if(db_select==0){
				alert("Please Select Ledger");
				result =  false;
				//throw "Please Select Ledger";
			}
			
		});
		
		remaining_total = cr_amount-dr_amount;	
		if(remaining_total>0){
			alert(" Credit Amount is greater than Debit Amount ");
			result =  false;
		}
		if(remaining_total<0){
			alert(" Debit Amount is Remaining to be Credit  ");
			result =  false;
		}
		if(cr_amount==0 || dr_amount==0){
			alert(" Please Fill Entries  ");
			result =  false;
		}
		return result;	
	}

	function chosenClick(val_this,type){
		getCurrentBalance($(val_this).val(),val_this);
		getBankName($(val_this).val(),val_this);
		checkCostCenter($(val_this),type);
		//getBankChecque($(val_this),type);
		getcashFlow($(val_this).val(),val_this);
	}
	function getcashFlow(ledger_id, val_this){
		$.ajax({
			url: 'add-recipt-voucher.php?type=receipt',
			type: 'POST',
			data: {ledger_id:ledger_id,command:'get-cash-flow'},
		})
		.done(function(result) {
			res = $.parseJSON(result);
			$(".ccostCenterModal .row_cost_center:first>.add_cost_voucher").html('');
			$(".ccostCenterModal .row_cost_center:first>.add_cost_voucher").html(res.cash_flow_cost_center);
		})
		.fail(function() {
			
		})
		.always(function() {
			
		});
		
	}

	function formatNumber(num) {
		return num.toString().replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
	}


	function getBankChecque(val_this,type){
		
		$.ajax({
			url: 'ajax/check-bank-checque.php',
			type: 'POST',
			data: {command: 'bank-check',ledger_id:val_this.val()},
		})
		.done(function(result) {
			res = $.parseJSON(result);
			if(res.bank=='yes'){
				// $(val_this).parent().parent().find('.cheque_book').remove();
				if(type=='debit'){
					$(val_this).parent().parent().find('.cheque_book').show();
				}else if(type=='credit'){
					$(val_this).parent().parent().find('.cheque_book').show();
					// $(val_this).parent().append('<input type="hidden" name="c_bankChecque_active[]" class="bank_check bank_check_active'+val_this.val()+'" value="'+res.bank+'">');
				}
			}
			else{
				$(val_this).parent().parent().find('.checque').val(0);
				$(val_this).parent().parent().find('.cheque_book').hide();
			}
		})
		.fail(function() {
			console.log("error");
		})
		.always(function() {
			console.log("complete");
		});
	}
	function checkCostCenter(val_this,type){
		$.ajax({
			url: 'ajax/check-cost-center.php',
			type: 'POST',
			data: {command: 'check-cost',ledger_id:val_this.val()},
		})
		.done(function(result) {
			res = $.parseJSON(result);
			if(res.cost_center!=''){
				$(val_this).parent().parent().find(" .input_cost").remove();
				$(val_this).parent().parent().find(".modal-href").remove();
				if(type=='debit'){
					$(val_this).parent().append('<input type="hidden" name="cost_active[]" class="input_cost input_cost_active'+val_this.val()+'" value="'+res.cost_center+'">');	
				}else if(type=='credit'){
					$(val_this).parent().append('<input type="hidden" name="c_cost_active[]" class="input_cost input_cost_active'+val_this.val()+'" value="'+res.cost_center+'">');
				}
			}
		})
		.fail(function() {
			console.log("error");
		})
		.always(function() {
			console.log("complete");
		});
	}

	$(document).ready(function(){
		$('.chosen-select').chosen({width:'100%',disable_search_threshold:1});
	});

	function get_sub_loc(val_this){
		$.ajax({
			url: 'add-recipt-voucher.php?type=receipt',
			type: 'POST',
			data: {item_loc:$(val_this).val(),command:'get_sub_loc'},
		})
		.done(function(data) {
			$(val_this).parent().parent().find('.sublocation_form').chosen('destroy');
			$(val_this).parent().parent().find('.sublocation_form').html('');
			$(val_this).parent().parent().find('.sublocation_form').html(data);
			$(val_this).parent().parent().find('.sublocation_form').chosen();
			
		})
	}

		function get_sub_location(val_this){
			$.ajax({
				url: 'add-recipt-voucher.php?type=receipt',
				type: 'POST',
				data: {item_loc:$(val_this).val(),command:'get_sub_loc'},
			})
			.done(function(data) {
				$(val_this).closest('tr').find('.sublocations').chosen('destroy');
				$(val_this).closest('tr').find('.sublocations').html('');
				$(val_this).closest('tr').find('.sublocations').html(data);
				$(val_this).closest('tr').find('.sublocations').chosen();
				
			})
		}

	function add_row(){
		var Tot
		$(".db_select").chosen('destroy');
		$(".debit_body").append($('.debit_row:first').clone());
		rowCount = $('.debit_table >tbody >tr').length;
		$('.debit_body tr:last .sr_no').text(rowCount+1-1);
		$('.debit_body tr:last .dr_sr_no').val(rowCount+1-1);
		$('.debit_body tr:last .db_balance').text(0);
		$('.debit_body tr:last .modal-href').remove();
		$(".debit_row:last .db_select").chosen().val(0);
		$(".db_select").chosen();
		$('.debit_body tr:last .dr_amount').val('0');
		Tot=calculateRemaingCredit();
		if (Tot > 0){
			$('.debit_body tr:last .dr_amount').val(Tot);
		}
		$('.debit_body tr:last .input_cost').remove();
		// $('.debit_body tr:last .cheque_book.').remove();
		$('.debit_body tr:last .modal-href').remove();
		getTotalDebit();
	}

	function add_cr_row(val_this){
		$(".cr_select").chosen('destroy');
		$(".credit_body").append($('.credit_row:first').clone());
		$(".credit_body .credit_row:last .mat_modal").find(".modal").remove();
		$('.credit_body tr:last').find(".link_modal_btn").remove();
		rowcCount = $('.credit_table >tbody >tr').length;
		$('.credit_body tr:last .crs_sr_no').text('');
		$('.credit_body tr:last .cr_sr_no').val('');
		$('.credit_body tr:last .crs_sr_no').text(parseInt(rowcCount));
		$('.credit_body tr:last .cr_sr_no').val(parseInt(rowcCount));
		$('.credit_body tr:last .cr_balance').text(0);
		$(".cr_select").chosen();
		$('.credit_body tr:last .input_cost').remove();
		$('.credit_body tr:last .modal-href').remove();
		getTotalCredit();
	}

	function calculateRemaingCredit() {
		var credit_total=0;
		var debit_total=0;
		var total=0;
		$( ".credit_body .cr_amount" ).each(function( index ){
			if(isNaN(parseFloat($(this).val()))){
				val =0;	
			}else{
				val = parseFloat($(this).val());
			}
			credit_total= credit_total +val;
		});

		$( ".debit_body .dr_amount" ).each(function( index ){
			if(isNaN(parseFloat($(this).val()))){
				val =0;	
			}else{
				val = parseFloat($(this).val());
			}
			debit_total= debit_total + val;
		});
		total = credit_total - debit_total;
		return total;
	}


	function remove_row(val){
		$(val).parent().parent('tr').remove();
		getTotalDebit();
	}

	function remove_cr_row(val){
		$(val).parent().parent('tr').remove();
		getTotalCredit();
	}
	

	function addVoucher(){
		if(form.valid()){
			if(check_db_cr()!==false ){
				$(".costCenterModal").remove();
				$(".ccostCenterModal").remove();
				// $(".dr_amount").prop("disabled",false);
				// $(".cr_amount").prop("disabled",false);

				$("#inp_file").val('');
				document.form.submit();
			}
		}
	}
	
	function removeCostRow(val){
		$(val).parent().parent().remove();
	}

	function getCurrentBalance(id,this_val){
		ledger_balance='';
		$.ajax({
			url:"<?php echo 'ajax/get-ledger-balance.php'?>",
			method:"POST",
			data: {'ledger_id':id},
		}).done(function(msg){
			response=jQuery.parseJSON(msg);
			if(response.ledger_balance!=''){
				ledger_balance =  response.ledger_balance;
				$(this_val).parent().parent().find(".current_balance").html(ledger_balance);	
			}		
		}).fail(function(jqXHR,textStatus){
			console.log(jqXHR);
		});
	}

	function getBankName(id,this_val){
		$.ajax({
			url:"<?php echo 'ajax/get-bank-data.php'?>",
			method:"POST",
			data: {'ledger_id':id},

		}).done(function(msg){
			res=jQuery.parseJSON(msg);
			if(res.bank_name!=''){
				Bank_Name = res.bank_name;
				$(this_val).parent().parent().parent().find(".bank_data").val(Bank_Name);
				// $(this_val).parent().parent().parent().find(".instrument_no").val(res.cheque_number);	
			}
		}).fail(function(jqXHR,textStatus){
			console.log(jqXHR);
		});
	}

	$(document).ready(function(){
		$('.date_vc').datepicker({format:'dd-mm-yyyy',autoclose:true}).on('changeDate', function(e) {
			str = e.date;
			var weekday = new Array(7);
				weekday[0] =  "Sunday";
				weekday[1] = "Monday";
				weekday[2] = "Tuesday";
				weekday[3] = "Wednesday";
				weekday[4] = "Thursday";
				weekday[5] = "Friday";
				weekday[6] = "Saturday";
				$('#weekDay').val(weekday[str.getDay()]);
		});
	});	
</script>
<script>
	$(document).ready(function(){
		var date_input=$('.voucher_date'); //our date input has the name "date"
		var container=$('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";
		date_input.datepicker({
			format: 'dd-mm-yyyy',
			container: container,
			todayHighlight: true,
			autoclose: true,
		});
	})
	
function AddCostRow(val_this){
	html = $(val_this).parent().parent().parent().find(".add_cost_voucher:first").clone();
	$(val_this).parent().parent().parent().parent().find(".row_cost_center").append(html);
	$('.add_cost_voucher:last').find(".myLabel").remove();
	$('.add_cost_voucher:last').find(".myLabel").remove();
}

function AddCcostRow(val_this){
	
	html = $(val_this).parent().parent().parent().find(".add_cost_voucher:first").clone();
	$(val_this).parent().parent().parent().parent().find(".row_cost_center").append(html);
	$(val_this).parent().parent().parent().find(".add_cost_voucher:last .ccost_center_cost").val("0");
	cost_center_value = $(val_this).parent().parent().parent().parent().find(".row_cost_center .ccost_center_cost");
	
	remaingDebitCostCenterVal = RemainingCostCenterValueDb(cost_center_value,val_this);

	if (remaingDebitCostCenterVal > 0){
		$(val_this).parent().parent().parent().find(".add_cost_voucher:last .ccost_center_cost").val(remaingDebitCostCenterVal);
	}

	$('.add_cost_voucher:last').find(".myLabel").remove();
}


function RemainingCostCenterValueDb(cost_center_value,val_this){
	var cost=0;
	var cost_total=0;
	var val=0;
	var debitInputbalance=0;
	debitInputbalance=$(val_this).parent().parent().parent().parent().find('.totalBalance').val();
	$(cost_center_value).each(function( index ){
		if(isNaN(parseFloat($(this).val()))){
			val =0;	
		}else{
			val = parseFloat($(this).val());
		}
		cost= cost +val;
	});
	cost_total = debitInputbalance - cost;
	return cost_total;
}

$(document).on('click', ".md-btn", function() {
   modal = $(this).data('md');
   $(modal).modal({
     	backdrop: 'static',
     	keyboard: false
 	});
	});

</script>

<script>
	// function disable_credit(val_this){
	// 	data_db = parseFloat($(val_this).val());
	// 	if (data_db > 1 || !isNaN(data_db) ) {
	// 		$(val_this).parent().next('td').find(".cr_amount").prop("disabled",true).val(0);
	// 	}
	// 	else{
	// 		$(val_this).parent().next('td').find(".cr_amount").removeAttr("disabled");
	// 	}
	// }

	// function disable_debit(val_this){
	// 	data_cr = parseFloat($(val_this).val());
	// 	console.log(data_cr);
	// 	if (data_cr > 1 || !isNaN(data_cr) ) {
	// 		$(val_this).parent().prev('td').find(".dr_amount").prop("disabled",true).val(0);
	// 	}
	// 	else{
	// 		$(val_this).parent().prev('td').find(".dr_amount").removeAttr("disabled");
	// 	}
	// }
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
	              } else     {  if (h > max_size) { w*=max_size/h; h=max_size; } }
	             
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
	     } else {
	        document.getElementById('inp_file').value = ''; 
	        alert('Please only select images in JPG- or PNG-format.');  
	     }
 	});
  }
 
  document.getElementById('inp_file').addEventListener('change', fileChange, false);

  function getLedgers(val_this,type){
  	ledger_type = $(val_this).val();
	  if((ledger_type=='online') || (ledger_type=='cheque') || (ledger_type=='mobile')){
			ledger_type = 'bank';
		}
		if(ledger_type == 'form_return'){
			page = "add-recipt-voucher.php?type=receipt";
		}else{
			page = "ajax/add-ledger.php"
		}
  	$.ajax({
	  	url: page,
	  	type: "POST",
	  	data: {'command':'ledger_type','l_type':ledger_type }, 
			  
	}).done(function(result) {					
		res = $.parseJSON(result);
		if(type=='debit'){
			find = $(val_this).parent().parent().find('.app_select');
		}
		if(type=='credit'){
			find = $(val_this).parent().parent().find('.crs_select');	
		}

	if(($(val_this).val()=='cash') || ($(val_this).val()=='mobile') || ($(val_this).val()=='form_return')){
			$(val_this).parent().parent().find('.bank_data').show();
			$(val_this).parent().parent().find('.bank_data_select').hide();
			if($(val_this).val()=='mobile'){
				$(val_this).parent().parent().find('.bank_data').removeAttr( "disabled");
				$(val_this).parent().parent().find('.bank_data').attr("placeholder", "Mobile Company");
			}else{
				$(val_this).parent().parent().find('.bank_data').prop( "disabled", true );
				$(val_this).parent().parent().find('.bank_data').attr("placeholder", "Bank");
			}
			$(val_this).parent().parent().find('.bank_data_select').removeAttr("name");
			$(val_this).parent().parent().find('.bank_data').attr('name', 'bank[]');
			$(val_this).parent().parent().find('.instrument_no').prop( "disabled", true );
			$(val_this).parent().parent().find('.instrument_no').val(0);
			$(val_this).parent().parent().find('.bank_date').prop( "disabled", true );
		}else if(($(val_this).val()=='bank') || ($(val_this).val()=='online')|| ($(val_this).val()=='cheque')){
			$(val_this).parent().parent().find('.bank_data').hide();
			$(val_this).parent().parent().find('.bank_data_select').show();
			$(val_this).parent().parent().find('.bank_data_select').attr('name', 'bank[]');
			$(val_this).parent().parent().find('.bank_data').removeAttr("name");
			$(val_this).parent().parent().find('.bank_data').prop( "disabled", false );
			$(val_this).parent().parent().find('.instrument_no').prop( "disabled", false );
			$(val_this).parent().parent().find('.instrument_no').val(0);
			$(val_this).parent().parent().find('.bank_date').prop( "disabled", false );
		}

		if(ledger_type == 'form_return'){
			$(find).chosen('destroy');
			$(find).html('');
			$(find).html( res.html );
			$(find).chosen();
			
		}else{
			$(find).html('');
			$(find).html( res.html );
			$(find).val(val_this).trigger("chosen:updated");

		}


	}).fail(function(jqXHR, textStatus) {
	  alert( "Request failed: " + textStatus );
	});
  }


  function againstValue(val_this){

  	if($(val_this).val()=='form'){
  		ShowFormModal(val_this);
  	}

  }

  function ShowFormModal(val_this){
  	var sr_no = $(val_this).parent().parent().find(".cr_sr_no").val();
  	$(".chosen-select").chosen("destroy");
  	if($('.form_modal'+sr_no).html()!=undefined){
		modal_no = 'form_modal'+sr_no;
		console.log("exists");
		$('.form_modal'+sr_no).modal({backdrop: 'static', keyboard: false});
		// $('.form_modal'+sr_no,).modal('show');
	}else{
		modal_no = 'form_modal'+sr_no;
		modal_no = modal_no.trim();
		adjusForm = "AdjustForm(this,'"+modal_no+"')";
		reverseForm = "RemoveAdjustForm(this,'"+modal_no+"')";
		$(val_this).parent().append($(".form_modal:first").clone().addClass(modal_no));
		$(".form_modal"+sr_no).removeClass("form_modal");
		$(val_this).parent().append("<a class='link_modal_btn' data-toggle='modal' href='."+modal_no+"'>Form</a>")
		$(".form_modal"+sr_no).find(".form-button").append("<button data-dismiss='modal' style='margin-top: 20px;margin-right: 10px; type='button' class='btn btn-primary' >Save</button>");
		$(".form_modal"+sr_no).find(".form-button .btn").attr("onclick","SaveFormAdjustment('"+modal_no+"')");
		$(".form_modal"+sr_no).find(".btn_div").append("<button type='button' style='margin-top: 26px' class='btn btn-primary btn-sm'>Add Form</button>");
		$(".form_modal"+sr_no).find(".btn_div button").attr("onclick","AddFormReciept('"+modal_no+"')");
		$(".form_modal"+sr_no).find(".customer_form").val(<?php echo $booking_detail['customer_ledger']; ?>);
		$(".form_modal"+sr_no).find(".buttons_div").html('<button type="button" class="btn btn-primary" data-value="'+sr_no+'" onclick="'+adjusForm+'" style="margin-bottom: 15px"><i class="fa fa-arrow-right"></i></button><br><button type="button"  data-value="'+sr_no+'"  onclick="'+reverseForm+'" class="btn btn-danger" ><i class="fa fa-arrow-left"></i></button>');
		$(".form_modal"+sr_no).find(".btn_div button").click();
		// $(".form_modal"+sr_no).modal("show");
		$('.form_modal'+sr_no).modal({backdrop: 'static', keyboard: false});
  	}
  	$(".chosen-select").chosen({width:'100%'});
  }

  // <?php //if($booking_id != 0){?>
	// 	$('.cr_select').change();
	// 	$('.cr_amount').keyup();
	// 	$('.dr_amount').keyup();
	// <?php //} ?>
//   $(document).ready(function(){
//     $("select.dr_ledger_type").change(function(){
//         var selectData = $(this).children("option:selected").val();
//         if (selectData=='bank') {
//         	$(".cheque_book").show();
//         }
//         else if(selectData!='bank'){
//         	$(".cheque_book").hide();
//         }
//     });
// });
// $('.showCharges').click(function(){
// 	if($(this).prop("checked") == true){
// 		$(".charge").removeClass("hidden");
// 		$(".charge").show();    
// 	}
// 	else if($(this).prop("checked") == false){
// 		$(".charge").hide();	
// 	}
// });
// $('.showCharges').click(function(){
// 	if($(this).prop("checked") == true){
// 		$(".main_footer").removeClass("hidden");
// 		$(".f_total").text(' SUB TOTAL (Rs) ');
// 		$(".main_footer").show();
// 	}
// 	else if($(this).prop("checked") == false){
// 		$(".main_footer").hide();
// 		$(".f_total").text(' TOTAL (Rs) ');
// 	}
// });

			// if ($(".showCharges").prop('checked')==true) {
			// 	str1 = parseFloat($("#chargeValue").val());
			// 	alert(str1);

			// 	debit_total = debit_total + str1;
			// 	console.log(debit_total + 'guio');
			// }
			// else
			// {
			// 	debit_total= debit_total +val;
			// }


	function remove_form_row(val_this){
		var modal_no = $(val_this).attr("data-value");
		var tr = $(val_this).parent().parent().attr("id");
		var sr = $("."+modal_no).parent().parent().find(".dr_sr_no").val();
		$("#"+tr).remove();
		$(".adjusted_forms #"+tr).remove();
		TotalFormAmount("adjustable_form",sr);
	}


	adjusted_form = 0;
	function AddFormReciept(modal_no){
		var sr = $("."+modal_no).parent().parent().find(".cr_sr_no").val();
		$.ajax({
			url : 'add-recipt-voucher.php?type=receipt',
			data : {form_no : $(".form_no").val(),customer_id : $(".customer_form").val(),type_form : $(".form_type").val(),booking_id:<?php echo intval($_REQUEST['booking_id']); ?>,command : 'getFormReciept'},
			type : 'post',
			dataType : 'json',
			success : function(result){
				var AdjustForm = "AdjustForm(this,'"+modal_no+"')";
				if(result){
					$.each(result,function(key , val){
						var html='';
						var form = val;
							if(val==null){
							return;
						}
						for(i=0;i<val.length;i++){
						var index = '';
						if($(".adjustable_forms #"+form[i].registration_no).html()==undefined){
							html += '<tr class="text-center reg_no" id="'+form[i].registration_no+'">';
							html += '<td class="check_td"><input class="adjust_check" type="checkbox" ></td>'
							html += '<td><input type="hidden" class="form-control registration_no" value="'+form[i].registration_no+'"><input type="hidden" class="form-control form_id" value="'+form[i].id+'">'+form[i].registration_no+'</td>';
							html += '<td style="text-transform:capitalize"><input type="hidden" class="form-control customer_id"  value="'+form[i].customer+'">'+form[i].c_name+'</td>';
							html += '<td><input type="hidden" class="form-control sr_no"  value="'+sr+'"><input type="hidden" class="marla form-control"   value="'+form[i].marla+'">'+form[i].marla+' '+form[i].unit+'</td>';
							if(form[i].balloting == 'yes'){
								adjusted_form++;
								html += '<td class="transfer_td">Balloting<input type="hidden" value="soldout" class="status"></td>';
								html += '<td class="frm_td"><input type="hidden" class="form-control frm_amount" value="150000"> 150000 </td>';
							}else{
								html += '<td class="transfer_td">'+form[i].status+'<input type="hidden" value="soldout" class="status"></td>';
								html += '<td class="frm_td"><input type="hidden" class="form-control frm_amount" value="180000"> 180000 </td>';
							}

							
							html += '</tr>';
						}
					}
					
					$("."+modal_no).find(".receipt_body .receipt_row").remove();
					if(key == 'adjustable_forms'){
						$("."+modal_no).find(".adjustable_form .receipt_body ").append(html);
					}else if(adjusted_form==1){
						$("."+modal_no).find(".adjusted_forms .receipt_body ").append(html);
						setAttribute(modal_no,sr);
					}
					TotalFormAmount("adjustable_form",sr);
					TotalFormAmount("adjusted_forms",sr);
					});

				
				}
			}
		});	
	}

	function TotalFormAmount(table,sr){
		var sum = 0;
		$(".form_modal"+sr+" ."+table).find("tbody tr").each(function(){
			sum += parseInt($(this).find(".frm_amount").val());
		});
		$(".form_modal"+sr+" ."+table).find(" tfoot tr .sum_of_form_amount").text(sum);
		$(".form_modal"+sr+" ."+table).find(" tfoot tr .sum_of_form_amount_inp").val(sum);
	}

	function SaveFormAdjustment(modal_no){
		var sum_of_form_amounts = $("."+modal_no+" .adjusted_forms").find(".sum_of_form_amount_inp").val();
		$('.'+modal_no).parent().parent().find(".cr_amount").val('');
		$('.'+modal_no).parent().parent().find(".cr_amount").val(sum_of_form_amounts);
		$('.'+modal_no).parent().parent().find(".cr_amount").keyup();
		$('.dr_ledger_type option[value=form_return]').prop('selected', 'selected');
		getLedgers($('.dr_ledger_type'),'debit');
	}	

	// function updateBooking(val_this){
	// 	<?php //if(isset($_REQUEST['booking_id']) && intval($_REQUEST['booking_id']) >0 ){ ?>
	// 	var arr = [];
	// 	$(val_this).parent().parent().find(".reg_no").each(function(){
	// 		arr.push($(this).find(".form_id").val());
	// 	});
	// 	var data = JSON.stringify(arr);
	// 	var frm_am = $(val_this).parent().parent().find(".sum_of_form_amount_inp").val()
	// 	$.ajax({
	// 		url : 'add-recipt-voucher.php',
	// 		data : {form_data :data,booking_id : <?php //echo $_REQUEST['booking_id']; ?>,frm_am ,command : 'updateBooking'},
	// 		dateType : 'json',
	// 		type : 'post',
	// 		success : function(result){
	// 		}
	// 	});
	// 	<?php //} ?>
	// }



	function ShowLocations(val_this){
		if($(val_this).prop('checked')==true){
			$(".credit_table").find(".location_td").removeClass("hidden");
			$(".cr_select").chosen("destroy");
			$(".credit_table").find(".location_td").find(".cr_location_id").attr("name",'cr_location_id[]');
			$(".credit_table").find(".location_td").find(".cr_sub_id").attr("name",'cr_sub_id[]');		
			$(".cr_select").chosen({width:'100%'});
		}else{
			$(".credit_table").find(".location_td").addClass("hidden");
			$(".cr_select").chosen("destroy");
			$(".credit_table").find(".location_td").find(".cr_location_id").attr("name",'');
			$(".credit_table").find(".location_td").find(".cr_sub_id").attr("name",'');	
			$(".cr_select").chosen({width:'100%'});
		}
	}

	function AdjustForm(val_this,modal_no){
		var frm_am = $(val_this).parent().parent().find(".frm_amount").val();
		var AdjustForm = "RemoveAdjustForm(this,'"+modal_no+"')"
		var sr = $(val_this).attr("data-value");
		var html ='';
		$("."+modal_no+" .adjustable_form tbody tr").each(function(){
			if($(this).find(".adjust_check").prop("checked")==true){
				var id = $(this).attr("id");
				if($(".adjusted_forms #"+id).html() == undefined){
					$("."+modal_no+" .adjusted_forms tbody").append($(this).clone());
					$(".adjusted_forms #"+id+" .check_td input").prop("checked",false);
				}
				$(this).remove();
			}
		});
		setAttribute(modal_no,sr)
		TotalFormAmount("adjustable_form",sr);
		TotalFormAmount("adjusted_forms",sr);

	}

	function RemoveAdjustForm(val_this,modal_no){
		var className = $(val_this).parent().parent().attr("id");
		var frm_am = $(val_this).parent().parent().find(".frm_amount").val();
		var AdjustForm = "AdjustForm(this,'"+modal_no+"')";
		var sr = $(val_this).attr("data-value");
		var html ='';
		$("."+modal_no+" .adjusted_forms tbody tr").each(function(){
			if($(this).find(".adjust_check").prop("checked")==true){
				var id = $(this).attr("id");
				if($(".adjustable_form #"+id).html() == undefined){
					$("."+modal_no+" .adjustable_form tbody").append($(this).clone());
					$(".adjustable_form #"+id+" .check_td input").prop("checked",false);
				}
				$(this).remove();
			}
		});
		$("."+modal_no+" .adjusted_forms tbody").find("#"+className).remove();
		$("."+modal_no+" .adjustable_form tbody").find("input").removeAttr("name");
		TotalFormAmount("adjusted_forms",sr);
		TotalFormAmount("adjustable_form",sr);
	}
	function setAttribute(modal_no,sr){
		$("."+modal_no+" .adjusted_forms .form_id").attr("name",'frm_id'+sr+'[]');
		$("."+modal_no+" .adjusted_forms .registration_no").attr("name","reg_no"+sr+"[]");
		$("."+modal_no+" .adjusted_forms .sr_no").attr("name","sr"+sr+"[]");
		$("."+modal_no+" .adjusted_forms .customer_id").attr("name","cust_id"+sr+"[]");
		$("."+modal_no+" .adjusted_forms .status").attr("name","status"+sr+"[]");
		$("."+modal_no+" .adjusted_forms .marla").attr("name","marla"+sr+"[]");
		$("."+modal_no+" .adjusted_forms .frm_amount").attr("name","frm_amount"+sr+"[]");
	}
	function SelectCustomer(val_this){
		var value = $(val_this).val();
		var customer_id = $(".form_no option[value='"+value+"']").attr("data-value");
		$(".customer_form").val(customer_id).trigger("chosen:updated");
	}
	
		<?php 
		if($sale_invoice_row!=''){
	?>
		$(".cr_amount").keyup();
	<?php
		}
	?>

</script>
</body>
</html>