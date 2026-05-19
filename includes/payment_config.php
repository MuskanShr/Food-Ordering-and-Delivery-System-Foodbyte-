<?php
define('ESEWA_MODE', 'sandbox');

// Merchant credentials
define('ESEWA_PRODUCT_CODE', 'EPAYTEST');                 // sandbox
define('ESEWA_SECRET_KEY',   '8gBm/:&EnhH.1/q');          // sandbox

// Endpoints
$_esewaEndpoints = [
    'sandbox' => [
        'form'   => 'https://rc-epay.esewa.com.np/api/epay/main/v2/form',
        'status' => 'https://rc-epay.esewa.com.np/api/epay/transaction/status/',
    ],
    'production' => [
        'form'   => 'https://epay.esewa.com.np/api/epay/main/v2/form',
        'status' => 'https://epay.esewa.com.np/api/epay/transaction/status/',
    ],
];
define('ESEWA_FORM_URL',   $_esewaEndpoints[ESEWA_MODE]['form']);
define('ESEWA_STATUS_URL', $_esewaEndpoints[ESEWA_MODE]['status']);

// Base URL of THIS site, used to build success/failure callback URLs.
// Adjust if your deployment uses a different host or path.
define('SITE_BASE_URL', 'http://localhost/foodbyte');


/**
 * Build the HMAC-SHA256 signature eSewa expects.
 * Matches the format `field1=value1,field2=value2,...` and base64-encodes the digest.
 */
function esewaSign(array $fields, string $signedFieldNames, string $secret): string
{
    $names   = array_map('trim', explode(',', $signedFieldNames));
    $parts   = [];
    foreach ($names as $n) {
        $parts[] = $n . '=' . ($fields[$n] ?? '');
    }
    $message = implode(',', $parts);
    return base64_encode(hash_hmac('sha256', $message, $secret, true));
}

/**
 * Format a number for eSewa: 2dp, no thousands separators.
 */
function esewaAmount(float $n): string
{
    return number_format($n, 2, '.', '');
}
