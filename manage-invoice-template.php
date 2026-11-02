<?php
include('includes/header.php');
require_once __DIR__ . '/includes/template-auto-migrate.php';

$page_nm = "Invoice Templates";
$pageUrl = "manage-invoice-template.php";

$userType = $_SESSION['user_type'] ?? 'admin';

// Fetch all available invoice templates
$templates = $ai_db->aiGetQueryObj("SELECT * FROM tbl_invoice_templates ORDER BY id ASC");
?>

<style>
    .template-thumb-preview {
        width: 65px;
        height: 85px;
        object-fit: contain;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 3px;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .template-thumb-preview:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.15);
        border-color: #7367f0;
    }
    .template-name-link {
        font-weight: 700;
        color: #1e293b;
        cursor: pointer;
        text-decoration: none;
    }
    .template-name-link:hover {
        color: #7367f0;
        text-decoration: underline;
    }
    .modal-preview-frame {
        width: 100%;
        height: 80vh;
        border: none;
        border-radius: 8px;
        background: #f8fafc;
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
                                <p class="text-muted mb-0">Browse and preview all available invoice & quotation templates in list view.</p>
                            </div>
                        </div>

                        <!-- Template Table Card -->
                        <div class="card shadow-sm border-0">
                            <div class="card-header border-bottom py-3 d-flex justify-content-between align-items-center bg-white">
                                <h5 class="card-title mb-0 fw-bold text-dark">
                                    <i class="ti ti-list me-1 text-primary"></i> Templates List
                                </h5>
                                <span class="badge bg-label-primary px-3 py-2 fs-6">
                                    Total: <?= count($templates ?? []) ?> Templates
                                </span>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive text-nowrap">
                                    <table class="table table-hover align-middle mb-0" id="templatesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 70px;" class="text-center">#</th>
                                                <th style="width: 100px;" class="text-center">PREVIEW</th>
                                                <th>TEMPLATE NAME</th>
                                                <th>TEMPLATE SLUG</th>
                                                <th style="width: 120px;" class="text-center">IS PREMIUM</th>
                                                <th style="width: 100px;" class="text-center">STATUS</th>
                                                <th style="width: 150px;" class="text-center">ACTION</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($templates)): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4 text-muted">
                                                        No invoice templates found.
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php $i = 1; foreach ($templates as $tpl): 
                                                    $imgSrc = !empty($tpl->preview_image) ? SITE_URL . $tpl->preview_image : SITE_URL . 'assets/img/templates/template-classic.svg';
                                                    $livePreviewUrl = "invoice-print.php?sample_preview=1&preview_template=" . $tpl->id;
                                                ?>
                                                    <tr>
                                                        <td class="text-center fw-bold text-muted"><?= $i++ ?></td>
                                                        <td class="text-center">
                                                            <a href="javascript:void(0);" class="btn-open-preview" data-url="<?= $livePreviewUrl ?>" data-name="<?= htmlspecialchars($tpl->template_name) ?>" title="Click to Preview Template">
                                                                <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($tpl->template_name) ?>" class="template-thumb-preview" />
                                                            </a>
                                                        </td>
                                                        <td>
                                                            <a href="javascript:void(0);" class="template-name-link btn-open-preview" data-url="<?= $livePreviewUrl ?>" data-name="<?= htmlspecialchars($tpl->template_name) ?>">
                                                                <?= htmlspecialchars($tpl->template_name) ?>
                                                            </a>
                                                            <div class="small text-muted">
                                                                <?php
                                                                if ($tpl->template_slug === 'template-classic') {
                                                                    echo 'Classic 2-box GST layout with clean borders';
                                                                } elseif ($tpl->template_slug === 'template-modern') {
                                                                    echo 'Modern navy accent banner with sleek headers';
                                                                } elseif ($tpl->template_slug === 'template-minimal') {
                                                                    echo 'Minimalist clean typography and bold contrast';
                                                                } elseif ($tpl->template_slug === 'template-golden-executive') {
                                                                    echo 'High contrast dark header with gold accents';
                                                                } elseif ($tpl->template_slug === 'template-emerald') {
                                                                    echo 'Emerald green corporate gradient with mint tints';
                                                                } elseif ($tpl->template_slug === 'template-ruby') {
                                                                    echo 'Royal crimson luxury executive style';
                                                                } else {
                                                                    echo 'Full GST compliant format with complete breakdowns';
                                                                }
                                                                ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <code class="text-primary fw-semibold"><?= htmlspecialchars($tpl->template_slug) ?></code>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="form-check form-switch d-inline-block">
                                                                <input class="form-check-input switch-is-premium" type="checkbox" role="switch" data-id="<?= $tpl->id ?>" <?= (!empty($tpl->is_premium) && (int)$tpl->is_premium === 1) ? 'checked' : '' ?> style="cursor: pointer; width: 40px; height: 20px;">
                                                            </div>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($tpl->status === 'active'): ?>
                                                                <span class="badge bg-label-success">Active</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-label-secondary">Deactive</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="d-inline-flex gap-2">
                                                                <button type="button" class="btn btn-sm btn-outline-primary btn-open-preview" data-url="<?= $livePreviewUrl ?>" data-name="<?= htmlspecialchars($tpl->template_name) ?>" title="Live Preview">
                                                                    <i class="ti ti-eye me-1"></i> Preview
                                                                </button>
                                                                <a href="<?= $livePreviewUrl ?>" target="_blank" class="btn btn-sm btn-icon btn-text-secondary rounded-pill" title="Open in New Tab">
                                                                    <i class="ti ti-external-link"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <?php include('includes/footer.php'); ?>
                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <!-- Template Live Preview Modal -->
    <div class="modal fade" id="templatePreviewModal" tabindex="-1" aria-labelledby="templatePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" style="max-width: 950px;">
            <div class="modal-content">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold" id="templatePreviewModalLabel">
                        <i class="ti ti-eye me-2 text-primary"></i> <span id="modalTemplateName">Template Preview</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <a href="#" id="modalOpenExternalBtn" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-external-link me-1"></i> Open Fullscreen
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-0 position-relative" style="background: #e2e8f0;">
                    <div id="modalLoadingSpinner" class="position-absolute top-50 start-50 translate-middle text-center" style="display:none; z-index: 10;">
                        <div class="spinner-border text-primary" role="status"></div>
                        <div class="small fw-semibold text-muted mt-2">Loading Template Preview...</div>
                    </div>
                    <iframe id="templatePreviewIframe" class="modal-preview-frame" src=""></iframe>
                </div>
            </div>
        </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
    <div class="drag-target"></div>

    <?php include('includes/footer_js.php'); ?>

    <script>
        $(document).ready(function() {
            // Open Preview Modal on Thumbnail / Button / Name Click
            $(document).on('click', '.btn-open-preview', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                var name = $(this).data('name');

                $('#modalTemplateName').text(name);
                $('#modalOpenExternalBtn').attr('href', url);
                
                $('#modalLoadingSpinner').show();
                var iframe = document.getElementById('templatePreviewIframe');
                iframe.src = url;
                iframe.onload = function() {
                    $('#modalLoadingSpinner').hide();
                };

                var previewModal = new bootstrap.Modal(document.getElementById('templatePreviewModal'));
                previewModal.show();
            });

            // Clear iframe on modal close to free memory
            $('#templatePreviewModal').on('hidden.bs.modal', function() {
                $('#templatePreviewIframe').attr('src', '');
            });

            // Toggle Is Premium switch
            $(document).on('change', '.switch-is-premium', function() {
                var chk = $(this);
                var templateId = chk.data('id');
                var isPremium = chk.is(':checked') ? 1 : 0;

                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'toggle_template_premium',
                        template_id: templateId,
                        is_premium: isPremium
                    },
                    success: function(res) {
                        if (res && res.status) {
                            if (typeof toastr !== 'undefined') {
                                toastr.success(res.message);
                            }
                        } else {
                            chk.prop('checked', !isPremium);
                            if (typeof toastr !== 'undefined') {
                                toastr.error(res.message || 'Failed to update premium status.');
                            } else {
                                alert(res.message || 'Failed to update premium status.');
                            }
                        }
                    },
                    error: function() {
                        chk.prop('checked', !isPremium);
                        if (typeof toastr !== 'undefined') {
                            toastr.error('Error connecting to server.');
                        } else {
                            alert('Error connecting to server.');
                        }
                    }
                });
            });
        });
    </script>
</body>
</html>
