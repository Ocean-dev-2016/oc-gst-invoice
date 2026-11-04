<?php
$f = file_get_contents('test_card_minh296.pdf');
$p = strpos($f, '18 0 obj');
$sp = strpos($f, "stream\n", $p);
if ($sp === false) $sp = strpos($f, "stream\r\n", $p) + 8;
else $sp += 7;
$ep = strpos($f, 'endstream', $sp);
$data = substr($f, $sp, $ep - $sp);
$decomp = @gzuncompress($data) ?: @gzinflate(substr($data, 2));
echo "Stream 18 length: " . strlen($decomp) . "\n";
// Find coordinates of signature / Authorized Signatory or footer elements
echo "Last 800 chars:\n" . substr($decomp, -800) . "\n";

