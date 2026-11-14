<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\TelegramBotSetting;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageResellerSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationGroup = 'نمایندگی';
    protected static ?int $navigationSort = 60;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationLabel = 'تنظیمات نمایندگی';

    protected static ?string $title = 'تنظیمات نمایندگی';

    protected static string $view = 'filament.pages.manage-reseller-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $settings = TelegramBotSetting::all()->pluck('value', 'key')->toArray();

        // Convert old single-card format to new multi-card Repeater format
        if (isset($settings['agent_deposit_cards'])) {
            $decodedCards = json_decode($settings['agent_deposit_cards'], true);
            $settings['agent_deposit_cards'] = is_array($decodedCards) ? $decodedCards : [];
        } else {
            $oldCardNumber = $settings['agent_deposit_card_number'] ?? '';
            $oldCardName = $settings['agent_deposit_card_name'] ?? '';
            if ($oldCardNumber) {
                $settings['agent_deposit_cards'] = [[
                    'card_number' => $oldCardNumber,
                    'card_holder' => $oldCardName,
                ]];
            } else {
                $settings['agent_deposit_cards'] = [];
            }
        }

        // The bot menu toggle lives in the main settings table (the bot reads it
        // from there), while the deposit cards are stored in telegram_bot_settings.
        $settings['tg_show_reseller_button'] = filter_var(
            Setting::where('key', 'tg_show_reseller_button')->value('value') ?? true,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('نمایش در ربات')
                    ->description('کنترل دکمه «نمایندگی» در منوی اصلی ربات تلگرام.')
                    ->schema([
                        Toggle::make('tg_show_reseller_button')
                            ->label('نمایش دکمه «نمایندگی» در منوی ربات')
                            ->helperText('با خاموش بودن این گزینه، کاربران نمی‌توانند از ربات درخواست نمایندگی ثبت کنند.')
                            ->default(true),
                    ]),

                Section::make('تنظیمات شارژ نماینده')
                    ->description('شماره کارت و نام صاحب کارت برای شارژ کیف پول نمایندگان در مینی‌اپ. یکی از کارت‌ها به‌صورت تصادفی به نماینده نمایش داده می‌شود.')
                    ->schema([
                        Repeater::make('agent_deposit_cards')
                            ->label('کارت‌های بانکی')
                            ->addActionLabel('افزودن کارت جدید')
                            ->reorderable()
                            ->schema([
                                TextInput::make('card_number')
                                    ->label('شماره کارت')
                                    ->mask('9999-9999-9999-9999')
                                    ->placeholder('6037-1234-5678-9999')
                                    ->numeric(false)
                                    ->required(),
                                TextInput::make('card_holder')
                                    ->label('نام صاحب کارت')
                                    ->placeholder('به نام مدیریت پنل')
                                    ->required(),
                            ])
                            ->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $showResellerButton = filter_var($data['tg_show_reseller_button'] ?? true, FILTER_VALIDATE_BOOLEAN);
        unset($data['tg_show_reseller_button']);

        Setting::updateOrCreate(['key' => 'tg_show_reseller_button'], ['value' => $showResellerButton ? '1' : '0']);
        Cache::forget('settings');

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            TelegramBotSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        // Clean up old single-card keys (migrated to agent_deposit_cards Repeater)
        TelegramBotSetting::whereIn('key', ['agent_deposit_card_number', 'agent_deposit_card_name'])->delete();

        Notification::make()
            ->title('تنظیمات با موفقیت ذخیره شد.')
            ->success()
            ->send();
    }
}

