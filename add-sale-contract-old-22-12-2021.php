<?php	
	include("../includes/common-files.php");
	$a->authenticate();
	$obj_purchase = load_class('purchasequotation');
	$obj_item = load_class('item');
	$items = $obj_item->getAllRecords();
	$obj_item_group = load_class('itemgroup');
	$item_group = $obj_item_group->getAllRecords();
	$obj_location = load_class('location');
	$locations = $obj_location->getAllRecords();
	$obj_group = load_class('costcenter');

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getBrands'){
		$item_id = $_REQUEST['item'];

		$db->Select("select itb.* from item_brand as itb right join sub_item as sb on (sb.brand_id = itb.id) where sb.item_id = ".$item_id." and FIND_IN_SET('".getCompanyId()."',itb.company_id)  group by itb.name");
		$brands = $db->fetch_all();
		$brand_li.='<option value="0"> Select Brands </option>';
		foreach ($brands as $brand){
		 	$brand_li.='<option value="'.$brand['id'].'">'.$brand['name'].'</option>';
		}
		echo $brand_li;
		exit();
	}

	if(isset($_REQUEST['c_id']) && intval($_REQUEST['c_id'])  > 0){
		$contract = $db->fetch_array_by_query("Select * from sale_contract where id = ".$_REQUEST['c_id']);
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getQualities'){
		$item_id = $_REQUEST['item'];
		$brand = $_REQUEST['brand'];
		$location_id = $_REQUEST['location_id'];
		$project_id = $_REQUEST['project_id'];
		$db->Select("Select qi.quality_id as q_id from quotation_inventory as qi left join sub_item as sbi on (qi.item_id = sbi.item_id and qi.sub_item_id = sbi.id) where qi.item_id = ".$item_id." and sbi.brand_id = ".$brand." group by qi.quality_id");		
		$qualities = $db->fetch_all();
		$quality_li.='<option value="0"> Select Quality </option>';
		foreach ($qualities as $quality){
			$qualiti = $db->fetch_array_by_query("select * from item_quality where id=".$quality['q_id']);
		 	$quality_li.='<option value="'.$quality['q_id'].'">'.$qualiti['name'].'</option>';
		}
		echo $quality_li;
		exit();
	}



	function check_voucher_no(){
		global $db;
		$sale_contract_last = $db->fetch_array_by_query("SELECT * FROM sale_contract where company_id=".getCompanyId()." ORDER BY ID DESC LIMIT 1");
		$contract_no = $sale_contract_last['sale_contract_no']+1;
		return $contract_no;
	}
	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getPurchaseRate'){
		$quality = $_REQUEST['item_quality'];
		$item_id = $_REQUEST['item_id'];
		$head = $_REQUEST['head'];
		$sub_head = $_REQUEST['sub_head'];
		$purchase = $db->fetch_array_by_query("Select count(*) as total ,sum(rate) as total_amount from quotation_inventory where item_id = ".$item_id." and quality_id = ".$quality." and location_id = ".$head." and sub_location_id = ".$sub_head);
		$rate  = $purchase['total_amount'] / $purchase['total'];
		echo $rate;
		exit();
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'get_sub_loc') {
		$db->select('select * from item_sublocation where location_id='.$_REQUEST['item_loc'].' and FIND_IN_SET('.getCompanyId().',company_id)');
		$sub_locations = $db->fetch_all();
		$sub_loc .= "<option selected disabled>Select Sub Location</option>";
		foreach ($sub_locations as $sub_location) {
			$sub_loc .= "<option value='".$sub_location['id']."'>".$sub_location['name']."</option>";
			}
			echo $sub_loc;
		exit();
	}

	$contract_ar = check_voucher_no();
	if(isset($_POST['purchaseContract'])){
	
		$db->Select("select * from sale_contract where contract_type = '".$_POST['contract_type']."' and location_id=".intval($_POST['location'])." and sub_location_id=".intval($_POST['sub_location']).' and company_id ='.getCompanyId().' order by id desc');
		$last_contracts = $db->fetch_all();
		if($last_contracts){
			foreach($last_contracts as $last_contract){
				$l_c_id = $db->update($last_contract['id'],array('locked'=>'yes'),'sale_contract');
			}
		}

		$contract_row = $db->fetch_array_by_query("select * from sale_contract where sale_contract_no =".$_POST['sale_contract_no']." and company_id=".getCompanyId());
		if($contract_row){
			$contract_last = $db->fetch_array_by_query("select * from sale_contract where company_id=".getCompanyId()." order by id desc limit 1");
			$_POST['sale_contract_no'] = $contract_last['sale_contract_no']+1;
		}
		$arr = array();
		if(isset($_REQUEST['discount_mode']) && $_REQUEST['discount_mode']!='' ){
			$discount_mode=$_REQUEST['discount_mode'];
		}else{
			$discount_mode='no';
		}
		if(isset($_REQUEST['subitem_mode']) && $_REQUEST['subitem_mode']!='' ){
			$subitem_mode=$_REQUEST['subitem_mode'];
		}else{
			$subitem_mode='no';
		}
		if(isset($_REQUEST['customer_mode']) && $_REQUEST['customer_mode']!='' ){
			$customer_mode=$_REQUEST['customer_mode'];
		}
		else{
			if(isset($_POST['customer']) && intval($_POST['customer']) > 0){
				$customer_mode='yes';
			}else{
				$customer_mode='no';
			}

		}
	
		
		 $arr['target_qty']=$_POST['t_qty'];
		 $arr['target_type']=$_POST['t_type'];
		$arr['qty_type']=$_POST['qty_type'];
		$arr['customer_mode']=$customer_mode;
		$arr['subitem_mode']=$subitem_mode;
	
		$arr['discount_mode'] = $discount_mode;
		$arr['company_id'] = getCompanyId();
		$arr['user_id'] = getUSerId();
		$arr['created_at'] =time();
		$arr['narration'] = $_POST['narration'];

		$arr['sale_contract_series'] = $_POST['sale_contract_series'];
		$arr['contract_date'] = strtotime($_POST['contract_date']);
		$arr['contract_day'] = $_POST['contract_day'];
		$arr['sale_contract_no'] = $_POST['sale_contract_no'];
		$arr['validity_date'] =strtotime($_POST['validity_date']);
		$arr['total_amount'] = $_POST['total'];
		$arr['total_quantity'] = $_POST['tot_quantity'];	
		$arr['contract_type'] = $_POST['contract_type'];
		if(!empty($_POST['customer_mode'])){
			if(isset($_POST['customer']) && intval($_POST['customer']) > 0){
				$arr['customer_id'] = $_POST['customer'];
			}else{
				$arr['customer_id'] = 0;
			}
		}
		$arr['location_id'] = intval($_POST['location']);
		$arr['sale_type'] = $_POST['sale_type'];
		$arr['sub_location_id'] = intval($_POST['sub_location']);
		$arr['project_id'] = intval($_POST['sub_location']);
		$arr['total_rate'] = $_POST['tot_rate'];
		// $arr['contract_mode'] = $_POST['contract_mode'];
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
		// echo "<pre>";
		// print_r($arr);
		// die();
		$contract_id = $db->insert($arr,'sale_contract');
		// if($contract_id){
		// 	echo "added successfully";
		// }else{
		// 	echo "not added";
		// }
		// die();
		if ($contract_id > 0){
			if($_POST['item_name']){
			
				for ($i = 0; $i < count($_POST['item_name']); $i++){ 
					$arr_detail = array();
					$arr_detail['item_id'] = $_POST['item_name'][$i];
					$arr_detail['item_group'] = $_POST['item_group'][$i];
					$arr_detail['brand_id'] = $_POST['brand'][$i];
					$arr_detail['quality_id'] = $_POST['quality'][$i];
					$arr_detail['actual_rate'] = $_POST['rate'][$i];
					$arr_detail['commission'] = $_POST['commission'][$i];
					$arr_detail['purchase_rate'] = $_POST['purchase_rate'][$i];
					$arr_detail['balance_category'] = $_POST['balance_category'][$i];
					if($_POST['discount'][$i]==''){
						$arr_detail['discount'] ='0';
					}else{
						$arr_detail['discount'] = $_POST['discount'][$i];
					}
					$arr_detail['created_at'] =time();
					$arr_detail['updated_at'] =time();
					if(isset($_POST['contract_type']) && $_POST['contract_type'] == 'retail_sale'){
						if(isset($_POST['customer']) && intval($_POST['customer']) > 0){
							$arr_detail['customer_id'] = $_POST['customer'];
						}else{
							$arr_detail['customer_id'] = 0;
						}
					}
					
					$arr_detail['p_c_id'] = $contract_id;
					$arr_detail['company_id'] = getCompanyId();
					$arr_detail['user_id'] = getUSerId();
					$arr_detail['location_id'] = intval($_POST['location']);
					$arr_detail['project_id'] = intval($_POST['sub_location']);
					
					$receipt_trans_id = $db->insert($arr_detail,'s_contract_transaction');
				}
				$contract_id=$receipt_trans_id;
			}
		}
		if($contract_id){
			$obj_msg = load_class('InfoMessages');
			$obj_msg->setMessage('Added Successfully!');
			redirect_header(ADMIN_URL.'sale/sale-contract.php');
		}else{
			$obj_msg = load_class('InfoMessages');
			$obj_msg->setMessage('Error Occur. Please try again later.', 'error');
			redirect_header(ADMIN_URL.'sale/sale-contract.php');
		}
	}

	$page_title="";
	$tab =" Sale Contract";
?>
<!DOCTYPE html>
<html>
<head>
<?php include("../includes/common-header.php");?>
<link rel="stylesheet" href="<?php echo BASE_URL;?>css/voucher.css?v=7.3" type="text/css"/>
<style type="text/css">
.form-control{border-radius: 5px !important}
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
@media only screen and (min-width:787px) {.vl1 .form-control{width: 80%;}
}
@media only screen and (min-width:787px) {#sliderModal .vl1 .row{padding-left: 10% !important;}
}
.vl {border-left: 4px solid green;height: 170px;}
.vl1 {border-radius: 4px;border-bottom: 4px solid green;width: 100%;border-right: 4px solid green;border-left: 4px solid green;}
@media only screen and (max-width:480px) {button { width: 98% !important;display:block;	}
.vl {border-left: 4px solid green;height: 20px !important;margin-left: 50%;	}
}
.P_b{border-bottom-left-radius:0px !important}
.P_b{border-top-left-radius:0px !important}
.pv_b{border-bottom-right-radius:0px !important}
.pv_b{border-top-right-radius:0px !important}
.chosen-container-single .chosen-single{border-radius: 4px;}
.input-group .input-group-addon.pq{width:20%;}
.foot, .foot>tr, .foot>tr>td{border:none !important;}
.charges_row{background-color: #f9f9f9}
.customer-mode{display: none;}
</style>
</head>
<body class="skin-green-light sidebar-mini">
	<div class="wrapper">
		<?php include("../includes/header.php");?>
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
			<?php  $db->select("SELECT * FROM `ledger` where active_project='yes' and company_id=".getCompanyId());
			$ledgers = $db->fetch_all();?>
			<form method="post" enctype="multipart/form-data" id='contractForm' name="form" autocomplete="off">
				<input type="hidden" name="command" value="add" >
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
							<div class=" box-body">
								<div class="row" style="padding: 13px">
									<div class="col-sm-12">
										<div class="col-sm-2">
											<div class="form-group">
												<label > Sale Contract No: </label>
												<div class="input-group">
													<span class="input-group-addon pq">SC</span>
													<input type="hidden" name="sale_contract_series" value="<?php echo "SC" ?>">
													<input  type="text" class="form-control" placeholder=" Contract No." name="sale_contract_no" value="<?php echo $contract_ar ?>" readonly>
												</div>
											</div>
										</div>
										<?php
											$date =  strtotime(date('d-m-Y'));
											$day_value = date('l',$date); ?>
										<div class="col-sm-2">
											<div class="form-group">
												<label> Date & Day: </label>
												<div class="input-group">
													<span class="input-group-addon" style="width: 50%;padding: 0;">
														<input type="text" class="form-control" placeholder=" Date " value="<?php echo date('d-m-Y') ?>" name="contract_date" autocomplete="off" readonly >
													</span><input type="text" class="form-control input-group-addon" placeholder=" Day" id="weekDay" name="contract_day" value="<?php echo $day_value ?>" readonly>
												</div>
											</div>
										</div>
										<!-- <div class="col-sm-2">
											<label>Choose Contract Mode</label>
											<select class="form-control vendor_supplier_mode contract_mode" name="contract_mode" onchange="mode_supp_ven(this)">
												<option value="0"> Select Mode </option>
												<option value="vendor"> Vendor </option>
												<option value="supplier"> Supplier </option>
												<option value="both_sup_veh"> Both Supplier And Vendor </option>
											</select>
										</div>
										<div class="form-group col-md-2">
											<div class="selectedMode">
												<label> Select Mode </label>
												<select class="form-control">
													<option> Selection Mode  </option>
												</select>
											</div>
										</div> -->
										<div class="col-sm-2">
											<div class="form-group">
												<label> Validity Date: </label>
												<div class="form-group">
													<input type="text" class="form-control validity validity_date" placeholder="Validity Date" name="validity_date">
												</div>
											</div>
										</div>
										<div class="col-md-6 no-gutter">
											<div class="col-md-5">
												<?php 
												$db->Select("Select * from item_location where FIND_IN_SET(".getCompanyId().",company_id)");
												$locations = $db->fetch_all();
												?>
												<label>Location</label>
												<select class="form-control chosen-select location" onchange="get_sub_location(this)" name="location">
													<option value="">Select Location</option>
													<?php
													foreach($locations as $loc){
														$select = '';
														if($contract['location_id'] == $loc['id']){
															$select = 'selected';
														}
													?>
													<option <?php echo $select; ?> value="<?php echo $loc['id']; ?>"><?php echo $loc['name']; ?></option>
													<?php
													}
													?>
												</select>
											</div>
											<div class="col-md-5">
												<label>Sub Location</label>
												<select class="form-control sub_location chosen-select" name="sub_location">
													<?php 
													$db->Select("Select * from item_sublocation where location_id = ".$contract['location_id']." and  FIND_IN_SET(".getCompanyId().",company_id)");
													$sublocations = $db->fetch_all();
													?>
													<option value="">Select Sublocation</option>
													<?php
													foreach($sublocations as $sloc){
														$select = '';
														if($contract['sub_location_id'] == $sloc['id']){
															$select = 'selected';
														}
													?>
													<option <?php echo $select; ?> value="<?php echo $sloc['id']; ?>"><?php echo $sloc['name']; ?></option>
													<?php
													}
													?>
												</select>
											</div>
											<div class="col-sm-2">
											<div>&nbsp;</div>
											<label style="margin-top: 15px"><input type="checkbox" name="subitem_mode" value="yes"> Subitem </label>
										</div>
											<div class="clearfix"></div>
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="col-md-12">
										<div class="col-md-6 no-gutter">
											<div class="col-md-4 ">
												<label>Contract Type</label>
												<select onchange="ContractType(this)" class="form-control chosen-select" name="contract_type">
												<option <?php if($contract['contract_type'] == 'project'){ echo "selected";}?> value="project">Project</option>
													<option <?php if($contract['contract_type'] == 'retail_sale'){ echo "selected";}?> value="retail_sale">Retail Sale</option>
													<option <?php if($contract['contract_type'] == 'whole_sale'){ echo "selected";}?> value="whole_sale">Whole Sale</option>
													<option <?php if($contract['contract_type'] == 'company_sale'){ echo "selected";}?> value="company_sale">Company Sale</option>
													<option <?php if($contract['contract_type'] == 'distributer'){ echo "selected";}?> value="distributer">Distributer</option>
												</select>
											</div>
											<div class="col-sm-2 customer-mode">
										<div>&nbsp;</div>
										<label style="margin-top: 15px">
												<input type="checkbox" class="customer-check" name="customer_mode" onclick="ContractType(this)" value="yes">Customer
										</label>
											</div>
										

											<div class="col-md-4 customer <?php echo $s_contract['contract_type']; ?>" <?php if($s_contract['contract_type'] == 'whole_sale' || $s_contract['contract_type'] == 'company_sale'){?> style="display: none"<?php } ?>>
												<label>Customer</label>
												<select class="form-control chosen-select" name="customer">
													<option value="">Select Customer</option>
													<?php
													$db->Select("select * from ledger where active_customer='yes' and company_id = ".getCompanyId());
													$customers = $db->fetch_all();
													foreach($customers as $c){
													?>
													<option <?php if($contract['customer_id'] == $c['id']){ echo "selected";}?> value="<?php echo $c['id']?>"><?php echo $c['name']?></option>
													<?php } ?>
												</select>
											</div>
										</div>
										<div class="col-sm-2">
											<div>&nbsp;</div>
											<label style="margin-top: 15px"><input type="checkbox" name="discount_mode" value="yes"> Discount </label>
										</div>
									
										<div class="clearfix"></div>
										

											<div class="col-md-6 no-gutter">
											
											<div class="col-md-4">
												<label>Target-Quantity</label>
												<input type="number" class="form-control quantity"  name="t_qty">
											</div>
											<div class="col-md-4">
											<label>Quantity-type</label>
												<select name="qty_type" class="form-control">
													<option value="squares">Squares</option>
													<option value="boxes" selected>Boxes</option>

												</select>
											</div>
											<div class="col-md-4">
												<label>Target-Type</label>
												<select name="t_type" class="form-control">
													<option value="monthly" selected>Monthly</option>
													<option value="daily">daily</option>

												</select>
											</div>
											</div>
											

									
									
									
									
									
										
									</div>			
								</div>
								<div class="table-responsive">
									<table class="table table-striped pq_table">
										<thead>
											<tr class="title_bg">
												<th rowspan="2" style="width:5%">Sr #</th>
												<th rowspan="2" style="width:10%">Item Group</th>
												<th rowspan="2" style="width:10%">Item Name</th>
												<th rowspan="2" style="width:10%">Brand</th>
												<th rowspan="2" style="width:10%">Quality</th>
												<th rowspan="2" style="width:10%">Purchase Rate</th>
												<th rowspan="2" style="width:15%">Rate</th>
												<th rowspan="2" style="width:10%">Commission</th>
												<th colspan="2" style="width:15%" class="hidden discount_heading border-b0">Discount</th>												
												<th rowspan="2" style="width:5%">Action</th>
											</tr>
											<tr class="hidden discount_heading">
                                                <th scope="col">Basic</th>
                                                <th scope="col">Net</th>
                                            </tr>
										</thead>
										<tbody class="pq_body">
											<?php
											$sr_no = 1;
											if($contract){
											$db->select("select * from s_contract_transaction where p_c_id=".$_REQUEST['c_id']." and company_id=".getCompanyId());
											$p_t_contracts=$db->fetch_all();
											foreach ($p_t_contracts as $p_t_contract){
												$sr_no=$sr_no+1;
												$itemSymbol=$db->fetch_array_by_query("SELECT iu.*, iu.symbol FROM item_unit as iu ,p_invoice_transaction as pi,item as u where u.id=".$p_t_contract['item_id']." and u.unit_id = iu.id and iu.company_id=".getCompanyId()." group by id");
												?>
												<tr class="pq_row">
													<td class="sr_no_tab1 sr_no pq_dr_no"><?php echo $sr_no ?></td>
													<input type="hidden" name="sr_no[]" class="input_sr" value="<?php echo $sr_no ?>">
													<td class="group_chosen">
													<div style="width: 100% !important" class="input-group">
														<select name="item_group[]" onchange="group_item(this)" class="chosen-select form-control">
															<option> select Item Group</option>
															<?php
																$db->select('SELECT * FROM item_group where FIND_IN_SET('.getCompanyId().',company_id) order by id desc');
																	$group_results = $db->fetch_all();
																	foreach ($group_results as $group_result){
																		if ($group_result['id']==$p_t_contract['item_group']) {
																			$select="selected";
																		}else{
																			$select="";
																		} ?>
																<option id='<?php echo ($group_result['id']) ?>' value='<?php echo ($group_result['id']) ?>' <?php echo $select ?>> <?php echo $group_result['name'] ?>
																</option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td>
													<div class="chosenWidth chosen">
														<div class="input-group">
															<select class="form-control chosen-select check_item selecteditem item_name" name="item_name[]" placeholder=" Enter Item Name" onchange="GetBrands(this)">
																<option> Select Item Name</option>
																<?php
																	$db->select('SELECT * FROM item where FIND_IN_SET('.getCompanyId().',company_id)');
																	$optionResults = $db->fetch_all();
																	foreach ($optionResults as $optionResult){
																		if ($optionResult['id'] == $p_t_contract['item_id']){
																	 		$select="selected";
																	 	} else {
																	 		$select="";
																	 	} ?>
																		<option id='<?php echo ($optionResult['id']) ?>' value='<?php echo ($optionResult['id']) ?>' <?php echo $select; ?>> <?php echo $optionResult['name'] ?> </option>
																<?php } ?>
															</select>
															<span class="input-group-btn">
																<button class="btn btn-default" type="button" onclick="showItemModal()"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
															</span>
														</div>
													</div>
												</td>
												<td>
													<div class="chosenWidth chosen">
														<div class="form-group">
															<select class="form-control chosen-select item_brand" name="brand[]" placeholder=" Enter Item Name" onchange="getQuality(this)">
																<option> Select Brand</option>
																<?php
																	$db->select("select itb.* from item_brand as itb right join sub_item as sb on (sb.brand_id = itb.id) where sb.item_id = ".$p_t_contract['item_id']." and FIND_IN_SET('".getCompanyId()."',itb.company_id)  group by itb.name");
																	$brands = $db->fetch_all();
																	foreach ($brands as $brand){
																		if ($brand['id'] == $p_t_contract['brand_id']){
																	 		$select="selected";
																	 	} else {
																	 		$select="";
																	 	}
																?>
																		<option <?php echo $select; ?> id='<?php echo ($brand['id']) ?>' value='<?php echo ($brand['id']) ?>'> <?php echo $brand['name'] ?> </option>
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
																	$db->select("Select qi.quality_id as q_id from quotation_inventory as qi left join sub_item as sbi on (qi.item_id = sbi.item_id and qi.sub_item_id = sbi.id) where qi.item_id = ".$p_t_contract['item_id']." and sbi.brand_id = ".$p_t_contract['brand_id']." group by qi.quality_id");
																	$qualities = $db->fetch_all();
																	foreach ($qualities as $quality){
																		$qualiti = $db->fetch_array_by_query("select * from item_quality where id=".$quality['q_id']);
																		if ($quality['q_id'] == $p_t_contract['quality_id']){
																	 		$select="selected";
																	 	} else {
																	 		$select="";
																	 	}
																?>
																		<option <?php echo $select; ?> id='<?php echo ($quality['id']) ?>' value='<?php echo ($qualiti['id']) ?>'> <?php echo $qualiti['name'] ?> </option>
																<?php } ?>
															</select>
														</div>
													</div>
												</td>
												<td class="mat_modal">
													<div class="input-group">
														<span class="input-group-btn show_mt_modal" <?php if ($p_t_contract['delivery_term']!='yes'){echo 'style="display: none;"';} ?>>
															<button class="btn btn-primary" type="button" onclick="showCarrMat(this)"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
														</span>
														<input type="number" name="rate[]" class="form-control check_rate rate" onkeyup="calculate_Total(this)" placeholder="Rate" value="<?php echo $p_t_contract['actual_rate']; ?>">
														<span class="input-group-addon"><strong>P/</strong><b class="qty_symbol_per"><?php if($itemSymbol['symbol']!=''){
															echo $itemSymbol['symbol'];
														}else{
															echo "unit";
														} ?></b></span>
													</div>
												</td>
												
												<td>
													<div class="input-group">
														<input type="number" name="commission[]" class="form-control" value="<?php echo $p_t_contract['commission'];?>" placeholder="Commission">
														<span class="input-group-addon no-gutter">
															<select style="padding: 5px" name="balance_category[]" class="balance_category">
																<option value="Rs" <?php if($p_t_contract['balance_category'] == 'Rs'){echo "selected";}?>>No.</option>
																<option value="%" <?php if($p_t_contract['balance_category'] == '%'){echo "selected";}?> >%</option>
															</select>
														</span>
													</div>
												</td>
												
												<td>
												<div class="form-group">
													<input type="number" value="<?php echo $p_t_contract['purchase_rate'];?>" name="purchase_rate[]" class="purchase_rate form-control" placeholder="Purchase Rate">
												</div>
												</td>
													<td>
														<a class="btn btn-primary add_row" onclick="add_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
														<a class="btn btn-danger" class="remove_row" onclick="remove_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
														</a>
													</td>
												</tr>
											<?php }
											}else{ 
											?>
											<tr class="pq_row">
												<td class="sr_no_tab1 sr_no pq_dr_no"><?php echo $sr_no++ ?></td>
												<input type="hidden" name="sr_no[]" class="input_sr" value="1">											
												<td class="group_chosen">
													<div style="width: 100% !important" class="input-group">
														<select name="item_group[]" onchange="group_item(this)" class="chosen-select form-control">
															<option> select Item Group</option>
															<?php
																$db->select('SELECT * FROM item_group where company_id='.getCompanyId()." order by id desc");
																	$group_results = $db->fetch_all();
																	foreach ($group_results as $group_result){
																		if ($group_result['id']==$p_t_contract['item_group']) {
																			$select="selected";
																		}else{
																			$select="";
																		} ?>
																<option id='<?php echo ($group_result['id']) ?>' value='<?php echo ($group_result['id']) ?>' <?php echo $select ?>> <?php echo $group_result['name'] ?>
																</option>
															<?php } ?>
														</select>
													</div>
												</td>
												<td>
													<div class="chosenWidth chosen">
														<div class="input-group">
															<select class="form-control chosen-select check_item selecteditem item_name" name="item_name[]" placeholder=" Enter Item Name" onchange="GetBrands(this)">
																<option> Select Item Name</option>
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
												</td>
												<td>
													<div class="chosenWidth chosen">
														<div class="form-group">
															<select class="form-control chosen-select item_brand" name="brand[]" placeholder=" Enter Item Name" onchange="getQuality(this)">
																<option> Select Brand </option>
																<?php
																	$db->select('SELECT * FROM item_brand where company_id='.getCompanyId());
																	$brands = $db->fetch_all();
																	foreach ($brands as $brand){?>
																		<option id='<?php echo ($brand['id']) ?>' value='<?php echo ($brand['id']) ?>'> <?php echo $brand['name'] ?> </option>
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
																	$db->select('SELECT * FROM item_quality where company_id='.getCompanyId());
																	$qualities = $db->fetch_all();
																	foreach ($qualities as $quality){?>
																		<option id='<?php echo ($quality['id']) ?>' value='<?php echo ($quality['id']) ?>'> <?php echo $quality['name'] ?> </option>
																<?php } ?>
															</select>
														</div>
													</div>
												</td>
												<td class="mat_modal">
													<div class="form-group">
														<span class="input-group-btn show_mt_modal" style="display: none;">
															<button class="btn btn-primary" type="button" onclick="showCarrMat(this)"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
														</span>
														<input type="number" name="purchase_rate[]" class="form-control purchase_rate rate" onkeyup="calculate_Total(this)" placeholder="Purchase Rate">
													</div>
												</td>
												<td>
													<div class="form-group">
														<input type="number" name="rate[]" class=" check_rate form-control" placeholder="Enter Rate">
													</div>
												</td>
												<td>
													<div class="input-group">
														<input type="number" name="commission[]" class="form-control" placeholder="Commission">
														<span class="input-group-addon no-gutter">
															<select style="padding: 5px" name="balance_category[]" class="balance_category">
																<option value="Rs">No.</option>
																<option value="%">%</option>
															</select>
														</span>
													</div>
												</td>
												
													<div class="appened_discount_hidden"><input type="hidden" class="discount" value="0">
												</div>
												<?php if ($pi_row['discount_mode']=='yes' && $p_t_contract['discount_mode']=="yes"){ ?>
												<td class="enable_multiple_discount">
													<div class="input-group table_inputs">
														<input type="number" name="discount[]" class="form-control discount" onkeyup="calculate_discount(this)" placeholder=" Enter Discount " required="true" value="<?php echo $p_t_contract['discount']; ?>">
														<span style="padding: 0px;" class="pd_right input-group-addon">
															<select onchange="calculate(this)" class="discount_type form-control" name="balance_category[]" style="width: auto;">
																<option <?php if($p_t_contract['balance_category']=="Rs"){ echo "selected";} ?> value="Rs">No</option>
																<option <?php if($p_t_contract['balance_category']=="%"){ echo "selected";} ?>  value="%">%</option>
															</select>
														</span>
													</div>
													<input type="hidden" class="check_percentage" value="no" name="check_percentage[]">
													<div class="individusl_discount"></div>
												</td>
												<td class="multiple_discount">
												
												</td>	
									
											<?php } else { ?> <td class="hidden enable_multiple_discount"><input type="hidden" class="discount" name="discount[]" value="0"></td>
												<td class="hidden multiple_discount"></td>
												<?php } ?>
										
											<td>
													<a class="btn btn-primary add_row" onclick="add_row(this)"><i class="fa fa-plus-circle" aria-hidden="true"></i></a>
													<a class="btn btn-danger" class="remove_row" onclick="remove_row(this)" ><i class="fa fa-minus-circle" aria-hidden="true"></i>
													</a>
												</td>
											</tr>
											<?php } ?>
										</tbody>
										<tfoot>
											<tr class="footer_bg">
												<td></td>
												<td></td>
												<td></td>
												<td colspan="2" style="text-align: center;"> Total </td>
												<input type="hidden" name="tot_quantity" class="tot_quantity" value="0">
												<input type="hidden" name="tot_rate" class="tot_rate" value="0">
																								<td></td>
												<input type="hidden" name="total" class="tot_amount" value="0">

												<td class="tot_rate">0</td>
												<td class="discount_heading hidden"></td>
												<td></td>
												<td></td>
											</tr>
										</tfoot>
									</table>
								</div>
								<div class="container-fluid" style="padding: 20px">
									<div class="col-md-6">
										<div class="form-group">
											<textarea name="narration" placeholder="Memo" class="form-control" style="border-radius: 1.375rem !important;padding:17px"></textarea>
										</div>
									</div>
									<div class="col-md-6">
										<div class="form-group" style="border:2px #d2d6de dotted;border-radius:15px;">
											<input type="file" class="text-center" name="attachment[]" style="margin: 0px auto; padding:27px" id="inp_file" multiple="">
										</div>
									</div>
									<div class="clearfix"></div>
									<div class="main_button" style="text-align:right; padding-bottom: 10px ;margin: 10px">
										<input type="submit" name="purchaseContract" value="Save Sale Contract" class="btn btn-primary">
										<button type="button" class="btn btn-danger"> Close Sale Contract </button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</section>
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

		<?php include("../includes/item-popup.php");?>
		<?php include("../includes/footer.php");?>
		<div class='control-sidebar-bg'></div>
	</div>
	<?php include("../includes/footer-jsfiles.php");?>
	<?php include("../includes/popups-validation.php");?>
	<script type="text/javascript">	
	$('input[type=checkbox][name=discount_mode]').change(function(){
			if ($(this).is(":checked")){
				var multiple_discount ='<div class="input-group table_inputs"><input type="number"  name="discount[]" class="form-control discount" onkeyup="calculate_discount(this)" placeholder=" Enter Discount " required="true" value="0"><span style="padding: 0px;" class="pd_right input-group-addon"><select onchange="calculate(this)" class="discount_type form-control" name="balance_category[]" style="width: auto;"><option selected value="Rs">No</option><option value="%">%</option></select></span></div><input type="hidden" class="check_percentage" value="no" name="check_percentage[]"><div class="individusl_discount"><input type="hidden" class="hidden_discount_amt" ><span></span></div>';
				var discount_rate='<input type="text" name="t_discount[]" class="calculated-discount">';
				$('.enable_multiple_discount').html(multiple_discount);
				$('.multiple_discount').html(discount_rate);
				$('.enable_multiple_discount').removeClass('hidden');
				$('.multiple_discount').removeClass('hidden');
				$('.rate').trigger("onkeyup");
				$('.discount_heading').removeClass('hidden');
				$('.appened_discount_hidden').html("");
			} else {
				var hidden_discount = '<input type="hidden" class="discount" value="0">';
				$('.appened_discount_hidden').html(hidden_discount);
				$('.enable_multiple_discount').html("");
				$('.enable_multiple_discount').addClass('hidden');
				$('.multiple_discount').addClass('hidden');
				$('.discount_heading').addClass('hidden');
				$('.rate').trigger("onkeyup");
			}
		});


		$(document).ready(function(){
			$('.chosen_party').chosen({width:"100%"});
			$('.chosen_brand').chosen({width:"100%"});
			$('.chosen_quality').chosen({width:"100%"});
			$('.chosen-discount').chosen({width:"100%"});
			var single_party_mode='<th style="width:5%">Sr #</th><th style="width:15%">Item Group</th><th style="width:15%">Item Name</th><th style="width:15%">Quantity</th><th style="width:20%">Rate</th><th style="width:15%">Amount</th><th style="width:15%">Action</th>';
			$('.change_head').html(single_party_mode);
			$('#discount_checkbox').click(function(){
				if($(this).prop("checked") == false){
					$('.dis_rate').addClass('hidden');
					$("#discount").prop("disabled", true);
					grand();
				}
				else if($(this).prop("checked") == true){
					$('.dis_rate').removeClass('hidden');
					$("#discount").prop("disabled", false);
					grand();
				}
			});
		});

		function calculate_net(val){
            var discount_in=$('.discount-type').val();
            
            var rate=$(val).parent().parent().parent().find(".rate").val();
            
            var discount_rate=$(val).parent().parent().parent().find(".disc").val();
           // var discount_rate=$(val).parent().parent().parent().find(".net-discount").val();
            var total_rate=0;
            
            if(discount_in=="%"){
                total_rate=rate/100*discount_rate;
                console.log("rate="+total_rate);
            
            }else{
               total_rate=rate-discount_rate;
            }
            $(val).parent().parent().parent().find(".net-discount").val(total_rate);

        }

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
					url: "ajax/location.php",
					type: "POST",
					data: {'form-data':form1.serialize()}
				}).done(function(msg) {
					$.ajax({
						url: "ajax/location.php",
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
			$(`.chosen-supplier`).prop('disabled', true).trigger("chosen:updated");
		});

		$(document).ready(function(){
			$("#contractForm").validate({
				submitHandler: function(form) {
					if (check_Entries()!==false){
						form.submit();
					}
				}
			})
		});

		function check_Entries(){
			result=true;
			
			var contractMode="";
			var contractMode = $(".contract_mode option:selected").val();
			if (contractMode=="" || contractMode=='0'){
				alert("Please Select Contract Mode");
					return result = false;
			}

			var validity_date="";
			var validity_date = $(".validity_date").val();
			if (validity_date=="" || validity_date=='0'){
				alert("Please Enter Validity Date");
					return result = false;
			}

			$(".project_id").each(function( i ) {
				project=parseFloat($(this).val());
				if(project==0 || isNaN(project)){
					alert("Please Select Project");
					return result=false;
				}
			});

			$(".item_name").each(function( i ) {
				item=parseFloat($(this).val());
				if(item==0 || isNaN(item)){
					alert("Please Select Item");
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

		function mode_supp_ven(val_this){

			var selectSupplier='<label> Supplier </label><select class="form-control supplier-single" name="supplier_id"><option> Select Supplier Name </option><?php $db->select("select * from ledger where active_supplier='yes' and company_id=".getCompanyId()." order by id desc"); $suppliers = $db->fetch_all();foreach ($suppliers as $supplier){?><option value="<?php echo $supplier['id']; ?>"> <?php echo $supplier['name']; ?></option><?php } ?> </select>';

			var selectVendor='<label> Vendor </label><select class="form-control chosen-transporter" name="vendor_id"><option> Select Vendor Name </option><?php $db->select("select * from ledger where active_transporter='yes' and company_id=".getCompanyId()." order by id desc");$transporters = $db->fetch_all();foreach ($transporters as $transporter){?><option value="<?php echo $transporter['id']; ?>"> <?php echo $transporter['name']; ?></option><?php } ?></select>';

			var selectMode='<label> Select Mode </label><select class="form-control"><option> Select Vendor Name </option></select>';

			var dlv_tran='<div class="form-check"><input class="form-check-input showCharges" name="delivery_term[]" onclick="getDelivery(this)" value="yes" id="delivery" type="checkbox"><input type="hidden" class="deliveryValue delivery_term delivery_term_name md_delivery inv_delivery_no" value="exdelivery" name="delivery[]"><label class="form-check-label"> Ex-Factory </label></div>';

			var supp_tran='<select class="form-control supplier-multiple" name="supplier_no[]"><option> Select Supplier Name </option><?php $db->select("select * from ledger where active_supplier='yes' and company_id=".getCompanyId()." order by id desc"); $suppliers = $db->fetch_all();foreach ($suppliers as $supplier){?><option value="<?php echo $supplier['id']; ?>"> <?php echo $supplier['name']; ?></option><?php } ?></select>';

			if ($(val_this).val()=="vendor"){
				$('.selectedMode').html("");
				$('.selectedMode').html(selectVendor);
				$('.dlv_th').addClass('hidden');
				$('.dlv').addClass('hidden');
				$(".chosen-transporter").chosen({width:"100%"});
				$('.dlv').html("");
				$('.supp').html("");
			} else if($(val_this).val()=="supplier"){
				$('.selectedMode').html("");
				$('.selectedMode').html(selectSupplier);
				$('.dlv_th').addClass('hidden');
				$('.dlv').addClass('hidden');
				$(".supplier-single").chosen({width:"100%"});
				$('.dlv').html("");
				$('.supp').html("");
			} else if ($(val_this).val()=="both_sup_veh"){
				$('.selectedMode').html("");
				$('.selectedMode').html(selectVendor);
				$('.dlv_th').removeClass('hidden');
				$('.supp_th').removeClass('hidden');
				$('.dlv').removeClass('hidden');
				$('.supp').removeClass('hidden');
				$('.dlv').html("");
				$('.supp').html("");
				$('.dlv').html(dlv_tran);
				$('.supp').html(supp_tran);
				$('.supp').html(supp_tran);
				$(".chosen-transporter").chosen({width:"100%"});
				$(".supplier-multiple").chosen({width:"100%"});
			}
			else { 
				$('.selectedMode').html(selectMode);
			}
		}

		function countQuantity(){
			var tot_quantity=0;
			$('.quantity').each(function(){
				tot_quantity += parseFloat($(this).val());
				$('.tot_quantity').val(tot_quantity);
				$('.tot_quantity').text(tot_quantity);
			});
		}

		function countRate(){
			var tot_rate=0;
			$('.rate').each(function(){
				tot_rate += parseFloat($(this).val());
				$('.tot_rate').val(tot_rate);
				$('.tot_rate').text(tot_rate);
			});
		}

		function calculate_Total(val_this){
			var rate=$(val_this).parent().parent().parent().find(".rate").val();
			var quantity=$(val_this).parent().parent().parent().find(".quantity").val();
			var total_amount=rate * quantity;
			$(val_this).parent().parent().parent().find(".amount").val(total_amount);
			var tot_amount=0;
			$('.amount').each(function(){
				tot_amount += parseFloat($(this).val());
				console.log(tot_amount);
				$('.tot_amount').val(tot_amount);
				$('.tot_amount').text(tot_amount);
			});
			
			countQuantity();
			countRate();
		}

		function freightMaterialRate(val_this){
			var freight_charge = parseFloat($(val_this).parent().parent().parent().find('.freight_charg').val());
			var material_charge =  parseFloat($(val_this).parent().parent().parent().find('.material_charg').val());
			var rate= freight_charge + material_charge;
			$(val_this).parent().parent().parent().parent().find('.rate').val(rate);
			$(val_this).parent().parent().parent().parent().find('.rate').trigger("keyup");
		}

		function chosenValue(val_this){
			var data_id = "";
			var data_id = $(val_this).val();
			$.ajax({
			url: '<?php echo ADMIN_URL; ?>ajax/data.php',
			method: 'POST',
			data: {data_id}
			}).done(function(units){
				units = JSON.parse(units);
				units.item_unit.forEach(function(unit){
					$(val_this).parent().parent().parent().parent().find('.qty_symbol').html("");
					$(val_this).parent().parent().parent().parent().find('.qty_symbol').html(unit.symbol);
					$(val_this).parent().parent().parent().parent().find('.qty_symbol_per').html(unit.symbol);
				});
				getPurchaseRate(val_this);
			});
		}

		function getDelivery(val_this){
			if($(val_this).is(":checked")==true){
				$(val_this).next('input.deliveryValue').val("exfactory");
				$(val_this).parent().parent().parent().find(".show_mt_modal").show();
				$(val_this).parent().parent().parent().find(`.supplier-select`).prop('disabled', false).trigger("chosen:updated");
				$(val_this).parent().parent().parent().find('.mat_modal').append('<input type="hidden" name="material[]" class="material_status" value="yes">');
			} else if($(val_this).is(":checked")==false){
				$(val_this).next('input.deliveryValue').val("exdelivery");
				$(val_this).parent().parent().parent().find(".show_mt_modal").hide();
				$(val_this).parent().parent().parent().find(`.supplier-select`).prop('disabled', true).trigger("chosen:updated");
				$(val_this).parent().parent().parent().find('.material_status').remove('.material_status');
				$(val_this).parent().parent().parent().find('.freight_charg').val(0);
				$(val_this).parent().parent().parent().find('.material_charg').val(0);
				$(val_this).parent().parent().parent().find('.material_charg, .freight_charg').addClass("hidden");
			}
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
			var date_input=$('.validity');
			var container=$('.bootstrap-iso form').length>0 ? $('.bootstrap-iso form').parent() : "body";
			date_input.datepicker({
				format: 'dd-mm-yyyy',
				container: container,
				todayHighlight: true,
				autoclose: true,
			});
		})

		function goBack(){
			window.history.back();
		}

		$(document).ready(function(){
			$('.chosen-select').chosen({width: "100%"});
			$('.chosen-transporter').chosen({width: "100%"});
			$('.chosen-supplier').chosen({width: "100%"});
		})

		function projectlocation(val_this){
			var project_id = "";
			project_id = $(val_this).val();
			if(project_id){
				$.ajax({
					type:'POST',
					url: "ajax/location.php",
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
		  
		function add_row(val_this){
			$(".chosen-select").chosen('destroy');
			$(".supplier-multiple").chosen('destroy');
			$(".pq_body").append($('.pq_row:first').clone());
			$('.pq_row:last').find('.quantity').val('');
			SR_NO=$('.pq_row:last').find('.sr_no').text();
			$('.pq_row:last').find('.rate_modal'+SR_NO).remove();
			$('.pq_row:last').find('.showCharges').removeAttr('checked');
			$('.pq_row:last').find('.chosen-select').val('');
			$('.pq_row:last').find('.show_mt_modal').hide();
			// $('.pq_row:last').find('.frightCharge').html();
			// $('.pq_row:last').find('.materialCharge').html("");
			$('.pq_row:last').find('.freight_charg').val(0);
			$('.pq_row:last').find('.material_charg').val(0);
			$('.pq_row:last').find('.freight_charg').addClass("hidden");
			$('.pq_row:last').find('.material_charg').addClass("hidden");
			$('.pq_row:last').find('.rate').val('');
			$('.pq_row:last').find('.md_delivery').val('exdelivery');
			$('.pq_row:last').find('.discount').val('0');
			$('.pq_row:last').find('.net_rate').val('');
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
			$(".chosen-select").chosen({width:"100%"});
			$(".supplier-multiple").chosen({width:"100%"});
		};
		
		function remove_row(val){
			$(val).parent().parent('tr').remove();
			countQuantity();
			countRate();
			calculate_Total();
		}

		function showCarrMat(val_this){
			material_status= $(val_this).parent().parent().parent().parent().find(".delivery_term").val();
			if(material_status=='exfactory'){
				$(val_this).parent().parent().parent().parent().find('.freight_charg').removeClass('hidden');
				$(val_this).parent().parent().parent().parent().find('.material_charg').removeClass('hidden');
			}
		}
		function addItem(){
			if(ite.valid()){
				$(".item-loading").show();
				$(".item-button").hide();
				$.ajax({
					url:"ajax/inventory-popup.php",
					type:"POST",
					data:{'item-data':ite.serialize()}
				}).done(function(msg){
					$.ajax({
						url: "ajax/inventory-popup.php",
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
				url: '<?php echo ADMIN_URL;?>sale/add-sale-contract.php',
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

		function calculate_discount(val){
			var net=0;
			var sr_no = $(val).parent().parent().parent().find('.input_sr').val();
			$('#discount').trigger('onkeyup');
			if($(val).parent().parent().parent().find('.discount_type').val() == '%') {
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().find('.discount').val());
				amount = discount*rate;
				percentage = amount / 100;
				net_rate = rate - percentage;
				individual = rate - net_rate;
				$(val).parent().parent().parent().find('.calculated-discount').val(individual);
				$(val).parent().parent().parent().find(".individusl_discount").text(individual);
				$(val).parent().parent().parent().find('.net_rate_area').text(net_rate);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(net_rate));
				qty = parseInt($(val).parent().parent().parent().find('.quantity').val());
				total_amt = rate * qty;
				amount = net_rate * qty;
				total_discount = total_amt - amount;
				rem_amount = total_amt - total_discount;
				$(val).parent().parent().parent().find('.amount').val(rem_amount);
				$(val).parent().parent().parent().find('.hidden_disc_amt_val').val(Number(total_discount));
				$(val).parent().parent().parent().find('.hidden_disc_amt').text('Discount: '+total_discount);
				$(val).parent().parent().parent().find('.hidden_discount').val(total_discount);
			}else{
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().find('.discount').val());
				disc = rate - discount;
				$(val).parent().parent().parent().find('.net_rate_area').text(disc);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(disc));
				qty = parseFloat($(val).parent().parent().parent().find('.quantity').val());
				total_amt = qty * rate;
				amount = disc * qty;
				total_discount = total_amt - amount;
				rem_amount = total_amt - total_discount;
				$(val).parent().parent().parent().find('.calculated-discount').val(individual);
				$(val).parent().parent().parent().find('.amount').val(rem_amount);
				$(val).parent().parent().parent().find('.hidden_disc_amt_val').val(Number(total_discount));
				$(val).parent().parent().parent().find('.hidden_disc_amt').text('Discount: '+total_discount);
				$(val).parent().parent().parent().find('.hidden_discount').val(total_discount);
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
			calculateTotalDiscount();
			modal = "SubItemModal"+sr_no;
			calculateCommission(modal);
			calculateTotalBoxes();
			calculateTotalTons();
		}

		function GetBrands(val_this){
			var item = $(val_this).val();
			$.ajax({
				url : 'add-sale-contract.php',
				type : 'post',
				data : {item,command:'getBrands'},
				success : function(data){
					$(val_this).closest('tr').find(".item_brand").html(data).trigger('chosen:updated');
				}
			});
		}

		function getQuality(val_this){
			var item = $(val_this).closest('tr').find(".check_item").val();
			var brand = $(val_this).val();
			var project_id=$('.sub_location').val();
			var location_id=$('select[name=location]').val();
			$.ajax({
				url : 'add-sale-contract.php',
				type : 'post',
				data : {item,brand,project_id,location_id,command:'getQualities'},
				success : function(data){
					$(val_this).closest('tr').find(".item_quality").html(data).trigger('chosen:updated');
				}
			});
		}

		function group_item(val_this){
			var group_id = "";
			group_id = $(val_this).val();
			if(group_id){
				$.ajax({
					type:'POST',
					url:'<?php echo ADMIN_URL?>ajax/item_name.php',
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
				$(val).parent().parent().parent().parent().find('.check_amount').val(amount);
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
				$(val).parent().parent().parent().parent().find('.check_amount').val(amount);
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
		function ContractType(val_this){
			if($(val_this).val()=='project' || $(val_this).val()=='distributer'){
				$(".customer-mode").hide();
				$(".customer").show();
			}else{
				$(".customer-mode").show();
				
			if ($('.customer-check').is(":checked")){
				$(".customer").show();
			}else{
				$(".customer").hide();
			}
			
		}
	}


		function getPurchaseRate(val_this){
			var item_id=$(val_this).closest('tr').find(".check_item").val();
			var item_quality=$(val_this).closest('tr').find(".item_quality").val();
			var head=$(".location").val();
			var sub_head=$(".sub_location").val();
			$.ajax({
				url  : 'add-sale-contract.php',
				data : {item_id,item_quality,head,sub_head,command:'getPurchaseRate'},
				type : 'post',
				success : function(data){
					$(val_this).parent().parent().parent().parent().find(".purchase_rate").val(data.trim());
				}
			});
		}
	</script>
</body>
</html>