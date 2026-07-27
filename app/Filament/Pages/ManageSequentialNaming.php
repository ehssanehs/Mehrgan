<?php

namespace App\Filament\Pages;

use App\Models\SequentialNamingSetting;
use App\Services\ClientNamingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class ManageSequentialNaming extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'مدیریت کاربران';
    protected static ?string $navigationIcon = 'heroicon-o-numbered-list';
    protected static ?string $navigationLabel = 'تنظیمات نام‌گذاری ترتیبی';
    protected static string $view = 'filament.pages.manage-sequential-naming';
    protected static ?string $title = 'مدیریت نام‌گذاری ترتیبی کلاینت‌ها';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = SequentialNamingSetting::getSettings();
        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'prefix' => $settings->prefix,
            'counter' => $settings->counter,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('تنظیمات نام‌گذاری ترتیبی')
                    ->description('در این بخش می‌توانید قابلیت نام‌گذاری ترتیبی کلاینت‌ها را فعال کرده و پیشوند آن را تعیین کنید. شماره‌ها همیشه افزایشی هستند و با حذف کاربران، شماره‌ها دوباره استفاده نمی‌شوند.')
                    ->schema([
                        Toggle::make('is_enabled')
                            ->label('فعال‌سازی نام‌گذاری ترتیبی')
                            ->helperText('اگر فعال باشد، نام کلاینت‌ها به صورت ترتیبی با پیشوند مشخص تولید می‌شود. مثال: server1u1, server1u2, ...')
                            ->columnSpanFull(),

                        TextInput::make('prefix')
                            ->label('پیشوند نام کلاینت')
                            ->placeholder('مثال: server1u یا eu-')
                            ->required()
                            ->maxLength(50)
                            ->helperText('پیشوند مورد نظر برای نام کلاینت‌ها. تغییر پیشوند باعث ریست شدن شمارنده به 1 می‌شود.')
                            ->prefixIcon('heroicon-o-tag'),

                        TextInput::make('counter')
                            ->label('شمارنده فعلی (فقط خواندنی)')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('این شمارنده تعداد کلاینت‌های ساخته شده با پیشوند فعلی را نشان می‌دهد. نام بعدی برابر است با پیشوند + (شمارنده + 1).')
                            ->prefixIcon('heroicon-o-hashtag'),

                        Placeholder::make('next_name_preview')
                            ->label('پیش‌نمایش نام بعدی')
                            ->content(function ($get) {
                                $prefix = $get('prefix') ?? SequentialNamingSetting::currentPrefix();
                                $counter = SequentialNamingSetting::currentCounter();
                                $next = $counter + 1;
                                return new HtmlString("<span class='font-mono font-bold text-primary-600'>{$prefix}{$next}</span>");
                            }),

                        Placeholder::make('info')
                            ->label('راهنما')
                            ->content(new HtmlString('
                                <ul class="list-disc list-inside space-y-1 text-sm text-gray-600">
                                    <li>شماره‌گذاری همیشه افزایشی است</li>
                                    <li>حذف کاربران باعث استفاده مجدد شماره‌ها نمی‌شود</li>
                                    <li>تغییر پیشوند، شمارنده را به 0 ریست می‌کند و نام‌گذاری از 1 شروع می‌شود</li>
                                    <li>نام تولید شده هم در دیتابیس VPNMarket و هم در پنل Xray/XUI استفاده می‌شود</li>
                                </ul>
                            '))
                            ->columnSpanFull(),
                    ])->columns(2)
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetCounter')
                ->label('ریست شمارنده')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('ریست شمارنده')
                ->modalDescription('آیا از ریست کردن شمارنده اطمینان دارید؟ شمارنده فعلی به 0 برگردانده می‌شود و نام بعدی از 1 شروع می‌شود. این عملیات فقط روی پیشوند فعلی تاثیر دارد.')
                ->action(function () {
                    $settings = ClientNamingService::resetCounter();
                    $this->form->fill([
                        'is_enabled' => $settings->is_enabled,
                        'prefix' => $settings->prefix,
                        'counter' => $settings->counter,
                    ]);

                    Notification::make()
                        ->title('شمارنده با موفقیت ریست شد.')
                        ->body("شمارنده برای پیشوند '{$settings->prefix}' به 0 برگردانده شد. نام بعدی: {$settings->prefix}1")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $enabled = (bool) ($data['is_enabled'] ?? false);
        $prefix = trim($data['prefix'] ?? '');

        if (empty($prefix)) {
            Notification::make()
                ->title('خطا')
                ->body('پیشوند نمی‌تواند خالی باشد.')
                ->danger()
                ->send();
            return;
        }

        // Validate prefix: allow alphanumeric, dash, underscore
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $prefix)) {
            Notification::make()
                ->title('خطا')
                ->body('پیشوند فقط می‌تواند شامل حروف انگلیسی، اعداد، خط تیره و زیرخط باشد.')
                ->danger()
                ->send();
            return;
        }

        $settings = ClientNamingService::updateSettings($enabled, $prefix);

        $this->form->fill([
            'is_enabled' => $settings->is_enabled,
            'prefix' => $settings->prefix,
            'counter' => $settings->counter,
        ]);

        Notification::make()
            ->title('تنظیمات با موفقیت ذخیره شد.')
            ->body($settings->is_enabled ? "نام‌گذاری ترتیبی فعال است. نام بعدی: {$settings->prefix}" . ($settings->counter + 1) : "نام‌گذاری ترتیبی غیرفعال شد.")
            ->success()
            ->send();
    }
}
