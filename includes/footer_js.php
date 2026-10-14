<script src="<?= ADMIN_URL; ?>assets/vendor/libs/jquery/jquery.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/popper/popper.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/js/bootstrap.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/node-waves/node-waves.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/hammer/hammer.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/i18n/i18n.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/typeahead-js/typeahead.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/js/menu.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/sweetalert2/sweetalert2.js"></script>
<!-- endbuild -->

<!-- Vendors JS -->
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/@form-validation/popular.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/@form-validation/bootstrap5.js"></script>
<script src="<?= ADMIN_URL; ?>assets/vendor/libs/@form-validation/auto-focus.js"></script>

<!-- Main JS -->
<script src="<?= ADMIN_URL; ?>assets/js/main.js"></script>


<!-- Page JS -->
<script src="<?= ADMIN_URL; ?>assets/js/pages-auth.js"></script>
  <script src="<?php ADMIN_URL; ?>ckeditor/ckeditor.js"></script>

<?php if (isset($ai_page_perm) && is_array($ai_page_perm)) { ?>
<script>
    window.aiPagePerm = <?= json_encode($ai_page_perm) ?>;
    (function() {
        if (!window.aiPagePerm || window.aiPagePerm.user_type !== 'user') {
            return;
        }

        const perm = window.aiPagePerm;
        const hideLinksByMode = (mode, allowed) => {
            if (allowed) {
                return;
            }
            document.querySelectorAll(`a[href*="mode=${mode}"]`).forEach(link => {
                link.dataset.aiHidden = '1';
                link.style.display = 'none';
            });
        };

        hideLinksByMode('add', !!perm.can_add);
        hideLinksByMode('edit', !!perm.can_edit);
        hideLinksByMode('delete', !!perm.can_delete);
        if (!perm.can_delete) {
            document.querySelectorAll(`a[href*="action=delete"]`).forEach(link => {
                link.dataset.aiHidden = '1';
                link.style.display = 'none';
            });
        }

        if (!perm.can_edit) {
            document.querySelectorAll('a[href*="manage-role-permission.php"]').forEach(link => {
                link.dataset.aiHidden = '1';
                link.style.display = 'none';
            });
        }

        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            const visibleItems = Array.from(menu.querySelectorAll('a.dropdown-item')).filter(item => item.style.display !== 'none');
            if (visibleItems.length === 0) {
                const dropdown = menu.closest('.dropdown');
                if (dropdown) {
                    dropdown.style.display = 'none';
                }
            }
        });
    })();
</script>
<?php } ?>
<style>
  /* Ensure Deny button ('No') is completely hidden on confirmation alerts */
  .swal2-deny {
    display: none !important;
  }
</style>
<script>
  (function () {
    var GLOBAL_DELETE_MESSAGE = 'You Want To Delete This Record?';

    function showDeleteSweetAlert(url, message) {
      if (typeof Swal === 'undefined') {
        if (confirm(message || GLOBAL_DELETE_MESSAGE)) {
          window.location.href = url;
        }
        return;
      }

      Swal.fire({
        title: 'Are you sure?',
        text: message || GLOBAL_DELETE_MESSAGE,
        icon: 'warning',
        showCancelButton: true,
        showDenyButton: false,
        denyButtonText: '',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        buttonsStyling: false,
        customClass: {
          confirmButton: 'btn btn-danger me-3 waves-effect waves-light',
          cancelButton: 'btn btn-secondary waves-effect waves-light'
        },
        reverseButtons: true,
        didOpen: function () {
          var denyBtn = Swal.getDenyButton ? Swal.getDenyButton() : null;
          if (denyBtn) {
            denyBtn.style.display = 'none';
            denyBtn.remove();
          }
          document.querySelectorAll('.swal2-deny').forEach(function (btn) {
            btn.style.display = 'none';
            btn.remove();
          });
        }
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = url;
        }
      });
    }

    function bindDeleteConfirm(anchor) {
      if (!anchor || anchor.dataset.swalBound === '1') return;
      var inlineOnclick = anchor.getAttribute('onclick') || '';
      var extractedUrl = '';

      var rawHref = anchor.getAttribute('href') || '';
      if (rawHref && rawHref !== 'javascript:void(0);' && rawHref !== 'javascript:;' && rawHref !== '#') {
        extractedUrl = rawHref;
      } else if (inlineOnclick) {
        var match = inlineOnclick.match(/window\.location(?:\.href)?\s*=\s*['"]([^'"]+)['"]/i);
        if (match && match[1]) {
          extractedUrl = match[1];
        }
      }

      if (!extractedUrl) return;

      anchor.removeAttribute('onclick');
      anchor.dataset.targetUrl = extractedUrl;
      anchor.dataset.confirmMessage = GLOBAL_DELETE_MESSAGE;
      anchor.dataset.swalBound = '1';

      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var url = anchor.dataset.targetUrl || anchor.getAttribute('href');
        var message = anchor.dataset.confirmMessage || GLOBAL_DELETE_MESSAGE;

        if (!url || url === 'javascript:void(0);' || url === '#') return;
        showDeleteSweetAlert(url, message);
      });
    }

    // Run immediately for currently rendered elements
    document.querySelectorAll('a[onclick*="confirm("], a.js-delete-hero, a[data-confirm-message]').forEach(bindDeleteConfirm);

    // Also run on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('a[onclick*="confirm("], a.js-delete-hero, a[data-confirm-message]').forEach(bindDeleteConfirm);
    });

    // Delegated click handler on document level to ensure all delete clicks are intercepted and handled cleanly
    document.addEventListener('click', function(e) {
      var anchor = e.target.closest('a[onclick*="confirm("], a.js-delete-hero, a[data-confirm-message], a.text-danger[href*="action=delete"], a.text-danger[href*="mode=delete"]');
      if (!anchor) return;

      var rawHref = anchor.getAttribute('href') || '';
      var inlineOnclick = anchor.getAttribute('onclick') || '';
      var targetUrl = anchor.dataset.targetUrl || '';

      if (!targetUrl) {
        if (rawHref && rawHref !== 'javascript:void(0);' && rawHref !== 'javascript:;' && rawHref !== '#') {
          targetUrl = rawHref;
        } else if (inlineOnclick) {
          var match = inlineOnclick.match(/window\.location(?:\.href)?\s*=\s*['"]([^'"]+)['"]/i);
          if (match && match[1]) {
            targetUrl = match[1];
          }
        }
      }

      if (!targetUrl || targetUrl === 'javascript:void(0);' || targetUrl === '#') return;

      e.preventDefault();
      e.stopPropagation();

      var message = anchor.dataset.confirmMessage || GLOBAL_DELETE_MESSAGE;
      showDeleteSweetAlert(targetUrl, message);
    }, true);
  })();
</script>

<script src="<?= ADMIN_URL; ?>assets/vendor/libs/select2/select2.js"></script>
<script>
    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').each(function() {
                var $this = $(this);
                if (!$this.hasClass("select2-hidden-accessible")) {
                    var ph = $this.attr('placeholder') || $this.find('option[value=""]').text() || '-- Select --';
                    if (!$this.parent().hasClass('position-relative')) {
                        $this.wrap('<div class="position-relative"></div>');
                    }
                    $this.select2({
                        placeholder: ph,
                        allowClear: true,
                        dropdownParent: $this.parent()
                    });
                }
            });
        }
    });
</script>

