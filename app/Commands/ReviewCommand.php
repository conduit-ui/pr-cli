<?php

namespace App\Commands;

use App\Concerns\ResolvesRepository;
use App\Output\PullRequestRenderer;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class ReviewCommand extends Command
{
    use ResolvesRepository;

    protected $signature = 'review
        {number : PR number}
        {--repo= : Repository (owner/name)}
        {--approve : Approve the PR}
        {--request-changes : Request changes}
        {--comment= : Leave a comment/body with the review}';

    protected $description = 'Submit a review on a pull request';

    public function handle(PrServiceInterface $service): int
    {
        $repo = $this->resolveRepositoryOrFail();
        $number = (int) $this->argument('number');

        try {
            $pr = $service->find($repo, $number);
        } catch (\Throwable $e) {
            $this->error("Failed to fetch PR #{$number}: " . $e->getMessage());
            return self::FAILURE;
        }

        $renderer = new PullRequestRenderer($this->output);
        $renderer->renderDetail($pr);

        $body = $this->option('comment');

        if ($this->option('approve')) {
            $event = 'APPROVE';
        } elseif ($this->option('request-changes')) {
            $event = 'REQUEST_CHANGES';
            if (!$body) {
                $body = $this->ask('Review body (required for requesting changes)');
                if (!$body) {
                    $this->error('A comment body is required when requesting changes.');
                    return self::FAILURE;
                }
            }
        } else {
            $event = 'COMMENT';
            if (!$body) {
                $body = $this->ask('Review comment');
                if (!$body) {
                    $this->error('A comment body is required for a comment review.');
                    return self::FAILURE;
                }
            }
        }

        if (!$this->confirm("Submit {$event} review on PR #{$number}?")) {
            $this->line('  Review cancelled.');
            return self::SUCCESS;
        }

        try {
            $pr->submitReview($event, $body);
            $this->info("  Review ({$event}) submitted on PR #{$number}.");
        } catch (\Throwable $e) {
            $this->error("  Failed to submit review: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
