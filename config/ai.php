<?php

return [
    'model'               => env('AI_MODEL', 'claude-sonnet-4-6'),
    'model_mini'          => env('AI_MODEL_MINI', 'claude-haiku-4-5-20251001'),
    'max_tokens'          => (int) env('AI_MAX_TOKENS', 4096),
    'temperature'         => (float) env('AI_TEMPERATURE', 0.7),
    'anthropic_key'       => env('ANTHROPIC_API_KEY'),
    'openai_key'          => env('OPENAI_API_KEY'),
    'embedding_model'     => 'text-embedding-3-small',
    'embedding_dimensions' => 1536,
    'chunk_size'          => 2000,
    'chunk_overlap'       => 400,
    'kb_top_k'            => 5,
];
