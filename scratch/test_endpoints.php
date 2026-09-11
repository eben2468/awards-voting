<?php
$key = 'ChKWJbL_FcwPWjTcNSkmtMw0kxxadrwEMoRC-tqSCAk6LYWLoy48QpopmAkfN75E';
$recipient = '233548811774';
$message = 'Hello! This is a test message from VoteHubGH using Nalo Solutions SMS API.';
$sender_id = 'VOTEHUBGH';

$endpoints = [
    'Endpoint A (JSON/POST send-message)' => [
        'url' => 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-message/',
        'type' => 'json',
        'data' => [
            'key' => $key,
            'msisdn' => $recipient,
            'message' => $message,
            'sender_id' => $sender_id
        ]
    ],
    'Endpoint B (JSON/POST send-sms)' => [
        'url' => 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-sms/',
        'type' => 'json',
        'data' => [
            'key' => $key,
            'msisdn' => $recipient,
            'message' => $message,
            'sender_id' => $sender_id
        ]
    ],
    'Endpoint C (Form-encoded send-sms)' => [
        'url' => 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-sms/',
        'type' => 'form',
        'data' => [
            'key' => $key,
            'msisdn' => $recipient,
            'message' => $message,
            'sender_id' => $sender_id
        ]
    ],
    'Endpoint D (Form-encoded send-message)' => [
        'url' => 'https://sms.nalosolutions.com/smsbackend/Resl_Nalo/send-message/',
        'type' => 'form',
        'data' => [
            'key' => $key,
            'msisdn' => $recipient,
            'message' => $message,
            'sender_id' => $sender_id
        ]
    ]
];

foreach ($endpoints as $name => $cfg) {
    echo "=== Testing $name ===\n";
    $ch = curl_init($cfg['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    if ($cfg['type'] === 'json') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cfg['data']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($cfg['data']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($err) {
        echo "Error: $err\n";
    } else {
        echo "Response: $response\n";
    }
    echo "\n";
}
