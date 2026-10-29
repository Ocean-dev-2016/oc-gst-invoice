<?php
// Function to generate standardized footer HTML with Left: Terms & Conditions and Right: Signature
function renderStandardInvoiceFooter($company, $q, $themeColor = '#1e293b') {
    $companyName = !empty($company->company_name) ? htmlspecialchars($company->company_name) : 'Company';
    $signImg = !empty($company->signature) ? SITE_URL . $company->signature : '';
    $terms = !empty($q->terms_condition) ? nl2br(htmlspecialchars($q->terms_condition)) : "1. Goods once sold will not be taken back.<br>2. Interest @ 18% p.a. will be charged if payment is not made within due date.<br>3. Subject to local jurisdiction.";
    ?>
    <style>
        .std-invoice-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            gap: 30px;
            border-top: 1px dashed #e2e8f0;
            page-break-inside: avoid;
        }
        .std-terms-col {
            flex: 1;
            max-width: 58%;
        }
        .std-terms-title {
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: <?= $themeColor ?>;
            margin-bottom: 6px;
        }
        .std-terms-content {
            font-size: 11px;
            color: #64748b;
            line-height: 1.6;
        }
        .std-sign-col {
            width: 220px;
            flex-shrink: 0;
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }
        .std-sign-preview {
            max-height: 50px;
            max-width: 150px;
            object-fit: contain;
            margin-bottom: 6px;
        }
        .std-sign-line {
            width: 180px;
            border-top: 1.5px solid <?= $themeColor ?>;
            padding-top: 5px;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            text-align: center;
        }
        .std-sign-subtitle {
            font-size: 10.5px;
            font-weight: 500;
            color: #64748b;
            margin-top: 2px;
            text-align: center;
        }
        @media screen and (max-width: 768px) {
            .std-invoice-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .std-terms-col { max-width: 100%; }
            .std-sign-col { width: 100%; align-items: flex-start; }
            .std-sign-line { text-align: left; margin: 0; }
            .std-sign-subtitle { text-align: left; }
        }
        @media print {
            .std-invoice-footer {
                page-break-inside: avoid;
                margin-top: 20px !important;
                padding-top: 15px !important;
            }
            .std-terms-content { font-size: 10px !important; color: #475569 !important; }
            .std-sign-line { font-size: 11px !important; }
        }
    </style>
    <div class="std-invoice-footer">
        <!-- Left Side: Terms & Conditions -->
        <div class="std-terms-col">
            <div class="std-terms-title">Terms &amp; Conditions</div>
            <div class="std-terms-content"><?= $terms ?></div>
        </div>

        <!-- Right Side: Signature -->
        <div class="std-sign-col">
            <?php if (!empty($signImg)): ?>
                <img src="<?= $signImg ?>" alt="Signature" class="std-sign-preview">
            <?php endif; ?>
            <div class="std-sign-line">
                For <?= $companyName ?>
                <div class="std-sign-subtitle">Authorized Signatory</div>
            </div>
        </div>
    </div>
    <?php
}

