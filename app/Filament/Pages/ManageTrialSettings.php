<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select; // اضافه شد
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageTrialSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'مدیریت کاربران';
    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'تنظیمات اکانت تست';
    protected static string $view = 'filament.pages.manage-trial-settings';
    protected static ?string $title = 'مدیریت تنظیمات اکانت تست';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()
            ->pluck('value', 'key')
            ->map(fn ($value) => Setting::normalizeValue($value))
            ->toArray();
        $this->form->fill([
            'trial_enabled' => $settings['trial_enabled'] ?? false,
            'trial_volume_mb' => $settings['trial_volume_mb'] ?? 500,
            'trial_duration_hours' => $settings['trial_duration_hours'] ?? 24,
            'trial_limit_per_user' => $settings['trial_limit_per_user'] ?? 1,
            'trial_server_id' => $settings['trial_server_id'] ?? null, // اضافه شد
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('تنظیمات اصلی اکانت تست')
                    ->description('در این بخش می‌توانید قابلیت اکانت تست را فعال کرده و مقادیر پیش‌فرض آن را تعیین کنید.')
                    ->schema([
                        Toggle::make('trial_enabled')
                            ->label('فعال‌سازی اکانت تست')
                            ->default(false)
                            ->helperText('اگر فعال باشد، دکمه اکانت تست در منوی ربات نمایش داده می‌شود و کاربران می‌توانند اکانت تست دریافت کنند.'),

                        // 👇 فیلد جدید انتخاب سرور 👇
                        Select::make('trial_server_id')
                            ->label('سرور مخصوص اکانت تست')
                            ->options(function () {
                                // چک می‌کنیم ماژول مولتی سرور وجود داشته باشد
                                if (class_exists('Modules\MultiServer\Models\Server')) {
                                    return \Modules\MultiServer\Models\Server::where('is_active', true)
                                        ->get()
                                        ->mapWithKeys(function ($server) {
                                            return [$server->id => "{$server->name} ({$server->ip_address})"];
                                        });
                                }
                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('انتخاب کنید...')
                            ->helperText('اکانت‌های تست روی این سرور ساخته می‌شوند. اگر انتخاب نکنید، سیستم خودکار یک سرور خالی را انتخاب می‌کند.'),

                        TextInput::make('trial_volume_mb')
                            ->label('حجم اکانت تست (مگابایت)')
                            ->numeric()
                            ->required()
                            ->default(500),

                        TextInput::make('trial_duration_hours')
                            ->label('مدت زمان اکانت تست (ساعت)')
                            ->numeric()
                            ->required()
                            ->default(24),

                        TextInput::make('trial_limit_per_user')
                            ->label('محدودیت هر کاربر')
                            ->numeric()
                            ->required()
                            ->default(1),
                    ])
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        // Toggle values must be stored in the same format that the Telegram
        // webhook reads. In particular, do not let a false value turn into an
        // empty setting and accidentally fall back to an unrelated default.
        $data['trial_enabled'] = filter_var(
            $data['trial_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        ) ? '1' : '0';

        foreach ($data as $key => $value) {
            // تبدیل مقدار null به رشته خالی یا ذخیره نکردن آن
            $val = is_null($value) ? '' : $value;
            Setting::updateOrCreate(['key' => $key], ['value' => $val]);
        }

        // Activating the feature from this page is also the user's request to
        // make it available in the bot. Reset the optional visibility override
        // so an old `tg_show_trial_button=0` value cannot hide the new feature.
        if ($data['trial_enabled'] === '1') {
            Setting::updateOrCreate(
                ['key' => 'tg_show_trial_button'],
                ['value' => '1']
            );
        }

        Notification::make()->title('تنظیمات با موفقیت ذخیره شد.')->success()->send();
    }
}
