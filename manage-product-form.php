<?php
include('includes/header.php');

$page_nm = 'Product Details';
$table = 'tbl_product';
$redirection_url = 'manage-product-list.php';

error_reporting(E_ALL);

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$err_msg = '';
$categoryData = null;

// Fetch active companies for dropdown if admin
$companies = $ai_db->aiGetQueryObj("SELECT id, company_name FROM tbl_company WHERE status='active' ORDER BY company_name ASC");

// ===================== ADD =====================
if ($mode === 'add' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    $product_name = addslashes(trim($_POST['product_name'] ?? ''));
    $hsn_code = addslashes(trim($_POST['hsn_code'] ?? ''));
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $sales_price = floatval($_POST['sales_price'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($product_name)) {
        $err_msg = "Please enter Product Name!";
    } else {
        // Duplicate check (product_name OR hsn_code per company)
        $dup_conds = ["LOWER(product_name)='" . strtolower($product_name) . "'"];
        if (!empty($hsn_code)) {
            $dup_conds[] = "LOWER(hsn_code)='" . strtolower($hsn_code) . "'";
        }

        $check_dup = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code FROM $table WHERE company_id='" . $company_id . "' AND (" . implode(" OR ", $dup_conds) . ") LIMIT 1");
        if (!empty($check_dup)) {
            if (strtolower($check_dup[0]->product_name) === strtolower($product_name)) {
                $err_msg = "Product Name '$product_name' already exists!";
            } elseif (!empty($hsn_code) && strtolower($check_dup[0]->hsn_code) === strtolower($hsn_code)) {
                $err_msg = "HSN Code '$hsn_code' already exists for this company!";
            } else {
                $err_msg = "Product already exists with given details!";
            }
        } else {
            $add_qry = "INSERT INTO $table SET 
                company_id='" . $company_id . "',
                product_name='" . $product_name . "',
                hsn_code='" . $hsn_code . "',
                purchase_price='" . $purchase_price . "',
                sales_price='" . $sales_price . "',
                status='" . $status . "'";

            $ai_db->aiQuery($add_qry);
            $ai_core->aiGoPage($redirection_url . '?msg=1');
            exit;
        }
    }
}

// ===================== EDIT =====================
if ($mode === 'edit' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    $product_name = addslashes(trim($_POST['product_name'] ?? ''));
    $hsn_code = addslashes(trim($_POST['hsn_code'] ?? ''));
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $sales_price = floatval($_POST['sales_price'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if (empty($product_name)) {
        $err_msg = "Please enter Product Name!";
    } else {
        // Duplicate check excluding current ID
        $dup_conds = ["LOWER(product_name)='" . strtolower($product_name) . "'"];
        if (!empty($hsn_code)) {
            $dup_conds[] = "LOWER(hsn_code)='" . strtolower($hsn_code) . "'";
        }

        $check_dup = $ai_db->aiGetQueryObj("SELECT id, product_name, hsn_code FROM $table WHERE company_id='" . $company_id . "' AND (" . implode(" OR ", $dup_conds) . ") AND id!='" . intval($id) . "' LIMIT 1");
        if (!empty($check_dup)) {
            if (strtolower($check_dup[0]->product_name) === strtolower($product_name)) {
                $err_msg = "Product Name '$product_name' already exists!";
            } elseif (!empty($hsn_code) && strtolower($check_dup[0]->hsn_code) === strtolower($hsn_code)) {
                $err_msg = "HSN Code '$hsn_code' already exists for this company!";
            } else {
                $err_msg = "Product already exists with given details!";
            }
        } else {
            $edit_qry = "UPDATE $table SET 
                company_id='" . $company_id . "',
                product_name='" . $product_name . "',
                hsn_code='" . $hsn_code . "',
                purchase_price='" . $purchase_price . "',
                sales_price='" . $sales_price . "',
                status='" . $status . "'
                WHERE id='" . intval($id) . "'";

            $ai_db->aiQuery($edit_qry);
            $ai_core->aiGoPage($redirection_url . '?msg=2');
            exit;
        }
    }
}

// ===================== DELETE =====================
if ($mode === 'delete' && $id) {
    $ai_db->aiQuery("DELETE FROM $table WHERE id='" . intval($id) . "'");
    $ai_core->aiGoPage($redirection_url . '?msg=3');
    exit;
}

// ===================== FETCH =====================
if ($mode === 'edit' && $id && !isset($_POST['btn_submit'])) {
    $query = "SELECT * FROM $table WHERE id='" . intval($id) . "' LIMIT 1";
    $result = $ai_db->aiGetQueryObj($query);
    $categoryData = isset($result[0]) ? $result[0] : null;
}

$selected_company_id = $_POST['company_id'] ?? $categoryData->company_id ?? ($_SESSION['company_id'] ?? 0);
?>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <h4 class="py-3 mb-4">
                            <span class="text-muted fw-light">Dashboard /</span> <?= $page_nm ?>
                        </h4>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4" id="categoryForm">
                                    <h5 class="card-header" id="formTitle">
                                        <?= ($mode == 'edit') ? 'Edit ' . $page_nm : 'Add ' . $page_nm ?>
                                    </h5>

                                    <form class="card-body" id="mainForm" method="post"
                                        action="manage-product-form.php?mode=<?= $mode ?>&id=<?= $id ?>"
                                        enctype="multipart/form-data">

                                        <input type="hidden" name="id" id="cat_id" value="<?= $categoryData->id ?? $id ?>">

                                        <div class="row g-3">
                                            <?php if (($_SESSION['user_type'] ?? '') !== 'company' && !empty($companies)) { ?>
                                                <!-- Company Selection (for Admin) -->
                                                <div class="col-md-12">
                                                    <label class="form-label">Company</label>
                                                    <select name="company_id" class="form-select select2">
                                                        <option value="0">-- Select Company --</option>
                                                        <?php foreach ($companies as $comp) { ?>
                                                            <option value="<?= $comp->id ?>" <?= ($selected_company_id == $comp->id) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($comp->company_name) ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                            <?php } ?>

                                            <!-- Product Name -->
                                            <div class="col-md-12">
                                                <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                                <input type="text" name="product_name" class="form-control" placeholder="Enter product Name" value="<?= htmlspecialchars($_POST['product_name'] ?? $categoryData->product_name ?? '') ?>" required>
                                            </div>

                                            <!-- HSN Code -->
                                            <div class="col-md-12">
                                                <label class="form-label">HSN Code</label>
                                                <input type="text" name="hsn_code" class="form-control" placeholder="Enter HSN code" value="<?= htmlspecialchars($_POST['hsn_code'] ?? $categoryData->hsn_code ?? '') ?>">
                                            </div>

                                            <!-- Purchase Price -->
                                            <div class="col-md-12">
                                                <label class="form-label">Purchase Price</label>
                                                <input type="number" step="0.01" name="purchase_price" class="form-control" placeholder="Enter purchase Price" value="<?= htmlspecialchars($_POST['purchase_price'] ?? $categoryData->purchase_price ?? '') ?>">
                                            </div>

                                            <!-- Sales Price -->
                                            <div class="col-md-12">
                                                <label class="form-label">Sales Price</label>
                                                <input type="number" step="0.01" name="sales_price" class="form-control" placeholder="Enter sales price" value="<?= htmlspecialchars($_POST['sales_price'] ?? $categoryData->sales_price ?? '') ?>">
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-12">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select select2">
                                                    <option value="active" <?= ((($_POST['status'] ?? $categoryData->status ?? 'active') == 'active')) ? 'selected' : '' ?>>Active</option>
                                                    <option value="deactive" <?= ((($_POST['status'] ?? $categoryData->status ?? '') == 'deactive')) ? 'selected' : '' ?>>Deactive</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="pt-4">
                                            <button type="submit" name="btn_submit" class="btn btn-primary me-sm-3 me-1 waves-effect waves-light" id="btnSubmit">
                                                <?= ($mode == 'edit') ? 'Update' : 'Submit' ?>
                                            </button>
                                            <a href="<?= $redirection_url ?>" class="btn btn-label-secondary waves-effect">Cancel</a>
                                        </div>

                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php include('includes/footer.php'); ?>
                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
        <div class="drag-target"></div>
    </div>

    <?php include('includes/footer_js.php'); ?>

    <?php if (!empty($err_msg)) { ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error!',
                        text: '<?= addslashes($err_msg) ?>',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                } else {
                    alert('<?= addslashes($err_msg) ?>');
                }
            });
        </script>
    <?php } ?>

    <script>
        $(document).ready(function() {
            $('#mainForm').on('submit', function(e) {
                var form = this;
                var productName = $('input[name="product_name"]').val().trim();
                var hsnCode = $('input[name="hsn_code"]').val().trim();
                var companyId = $('select[name="company_id"]').val() || '<?= (int)($_SESSION['company_id'] ?? 0) ?>';
                var catId = $('input[name="id"]').val() || 0;

                function showToastError(msg) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error!',
                            text: msg,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    } else {
                        alert(msg);
                    }
                }

                if (productName === '') {
                    e.preventDefault();
                    showToastError('Please enter Product Name!');
                    return false;
                }

                if ($(form).data('valid') === true) {
                    return true;
                }

                e.preventDefault();

                // AJAX duplicate check for product_name & hsn_code
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'check_product_duplicate',
                        company_id: companyId,
                        product_name: productName,
                        hsn_code: hsnCode,
                        id: catId
                    },
                    success: function(res) {
                        if (res.status === 'duplicate') {
                            showToastError(res.message);
                            if (res.field === 'hsn_code') {
                                $('input[name="hsn_code"]').focus();
                            } else {
                                $('input[name="product_name"]').focus();
                            }
                        } else {
                            $(form).data('valid', true);
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    },
                    error: function() {
                        $(form).data('valid', true);
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });
        });
    </script>
</body>
</html>
