<?php
$f = file_get_contents('scratch_live.pdf');
$p = strpos($f, '4 0 obj');
$sp = strpos($f, 'stream', $p) + 7; // after \r\n or \n
$ep = strpos($f, 'endstream', $sp);
$data = substr($f, $sp, $ep - $sp);
echo "Length: " . strlen($data) . "\n";
echo "Decompressed:\n";
echo @gzuncompress($data) ?: @gzinflate(substr($data, 2));
echo "\n";

