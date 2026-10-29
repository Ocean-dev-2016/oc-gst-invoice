<?php
/**
 * Renders the exact Table & Totals block matching user's two reference images:
 * Image 1: With GST (HSN, RATE, QTY, DISCOUNT, TAXABLE AMT, GST, TOTAL) + CGST/SGST/IGST breakdown
 * Image 2: Without GST (HSN, RATE, QTY, DISCOUNT, TAXABLE AMT, TOTAL) - NO GST column & NO Tax breakdown
 */
function renderInvoiceProductTableAndTotals($items, $q, $isGst, $hasCgstSgst, $hasIgst, $amountInWords, $themeColor = null) {
    // Determine Theme Color based on active template if not explicitly provided
    if (empty($themeColor)) {
        if (isset($GLOBALS['currentTemplateSlug'])) {
            $slug = $GLOBALS['currentTemplateSlug'];
            if ($slug === 'template-modern') {
                $themeColor = '#0284c7'; // Navy/Sky Blue
            } elseif ($slug === 'template-golden-executive') {
                $themeColor = '#e5ad45'; // Gold
            } elseif ($slug === 'template-minimal') {
                $themeColor = '#171717'; // Neutral Dark
            } else {
                $themeColor = '#7367f0'; // Classic Primary Purple
            }
        } else {
            $themeColor = '#0284c7';
        }
    }
    // Background tint for grand total strip
    $stripBg = 'rgba(' . implode(',', sscanf(ltrim($themeColor, '#'), "%02x%02x%02x")) . ', 0.08)';
?>
    <style>
        :root {
            --tpl-theme-color: <?= $themeColor ?>;
            --tpl-strip-bg: <?= $stripBg ?>;
        }
        .ref-items-table {
            width: 100%; border-collapse: collapse; margin-bottom: 24px;
            border: 1px solid #dbeafe; font-size: 13px; background: #ffffff;
            table-layout: auto;
        }
        .ref-items-table th {
            background-color: #f0f7ff; color: #1e293b; padding: 10px 8px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.5px; border: 1px solid #dbeafe; text-align: center;
            white-space: nowrap;
        }
        .ref-items-table td {
            padding: 8px 8px; border: 1px solid #dbeafe; color: #1e293b;
            vertical-align: middle; font-size: 12px;
            word-break: normal;
        }
        .ref-calc-box {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-top: 15px; margin-bottom: 25px; gap: 20px;
        }
        .ref-words-card {
            flex: 1; min-width: 0; background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: 14px 16px;
        }
        .ref-words-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase;
            color: #64748b; margin-bottom: 6px; letter-spacing: 0.5px;
        }
        .ref-words-text {
            font-size: 13px; font-weight: 700; color: #1e293b; line-height: 1.5;
        }
        .ref-totals-table {
            width: 100%; max-width: 340px; font-size: 13px; border-collapse: collapse;
        }
        .ref-totals-table td { padding: 5px 0; }
        .ref-totals-table td.label { color: #475569; font-weight: 500; }
        .ref-totals-table td.val { text-align: right; font-weight: 600; color: #1e293b; white-space: nowrap; }
        .ref-grand-total-strip {
            background: <?= $stripBg ?>; border-radius: 6px; padding: 10px 14px;
            margin-top: 8px; display: flex; justify-content: space-between; align-items: center;
            border-top: 1px solid <?= $themeColor ?>; white-space: nowrap;
        }
        .ref-grand-label { font-size: 14px; font-weight: 800; color: <?= $themeColor ?>; }
        .ref-grand-val { font-size: 16px; font-weight: 800; color: <?= $themeColor ?>; }
        .ref-table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 20px;
        }
        @media screen and (max-width: 768px) {
            .ref-calc-box {
                flex-direction: column !important;
                gap: 15px !important;
            }
            .ref-words-card, .ref-calc-box > div {
                width: 100% !important;
                max-width: 100% !important;
            }
            .ref-totals-table {
                max-width: 100% !important;
            }
        }
        
        @media print {
            .ref-items-table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: auto;
            }
            .ref-items-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .ref-items-table th, .ref-items-table td {
                padding: 6px 6px !important;
                font-size: 11px !important;
            }
            .ref-calc-box {
                page-break-inside: avoid;
                gap: 15px !important;
            }
            .ref-totals-table td.val, .ref-grand-val {
                white-space: nowrap !important;
            }
        }
    </style>

    <!-- Product Details Table -->
    <div class="ref-table-responsive">
    <table class="ref-items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="text-align: left; padding-left: 14px;">ITEM DESCRIPTION</th>
                <th style="width: 11%;">HSN/SAC</th>
                <th style="width: 12%;">RATE</th>
                <th style="width: 8%;">QTY</th>
                <th style="width: 14%;">DISCOUNT</th>
                <th style="width: 14%;">TAXABLE AMT</th>
                <?php if ($isGst): ?>
                    <th style="width: 8%;">GST</th>
                <?php endif; ?>
                <th style="width: 14%;">TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($items)): foreach ($items as $idx => $it): 
                $itemQty = floatval($it->qty ?? 1);
                $itemRate = floatval($it->rate ?? 0);
                $itemTaxable = isset($it->net_amount) && floatval($it->net_amount) > 0 ? floatval($it->net_amount) : ($itemQty * $itemRate);
                $itemTotal = isset($it->total_amount) && floatval($it->total_amount) > 0 ? floatval($it->total_amount) : $itemTaxable;
                $itemGst = floatval($it->gst_percent ?? 0);

                // Discount Display
                $discVal = floatval($it->discount_value ?? 0);
                $discAmt = floatval($it->discount_amount ?? 0);
                $discType = $it->discount_type ?? 'percentage';
                $discText = '-';
                if ($discAmt > 0 || $discVal > 0) {
                    if ($discType === 'percentage') {
                        $discText = $discVal . '%<br><span style="font-size:11px; color:#64748b;">(₹' . number_format($discAmt, 2) . ')</span>';
                    } else {
                        $discText = '₹' . number_format($discVal, 2);
                    }
                }
            ?>
            <tr>
                <td style="text-align: center; color: #64748b;"><?= $idx + 1 ?></td>
                <td style="text-align: left; padding-left: 14px;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;"><?= htmlspecialchars($it->product_name ?? 'Item') ?></div>
                    <?php if (!empty($it->sku)): ?>
                        <div style="font-size: 11px; color: #64748b; margin-top: 1px;">SKU: <?= htmlspecialchars($it->sku) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($it->description)): ?>
                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?= nl2br(htmlspecialchars($it->description)) ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; color: #475569;"><?= !empty($it->hsn_code) ? htmlspecialchars($it->hsn_code) : '-' ?></td>
                <td style="text-align: center;">₹<?= number_format($itemRate, 2) ?></td>
                <td style="text-align: center;"><?= number_format($itemQty, 2) ?></td>
                <td style="text-align: center;"><?= $discText ?></td>
                <td style="text-align: center;">₹<?= number_format($itemTaxable, 2) ?></td>
                <?php if ($isGst): ?>
                    <td style="text-align: center; font-weight: 600;"><?= $itemGst ?>%</td>
                <?php endif; ?>
                <td style="text-align: center; font-weight: 800; color: #0f172a;">₹<?= number_format($itemTotal, 2) ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Calculation Section -->
    <div class="ref-calc-box">
        <!-- Amount in Words -->
        <div class="ref-words-card">
            <div class="ref-words-label">AMOUNT IN WORDS:</div>
            <div class="ref-words-text"><?= htmlspecialchars($amountInWords) ?></div>
        </div>

        <!-- Totals Table -->
        <div style="width: 320px; flex-shrink: 0;">
            <table class="ref-totals-table">
                <tr>
                    <td class="label">Sub Total (Taxable):</td>
                    <td class="val">₹<?= number_format(floatval($q->total_amount ?? 0), 2) ?></td>
                </tr>
                <?php if ($isGst): ?>
                    <?php if ($hasCgstSgst): ?>
                        <tr>
                            <td class="label">CGST Amount:</td>
                            <td class="val">₹<?= number_format(floatval($q->cgst_amount ?? 0), 2) ?></td>
                        </tr>
                        <tr>
                            <td class="label">SGST Amount:</td>
                            <td class="val">₹<?= number_format(floatval($q->sgst_amount ?? 0), 2) ?></td>
                        </tr>
                    <?php elseif ($hasIgst): ?>
                        <tr>
                            <td class="label">IGST Amount:</td>
                            <td class="val">₹<?= number_format(floatval($q->igst_amount ?? 0), 2) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endif; ?>
            </table>

            <div class="ref-grand-total-strip">
                <span class="ref-grand-label">Grand Total:</span>
                <span class="ref-grand-val">₹<?= number_format(floatval($q->grand_total ?? 0), 2) ?></span>
            </div>
        </div>
    </div>
<?php
}

