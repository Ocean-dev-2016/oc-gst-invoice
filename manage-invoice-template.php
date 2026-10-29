<?php
include('includes/header.php');
require_once __DIR__ . '/includes/template-auto-migrate.php';

$page_nm = "Invoice Templates";
$pageUrl = "manage-invoice-template.php";

// Fetch user type and company context
$userType = $_SESSION['user_type'] ?? 'admin';
$sessionCompanyId = (int)($_SESSION['company_id'] ?? 0);

// Fetch all active companies for dropdown
$companies = $ai_db->aiGetQueryObj("SELECT id, company_name, gst_no FROM tbl_company WHERE status='active' ORDER BY company_name ASC");

// Determine selected company
$selectedCompanyId = 0;
if ($userType === 'company' && $sessionCompanyId > 0) {
    $selectedCompanyId = $sessionCompanyId;
} elseif (isset($_GET['company_id']) && intval($_GET['company_id']) > 0) {
    $selectedCompanyId = intval($_GET['company_id']);
}
// For admin: Company selection is required! Don't auto-pick the first company.

// Fetch selected company details
$currentCompany = null;
if ($selectedCompanyId > 0) {
    $compRow = $ai_db->aiGetQueryObj("SELECT * FROM tbl_company WHERE id='$selectedCompanyId' LIMIT 1");
    if (!empty($compRow)) {
        $currentCompany = $compRow[0];
    }
}
$currentTemplateId = (!empty($currentCompany) && isset($currentCompany->invoice_template_id) && !empty($currentCompany->invoice_template_id)) ? (int)$currentCompany->invoice_template_id : 1;

// Fetch all available invoice templates
$templates = $ai_db->aiGetQueryObj("SELECT * FROM tbl_invoice_templates WHERE status='active' ORDER BY id ASC");

// Fetch last invoice ID for live preview if available
$previewInvoiceId = 0;
if ($selectedCompanyId > 0) {
    $lastInv = $ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice WHERE company_id='$selectedCompanyId' ORDER BY id DESC LIMIT 1");
    if (!empty($lastInv)) {
        $previewInvoiceId = (int)$lastInv[0]->id;
    }
}
if ($previewInvoiceId === 0) {
    $anyInv = $ai_db->aiGetQueryObj("SELECT id FROM tbl_invoice ORDER BY id DESC LIMIT 1");
    if (!empty($anyInv)) {
        $previewInvoiceId = (int)$anyInv[0]->id;
    }
}
?>

<style>
    .template-card {
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        transition: all 0.25s ease-in-out;
        background: #ffffff;
        overflow: hidden;
        position: relative;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .template-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -10px rgba(0, 0, 0, 0.15);
        border-color: #7367f0;
    }

    .template-card.active-template {
        border-color: #28c76f;
        box-shadow: 0 0 0 1px #28c76f, 0 10px 20px -8px rgba(40, 199, 111, 0.35);
    }

    .template-preview-wrapper {
        position: relative;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 12px;
        height: 280px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .template-preview-wrapper img {
        max-height: 100%;
        width: auto;
        object-fit: contain;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        transition: transform 0.25s ease;
    }

    .template-card:hover .template-preview-wrapper img {
        transform: scale(1.02);
    }

    .template-badge-active {
        position: absolute;
        top: 12px;
        right: 12px;
        background: #28c76f;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 6px rgba(40, 199, 111, 0.4);
        display: flex;
        align-items: center;
        gap: 4px;
        z-index: 2;
    }

    .template-body {
        padding: 18px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
        justify-content: space-between;
    }

    .template-title {
        font-size: 16px;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 4px;
    }

    .template-subtitle {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 16px;
    }

    .company-select-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px 20px;
        margin-bottom: 24px;
    }
</style>

<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <?php include('sidebar.php'); ?>

            <div class="layout-page">
                <?php include('navbar.php'); ?>

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">

                        <!-- Header -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                            <div>
                                <h4 class="fw-bold mb-1"><i class="ti ti-template me-2 text-primary"></i><?= $page_nm ?></h4>
                                <p class="text-muted mb-0">Choose and customize the PDF & Print invoice layout for each company.</p>
                            </div>
                            <?php if ($userType === 'admin'): ?>
                                <a href="manage-template-form.php?mode=add" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i> Add New Template
                                </a>
                            <?php endif; ?>
                            <?php if ($previewInvoiceId > 0): ?>

                            <?php endif; ?>
                        </div>

                        <!-- Company Selection Bar -->
                        <div class="company-select-card">
                            <div class="row align-items-center g-3">
                                <div class="col-md-5 col-sm-12">
                                    <label class="form-label fw-bold text-dark mb-1">
                                        <i class="ti ti-building me-1 text-primary"></i> Select Company:
                                    </label>
                                    <select class="form-select select2" id="company_selector" <?= ($userType === 'company') ? 'disabled' : '' ?> data-placeholder="-- Select Company --">
                                        <?php if (empty($companies)): ?>
                                            <option value="">No Active Companies Found</option>
                                        <?php else: ?>
                                            <?php if ($userType !== 'company'): ?>
                                                <option value="" <?= ($selectedCompanyId === 0) ? 'selected' : '' ?>>-- Please Select Company --</option>
                                            <?php endif; ?>
                                            <?php foreach ($companies as $comp): ?>
                                                <option value="<?= $comp->id ?>" <?= ($selectedCompanyId === (int)$comp->id) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($comp->company_name) ?> <?= !empty($comp->gst_no) ? ' (GST: ' . htmlspecialchars($comp->gst_no) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <?php if ($userType === 'company'): ?>
                                        <small class="text-muted"><i class="ti ti-lock me-1"></i>You are configuring templates for your logged-in company.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-7 col-sm-12">
                                    <div class="d-flex align-items-center gap-3 justify-content-md-end pt-md-3">
                                        <?php if ($selectedCompanyId > 0): ?>
                                            <span class="badge bg-label-info p-2 px-3 fs-6">
                                                <i class="ti ti-check me-1"></i> Current Active:
                                                <strong id="active-template-label">
                                                    <?php
                                                    $activeName = "Classic Professional GST";
                                                    if (!empty($templates)) {
                                                        foreach ($templates as $t) {
                                                            if ((int)$t->id === $currentTemplateId) {
                                                                $activeName = $t->template_name;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    echo htmlspecialchars($activeName);
                                                    ?>
                                                </strong>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-label-warning p-2 px-3 fs-6">
                                                <i class="ti ti-alert-circle me-1"></i> Please select a company to view or change active template
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <?php if (empty($templates)): ?>
                                <div class="col-12">
                                    <div class="alert alert-warning">No invoice templates available in database. Please run <a href="db_sync.php">db_sync.php</a> to initialize templates.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($templates as $tpl):
                                    $isCurrent = ($selectedCompanyId > 0 && (int)$tpl->id === $currentTemplateId);
                                    $imgSrc = !empty($tpl->preview_image) ? SITE_URL . $tpl->preview_image : SITE_URL . 'assets/img/templates/template-classic.svg';
                                    $previewUrl = "invoice-print.php?sample_preview=1&company_id={$selectedCompanyId}&preview_template=" . $tpl->id;
                                ?>
                                    <div class="col-lg-4 col-md-6 col-12">
                                        <div class="template-card <?= $isCurrent ? 'active-template' : '' ?>" id="template-card-<?= $tpl->id ?>">

                                            <!-- Active Badge -->
                                            <div class="template-badge-active" id="badge-<?= $tpl->id ?>" style="<?= $isCurrent ? '' : 'display:none;' ?>">
                                                <i class="ti ti-check"></i> ACTIVE
                                            </div>

                                            <!-- Thumbnail / Preview Image -->
                                            <div class="template-preview-wrapper">
                                                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($tpl->template_name) ?>" />
                                            </div>

                                            <!-- Card Body -->
                                            <div class="template-body">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <h5 class="template-title"><?= htmlspecialchars($tpl->template_name) ?></h5>
                                                        <?php if ($userType === 'admin'): ?>
                                                            <div class="dropdown ms-1">
                                                                <button class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                                    <i class="ti ti-dots-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-end">
                                                                    <a class="dropdown-item" href="manage-template-form.php?mode=edit&id=<?= $tpl->id ?>">
                                                                        <i class="ti ti-pencil me-1"></i> Edit
                                                                    </a>
                                                                    <?php if ((int)$tpl->id > 3): // Allow deleting custom templates 
                                                                    ?>
                                                                        <a class="dropdown-item text-danger" href="manage-template-form.php?mode=delete&id=<?= $tpl->id ?>" onclick="return confirm('Are you sure you want to delete this template?');">
                                                                            <i class="ti ti-trash me-1"></i> Delete
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="template-subtitle">
                                                        <?php
                                                        if ($tpl->template_slug === 'template-classic') {
                                                            echo 'Classic 2-box GST layout with borders, perfect for traditional businesses.';
                                                        } elseif ($tpl->template_slug === 'template-modern') {
                                                            echo 'Modern navy accent banner, contemporary typography and sleek invoice headers.';
                                                        } elseif ($tpl->template_slug === 'template-minimal') {
                                                            echo 'Clean minimalist layout with high contrast and neat column alignments.';
                                                        } else {
                                                            echo 'Full GST compliant format with HSN code, tax breakdown and bank info.';
                                                        }
                                                        ?>
                                                    </p>
                                                </div>

                                                <div class="d-flex align-items-center gap-2 pt-2 border-top">
                                                    <a href="<?= $previewUrl ?>" target="_blank" class="btn btn-outline-secondary btn-sm flex-fill">
                                                        <i class="ti ti-eye me-1"></i> Preview
                                                    </a>

                                                    <button type="button"
                                                        class="btn btn-sm flex-fill btn-set-template <?= $isCurrent ? 'btn-success' : 'btn-primary' ?>"
                                                        data-template-id="<?= $tpl->id ?>"
                                                        data-template-name="<?= htmlspecialchars($tpl->template_name) ?>"
                                                        id="btn-select-<?= $tpl->id ?>"
                                                        <?= $isCurrent ? 'disabled' : '' ?>>
                                                        <i class="ti <?= $isCurrent ? 'ti-check' : 'ti-device-floppy' ?> me-1"></i>
                                                        <span class="btn-text"><?= $isCurrent ? 'Selected' : 'Use Template' ?></span>
                                                    </button>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
        $(document).ready(function() {
            // Change Company dropdown
            $('#company_selector').on('change', function() {
                var compId = $(this).val();
                if (compId) {
                    window.location.href = 'manage-invoice-template.php?company_id=' + compId;
                }
            });

            // Set Template as Default
            $(document).on('click', '.btn-set-template', function() {
                var btn = $(this);
                var templateId = btn.data('template-id');
                var templateName = btn.data('template-name');
                var companyId = $('#company_selector').val();

                if (!companyId) {
                    if (typeof toastr !== 'undefined') {
                        toastr.warning('Please select a company first before assigning an invoice template.');
                    } else {
                        alert('Please select a company first before assigning an invoice template.');
                    }
                    $('#company_selector').focus();
                    if ($('#company_selector').hasClass('select2-hidden-accessible')) {
                        $('#company_selector').select2('open');
                    }
                    return;
                }

                btn.prop('disabled', true).find('.btn-text').text('Saving...');

                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'set_company_invoice_template',
                        company_id: companyId,
                        template_id: templateId
                    },
                    success: function(res) {
                        if (res && res.status) {
                            // Reset all cards
                            $('.template-card').removeClass('active-template');
                            $('.template-badge-active').hide();
                            $('.btn-set-template').removeClass('btn-success').addClass('btn-primary').prop('disabled', false);
                            $('.btn-set-template .ti').removeClass('ti-check').addClass('ti-device-floppy');
                            $('.btn-set-template .btn-text').text('Use Template');

                            // Activate selected card
                            $('#template-card-' + templateId).addClass('active-template');
                            $('#badge-' + templateId).fadeIn();
                            $('#btn-select-' + templateId).removeClass('btn-primary').addClass('btn-success').prop('disabled', true);
                            $('#btn-select-' + templateId).find('.ti').removeClass('ti-device-floppy').addClass('ti-check');
                            $('#btn-select-' + templateId).find('.btn-text').text('Selected');

                            // Update label
                            $('#active-template-label').text(templateName);

                            if (typeof toastr !== 'undefined') {
                                toastr.success(res.message || 'Template updated successfully!');
                            } else {
                                alert(res.message || 'Template updated successfully!');
                            }
                        } else {
                            btn.prop('disabled', false).find('.btn-text').text('Use Template');
                            if (typeof toastr !== 'undefined') {
                                toastr.error(res.message || 'Failed to update template.');
                            } else {
                                alert(res.message || 'Failed to update template.');
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).find('.btn-text').text('Use Template');
                        if (typeof toastr !== 'undefined') {
                            toastr.error('An error occurred while saving template.');
                        } else {
                            alert('An error occurred while saving template.');
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>