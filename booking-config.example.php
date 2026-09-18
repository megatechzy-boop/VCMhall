<?php
// Copy to booking-config.php on the PHP host. Never commit the filled file.
return [
    'mysql' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'CPANEL_USER_vcmhall',
        'user' => 'CPANEL_USER_vcmhall',
        'password' => 'SET_ON_SERVER',
    ],
    'admin_password_hash' => 'PASTE_PASSWORD_HASH_HERE',
    'email_to' => 'bookings@venutaihall.com',
    'email_from' => 'bookings@venutaihall.com',
];
