<?php

return [
    'api_key'  => env('OPENROUTER_API_KEY'),
    'endpoint' => 'https://openrouter.ai/api/v1/chat/completions',
    'model'    => env('AI_MODEL', 'deepseek/deepseek-v4-flash:free'),
];