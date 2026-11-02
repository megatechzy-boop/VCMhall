<?php
// Meta's crawler sends a Range header and static HTML answers 206, which Meta rejects
// as an invalid Privacy Policy URL; PHP ignores Range and always answers 200.
header('Content-Type: text/html; charset=utf-8');
readfile(__DIR__ . '/privacy-policy.html');
