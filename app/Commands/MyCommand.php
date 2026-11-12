<?php

namespace App\Commands;

use App\Output\PullRequestRenderer;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class MyCommand extends Command
{
    protected $signature = 'my
        {--user= : GitHub user (defaults to config github_user)}
        {--state=open : Filter state (open, closed, all)}';

    protected $description = 'Show your open PRs across all monitored repos';

    public function handle(PrServiceInterface $service): int
    {
        $user = $this->option('user') ?: config('workflows.github_user', 'shadetheory');
        $state = $this->option('state');

        /** @var array<int, string> $repos */
        $repos = config('workflows.monitored_repos', []);

        if (empty($repos)) {
            $this->warn('No monitored repos configured in config/workflows.php');
            return self::SUCCESS;
        }

        $this->info("PRs by {$user} (state: {$state}) across monitored repos:");
        $this->newLine();

        $renderer = new PullRequestRenderer($this->output);
        $total = 0;

        foreach ($repos as $repo) {
            try {
                $prs = $service->for($repo)
                    ->state($state)
                    ->author($user)
                    ->take(20)
                    ->get();

                foreach ($prs as $pr) {
                    $renderer->renderLine($pr, $repo);
                    $total++;
                }
            } catch (\Throwable $e) {
                $this->warn("  Could not fetch from {$repo}: " . $e->getMessage());
            }
        }

        $this->newLine();

        if ($total === 0) {
            $this->line('  No PRs found.');
        } else {
            $this->info("  {$total} PR(s) found.");
        }

        return self::SUCCESS;
    }
}
