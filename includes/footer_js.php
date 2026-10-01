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
<script>
  (function () {
    var GLOBAL_DELETE_MESSAGE = 'You Want To Delete This Record?';

    function bindDeleteConfirm(anchor) {
      if (!anchor || anchor.dataset.swalBound === '1') return;
      var inlineOnclick = anchor.getAttribute('onclick') || '';

      anchor.removeAttribute('onclick');
      anchor.dataset.confirmMessage = GLOBAL_DELETE_MESSAGE;
      anchor.dataset.swalBound = '1';

      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        var url = anchor.getAttribute('href');
        var message = anchor.dataset.confirmMessage || GLOBAL_DELETE_MESSAGE;

        if (!url) return;

        if (typeof Swal === 'undefined') {
          if (confirm(message)) window.location.href = url;
          return;
        }

        Swal.fire({
          title: 'Are you sure?',
          text: message,
          icon: 'warning',
          buttonsStyling: true,
          showDenyButton: false,
          denyButtonText: '',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete it!',
          cancelButtonText: 'Cancel',
          confirmButtonColor: '#000000ff',
          cancelButtonColor: '#bfc3c9',
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
          if (result.isConfirmed) window.location.href = url;
        });
      });
    }

    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('a[onclick*="confirm("], a.js-delete-hero, a[data-confirm-message]').forEach(bindDeleteConfirm);
    });
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

