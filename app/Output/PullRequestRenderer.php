<?php

namespace App\Output;

use ConduitUI\Pr\DataTransferObjects\PullRequest as PullRequestData;
use ConduitUI\Pr\PullRequest;
use Symfony\Component\Console\Output\OutputInterface;

class PullRequestRenderer
{
    public function __construct(
        protected OutputInterface $output,
    ) {}

    /**
     * Render a single PR in detail view.
     */
    public function renderDetail(PullRequest $pr): void
    {
        $data = $pr->data;

        $stateLabel = $this->stateLabel($data);

        $this->output->writeln('');
        $this->output->writeln("  <info>#{$data->number}</info> {$data->title}");
        $this->output->writeln("  State: {$stateLabel}  |  Author: <comment>{$data->user->login}</comment>");
        $this->output->writeln("  Branch: <comment>{$data->head->ref}</comment> -> <comment>{$data->base->ref}</comment>");
        $this->output->writeln("  URL: {$data->htmlUrl}");
        $this->output->writeln("  Created: {$data->createdAt->format('Y-m-d H:i')}  |  Updated: {$data->updatedAt->format('Y-m-d H:i')}");

        if ($data->additions !== null || $data->deletions !== null) {
            $additions = $data->additions ?? 0;
            $deletions = $data->deletions ?? 0;
            $files = $data->changedFiles ?? 0;
            $this->output->writeln("  Changes: <info>+{$additions}</info> <fg=red>-{$deletions}</> across {$files} file(s)");
        }

        if (! empty($data->labels)) {
            $labels = implode(', ', array_map(fn ($l) => $l->name, $data->labels));
            $this->output->writeln("  Labels: {$labels}");
        }

        if (! empty($data->assignees)) {
            $assignees = implode(', ', array_map(fn ($a) => $a->login, $data->assignees));
            $this->output->writeln("  Assignees: {$assignees}");
        }

        if (! empty($data->requestedReviewers)) {
            $reviewers = implode(', ', array_map(fn ($r) => $r->login, $data->requestedReviewers));
            $this->output->writeln("  Requested reviewers: {$reviewers}");
        }

        if ($data->body) {
            $this->output->writeln('');
            $this->output->writeln('  '.str_replace("\n", "\n  ", trim($data->body)));
        }

        $this->output->writeln('');
    }

    /**
     * Render a compact single-line entry for list views.
     */
    public function renderLine(PullRequest $pr, ?string $repoPrefix = null): void
    {
        $data = $pr->data;
        $stateLabel = $this->stateLabel($data);
        $prefix = $repoPrefix ? "<fg=cyan>{$repoPrefix}</> " : '';

        $this->output->writeln(
            "  {$prefix}<info>#{$data->number}</info> {$stateLabel} {$data->title} "
            ."<comment>({$data->user->login})</comment> "
            ."<fg=gray>{$data->updatedAt->format('Y-m-d')}</>"
        );
    }

    /**
     * Render guard check results.
     *
     * @param  array<int, array{passed: bool, label: string, detail: string}>  $guards
     */
    public function renderGuards(array $guards): void
    {
        $this->output->writeln('');
        $this->output->writeln('  <info>Guard checks:</info>');

        foreach ($guards as $guard) {
            $icon = $guard['passed'] ? '<info>[PASS]</info>' : '<fg=red>[FAIL]</>';
            $this->output->writeln("    {$icon} {$guard['label']} - {$guard['detail']}");
        }

        $this->output->writeln('');
    }

    /**
     * Render workflow configuration.
     *
     * @param  array{merge_strategy: string, base_branch: string, delete_branch: bool, required_reviews: int}  $workflow
     */
    public function renderWorkflow(string $repo, array $workflow): void
    {
        $this->output->writeln('');
        $this->output->writeln("  <info>Workflow for {$repo}:</info>");
        $this->output->writeln("    Strategy: <comment>{$workflow['merge_strategy']}</comment>");
        $this->output->writeln("    Base branch: <comment>{$workflow['base_branch']}</comment>");
        $this->output->writeln('    Delete branch: <comment>'.($workflow['delete_branch'] ? 'yes' : 'no').'</comment>');
        $this->output->writeln("    Required reviews: <comment>{$workflow['required_reviews']}</comment>");
    }

    private function stateLabel(PullRequestData $data): string
    {
        if ($data->isMerged()) {
            return '<fg=magenta>[MERGED]</>';
        }

        if ($data->isDraft()) {
            return '<fg=gray>[DRAFT]</>';
        }

        if ($data->isOpen()) {
            return '<info>[OPEN]</info>';
        }

        return '<fg=red>[CLOSED]</>';
    }
}
