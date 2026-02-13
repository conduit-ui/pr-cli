<?php

namespace App\Commands;

use App\Concerns\EnforcesWorkflow;
use App\Concerns\ResolvesRepository;
use App\Config\WorkflowResolver;
use App\Output\PullRequestRenderer;
use ConduitUI\Pr\Contracts\PrServiceInterface;
use LaravelZero\Framework\Commands\Command;

class MergeCommand extends Command
{
    use EnforcesWorkflow;
    use ResolvesRepository;

    protected $signature = 'merge
        {number : PR number to merge}
        {--repo= : Repository (owner/name)}
        {--strategy= : Override merge strategy (merge, squash, rebase)}
        {--force : Skip workflow guard checks}
        {--dry-run : Show what would happen without merging}';

    protected $description = 'Merge a pull request with workflow guards';

    public function handle(PrServiceInterface $service): int
    {
        $repo = $this->resolveRepositoryOrFail();
        $number = (int) $this->argument('number');

        // 1. Fetch PR
        try {
            $pr = $service->find($repo, $number);
        } catch (\Throwable $e) {
            $this->error("Failed to fetch PR #{$number}: " . $e->getMessage());
            return self::FAILURE;
        }

        // 2. Resolve workflow
        $workflow = WorkflowResolver::forRepo($repo);
        $strategy = $this->option('strategy') ?: $workflow['merge_strategy'];

        $renderer = new PullRequestRenderer($this->output);

        // Display PR info
        $renderer->renderDetail($pr);

        // Display workflow config
        $renderer->renderWorkflow($repo, $workflow);

        // 3. Run guards (unless --force)
        if (!$this->option('force')) {
            $guards = $this->runGuards($pr, $workflow);
            $renderer->renderGuards($guards);

            if (!$this->allGuardsPassed($guards)) {
                $this->error('Merge blocked by workflow guards. Use --force to override.');
                return self::FAILURE;
            }
        } else {
            $this->warn('  Workflow guards skipped (--force)');
            $this->newLine();
        }

        // 4. Dry run check
        if ($this->option('dry-run')) {
            $this->info("  [DRY RUN] Would merge PR #{$number} using strategy: {$strategy}");
            return self::SUCCESS;
        }

        // 5. Confirm with user
        if (!$this->confirm("Merge PR #{$number} using {$strategy} strategy?")) {
            $this->line('  Merge cancelled.');
            return self::SUCCESS;
        }

        // 6. Execute merge
        try {
            $merged = $service->merge($repo, $number, null, $strategy);

            if ($merged) {
                $this->info("  PR #{$number} merged successfully using {$strategy} strategy.");
            } else {
                $this->error("  Merge returned false. The PR may not be mergeable.");
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error("  Merge failed: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
