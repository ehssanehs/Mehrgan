@php($settings = \App\Models\Setting::all()->pluck('value', 'key'))
<x-guest-layout>
    <div class="theme-auth-card">
        <div class="theme-auth-brand">{{ $settings->get('auth_brand_name', config('app.name', 'VPN Market')) }}</div>
        <h1 class="theme-auth-title">ایجاد حساب کاربری جدید</h1>
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            @if(request()->has('ref'))<input type="hidden" name="ref" value="{{ request()->query('ref') }}">@endif
            <div><input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="نام کامل"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div>
            <div><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="ایمیل"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
            <div><input id="password" type="password" name="password" required autocomplete="new-password" placeholder="رمز عبور"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
            <div><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="تکرار رمز عبور"></div>
            <button type="submit" class="theme-button w-full">ثبت نام</button>
        </form>
        <p class="mt-6 text-center text-sm">قبلاً ثبت‌نام کرده‌اید؟ <a href="{{ route('login') }}">وارد شوید</a></p>
    </div>
</x-guest-layout>
