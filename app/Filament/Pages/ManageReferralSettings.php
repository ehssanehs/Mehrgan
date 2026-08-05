<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageReferralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'مدیریت افزونه‌ها';
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'تنظیمات دعوت (رفرال)';
    protected static ?string $title = 'مدیریت تنظیمات سیستم دعوت';
    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.manage-referral-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();

        $this->form->fill([
            'referral_enabled' => filter_var($settings['referral_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),

            'referral_referrer_reward' => (int) ($settings['referral_referrer_reward'] ?? 0),
            'referral_referrer_reward_percent' => (float) ($settings['referral_referrer_reward_percent'] ?? 0),

            'referral_welcome_gift' => (int) ($settings['referral_welcome_gift'] ?? 0),

            'referral_min_purchase_for_reward' => (int) ($settings['referral_min_purchase_for_reward'] ?? 0),
            'referral_reward_only_first_purchase' => filter_var($settings['referral_reward_only_first_purchase'] ?? true, FILTER_VALIDATE_BOOLEAN),

            'referral_ip_check' => filter_var($settings['referral_ip_check'] ?? true, FILTER_VALIDATE_BOOLEAN),

            'referral_telegram_notify_referrer' => filter_var($settings['referral_telegram_notify_referrer'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'referral_telegram_notify_referred' => filter_var($settings['referral_telegram_notify_referred'] ?? true, FILTER_VALIDATE_BOOLEAN),

            'referral_telegram_referrer_reward_message' => $settings['referral_telegram_referrer_reward_message']
                ?? "🎉 تبریک!\n\nموجودی کیف پول شما به خاطر خرید موفق زیرمجموعه {referral_name} به مبلغ {amount} تومان افزایش یافت.\nموجودی فعلی: {balance} تومان",

            'referral_telegram_referrer_join_message' => $settings['referral_telegram_referrer_join_message']
                ?? "👤 خبر خوب!\n\nکاربر جدیدی با نام «{referral_name}» با لینک دعوت شما پیوست.",

            'referral_telegram_welcome_gift_message' => $settings['referral_telegram_welcome_gift_message']
                ?? "🎁 هدیه خوش‌آمدگویی\n\nمبلغ {amount} تومان هدیه دعوت به کیف پول شما اضافه شد.\nموجودی فعلی: {balance} تومان",
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('تنظیمات کلی سیستم دعوت')
                    ->description('فعال یا غیرفعال کردن سیستم دعوت از دوستان در وب‌سایت و ربات تلگرام.')
                    ->schema([
                        Toggle::make('referral_enabled')
                            ->label('فعال‌سازی سیستم دعوت')
                            ->helperText('در صورت خاموش بودن، پاداش دعوت به کاربران تعلق نمی‌گیرد و پیام‌های تلگرامی ارسال نخواهد شد.'),
                    ]),

                Section::make('پاداش دعوت‌کننده (Referrer)')
                    ->description('مشخص کنید پس از یک خرید موفق توسط فرد دعوت‌شده، چه مقدار به کیف پول دعوت‌کننده واریز شود.')
                    ->schema([
                        TextInput::make('referral_referrer_reward')
                            ->label('پاداش ثابت دعوت‌کننده (تومان)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('مبلغ ثابت به تومان. برای غیرفعال کردن 0 وارد کنید.'),
                        TextInput::make('referral_referrer_reward_percent')
                            ->label('پاداش درصدی از مبلغ سفارش (٪)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('در صورت وارد کردن عدد، به‌صورت درصدی از مبلغ سفارش (علاوه بر مبلغ ثابت) به کیف پول اضافه می‌شود.'),
                    ])->columns(2),

                Section::make('هدیه خوش‌آمدگویی به فرد دعوت‌شده')
                    ->schema([
                        TextInput::make('referral_welcome_gift')
                            ->label('هدیه خوش‌آمدگویی (تومان)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('این مبلغ در هنگام ثبت‌نام با لینک دعوت به کیف پول فرد دعوت‌شده واریز می‌شود. برای غیرفعال کردن 0 وارد کنید.'),
                    ]),

                Section::make('قوانین اختصاص پاداش')
                    ->schema([
                        Toggle::make('referral_reward_only_first_purchase')
                            ->label('پاداش فقط برای اولین خرید موفق')
                            ->helperText('فقط در صورتی که زیرمجموعه برای اولین بار خرید موفقی انجام دهد پاداش داده می‌شود.')
                            ->default(true),
                        TextInput::make('referral_min_purchase_for_reward')
                            ->label('حداقل مبلغ سفارش برای تعلق پاداش (تومان)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('اگر مبلغ سفارش زیرمجموعه کمتر از این مقدار باشد، پاداشی به دعوت‌کننده داده نمی‌شود. 0 یعنی بدون محدودیت.'),
                        Toggle::make('referral_ip_check')
                            ->label('بررسی IP تکراری برای هدیه خوش‌آمدگویی')
                            ->helperText('در صورت فعال بودن، هدیه خوش‌آمدگویی به کاربرانی که IP مشترک با کاربران قبلی دارند داده نمی‌شود (جلوگیری از تقلب).')
                            ->default(true),
                    ])->columns(1),

                Section::make('تنظیمات اطلاع‌رسانی تلگرام')
                    ->description('پس از واریز پاداش به کیف پول کاربر، از طریق ربات تلگرام به او پیام ارسال شود.')
                    ->schema([
                        Toggle::make('referral_telegram_notify_referrer')
                            ->label('ارسال پیام به دعوت‌کننده هنگام واریز پاداش')
                            ->default(true),
                        Toggle::make('referral_telegram_notify_referred')
                            ->label('ارسال پیام به فرد دعوت‌شده هنگام دریافت هدیه خوش‌آمدگویی')
                            ->default(true),

                        Textarea::make('referral_telegram_referrer_reward_message')
                            ->label('متن پیام پاداش به دعوت‌کننده')
                            ->rows(5)
                            ->helperText('متغیرهای قابل استفاده: {referral_name} نام فرد دعوت‌شده، {amount} مبلغ واریزی، {balance} موجودی جدید کیف پول. متن به صورت plain text ارسال می‌شود و کاراکترهای ویژه تلگرام به‌طور خودکار escape می‌گردند.'),
                        Textarea::make('referral_telegram_referrer_join_message')
                            ->label('متن پیام عضویت جدید (هنگام پیوستن زیرمجموعه)')
                            ->rows(4)
                            ->helperText('متغیر قابل استفاده: {referral_name}.'),
                        Textarea::make('referral_telegram_welcome_gift_message')
                            ->label('متن پیام هدیه خوش‌آمدگویی برای فرد دعوت‌شده')
                            ->rows(4)
                            ->helperText('متغیرهای قابل استفاده: {amount} مبلغ هدیه، {balance} موجودی جدید.'),
                    ])->columns(1),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Notification::make()
            ->title('تنظیمات سیستم دعوت با موفقیت ذخیره شد.')
            ->success()
            ->send();
    }
}
