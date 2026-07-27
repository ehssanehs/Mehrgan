<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionImportService;
use App\Services\VlessParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionImportController extends Controller
{
    public function show()
    {
        return view('subscription.import');
    }

    public function store(Request $request)
    {
        $request->validate([
            'import_input' => 'required|string|max:10000',
        ], [
            'import_input.required' => 'لطفاً لینک VLESS یا URL اشتراک را وارد کنید.',
        ]);

        $input = trim($request->input('import_input'));
        $user = Auth::user();

        // Security: sanitize input, prevent XSS, limit length already done
        // Additional validation: check for malicious patterns
        if (strlen($input) > 10000) {
            return back()->with('error', 'ورودی بیش از حد طولانی است.');
        }

        // Log attempt (without sensitive full data)
        Log::info('Subscription import attempt', [
            'user_id' => $user->id,
            'input_type' => VlessParserService::detectInputType($input),
            'input_preview' => substr($input, 0, 100),
        ]);

        try {
            $result = SubscriptionImportService::import($input, $user, 'web');

            if ($result['success']) {
                $order = $result['order'];
                return redirect()->route('dashboard', ['tab' => 'my_services'])
                    ->with('status', "اشتراک با موفقیت وارد شد! نام کاربری: {$order->panel_username} | UUID: {$order->panel_client_id}");
            } else {
                return back()->with('error', $result['error'] ?? 'خطا در وارد کردن اشتراک.')->withInput();
            }
        } catch (\Exception $e) {
            Log::error('Exception in subscription import controller', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'خطای سیستمی در وارد کردن اشتراک. لطفاً بعداً تلاش کنید یا با پشتیبانی تماس بگیرید.')->withInput();
        }
    }

    public function apiImport(Request $request)
    {
        // For AJAX API usage
        $request->validate([
            'import_input' => 'required|string|max:10000',
        ]);

        $input = trim($request->input('import_input'));
        $user = Auth::user();

        $result = SubscriptionImportService::import($input, $user, 'web');

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Subscription imported successfully',
                'order_id' => $result['order']->id,
                'panel_username' => $result['order']->panel_username,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
            ], 400);
        }
    }
}
