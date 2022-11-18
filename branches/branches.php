<?php
include("../../includes/common-files.php");
$a->authenticate();
$page_title = "Branches";
// $tab = " Sale Master";
$url_photo = "pics/";


if (isset($_POST['command']) && $_POST['command'] == 'Add') {

    $arr = array();
    $arr['city_id'] = intval($_POST['city_name']);
    $arr['branch_name'] = $_POST['branch_name'];
    $arr['contact_person'] = $_POST['contact_person'];
    $arr['phone'] = $_POST['phone'];
    $arr['address'] = $_POST['address'];
    $arr['multicompany'] =  intval($_POST['multicompany']);

    if (isset($_FILES['pic']) && $_FILES['pic']['name'] != '') {
        $obj_upload = load_class('UploadImage');
        $uploadName = time() . rand();
        $resultFile = $obj_upload->upload_files($_FILES["pic"], $url_photo, $uploadName);
        if ($resultFile) {
            $photo_name = $obj_upload->get_image_name();
        }
        $arr['image'] = $photo_name;
    }

    $arr['company_name'] = $_POST['company_name'];
    $arr['created_at'] = time();
    $arr['updated_at'] = time();
    $arr['user_id'] = getUserId();
    $arr['company_id'] = getCompanyId();
    $res = $db->insert($arr, 'branches');
    if ($res) {
        $imsg->setMessage("'Record Added Successfully'");
        redirect_header("add-branches.php");
    }
}


if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'delete' && isset($_REQUEST['id']) && intval($_REQUEST['id']) > 0) {
    $result = true;
    $row = $db->fetch_array_by_query("select * from branches where city_id =" . intval($_REQUEST['city_name']));
    if ($row) {
        $imsg->setMessage("'Record Entry Exist. Try Again'", 'error');
        redirect_header("add-branches.php");
    } else {
        $result = $db->query("delete from branches where id=" . intval($_REQUEST['id']));
        if ($result) {
            $imsg->setMessage("'Record Deleted Successfully'");
            redirect_header("add-branches.php");
        }
    }
}
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'edit') {
    $id = intval($_REQUEST['id']);
    $updated_row = $db->fetch_array_by_query("select * from branches where id=" . $id);
    if (!$updated_row) {
    }
}

?>
<!DOCTYPE html>
<html>

<head>
    <?php include("../../includes/common-header.php"); ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/voucher.css?v=7.3" type="text/css" />
    <style type="text/css">
        .form-control {
            border-radius: 5px !important
        }

        #data-table {
            margin-top: 50px;
        }

        th {
            background-color: #7f9d9d;
            color: white;
        }

        hr {
            background-color: red;
            padding: 1px;
        }

        .modal-footer {
            margin-top: 30px;
        }

    </style>
</head>

<body class="skin-green-light sidebar-mini">
    <div class="wrapper">
        <?php include("../../includes/header.php"); ?>

        <?php $db->select("SELECT * FROM `ledger` where active_project='yes' and company_id=" . getCompanyId());
        $ledgers = $db->fetch_all(); ?>
        <form method="post" enctype="multipart/form-data" id='contractForm' name="form" autocomplete="off">
            <input type="hidden" name="command" value="add">
            <div class="append_image"></div>
            <div class="wrapper">
                <?php include("includes/header.php"); ?>
                <div class="content-wrapper">
                    <section class="content-header">
                        <h1 style="font-size:30px;"><?php echo ucfirst($tab); ?> <span class="small text-white " style="color:grey;"><?php echo $page_title; ?></span></h1>
                        <ol class="breadcrumb">
                            <li><a href="<?php echo ADMIN_URL; ?>"><i class="fa fa-dashboard"></i> Home</a></li>
                            <li class="active"><?php echo $page_title; ?></li>
                        </ol>
                    </section>
                    <section class="content">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="row clearfix">
                                    <div class="span12"> <?php echo $imsg->getMessage(); ?> </div>
                                </div>
                                <div class="box box-danger">
                                    <div class="box-header">
                                        <h3 class="box-title pull-left"><?php echo $page_title; ?></h3>
                                        <!--<a href="<?php echo ADMIN_URL ?>add-cost-category.php" class="btn btn-primary pull-right">Add Item Group </a>
										-->
                                        <button type="button" class="btn btn-primary pull-right" data-toggle="modal" data-target="#add_branches">Add Branches</button>
                                        <div class="box-body">
                                            <table class="table table-striped table-bordered" id="data-table">
                                                <thead>
                                                    <tr style="">
                                                        <th class="hidden-xs hidden-sm">#</th>
                                                        <!-- <th>Head</th> -->
                                                        <!-- <th>Sub Head</th> -->
                                                        <th>City Name</th>
                                                        <th>Branch Name</th>
                                                        <th>Contact Person</th>
                                                        <th>Phone</th>
                                                        <th>Address</th>
                                                        <th>Multicompany</th>
                                                        <!-- <th>Image</th> -->
                                                        <th>Company Name</th>
                                                        <th class="td-actions">Options</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $db->select('select * from branches where company_id=' . getCompanyId());
                                                    $branches_data = $db->fetch_all();
                                                    $i = 1;
                                                    if ($branches_data) {
                                                        foreach ($branches_data as $data) { ?>
                                                            <tr>
                                                                <td class="hidden-xs hidden-sm"><?php echo $i++; ?></td>
                                                                <?php
                                                                // $head = $db->fetch_array_by_query("select * from item_location where id=" . $data['head']);
                                                                ?>
                                                                <!-- <td><?php //echo $head['name'] 
                                                                            ?></td> -->
                                                                <?php
                                                                // $sub_head = $db->fetch_array_by_query("select * from item_sublocation where id=" .  $data['sub_head']);
                                                                ?>
                                                                <!-- <td><? php // echo $sub_head['name'] 
                                                                            ?></td> -->
                                                                <?php
                                                                $city = $db->fetch_array_by_query("select * from cities where id=" . $data['city_id']);
                                                                ?>
                                                                <td><?php echo $city['name'] ?></td>
                                                                <td><?php echo $data['branch_name'] ?></td>
                                                                <td><?php echo $data['contact_person'] ?></td>
                                                                <td><?php echo $data['phone'] ?></td>
                                                                <td><?php echo $data['address'] ?></td>
                                                                <?php
                                                                $multi_company = $db->fetch_array_by_query("select * from companies where id=" . $data['multicompany']);
                                                                ?>
                                                                <td><?php echo $multi_company['name'] ?></td>
                                                                <!-- <td><?php //echo $data['image'] ?></td> -->
                                                                <td><?php echo $data['company_name'] ?></td>
                                                                <td class="td-actions">
                                                                    <a href="edit-branches.php?id=<?php echo $data['id'] ?>&command=edit" class="btn btn-sm btn-primary">
                                                                        <i class=" glyphicon glyphicon-edit"> Edit </i>
                                                                    </a>
                                                                    <?php if (check_permission($auth_id, 'add-branches.php?command=delete', false)) { ?>
                                                                        <a href="<?php echo ADMIN_URL . 'sale/branches/add-branches.php?command=delete&id=' . $data['id']; ?>" onClick="return confirm('Are you sure? You want to delete this record?')" class="btn btn-danger btn-small"> <i class="btn-icon-only glyphicon glyphicon-remove" title="delete"> </i> </a><?php
                                                                                                                                                                                                                                                                                                                                                                    } ?>
                                                                </td>
                                                            </tr>
                                                        <?php
                                                        }
                                                    } else { ?>
                                                        <tr>
                                                            <td colspan="7" style="text-decoration:underline;text-align:center;font-size:16px;">Sorry! No Record Found!</td>
                                                        </tr><?php
                                                            } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <!--  __________________Add Branches modal______________ -->

                <div id="add_branches" class="tenant_modal modal fade" role="dialog">
                    <div class="modal-dialog modal-md" style="width: 67%;">
                        <!-- Modal content-->
                        <div class="modal-content">
                            <div class="form-header text-black" style="background-color:#7f9d9d;padding:5px;">
                                <h1>
                                    <span class="small text-white " style="color:white;margin-left:10px;">Add <?php echo $page_title; ?></span>
                                </h1>
                            </div>
                            <hr>
                            <div class="modal-body">
                                <div class="emp_sel">
                                    <form method="get" name="select_filter" id="select_filter" style="padding: 0px 0px 0px 0px; " enctype="multipart/form-data">
                                        <div class="row">
                                            <!-- <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="branch_name">Head</label>
                                                <select name="locat_id" class="sel_pro form-control location set_chosen" onchange="location_change(this)" id="locat_id">
                                                    <option value="0">Select Head</option>
                                                    <?php
                                                    // $db->select('select id,name from item_location where company_id=' . getCompanyId());
                                                    // $locations = $db->fetch_all();
                                                    // foreach ($locations as $location) {
                                                    ?>
                                                        <option value="<?php //echo $location['id'] ?>"><?php //echo $location['name'] ?></option>
                                                        <?php //if (intval($_REQUEST['locat_id']) > 0) { ?>
                                                            <script>
                                                                document.select_filter.locat_id.value = <?php// echo intval($_REQUEST['locat_id']); ?>
                                                            </script>
                                                    <?php //}
                                                    //} ?>
                                                </select>
                                            </div> -->
                                            <!-- <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="branch_name">Sub Head</label>
                                                <select class="sel_dep form-control set_chosen" name="sub_location" style="border-radius: 5px !important" id="sub_location">
                                                    <option value="0">All Sub Head</option>
                                                    <?php
                                                    // $db->select('select id,name from item_sublocation where company_id=' . getCompanyId() . " and location_id=" . intval($_REQUEST['locat_id']));
                                                    // $sub_locations = $db->fetch_all();
                                                    // foreach ($sub_locations as $sub_loca) { 
                                                        ?>
                                                        <option value="<?php//echo $sub_loca['id'] ?>"><?php //echo $sub_loca['name'] ?></option>
                                                        <?php //if (intval($_REQUEST['sub_location']) > 0) { ?>
                                                            <script>
                                                                document.select_filter.sub_location.value = <?php //echo intval($_REQUEST['sub_location']); ?>
                                                            </script>
                                                    <?php //}} ?>
                                                </select>
                                            </div> -->
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label> City Name </label>
                                                <select name="city_name" class="sel_pro form-control location set_chosen" id="city_name">
                                                    <option value="0">Select city</option>
                                                    <?php
                                                    $db->select('select id,name from cities');
                                                    $cities_name = $db->fetch_all();
                                                    foreach ($cities_name as $cities) { ?>
                                                        <option value="<?php echo $cities['id'] ?>"><?php echo $cities['name'] ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="branch_name">Branch Name</label>
                                                <input type="text" class="form-control" name="branch_name" placeholder="branch name">
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="contact_person">Contact Person</label>
                                                <input type="text" class="form-control" name="contact_person" placeholder="contact person">
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="phone">phone</label>
                                                <input type="text" class="form-control" name="phone" placeholder="enter phone">
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="address">Address</label>
                                                <input type="text" class="form-control" name="address" placeholder="enter address">
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="designation">Multicompany</label>
                                                <select name="multicompany" class="sel_pro form-control location set_chosen" id="multicompany">
                                                    <option value="0">Select Multicompany</option>
                                                    <?php
                                                    $db->select('select id,name from companies');
                                                    $company_name = $db->fetch_all();
                                                    foreach ($company_name as $company) { ?>
                                                        <option value="<?php echo $company['id'] ?>"><?php echo $company['name'] ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="pic">Image</label>
                                                <input type="file" class="form-control" name="pic">
                                            </div>
                                            <div class="col-md-2 col-lg-4 col-sm-6 col-xs-12">
                                                <label class="control-label" for="company_name">Company Name</label>
                                                <input type="text" class="form-control" name="company_name" placeholder="company name">
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <!-- <hr> -->
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-primary pull-left" name="command" value="Add">Save</button>
                                    <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Modal End -->
                </div>
                <?php include("includes/footer.php"); ?>
                <div class='control-sidebar-bg'></div>
            </div>
        </form>

        <?php include("../../includes/item-popup.php"); ?>
        <?php include("../../includes/footer.php"); ?>
        <div class='control-sidebar-bg'></div>
    </div>
    <?php include("../../includes/footer-jsfiles.php"); ?>
    <?php include("../../includes/popups-validation.php"); ?>
    <!-- <script>
        // function location_change(val_this) {
        //     $('#sle_month').val('');
        //     var ajaxlocat_id = $(val_this).val();
        //     $.ajax({
        //         url: 'add-branches.php',
        //         type: 'POST',
        //         data: {
        //             ajaxlocat_id: ajaxlocat_id
        //         },
        //         success: function(response) {
        //             $('.set_chosen').chosen('destroy');
        //             $("#sub_location").html('');
        //             $("#sub_location").append('<option value="0">All Sub Head</option>')
        //             $("#sub_location").append(response);
        //             $('.set_chosen').chosen({
        //                 width: '100%'
        //             });
        //         }
        //     });
        // }
    </script> -->
</body>

</html>