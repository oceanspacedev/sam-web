<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CleanupUserOrganizationalPivots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:cleanup-pivots {--dry-run : Run in dry-run mode without making changes} {--user-id= : Clean up specific user ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up organizational pivot tables based on users\' role scope levels';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        $this->info('Starting organizational pivot cleanup...');
        if ($isDryRun) {
            $this->warn('Running in DRY-RUN mode - no changes will be made');
        }

        $query = User::with('role');

        if ($userId) {
            $query->where('id', $userId);
            $this->info("Processing only user ID: {$userId}");
        }

        $users = $query->get();
        $totalUsers = $users->count();

        $this->info("Found {$totalUsers} user(s) to process");

        $stats = [
            'all' => 0,
            'badanusaha' => 0,
            'divisi' => 0,
            'region' => 0,
            'cluster' => 0,
            'no_role' => 0,
        ];

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        foreach ($users as $user) {
            if (! $user->role) {
                $stats['no_role']++;
                $this->newLine();
                $this->warn("User {$user->id} ({$user->nama_lengkap}) has no role - skipping");
                $bar->advance();

                continue;
            }

            $scopeLevel = $user->role->organizational_scope_level ?? 'cluster';
            $stats[$scopeLevel]++;

            if (! $isDryRun) {
                $this->cleanupForUser($user, $scopeLevel);
            } else {
                $this->simulateCleanup($user, $scopeLevel);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display statistics
        $this->table(
            ['Scope Level', 'User Count'],
            collect($stats)->map(fn ($count, $level) => [$level, $count])->values()
        );

        if ($isDryRun) {
            $this->info('DRY-RUN completed. Run without --dry-run to apply changes.');
        } else {
            $this->info('Cleanup completed successfully!');
        }

        return Command::SUCCESS;
    }

    /**
     * Perform actual cleanup for a user
     */
    protected function cleanupForUser(User $user, string $scopeLevel): void
    {
        switch ($scopeLevel) {
            case 'all':
                $user->badanUsahas()->detach();
                $user->divisis()->detach();
                $user->regions()->detach();
                $user->clusters()->detach();
                break;

            case 'badanusaha':
                $user->divisis()->detach();
                $user->regions()->detach();
                $user->clusters()->detach();
                break;

            case 'divisi':
                $user->regions()->detach();
                $user->clusters()->detach();
                break;

            case 'region':
                $user->clusters()->detach();
                break;

            case 'cluster':
                // No cleanup needed
                break;
        }
    }

    /**
     * Simulate cleanup and show what would be detached
     */
    protected function simulateCleanup(User $user, string $scopeLevel): void
    {
        $counts = [
            'badanusaha' => $user->badanUsahas()->count(),
            'divisi' => $user->divisis()->count(),
            'region' => $user->regions()->count(),
            'cluster' => $user->clusters()->count(),
        ];

        $toDetach = [];

        switch ($scopeLevel) {
            case 'all':
                $toDetach = ['badanusaha', 'divisi', 'region', 'cluster'];
                break;
            case 'badanusaha':
                $toDetach = ['divisi', 'region', 'cluster'];
                break;
            case 'divisi':
                $toDetach = ['region', 'cluster'];
                break;
            case 'region':
                $toDetach = ['cluster'];
                break;
        }

        if (! empty($toDetach)) {
            $detachInfo = collect($toDetach)
                ->filter(fn ($level) => $counts[$level] > 0)
                ->map(fn ($level) => "{$level}: {$counts[$level]}")
                ->join(', ');

            if ($detachInfo) {
                $this->newLine();
                $this->line("  User {$user->id} ({$user->nama_lengkap}) - Scope: {$scopeLevel} - Would detach: {$detachInfo}");
            }
        }
    }
}
