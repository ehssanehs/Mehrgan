<?php

namespace Modules\Referral\Filament\Resources;

use App\Models\Transaction;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Referral\Filament\Resources\ReferralResource\Pages;

class ReferralResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'گزارش دعوت‌ها';
    protected static ?string $modelLabel = 'کاربر';
    protected static ?string $pluralModelLabel = 'گزارش دعوت‌ها';
    protected static ?string $slug = 'referrals';

    protected static ?string $navigationGroup = 'مدیریت افزونه‌ها';
    protected static ?int $navigationSort = 21;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('نام کاربر')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('ایمیل')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telegram_chat_id')
                    ->label('آیدی تلگرام')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                Tables\Columns\TextColumn::make('referral_code')
                    ->label('کد دعوت')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('referrals_count')
                    ->label('تعداد دعوت (ثبت‌نام)')
                    ->counts('referrals')
                    ->sortable(),
                Tables\Columns\TextColumn::make('successful_referrals_count')
                    ->label('دعوت‌های موفق (خرید)')
                    ->counts([
                        'referrals as successful_referrals_count' => function (Builder $query) {
                            $query->whereHas('orders', function (Builder $q) {
                                $q->where('status', 'paid')->whereNotNull('plan_id');
                            });
                        },
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('referral_earnings')
                    ->label('مجموع درآمد از دعوت (تومان)')
                    ->getStateUsing(function (User $record) {
                        return Transaction::where('user_id', $record->id)
                            ->where('type', Transaction::TYPE_DEPOSIT)
                            ->where('status', Transaction::STATUS_COMPLETED)
                            ->whereJsonContains('metadata->referral_reward', true)
                            ->sum('amount');
                    })
                    ->formatStateUsing(fn ($state) => number_format((int) $state) . ' تومان')
                    ->sortable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('موجودی کیف پول')
                    ->formatStateUsing(fn ($state) => number_format((int) $state) . ' تومان')
                    ->sortable(),
            ])
            ->defaultSort('referrals_count', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferrals::route('/'),
        ];
    }


    public static function canCreate(): bool
    {
        return false;
    }
}




