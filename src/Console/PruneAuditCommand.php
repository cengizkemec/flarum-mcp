<?php

/*
 * This file is part of Flarum MCP.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace CengizKemec\FlarumMcp\Console;

use Carbon\Carbon;
use CengizKemec\FlarumMcp\Mcp\ApprovalService;
use CengizKemec\FlarumMcp\Model\McpAuditLog;
use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Symfony\Component\Console\Input\InputOption;

class PruneAuditCommand extends AbstractCommand
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly ApprovalService $approvals
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('mcp:prune')
            ->setDescription('Prune old Flarum MCP audit records and expire pending approval requests.')
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Override the configured audit retention in days.');
    }

    protected function fire(): int
    {
        $days = $this->input->getOption('days');

        if ($days !== null && $days !== false) {
            $days = max(1, min(3650, (int) $days));
        } else {
            $days = max(1, (int) $this->settings->get('cengizkemec-mcp.audit_retention_days', 90));
        }

        $expired = $this->approvals->expirePending();
        $deleted = McpAuditLog::query()
            ->where('created_at', '<', Carbon::now()->subDays($days))
            ->delete();

        $this->info("Expired approvals: $expired");
        $this->info("Deleted audit records older than $days days: $deleted");

        return 0;
    }
}
