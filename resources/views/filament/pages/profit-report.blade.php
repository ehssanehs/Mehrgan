<x-filament-panels::page>
    <form wire:submit.prevent="search" class="mb-6">
        <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {{ $this->form }}
                <div class="flex items-end">
                    <x-filament::button type="submit" color="primary" icon="heroicon-m-magnifying-glass" class="w-full">
                        جستجو و نمایش گزارش
                    </x-filament::button>
                </div>
            </div>
        </div>
    </form>

    @if($hasSearched)
        {{-- Summary Cards --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900">
                        <x-heroicon-o-banknotes class="h-6 w-6 text-emerald-600 dark:text-emerald-400"/>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">کل فروش (تایید شده)</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalAmount) }} تومان</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                        <x-heroicon-o-shopping-cart class="h-6 w-6 text-blue-600 dark:text-blue-400"/>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">تعداد سفارش‌های موفق</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalOrders) }} عدد</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900">
                        <x-heroicon-o-calculator class="h-6 w-6 text-amber-600 dark:text-amber-400"/>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">میانگین هر سفارش</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $totalOrders > 0 ? number_format((int)($totalAmount / $totalOrders)) : '۰' }} تومان
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Results Table --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    جزئیات فروش ({{ count($reportData) }} مورد)
                </h2>
            </div>

            @if(empty($reportData))
                <div class="p-12 text-center text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-inbox class="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600"/>
                    <p>هیچ سفارش تأیید شده‌ای در این بازه زمانی یافت نشد.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                                <th class="px-4 py-3 text-center">#</th>
                                <th class="px-4 py-3">کاربر</th>
                                <th class="px-4 py-3">پلن</th>
                                <th class="px-4 py-3">نام کاربری</th>
                                <th class="px-4 py-3">نوع</th>
                                <th class="px-4 py-3">پرداخت</th>
                                <th class="px-4 py-3">منبع</th>
                                <th class="px-4 py-3">تاریخ</th>
                                <th class="px-4 py-3">مبلغ (تومان)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($reportData as $index => $row)
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 text-center text-gray-400">{{ $row['id'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $row['user'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['plan'] }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $row['username'] }}</td>
                                    <td class="px-4 py-3">
                                        @if($row['type'] === 'تمدید')
                                            <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-900/30 dark:text-blue-300">تمدید</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-900/30 dark:text-emerald-300">خرید</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['payment'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['source'] }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $row['date'] }}</td>
                                    <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">{{ number_format($row['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold dark:border-gray-600 dark:bg-gray-900">
                                <td class="px-4 py-3 text-center" colspan="8">جمع کل</td>
                                <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400">{{ number_format($totalAmount) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </div>
    @else
        <div class="rounded-xl bg-white p-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:ring-white/10">
            <x-heroicon-o-chart-bar class="mx-auto mb-4 h-16 w-16 text-gray-300 dark:text-gray-600"/>
            <p class="text-lg text-gray-500 dark:text-gray-400">بازه زمانی مورد نظر را انتخاب کرده و روی «جستجو» کلیک کنید.</p>
            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">گزارش سود بر اساس سفارش‌های تأیید شده (paid) نمایش داده می‌شود.</p>
        </div>
    @endif
</x-filament-panels::page>
