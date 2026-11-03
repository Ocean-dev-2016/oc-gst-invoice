<?php
// Shared invoice PDF save & download scripts
?>
<!-- html2pdf.js for direct client-side PDF download & server-side save -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
    /* Hide action bar during PDF generation */
    .is-generating-pdf .top-action-bar,
    .is-generating-pdf .top-bar,
    .is-generating-pdf .no-print {
        display: none !important;
    }

    .is-generating-pdf .invoice-wrapper {
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        background: transparent !important;
    }

    .is-generating-pdf .invoice-card {
        box-shadow: none !important;
        border: none !important;
        margin: 0 auto !important;
    }

    /* ===== Browser Print (@media print) ===== */
    @media print {
        @page {
            size: A4 portrait;
            margin: 5px;
        }

        body {
            background: #ffffff !important;
            margin: 0 !important;
            padding: 0 !important;
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
            border: none !important;
            box-shadow: none !important;
        }

        .invoice-card {
            box-shadow: none !important;
            border: none !important;
            margin: 0 auto !important;
            padding: 10px 15px !important;
            height: auto !important;
            display: flex !important;
            flex-direction: column !important;
            max-width: 100% !important;
            page-break-inside: avoid;
        }

        .invoice-footer-pin,
        .std-invoice-footer,
        .invoice-footer {
            margin-top: auto !important;
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
            margin: [MARGIN_PX_IN_MM, MARGIN_PX_IN_MM, MARGIN_PX_IN_MM, MARGIN_PX_IN_MM],
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
            }
        };
    }

    /**
     * Prepares the invoice container for 1-page PDF rendering:
     * 1. Resets any forced borders/margins that cause overflow.
     * 2. Calculates exact single-page A4 height available.
     * 3. Only inserts spacer if height is safely below page limit with a generous 150px buffer.
     * 4. Multi-page: does NOT add spacer, lets natural layout flow so content does not get forced onto an empty page.
     */
    function prepareCardForPdf(element) {
        var footer = element.querySelector('.invoice-footer-pin') ||
            element.querySelector('.std-invoice-footer') ||
            element.querySelector('.invoice-footer');

        if (!footer) {
            return function() {};
        }

        var spacer = element.querySelector('.pdf-footer-spacer');
        if (!spacer) {
            spacer = document.createElement('div');
            spacer.className = 'pdf-footer-spacer';
            footer.parentNode.insertBefore(spacer, footer);
        }

        spacer.style.height = '0px';
        spacer.style.display = 'block';
        spacer.style.margin = '0';
        spacer.style.padding = '0';
        spacer.style.border = 'none';

        var originalHeight = element.style.height;
        var originalMinHeight = element.style.minHeight;
        element.style.height = 'auto';
        element.style.minHeight = 'none';

        // Ratio of A4 printable area: (297 - 2*M) / (210 - 2*M) = 1.414
        var printW = 210 - (2 * MARGIN_PX_IN_MM);
        var printH = 297 - (2 * MARGIN_PX_IN_MM);
        var ratio = printH / printW;

        var cardWidth = element.offsetWidth || 840;
        var maxSinglePageHeight = Math.floor(cardWidth * ratio);

        var naturalHeight = element.offsetHeight || element.scrollHeight;

        // Only add spacer if content comfortably fits on a single page,
        // with a 150px safety buffer to GUARANTEE footer never overflows into page 2!
        if (naturalHeight < (maxSinglePageHeight - 150)) {
            var spaceToAdd = (maxSinglePageHeight - 150) - naturalHeight;
            if (spaceToAdd > 0) {
                spacer.style.height = spaceToAdd + 'px';
            }
        }

        return function() {
            if (spacer) {
                spacer.style.height = '0px';
            }
            element.style.height = originalHeight;
            element.style.minHeight = originalMinHeight;
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
            window.print();
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
            restoreCard();
            uploadPdfBlobToServer(pdfBlob).catch(function(e) {
                console.warn('Background server PDF save failed:', e);
            });
            return html2pdf().set(opt).from(element).save();
        }).then(function() {
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
            window.print();
        });
    }

    window.addEventListener('load', function() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('silent_gen') === '1') {
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