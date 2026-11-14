<?php

namespace App\Filament\Pages;

use App\Models\Inbound;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ThemeSettings extends Page implements HasForms
{
    protected static ?string $navigationGroup = 'تنظیمات';
    protected static ?int $navigationSort = 10;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.theme-settings';
    protected static ?string $navigationLabel = 'تنظیمات سایت';
    protected static ?string $title = 'تنظیمات و محتوای سایت';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::all()->pluck('value', 'key')->toArray();


        foreach ($settings as $key => $value) {
            if ($value === '') {
                $settings[$key] = null;
            }
            if ($key === 'xui_default_inbound_id' && $value !== null) {
                $settings[$key] = (string) $value;
            }
            // Convert telegram_admin_chat_id to array for TagsInput
            if ($key === 'telegram_admin_chat_id' && $value !== null) {
                $decoded = Setting::decodeArrayValue($value);
                if (! empty($decoded)) {
                    $settings[$key] = $decoded;
                } elseif (is_numeric($value)) {
                    // Old single-value format — wrap in array
                    $settings[$key] = [$value];
                } else {
                    // Unknown format — try splitting by comma
                    $parts = preg_split('/[,\s،]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
                    $settings[$key] = !empty($parts) ? $parts : [];
                }
            }

            // Convert multi-card JSON (including accidentally double-encoded values)
            // to an array before passing it to the Filament Repeater.
            if ($key === 'payment_cards' && $value !== null) {
                $settings[$key] = Setting::decodeArrayValue($value);
            }
        }

        // Convert old single-card format to new multi-card Repeater format
        if (!isset($settings['payment_cards']) || empty($settings['payment_cards'])) {
            $oldCardNumber = $settings['payment_card_number'] ?? null;
            $oldCardHolder = $settings['payment_card_holder_name'] ?? null;
            if ($oldCardNumber) {
                $settings['payment_cards'] = [[
                    'card_number' => $oldCardNumber,
                    'card_holder' => $oldCardHolder ?? '',
                ]];
            } else {
                $settings['payment_cards'] = [];
            }
        }

        $this->form->fill(array_merge([
            'panel_type' => 'marzban',
            // The selected site theme is shared by public, authentication, and
            // customer pages, and is also applied to the admin web panel.
            'active_theme' => 'arcane',
            'xui_host' => null,
            'xui_user' => null,
            'xui_pass' => null,
            'xui_default_inbound_id' => null,
            'xui_link_type' => 'single',
            'marzban_host' => null,
            'marzban_sudo_username' => null,
            'marzban_sudo_password' => null,
            'site_login_url' => null,
        ], $settings));
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make('Tabs')
                ->id('main-tabs')
                ->persistTab()
                ->tabs([
                    Tabs\Tab::make('تنظیمات قالب')
                        ->icon('heroicon-o-swatch')
                        ->schema([
                            Select::make('active_theme')->label('قالب اصلی سایت')->options([
                                'arcane' => 'Arcane — تیره (پیشنهادی)',
                                'aurora' => 'Aurora — روشن',
                            ])->default('arcane')
                                ->helperText('قالب انتخاب‌شده روی صفحات سایت، صفحات ورود و پنل مدیریت اعمال می‌شود.')
                                ->live(),
                        ]),

                    Tabs\Tab::make('محتوای قالب Arcane')->icon('heroicon-o-sparkles')->visible(fn(Get $get) => $get('active_theme') === 'arcane')->schema([
                        Section::make('عمومی')->schema([
                            TextInput::make('arcane_navbar_brand')->label('نام برند در Navbar'),
                            TextInput::make('arcane_footer_text')->label('متن فوتر'),
                        ])->columns(2),
                        Section::make('بخش اصلی')->schema([
                            TextInput::make('arcane_hero_title')->label('تیتر اصلی'),
                            Textarea::make('arcane_hero_subtitle')->label('زیرتیتر')->rows(2),
                            TextInput::make('arcane_hero_button')->label('متن دکمه'),
                        ]),
                        Section::make('ویژگی‌ها')->schema([
                            TextInput::make('arcane_features_title')->label('عنوان بخش'),
                            TextInput::make('arcane_feature1_title')->label('ویژگی ۱'),
                            Textarea::make('arcane_feature1_desc')->label('توضیح ۱')->rows(2),
                            TextInput::make('arcane_feature2_title')->label('ویژگی ۲'),
                            Textarea::make('arcane_feature2_desc')->label('توضیح ۲')->rows(2),
                            TextInput::make('arcane_feature3_title')->label('ویژگی ۳'),
                            Textarea::make('arcane_feature3_desc')->label('توضیح ۳')->rows(2),
                        ])->columns(2),
                        Section::make('قیمت‌گذاری')->schema([
                            TextInput::make('arcane_pricing_title')->label('عنوان بخش'),
                        ]),
                        Section::make('سوالات متداول')->schema([
                            TextInput::make('arcane_faq_title')->label('عنوان بخش'),
                            TextInput::make('arcane_faq1_q')->label('سوال اول'),
                            Textarea::make('arcane_faq1_a')->label('پاسخ اول')->rows(2),
                            TextInput::make('arcane_faq2_q')->label('سوال دوم'),
                            Textarea::make('arcane_faq2_a')->label('پاسخ دوم')->rows(2),
                        ]),
                    ]),

                    Tabs\Tab::make('محتوای قالب Aurora')->icon('heroicon-o-sun')->visible(fn(Get $get) => $get('active_theme') === 'aurora')->schema([
                        Section::make('عمومی')->schema([
                            TextInput::make('aurora_navbar_brand')->label('نام برند در Navbar'),
                            TextInput::make('aurora_footer_text')->label('متن فوتر'),
                        ])->columns(2),
                        Section::make('بخش اصلی')->schema([
                            TextInput::make('aurora_hero_title')->label('تیتر اصلی'),
                            Textarea::make('aurora_hero_subtitle')->label('زیرتیتر')->rows(2),
                            TextInput::make('aurora_hero_button')->label('متن دکمه'),
                        ]),
                        Section::make('ویژگی‌ها')->schema([
                            TextInput::make('aurora_features_title')->label('عنوان بخش'),
                            TextInput::make('aurora_feature1_title')->label('ویژگی ۱'),
                            Textarea::make('aurora_feature1_desc')->label('توضیح ۱')->rows(2),
                            TextInput::make('aurora_feature2_title')->label('ویژگی ۲'),
                            Textarea::make('aurora_feature2_desc')->label('توضیح ۲')->rows(2),
                            TextInput::make('aurora_feature3_title')->label('ویژگی ۳'),
                            Textarea::make('aurora_feature3_desc')->label('توضیح ۳')->rows(2),
                        ])->columns(2),
                        Section::make('قیمت‌گذاری')->schema([
                            TextInput::make('aurora_pricing_title')->label('عنوان بخش'),
                        ]),
                        Section::make('سوالات متداول')->schema([
                            TextInput::make('aurora_faq_title')->label('عنوان بخش'),
                            TextInput::make('aurora_faq1_q')->label('سوال اول'),
                            Textarea::make('aurora_faq1_a')->label('پاسخ اول')->rows(2),
                            TextInput::make('aurora_faq2_q')->label('سوال دوم'),
                            Textarea::make('aurora_faq2_a')->label('پاسخ دوم')->rows(2),
                        ]),
                    ]),

                    Tabs\Tab::make('محتوای صفحات ورود')->icon('heroicon-o-key')->schema([
                        Section::make('متن‌های عمومی')->schema([
                            TextInput::make('auth_brand_name')->label('نام برند')->placeholder('Mehrgan'),
                        ]),
                        Section::make('صفحه ورود (Login)')->schema([
                            TextInput::make('auth_login_title')->label('عنوان فرم ورود'),
                            TextInput::make('auth_login_email_placeholder')->label('متن داخل فیلد ایمیل'),
                            TextInput::make('auth_login_password_placeholder')->label('متن داخل فیلد رمز عبور'),
                            TextInput::make('auth_login_remember_me_label')->label('متن "مرا به خاطر بسپار"'),
                            TextInput::make('auth_login_forgot_password_link')->label('متن لینک "فراموشی رمز"'),
                            TextInput::make('auth_login_submit_button')->label('متن دکمه ورود'),
                            TextInput::make('auth_login_register_link')->label('متن لینک ثبت‌نام'),
                        ])->columns(2),
                        Section::make('صفحه ثبت‌نام (Register)')->schema([
                            TextInput::make('auth_register_title')->label('عنوان فرم ثبت‌نام'),
                            TextInput::make('auth_register_name_placeholder')->label('متن داخل فیلد نام'),
                            TextInput::make('auth_register_password_confirm_placeholder')->label('متن داخل فیلد تکرار رمز'),
                            TextInput::make('auth_register_submit_button')->label('متن دکمه ثبت‌نام'),
                            TextInput::make('auth_register_login_link')->label('متن لینک ورود'),
                        ])->columns(2),
                    ]),

                    Tabs\Tab::make('تنظیمات پنل V2Ray')
                        ->icon('heroicon-o-server-stack')
                        ->schema([
                            Section::make('🌍 سیستم مولتی سرور')
                                ->description('تمامی سرورها (مرزبان، سنایی و...) را از طریق منوی «مولتی سرور» در نوار کناری مدیریت کنید.')
                                ->schema([
                                    Placeholder::make('multiserver_info')
                                        ->content('✅ سیستم به طور کامل روی حالت مولتی سرور تنظیم شده است. لطفاً برای افزودن سرور جدید به منوی «مولتی سرور > سرورها» مراجعه کنید.')
                                        ->columnSpanFull(),
                                ]),
                                
                            // Hidden fields to maintain backward compatibility if needed, or forced values
                            Hidden::make('panel_type')->default('xui'), 
                            Hidden::make('enable_multilocation')->default(true),
                        ]),

                    Tabs\Tab::make('تنظیمات پرداخت')->icon('heroicon-o-credit-card')->schema([
                        Section::make('پرداخت کارت به کارت')
                            ->description('می‌توانید چندین شماره کارت اضافه کنید. هنگام پرداخت، یکی از کارت‌ها به‌صورت تصادفی به کاربر نمایش داده می‌شود.')
                            ->schema([
                                Repeater::make('payment_cards')
                                    ->label('کارت‌های بانکی')
                                    ->addActionLabel('افزودن کارت جدید')
                                    ->reorderable()
                                    ->schema([
                                        TextInput::make('card_number')
                                            ->label('شماره کارت')
                                            ->mask('9999-9999-9999-9999')
                                            ->placeholder('XXXX-XXXX-XXXX-XXXX')
                                            ->helperText('شماره کارت ۱۶ رقمی خود را وارد کنید.')
                                            ->numeric(false)
                                            ->validationAttribute('شماره کارت')
                                            ->required(),
                                        TextInput::make('card_holder')
                                            ->label('نام صاحب حساب')
                                            ->placeholder('به نام ...')
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->helperText('هر بار که کاربر بخواهد کارت به کارت پرداخت کند، یکی از کارت‌های بالا به‌صورت تصادفی انتخاب و نمایش داده می‌شود.'),
                                Textarea::make('payment_card_instructions')->label('توضیحات اضافی (برای همه کارت‌ها)')->rows(3),
                            ]),
                    ]),

                    Tabs\Tab::make('تنظیمات ربات تلگرام')->icon('heroicon-o-paper-airplane')->schema([
                        Section::make('اطلاعات اتصال ربات')->schema([
                            TextInput::make('telegram_bot_token')->label('توکن ربات تلگرام')->password(),
                            TagsInput::make('telegram_admin_chat_id')
                                ->label('چت آی‌دی ادمین‌ها')
                                ->placeholder('آی‌دی عددی را وارد کنید و Enter بزنید')
                                ->helperText('می‌توانید چندین چت آی‌دی ادمین اضافه کنید. هر آی‌دی را تایپ کرده و Enter بزنید.')
                                ->splitKeys([',', '،', ' ', 'Enter', 'Tab'])
                                ->nestedRecursiveRules(['numeric']),
                            TextInput::make('site_login_url')
                                ->label('آدرس ورود به سایت (استفاده در دکمه اطلاعات ورود)')
                                ->placeholder('https://example.com/login')
                                ->helperText('در صورت خالی بودن، آدرس پیش‌فرض سیستم نمایش داده می‌شود.')
                                ->url(),
                        ]),
                        Section::make('اجبار به عضویت در کانال')
                            ->description('کاربران باید قبل از استفاده از ربات، در کانال عضو شوند.')
                            ->schema([
                                Toggle::make('force_join_enabled')
                                    ->label('فعالسازی اجبار به عضویت')
                                    ->reactive()
                                    ->default(false),
                                TextInput::make('telegram_required_channel_id')
                                    ->label('آی‌دی کانال (Username یا Chat ID)')
                                    ->placeholder('@mychannel یا -100123456789')
                                    ->hint('اگر کانال عمومی است @username و اگر خصوصی است Chat ID (مثل -100123456789) را وارد کنید.')
                                    ->required(fn (Get $get): bool => $get('force_join_enabled') === true)
                                    ->maxLength(100),
                            ]),
                    ]),

                ])->columnSpanFull(),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $this->form->validate();
        $formData = $this->form->getState();

        foreach ($formData as $key => $value) {
            // حذف تنظیمات خالی
            if ($value === '' || $value === null) {
                \App\Models\Setting::where('key', $key)->delete();
                Cache::forget("setting.{$key}");
                continue;
            }

            // 🔥 مهم: تبدیل xui_default_inbound_id به string ساده
            if ($key === 'xui_default_inbound_id') {
                $value = (string) $value;
            }

            // ذخیره مستقیم
            \App\Models\Setting::updateOrCreate(
                ['key' => $key],
                ['value' => is_array($value) || is_object($value) ? json_encode($value) : $value]
            );

            Cache::forget("setting.{$key}");
        }

        // Clean up old single-card keys (migrated to payment_cards Repeater)
        \App\Models\Setting::whereIn('key', ['payment_card_number', 'payment_card_holder_name'])->delete();
        Cache::forget("setting.payment_card_number");
        Cache::forget("setting.payment_card_holder_name");

        // پاک کردن کش‌های مرتبط
        Cache::forget('inbounds_dropdown');
        Cache::forget('settings');

        Notification::make()
            ->title('تنظیمات با موفقیت ذخیره شد.')
            ->success()
            ->send();
    }
}
