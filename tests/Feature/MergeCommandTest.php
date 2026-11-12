<?php

use ConduitUI\Pr\Contracts\PrServiceInterface;
use ConduitUI\Pr\DataTransferObjects\Base;
use ConduitUI\Pr\DataTransferObjects\Head;
use ConduitUI\Pr\DataTransferObjects\PullRequest as PullRequestData;
use ConduitUI\Pr\DataTransferObjects\Repository;
use ConduitUI\Pr\DataTransferObjects\User;
use ConduitUI\Pr\PullRequest;

function makeFakePr(int $number = 42, string $state = 'open', bool $draft = false): PullRequest
{
    $user = new User(1, 'testuser', 'https://avatar.url', 'https://github.com/testuser', 'User');
    $repo = new Repository(1, 'test-repo', 'owner/test-repo', 'https://github.com/owner/test-repo', false);

    $data = new PullRequestData(
        number: $number,
        title: 'Test PR',
        body: 'Test body',
        state: $state,
        user: $user,
        htmlUrl: "https://github.com/owner/test-repo/pull/{$number}",
        createdAt: new DateTimeImmutable('2025-01-01'),
        updatedAt: new DateTimeImmutable('2025-01-02'),
        closedAt: null,
        mergedAt: null,
        mergeCommitSha: null,
        draft: $draft,
        additions: 10,
        deletions: 5,
        changedFiles: 3,
        assignee: null,
        assignees: [],
        requestedReviewers: [],
        labels: [],
        head: new Head('feature-branch', 'abc123', $user, $repo),
        base: new Base('main', 'def456', $user, $repo),
    );

    $connector = Mockery::mock(\ConduitUi\GitHubConnector\Connector::class);

    return new PullRequest($connector, 'owner', 'test-repo', $data);
}

it('shows dry run output without merging', function () {
    $pr = makeFakePr();

    $service = Mockery::mock(PrServiceInterface::class);
    $service->shouldReceive('find')
        ->with('owner/test-repo', 42)
        ->andReturn($pr);

    $this->app->instance(PrServiceInterface::class, $service);

    $this->artisan('merge', [
        'number' => 42,
        '--repo' => 'owner/test-repo',
        '--dry-run' => true,
        '--force' => true,
    ])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();
});

it('blocks merge when PR is a draft', function () {
    $pr = makeFakePr(draft: true);

    $service = Mockery::mock(PrServiceInterface::class);
    $service->shouldReceive('find')
        ->with('owner/test-repo', 42)
        ->andReturn($pr);

    $this->app->instance(PrServiceInterface::class, $service);

    $this->artisan('merge', [
        'number' => 42,
        '--repo' => 'owner/test-repo',
    ])
        ->expectsOutputToContain('FAIL')
        ->assertFailed();
});
