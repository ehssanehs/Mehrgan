<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Import Existing Subscription
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative" role="alert">
                    <strong class="font-bold block">خطا!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if (session('status'))
                <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg relative" role="alert">
                    <strong class="font-bold block">موفقیت!</strong>
                    <span class="block sm:inline">{{ session('status') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                        📥 وارد کردن اشتراک موجود
                    </h3>

                    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-700">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">راهنما:</h4>
                        <ul class="list-disc list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                            <li>می‌توانید یک لینک VLESS کامل (مثال: <code>vless://uuid@host:port?...#remark</code>) وارد کنید.</li>
                            <li>یا یک URL اشتراک (Subscription URL) که شامل لیست کانفیگ‌هاست وارد کنید.</li>
                            <li>در صورت وارد کردن Subscription URL، سیستم فقط UUID اولین کانفیگ را استفاده می‌کند (فرض بر این است که همه کانفیگ‌ها متعلق به یک کاربر هستند).</li>
                            <li>UUID استخراج شده در پنل‌های X-UI و مرزبان جستجو می‌شود.</li>
                            <li>در صورت یافت شدن، تمام اطلاعات اشتراک از پنل خوانده شده و به حساب شما متصل می‌شود.</li>
                            <li>این اشتراک سپس دقیقاً مانند اشتراک‌های ساخته شده توسط VPNMarket عمل خواهد کرد (مشاهده مصرف، تمدید، اعلان‌ها، وب‌سایت و ربات تلگرام).</li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('subscription.import.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <label for="import_input" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                لینک VLESS یا Subscription URL
                            </label>
                            <textarea
                                id="import_input"
                                name="import_input"
                                rows="6"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 font-mono text-sm"
                                placeholder="vless://6e5ab8e7-1a34-4d9d-9a2c-9f3e2b6d8c1a@1.2.3.4:443?type=ws&security=tls#MyConfig&#10;&#10;یا&#10;&#10;https://example.com:2096/sub/abcd1234"
                                required
                            >{{ old('import_input') }}</textarea>
                            @error('import_input')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 font-medium text-sm transition">
                                📥 وارد کردن اشتراک
                            </button>

                            <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-sm">
                                بازگشت به داشبورد
                            </a>
                        </div>
                    </form>

                    <div class="mt-8 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg">
                        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">امنیت و محافظت:</h4>
                        <ul class="list-disc list-inside text-xs text-gray-600 dark:text-gray-400 space-y-1">
                            <li>URL های خصوصی (localhost, شبکه‌های داخلی) مسدود هستند (محافظت SSRF).</li>
                            <li>UUID نامعتبر رد می‌شود.</li>
                            <li>اگر UUID قبلاً متعلق به کاربر دیگری باشد، وارد کردن رد می‌شود.</li>
                            <li>در صورت عدم دسترسی به پنل یا نامعتبر بودن محتوا، پیام خطای مناسب نمایش داده می‌شود.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
