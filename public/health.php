<?php
// Dedicated health check endpoint for cloud platforms (Railway, Render, Kubernetes)
header('Content-Type: application/json');
http_response_code(200);

echo json_encode([
    'status' => 'ok',
    'timestamp' => time(),
    'service' => 'flavour-pos'
]);
exit;
