<?php

namespace Modules\Ticketing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Ticketing\Events\TicketCreated;
use Modules\Ticketing\Events\TicketReplied;
use Modules\TelegramBot\Listeners\SendTelegramReplyNotification;
use Modules\TelegramBot\Listeners\SendTicketCreatedNotification;
use Modules\TelegramBot\Listeners\SendTicketRepliedNotificationToAdmin;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // اطلاع‌رسانی به کاربر (صاحب تیکت) هنگام پاسخ ادمین
        TicketReplied::class => [
            SendTelegramReplyNotification::class,
        ],
        
        // اطلاع‌رسانی به ادمین‌ها هنگام ایجاد تیکت جدید
        TicketCreated::class => [
            SendTicketCreatedNotification::class,
        ],
        
        // اطلاع‌رسانی به ادمین‌ها هنگام پاسخ کاربر به تیکت
        TicketReplied::class => [
            SendTicketRepliedNotificationToAdmin::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
