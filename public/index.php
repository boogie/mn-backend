<?php
// This file serves as the entry point if not using React frontend
// If hosting React frontend separately, this can be removed

header('Content-Type: application/json');
echo json_encode([
    'name' => 'Magician News API',
    'version' => '1.0.0',
    'status' => 'running',
    'endpoints' => [
        'POST /api/auth?action=register' => 'Register new user',
        'POST /api/auth?action=login' => 'Login user',
        'GET /api/auth' => 'Get current user',
        'GET /api/subscription?action=status' => 'Check subscription status',
        'POST /api/subscription?action=checkout' => 'Create checkout session',
        'GET /api/content' => 'Get articles (requires subscription)',
        'GET /api/content?id={id}' => 'Get single article',
        'POST /api/webhook' => 'Stripe webhook handler',
    ]
]);
