<?php
// Shared invoice PDF save & download scripts
?>
<!-- html2pdf.js for direct client-side PDF download & server-side save -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
    :root {
        --invoice-pdf-side-gutter: 16px;
        --invoice-pdf-header-inset: 12px;
    }

    /* Hide action bar during PDF generation */
    .is-generating-pdf .top-action-bar,
    .is-generating-pdf .top-bar,
    .is-generating-pdf .no-print {
        display: none !important;
    }

    .is-generating-pdf body {
        background: #ffffff !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .is-generating-pdf .invoice-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
        max-width: 100% !important;
    }

    .is-generating-pdf .invoice-card {
        box-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
        padding: 0 var(--invoice-pdf-side-gutter) !important;
        min-height: unset !important;
        height: auto !important;
    }

    .is-generating-pdf .invoice-wrapper {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .is-generating-pdf .invoice-card > .modern-content,
    .is-generating-pdf .invoice-card > .parties-row,
    .is-generating-pdf .invoice-card > .parties-grid,
    .is-generating-pdf .invoice-card > .info-section,
    .is-generating-pdf .invoice-card > .items-wrapper,
    .is-generating-pdf .invoice-card > .invoice-footer-pin,
    .is-generating-pdf .invoice-card > div[style*="padding"] {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    .is-generating-pdf .invoice-card > .modern-banner,
    .is-generating-pdf .invoice-card > .emerald-header,
    .is-generating-pdf .invoice-card > .crimson-header,
    .is-generating-pdf .invoice-card > .header-split {
        margin-left: calc(-1 * var(--invoice-pdf-header-inset)) !important;
        margin-right: calc(-1 * var(--invoice-pdf-header-inset)) !important;
        padding-left: var(--invoice-pdf-header-inset) !important;
        padding-right: var(--invoice-pdf-header-inset) !important;
    }

    .is-generating-pdf .invoice-card > .header-split > .header-dark {
        padding-left: var(--invoice-pdf-header-inset) !important;
    }

    .is-generating-pdf .invoice-card .invoice-footer-pin > .std-invoice-footer {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }

    .is-generating-pdf .invoice-footer-pin,
    .is-generating-pdf .std-invoice-footer,
    .is-generating-pdf .ref-calc-box,
    .is-generating-pdf .ref-items-table,
    .is-generating-pdf .bottom-bar {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }

    /* ===== Browser Print (@media print) & Server-Side Headless PDF ===== */
    @media print {
        @page {
            size: A4 portrait;
            margin: 0 !important;
        }

        html, body {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }

        .top-action-bar,
        .top-bar,
        .no-print {
            display: none !important;
        }

        .invoice-wrapper {
            margin: 0 !important;
            padding: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
            border: none !important;
            box-shadow: none !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: column !important;
        }

        .invoice-card {
            box-shadow: none !important;
            border: none !important;
            margin: 0 !important;
            padding: 0 var(--invoice-pdf-side-gutter) !important;
            box-sizing: border-box !important;
            width: 100% !important;
            max-width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            box-sizing: border-box !important;
            min-height: 0 !important;
            height: auto !important;
            page-break-inside: auto !important;
            break-inside: auto !important;
        }

        .invoice-card > .modern-content,
        .invoice-card > .parties-row,
        .invoice-card > .parties-grid,
        .invoice-card > .info-section,
        .invoice-card > .items-wrapper,
        .invoice-card > .invoice-footer-pin,
        .invoice-card > div[style*="padding"] {
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        .invoice-card > .modern-banner,
        .invoice-card > .emerald-header,
        .invoice-card > .crimson-header,
        .invoice-card > .header-split {
            margin-left: calc(-1 * var(--invoice-pdf-header-inset)) !important;
            margin-right: calc(-1 * var(--invoice-pdf-header-inset)) !important;
            padding-left: var(--invoice-pdf-header-inset) !important;
            padding-right: var(--invoice-pdf-header-inset) !important;
        }

        .invoice-card > .header-split > .header-dark {
            padding-left: var(--invoice-pdf-header-inset) !important;
        }

        .std-footer-accent {
            width: auto !important;
            margin-left: calc(-1 * var(--invoice-pdf-header-inset)) !important;
            margin-right: calc(-1 * var(--invoice-pdf-header-inset)) !important;
        }

        .invoice-card .invoice-footer-pin > .std-invoice-footer {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .modern-content {
            display: flex !important;
            flex-direction: column !important;
            flex: 1 1 auto !important;
            box-sizing: border-box !important;
        }

        .invoice-footer-pin,
        .std-invoice-footer,
        .invoice-footer {
            margin-top: auto !important;
            flex-shrink: 0 !important;
            page-break-before: avoid !important;
            break-before: avoid !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }

        .bottom-bar {
            page-break-before: avoid !important;
            break-before: avoid !important;
        }
    }
</style>
<script>
    var currentInvoiceId = <?= intval($q->id ?? 0) ?>;
    var currentDocType = <?= json_encode($docType ?? 'invoice') ?>;
    <?php
    $formattedId = str_pad($q->id ?? 0, 2, '0', STR_PAD_LEFT);
    if ($docType === 'quotation') {
        $rawDocNo = $q->quotation_no ?? ($q->invoice_no ?? $q->id);
        $cleanDocSlug = preg_replace('/[^a-zA-Z0-9_]/', '_', $rawDocNo);
        $finalPdfName = 'quotation_' . $formattedId . '_' . $cleanDocSlug . '.pdf';
    } elseif ($docType === 'purchase') {
        $rawDocNo = $q->purchase_no ?? ($q->invoice_no ?? $q->id);
        $cleanDocSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $rawDocNo);
        $finalPdfName = 'purchase_' . ($q->id ?? 0) . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $rawDocNo) . '.pdf';
    } else {
        $rawDocNo = $q->invoice_no ?? $q->id;
        $cleanDocSlug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $rawDocNo);
        $finalPdfName = 'invoice_' . ($q->id ?? 0) . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $rawDocNo) . '.pdf';
    }
    ?>
    var invoiceFileName = <?= json_encode($finalPdfName) ?>;

    // 5px margin in mm for jsPDF (96 dpi: 5px / (96 / 25.4) = ~1.32 mm)
    var MARGIN_PX_IN_MM = 5 * 25.4 / 96;

    function getPdfOptions() {
        return {
            margin: 0,
            filename: invoiceFileName,
            image: {
                type: 'jpeg',
                quality: 0.98
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                logging: false,
                scrollX: 0,
                scrollY: 0
            },
            jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: 'portrait'
            },
            pagebreak: {
                mode: ['css', 'legacy']
            }
        };
    }

    /**
     * Fits the invoice card to one A4 page and keeps its footer at the bottom.
     */
    function prepareCardForPdf(element) {
        var footer = element.querySelector('.invoice-footer-pin') ||
            element.querySelector('.std-invoice-footer') ||
            element.querySelector('.invoice-footer');

        // Hide any stale spacer divs
        var spacer = element.querySelector('.pdf-footer-spacer');
        if (spacer) {
            spacer.style.height = '0px';
            spacer.style.display = 'none';
        }

        // Save original styles
        var originalHeight = element.style.height;
        var originalMinHeight = element.style.minHeight;
        var originalPadding = element.style.padding;
        var originalFooterMargin = footer ? footer.style.marginTop : '';
        var originalOverflow = element.style.overflow;
        var originalDisplay = element.style.display;
        var originalFlexDir = element.style.flexDirection;
        var originalBoxSizing = element.style.boxSizing;
        var originalZoom = element.style.zoom;

        // Use the full printable page width and remove template-level card gutters.
        var originalWidth = element.style.width;
        var originalMaxWidth = element.style.maxWidth;
        element.style.setProperty('width', '790px', 'important');
        element.style.setProperty('max-width', '790px', 'important');
        element.style.setProperty('padding', '0 16px', 'important');

        element.style.setProperty('height', 'auto', 'important');
        element.style.setProperty('min-height', 'unset', 'important');
        element.style.setProperty('zoom', '1', 'important');
        if (footer) {
            footer.style.setProperty('margin-top', '15px', 'important');
        }

        void element.offsetHeight;
        var naturalH = element.scrollHeight;
        var targetA4H = 1100;
        var scale = naturalH > targetA4H ? targetA4H / naturalH : 1;
        var cardHeight = targetA4H / scale;

        var innerWrapper = element.querySelector('.modern-content');
        var originalInnerFlex = innerWrapper ? innerWrapper.style.display : null;
        var originalInnerFlexDir = innerWrapper ? innerWrapper.style.flexDirection : null;
        var originalInnerGrow = innerWrapper ? innerWrapper.style.flex : null;
        var originalFooterFlexShrink = footer ? footer.style.flexShrink : '';

        element.style.setProperty('height', cardHeight + 'px', 'important');
        element.style.setProperty('min-height', cardHeight + 'px', 'important');
        element.style.setProperty('display', 'flex', 'important');
        element.style.setProperty('flex-direction', 'column', 'important');
        element.style.setProperty('box-sizing', 'border-box', 'important');
        element.style.setProperty('zoom', String(scale), 'important');

        if (footer) {
            footer.style.setProperty('margin-top', 'auto', 'important');
            footer.style.setProperty('flex-shrink', '0', 'important');
        }

        if (innerWrapper) {
            innerWrapper.style.setProperty('display', 'flex', 'important');
            innerWrapper.style.setProperty('flex-direction', 'column', 'important');
            innerWrapper.style.setProperty('flex', '1 1 auto', 'important');
            innerWrapper.style.setProperty('box-sizing', 'border-box', 'important');
        }

        return function() {
            element.style.width = originalWidth;
            element.style.maxWidth = originalMaxWidth;
            element.style.height = originalHeight;
            element.style.minHeight = originalMinHeight;
            element.style.padding = originalPadding;
            element.style.overflow = originalOverflow;
            element.style.display = originalDisplay;
            element.style.flexDirection = originalFlexDir;
            element.style.boxSizing = originalBoxSizing;
            element.style.zoom = originalZoom;
            if (footer) {
                footer.style.marginTop = originalFooterMargin;
                footer.style.flexShrink = originalFooterFlexShrink;
            }

            if (innerWrapper) {
                if (originalInnerFlex !== null) innerWrapper.style.display = originalInnerFlex;
                if (originalInnerFlexDir !== null) innerWrapper.style.flexDirection = originalInnerFlexDir;
                if (originalInnerGrow !== null) innerWrapper.style.flex = originalInnerGrow;
            }

            if (spacer) {
                spacer.style.height = '0px';
                spacer.style.display = 'none';
            }
        };
    }
    function uploadPdfBlobToServer(blob) {
        var formData = new FormData();
        formData.append('invoice_id', currentInvoiceId);
        formData.append('doc_type', currentDocType);
        formData.append('pdf_blob', blob, invoiceFileName);

        return fetch('save-invoice-pdf.php', {
            method: 'POST',
            body: formData
        }).then(function(res) {
            return res.json();
        });
    }

    function printInvoiceFallback(element) {
        document.body.classList.add('is-generating-pdf');
        var restoreCard = prepareCardForPdf(element);
        window.addEventListener('afterprint', function() {
            restoreCard();
            document.body.classList.remove('is-generating-pdf');
        }, { once: true });
        window.print();
    }

    function saveAndShowPDF() {
        var topBar = document.querySelector('.top-action-bar') || document.querySelector('.top-bar');
        var originalBarHtml = topBar ? topBar.innerHTML : '';
        if (topBar) {
            topBar.innerHTML = '<div style="color: #1e293b; font-weight: 600; padding: 10px;"><i class="ti ti-loader ti-spin me-2"></i> PDF બનાવી રહ્યા છીએ, કૃપા કરીને થોડી રાહ જુઓ...</div>';
        }

        document.body.classList.add('is-generating-pdf');
        var element = document.querySelector('.invoice-card');
        var restoreCard = prepareCardForPdf(element);
        var opt = getPdfOptions();

        html2pdf().set(opt).from(element).outputPdf('blob').then(function(pdfBlob) {
            restoreCard();
            return uploadPdfBlobToServer(pdfBlob);
        }).then(function(data) {
            document.body.classList.remove('is-generating-pdf');
            if (data && data.status && data.pdf_url) {
                window.location.replace(data.pdf_url);
            } else {
                throw new Error(data && data.message ? data.message : 'Server upload failed');
            }
        }).catch(function(err) {
            restoreCard();
            document.body.classList.remove('is-generating-pdf');
            console.error('Save & Show PDF error:', err);
            if (topBar) {
                topBar.innerHTML = originalBarHtml;
            }
            alert('PDF generation notice: ' + err.message);
            printInvoiceFallback(element);
        });
    }

    function downloadPDF() {
        var btn = document.getElementById('btnDownloadPdf');
        var originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ti ti-loader"></i> Processing...';
        }

        document.body.classList.add('is-generating-pdf');
        var element = document.querySelector('.invoice-card');
        var restoreCard = prepareCardForPdf(element);
        var opt = getPdfOptions();

        html2pdf().set(opt).from(element).outputPdf('blob').then(function(pdfBlob) {
            uploadPdfBlobToServer(pdfBlob).catch(function(e) {
                console.warn('Background server PDF save failed:', e);
            });
            return html2pdf().set(opt).from(element).save();
        }).then(function() {
            restoreCard();
            document.body.classList.remove('is-generating-pdf');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }).catch(function(err) {
            restoreCard();
            document.body.classList.remove('is-generating-pdf');
            console.error('PDF error:', err);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
            printInvoiceFallback(element);
        });
    }

    window.addEventListener('load', function() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('token') === 'system_auto_pdf') {
            var element = document.querySelector('.invoice-card');
            if (element) {
                document.body.classList.add('is-generating-pdf');
                prepareCardForPdf(element);
            }
        } else if (urlParams.get('silent_gen') === '1') {
            // Background generation without redirecting user away - wait 800ms for template DOM & fonts
            setTimeout(function() {
                var element = document.querySelector('.invoice-card');
                if (!element) {
                    console.error('silent_gen: .invoice-card not found in DOM');
                    return;
                }
                document.body.classList.add('is-generating-pdf');
                var restoreCard = prepareCardForPdf(element);
                var opt = getPdfOptions();
                html2pdf().set(opt).from(element).outputPdf('blob').then(function(pdfBlob) {
                    restoreCard();
                    document.body.classList.remove('is-generating-pdf');
                    return uploadPdfBlobToServer(pdfBlob);
                }).then(function(data) {
                    console.log('Background PDF saved successfully:', data);
                }).catch(function(err) {
                    restoreCard();
                    document.body.classList.remove('is-generating-pdf');
                    console.error('Background PDF save error:', err);
                });
            }, 800);
        } else if (urlParams.get('store_and_show') === '1' || urlParams.get('save_and_show') === '1') {
            setTimeout(saveAndShowPDF, 400);
        } else if (urlParams.get('download') === '1') {
            setTimeout(downloadPDF, 600);
        }
    });
</script>
