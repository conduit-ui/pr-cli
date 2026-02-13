<?php

namespace App\Concerns;

use App\Config\WorkflowResolver;
use ConduitUI\Pr\DataTransferObjects\CheckRun;
use ConduitUI\Pr\DataTransferObjects\Review;
use ConduitUI\Pr\PullRequest;

trait EnforcesWorkflow
{
    /**
     * Run all workflow guard checks for a merge operation.
     *
     * @param  array{merge_strategy: string, base_branch: string, delete_branch: bool, required_reviews: int}  $workflow
     * @return array<int, array{passed: bool, label: string, detail: string}>
     */
    protected function runGuards(PullRequest $pr, array $workflow): array
    {
        $guards = [];

        // Guard 1: PR must be open
        $guards[] = [
            'passed' => $pr->data->isOpen(),
            'label' => 'PR is open',
            'detail' => $pr->data->isOpen() ? 'State: open' : 'State: ' . $pr->data->state,
        ];

        // Guard 2: Not a draft
        $guards[] = [
            'passed' => !$pr->data->isDraft(),
            'label' => 'Not a draft',
            'detail' => $pr->data->isDraft() ? 'PR is still a draft' : 'Ready for review',
        ];

        // Guard 3: Required approvals
        $requiredReviews = $workflow['required_reviews'];
        $approvals = $this->countApprovals($pr);
        $guards[] = [
            'passed' => $approvals >= $requiredReviews,
            'label' => "Required approvals ({$approvals}/{$requiredReviews})",
            'detail' => $approvals >= $requiredReviews
                ? "{$approvals} approval(s) received"
                : "Need {$requiredReviews}, have {$approvals}",
        ];

        // Guard 4: CI checks passed
        $checksResult = $this->evaluateChecks($pr);
        $guards[] = [
            'passed' => $checksResult['passed'],
            'label' => 'CI checks',
            'detail' => $checksResult['detail'],
        ];

        return $guards;
    }

    /**
     * Count the number of approved reviews on a PR.
     */
    protected function countApprovals(PullRequest $pr): int
    {
        try {
            $reviews = $pr->reviews()->get();

            // Only count the latest review per user
            $latestByUser = [];
            foreach ($reviews as $review) {
                $login = $review->user->login;
                if (!isset($latestByUser[$login]) || $review->submittedAt > $latestByUser[$login]->submittedAt) {
                    $latestByUser[$login] = $review;
                }
            }

            return count(array_filter(
                $latestByUser,
                fn (Review $review): bool => $review->isApproved()
            ));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Evaluate CI check status.
     *
     * @return array{passed: bool, detail: string}
     */
    protected function evaluateChecks(PullRequest $pr): array
    {
        try {
            $checks = $pr->checks();

            if (empty($checks)) {
                return ['passed' => true, 'detail' => 'No CI checks configured'];
            }

            $failed = array_filter($checks, fn (CheckRun $check): bool => $check->isFailed());
            $pending = array_filter($checks, fn (CheckRun $check): bool => !$check->isCompleted());
            $passed = array_filter($checks, fn (CheckRun $check): bool => $check->isSuccessful());

            if (count($failed) > 0) {
                $names = implode(', ', array_map(fn (CheckRun $c) => $c->name, $failed));
                return ['passed' => false, 'detail' => "Failed: {$names}"];
            }

            if (count($pending) > 0) {
                $names = implode(', ', array_map(fn (CheckRun $c) => $c->name, $pending));
                return ['passed' => false, 'detail' => "Pending: {$names}"];
            }

            return ['passed' => true, 'detail' => count($passed) . ' check(s) passed'];
        } catch (\Throwable) {
            return ['passed' => true, 'detail' => 'Could not fetch checks'];
        }
    }

    /**
     * Check if all guards pass.
     *
     * @param  array<int, array{passed: bool, label: string, detail: string}>  $guards
     */
    protected function allGuardsPassed(array $guards): bool
    {
        return count(array_filter($guards, fn (array $g): bool => !$g['passed'])) === 0;
    }
}
