<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provider-Neutral Feature Routing
    |--------------------------------------------------------------------------
    |
    | Feature code should call AiGatewayService with a feature key and stay
    | unaware of the provider behind that request. These profiles are defaults;
    | Dev/Admin settings can override provider, model, and fallback order per
    | feature without changing controllers, jobs, or services.
    |
    */

    'routes' => [
        'ask_life' => [
            'label' => 'Ask Life public assistant',
            'profile' => 'fast_balanced',
            'required_capabilities' => ['structured_json'],
            'notes' => 'High-volume chat. Prefer a cheap, fast, source-grounded model before premium fallback.',
        ],
        'listing_description' => [
            'label' => 'Listing description helper',
            'profile' => 'cheap_structured',
            'notes' => 'Business-owner copy. Cheap structured providers are usually enough.',
        ],
        'event_description' => [
            'label' => 'Event description helper',
            'profile' => 'cheap_structured',
            'notes' => 'Short factual event copy from supplied fields only.',
        ],
        'voucher_copy' => [
            'label' => 'Voucher copy helper',
            'profile' => 'cheap_structured',
            'notes' => 'Short offer copy with strict missing-field behavior.',
        ],
        'ad_copy' => [
            'label' => 'Advert copy helper',
            'profile' => 'balanced',
            'notes' => 'Commercial copy that benefits from stronger tone control.',
        ],
        'push_copy' => [
            'label' => 'Push copy helper',
            'profile' => 'cheap_structured',
            'notes' => 'Small, bounded copy variants.',
        ],
        'article_seo' => [
            'label' => 'Article SEO helper',
            'profile' => 'cheap_structured',
            'notes' => 'Low-risk metadata from supplied article fields.',
        ],
        'article_translation' => [
            'label' => 'Article translation helper',
            'profile' => 'balanced',
            'notes' => 'Use dedicated translation providers first when available; LLM fallback stays provider-neutral.',
        ],
        'fault_category' => [
            'label' => 'Fault category triage',
            'profile' => 'cheap_structured',
            'notes' => 'Fast classification with local keyword fallback.',
        ],
        'content_review' => [
            'label' => 'Content review',
            'profile' => 'balanced',
            'notes' => 'Moderation support for trust and quality checks.',
        ],
        'editorial_brief' => [
            'label' => 'Editorial brief scorer',
            'profile' => 'balanced',
            'notes' => 'Source-aware editorial judgement before humans approve an article brief.',
        ],
        'jimmy_article_draft' => [
            'label' => 'Jimmy article draft writer',
            'profile' => 'premium',
            'notes' => 'Lower-volume, higher-value writing. Route to the best writing provider you can afford.',
        ],
        'dev_operator_agent' => [
            'label' => 'Developer Operator Assistant',
            'profile' => 'premium',
            'required_capabilities' => ['structured_json'],
            'notes' => 'Low-volume tool planning for audited developer tasks. The server remains responsible for authorization and execution.',
        ],
        'web_search' => [
            'label' => 'Operator public web search',
            'profile' => 'balanced',
            'provider' => 'perplexity',
            'required_capabilities' => ['structured_json', 'web_grounded'],
            'notes' => 'Current public-web discovery. Selected results are fetched and retained separately before use as evidence.',
        ],
        'settings_test' => [
            'label' => 'Provider health check',
            'profile' => 'cheap_structured',
            'notes' => 'One small structured response to prove the route works.',
        ],
    ],

    'profiles' => [
        'cheap_structured' => [
            'label' => 'Cheap structured',
            'description' => 'Classification, short copy, metadata, and other bounded JSON tasks.',
        ],
        'fast_balanced' => [
            'label' => 'Fast balanced',
            'description' => 'High-volume assistant traffic where speed and cost matter.',
        ],
        'balanced' => [
            'label' => 'Balanced',
            'description' => 'Medium-value work that needs better judgement but not flagship pricing.',
        ],
        'premium' => [
            'label' => 'Premium',
            'description' => 'Low-volume, high-value writing, review, or reasoning work.',
        ],
    ],
];
