<?php
$url = 'http://localhost/OC-GST-invoice/api/app.php?action=profile&company_id=1';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
echo "PROFILE API RESPONSE:\n" . $res . "\n";
unlink(__FILE__);
