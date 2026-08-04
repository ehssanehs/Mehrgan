<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
        'test_account_enabled',
        'test_account_volume_gb',
        'test_account_days',
        'test_account_max_per_user',
    ];

    protected $casts = [
        'test_account_enabled' => 'boolean',
        'test_account_volume_gb' => 'integer',
        'test_account_days' => 'integer',
        'test_account_max_per_user' => 'integer',
    ];

    /**
     * Decode a setting value that is expected to contain a JSON array.
     *
     * Settings are stored in a generic text column. Some older broken saves may
     * have double-encoded array values (for example: "\"[{...}]\""). Decode a
     * couple of layers so admin repeaters can still load and re-save them.
     */
    public static function decodeArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (! is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        for ($i = 0; $i < 2 && is_string($decoded); $i++) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function inbounds()
    {
        return $this->hasMany(\App\Models\Inbound::class);
    }

    public function getTestAccountEnabledAttribute($value)
    {
        return (bool) $value;
    }

    /**
     * Get a random payment card from settings.
     * Supports both the new multi-card format (payment_cards) and the old single-card format.
     *
     * @return array{card_number: string, card_holder: string, instructions: string}
     */
    public static function getRandomPaymentCard(): array
    {
        $settings = static::all()->pluck('value', 'key');

        // New format: payment_cards is a JSON array of {card_number, card_holder}
        $cards = $settings->get('payment_cards');

        $cards = static::decodeArrayValue($cards);

        if (count($cards) > 0) {
            // Filter out empty entries
            $validCards = array_values(array_filter($cards, fn($c) => is_array($c) && !empty($c['card_number'])));
            if (count($validCards) > 0) {
                $random = $validCards[array_rand($validCards)];
                return [
                    'card_number'  => $random['card_number'] ?? '---- ---- ---- ----',
                    'card_holder'  => $random['card_holder'] ?? 'ثبت نشده',
                    'instructions' => $settings->get('payment_card_instructions', ''),
                ];
            }
        }

        // Fallback: old single-card format
        return [
            'card_number'  => $settings->get('payment_card_number', '---- ---- ---- ----'),
            'card_holder'  => $settings->get('payment_card_holder_name', 'ثبت نشده'),
            'instructions' => $settings->get('payment_card_instructions', ''),
        ];
    }

    /**
     * Get all payment cards from settings (for admin display).
     * Supports both the new multi-card format and the old single-card format.
     *
     * @return array<int, array{card_number: string, card_holder: string}>
     */
    public static function getAllPaymentCards(): array
    {
        $settings = static::all()->pluck('value', 'key');

        $cards = $settings->get('payment_cards');

        $cards = static::decodeArrayValue($cards);

        if (count($cards) > 0) {
            return array_values(array_filter($cards, fn($c) => is_array($c) && !empty($c['card_number'])));
        }

        // Fallback: old single-card format
        $cardNumber = $settings->get('payment_card_number');
        if ($cardNumber) {
            return [[
                'card_number' => $cardNumber,
                'card_holder' => $settings->get('payment_card_holder_name', ''),
            ]];
        }

        return [];
    }
}
