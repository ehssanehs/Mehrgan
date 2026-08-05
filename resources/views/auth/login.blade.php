@php($settings = \App\Models\Setting::all()->pluck('value', 'key'))
<x-guest-layout>
    <div class="theme-auth-card">
        <div class="theme-auth-brand">{{ $settings->get('auth_brand_name', config('app.name', 'VPN Market')) }}</div>
        <h1 class="theme-auth-title">ورود به حساب کاربری</h1>
        <x-auth-session-status class="mb-4" :status="session('status')" />
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="ایمیل خود را وارد کنید"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
            <div><input id="password" type="password" name="password" required autocomplete="current-password" placeholder="رمز عبور"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
            <label class="flex items-center gap-2 text-sm"><input id="remember_me" type="checkbox" name="remember" class="!w-auto"> مرا به خاطر بسپار</label>
            <button type="submit" class="theme-button w-full">ورود</button>
        </form>
        <p class="mt-6 text-center text-sm">حساب کاربری ندارید؟ <a href="{{ route('register') }}">یک حساب بسازید</a></p>
    </div>
</x-guest-layout>
