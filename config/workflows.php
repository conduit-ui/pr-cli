<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub User
    |--------------------------------------------------------------------------
    |
    | Your GitHub username, used for filtering "my" PRs.
    |
    */

    'github_user' => env('GITHUB_USER', 'shadetheory'),

    /*
    |--------------------------------------------------------------------------
    | Default Workflow Settings
    |--------------------------------------------------------------------------
    |
    | Fallback merge strategy and branch settings when no repo-specific
    | config matches.
    |
    */

    'defaults' => [
        'merge_strategy' => 'merge',
        'base_branch' => 'main',
        'delete_branch' => true,
        'required_reviews' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Repository Workflow Overrides
    |--------------------------------------------------------------------------
    |
    | Keys support glob patterns (e.g., "conduit-ui/*").
    | Values override defaults for matching repos.
    |
    */

    'repos' => [
        'PSTrax/pstrax-laravel' => [
            'merge_strategy' => 'squash',
            'base_branch' => 'development',
            'required_reviews' => 1,
        ],
        'conduit-ui/*' => [
            'merge_strategy' => 'merge',
            'base_branch' => 'main',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitored Repositories
    |--------------------------------------------------------------------------
    |
    | Repos to scan when running cross-repo commands like `pr my` or
    | `pr reviews`.
    |
    */

    'monitored_repos' => [
        'PSTrax/pstrax-laravel',
        'conduit-ui/connector',
        'conduit-ui/pr',
        'conduit-ui/knowledge',
        'shadetheory/agentctl',
        'shadetheory/triage-agent',
    ],

];
