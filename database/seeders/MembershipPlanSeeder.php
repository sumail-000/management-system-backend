<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MembershipPlan;
use Illuminate\Support\Facades\Log;

class MembershipPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Log::info('Starting MembershipPlan seeder');
        
        $plans = [
            [
                'name' => 'Basic',
                'price' => 0.00,
                'stripe_price_id' => null, // Free plan doesn't need Stripe price ID
                'description' => 'Perfect for small food businesses getting started',
                'features' => [
                    'Manual product entry only',
                    'Max 3 product submissions/month',
                    'Standard label templates',
                    'Basic compliance feedback',
                    'Self-help support',
                    'Email notifications',

                ],
                'product_limit' => 3,
                'label_limit' => 10,
                'qr_code_limit' => 3,// No QR codes for Basic plan
            ],
            [
                'name' => 'Pro',
                'price' => 79.00,
                'stripe_price_id' => 'price_1RmuHTQTPqAU2eQEDWVdz2Nj',
                'description' => 'Ideal for growing businesses with advanced needs',
                'features' => [
                    '20 product limit/month',
                    'Advanced label templates',
                    'Priority label validation',
                    'Label validation PDF reports',
                    'Product dashboard & history',
                    'Email + chat support',

                    'QR code generation',
                    'Multi-language labels',
                    'Allergen detection'
                ],
                'product_limit' => 20,
                'label_limit' => 100,
                'qr_code_limit' => 50, // 50 QR codes for Pro plan
            ],
            [
                'name' => 'Enterprise',
                'price' => 199.00,
                'stripe_price_id' => 'price_1RmuIIQTPqAU2eQEih8REL02',
                'description' => 'Complete solution for large organizations',
                'features' => [
                    'Unlimited products',
                    'Bulk upload via Excel/CSV/API',
                    'Dedicated account manager',
                    'Full API access',
                    'Custom badges & certificates',
                    'Compliance dashboard',
                    'Role-based team access',
                    'Private label management',
                    'Regulatory update access',
                    '24/7 priority support',
                    'Custom integrations',
                    'White-label options'
                ],
                'product_limit' => 0, // 0 means unlimited
                'label_limit' => 0, // 0 means unlimited
                'qr_code_limit' => 0, // 0 means unlimited QR codes
            ]
        ];

        foreach ($plans as $planData) {
            $plan = MembershipPlan::updateOrCreate(
                ['name' => $planData['name']],
                $planData
            );
            
            Log::info('Created/Updated membership plan', [
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'price' => $plan->price,
                'product_limit' => $plan->product_limit,
                'label_limit' => $plan->label_limit,
                'qr_code_limit' => $plan->qr_code_limit
            ]);
        }
        
        Log::info('MembershipPlan seeder completed successfully');
    }
}