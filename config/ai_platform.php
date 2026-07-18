<?php

return [
    'public_chat' => [
        'enabled' => env('AI_PUBLIC_CHAT_ENABLED', false),
        'anonymous_enabled' => env('AI_PUBLIC_CHAT_ANONYMOUS_ENABLED', false),
        'hybrid_retrieval_enabled' => env('AI_PUBLIC_CHAT_HYBRID_RETRIEVAL', false),
        'streaming_enabled' => env('AI_PUBLIC_CHAT_STREAMING', false),
        'emergency_stop' => env('AI_PUBLIC_CHAT_EMERGENCY_STOP', false),
        'retention_days' => (int) env('AI_PUBLIC_CHAT_RETENTION_DAYS', 30),
    ],
    'embeddings' => [
        'provider' => env('AI_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'key' => env('OPENAI_API_KEY'),
    ],
    'knowledge' => [
        'auto_index' => env('AI_KNOWLEDGE_AUTO_INDEX', false),
        'chunk_characters' => (int) env('AI_KNOWLEDGE_CHUNK_CHARACTERS', 1800),
        'chunk_overlap_characters' => (int) env('AI_KNOWLEDGE_CHUNK_OVERLAP_CHARACTERS', 200),
    ],
    'editorial' => [
        'dossiers_enabled' => env('AI_EDITORIAL_DOSSIERS', false),
        'evidence_writer_enabled' => env('AI_EDITORIAL_EVIDENCE_WRITER', false),
    ],
    'operator' => [
        'enabled' => env('AI_OPERATOR_ASSISTANT', false),
        'agent_enabled' => env('AI_OPERATOR_AGENT_ENABLED', false),
        'mutations_enabled' => env('AI_OPERATOR_MUTATIONS', false),
        'limited_auto_enabled' => env('AI_OPERATOR_LIMITED_AUTO', false),
        'step_limit' => (int) env('AI_OPERATOR_STEP_LIMIT', 12),
        'task_timeout_seconds' => (int) env('AI_OPERATOR_TASK_TIMEOUT', 300),
        'max_cost' => (float) env('AI_OPERATOR_MAX_TASK_COST', 1.00),
    ],
];
