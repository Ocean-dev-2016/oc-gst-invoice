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
                                                <?php $i = 1;
                                                foreach ($templates as $tpl):
                                                    $imgSrc = !empty($tpl->preview_image) ? SITE_URL . $tpl->preview_image : SITE_URL . 'assets/img/templates/template-classic.svg';
                                                    $livePreviewUrl = "invoice-print.php?sample_preview=1&preview_template=" . $tpl->id;
                                                ?>
                                                    <tr>
                                                        <td class="text-center fw-bold text-muted"><?= $i++ ?></td>
                                                        <td class="text-center">
                                                            <a href="<?= $imgSrc ?>" class="btn-view-image" data-img="<?= $imgSrc ?>" data-name="<?= htmlspecialchars($tpl->template_name) ?>" title="Click to View Image">
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
                                                                <button type="button" class="btn btn-sm btn-outline-warning btn-edit-template-img" data-id="<?= $tpl->id ?>" data-name="<?= htmlspecialchars($tpl->template_name) ?>" data-current-img="<?= $imgSrc ?>" title="Edit Preview Image">
                                                                    <i class="ti ti-edit me-1"></i> Edit
                                                                </button>
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

    <!-- Image Direct View Modal -->
    <div class="modal fade" id="imageDirectViewModal" tabindex="-1" aria-labelledby="imageDirectViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width: 800px;">
            <div class="modal-content">
                <div class="modal-header border-bottom py-3">
                    <h5 class="modal-title fw-bold" id="imageDirectViewModalLabel">
                        <i class="ti ti-photo me-2 text-primary"></i> <span id="imgModalTemplateName">Preview Image</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <a href="#" id="imgModalOpenTabBtn" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="ti ti-external-link me-1"></i> Open Image in New Tab
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-3 text-center" style="background: #f1f5f9; min-height: 400px; display: flex; align-items: center; justify-content: center;">
                    <img id="imgDirectFullView" src="" alt="Template Full Image" style="max-width: 100%; max-height: 75vh; object-fit: contain; box-shadow: 0 4px 15px rgba(0,0,0,0.15); border-radius: 6px; background: #fff;" />
                </div>
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

    <!-- Edit Template Preview Image Modal -->
    <div class="modal fade" id="editTemplateImgModal" tabindex="-1" aria-labelledby="editTemplateImgModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="formEditTemplateImg" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_template_preview_image">
                    <input type="hidden" name="template_id" id="editTemplateId" value="">

                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold" id="editTemplateImgModalLabel">
                            <i class="ti ti-photo me-2 text-warning"></i> Update Preview Image
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Template Name</label>
                            <input type="text" class="form-control" id="editTemplateName" readonly style="background-color: #f8fafc;">
                        </div>

                        <div class="mb-3 text-center">
                            <label class="form-label fw-bold d-block text-start">Current Preview</label>
                            <div class="p-2 border rounded bg-light d-inline-block">
                                <img id="editCurrentImgPreview" src="" alt="Preview" style="max-height: 140px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editPreviewFileInput" class="form-label fw-bold">Choose New Image (Auto converts to WebP)</label>
                            <input class="form-control" type="file" id="editPreviewFileInput" name="preview_image" accept="image/png, image/jpeg, image/webp, image/gif" required>
                            <div class="form-text text-muted">Supports JPG, PNG, GIF, WebP. It will automatically convert to optimized WebP format.</div>
                        </div>

                        <div id="newImgSelectedPreviewBox" class="mb-2 text-center" style="display: none;">
                            <label class="form-label fw-bold d-block text-start text-success">New Selected Preview</label>
                            <div class="p-2 border border-success rounded bg-white d-inline-block">
                                <img id="newImgSelectedPreview" src="" alt="New Preview" style="max-height: 140px; max-width: 100%; object-fit: contain; border-radius: 4px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top py-2">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveTemplateImg">
                            <i class="ti ti-check me-1"></i> Upload & Convert to WebP
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>
    <div class="drag-target"></div>

    <?php include('includes/footer_js.php'); ?>

    <script>
        $(document).ready(function() {
            // Open Direct Image View Modal on Thumbnail Click
            var imgViewModal = new bootstrap.Modal(document.getElementById('imageDirectViewModal'));
            $(document).on('click', '.btn-view-image', function(e) {
                e.preventDefault();
                var imgSrc = $(this).data('img') || $(this).attr('href');
                var name = $(this).data('name') || 'Preview Image';

                $('#imgModalTemplateName').text(name);
                $('#imgModalOpenTabBtn').attr('href', imgSrc);
                $('#imgDirectFullView').attr('src', imgSrc);

                imgViewModal.show();
            });

            // Open Preview Modal on Button / Name Click
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

            // Open Edit Template Preview Image Modal
            var editImgModal = new bootstrap.Modal(document.getElementById('editTemplateImgModal'));
            $(document).on('click', '.btn-edit-template-img', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                var name = $(this).data('name');
                var img = $(this).data('current-img');

                $('#editTemplateId').val(id);
                $('#editTemplateName').val(name);
                $('#editCurrentImgPreview').attr('src', img);
                $('#editPreviewFileInput').val('');
                $('#newImgSelectedPreviewBox').hide();
                $('#newImgSelectedPreview').attr('src', '');

                editImgModal.show();
            });

            // Instant Preview of Selected Image
            $('#editPreviewFileInput').on('change', function() {
                var file = this.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        $('#newImgSelectedPreview').attr('src', e.target.result);
                        $('#newImgSelectedPreviewBox').fadeIn();
                    };
                    reader.readAsDataURL(file);
                } else {
                    $('#newImgSelectedPreviewBox').hide();
                }
            });

            // Submit Image Update via AJAX
            $('#formEditTemplateImg').on('submit', function(e) {
                e.preventDefault();

                var fileInput = document.getElementById('editPreviewFileInput');
                if (!fileInput.files.length) {
                    alert('Please select an image file.');
                    return;
                }

                var formData = new FormData(this);
                var saveBtn = $('#btnSaveTemplateImg');
                var origBtnHtml = saveBtn.html();

                saveBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Converting to WebP...');

                $.ajax({
                    url: 'ajax.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        saveBtn.prop('disabled', false).html(origBtnHtml);
                        if (res && res.status) {
                            editImgModal.hide();

                            // Update the image in the table directly without needing page reload
                            var tplId = $('#editTemplateId').val();
                            $('button[data-id="' + tplId + '"].btn-edit-template-img').data('current-img', res.preview_url);
                            var $row = $('button[data-id="' + tplId + '"].btn-edit-template-img').closest('tr');
                            $row.find('img.template-thumb-preview').attr('src', res.preview_url);
                            $row.find('.btn-view-image').attr('href', res.preview_url).data('img', res.preview_url);

                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Updated Successfully!',
                                    text: res.message,
                                    timer: 2000,
                                    showConfirmButton: false,
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                            } else if (typeof toastr !== 'undefined') {
                                toastr.success(res.message);
                            } else {
                                alert(res.message);
                            }
                        } else {
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: res.message || 'Failed to update image.',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                            } else if (typeof toastr !== 'undefined') {
                                toastr.error(res.message || 'Failed to update image.');
                            } else {
                                alert(res.message || 'Failed to update image.');
                            }
                        }
                    },
                    error: function() {
                        saveBtn.prop('disabled', false).html(origBtnHtml);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error!',
                                text: 'Error connecting to server. Please try again.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            });
                        } else if (typeof toastr !== 'undefined') {
                            toastr.error('Error connecting to server.');
                        } else {
                            alert('Error connecting to server.');
                        }
                    }
                });
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
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: res.message,
                                    timer: 1500,
                                    showConfirmButton: false,
                                    toast: true,
                                    position: 'top-end'
                                });
                            } else if (typeof toastr !== 'undefined') {
                                toastr.success(res.message);
                            }
                        } else {
                            chk.prop('checked', !isPremium);
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: res.message || 'Failed to update premium status.',
                                    customClass: {
                                        confirmButton: 'btn btn-primary'
                                    },
                                    buttonsStyling: false
                                });
                            } else if (typeof toastr !== 'undefined') {
                                toastr.error(res.message || 'Failed to update premium status.');
                            } else {
                                alert(res.message || 'Failed to update premium status.');
                            }
                        }
                    },
                    error: function() {
                        chk.prop('checked', !isPremium);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error!',
                                text: 'Error connecting to server.',
                                customClass: {
                                    confirmButton: 'btn btn-primary'
                                },
                                buttonsStyling: false
                            });
                        } else if (typeof toastr !== 'undefined') {
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