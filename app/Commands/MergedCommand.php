<?php

namespace App\Commands;

use App\Concerns\ResolvesRepository;
use App\Output\PullRequestRenderer;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class MergedCommand extends Command
{
    use ResolvesRepository;

    protected $signature = 'merged
        {--repo= : Repository (owner/name); omit to scan all monitored repos}
        {--since=today : Show merged since (today, yesterday, or Y-m-d)}
        {--limit=50 : Maximum results per repo}';

    protected $description = 'List recently merged pull requests';

    public function handle(PrServiceInterface $service): int
    {
        $since = $this->resolveSince($this->option('since'));
        $limit = (int) $this->option('limit');

        // If --repo given, query just that repo; otherwise use monitored repos
        $repoOption = $this->option('repo');
        $repos = $repoOption
            ? [$repoOption]
            : (array) config('workflows.monitored_repos', []);

        if (empty($repos)) {
            $this->warn('No repos to scan. Use --repo or configure monitored_repos.');
            return self::SUCCESS;
        }

        $this->info("Merged PRs since {$since->format('Y-m-d')}:");
        $this->newLine();

        $renderer = new PullRequestRenderer($this->output);
        $total = 0;

        foreach ($repos as $repo) {
            try {
                $prs = $service->for($repo)
                    ->closed()
                    ->take($limit)
                    ->get();

                foreach ($prs as $pr) {
                    if (!$pr->data->isMerged()) {
                        continue;
                    }

                    if ($pr->data->mergedAt < $since) {
                        continue;
                    }

                    $renderer->renderLine($pr, $repo);
                    $total++;
                }
            } catch (\Throwable $e) {
                $this->warn("  Could not fetch from {$repo}: " . $e->getMessage());
            }
        }

        $this->newLine();

        if ($total === 0) {
            $this->line('  No merged PRs found in the given time range.');
        } else {
            $this->info("  {$total} merged PR(s) found.");
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
