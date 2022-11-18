<?php
include("../../includes/common-files.php");
$a->authenticate();
$page_title = "Edit Branches";
// $tab = " Sale Master";                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  
$url_photo = "pics/";

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'edit') {
    $id = intval($_REQUEST['id']);
    $updated_row = $db->fetch_array_by_query("select * from branches where id=" . $id);
    if (!$updated_row) {
    }
}

if (isset($_POST['command']) && $_POST['command'] == 'Update') {
    $id = $_REQUEST['id'];
    $update_arr = array();
    $update_arr['city_id'] = intval($_POST['city_name']);
    $update_arr['branch_name'] = $_POST['branch_name'];
    $update_arr['contact_person'] = $_POST['contact_person'];
    $update_arr['phone'] = $_POST['phone'];
    $update_arr['address'] = $_POST['address'];
    $update_arr['multicompany'] =  intval($_POST['multicompany']);

    if (isset($_FILES['pic']) && $_FILES['pic']['name'] != '') {
        $obj_upload = load_class('UploadImage');
        $uploadName = time() . rand();
        $resultFile = $obj_upload->upload_files($_FILES["pic"], $url_photo, $uploadName);
        if ($resultFile) {
            $photo_name = $obj_upload->get_image_name();
        }
    }
    if ($photo_name != '') {
        $update_arr['image'] = $photo_name;
    }
    $update_arr['company_name'] = $_POST['company_name'];
    $update_arr['created_at'] = time();
    $update_arr['updated_at'] = time();
    $update_arr['user_id'] = getUserId();
    $update_arr['company_id'] = getCompanyId();

    $update_id = $db->update($id, $update_arr, 'branches');
    if ($update_id) {
        $imsg->setMessage("'Record Updated Successfully'");
        redirect_header("branches.php");
    } else {
        echo "Not Updated try again ";
        die();
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
        img{
            clip-path: circle(50% at 50% 50%);
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
                                        <!-- <h3 class="box-title pull-left"><?php echo $page_title; ?></h3> -->
                                        <div class="box-body">
                                            <form method="post" name="select_filter" id="select_filter" style="padding: 0px 0px 0px 0px; " enctype="multipart/form-data">
                                                <img src="<?php echo $url_photo . $updated_row['image'] ?>" style="width:180px;hight:160px;margin-top:10px;margin-bottom:10px;" alt="xyz">
                                                <div class="row">
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label> City Name </label>
                                                        <select name="city_name" class="sel_pro form-control location set_chosen" id="city_name">
                                                            <option value="0">Select city</option>
                                                            <?php
                                                            $db->select('select id,name from cities');
                                                            $cities_name = $db->fetch_all();
                                                            foreach ($cities_name as $cities) { ?>
                                                                <option value="<?php echo $cities['id'] ?>" <?php if ($updated_row['city_id'] == $cities['id']) {
                                                                                                                echo "selected";
                                                                                                            } ?>><?php echo $cities['name'] ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="branch_name">Branch Name</label>
                                                        <input type="text" class="form-control" name="branch_name" value="<?php echo $updated_row['branch_name'] ?>" placeholder="branch name">
                                                    </div>
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="contact_person">Contact Person</label>
                                                        <input type="text" class="form-control" name="contact_person" value="<?php echo $updated_row['contact_person'] ?>" placeholder="contact person">
                                                    </div>
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="phone">phone</label>
                                                        <input type="text" class="form-control" name="phone" value="<?php echo $updated_row['phone'] ?>" placeholder="enter phone">
                                                    </div>
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="address">Address</label>
                                                        <input type="text" class="form-control" name="address" value="<?php echo $updated_row['address'] ?>" placeholder="enter address">
                                                    </div>
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="designation">Multicompany</label>
                                                        <select name="multicompany" class="sel_pro form-control location set_chosen" id="multicompany">
                                                            <option value="0">Select Multicompany</option>
                                                            <?php
                                                            $db->select('select id,name from companies');
                                                            $company_name = $db->fetch_all();
                                                            foreach ($company_name as $company) { ?>
                                                                <option value="<?php echo $company['id'] ?>" <?php if ($updated_row['multicompany'] == $company['id']) {
                                                                                                                    echo "selected";
                                                                                                                } ?>><?php echo $company['name'] ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="company_name">Company Name</label>
                                                        <input type="text" class="form-control" name="company_name" value="<?php echo $updated_row['company_name'] ?>" placeholder="company name">
                                                    </div>
                                                    <div class="col-md-2 col-lg-6 col-sm-6 col-xs-12">
                                                        <label class="control-label" for="pic">Image</label>
                                                        <input type="file" class="form-control" name="pic">
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-primary pull-right" name="command" value="Update" style="width: 10%;margin-top:20px;">Update</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
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
</body>

</html>