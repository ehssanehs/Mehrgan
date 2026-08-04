<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SetTelegramWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:set-webhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the Telegram bot webhook URL based on your configuration.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Attempting to set Telegram webhook...');
        Log::info('Running telegram:set-webhook command...');

        $appUrl = rtrim((string) config('app.url'), '/');
        $botToken = $this->normaliseBotToken(
            (string) Setting::where('key', 'telegram_bot_token')->value('value')
        );

        if (! $this->isPublicHttpsUrl($appUrl)) {
            $errorMessage = 'Error: APP_URL must be a public HTTPS URL (for example, https://yourdomain.com). Telegram cannot deliver webhooks to localhost or HTTP URLs.';
            $this->error($errorMessage);
            Log::error($errorMessage, ['app_url' => $appUrl]);

            return self::FAILURE;
        }

        if (! $botToken) {
            $errorMessage = 'Error: the Telegram bot token is missing or invalid. Enter the token from BotFather in Site Settings and try again.';
            $this->error($errorMessage);
            $this->warn('Paste only the token (for example, 123456:ABC...), not a Bot API URL or the word "bot".');
            Log::error($errorMessage);

            return self::FAILURE;
        }

        $webhookUrl = $appUrl.'/webhooks/telegram';
        // Do not put the webhook URL in a query string. Posting form data lets the
        // HTTP client correctly encode every valid URL, including URLs with paths.
        $telegramApiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";

        $this->line("Setting webhook to: {$webhookUrl}");
        Log::info('Attempting to set Telegram webhook.', ['webhook_url' => $webhookUrl]);

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post($telegramApiUrl, ['url' => $webhookUrl]);

            if ($response->successful() && $response->json('ok') === true) {
                $successMessage = 'Webhook set successfully! Description: '.$response->json('description');
                $this->info($successMessage);
                Log::info($successMessage, ['webhook_url' => $webhookUrl]);

                return self::SUCCESS;
            }

            $reason = $response->json('description') ?: $response->body() ?: 'Unknown error';
            $errorMessage = "Failed to set webhook (HTTP {$response->status()}). Reason: {$reason}";
            $this->error($errorMessage);
            Log::error($errorMessage, [
                'webhook_url' => $webhookUrl,
                'telegram_error_code' => $response->json('error_code'),
            ]);

            if ($response->status() === 404) {
                $this->warn('Telegram returned “Not Found”. This normally means the bot token saved in Site Settings is no longer valid. Get the current token from BotFather, save it, clear Laravel’s config cache, and run this command again.');
            }

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $errorMessage = 'Unable to connect to the Telegram API: '.$exception->getMessage();
            $this->error($errorMessage);
            Log::critical($errorMessage, ['webhook_url' => $webhookUrl]);

            return self::FAILURE;
        }
    }

    /**
     * Accept a token copied from BotFather, with harmless surrounding whitespace.
     * A few users paste "bot<token>" from an API URL; accepting that prefix avoids
     * producing a misleading Telegram 404 while never logging the secret.
     */
    private function normaliseBotToken(string $token): ?string
    {
        $token = trim($token);
        $token = preg_replace('/^bot\s*/i', '', $token) ?? '';
        $token = trim($token);

        return preg_match('/^\d{5,}:[A-Za-z0-9_-]{20,}$/', $token) ? $token : null;
    }

    private function isPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        return ! in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true);
    }
}
