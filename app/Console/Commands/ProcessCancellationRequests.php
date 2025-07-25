<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessCancellationRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancellations:process-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscription cancellation requests and handle 3-day waiting period';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing cancellation requests...');
        
        // Process users with confirmed cancellations that are ready to be executed
        $this->processReadyCancellations();
        
        // Send reminders for pending cancellations
        $this->sendCancellationReminders();
        
        $this->info('Cancellation processing completed.');
        
        return Command::SUCCESS;
    }
    
    /**
     * Process cancellations that have reached their effective date
     */
    private function processReadyCancellations()
    {
        $readyCancellations = User::where('cancellation_status', 'confirmed')
            ->where('cancellation_effective_at', '<=', now())
            ->get();
            
        foreach ($readyCancellations as $user) {
            $this->processCancellation($user);
        }
        
        $this->info("Processed {$readyCancellations->count()} ready cancellations.");
    }
    
    /**
     * Process individual user cancellation
     */
    private function processCancellation(User $user)
    {
        try {
            // Update user status to cancelled/expired
            $user->update([
                'payment_status' => 'expired',
                'cancellation_status' => 'processed',
                'subscription_ends_at' => now(),
                'auto_renew' => false
            ]);
            
            Log::info('Subscription cancelled and processed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'cancelled_at' => now(),
                'reason' => $user->cancellation_reason
            ]);
            
            $this->info("Cancelled subscription for user: {$user->email}");
            
        } catch (\Exception $e) {
            Log::error('Failed to process cancellation', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            $this->error("Failed to cancel subscription for user: {$user->email}");
        }
    }
    
    /**
     * Send reminders for pending cancellations
     */
    private function sendCancellationReminders()
    {
        $pendingCancellations = User::where('cancellation_status', 'confirmed')
            ->where('cancellation_effective_at', '>', now())
            ->get();
            
        foreach ($pendingCancellations as $user) {
            $daysRemaining = now()->diffInDays($user->cancellation_effective_at);
            
            if ($daysRemaining <= 1) {
                Log::info('Cancellation reminder - 1 day remaining', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'effective_date' => $user->cancellation_effective_at
                ]);
            }
        }
        
        $this->info("Checked {$pendingCancellations->count()} pending cancellations for reminders.");
    }
}
