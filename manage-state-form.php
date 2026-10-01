<?php
include('includes/header.php');

$page_nm = 'State';
$table = 'tbl_state';
$redirection_url = 'manage-state-list.php';

error_reporting(E_ALL);

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;

$err_msg = '';
$categoryData = null;

// ===================== ADD =====================
if ($mode === 'add' && isset($_POST['btn_submit'])) {
    $state_name = addslashes(trim($_POST['state_name'] ?? ''));
    $state_code = addslashes(trim($_POST['state_code'] ?? ''));
    $order_no = intval($_POST['order_no'] ?? 0);
    $status = $_POST['status'] ?? 'deactive';

    // Duplicate check
    $check_dup = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM $table WHERE LOWER(state_name)='" . strtolower($state_name) . "' OR state_code='" . $state_code . "' LIMIT 1");
    if (!empty($check_dup)) {
        if (strtolower($check_dup[0]->state_name) === strtolower($state_name)) {
            $err_msg = "State Name '$state_name' already exists!";
        } else {
            $err_msg = "GST State Code '$state_code' already exists!";
        }
    } else {
        $add_qry = "INSERT INTO $table SET 
            state_name='" . $state_name . "',
            state_code='" . $state_code . "',
            order_no='" . $order_no . "',
            status='" . $status . "'";

        $ai_db->aiQuery($add_qry);
        $ai_core->aiGoPage($redirection_url . '?msg=1');
        exit;
    }
}

// ===================== EDIT =====================
if ($mode === 'edit' && isset($_POST['btn_submit'])) {
    $state_name = addslashes(trim($_POST['state_name'] ?? ''));
    $state_code = addslashes(trim($_POST['state_code'] ?? ''));
    $order_no = intval($_POST['order_no'] ?? 0);
    $status = $_POST['status'] ?? 'deactive';

    // Duplicate check excluding current ID
    $check_dup = $ai_db->aiGetQueryObj("SELECT id, state_name, state_code FROM $table WHERE (LOWER(state_name)='" . strtolower($state_name) . "' OR state_code='" . $state_code . "') AND id!='" . intval($id) . "' LIMIT 1");
    if (!empty($check_dup)) {
        if (strtolower($check_dup[0]->state_name) === strtolower($state_name)) {
            $err_msg = "State Name '$state_name' already exists!";
        } else {
            $err_msg = "GST State Code '$state_code' already exists!";
        }
    } else {
        $edit_qry = "UPDATE $table SET 
            state_name='" . $state_name . "',
            state_code='" . $state_code . "',
            order_no='" . $order_no . "',
            status='" . $status . "'
            WHERE id='" . intval($id) . "'";

        $ai_db->aiQuery($edit_qry);
        $ai_core->aiGoPage($redirection_url . '?msg=2');
        exit;
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
                            <span class="text-muted fw-light">Manage /</span> <?= $page_nm ?>
                        </h4>

                        <div class="row">
                            <div class="col-12">
                                <div class="card mb-4" id="categoryForm">
                                    <h5 class="card-header" id="formTitle">
                                        <?= ($mode == 'edit') ? 'Edit ' . $page_nm : 'Add ' . $page_nm ?>
                                    </h5>

                                    <form class="card-body" id="mainForm" method="post"
                                        action="manage-state-form.php?mode=<?= $mode ?>&id=<?= $id ?>"
                                        enctype="multipart/form-data">

                                        <input type="hidden" name="id" id="cat_id" value="<?= $categoryData->id ?? $id ?>">

                                        <div class="row g-3">
                                            <!-- State Name -->
                                            <div class="col-md-6">
                                                <label class="form-label">State Name <span class="text-danger">*</span></label>
                                                <input type="text" name="state_name" class="form-control" placeholder="e.g. Gujarat" value="<?= htmlspecialchars($_POST['state_name'] ?? $categoryData->state_name ?? '') ?>" required>
                                            </div>

                                            <!-- GST State Code -->
                                            <div class="col-md-6">
                                                <label class="form-label">GST State Code <span class="text-danger">*</span></label>
                                                <input type="text" name="state_code" class="form-control" placeholder="e.g. 24" value="<?= htmlspecialchars($_POST['state_code'] ?? $categoryData->state_code ?? '') ?>" required>
                                            </div>

                                            <!-- Order No -->
                                            <div class="col-md-6">
                                                <label class="form-label">Order No</label>
                                                <input type="number" name="order_no" class="form-control" placeholder="Enter order number" value="<?= htmlspecialchars($_POST['order_no'] ?? $categoryData->order_no ?? 0) ?>">
                                            </div>

                                            <!-- Status -->
                                            <div class="col-md-6">
                                                <label class="form-label">Status</label>
                                                <select name="status" id="cat_status" class="form-select select2">
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
                        title: 'Duplicate Entry!',
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
                var stateName = $('input[name="state_name"]').val().trim();
                var stateCode = $('input[name="state_code"]').val().trim();
                var id = $('input[name="id"]').val();

                if (stateName === '' || stateCode === '') {
                    return true;
                }

                if ($(form).data('valid') === true) {
                    return true;
                }

                e.preventDefault();

                $.ajax({
                    type: "POST",
                    url: "ajax.php",
                    data: {
                        action: "check_state_duplicate",
                        state_name: stateName,
                        state_code: stateCode,
                        id: id
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.status === "duplicate") {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Duplicate Entry!',
                                    text: response.message,
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 4000,
                                    timerProgressBar: true
                                });
                            } else {
                                alert(response.message);
                            }
                        } else {
                            $(form).data('valid', true);
                            form.submit();
                        }
                    },
                    error: function() {
                        $(form).data('valid', true);
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>
