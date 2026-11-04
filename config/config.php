<?php
// ReliefLink configuration. Edit for your environment.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'relieflink';
const DB_USER = 'root';          // use a dedicated MySQL user in production
const DB_PASS = '';              // XAMPP default is empty

const APP_NAME = 'ReliefLink';
const EOC_PHONE = '+880-1990-000999';

// While true, the password-reset link is shown on screen instead of emailed.
// Set to false in production (then configure mail()/SMTP).
const DEV_SHOW_RESET_LINK = true;

// Shown on the donate page. Replace with real account details.
const DONATION_ACCOUNTS = [
    'bkash'  => '01700-000000 (Personal)',
    'nagad'  => '01800-000000 (Personal)',
    'rocket' => '01900-0000000',
    'card'   => 'Pay to merchant ID RELIEFLINK via any card',
    'bank'   => 'A/C 1234567890, Bangladesh Relief Fund, Dhaka Main Branch',
];
