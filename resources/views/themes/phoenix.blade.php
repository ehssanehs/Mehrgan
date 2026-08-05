@extends('layouts.frontend')

@section('title', $settings->get('phoenix_navbar_brand') ?: 'Phoenix VPN')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/phoenix/css/style.css') }}">
@endpush

@section('content')
    <div class="content-wrapper">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#">{{ $settings->get('phoenix_navbar_brand') ?: 'PHOENIX' }}</a>
                <a href="{{ auth()->check() ? route('filament.admin.pages.dashboard') : route('login') }}" class="btn btn-outline-warning btn-sm">{{ auth()->check() ? 'پنل کاربری' : 'ورود / ثبت‌نام' }}</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <header class="hero-section">
            <div class="container" data-aos="fade-up">
                <h1 class="display-2 mb-4" style="font-weight:900; color: var(--phoenix-gold);">{{ $settings->get('phoenix_hero_title') ?: 'تولد دوباره در شبکه‌های دیجیتال' }}</h1>
                <p class="lead mb-5" style="color: var(--phoenix-gray); max-width: 620px; margin: 0 auto 1.5rem auto;">
                    {{ $settings->get('phoenix_hero_subtitle') ?: 'از خاکستر محدودیت‌ها برخیزید. امنیت و سرعت در کنار هم.' }}
                </p>
                <a href="#pricing" class="btn btn-lg px-5 py-3" style="background: linear-gradient(135deg, var(--phoenix-gold), var(--phoenix-orange)); color:#060608; font-weight:800; border-radius:50px; text-decoration:none;">
                    {{ $settings->get('phoenix_hero_button') ?: 'برخیز از خاکستر' }}
                </a>
            </div>
        </header>

        <!-- Features -->
        <section id="features" class="py-5">
            <div class="container text-center">
                <h2 class="mb-5" style="font-weight:800; font-size:2.2rem; color: var(--phoenix-light);">{{ $settings->get('phoenix_features_title') ?: 'ویژگی‌های آتشین' }}</h2>
                <div class="row g-4">
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="card h-100" style="background: rgba(15,15,24,0.8); border: 1px solid rgba(255,215,0,0.15); border-radius: 1.25rem; padding: 2rem;">
                            <i class="fas fa-fire mb-3" style="font-size: 2.5rem; color: var(--phoenix-gold);"></i>
                            <h4 class="fw-bold mb-3">{{ $settings->get('phoenix_feature1_title') ?: 'سرعت شعله‌ور' }}</h4>
                            <p style="color: var(--phoenix-gray);">{{ $settings->get('phoenix_feature1_desc') ?: 'اتصال با سرعتی فراتر از محدودیت‌ها برای تجربه‌ای بدون وقفه.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="card h-100" style="background: rgba(15,15,24,0.8); border: 1px solid rgba(255,215,0,0.15); border-radius: 1.25rem; padding: 2rem;">
                            <i class="fas fa-shield-halved mb-3" style="font-size: 2.5rem; color: var(--phoenix-gold);"></i>
                            <h4 class="fw-bold mb-3">{{ $settings->get('phoenix_feature2_title') ?: 'سپر آتشین' }}</h4>
                            <p style="color: var(--phoenix-gray);">{{ $settings->get('phoenix_feature2_desc') ?: 'رمزنگاری کامل برای حفظ حریم خصوصی در هر لحظه.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="card h-100" style="background: rgba(15,15,24,0.8); border: 1px solid rgba(255,215,0,0.15); border-radius: 1.25rem; padding: 2rem;">
                            <i class="fas fa-infinity mb-3" style="font-size: 2.5rem; color: var(--phoenix-gold);"></i>
                            <h4 class="fw-bold mb-3">{{ $settings->get('phoenix_feature3_title') ?: 'جاودانگی' }}</h4>
                            <p style="color: var(--phoenix-gray);">{{ $settings->get('phoenix_feature3_desc') ?: 'سرورهای پایدار با آپتایم نزدیک به صد درصد.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="py-5">
            <div class="container">
                <h2 class="text-center mb-5" style="font-weight:800; font-size:2.2rem; color: var(--phoenix-light);">{{ $settings->get('phoenix_pricing_title') ?: 'انتخاب شعله' }}</h2>
                <div class="row justify-content-center">
                    @forelse($plans as $plan)
                        <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="{{ $loop->iteration * 100 }}">
                            <div class="card text-center h-100 d-flex flex-column" style="background: rgba(15,15,24,0.85); border: 1px solid rgba(255,215,0,0.2); border-radius: 1.25rem; padding: 2rem;">
                                <h4 class="fw-bold" style="color: var(--phoenix-gold);">{{ $plan->name }}</h4>
                                <p class="display-4 fw-bold" style="color: var(--phoenix-light);">{{ number_format($plan->price) }}<span class="fs-5" style="color: var(--phoenix-gray);"> تومان</span></p>
                                <hr style="border-color: rgba(255,215,0,0.2);">
                                <ul class="list-unstyled my-3 text-end">
                                    <li class="mb-2" style="color: var(--phoenix-gray);"><i class="fas fa-check-circle me-2" style="color: var(--phoenix-gold);"></i> {{ $plan->volume_gb }} گیگابایت ترافیک</li>
                                    <li class="mb-2" style="color: var(--phoenix-gray);"><i class="fas fa-check-circle me-2" style="color: var(--phoenix-gold);"></i> {{ $plan->duration_days }} روز اعتبار</li>
                                </ul>
                                <div class="mt-auto">
                                    <a href="{{ route('login') }}" class="btn btn-lg w-100" style="background: linear-gradient(135deg, var(--phoenix-gold), var(--phoenix-orange)); color:#060608; font-weight:800; border-radius:50px;">فعالسازی</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center" style="color: var(--phoenix-gray);">در حال حاضر هیچ پلن فعالی وجود ندارد.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section id="faq" class="py-5">
            <div class="container w-75">
                <h2 class="text-center mb-5" style="font-weight:800; font-size:2.2rem; color: var(--phoenix-light);">{{ $settings->get('phoenix_faq_title') ?: 'طومارهای آتشین' }}</h2>
                <div class="accordion" id="faqAccordion">
                    <div class="card mb-3" style="background: rgba(15,15,24,0.8); border: 1px solid rgba(255,215,0,0.15); border-radius: 0.75rem;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q1" style="background: transparent; color: var(--phoenix-light); font-weight:700;">
                                {{ $settings->get('phoenix_faq1_q') ?: 'آیا اطلاعات کاربران ذخیره می‌شود؟' }}
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body" style="color: var(--phoenix-gray);">
                                {{ $settings->get('phoenix_faq1_a') ?: 'خیر. ما هیچ گزارشی از فعالیت شما ذخیره نمی‌کنیم.' }}
                            </div>
                        </div>
                    </div>
                    <div class="card mb-3" style="background: rgba(15,15,24,0.8); border: 1px solid rgba(255,215,0,0.15); border-radius: 0.75rem;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2" style="background: transparent; color: var(--phoenix-light); font-weight:700;">
                                {{ $settings->get('phoenix_faq2_q') ?: 'چگونه سرویس را روی چند دستگاه استفاده کنم؟' }}
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body" style="color: var(--phoenix-gray);">
                                {{ $settings->get('phoenix_faq2_a') ?: 'کانفیگ را می‌توانید روی دستگاه‌های مختلف فعال کنید.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-4 mt-5" style="border-top: 1px solid rgba(255,215,0,0.15); text-align: center; color: var(--phoenix-gray); font-size: 0.9rem;">
            <div class="container">
                <p class="mb-0">{{ $settings->get('phoenix_footer_text') ?: '© 2025 Phoenix Networks' }}</p>
            </div>
        </footer>
    </div>
@endsection

@push('scripts')
    <script>
        if (typeof AOS !== 'undefined') AOS.init({ duration: 900, once: true, offset: 80 });
    </script>
@endpush
