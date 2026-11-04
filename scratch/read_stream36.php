<?php
$f = file_get_contents('scratch_live.pdf');
$p = strpos($f, '36 0 obj');
$sp = strpos($f, "stream\n", $p);
if ($sp === false) $sp = strpos($f, "stream\r\n", $p) + 8;
else $sp += 7;
$ep = strpos($f, 'endstream', $sp);
$data = substr($f, $sp, $ep - $sp);
$decomp = @gzuncompress($data) ?: @gzinflate(substr($data, 2));
echo "Stream 36 length: " . strlen($decomp) . "\n";
echo "First 500 chars:\n" . substr($decomp, 0, 500) . "\n";

