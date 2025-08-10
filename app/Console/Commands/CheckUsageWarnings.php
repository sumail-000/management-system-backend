<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\EmailService;
use App\Services\UsageTrackingService;
use Illuminate\Support\Facades\Log;

class CheckUsageWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'usage:check-warnings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user usage and send warning emails when users reach 80% of their plan limits';

    protected $emailService;
    protected $usageService;

    /**
     * Create a new command instance.
     */
    public function __construct(EmailService $emailService, UsageTrackingService $usageService)
    {
        parent::__construct();
        $this->emailService = $emailService;
        $this->usageService = $usageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting usage warning check...');
        
        $warningsSent = 0;
        $usersChecked = 0;
        
        // Get all users with membership plans
        User::whereNotNull('membership_plan_id')
            ->where('payment_status', '!=', 'expired')
            ->chunk(100, function ($users) use (&$warningsSent, &$usersChecked) {
                foreach ($users as $user) {
                    $usersChecked++;
                    
                    try {
                        // Get user's current usage percentages
                        $percentages = $this->usageService->getUsagePercentages($user);
                        $usage = $this->usageService->getCurrentUsage($user);
                        
                        // Check products usage
                        if (($percentages['products'] ?? 0) >= 80 && !$usage['products']['unlimited']) {
                            // Check if we haven't sent a warning in the last 7 days
                            $lastWarning = $user->last_usage_warning_sent_at;
                            if (!$lastWarning || $lastWarning->diffInDays(now()) >= 7) {
                                $sent = $this->emailService->sendUsageWarningEmail(
                                    $user,
                                    (int)$percentages['products'],
                                    'products',
                                    $usage['products']['current_month'],
                                    $usage['products']['limit']
                                );
                                
                                if ($sent) {
                                    $warningsSent++;
                                    // Update last warning sent timestamp
                                    $user->update(['last_usage_warning_sent_at' => now()]);
                                    
                                    Log::info('Usage warning email sent', [
                                        'user_id' => $user->id,
                                        'email' => $user->email,
                                        'usage_percentage' => $percentages['products'],
                                        'usage_type' => 'products'
                                    ]);
                                }
                            }
                        }
                        
                        // Check labels usage
                        if (($percentages['labels'] ?? 0) >= 80 && !$usage['labels']['unlimited']) {
                            // Check if we haven't sent a warning in the last 7 days
                            $lastWarning = $user->last_usage_warning_sent_at;
                            if (!$lastWarning || $lastWarning->diffInDays(now()) >= 7) {
                                $sent = $this->emailService->sendUsageWarningEmail(
                                    $user,
                                    (int)$percentages['labels'],
                                    'labels',
                                    $usage['labels']['current_month'],
                                    $usage['labels']['limit']
                                );
                                
                                if ($sent) {
                                    $warningsSent++;
                                    // Update last warning sent timestamp
                                    $user->update(['last_usage_warning_sent_at' => now()]);
                                    
                                    Log::info('Usage warning email sent', [
                                        'user_id' => $user->id,
                                        'email' => $user->email,
                                        'usage_percentage' => $percentages['labels'],
                                        'usage_type' => 'labels'
                                    ]);
                                }
                            }
                        }
                        
                    } catch (\Exception $e) {
                        Log::error('Failed to check usage for user', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                        
                        $this->error("Failed to check usage for user {$user->id}: " . $e->getMessage());
                    }
                }
            });
        
        $this->info("Usage warning check completed!");
        $this->info("Users checked: {$usersChecked}");
        $this->info("Warning emails sent: {$warningsSent}");
        
        Log::info('Usage warning check completed', [
            'users_checked' => $usersChecked,
            'warnings_sent' => $warningsSent
        ]);
        
        return 0;
    }
}