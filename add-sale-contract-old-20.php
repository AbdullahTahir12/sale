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
	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getArticles'){
		$code = $_REQUEST['code'];
		$item_id = $_REQUEST['item'];
		$brand = $_REQUEST['brand'];
		$quality = $_REQUEST['quality'];
		$location = $_REQUEST['location_id'];
		$sub_location = $_REQUEST['project_id'];
		$db->Select("SELECT ar.* FROM quotation_inventory as qi left join article ar on ar.id = qi.article_id left join sub_item as sb on qi.sub_item_id = sb.id  where qi.quality_id=".$quality." and qi.item_id=".$item_id." and sb.brand_id = ".$brand." and location_id = ".$location." and sub_location_id= ".$sub_location);
		$articles = $db->fetch_all();
		$article_li.='<option value="0"> Select Article </option>';
		foreach ($articles as $article){
		 	$article_li.='<option value="'.$article['id'].'">'.$article['name'].'</option>';
		}
		echo $article_li;
		exit();
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getBrands'){
		$item_id = $_REQUEST['item'];
		$db->Select("select itb.* from item_brand as itb right join sub_item as sb on (sb.brand_id = itb.id) where sb.item_id = ".$item_id." and FIND_IN_SET('".getCompanyId()."',itb.company_id)  group by itb.name");
		$brands = $db->fetch_all();
		$brand_li.='<option value="" selected disabled> select Brands </option>';
		$brand_li.='<option value="0"> All Brands </option>';
		foreach ($brands as $brand){
		 	$brand_li.='<option value="'.$brand['id'].'">'.$brand['name'].'</option>';
		}
		echo $brand_li;
		exit();
	}

	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getQualities'){
		$item_id = $_REQUEST['item'];
		$brand = $_REQUEST['brand'];
		
		$location_id = $_REQUEST['location_id'];
		$project_id = $_REQUEST['project_id'];
		if($brand == 0){
			$db->Select("select qi.quality_id as q_id from quotation_inventory as qi group by qi.quality_id");		
		$qualities = $db->fetch_all();
		}else{
			$db->Select("Select qi.quality_id as q_id from quotation_inventory as qi left join sub_item as sbi on (qi.item_id = sbi.item_id and qi.sub_item_id = sbi.id) where qi.item_id = ".$item_id." and sbi.brand_id = ".$brand." and location_id = ".$location_id." and sub_location_id = ".$project_id." group by qi.quality_id");		
		$qualities = $db->fetch_all();
		}		
		$quality_li.='<option value="0"> Select Quality </option>';
		
		foreach ($qualities as $quality){
			$qualiti = $db->fetch_array_by_query("select * from item_quality where id=".$quality['q_id']);
		 	$quality_li.='<option value="'.$quality['q_id'].'">'.$qualiti['name'].'</option>';
		}
		echo $quality_li;
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
	function check_voucher_no(){
		global $db;
		$sale_contract_last = $db->fetch_array_by_query("SELECT * FROM sale_contract where company_id=".getCompanyId()." ORDER BY ID DESC LIMIT 1");
		$contract_no = $sale_contract_last['sale_contract_no']+1;
		return $contract_no;
	}
	if(isset($_REQUEST['command']) && $_REQUEST['command'] == 'getPurchaseRate'){
		$purchase = $db->fetch_array_by_query("select pit.actual_rate as rate from purchase_invoice_inventory as pii ,purchase_invoice as pi, p_invoice_transaction as pit where pii.receipt_id = pi.id and pii.receipt_transaction_id = pit.id and pit.item_id=".$_REQUEST['item_id']." order by pi.id desc");
		echo $purchase['rate'];
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
		/* echo "<pre>";
		 print_r($_POST);
		 die();*/
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
		if(isset($_POST['contract_type']) && $_POST['contract_type'] == 'retail_sale'){
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
		
		$contract_id = $db->insert($arr,'sale_contract');
		
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
				if($receipt_trans_id > 0){
					$sr_no = $_POST['sr_no'][$i];
					
                if(isset($_REQUEST['Sub_item_id'.$sr_no]) && $_REQUEST['Sub_item_id'.$sr_no] != ''){	
					
					foreach($_REQUEST['Sub_item_id'.$sr_no] as $subItemIndex => $subItemId){
						$ary = array();
						$ary['sub_item_id'] = $subItemId;
						$ary['contract_id'] = $contract_id;
						$ary['c_transaction_id'] = $receipt_trans_id;
						$ary['item_id'] = $_POST['item_name'][$i];
						$ary['article_id'] = intval($_POST['Sub_item_article'.$sr_no][$subItemIndex]);
						$ary['created_at'] = time();
						$ary['updated_at'] = time();
						$ary['company_id'] = getCompanyId();
						$ary['user_id'] = getUSerId();
						$ary_contract_inventory=$db->insert($ary,'s_contract_inventory');
						$result = $ary_contract_inventory;
						// echo "<pre>";
						// echo "<br>";
						// print_r($ary);
					}	
                    //die();
				}
				}				
			}
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
										<!-- <div class="col-sm-2">
										    <div class="form-group">
												<label>Subitem: </label>
												<div class="form-group">
													<label><input type="checkbox" onclick="EnableCommission(this)" name="commission_check" class="commission_check" value="yes"></label>
												</div>		
										    </div>
										</div> -->
									</div>
									<div class="clearfix"></div>
									<div class="col-md-12">
										<div class="col-md-6 no-gutter">
											<div class="col-md-6 location_div">
												<?php 
												$db->Select("Select * from item_location where FIND_IN_SET(".getCompanyId().",company_id)");
												$locations = $db->fetch_all();
												?>
												<label>Location</label>
												<select class="form-control chosen-select location_id" onchange="get_sub_location(this)" name="location">
													<option value="">Select Location</option>
													<?php
													foreach($locations as $loc){
													?>
													<option value="<?php echo $loc['id']; ?>"><?php echo $loc['name']; ?></option>
													<?php
													}
													?>
												</select>
											</div>
											<div class="col-md-6 location_div">
												<label>Sub Location</label>
												<select class="form-control sub_location chosen-select" name="sub_location">
													<option value="">Select Sublocation</option>
												</select>
											</div>
											<div class="clearfix"></div>
										</div> 
										<div class="col-md-6 no-gutter">
											<div class="col-md-6 ">
												<label>Contract Type</label>
												<select onchange="ContractType(this)" class="form-control chosen-select" name="contract_type">
													<option value="retail_sale">Retail Sale</option>
													<option value="whole_sale">Whole Sale</option>
													<option value="company_sale">Company Sale</option>
												</select>
											</div>

											<div class="col-md-6 customer">
												<label>Customer</label>
												<select class="form-control chosen-select" name="customer">
													<option value="">Select Sale Type</option>
													<?php
													$db->Select("select * from ledger where active_customer='yes' and company_id = ".getCompanyId());
													$customers = $db->fetch_all();
													foreach($customers as $c){
													?>
													<option value="<?php echo $c['id']?>"><?php echo $c['name']?></option>
													<?php } ?>
												</select>
											</div>
											<div class="clearfix"></div>
										</div>
										<div class="clearfix"></div>
									</div>			
								</div>
								<div class="table-responsive">
									<table class="table table-striped pq_table">
										<thead>
											<tr class="title_bg">
												<th style="width:10%">Sr #</th>
												<th style="width:10%">Item Group</th>
												<th style="width:10%">Item Name</th>
												<th style="width:10%">Brand</th>
												<th style="width:10%">Quality</th>
												<th style="width:15%">Rate</th>
												<th style="width:15%">Commission</th>
												<th style="width:15%">Purchase Rate</th>
												<th style="width:5%">Action</th>
											</tr>
										</thead>
										<tbody class="pq_body">
											<?php
											$sr_no = 1;?>
											<tr class="pq_row">
												<td class="sr_no_tab1 sr_no pq_dr_no"><?php echo $sr_no++ ?></td>
												<input type="hidden" name="sr_no[]" class="input_sr" value="1">											
												<td class="group_chosen ">
													<div style="width: 100% !important" class="input-group">
														<select name="item_group[]" onchange="group_item(this)" class="chosen-select form-control">
															<option> select Item Group</option>
															<?php
																$db->select('SELECT * FROM item_group where company_id='.getCompanyId()." order by id desc");
																	$group_results = $db->fetch_all();
																	foreach ($group_results as $group_result){
																		if ($group_result['id']==$purchaseDetai8l['item_group']) {
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
												<td class="brand_qual_modal">
													<div class="chosenWidth chosen ">
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
													<div class="brand_md_button"></div>
												</td>
												<td>
													<div class="chosenWidth chosen ">
														<div class="input-group">
															<select class="form-control chosen-select  item_brand" name="brand[]" placeholder=" Enter Item Name" onchange="getQuality(this)">
																<option> Select Brand</option>
																<option value="0"> All Brands</option>
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
														<div class="input-group">
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
													<div class="input-group">
														<span class="input-group-btn show_mt_modal" style="display: none;">
															<button class="btn btn-primary" type="button" onclick="showCarrMat(this)"><i style="padding-left:0px !important;" class="fa fa-plus" aria-hidden="true"></i></button>
														</span>
														<input type="number" name="rate[]" class="form-control check_rate rate" onkeyup="calculate_Total(this)" placeholder="Rate">
														<span class="input-group-addon"><strong>P/</strong><b class="qty_symbol_per">unit</b></span>
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
												<td>
													<div class="form-group">
														<input type="number" name="purchase_rate[]" class="purchase_rate form-control" placeholder="Purchase Rate">
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
												<td></td>
												<td></td>
												<td></td>
												<td colspan="2" style="text-align: center;"> Total </td>
												<input type="hidden" name="tot_quantity" class="tot_quantity" value="0">
												<input type="hidden" name="tot_rate" class="tot_rate" value="0">
												<td class="tot_rate">0</td>
												<input type="hidden" name="total" class="tot_amount" value="0">
												<td></td>
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
											<div class="form-group ">
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
									<table class="table table-striped table-bordered SubItemTable">
										<thead>
											<tr>
												<th>Code</th>
												<th  class="article_td">Article</th>											
												<th>Action</th>
											</tr>
											
										</thead>
										<tbody class="SubItemBody">
											<tr class="SubItemRow">
												<td>
												    <div class="form-group">
														<select onchange="getArticles(this)" class=" SubItemCode chosen-code chosen-select form-control" >
															<option value="0"> Select Subitem code</option>
														</select>
														<h4 class="SubItemCodeText"></h4>
														<input type="hidden" class="SubITemID" >
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
															foreach($articles as $article){
															?>
															<option value="<?php echo $article['id']; ?>"><?php echo $article['name']; ?></option>
															<?php } ?>
														</select>
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
												<td>Total</td>
												<td class="quantity_td"><span class="quantity_span"><b class="quantity_b">0</b></span> <input type="hidden" class="total_auantity" ></td>
												<td class="boxes_td"><span class="boxes_span"><b class="boxes_b">0</b></span> <input type="hidden" class="total_boxes"></td>
												<td class="tons_td hidden"><span class="tons_span"><b class="tons_b">0</b></span> <input type="hidden" class="total_tons"></td>
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

		<?php include("../includes/item-popup.php");?>
		<?php include("../includes/footer.php");?>
		<div class='control-sidebar-bg'></div>
	</div>
	<?php include("../includes/footer-jsfiles.php");?>
	<?php include("../includes/popups-validation.php");?>
	<script type="text/javascript">	

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
			
			if($(".sale_type").val()=='company'){
				var location=$(".debit_head option:selected").val();
				if (location=="" || location=='0'){
					alert("Please Select Debit Location");
						return result=false;
				}

				var slocation=$(".debit_subhead option:selected").val();
				if (slocation=="" || slocation=='0'){
					alert("Please Select Debit Sub Location");
					return result=false;
				}
			}else{
				var Vendor=$('.inv_transporter_id option:selected').val();
				if (Vendor=="" || Vendor=='0'){
					alert("Please Select Customer");
					return result=false;
				}
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
			var project_id=parseInt($('.location_div .sub_location').val());
			var location_id=parseInt($('.location_div .location_id').val());
			var data_id = "";
			var data_id = $(val_this).parent().parent().parent().parent().find(".check_item").val();
			if(project_id=="0" || project_id== null || isNaN(project_id)){
				alert("Kindly select Sub location.THANKS");
			}else if(location_id=="0" || location_id == null || isNaN(location_id)){
				alert("Kindly Select Locatoin.THANKS");
			} else {
				$.ajax({
				url: '<?php echo ADMIN_URL; ?>ajax/data.php',
				method: 'POST',
				data: {data_id},
				}).done(function(units){
				
					checkSubItems(val_this,project_id,location_id);
					units = JSON.parse(units);
					units.item_unit.forEach(function(unit){
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
        
		function checkSubItems(val_this,project_id,location_id){
			sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
			var item_id = $(val_this).parent().parent().parent().parent().find(".check_item").val();
			var brand_id = $(val_this).parent().parent().parent().parent().find(".item_brand").val();
			var quality_id = $(val_this).parent().parent().parent().parent().find(".item_quality").val();
			$.ajax({
				url : 'add-sale-contract.php',
				Type : 'post',
				data : {item_id,command:'checkSubItems'},
				success : function(result){
					result = result.trim();
					if(result == 'yes'){
						ShowSubItemModal(val_this);
						$.ajax({
							url: "<?php echo ADMIN_URL; ?>ajax/get-item-brand.php",
							type: "POST",
							data: {'item':'Add_item',item_id,brand_id,quality_id,project_id,location_id},
						}).done(function(result) {
							res = $.parseJSON(result);
							if(res.sub_item == 'yes'){
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
								if(res.item_color != 'no'){	
									$(val_this).parent().parent().parent().parent().find('.color_td').show();	
									$(val_this).parent().parent().parent().parent().find('.selectcolor').html("");
									$(val_this).parent().parent().parent().parent().find('.selectcolor').html(res.item_color);
									$(val_this).parent().parent().parent().parent().find('.chosen_color').trigger("chosen:updated");
								}else{
									$(val_this).parent().parent().parent().parent().find('.selectcolor').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.color_td').hide();	
								}


								if(res.article != 'no'){
									$(val_this).parent().parent().parent().parent().find('.article_td').show();		
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').chosen({width:'100%'});	
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').html("");
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').html(res.article);
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').trigger("chosen:updated");
								}else{
									$(val_this).parent().parent().parent().parent().find('.SubItemArticle ').val("").trigger("chosen:updated");
									$(val_this).parent().parent().parent().parent().find('.article_td').hide();	
								}


								if(res.item_unit!=''){
									$(val_this).parent().parent().parent().find(".qty_symbol").text(res.item_unit);
								}

								// forSubItemCode	
								if(res.SubItemCode != ''){	
									$(val_this).parent().parent().parent().parent().find('.SubItemCode').html("");
									$(val_this).parent().parent().parent().parent().find('.SubItemCode').html(res.SubItemCode);
									$(val_this).parent().parent().parent().parent().find('.SubItemCode').trigger("chosen:updated");
								}

								$('.SubItemModal'+sr_no).modal('show');
							}
						}).fail(function(jqXHR, textStatus) {
							alert( "Request  are failed:" + textStatus );
						});
					}else{
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
		var item_id = $(val_this).val();
		var item_group = $(val_this).parent().parent().parent().parent().find(".item_group option:selected").text();
		var item = $(val_this).parent().parent().parent().find(".check_item option:selected").text();
		sr_no = $(val_this).parent().parent().parent().parent().find(".sr_no").html();
		$(val_this).parent().parent().parent().parent().find(".sub_item").val("yes");
		if($('.SubItemModal'+sr_no).html()!=undefined){
			
			$('.SubItemModal'+sr_no).find(".head").val(location_id);
			$('.SubItemModal'+sr_no).find(".sub_head").val(sublocation_id);
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
			$('.SubItemModal'+sr_no).find(".modal-title").text(item_group+'( '+item+' )');
			$('.SubItemModal'+sr_no).find('.inv_section').attr('onchange',getSaleItemQuantity);
			$('.SubItemModal'+sr_no).find('.removeRowBtn').attr('onclick','removeSubItemrow(this,"'+modal_no+'")');
			ChangeSubItemModalNAmes(modal_no,sr_no);
			$('.SubItemModal'+sr_no).find('.showQ').attr('onchange','QuantityValue("'+modal_no+'",this)');
			$('.SubItemModal'+sr_no).find('.sub-item-quantity').attr('onkeyup','QuantityAdd(this,"'+modal_no+'")');
			$('.SubItemModal'+sr_no+" .brand-quality-button").append('<button type="button" class="btn btn-primary pull-right" onclick=saveSubItemSpecifications("'+modal_no+'")> Save </button>');
			specificationsBtn();
			// $('.SubItemModal'+sr_no).modal('show');
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
			
			
			$("."+modal_no).modal("hide");
			
		}

		function ChangeSubItemModalNAmes(modal_no,sr){
			$("."+modal_no+" .SubItemCode").attr("name","Sub_item_code"+sr+'[]');
			$("."+modal_no+" .SubITemID").attr("name","Sub_item_id"+sr+'[]');
			$("."+modal_no+" .chosen_article").attr("name","Sub_item_article"+sr+'[]');
			$("."+modal_no+" .inv_warehouse").attr("name","Sub_item_warehouse"+sr+'[]');
			$("."+modal_no+" .inv_section").attr("name","Sub_item_section"+sr+'[]');
			$("."+modal_no+" .inv_color").attr("name","Sub_item_color"+sr+'[]');
			$("."+modal_no+" .inv_quality").attr("name","Sub_item_quality"+sr+'[]');
			$("."+modal_no+" .sub-item-quantity").attr("name","Sub_item_value"+sr+'[]');
			$("."+modal_no+" .sub-item-unit").attr("name","Sub_item_unit"+sr+'[]');
			$("."+modal_no+" .inv_quantity").attr("name","Sub_item_quantity"+sr+'[]');
			$("."+modal_no+" .inv_boxes").attr("name","Sub_item_boxes"+sr+'[]');
			$("."+modal_no+" .inv_tons").attr("name","Sub_item_tons"+sr+'[]');
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
        
		
		function getArticles(val_this){
		var code = $(val_this).val();
		var item = $(val_this).closest(".brand_qual_modal").find(".check_item").val();
		var brand = $(val_this).closest(".brand_qual_modal").parent().find(".item_brand").val();
		var quality = $(val_this).closest(".brand_qual_modal").parent().find(".item_quality").val();
		var project_id=$('.sub_location').val();
		var location_id=$('.location_id').val();
		$.ajax({
			url:'add-sale-contract.php',
			data : {code,item,brand,project_id,quality,location_id,command:'getArticles'},
			type : 'POST',
			success : function(data){
				$(val_this).parent().parent().parent().find(".SubItemArticle").html(data).trigger("chosen:updated");
				CheckSubItem(val_this);
			}
		})
	}
	function CheckSubItem(val_this){
			var SubItemCode = $(val_this).parent().parent().parent().find(".SubItemCode").val();
			var SubItemArticle = $(val_this).parent().parent().parent().find(".SubItemArticle").val();
			var ItemID = $(val_this).closest(".brand_qual_modal").find(".check_item").val();
			$.ajax({
				url : 'add-sale-contract.php',
				type : 'post',
				dataType : 'json',
				data : {SubItemCode,SubItemArticle,ItemID,command:'CheckIngSubItem'},
				success : function(sub_item){
					if(sub_item[0]!= 'fail'){
						var html = ''
						if(sub_item.subItemimgs!=''){
							sub_item.subItemimgs.forEach(function(images){
							html += '<div class="col-sm-6" style="padding-left:0"><a href="<?php echo BASE_URL."admin/itemImages/"?>'+images+'" target="blank" ><img style="width:100%;height:50px" src="<?php echo BASE_URL."admin/itemImages/"?>'+images+'"></a></div>';
							});
						}
						$(val_this).parent().parent().parent().find(".SubITemID").val(sub_item.id);
						$(val_this).parent().parent().parent().find(".img_td div ").html(" ");
						$(val_this).parent().parent().parent().find(".img_td div ").html(html);
					}else{
						// alert("Sorry! No Sub Item Found");
						$(val_this).parent().parent().parent().find(".SubITemID").val('');
						$(val_this).parent().parent().parent().find(".img_td div").html('<h4>No Sub Item Images</h4>');
					}
					
				}
			}).fail(function(jqXHR, textStatus) {
				alert("Sorry! No Sub Item Found");
				$(val_this).parent().parent().parent().find(".SubItemCodeText").text('');
				$(val_this).parent().parent().parent().find(".SubITemID").val('');
			});
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
        
		function specificationsBtn(){
		$(".pq_table tbody tr").each(function(){
			var sr = $(this).find(".sr_no").html();
			if($(this).find(".SubItemModal"+sr).html() != undefined){
				modal_no = 'SubItemModal'+sr;
				$(this).find(".brand_md_button").html('<a data-toggle="modal" data-target=".'+modal_no+'"class="md-btn specification-toggle" data-keyboard="false" data-backdrop="static"> SubItem Specifications </a>');
			}
		});
	}
	    
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
			$('.pq_row:last').find('.SubItemModal'+SR_NO).remove();
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
			$('#discount').trigger('onkeyup');
			if($(val).parent().parent().find('.discount_type').val() == '%') {
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
				$(val).parent().parent().parent().find('.check_amount').val(amount);
			}else{
				rate = parseFloat($(val).parent().parent().parent().find('.rate').val());
				discount =  parseFloat($(val).parent().parent().parent().find('.discount').val());
				disc = rate - discount;
				console.log(rate);
				console.log(discount);
				console.log(disc);
				$(val).parent().parent().parent().find('.net_rate_area').text(disc);
				parseFloat($(val).parent().parent().parent().find('.net_rate').val(disc));
				qty = parseFloat($(val).parent().parent().parent().find('.quantity').val());
				amount = disc * qty;
				$(val).parent().parent().parent().find('.check_amount').val(amount);
				$(val).parent().parent().parent().find(".individusl_discount").text("");
			}
			total = 0;
			// var discount_amount=0;
			// $('.amount').each(function(){
			// 	total += parseFloat($(this).val());
			// 	$('#total').val(total);
			// 	$('.total').text(total);
			// 	discount_amount=$('#discount').val();
			// 	if (discount_amount==0 || isNaN(discount_amount)){
			// 		$('#net').val(total);
			// 		$('.net').val(total);
			// 		grand();
			// 	}
			// })
			// var tot_quantity=0;
			// $('.quantity').each(function(){
			// 	tot_quantity += parseFloat($(this).val());
			// 	$('.tot_quantity').val(tot_quantity);
			// 	$('.tot_quantity').text(tot_quantity);
			// })
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
			if($(val_this).val()=='retail_sale'){
				$(".customer").show();
			}else{
				$(".customer").hide();
			}
		}

        function AddSubItemRow(val_this){
			$(".chosen-select").chosen("destroy");
			var cloneRow = $(val_this).parent().parent().clone();
			$(val_this).parent().parent().parent().append(cloneRow);
			$(val_this).parent().parent().parent().find("tr:last input").val('');
			$(val_this).parent().parent().parent().find("tr:last .chosen_article").val('');
			$(val_this).parent().parent().parent().find("tr:last .chosen-code ").val('');
			$(val_this).parent().parent().parent().find("tr:last .sub-item-unit").val('sqm');
			$(val_this).parent().parent().parent().find("tr:last h4").text('');
			$(val_this).parent().parent().parent().find("tr:last h5").html('');
			$(".chosen-select").chosen({width:'100%'});
		}

		function getPurchaseRate(val_this){
			var item_id=$(val_this).val();
			$.ajax({
				url  : 'add-sale-contract.php',
				data : {item_id,command:'getPurchaseRate'},
				type : 'post',
				success : function(data){
					$(val_this).parent().parent().parent().parent().find(".purchase_rate").val(data.trim());
				}
			});
		}
	</script>
</body>
</html>