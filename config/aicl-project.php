<?php

/*
|--------------------------------------------------------------------------
| Project-Specific AICL Configuration Overrides
|--------------------------------------------------------------------------
|
| Values here are deep-merged on top of config/aicl.php at boot time
| using array_replace_recursive(). This file is NEVER overwritten by
| skeleton upgrades — it is your project's own configuration layer.
|
| How merging works:
|   - Scalar values (strings, booleans, integers) REPLACE the base value
|   - Associative arrays are merged recursively (nested keys preserved)
|   - Indexed arrays (numeric keys) are REPLACED entirely, not appended
|
| Example: to override the brand name and add a custom AI tool:
|
|   return [
|       'theme' => [
|           'brand_name' => 'My App',
|       ],
|       'ai' => [
|           'tools' => [
|               \App\AI\Tools\MyCustomTool::class,
|           ],
|           'system_prompt' => 'You are a helpful assistant for My App.',
|       ],
|   ];
|
*/

return [
    // 'theme' => [
    //     'brand_name' => 'My App',
    // ],
    // 'ai' => [
    //     'tools' => [],
    //     'system_prompt' => '...',
    // ],
    // 'features' => [
    //     'social_login' => true,
    // ],
];
