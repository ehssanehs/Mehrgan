<?php

namespace Modules\TelegramBot\Listeners;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Ticketing\Events\TicketReplied;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendTicketRepliedNotificationToAdmin
{
    /**
     * Handle the event.
     */
    public function handle(TicketReplied $event): void
    {
        Log::info('SendTicketRepliedNotificationToAdmin handler triggered.');

        $reply = $event->reply->load('ticket.user', 'user');
        $ticket = $reply->ticket;
        $ticketOwner = $ticket->user;
        $replyingUser = $reply->user;

        // If the reply is from admin, don't notify admins again (prevent loops)
        if ($replyingUser && $replyingUser->is_admin) {
            Log::info('Skipping admin notification: reply is from admin.');
            return;
        }

        $settings = Setting::pluck('value', 'key');
        $botToken = $settings->get('telegram_bot_token');

        if (!$botToken) {
            Log::error('Telegram bot token not found in settings.');
            return;
        }

        $adminChatIds = $this->getTelegramAdminChatIds($settings);
        if (empty($adminChatIds)) {
            Log::warning('No admin chat IDs configured for ticket reply notification.');
            return;
        }

        try {
            Telegram::setAccessToken($botToken);

            // Prepare the message for admins
            // ⚠️ HTML parse mode: فقط کاراکترهای «& < >» باید escape شوند؛
            // «#» و پرانتز (که در MarkdownV2 مشکل‌ساز بودند) در HTML خطا ایجاد
            // نمی‌کنند. محتوای کاربری با escapeTelegramHTML() ایمن می‌شود.
            $message = "💬 <b>پاسخ جدید به تیکت #" . escapeTelegramHTML((string) $ticket->id) . "</b>\n\n";
            $message .= "<b>کاربر:</b> " . escapeTelegramHTML((string) ($ticketOwner->name ?? 'نامشخص')) . " (ID: " . escapeTelegramHTML((string) $ticketOwner->id) . ")\n";
            $message .= "<b>موضوع:</b> " . escapeTelegramHTML((string) $ticket->subject) . "\n";
            $message .= "<b>تاریخ:</b> " . escapeTelegramHTML($reply->created_at->format('Y/m/d H:i')) . "\n\n";
            $message .= "<b>متن پاسخ:</b>\n" . escapeTelegramHTML((string) $reply->message);

            // Create inline keyboard for admin actions
            $keyboard = Keyboard::make()->inline()->row([
                Keyboard::inlineButton([
                    'text' => '✉️ پاسخ به تیکت',
                    'callback_data' => "admin_reply_ticket_{$ticket->id}"
                ]),
                Keyboard::inlineButton([
                    'text' => '❌ بستن تیکت',
                    'callback_data' => "admin_close_ticket_{$ticket->id}"
                ]),
            ]);

            $basePayload = [
                'reply_markup' => $keyboard,
                'parse_mode' => 'HTML',
            ];

            // Send with attachment if it exists
            if ($reply->attachment_path && Storage::disk('public')->exists($reply->attachment_path)) {
                $filePath = Storage::disk('public')->path($reply->attachment_path);
                $mimeType = Storage::disk('public')->mimeType($reply->attachment_path);
                Log::info('Sending ticket reply with attachment.', ['path' => $filePath, 'mime' => $mimeType]);

                $filePayload = $basePayload + ['caption' => $message];

                if (str_starts_with($mimeType, 'image/')) {
                    $filePayload['photo'] = InputFile::create($filePath);

                    foreach ($adminChatIds as $adminChatId) {
                        try {
                            $filePayload['chat_id'] = $adminChatId;
                            Telegram::sendPhoto($filePayload);
                        } catch (\Exception $e) {
                            Log::warning("Failed to send ticket reply notification to admin {$adminChatId}: " . $e->getMessage());
                        }
                    }
                } else {
                    $filePayload['document'] = InputFile::create($filePath);

                    foreach ($adminChatIds as $adminChatId) {
                        try {
                            $filePayload['chat_id'] = $adminChatId;
                            Telegram::sendDocument($filePayload);
                        } catch (\Exception $e) {
                            Log::warning("Failed to send ticket reply notification to admin {$adminChatId}: " . $e->getMessage());
                        }
                    }
                }
            } else {
                // Send text-only message
                Log::info('Sending text-only ticket reply notification.');

                foreach ($adminChatIds as $adminChatId) {
                    try {
                        $textPayload = $basePayload + [
                            'chat_id' => $adminChatId,
                            'text' => $message,
                        ];
                        Telegram::sendMessage($textPayload);
                    } catch (\Exception $e) {
                        Log::warning("Failed to send ticket reply notification to admin {$adminChatId}: " . $e->getMessage());
                        // ⚠️ Fallback: اگر پیام MarkdownV2 به هر دلیلی رد شد،
                        // همان پیام را بدون parse_mode (متن ساده) دوباره بفرست تا
                        // ادمین هرگز نوتیف پاسخ تیکت را از دست ندهد.
                        try {
                            unset($textPayload['parse_mode']);
                            Telegram::sendMessage($textPayload);
                        } catch (\Exception $fallbackException) {
                            Log::error("Failed to send plain-text ticket reply notification to admin {$adminChatId}: " . $fallbackException->getMessage());
                        }
                    }
                }
            }

            Log::info("Successfully sent ticket reply notification for ticket #{$ticket->id} to admins.");

        } catch (\Exception $e) {
            Log::error("Failed to send Telegram notification for ticket reply: {$e->getMessage()}", [
                'ticket_id' => $ticket->id,
                'reply_id' => $reply->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Get admin chat IDs from settings.
     */
    protected function getTelegramAdminChatIds($settings): array
    {
        $raw = $settings->get('telegram_admin_chat_id');

        if (empty($raw)) {
            return [];
        }

        // New format: JSON array
        $decoded = Setting::decodeArrayValue($raw);
        if (!empty($decoded)) {
            return array_filter(array_map('strval', $decoded));
        }

        // Old format: single numeric string
        if (is_numeric($raw)) {
            return [(string) $raw];
        }

        // Fallback: comma/space separated
        $parts = preg_split('/[,\s،]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        return !empty($parts) ? array_map('trim', $parts) : [];
    }
}
