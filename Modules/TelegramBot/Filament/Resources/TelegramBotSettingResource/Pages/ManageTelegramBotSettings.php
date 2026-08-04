<?php

namespace Modules\TelegramBot\Filament\Resources\TelegramBotSettingResource\Pages;

use Filament\Forms\Components\Repeater;
use Modules\TelegramBot\Filament\Resources\TelegramBotSettingResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use App\Models\TelegramBotSetting;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;

class ManageTelegramBotSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = TelegramBotSettingResource::class;
    protected static string $view = 'filament.pages.manage-settings';


    public ?string $activeTab = 'settings';
    public ?array $data = [];
    public ?array $currentAmounts = [];

    public function mount(): void
    {
        $settings = TelegramBotSetting::all()->pluck('value', 'key')->toArray();


        if (isset($settings['deposit_amounts'])) {

            $decodedAmounts = json_decode($settings['deposit_amounts'], true);
            // اطمینان از اینکه خروجی یک آرایه است
            $settings['deposit_amounts'] = is_array($decodedAmounts) ? $decodedAmounts : [];
        } else {
            $settings['deposit_amounts'] = [];
        }

        // Convert old single-card format to new multi-card Repeater format for agent deposit
        if (isset($settings['agent_deposit_cards'])) {
            $decodedCards = json_decode($settings['agent_deposit_cards'], true);
            $settings['agent_deposit_cards'] = is_array($decodedCards) ? $decodedCards : [];
        } else {
            // Migrate from old single-card format
            $oldCardNumber = $settings['agent_deposit_card_number'] ?? null;
            $oldCardName = $settings['agent_deposit_card_name'] ?? null;
            if ($oldCardNumber) {
                $settings['agent_deposit_cards'] = [[
                    'card_number' => $oldCardNumber,
                    'card_holder' => $oldCardName ?? '',
                ]];
            } else {
                $settings['agent_deposit_cards'] = [];
            }
        }


        $this->currentAmounts = $settings['deposit_amounts'];

        $this->form->fill($settings);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('آموزش‌های اتصال')->schema([
                    Textarea::make('tutorial_android')->label('راهنمای اندروید (V2RayNG)')->rows(8),
                    Textarea::make('tutorial_ios')->label('راهنمای آیفون (V2Box/Streisand)')->rows(8),
                    Textarea::make('tutorial_windows')->label('راهنمای ویندوز (V2RayN)')->rows(8),
                ])->columnSpan('full')->hidden(fn() => $this->activeTab !== 'tutorials'),


                Section::make('پیام‌های عمومی')->schema([
                    Textarea::make('welcome_message')->label('پیام خوش‌آمدگویی')->rows(5),
                    Textarea::make('start_message')->label('پیام دستور /start')->rows(3),
                ])->columnSpan('full')->hidden(fn() => $this->activeTab !== 'messages'),

//                Section::make('تنظیمات ربات')->schema([
//                    Textarea::make('bot_name')->label('نام ربات')->rows(1),
//                    Textarea::make('bot_token')->label('توکن ربات')->rows(1),
//                ])->columnSpan('full')->hidden(fn() => $this->activeTab !== 'settings'),

                Section::make('تنظیمات کیف پول')
                    ->schema([
                        Repeater::make('deposit_amounts')
                            ->label('مبلغ‌های پیش‌فرض شارژ')
                            ->addActionLabel('افزودن مبلغ جدید')
                            ->columns(1)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('مبلغ (تومان)')
                                    ->required()
                                    ->numeric()
                                    ->prefix('تومان')
                                    ->minValue(1000),
                            ])
                            ->helperText('این مبالغ به صورت دکمه در ربات به کاربر نمایش داده می‌شوند.'),
                    ]),

                Section::make('تنظیمات نمایندگی')
                    ->schema([
                        Repeater::make('agent_deposit_cards')
                            ->label('کارت‌های بانکی برای شارژ نمایندگان')
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
                            ->columns(2)
                            ->helperText('در صفحه شارژ کیف پول مینی‌اپ نمایندگی، یکی از کارت‌ها به‌صورت تصادفی نمایش داده می‌شود.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();


        if (isset($formData['deposit_amounts'])) {

            $formData['deposit_amounts'] = json_encode($formData['deposit_amounts']);
        }

        if (isset($formData['agent_deposit_cards'])) {
            $formData['agent_deposit_cards'] = json_encode($formData['agent_deposit_cards']);
        }

        foreach ($formData as $key => $value) {
            TelegramBotSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        }

        // Clean up old single-card keys (migrated to agent_deposit_cards Repeater)
        TelegramBotSetting::whereIn('key', ['agent_deposit_card_number', 'agent_deposit_card_name'])->delete();

        Notification::make()->title('تنظیمات با موفقیت ذخیره شد.')->success()->send();

        // رفرش کردن صفحه برای نمایش تغییرات
        $this->redirect(static::getResource()::getUrl('index'));
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('ذخیره تغییرات')
                ->submit('submit'),
        ];
    }
}
