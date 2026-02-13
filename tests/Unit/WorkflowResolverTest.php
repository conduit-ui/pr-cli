<?php

use App\Config\WorkflowResolver;

it('matches exact repo names', function () {
    expect(WorkflowResolver::matches('PSTrax/pstrax-laravel', 'PSTrax/pstrax-laravel'))->toBeTrue();
    expect(WorkflowResolver::matches('PSTrax/pstrax-laravel', 'PSTrax/other'))->toBeFalse();
});

it('matches glob patterns', function () {
    expect(WorkflowResolver::matches('conduit-ui/pr', 'conduit-ui/*'))->toBeTrue();
    expect(WorkflowResolver::matches('conduit-ui/connector', 'conduit-ui/*'))->toBeTrue();
    expect(WorkflowResolver::matches('PSTrax/pstrax-laravel', 'conduit-ui/*'))->toBeFalse();
});

it('returns defaults when no repo matches', function () {
    config()->set('workflows.defaults', [
        'merge_strategy' => 'merge',
        'base_branch' => 'main',
        'delete_branch' => true,
        'required_reviews' => 0,
    ]);
    config()->set('workflows.repos', []);

    $result = WorkflowResolver::forRepo('unknown/repo');

    expect($result)
        ->merge_strategy->toBe('merge')
        ->base_branch->toBe('main')
        ->delete_branch->toBeTrue()
        ->required_reviews->toBe(0);
});

it('merges repo-specific overrides onto defaults', function () {
    config()->set('workflows.defaults', [
        'merge_strategy' => 'merge',
        'base_branch' => 'main',
        'delete_branch' => true,
        'required_reviews' => 0,
    ]);
    config()->set('workflows.repos', [
        'PSTrax/pstrax-laravel' => [
            'merge_strategy' => 'squash',
            'base_branch' => 'development',
            'required_reviews' => 1,
        ],
    ]);

    $result = WorkflowResolver::forRepo('PSTrax/pstrax-laravel');

    expect($result)
        ->merge_strategy->toBe('squash')
        ->base_branch->toBe('development')
        ->delete_branch->toBeTrue()  // from defaults
        ->required_reviews->toBe(1);
});

it('resolves glob-matched repos', function () {
    config()->set('workflows.defaults', [
        'merge_strategy' => 'rebase',
        'base_branch' => 'develop',
        'delete_branch' => false,
        'required_reviews' => 2,
    ]);
    config()->set('workflows.repos', [
        'conduit-ui/*' => [
            'merge_strategy' => 'merge',
            'base_branch' => 'main',
        ],
    ]);

    $result = WorkflowResolver::forRepo('conduit-ui/pr');

    expect($result)
        ->merge_strategy->toBe('merge')
        ->base_branch->toBe('main')
        ->delete_branch->toBeFalse()  // from defaults
        ->required_reviews->toBe(2);  // from defaults
});
