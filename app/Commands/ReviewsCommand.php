<?php

namespace App\Commands;

use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class ReviewsCommand extends Command
{
    protected $signature = 'reviews
        {--since=today : Show reviews since (today, yesterday, or Y-m-d)}
        {--user= : GitHub user to check (defaults to config github_user)}';

    protected $description = 'Show who reviewed your PRs';

    public function handle(PrServiceInterface $service): int
    {
        $user = $this->option('user') ?: config('workflows.github_user', 'shadetheory');
        $since = $this->resolveSince($this->option('since'));

        /** @var array<int, string> $repos */
        $repos = config('workflows.monitored_repos', []);

        if (empty($repos)) {
            $this->warn('No monitored repos configured in config/workflows.php');

            return self::SUCCESS;
        }

        $this->info("Reviews on {$user}'s PRs since {$since->format('Y-m-d')}:");
        $this->newLine();

        $totalReviews = 0;

        foreach ($repos as $repo) {
            try {
                $prs = $service->for($repo)
                    ->state('all')
                    ->author($user)
                    ->take(50)
                    ->get();

                foreach ($prs as $pr) {
                    // Only consider PRs updated since the since date
                    if ($pr->data->updatedAt < $since) {
                        continue;
                    }

                    try {
                        $reviews = $pr->reviews()->get();
                    } catch (\Throwable) {
                        continue;
                    }

                    foreach ($reviews as $review) {
                        if ($review->submittedAt < $since) {
                            continue;
                        }

                        $stateLabel = match ($review->state) {
                            'APPROVED' => '<info>APPROVED</info>',
                            'CHANGES_REQUESTED' => '<fg=red>CHANGES_REQUESTED</>',
                            'COMMENTED' => '<comment>COMMENTED</comment>',
                            default => $review->state,
                        };

                        $this->line(
                            "  <fg=cyan>{$repo}</> <info>#{$pr->data->number}</info> "
                            ."<comment>{$review->user->login}</comment> {$stateLabel} "
                            ."<fg=gray>{$review->submittedAt->format('Y-m-d H:i')}</>"
                        );

                        $totalReviews++;
                    }
                }
            } catch (\Throwable $e) {
                $this->warn("  Could not fetch from {$repo}: ".$e->getMessage());
            }
        }

        if ($totalReviews === 0) {
            $this->line('  No reviews found in the given time range.');
        } else {
            $this->newLine();
            $this->info("  {$totalReviews} review(s) found.");
        }

        return self::SUCCESS;
    }

    private function resolveSince(string $value): \DateTimeImmutable
    {
        return match ($value) {
            'today' => new \DateTimeImmutable('today'),
            'yesterday' => new \DateTimeImmutable('yesterday'),
            default => new \DateTimeImmutable($value),
        };
    }
}
