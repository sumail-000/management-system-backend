<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\BillingInformation;
use App\Models\PaymentMethod;
use App\Models\BillingHistory;
use App\Models\MembershipPlan;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class BillingController extends Controller
{
    /**
     * Get user's billing information.
     */
    public function getBillingInformation(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $billingInfo = $user->billingInformation;
        
        return response()->json([
            'success' => true,
            'data' => $billingInfo
        ]);
    }

    /**
     * Save or update billing information.
     */
    public function saveBillingInformation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'street_address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'state_province' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        
        $billingInfo = $user->billingInformation()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only([
                'full_name', 'email', 'company_name', 'tax_id',
                'street_address', 'city', 'state_province', 'postal_code',
                'country', 'phone'
            ])
        );

        return response()->json([
            'success' => true,
            'message' => 'Billing information saved successfully',
            'data' => $billingInfo
        ]);
    }

    /**
     * Get user's payment methods.
     */
    public function getPaymentMethods(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $paymentMethods = $user->activePaymentMethods()->get();
        
        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    /**
     * Add a new payment method (for testing purposes).
     */
    public function addPaymentMethod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:card,bank_account',
            'provider' => 'required|string|max:50',
            'brand' => 'required|string|max:50',
            'last_four' => 'required|string|size:4',
            'expiry_month' => 'required_if:type,card|integer|between:1,12',
            'expiry_year' => 'required_if:type,card|integer|min:' . date('Y'),
            'cardholder_name' => 'required_if:type,card|string|max:255',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        
        DB::transaction(function () use ($request, $user) {
            // Check if payment method with same last_four and brand already exists
            $existingPaymentMethod = $user->paymentMethods()
                ->where('last_four', $request->last_four)
                ->where('brand', $request->brand)
                ->where('type', $request->type)
                ->first();

            if ($existingPaymentMethod) {
                // Update existing payment method
                $existingPaymentMethod->update([
                    'provider' => $request->provider,
                    'provider_payment_method_id' => 'test_pm_' . uniqid(), // Test ID
                    'expiry_month' => $request->expiry_month,
                    'expiry_year' => $request->expiry_year,
                    'cardholder_name' => $request->cardholder_name,
                    'is_default' => $request->get('is_default', false),
                    'is_active' => true,
                    'verified_at' => now(), // Auto-verify for testing
                ]);
                
                // If this is set as default, unset other defaults
                if ($request->get('is_default', false)) {
                    $user->paymentMethods()->where('id', '!=', $existingPaymentMethod->id)->update(['is_default' => false]);
                }
                
                return $existingPaymentMethod;
            } else {
                // If this is set as default, unset other defaults
                if ($request->get('is_default', false)) {
                    $user->paymentMethods()->update(['is_default' => false]);
                }

                // Create new payment method
                $paymentMethod = $user->paymentMethods()->create([
                    'type' => $request->type,
                    'provider' => $request->provider,
                    'provider_payment_method_id' => 'test_pm_' . uniqid(), // Test ID
                    'brand' => $request->brand,
                    'last_four' => $request->last_four,
                    'expiry_month' => $request->expiry_month,
                    'expiry_year' => $request->expiry_year,
                    'cardholder_name' => $request->cardholder_name,
                    'is_default' => $request->get('is_default', false),
                    'is_active' => true,
                    'verified_at' => now(), // Auto-verify for testing
                ]);

                return $paymentMethod;
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Payment method added successfully'
        ]);
    }

    /**
     * Get user's billing history.
     */
    public function getBillingHistory(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $billingHistory = $user->billingHistory()
            ->with(['membershipPlan', 'paymentMethod'])
            ->orderBy('billing_date', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $billingHistory
        ]);
    }

    /**
     * Create initial billing history for testing.
     */
    public function createTestBillingHistory(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $membershipPlan = $user->membershipPlan;
        $paymentMethod = $user->defaultPaymentMethod;

        if (!$membershipPlan) {
            return response()->json([
                'success' => false,
                'message' => 'User does not have a membership plan'
            ], 400);
        }

        // Create a few test billing records
        $billingRecords = [
            [
                'billing_date' => now()->subMonth(2),
                'description' => $membershipPlan->name . ' Plan - Monthly Subscription',
                'amount' => $membershipPlan->price,
                'status' => 'paid',
                'paid_at' => now()->subMonth(2)->addHours(1),
            ],
            [
                'billing_date' => now()->subMonth(1),
                'description' => $membershipPlan->name . ' Plan - Monthly Subscription',
                'amount' => $membershipPlan->price,
                'status' => 'paid',
                'paid_at' => now()->subMonth(1)->addHours(1),
            ],
            [
                'billing_date' => now(),
                'description' => $membershipPlan->name . ' Plan - Monthly Subscription',
                'amount' => $membershipPlan->price,
                'status' => 'paid',
                'paid_at' => now()->addHours(1),
            ],
        ];

        foreach ($billingRecords as $record) {
            BillingHistory::create([
                'user_id' => $user->id,
                'membership_plan_id' => $membershipPlan->id,
                'payment_method_id' => $paymentMethod?->id,
                'invoice_number' => BillingHistory::generateInvoiceNumber(),
                'transaction_id' => 'test_txn_' . uniqid(),
                'type' => 'subscription',
                'description' => $record['description'],
                'amount' => $record['amount'],
                'currency' => 'USD',
                'status' => $record['status'],
                'billing_date' => $record['billing_date'],
                'due_date' => $record['billing_date']->copy()->addDays(30),
                'paid_at' => $record['paid_at'],
                'metadata' => [
                    'plan_name' => $membershipPlan->name,
                    'billing_period' => 'monthly'
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Test billing history created successfully'
        ]);
    }

    /**
     * Download invoice as PDF.
     */
    public function downloadInvoice(string $invoiceId)
    {
        try {
            Log::info('PDF Download Request Started', [
                'invoice_id' => $invoiceId,
                'user_id' => Auth::id(),
                'timestamp' => now(),
                'memory_usage' => memory_get_usage(true)
            ]);
            
            /** @var User $user */
            $user = Auth::user();
            
            // Check if user is authenticated
            if (!$user) {
                Log::warning('Unauthenticated PDF request', ['invoice_id' => $invoiceId]);
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            Log::info('Searching for billing record', [
                'invoice_id' => $invoiceId,
                'user_id' => $user->id
            ]);
            
            // Find the billing record by invoice number
            $billingRecord = $user->billingHistory()
                ->with(['membershipPlan', 'paymentMethod'])
                ->where('invoice_number', $invoiceId)
                ->first();

            if (!$billingRecord) {
                Log::warning('Invoice not found in database', [
                    'invoice_id' => $invoiceId,
                    'user_id' => $user->id,
                    'total_billing_records' => $user->billingHistory()->count()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found'
                ], 404);
            }

            Log::info('Billing record found', [
                'billing_id' => $billingRecord->id,
                'invoice_number' => $billingRecord->invoice_number,
                'amount' => $billingRecord->amount,
                'currency' => $billingRecord->currency
            ]);

            // Get billing information
            $billingInfo = $user->billingInformation;
            
            Log::info('Billing info retrieved', [
                'has_billing_info' => $billingInfo ? true : false,
                'billing_info_id' => $billingInfo ? $billingInfo->id : null
            ]);
            
            // Ensure billing record has due_date
            if (!$billingRecord->due_date) {
                $billingRecord->due_date = $billingRecord->billing_date->addDays(30);
            }
            
            Log::info('Starting HTML generation');
            // Generate HTML content for the invoice
            $html = $this->generateInvoiceHtml($billingRecord, $billingInfo, $user);
            
            Log::info('HTML Generated Successfully', [
                'invoice_id' => $invoiceId,
                'html_length' => strlen($html),
                'html_preview' => substr($html, 0, 200) . '...'
            ]);
            
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isPhpEnabled', false);
            // Disable all debug options to remove red borders
            $options->set('debugKeepTemp', false);
            $options->set('debugCss', false);
            $options->set('debugLayout', false);
            $options->set('debugLayoutLines', false);
            $options->set('debugLayoutBlocks', false);
            $options->set('debugLayoutInline', false);
            $options->set('debugLayoutPaddingBox', false);

            
            // Create PDF
            $dompdf = new Dompdf($options);
            
            // Load HTML with error handling
            $dompdf->loadHtml($html, 'UTF-8');
            
            $dompdf->setPaper('A4', 'portrait');
            
            // Render with error handling
            $dompdf->render();
            $pdfOutput = $dompdf->output();
            $filename = "invoice-{$invoiceId}.pdf";
            

            
            // Set headers for file download
            $headers = [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Content-Length' => strlen($pdfOutput),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];
            
            return response($pdfOutput, 200, $headers);
            
        } catch (\Exception $e) {
            Log::error('PDF Generation Failed - Exception Details', [
                'invoice_id' => $invoiceId,
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true),
                'exception_class' => get_class($e)
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'There was an error generating your invoice. Please try again or contact support if the problem persists.',
                'debug' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Export billing history as CSV.
     */
    public function exportBillingHistory()
    {
        try {
            Log::info('CSV Export Request Started', [
                'user_id' => Auth::id(),
                'timestamp' => now(),
                'memory_usage' => memory_get_usage(true)
            ]);
            
            /** @var User $user */
            $user = Auth::user();
            
            // Check if user is authenticated
            if (!$user) {
                Log::warning('Unauthenticated CSV export request');
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            Log::info('Fetching billing history for CSV export', [
                'user_id' => $user->id
            ]);
            
            // Get all billing history
            $billingHistory = $user->billingHistory()
                ->with(['membershipPlan', 'paymentMethod'])
                ->orderBy('billing_date', 'desc')
                ->get();

            Log::info('Billing history retrieved', [
                'record_count' => $billingHistory->count(),
                'user_id' => $user->id
            ]);

            Log::info('Starting CSV generation');
            // Generate CSV content
            $csvContent = $this->generateBillingHistoryCsv($billingHistory);
            
            Log::info('CSV content generated', [
                'csv_length' => strlen($csvContent),
                'record_count' => $billingHistory->count()
            ]);
            
            $filename = 'billing-history-' . now()->format('Y-m-d') . '.csv';
            
            Log::info('CSV Export completed successfully', [
                'filename' => $filename,
                'csv_size' => strlen($csvContent),
                'memory_usage_after' => memory_get_usage(true)
            ]);
            
            // Set headers for file download
            $headers = [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename={$filename}",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];
            
            return response($csvContent, 200, $headers);
            
        } catch (\Exception $e) {
            Log::error('CSV Export Failed - Exception Details', [
                'user_id' => Auth::id(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_usage(true),
                'exception_class' => get_class($e)
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export billing history',
                'debug' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500);
        }
    }

    /**
     * Generate HTML content for invoice PDF.
     */
    private function generateInvoiceHtml($billingRecord, $billingInfo, $user): string
    {
        
        $companyName = config('app.name', 'Your Company');
        $invoiceDate = $billingRecord->billing_date->format('M d, Y');
        $dueDate = $billingRecord->due_date ? $billingRecord->due_date->format('M d, Y') : $billingRecord->billing_date->addDays(30)->format('M d, Y');
        $paidDate = $billingRecord->paid_at ? $billingRecord->paid_at->format('M d, Y') : 'Not Paid';
        

        
        // Handle null billing info
        $billingInfo = $billingInfo ?: (object) [
            'full_name' => null,
            'email' => null,
            'company_name' => null,
            'street_address' => null,
            'city' => null,
            'state_province' => null,
            'postal_code' => null,
            'country' => null,
            'tax_id' => null
        ];
        

        
        try {
            $html = '<!DOCTYPE html>';
            $html .= '<html><head><meta charset="utf-8">';
            $html .= '<title>Invoice ' . htmlspecialchars($billingRecord->invoice_number) . '</title>';
            $html .= '<style>';
            $html .= '@import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap");';
            $html .= '* { margin: 0; padding: 0; box-sizing: border-box; }';
            $html .= 'body { font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: #ffffff; color: #1a1a1a; line-height: 1.6; padding: 40px 20px; }';
            $html .= '.invoice-container { max-width: 800px; margin: 0 auto; background: #ffffff; box-shadow: 0 0 20px rgba(0,0,0,0.1); border-radius: 12px; overflow: hidden; }';
            $html .= '.header { display: flex; justify-content: space-between; align-items: center; padding: 30px 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }';
            $html .= '.company-info h1 { font-size: 28px; font-weight: 700; margin-bottom: 5px; }';
            $html .= '.company-info p { font-size: 14px; opacity: 0.9; }';
            $html .= '.logo-space { width: 120px; height: 60px; background: rgba(255,255,255,0.1); border: 2px dashed rgba(255,255,255,0.3); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: rgba(255,255,255,0.7); }';
            $html .= '.invoice-details { padding: 30px 40px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }';
            $html .= '.invoice-meta { display: flex; justify-content: space-between; align-items: center; }';
            $html .= '.invoice-number { font-size: 24px; font-weight: 600; color: #2d3748; }';
            $html .= '.invoice-date { font-size: 14px; color: #718096; }';
            $html .= '.main-content { padding: 40px; }';
            $html .= '.details-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }';
            $html .= '.details-table th { background: #4a5568; color: white; padding: 16px 20px; text-align: left; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }';
            $html .= '.details-table td { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-size: 15px; }';
            $html .= '.details-table tr:last-child td { border-bottom: none; }';
            $html .= '.details-table tr:nth-child(even) { background: #f7fafc; }';
            $html .= '.section-title { font-size: 18px; font-weight: 600; color: #2d3748; margin-bottom: 20px; padding-bottom: 8px; border-bottom: 2px solid #667eea; }';
            $html .= '.price-section { background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%); padding: 30px; border-radius: 12px; text-align: center; margin: 30px 0; border: 1px solid #e2e8f0; }';
            $html .= '.price-label { font-size: 16px; color: #718096; margin-bottom: 10px; font-weight: 500; }';
            $html .= '.price-amount { font-size: 36px; font-weight: 700; color: #2d3748; margin-bottom: 8px; }';
            $html .= '.price-currency { font-size: 18px; color: #667eea; font-weight: 600; }';
            $html .= '.status-section { text-align: center; margin: 30px 0; }';
            $html .= '.status-badge { display: inline-block; padding: 12px 24px; border-radius: 25px; font-weight: 600; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }';
            $html .= '.status-paid { background: #c6f6d5; color: #22543d; }';
            $html .= '.status-pending { background: #fed7d7; color: #742a2a; }';
            $html .= '.footer-message { background: #edf2f7; padding: 25px; border-radius: 8px; text-align: center; margin-top: 30px; }';
            $html .= '.footer-message h3 { font-size: 18px; color: #2d3748; margin-bottom: 8px; font-weight: 600; }';
            $html .= '.footer-message p { color: #718096; font-size: 14px; line-height: 1.6; }';
            $html .= '</style></head><body>';
            $html .= '<div class="invoice-container">';
            

            
            // Header with company info and logo space
            $html .= '<div class="header">';
            $html .= '<div class="company-info">';
            $html .= '<h1>Professional Invoice</h1>';
            $html .= '<p>Your trusted business partner</p>';
            $html .= '</div>';
            $html .= '<div class="logo-space">Logo Space</div>';
            $html .= '</div>';
            
            // Invoice details section
            $html .= '<div class="invoice-details">';
            $html .= '<div class="invoice-meta">';
            $html .= '<div class="invoice-number">Invoice #' . htmlspecialchars($billingRecord->invoice_number) . '</div>';
            $html .= '<div class="invoice-date">Date: ' . htmlspecialchars($billingRecord->paid_at ? $paidDate : $invoiceDate) . '</div>';
            $html .= '</div>';
            $html .= '</div>';
            

            
            // Main content with centered table
            $html .= '<div class="main-content">';
            
            // Customer & Subscription Details Table
            $html .= '<h2 class="section-title">Invoice Details</h2>';
            $html .= '<table class="details-table">';
            $html .= '<thead>';
            $html .= '<tr><th>Description</th><th>Details</th></tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            $html .= '<tr><td><strong>Customer Name</strong></td><td>' . htmlspecialchars($billingInfo->full_name ?: $user->name) . '</td></tr>';
            $html .= '<tr><td><strong>Email Address</strong></td><td>' . htmlspecialchars($billingInfo->email ?: $user->email) . '</td></tr>';
            if ($billingInfo->company_name) {
                $html .= '<tr><td><strong>Company</strong></td><td>' . htmlspecialchars($billingInfo->company_name) . '</td></tr>';
            }
            $html .= '<tr><td><strong>Subscription Plan</strong></td><td>' . htmlspecialchars($billingRecord->membershipPlan->name ?? 'N/A') . '</td></tr>';
            $html .= '<tr><td><strong>Plan Description</strong></td><td>' . htmlspecialchars($billingRecord->description) . '</td></tr>';
            $html .= '<tr><td><strong>Billing Period</strong></td><td>Monthly</td></tr>';
            $html .= '<tr><td><strong>Payment Date</strong></td><td>' . htmlspecialchars($billingRecord->paid_at ? $paidDate : $invoiceDate) . '</td></tr>';
            $html .= '</tbody>';
            $html .= '</table>';
            

            

            
            // Calculate total amount
            $totalAmount = $billingRecord->amount;
            if (isset($billingRecord->tax_amount) && $billingRecord->tax_amount > 0) {
                $totalAmount += $billingRecord->tax_amount;
            }
            
            // Price section with professional styling
            $html .= '<div class="price-section">';
            $html .= '<div class="price-label">Total Amount</div>';
            $html .= '<div class="price-amount">' . number_format($totalAmount, 2) . '</div>';
            $html .= '<div class="price-currency">' . htmlspecialchars($billingRecord->currency) . '</div>';
            $html .= '</div>';
            

            
            // Payment status section
            $html .= '<div class="status-section">';
            if ($billingRecord->status === 'paid' && $billingRecord->paid_at) {
                $html .= '<span class="status-badge status-paid">✓ Payment Successful</span>';
            } else {
                $html .= '<span class="status-badge status-pending">⏳ Payment Pending</span>';
            }
            $html .= '</div>';
            
            // Footer message section
            $html .= '<div class="footer-message">';
            $html .= '<h3>Thank You for Your Business!</h3>';
            $html .= '<p>We appreciate your continued trust in our services. This invoice serves as your official receipt for the subscription payment. If you have any questions or concerns, please don\'t hesitate to contact our support team.</p>';
            $html .= '</div>';
            
            $html .= '</div>'; // Close main-content
            $html .= '</div>'; // Close invoice-container
            $html .= '</body></html>';
            

            
            return $html;
            
        } catch (\Exception $e) {
            Log::error('Error during HTML generation', [
                'error_message' => $e->getMessage(),
                'error_line' => $e->getLine(),
                'error_file' => $e->getFile()
            ]);
            throw $e;
        }
    }

    /**
     * Generate CSV content for billing history export.
     */
    private function generateBillingHistoryCsv($billingHistory): string
    {
        
        // Add BOM for Excel compatibility
        $csv = "\xEF\xBB\xBF";
        
        // Headers
        $headers = [
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Description',
            'Plan Name',
            'Billing Period',
            'Subtotal',
            'Tax Rate (%)',
            'Tax Amount',
            'Total Amount',
            'Currency',
            'Payment Method',
            'Paid Date',
            'Customer Name',
            'Customer Email',
            'Company Name',
            'Billing Address',
            'City',
            'State/Province',
            'Postal Code',
            'Country',
            'Tax ID',
            'Created At',
            'Updated At'
        ];
        

        
        $csv .= implode(',', array_map(function($header) {
            return '"' . str_replace('"', '""', $header) . '"';
        }, $headers)) . "\n";
        

        
        // Data rows
        foreach ($billingHistory as $index => $record) {

            
            // Get user and billing info
            $user = $record->user;
            $billingInfo = $user ? $user->billingInformation : null;
            
            // Safely handle payment method
            $paymentMethod = 'N/A';
            if ($record->paymentMethod && $record->paymentMethod->brand && $record->paymentMethod->last_four) {
                $paymentMethod = "{$record->paymentMethod->brand} ending in {$record->paymentMethod->last_four}";
            }
            
            // Calculate billing period
            $billingPeriod = 'Monthly'; // Default
            if ($record->membershipPlan) {
                $billingPeriod = ucfirst($record->membershipPlan->billing_cycle ?? 'monthly');
            }
            
            // Calculate tax information
            $taxRate = $record->tax_rate ?? 0;
            $taxAmount = $record->tax_amount ?? 0;
            $subtotal = $record->amount;
            $totalAmount = $subtotal + $taxAmount;
            
            // Safely handle dates and other fields
            $invoiceNumber = $record->invoice_number ?? 'N/A';
            $billingDate = $record->billing_date ? $record->billing_date->format('Y-m-d') : 'N/A';
            $dueDate = $record->due_date ? $record->due_date->format('Y-m-d') : ($record->billing_date ? $record->billing_date->addDays(30)->format('Y-m-d') : 'N/A');
            $description = $record->description ?? 'Subscription Payment';
            $planName = ($record->membershipPlan && $record->membershipPlan->name) ? $record->membershipPlan->name : 'N/A';
            $currency = $record->currency ?? 'USD';
            $paidDate = $record->paid_at ? $record->paid_at->format('Y-m-d H:i:s') : '';
            
            // Format address
            $fullAddress = '';
            if ($billingInfo && $billingInfo->street_address) {
                $fullAddress = $billingInfo->street_address;
            }
            
            $row = [
                $invoiceNumber,
                $billingDate,
                $dueDate,
                $description,
                $planName,
                $billingPeriod,
                number_format($subtotal, 2),
                number_format($taxRate, 2),
                number_format($taxAmount, 2),
                number_format($totalAmount, 2),
                $currency,
                $paymentMethod,
                $paidDate,
                $billingInfo ? ($billingInfo->full_name ?: ($user ? $user->name : 'N/A')) : 'N/A',
                $billingInfo ? ($billingInfo->email ?: ($user ? $user->email : 'N/A')) : 'N/A',
                $billingInfo ? ($billingInfo->company_name ?: 'N/A') : 'N/A',
                $fullAddress ?: 'N/A',
                $billingInfo ? ($billingInfo->city ?: 'N/A') : 'N/A',
                $billingInfo ? ($billingInfo->state_province ?: 'N/A') : 'N/A',
                $billingInfo ? ($billingInfo->postal_code ?: 'N/A') : 'N/A',
                $billingInfo ? ($billingInfo->country ?: 'N/A') : 'N/A',
                $billingInfo ? ($billingInfo->tax_id ?: 'N/A') : 'N/A',
                $record->created_at->format('Y-m-d H:i:s'),
                $record->updated_at->format('Y-m-d H:i:s')
            ];
            

            
            $csvRow = implode(',', array_map(function($field) {
                $fieldStr = (string)$field;
                return '"' . str_replace('"', '""', $fieldStr) . '"';
            }, $row)) . "\n";
            
            $csv .= $csvRow;
            

        }
        

        
        return $csv;
    }
}
