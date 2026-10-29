<?php
include('includes/header.php');

$page_nm = 'Invoice Template';
$table = 'tbl_invoice_templates';
$redirection_url = 'manage-invoice-template.php';

$mode = $_REQUEST['mode'] ?? 'add';
$id = isset($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
$err_msg = '';
$tplData = null;

// Upload directory for preview images
$uploadDir = __DIR__ . '/uploads/templates/';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// ===================== EDIT FETCH =====================
if ($mode === 'edit' && $id > 0) {
    $row = $ai_db->aiGetQueryObj("SELECT * FROM tbl_invoice_templates WHERE id='$id' LIMIT 1");
    if (!empty($row)) {
        $tplData = $row[0];
    } else {
        $err_msg = "Template not found!";
    }
}

// ===================== DELETE =====================
if ($mode === 'delete' && $id > 0) {
    // Check if companies are using this template
    $usingComps = $ai_db->aiGetQueryObj("SELECT COUNT(*) as cnt FROM tbl_company WHERE invoice_template_id='$id'");
    if (!empty($usingComps) && (int)$usingComps[0]->cnt > 0) {
        echo "<script>alert('Cannot delete this template because it is currently assigned to one or more companies.'); window.location.href='{$redirection_url}';</script>";
        exit;
    }

    $ai_db->aiQuery("DELETE FROM tbl_invoice_templates WHERE id='$id'");
    echo "<script>window.location.href='{$redirection_url}';</script>";
    exit;
}

// ===================== FORM SUBMIT (ADD / EDIT) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_POST['btn_submit'])) {
    $template_name = addslashes(trim($_POST['template_name'] ?? ''));
    $template_slug = addslashes(trim($_POST['template_slug'] ?? ''));
    $file_path = addslashes(trim($_POST['file_path'] ?? ''));
    $status = $_POST['status'] ?? 'active';

    if (empty($template_name)) {
        $err_msg = "Template Name is required!";
    } elseif (empty($template_slug)) {
        // Auto generate slug
        $template_slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $template_name)));
    }

    // Default file_path if empty
    if (empty($file_path)) {
        $file_path = 'templates/invoices/' . $template_slug . '.php';
    }

    // Handle Preview Image Upload
    $preview_image_path = ($tplData) ? ($tplData->preview_image ?? '') : '';
    if (isset($_FILES['preview_image']) && $_FILES['preview_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['preview_image']['tmp_name'];
        $fileName = $_FILES['preview_image']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];

        if (!in_array($ext, $allowed)) {
            $err_msg = "Invalid image type! Only JPG, PNG, SVG, WEBP allowed.";
        } else {
            $newFileName = 'tpl_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $dest = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmp, $dest)) {
                $preview_image_path = 'uploads/templates/' . $newFileName;
            } else {
                $err_msg = "Failed to upload preview image.";
            }
        }
    }

    // If no error, save to DB
    if (empty($err_msg)) {
        if ($mode === 'add') {
            // Check slug duplicate
            $dup = $ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice_templates WHERE template_slug='$template_slug' LIMIT 1");
            if (!empty($dup)) {
                $err_msg = "Template with this Slug already exists!";
            } else {
                $ins = $ai_db->aiQuery("INSERT INTO tbl_invoice_templates (template_name, template_slug, preview_image, file_path, status) VALUES ('$template_name', '$template_slug', '$preview_image_path', '$file_path', '$status')");
                if ($ins) {
                    // Create basic template PHP file if it doesn't exist
                    $realPhpPath = __DIR__ . '/' . ltrim($file_path, '/');
                    if (!file_exists($realPhpPath)) {
                        @copy(__DIR__ . '/templates/invoices/template-classic.php', $realPhpPath);
                    }
                    echo "<script>alert('Template added successfully!'); window.location.href='{$redirection_url}';</script>";
                    exit;
                } else {
                    $err_msg = "Database insert error.";
                }
            }
        } elseif ($mode === 'edit' && $id > 0) {
            $upd = $ai_db->aiQuery("UPDATE tbl_invoice_templates SET template_name='$template_name', template_slug='$template_slug', preview_image='$preview_image_path', file_path='$file_path', status='$status' WHERE id='$id'");
            if ($upd) {
                echo "<script>alert('Template updated successfully!'); window.location.href='{$redirection_url}';</script>";
                exit;
            } else {
                $err_msg = "Database update error.";
            }
        }
    }
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
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h4 class="fw-bold mb-1">
                                    <i class="ti ti-template me-2 text-primary"></i>
                                    <?= ($mode === 'edit') ? 'Edit Template' : 'Add New Invoice Template' ?>
                                </h4>
                                <p class="text-muted mb-0">Upload new layout screenshot and define the PHP template file.</p>
                            </div>
                            <a href="<?= $redirection_url ?>" class="btn btn-secondary">
                                <i class="ti ti-arrow-left me-1"></i> Back to Templates
                            </a>
                        </div>

                        <?php if (!empty($err_msg)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="ti ti-alert-circle me-2"></i> <?= htmlspecialchars($err_msg) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data" class="row g-3">
                                    <input type="hidden" name="mode" value="<?= htmlspecialchars($mode) ?>">
                                    <input type="hidden" name="id" value="<?= $id ?>">

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Template Name <span class="text-danger">*</span></label>
                                        <input type="text" name="template_name" id="template_name" class="form-control" placeholder="e.g. Modern Navy GST / Corporate Red" value="<?= htmlspecialchars($tplData->template_name ?? '') ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Template Slug (Unique Key)</label>
                                        <input type="text" name="template_slug" id="template_slug" class="form-control" placeholder="e.g. template-modern-navy" value="<?= htmlspecialchars($tplData->template_slug ?? '') ?>">
                                        <small class="text-muted">Used for file naming and identifiers.</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Template PHP File Path</label>
                                        <input type="text" name="file_path" id="file_path" class="form-control" placeholder="templates/invoices/your-template-name.php" value="<?= htmlspecialchars($tplData->file_path ?? '') ?>">
                                        <small class="text-muted">If left blank, it will default to <code>templates/invoices/{slug}.php</code></small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Status</label>
                                        <select name="status" class="form-select">
                                            <option value="active" <?= (($tplData->status ?? 'active') === 'active') ? 'selected' : '' ?>>Active</option>
                                            <option value="deactive" <?= (($tplData->status ?? '') === 'deactive') ? 'selected' : '' ?>>Deactive</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Preview Screenshot / Thumbnail Image</label>
                                        <input type="file" name="preview_image" class="form-control" accept="image/*">
                                        <small class="text-muted">Upload PNG, JPG, or SVG preview of invoice layout.</small>
                                    </div>

                                    <?php if (!empty($tplData->preview_image)): ?>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold d-block">Current Preview:</label>
                                            <img src="<?= SITE_URL . $tplData->preview_image ?>" alt="Preview" style="max-height: 120px; border: 1px solid #ddd; border-radius: 6px; padding: 4px;">
                                        </div>
                                    <?php endif; ?>

                                    <div class="col-12 mt-4 border-top pt-3 text-end">
                                        <a href="<?= $redirection_url ?>" class="btn btn-outline-secondary me-2">Cancel</a>
                                        <button type="submit" name="btn_submit" class="btn btn-primary">
                                            <i class="ti ti-device-floppy me-1"></i> Save Template
                                        </button>
                                    </div>
                                </form>
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

    <script>
    $('#template_name').on('input', function() {
        if ($('input[name="mode"]').val() === 'add') {
            var val = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
            $('#template_slug').val(val);
            if (!$('#file_path').val() || $('#file_path').val().indexOf('templates/invoices/') === 0) {
                $('#file_path').val('templates/invoices/' + val + '.php');
            }
        }
    });
    </script>
</body>
</html>

