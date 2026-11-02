<?php
// Meta's crawler sends a Range header and static HTML answers 206, which Meta rejects
// as an invalid Privacy Policy URL; PHP ignores Range and always answers 200.
// The canonical/og:url point here too, so Meta's app check does not follow them back to the HTML page.
header('Content-Type: text/html; charset=utf-8');
echo str_replace('https://venutaihall.com/privacy-policy"', 'https://venutaihall.com/privacy-policy.php"', file_get_contents(__DIR__ . '/privacy-policy.html'));
