<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class RewardReferrerListener
{
    /**
     * Handle the event.
     */
    public function handle(OrderPaid $event): void
    {
        $order = $event->order;
        $user = $order->user;

        if (! $user) {
            return;
        }

        $settings = Setting::all()->pluck('value', 'key');

        if (! filter_var($settings->get('referral_enabled', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! $user->referrer_id) {
            return;
        }

        // If the reward should only be given on the user's first paid order.
        $onlyFirst = filter_var($settings->get('referral_reward_only_first_purchase', '1'), FILTER_VALIDATE_BOOLEAN);
        if ($onlyFirst && ! $this->isFirstPaidOrder($user)) {
            return;
        }

        // Prevent awarding the same referrer multiple times for the same referred user/order
        $alreadyRewarded = Transaction::where('type', Transaction::TYPE_DEPOSIT)
            ->where('user_id', $user->referrer_id)
            ->where(function ($q) use ($user, $order) {
                // Match by order_id (most reliable)
                if ($order->id) {
                    $q->orWhere('order_id', $order->id);
                }
                // Match by metadata
                $q->orWhereJsonContains('metadata->referred_user_id', $user->id);
            })
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        // Minimum order amount check
        $minPurchase = (int) $settings->get('referral_min_purchase_for_reward', 0);
        $orderAmount = (int) ($order->amount ?? 0);
        if ($minPurchase > 0 && $orderAmount < $minPurchase) {
            return;
        }

        $referrer = User::find($user->referrer_id);
        if (! $referrer) {
            return;
        }

        // Calculate reward
        $fixedReward = (int) $settings->get('referral_referrer_reward', 0);
        $percentReward = (float) $settings->get('referral_referrer_reward_percent', 0);
        $percentAmount = 0;
        if ($percentReward > 0 && $orderAmount > 0) {
            $percentAmount = (int) round(($orderAmount * $percentReward) / 100);
        }
        $rewardAmount = $fixedReward + $percentAmount;

        if ($rewardAmount <= 0) {
            return;
        }

        $referrer->increment('balance', $rewardAmount);
        $referrer->refresh();

        $descParts = ["پاداش دعوت از کاربر: {$user->name}"];
        if ($fixedReward > 0) {
            $descParts[] = "ثابت: " . number_format($fixedReward) . " تومان";
        }
        if ($percentAmount > 0) {
            $descParts[] = "درصدی: " . number_format($percentAmount) . " تومان";
        }

        Transaction::create([
            'user_id' => $referrer->id,
            'order_id' => $order->id,
            'amount' => $rewardAmount,
            'type' => Transaction::TYPE_DEPOSIT,
            'status' => Transaction::STATUS_COMPLETED,
            'description' => implode(' | ', $descParts),
            'metadata' => [
                'referral_reward' => true,
                'referred_user_id' => $user->id,
                'fixed_amount' => $fixedReward,
                'percent_amount' => $percentAmount,
                'order_amount' => $orderAmount,
            ],
        ]);

        // Send Telegram notification to referrer
        if (filter_var($settings->get('referral_telegram_notify_referrer', '1'), FILTER_VALIDATE_BOOLEAN)) {
            $this->notifyReferrer($referrer, $user, $rewardAmount, $settings);
        }
    }

    /**
     * Checks if this is the user's first ever paid order.
     */
    private function isFirstPaidOrder(User $user): bool
    {
        $paidOrdersCount = Order::where('user_id', $user->id)
            ->where('status', 'paid')
            ->whereNotNull('plan_id')
            ->count();

        return $paidOrdersCount === 1;
    }

    private function notifyReferrer(User $referrer, User $referred, int $amount, $settings): void
    {
        try {
            if (! $referrer->telegram_chat_id) {
                return;
            }

            $botToken = $settings->get('telegram_bot_token');
            if (! $botToken) {
                return;
            }

            Telegram::setAccessToken($botToken);

            $template = $settings->get('referral_telegram_referrer_reward_message')
                ?: "🎉 تبریک!\n\nموجودی کیف پول شما به خاطر خرید موفق زیرمجموعه {referral_name} به مبلغ {amount} تومان افزایش یافت.\nموجودی فعلی: {balance} تومان";

            $text = str_replace(
                ['{referral_name}', '{amount}', '{balance}'],
                [
                    $referred->name ?? 'کاربر',
                    number_format($amount),
                    number_format($referrer->balance),
                ],
                $template
            );

            Telegram::sendMessage([
                'chat_id' => $referrer->telegram_chat_id,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to send referral reward Telegram notification: ' . $e->getMessage(), [
                'referrer_id' => $referrer->id,
            ]);
        }
    }
}
