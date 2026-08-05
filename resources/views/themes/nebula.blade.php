@extends('layouts.frontend')

@section('title', $settings->get('nebula_navbar_brand') ?: 'Nebula VPN')

@push('styles')
    <link rel="stylesheet" href="{{ asset('themes/nebula/css/style.css') }}">
@endpush

@section('content')
    <div id="nebula-stars"></div>

    <div class="content-wrapper">
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#">{{ $settings->get('nebula_navbar_brand') ?: 'NEBULA' }}</a>
                <a href="{{ auth()->check() ? route('filament.admin.pages.dashboard') : route('login') }}" class="btn btn-outline-light btn-sm">{{ auth()->check() ? 'پنل کاربری' : 'ورود / ثبت‌نام' }}</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <header class="hero-section">
            <div class="container" data-aos="fade-up">
                <h1 class="nebula-title mb-4">{{ $settings->get('nebula_hero_title') ?: 'سفر به کهکشان‌های دیجیتال' }}</h1>
                <p class="hero-subtitle">
                    {{ $settings->get('nebula_hero_subtitle') ?: 'با پروتکل‌های نوری ما، مرزهای اینترنت را بشکنید. امنیتی بی‌نهایت در سرعتی فراتر از تصور.' }}
                </p>
                <a href="#pricing" class="btn-nebula">{{ $settings->get('nebula_hero_button') ?: 'ورود به کهکشان' }}</a>
            </div>
        </header>

        <!-- Features Section -->
        <section id="features" class="py-5">
            <div class="container text-center">
                <h2 class="section-title" data-aos="fade-up">{{ $settings->get('nebula_features_title') ?: 'ویژگی‌های کهکشانی' }}</h2>
                <div class="row mt-4">
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="nebula-card h-100">
                            <div class="icon-glow"><i class="fas fa-rocket"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('nebula_feature1_title') ?: 'سرعت نوری' }}</h4>
                            <p>{{ $settings->get('nebula_feature1_desc') ?: 'اتصال با سرعتی باورنکردنی برای تجربه‌ای بدون قطعی و تاخیر.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="nebula-card h-100">
                            <div class="icon-glow"><i class="fas fa-shield-halved"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('nebula_feature2_title') ?: 'سپر نامرئی' }}</h4>
                            <p>{{ $settings->get('nebula_feature2_desc') ?: 'رمزنگاری پیشرفته برای حفظ حریم خصوصی در عمیق‌ترین لایه‌ها.' }}</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="nebula-card h-100">
                            <div class="icon-glow"><i class="fas fa-globe"></i></div>
                            <h4 class="fw-bold">{{ $settings->get('nebula_feature3_title') ?: 'مرزهای نامحدود' }}</h4>
                            <p>{{ $settings->get('nebula_feature3_desc') ?: 'دسترسی به تمام محتوا از هر نقطه کهکشان بدون هیچ محدودیتی.' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section id="pricing" class="py-5">
            <div class="container">
                <h2 class="section-title text-center" data-aos="fade-up">{{ $settings->get('nebula_pricing_title') ?: 'انتخاب سفر' }}</h2>
                <div class="row justify-content-center">
                    @forelse($plans as $plan)
                        <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="{{ $loop->iteration * 100 }}">
                            <div class="nebula-card pricing-card h-100 {{ $plan->is_popular ? 'popular' : '' }}">
                                <h4 class="fw-bold mb-3">{{ $plan->name }}</h4>
                                <p class="display-price fw-bold">{{ number_format($plan->price) }}<span> تومان</span></p>
                                <hr style="border-color: var(--nebula-border);">
                                <ul class="list-unstyled my-4 text-end">
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: var(--nebula-glow);"></i> {{ $plan->volume_gb }} گیگابایت ترافیک</li>
                                    <li class="mb-2"><i class="fas fa-check-circle me-2" style="color: var(--nebula-glow);"></i> {{ $plan->duration_days }} روز اعتبار</li>
                                </ul>
                                <div class="mt-auto">
                                    <a href="{{ route('login') }}" class="btn-nebula w-100">فعالسازی</a>
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
                <h2 class="section-title text-center" data-aos="fade-up">{{ $settings->get('nebula_faq_title') ?: 'سوالات کهکشانی' }}</h2>
                <div class="accordion" id="faqAccordion" data-aos="fade-up" data-aos-delay="100">
                    <div class="nebula-card mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-light fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                {{ $settings->get('nebula_faq1_q') ?: 'آیا اطلاعات کاربران ذخیره می‌شود؟' }}
                            </button>
                        </h2>
                        <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary pt-3">
                                {{ $settings->get('nebula_faq1_a') ?: 'خیر. ما به حریم خصوصی شما متعهدیم و هیچ‌گونه گزارشی از فعالیت‌های شما ذخیره نمی‌کنیم.' }}
                            </div>
                        </div>
                    </div>
                    <div class="nebula-card mb-3">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent text-light fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                {{ $settings->get('nebula_faq2_q') ?: 'چگونه سرویس را روی چند دستگاه استفاده کنم؟' }}
                            </button>
                        </h2>
                        <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-secondary pt-3">
                                {{ $settings->get('nebula_faq2_a') ?: 'پس از خرید، یک کانفیگ دریافت می‌کنید که می‌توانید روی دستگاه‌های مختلف استفاده کنید.' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer">
            <div class="container text-center">
                <p class="mb-2">{{ $settings->get('nebula_footer_text') ?: '© 2025 Nebula Networks' }}</p>
                <p class="mb-0" style="opacity: 0.7;">
                    طراحی و توسعه توسط
                    <a href="https://t.me/VPNMarket_OfficialSupport" target="_blank" rel="noopener noreferrer">VPNMarket</a>
                </p>
            </div>
        </footer>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('themes/nebula/js/main.js') }}"></script>
@endpush
