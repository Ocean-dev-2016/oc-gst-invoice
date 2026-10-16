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
$companies = $ai_db->aiGetQueryObj("SELECT id, company_name, gst_applicable FROM tbl_company WHERE status='active' ORDER BY company_name ASC");

function productGstRateForCompany($companyId, $postedRate = null)
{
    global $ai_db;

    $company = $ai_db->aiGetQueryObj("SELECT gst_applicable FROM tbl_company WHERE id='" . intval($companyId) . "' LIMIT 1");
    if (empty($company) || (int)$company[0]->gst_applicable !== 1) {
        return 0;
    }

    $allowedRates = [18, 12, 5, 28, 0];
    $rate = ($postedRate === null || $postedRate === '') ? 18 : (int)$postedRate;
    return in_array($rate, $allowedRates, true) ? $rate : 18;
}

// ===================== ADD =====================
if ($mode === 'add' && ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit']))) {
    $company_id = intval($_POST['company_id'] ?? ($_SESSION['company_id'] ?? 0));
    $product_name = addslashes(trim($_POST['product_name'] ?? ''));
    $sku = addslashes(trim($_POST['sku'] ?? ''));
    $hsn_code = addslashes(trim($_POST['hsn_code'] ?? ''));
    $gst_rate = productGstRateForCompany($company_id, $_POST['gst_rate'] ?? null);
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $sales_price = floatval($_POST['sales_price'] ?? 0);
    $stock = floatval($_POST['stock'] ?? 0);
    $min_stock_alert = floatval($_POST['min_stock_alert'] ?? 5);
    $track_stock = 1;
    $status = $_POST['status'] ?? 'active';

    if (empty($product_name)) {
        $err_msg = "Please enter Product Name!";
    } else {
        // Product names and SKUs must be unique within the company.
        $dup_conds = ["LOWER(product_name)='" . strtolower($product_name) . "'"];
        if (!empty($sku)) {
            $dup_conds[] = "LOWER(sku)='" . strtolower($sku) . "'";
        }

        $check_dup = $ai_db->aiGetQueryObj("SELECT id, product_name, sku FROM $table WHERE company_id='" . $company_id . "' AND (" . implode(" OR ", $dup_conds) . ") LIMIT 1");
        if (!empty($check_dup)) {
            if (strtolower($check_dup[0]->product_name) === strtolower($product_name)) {
                $err_msg = "Product Name '$product_name' already exists!";
            } elseif (!empty($sku) && strtolower($check_dup[0]->sku) === strtolower($sku)) {
                $err_msg = "SKU '$sku' already exists for this company!";
            } else {
                $err_msg = "Product already exists with given details!";
            }
        } else {
            $add_qry = "INSERT INTO $table SET 
                company_id='" . $company_id . "',
                product_name='" . $product_name . "',
                sku='" . $sku . "',
                hsn_code='" . $hsn_code . "',
                gst_rate='" . $gst_rate . "',
                purchase_price='" . $purchase_price . "',
                sales_price='" . $sales_price . "',
                stock='" . $stock . "',
                min_stock_alert='" . $min_stock_alert . "',
                track_stock='1',
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
    $sku = addslashes(trim($_POST['sku'] ?? ''));
    $hsn_code = addslashes(trim($_POST['hsn_code'] ?? ''));
    $gst_rate = productGstRateForCompany($company_id, $_POST['gst_rate'] ?? null);
    $purchase_price = floatval($_POST['purchase_price'] ?? 0);
    $sales_price = floatval($_POST['sales_price'] ?? 0);
    $stock = floatval($_POST['stock'] ?? 0);
    $min_stock_alert = floatval($_POST['min_stock_alert'] ?? 5);
    $track_stock = 1;
    $status = $_POST['status'] ?? 'active';

    if (empty($product_name)) {
        $err_msg = "Please enter Product Name!";
    } else {
        // Duplicate check excluding current ID
        $dup_conds = ["LOWER(product_name)='" . strtolower($product_name) . "'"];
        if (!empty($sku)) {
            $dup_conds[] = "LOWER(sku)='" . strtolower($sku) . "'";
        }

        $check_dup = $ai_db->aiGetQueryObj("SELECT id, product_name, sku FROM $table WHERE company_id='" . $company_id . "' AND (" . implode(" OR ", $dup_conds) . ") AND id!='" . intval($id) . "' LIMIT 1");
        if (!empty($check_dup)) {
            if (strtolower($check_dup[0]->product_name) === strtolower($product_name)) {
                $err_msg = "Product Name '$product_name' already exists!";
            } elseif (!empty($sku) && strtolower($check_dup[0]->sku) === strtolower($sku)) {
                $err_msg = "SKU '$sku' already exists for this company!";
            } else {
                $err_msg = "Product already exists with given details!";
            }
        } else {
            $edit_qry = "UPDATE $table SET 
                company_id='" . $company_id . "',
                product_name='" . $product_name . "',
                sku='" . $sku . "',
                hsn_code='" . $hsn_code . "',
                gst_rate='" . $gst_rate . "',
                purchase_price='" . $purchase_price . "',
                sales_price='" . $sales_price . "',
                stock='" . $stock . "',
                min_stock_alert='" . $min_stock_alert . "',
                track_stock='1',
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
$selected_company_gst = $selected_company_id > 0
    ? $ai_db->aiGetQueryObj("SELECT gst_applicable FROM tbl_company WHERE id='" . intval($selected_company_id) . "' LIMIT 1")
    : [];
$show_product_gst = !empty($selected_company_gst) && (int)$selected_company_gst[0]->gst_applicable === 1;
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
                                                    <select name="company_id" id="company_id" class="form-select select2">
                                                        <option value="0" data-gst-applicable="0">-- Select Company --</option>
                                                        <?php foreach ($companies as $comp) { ?>
                                                            <option value="<?= $comp->id ?>" data-gst-applicable="<?= (int)($comp->gst_applicable ?? 0) ?>" <?= ($selected_company_id == $comp->id) ? 'selected' : '' ?>>
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

                                            <!-- SKU -->
                                            <div class="col-md-12">
                                                <label class="form-label">SKU (Stock Keeping Unit)</label>
                                                <input type="text" name="sku" class="form-control" placeholder="Enter SKU (e.g. SKU-PROD-001)" value="<?= htmlspecialchars($_POST['sku'] ?? $categoryData->sku ?? '') ?>">
                                            </div>

                                            <!-- HSN Code -->
                                            <div class="col-md-12">
                                                <label class="form-label">HSN Code</label>
                                                <input type="text" name="hsn_code" class="form-control" placeholder="Enter HSN code" value="<?= htmlspecialchars($_POST['hsn_code'] ?? $categoryData->hsn_code ?? '') ?>">
                                            </div>

                                            <!-- GST Rate -->
                                            <div class="col-md-12" id="product_gst_rate_wrapper" style="<?= $show_product_gst ? '' : 'display: none;' ?>">
                                                <label class="form-label">GST Rate</label>
                                                <?php $selected_gst_rate = (string)($_POST['gst_rate'] ?? $categoryData->gst_rate ?? '18'); ?>
                                                <select name="gst_rate" id="input_gst_pct" class="form-select">
                                                    <option value="18" <?= ($selected_gst_rate === '18' || $selected_gst_rate === '18.00') ? 'selected' : '' ?>>18%</option>
                                                    <option value="12" <?= ($selected_gst_rate === '12' || $selected_gst_rate === '12.00') ? 'selected' : '' ?>>12%</option>
                                                    <option value="5" <?= ($selected_gst_rate === '5' || $selected_gst_rate === '5.00') ? 'selected' : '' ?>>5%</option>
                                                    <option value="28" <?= ($selected_gst_rate === '28' || $selected_gst_rate === '28.00') ? 'selected' : '' ?>>28%</option>
                                                    <option value="0" <?= ($selected_gst_rate === '0' || $selected_gst_rate === '0.00') ? 'selected' : '' ?>>0%</option>
                                                </select>
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

                                            <!-- Stock / Inventory Section -->
                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Stock Quantity (Current Stock) <span class="badge bg-label-info ms-1">Available Qty</span></label>
                                                <input type="number" step="0.01" name="stock" class="form-control fw-bold text-primary" placeholder="0.00" value="<?= htmlspecialchars($_POST['stock'] ?? $categoryData->stock ?? '0') ?>">
                                                <div class="form-text text-muted">Enter opening / current stock quantity. This will reduce automatically when sales invoices are created.</div>
                                            </div>

                                            <div class="col-md-6">
                                                <label class="form-label fw-bold">Low Stock Alert Limit</label>
                                                <input type="number" step="0.01" name="min_stock_alert" class="form-control" placeholder="5.00" value="<?= htmlspecialchars($_POST['min_stock_alert'] ?? $categoryData->min_stock_alert ?? '5') ?>">
                                                <div class="form-text text-muted">Stock falls below this number will show a warning alert.</div>
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
            function updateGstVisibility() {
                var $compSelect = $('select[name="company_id"]');
                if ($compSelect.length > 0) {
                    var gstApplicable = $compSelect.find('option:selected').data('gst-applicable') == 1;
                    if (gstApplicable) {
                        $('#product_gst_rate_wrapper').slideDown(200);
                    } else {
                        $('#product_gst_rate_wrapper').slideUp(200);
                    }
                }
            }

            $('select[name="company_id"]').on('change select2:select', function() {
                updateGstVisibility();
            });

            $('#mainForm').on('submit', function(e) {
                var form = this;
                var productName = $('input[name="product_name"]').val().trim();
                var sku = $('input[name="sku"]').val().trim();
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

                // AJAX duplicate check for product_name & SKU
                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'check_product_duplicate',
                        company_id: companyId,
                        product_name: productName,
                        sku: sku,
                        id: catId
                    },
                    success: function(res) {
                        if (res.status === 'duplicate') {
                            showToastError(res.message);
                            if (res.field === 'sku') {
                                $('input[name="sku"]').focus();
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
