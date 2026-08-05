<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Telegram\Bot\Laravel\Facades\Telegram;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'ip_address' => $request->ip(), // ذخیره کردن IP کاربر
        ]);


        if ($request->filled('ref')) {
            $referrer = User::where('referral_code', $request->ref)->first();
            $settings = Setting::all()->pluck('value', 'key');

            $referralEnabled = filter_var($settings->get('referral_enabled', '1'), FILTER_VALIDATE_BOOLEAN);

            if ($referrer && $referralEnabled && $referrer->id !== $user->id) {
                $user->referrer_id = $referrer->id;
                $user->save();

                // هدیه خوش‌آمدگویی را از تنظیمات بخوان
                $welcomeGift = (int) $settings->get('referral_welcome_gift', 0);
                $ipCheck = filter_var($settings->get('referral_ip_check', '1'), FILTER_VALIDATE_BOOLEAN);

                if ($welcomeGift > 0) {

                    $blockedByIp = false;
                    if ($ipCheck && $request->ip()) {
                        $blockedByIp = User::where('ip_address', $request->ip())
                            ->where('id', '!=', $user->id)
                            ->whereNotNull('referrer_id')
                            ->exists();
                    }

                    if (! $blockedByIp) {
                        $user->increment('balance', $welcomeGift);
                        $user->refresh();

                        Transaction::create([
                            'user_id' => $user->id,
                            'amount' => $welcomeGift,
                            'type' => Transaction::TYPE_DEPOSIT,
                            'status' => Transaction::STATUS_COMPLETED,
                            'description' => 'هدیه خوش‌آمدگویی دعوت از طرف ' . $referrer->name,
                            'metadata' => [
                                'referral_welcome_gift' => true,
                                'referrer_id' => $referrer->id,
                            ],
                        ]);

                        // Send Telegram notification to the new user (if linked)
                        if (
                            $user->telegram_chat_id &&
                            filter_var($settings->get('referral_telegram_notify_referred', '1'), FILTER_VALIDATE_BOOLEAN)
                        ) {
                            static::sendTelegramTemplateMessage(
                                (string) $user->telegram_chat_id,
                                (string) $settings->get('referral_telegram_welcome_gift_message', ''),
                                [
                                    '{amount}' => number_format($welcomeGift),
                                    '{balance}' => number_format($user->balance),
                                ],
                                $settings->get('telegram_bot_token')
                            );
                        }

                        // Send Telegram notification to referrer about new join
                        if (
                            $referrer->telegram_chat_id
                        ) {
                            $joinTpl = $settings->get('referral_telegram_referrer_join_message');
                            $message = $joinTpl
                                ? str_replace(['{referral_name}'], [$user->name], $joinTpl)
                                : "👤 خبر خوب!\nکاربر جدیدی با نام «{$user->name}» با لینک دعوت شما به سایت پیوست.";

                            static::sendTelegramRawMessage(
                                (string) $referrer->telegram_chat_id,
                                $message,
                                $settings->get('telegram_bot_token')
                            );
                        }
                    }
                }
            }
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    private static function sendTelegramTemplateMessage(string $chatId, string $template, array $vars, ?string $botToken): void
    {
        if (! $botToken || ! $chatId || ! $template) {
            return;
        }

        $text = strtr($template, $vars);
        static::sendTelegramRawMessage($chatId, $text, $botToken);
    }

    private static function sendTelegramRawMessage(string $chatId, string $text, ?string $botToken): void
    {
        if (! $botToken || ! $chatId || ! $text) {
            return;
        }

        try {
            Telegram::setAccessToken($botToken);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to send referral Telegram message: ' . $e->getMessage(), ['chat_id' => $chatId]);
        }
    }
}

