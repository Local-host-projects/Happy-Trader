<?php

return [
    'api_key'  => env('ANTHROPIC_API_KEY'),
    'endpoint' => 'https://api.anthropic.com/v1/messages',
    'model'    => env('AI_MODEL', 'claude-sonnet-4-6'),
    'version'  => '2023-06-01',
];
