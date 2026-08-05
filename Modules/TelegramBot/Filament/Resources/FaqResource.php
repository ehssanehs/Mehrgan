<?php

namespace Modules\TelegramBot\Filament\Resources;

use Modules\TelegramBot\Filament\Resources\FaqResource\Pages;
use Modules\TelegramBot\Models\Faq;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';
    protected static ?string $navigationGroup = 'مدیریت افزونه‌ها';
    protected static ?string $navigationLabel = 'سوالات متداول (FAQ)';
    protected static ?string $modelLabel = 'سوال متداول';
    protected static ?string $pluralModelLabel = 'سوالات متداول';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Card::make()->schema([
                    Forms\Components\TextInput::make('question')
                        ->label('سوال')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('مثال: چگونه می‌توانم سرویس خود را تمدید کنم؟'),

                    Forms\Components\Textarea::make('answer')
                        ->label('پاسخ')
                        ->required()
                        ->rows(6)
                        ->placeholder('پاسخ کامل سوال را اینجا بنویسید...'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتیب نمایش')
                        ->numeric()
                        ->default(0)
                        ->helperText('عدد کوچکتر، بالاتر نمایش داده می‌شود.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true)
                        ->helperText('در صورت غیرفعال بودن، این سوال در ربات تلگرام نمایش داده نمی‌شود.'),
                ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('question')
                    ->label('سوال')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('آخرین بروزرسانی')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('وضعیت فعالیت'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
