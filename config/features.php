<?php

return [
    'marketplace_enabled' => (bool) env('FEATURE_MARKETPLACE_ENABLED', true),
    'chat_enabled' => (bool) env('FEATURE_CHAT_ENABLED', false),
];
