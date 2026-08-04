<?php

namespace App\Filament\Pages;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Number;

class ProfitReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'گزارش سود';
    protected static ?string $title = 'گزارش سود و فروش';
    protected static ?string $navigationGroup = 'مدیریت سفارشات';
    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.pages.profit-report';

    public ?array $data = [];

    public array $reportData = [];
    public int $totalOrders = 0;
    public int $totalAmount = 0;
    public bool $hasSearched = false;

    public function mount(): void
    {
        $this->form->fill([
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('date_from')
                    ->label('از تاریخ')
                    ->default(now()->startOfMonth())
                    ->maxDate(now())
                    ->jalali()
                    ->closeOnDateSelection()
                    ->required(),
                DatePicker::make('date_to')
                    ->label('تا تاریخ')
                    ->default(now())
                    ->maxDate(now())
                    ->jalali()
                    ->closeOnDateSelection()
                    ->afterOrEqual('date_from')
                    ->required(),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function search(): void
    {
        $this->validate();

        $from = Carbon::parse($this->data['date_from'])->startOfDay();
        $to = Carbon::parse($this->data['date_to'])->endOfDay();

        $orders = Order::with(['user', 'plan'])
            ->where('status', 'paid')
            ->whereNotNull('plan_id')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at', 'desc')
            ->get();

        // Build grouped report rows
        $this->reportData = $orders->map(function (Order $order) {
            $planName = $order->plan?->name ?? 'پلن حذف شده';
            $userName = $order->user?->name ?? 'کاربر حذف شده';
            $amount = (int) $order->amount;
            $isRenewal = (bool) $order->renews_order_id;
            $type = $isRenewal ? 'تمدید' : 'خرید';

            return [
                'id'           => $order->id,
                'user'         => $userName,
                'plan'         => $planName,
                'amount'       => $amount,
                'type'         => $type,
                'payment'      => $order->payment_method === 'wallet' ? 'کیف پول' : 'کارت به کارت',
                'source'       => $order->source === 'telegram' ? 'تلگرام' : ($order->source === 'web' ? 'وب‌سایت' : $order->source),
                'date'         => $order->created_at->format('Y/m/d H:i'),
                'username'     => $order->panel_username,
            ];
        })->toArray();

        $this->totalOrders = count($this->reportData);
        $this->totalAmount = $orders->sum('amount');
        $this->hasSearched = true;
    }

    /**
     * Override header actions to include the form fields.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getViewData(): array
    {
        return [
            'reportData'   => $this->reportData,
            'totalOrders'  => $this->totalOrders,
            'totalAmount'  => $this->totalAmount,
            'hasSearched'  => $this->hasSearched,
        ];
    }
}
