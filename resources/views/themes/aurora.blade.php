@extends('layouts.frontend')

@section('title', $settings->get('aurora_navbar_brand') ?: 'Aurora VPN')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/aurora/css/style.css') }}">
@endpush

@section('content')
    <div class="content-wrapper">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#">{{ $settings->get('aurora_navbar_brand') ?: 'AURORA' }}</a>
                <a href="{{ auth()->check() ? route('filament.admin.pages.dashboard') : route('login') }}" class="btn btn-outline-primary btn-sm">{{ auth()->check() ? 'پنل کاربری' : 'ورود / ثبت‌نام' }}</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <header class="hero-section">
            <div class="container" data-aos="fade-up">
                <h1 class="aurora-title mb-4">{{ $settings->get('aurora_hero_title') ?: 'نور تازه در جهان دیجیتال' }}</h1>
                <p class="aurora-subtitle">
                    {{ $settings->get('aurora_hero_subtitle') ?: 'اتصالی روشن، سریع و امن برای تجربه‌ای متفاوت از اینترنت آزاد.' }}
                </p>
                <a href="#pricing" class="btn-aurora">{{ $settings->get('aurora_hero_button') ?: 'شروع روشنایی' }}</a>
            </div>
        </header>

        <!-- Features Section -->
        <section id="features" class="py-5">
            <div class="container text-center">
                <h2 class="section-title" data-aos="fade-up">{{ $settings->get('aurora_features_title') ?: 'ویژگی‌های روشن' }}</h2>
                <div class="row mt-4">
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="aurora-card h-100">
                            <div class="icon-glow"><i class="fas fa-wind"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('aurora_feature1_title') ?: 'سرعت بی‌نظیر' }}</h4>
                            <p>{{ $settings->get('aurora_feature1_desc') ?: 'اتصال با سرعت بالا برای استریم، بازی و مرور بدون هیچ وقفه‌ای.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="aurora-card h-100">
                            <div class="icon-glow"><i class="fas fa-lock"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('aurora_feature2_title') ?: 'امنیت شفاف' }}</h4>
                            <p>{{ $settings->get('aurora_feature2_desc') ?: 'رمزنگاری کامل برای حفظ حریم خصوصی شما در هر لحظه.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="icon-glow"><i class="fas fa-headset"></i></div>
                        <div class="aurora-card h-100">
                            <h4 class="fw-bold">{{ $settings->get('aurora_feature3_title') ?: 'پشتیبانی همیشه' }}</h4>
                            <p>{{ $settings->get('aurora_feature3_desc') ?: 'تیم متخصص ما همیشه آماده پاسخگویی و رفع مشکلات شماست.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-5">
            <div class="container">
                <h2 class="section-title text-center" data-aos="fade-up">{{ $settings->get('aurora_pricing_title') ?: 'انتخاب روشنایی' }}</h2>
                <div class="row justify-content-center">
                    @forelse($plans as $plan)
                        <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="{{ $loop->iteration * 100 }}">
                            <div class="aurora-card pricing-card h-100 {{ $plan->is_popular ? 'popular' : '' }}">
                                <h4 class="fw-bold mb-3">{{ $plan->name }}</h4>
                                <p class="display-price fw-bold">{{ number_format($plan->price) }}<span> تومان</span></p>
                                <hr style="border-color: var(--aurora-border);">
                                <ul class="list-unstyled my-4 text-end">
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: var(--aurora-accent);"></i> {{ $plan->volume_gb }} گیگابایت ترافیک</li>
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: var(--aurora-accent);"></i> {{ $plan->duration_days }} روز اعتبار</li>
                                </ul>
                                <div class="mt-auto">
                                    <a href="{{ route('login') }}" class="btn-aurora w-100">فعالسازی</a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-secondary">در حال حاضر هیچ پلن فعالی وجود ندارد.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="py-5">
            <div class="container w-75">
                <h2 class="section-title text-center" data-aos="fade-up">{{ $settings->get('aurora_faq_title') ?: 'سوالات روشن' }}</h2>
                <div class="accordion" id="faqAccordion" data-aos="fade-up" data-aos-delay="100">
                    <div class="aurora-card mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                {{ $settings->get('aurora_faq1_q') ?: 'آیا اطلاعات کاربران ذخیره می‌شود؟' }}
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary pt-3">
                                {{ $settings->get('aurora_faq1_a') ?: 'خیر. ما هیچ گزارشی از فعالیت کاربران ذخیره نمی‌کنیم.' }}
                            </div>
                        </div>
                    </div>
                    <div class="aurora-card mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                {{ $settings->get('aurora_faq2_q') ?: 'چگونه سرویس را روی چند دستگاه استفاده کنم؟' }}
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary pt-3">
                                {{ $settings->get('aurora_faq2_a') ?: 'کانفیگ دریافتی را می‌توانید روی دستگاه‌های مختلف فعال کنید.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="container text-center">
                <p class="mb-2">{{ $settings->get('aurora_footer_text') ?: '© 2025 Aurora Networks' }}</p>
                <p class="mb-0" style="opacity: 0.7;">
                    طراحی و توسعه توسط
                    <a href="https://t.me/VPNMarket_OfficialSupport" target="_blank" rel="noopener noreferrer">VPNMarket</a>
                </p>
            </div>
        </footer>
    </div>
@endsection

@push('scripts')
    <script>
        if (typeof AOS !== 'undefined') AOS.init({ duration: 900, once: true, offset: 80 });
    </script>
@endpush
