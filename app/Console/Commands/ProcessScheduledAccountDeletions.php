<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmailService;
use Illuminate\Support\Facades\Log;

class ProcessScheduledAccountDeletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:process-deletions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled account deletions after 24-hour waiting period';

    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing scheduled account deletions...');
        
        try {
            $usersToDelete = User::where('deletion_scheduled_at', '<=', now())
                ->whereNotNull('deletion_scheduled_at')
                ->get();
            
            if ($usersToDelete->isEmpty()) {
                $this->info('No accounts scheduled for deletion at this time.');
                return 0;
            }
            
            $this->info("Found {$usersToDelete->count()} accounts scheduled for deletion.");
            
            foreach ($usersToDelete as $user) {
                $this->info("Processing deletion for user: {$user->email} (ID: {$user->id})");
                
                Log::channel('auth')->info('Executing scheduled account deletion', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'scheduled_at' => $user->deletion_scheduled_at,
                    'reason' => $user->deletion_reason
                ]);
                
                // Send final confirmation email
                try {
                    $this->emailService->sendAccountDeletionConfirmationEmail(
                        $user,
                        now()->format('F j, Y \a\t g:i A T'),
                        false,
                        []
                    );
                    $this->info("Final confirmation email sent to {$user->email}");
                } catch (\Exception $e) {
                    $this->error("Failed to send final deletion confirmation email to {$user->email}: {$e->getMessage()}");
                    Log::channel('auth')->error('Failed to send final deletion confirmation email', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Revoke all tokens
                $tokenCount = $user->tokens()->count();
                $user->tokens()->delete();
                $this->info("Revoked {$tokenCount} access tokens for {$user->email}");
                
                // Delete the user
                $user->delete();
                
                Log::channel('auth')->info('Scheduled account deletion completed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'deletion_reason' => $user->deletion_reason
                ]);
                
                $this->info("✅ Account deleted successfully: {$user->email}");
            }
            
            $this->info("✅ Processed {$usersToDelete->count()} scheduled account deletions successfully.");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to process scheduled deletions: {$e->getMessage()}");
            Log::channel('auth')->error('Failed to process scheduled deletions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return 1;
        }
    }
}