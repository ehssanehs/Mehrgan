<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * NOWPayments reports "sending" while the transaction is still in flight;
     * only these statuses mean the money actually arrived.
     */
    private const PAID_STATUSES = ['finished', 'confirmed'];

    public function handle(Request $request)
    {
        $secret = (string) config('services.nowpayments.ipn_secret');

        // Fail closed: without a configured secret every request is
        // unverifiable, and this endpoint can flip an order to "paid".
        if ($secret === '') {
            Log::warning('NOWPayments webhook rejected: NOWPAYMENTS_IPN_SECRET is not configured.');

            return response()->json(['message' => 'Webhook is not configured'], 503);
        }

        $data = $request->all();
        $signature = strtolower((string) $request->header('x-nowpayments-sig', ''));
        $expected = hash_hmac('sha512', $this->canonicalJson($data), $secret);

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            Log::warning('NOWPayments webhook rejected: signature mismatch.', [
                'ip' => $request->ip(),
                'order_id' => $data['order_id'] ?? null,
            ]);

            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $status = $data['payment_status'] ?? null;

        if (! in_array($status, self::PAID_STATUSES, true)) {
            // Acknowledged but not a payment: pending, waiting, sending, failed,
            // expired, refunded. NOWPayments retries anything that is not a 2xx.
            return response()->json(['message' => 'Ignored'], 200);
        }

        $order = Order::find($data['order_id'] ?? null);

        if (! $order) {
            Log::warning('NOWPayments webhook for an unknown order.', ['order_id' => $data['order_id'] ?? null]);

            return response()->json(['message' => 'Unknown order'], 200);
        }

        if ($order->status === 'paid') {
            return response()->json(['message' => 'Already processed'], 200);
        }

        $order->update([
            'status' => 'paid',
            'nowpayments_payment_id' => $data['payment_id'] ?? null,
        ]);

        // Keep the paid-order side effects (referral rewards, notifications)
        // in sync with every other payment path.
        OrderPaid::dispatch($order->fresh());

        Log::info("Order #{$order->id} has been paid via NOWPayments.");

        return response()->json(['message' => 'Webhook Handled'], 200);
    }

    /**
     * NOWPayments signs the body with its keys sorted recursively.
     */
    private function canonicalJson(array $data): string
    {
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as $key => $item) {
                if (is_array($item)) {
                    $value[$key] = $sort($item);
                }
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return json_encode($sort($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
