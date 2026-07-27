<?php

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Providers\ConsoleServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | Default module namespace.
    |
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Module Stubs
    |--------------------------------------------------------------------------
    |
    | Default module stubs.
    |
    */
    'stubs' => [
        'enabled' => false,
        'path' => base_path('vendor/nwidart/laravel-modules/src/Commands/stubs'),
        'files' => [
            'routes/web' => 'routes/web.php',
            'routes/api' => 'routes/api.php',
            'views/index' => 'resources/views/index.blade.php',
            'views/master' => 'resources/views/components/layouts/master.blade.php',
            'scaffold/config' => 'config/config.php',
            'composer' => 'composer.json',
            'assets/js/app' => 'resources/assets/js/app.js',
            'assets/sass/app' => 'resources/assets/sass/app.scss',
            'vite' => 'vite.config.js',
            'package' => 'package.json',
        ],
        'replacements' => [
            /**
             * Define custom replacements for each section.
             * You can specify a closure for dynamic values.
             *
             * Example:
             *
             * 'composer' => [
             *      'CUSTOM_KEY' => fn (\Nwidart\Modules\Generators\ModuleGenerator $generator) => $generator->getModule()->getLowerName() . '-module',
             *      'CUSTOM_KEY2' => fn () => 'custom text',
             *      'LOWER_NAME',
             *      'STUDLY_NAME',
             *      // ...
             * ],
             *
             * Note: Keys should be in UPPERCASE.
             */
            'routes/web' => ['LOWER_NAME', 'STUDLY_NAME', 'PLURAL_LOWER_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'routes/api' => ['LOWER_NAME', 'STUDLY_NAME', 'PLURAL_LOWER_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'vite' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME'],
            'json' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'PROVIDER_NAMESPACE'],
            'views/index' => ['LOWER_NAME'],
            'views/master' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME'],
            'scaffold/config' => ['STUDLY_NAME'],
            'composer' => [
                'LOWER_NAME',
                'STUDLY_NAME',
                'VENDOR',
                'AUTHOR_NAME',
                'AUTHOR_EMAIL',
                'MODULE_NAMESPACE',
                'PROVIDER_NAMESPACE',
                'APP_FOLDER_NAME',
            ],
        ],
        'gitkeep' => true,
    ],
    'paths' => [
        /*
        |--------------------------------------------------------------------------
        | Modules path
        |--------------------------------------------------------------------------
        |
        | This path is used to save the generated module.
        | This path will also be added automatically to the list of scanned folders.
        |
        */
        'modules' => base_path('Modules'),

        /*
        |--------------------------------------------------------------------------
        | Modules assets path
        |--------------------------------------------------------------------------
        |
        | Here you may update the modules' assets path.
        |
        */
        'assets' => public_path('modules'),

        /*
        |--------------------------------------------------------------------------
        | The migrations' path
        |--------------------------------------------------------------------------
        |
        | Where you run the 'module:publish-migration' command, where do you publish the
        | the migration files?
        |
        */
        'migration' => base_path('database/migrations'),

        /*
        |--------------------------------------------------------------------------
        | The app path
        |--------------------------------------------------------------------------
        |
        | app folder name
        | for example can change it to 'src' or 'App'
        */
        'app_folder' => 'app',

        /*
        |--------------------------------------------------------------------------
        | Generator path
        |--------------------------------------------------------------------------
        | Customise the paths where the folders will be generated.
        | Setting the generate key to false will not generate that folder
        */
        'generator' => [
            // app/
            'actions' => ['path' => 'app/Actions', 'generate' => false],
            'casts' => ['path' => 'app/Casts', 'generate' => false],
            'channels' => ['path' => 'app/Broadcasting', 'generate' => false],
            'class' => ['path' => 'app/Classes', 'generate' => false],
            'command' => ['path' => 'app/Console', 'generate' => false],
            'component-class' => ['path' => 'app/View/Components', 'generate' => false],
            'emails' => ['path' => 'app/Emails', 'generate' => false],
            'event' => ['path' => 'app/Events', 'generate' => false],
            'enums' => ['path' => 'app/Enums', 'generate' => false],
            'exceptions' => ['path' => 'app/Exceptions', 'generate' => false],
            'jobs' => ['path' => 'app/Jobs', 'generate' => false],
            'helpers' => ['path' => 'app/Helpers', 'generate' => false],
            'interfaces' => ['path' => 'app/Interfaces', 'generate' => false],
            'listener' => ['path' => 'app/Listeners', 'generate' => false],
            'model' => ['path' => 'app/Models', 'generate' => false],
            'notifications' => ['path' => 'app/Notifications', 'generate' => false],
            'observer' => ['path' => 'app/Observers', 'generate' => false],
            'policies' => ['path' => 'app/Policies', 'generate' => false],
            'provider' => ['path' => 'app/Providers', 'generate' => true],
            'repository' => ['path' => 'app/Repositories', 'generate' => false],
            'resource' => ['path' => 'app/Transformers', 'generate' => false],
            'route-provider' => ['path' => 'app/Providers', 'generate' => true],
            'rules' => ['path' => 'app/Rules', 'generate' => false],
            'services' => ['path' => 'app/Services', 'generate' => false],
            'scopes' => ['path' => 'app/Models/Scopes', 'generate' => false],
            'traits' => ['path' => 'app/Traits', 'generate' => false],

            // app/Http/
            'controller' => ['path' => 'app/Http/Controllers', 'generate' => true],
            'filter' => ['path' => 'app/Http/Middleware', 'generate' => false],
            'request' => ['path' => 'app/Http/Requests', 'generate' => false],

            // config/
            'config' => ['path' => 'config', 'generate' => true],

            // database/
            'factory' => ['path' => 'database/factories', 'generate' => true],
            'migration' => ['path' => 'database/migrations', 'generate' => true],
            'seeder' => ['path' => 'database/seeders', 'generate' => true],

            // lang/
            'lang' => ['path' => 'lang', 'generate' => false],

            // resource/
            'assets' => ['path' => 'resources/assets', 'generate' => true],
            'component-view' => ['path' => 'resources/views/components', 'generate' => false],
            'views' => ['path' => 'resources/views', 'generate' => true],

            // routes/
            'routes' => ['path' => 'routes', 'generate' => true],

            // tests/
            'test-feature' => ['path' => 'tests/Feature', 'generate' => true],
            'test-unit' => ['path' => 'tests/Unit', 'generate' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Discover of Modules
    |--------------------------------------------------------------------------
    |
    | Here you configure auto discover of module
    | This is useful for simplify module providers.
    |
    */
    'auto-discover' => [
        /*
        |--------------------------------------------------------------------------
        | Migrations
        |--------------------------------------------------------------------------
        |
        | This option for register migration automatically.
        |
        */
        'migrations' => true,

        /*
        |--------------------------------------------------------------------------
        | Translations
        |--------------------------------------------------------------------------
        |
        | This option for register lang file automatically.
        |
        */
        'translations' => false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Package commands
    |--------------------------------------------------------------------------
    |
    | Here you can define which commands will be visible and used in your
    | application. You can add your own commands to merge section.
    |
    */
    'commands' => ConsoleServiceProvider::defaultCommands()
        ->merge([
            // New commands go here
        ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Scan Path
    |--------------------------------------------------------------------------
    |
    | Here you define which folder will be scanned. By default will scan vendor
    | directory. This is useful if you host the package in packagist website.
    |
    */
    'scan' => [
        'enabled' => false,
        'paths' => [
            base_path('vendor/*/*'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer File Template
    |--------------------------------------------------------------------------
    |
    | Here is the config for the composer.json file, generated by this package
    |
    */
    'composer' => [
        'vendor' => env('MODULE_VENDOR', 'nwidart'),
        'author' => [
            'name' => env('MODULE_AUTHOR_NAME', 'Nicolas Widart'),
            'email' => env('MODULE_AUTHOR_EMAIL', 'n.widart@gmail.com'),
        ],
        'composer-output' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Choose what laravel-modules will register as custom namespaces.
    | Setting one to false will require you to register that part
    | in your own Service Provider class.
    |--------------------------------------------------------------------------
    */
    'register' => [
        'translations' => true,
        /**
         * load files on boot or register method
         */
        'files' => 'register',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activators
    |--------------------------------------------------------------------------
    |
    | You can define new types of activators here, file, database, etc. The only
    | required parameter is 'class'.
    | The file activator will store the activation status in storage/installed_modules
    */
    'activators' => [
        'file' => [
            'class' => FileActivator::class,
            'statuses-file' => base_path('modules_statuses.json'),
        ],
    ],

    'activator' => 'file',
return [
 'customers'=>['title'=>'مشتریان','table'=>'customers','perm'=>'customers','search'=>['first_name','last_name','mobile','customer_code','occupation','referral_source','status'],'columns'=>['customer_code'=>'کد','full_name'=>'نام کامل','mobile'=>'موبایل','status'=>'وضعیت','segment'=>'بخش','last_visit'=>'آخرین مراجعه','total_spent'=>'کل خرید'],'fields'=>[
  'first_name'=>['نام','text','required'],'last_name'=>['نام خانوادگی','text','required'],'mobile'=>['موبایل','text','required'],'alternative_phone'=>['تلفن جایگزین','text'],'email'=>['ایمیل','email'],'gender'=>['جنسیت','select',['female'=>'خانم','male'=>'آقا','other'=>'سایر']],'birth_date'=>['تاریخ تولد','date'],'occupation'=>['شغل','text'],'address'=>['آدرس','textarea'],'referral_source'=>['منبع معرفی','text'],'referred_by_customer_id'=>['معرف مشتری','customer'],'status'=>['وضعیت','select',['new'=>'جدید','active'=>'فعال','vip'=>'VIP','inactive'=>'غیرفعال','at_risk'=>'در معرض ریزش','lost'=>'از دست رفته']],'tags'=>['برچسب‌ها','text'],'followup_interval_days'=>['بازه پیگیری اختصاصی (روز)','number'],'emergency_contact'=>['تماس اضطراری','text'],'consent_preferences'=>['رضایت‌نامه/ترجیحات','textarea'],'notes'=>['یادداشت','textarea']]],
 'services'=>['title'=>'خدمات ماساژ','table'=>'services','perm'=>'services','search'=>['name','description'],'columns'=>['name'=>'نام','default_price'=>'قیمت','duration_minutes'=>'مدت','commission_percentage'=>'پورسانت٪','status'=>'وضعیت'],'fields'=>['name'=>['نام خدمت','text','required'],'description'=>['توضیحات','textarea'],'default_price'=>['قیمت پیش‌فرض','number','required'],'duration_minutes'=>['مدت (دقیقه)','number','required'],'commission_percentage'=>['پورسانت پیش‌فرض٪','number'],'fixed_commission'=>['پورسانت ثابت','number'],'followup_interval_days'=>['بازه پیگیری (روز)','number'],'status'=>['وضعیت','select',['active'=>'فعال','inactive'=>'غیرفعال']]]],
 'therapists'=>['title'=>'درمانگران','table'=>'therapists','perm'=>'therapists','search'=>['name','code','phone','skills'],'columns'=>['code'=>'کد','name'=>'نام','phone'=>'تلفن','salary_model'=>'مدل حقوق','status'=>'وضعیت'],'fields'=>['name'=>['نام','text','required'],'code'=>['کد درمانگر','text','required'],'phone'=>['تلفن','text'],'email'=>['ایمیل','email'],'address'=>['آدرس','textarea'],'hire_date'=>['تاریخ استخدام','date'],'skills'=>['مهارت‌ها','textarea'],'service_ids'=>['خدمات قابل انجام','services_multi'],'salary_model'=>['مدل حقوق','select',['fixed_salary'=>'حقوق ثابت','percentage'=>'درصدی','fixed_per_session'=>'مبلغ ثابت هر جلسه','base_plus_percentage'=>'پایه + درصد','base_plus_fixed'=>'پایه + ثابت هر جلسه']],'base_salary'=>['حقوق پایه','number'],'commission_percentage'=>['پورسانت٪','number'],'fixed_commission'=>['پورسانت ثابت','number'],'working_schedule'=>['برنامه کاری','textarea'],'status'=>['وضعیت','select',['active'=>'فعال','inactive'=>'غیرفعال']],'notes'=>['یادداشت','textarea']]],
 'appointments'=>['title'=>'نوبت‌ها','table'=>'appointments','perm'=>'appointments','search'=>['status','notes'],'columns'=>['appointment_date'=>'تاریخ','start_time'=>'شروع','customer_name'=>'مشتری','therapist_name'=>'درمانگر','service_name'=>'خدمت','status'=>'وضعیت'],'fields'=>['customer_id'=>['مشتری','customer','required'],'therapist_id'=>['درمانگر','therapist','required'],'service_id'=>['خدمت','service','required'],'appointment_date'=>['تاریخ','date','required'],'start_time'=>['ساعت شروع','time','required'],'end_time'=>['ساعت پایان','time','required'],'status'=>['وضعیت','select',['pending'=>'در انتظار','confirmed'=>'تأیید شده','arrived'=>'حاضر شده','in_progress'=>'در حال انجام','completed'=>'تکمیل شده','cancelled'=>'لغو شده','no_show'=>'عدم مراجعه']],'notes'=>['یادداشت','textarea']]],
 'sessions'=>['title'=>'جلسات ماساژ','table'=>'massage_sessions','perm'=>'sessions','search'=>['payment_status','payment_method','customer_feedback','therapist_notes'],'columns'=>['massage_date'=>'تاریخ','customer_name'=>'مشتری','therapist_name'=>'درمانگر','service_name'=>'خدمت','final_amount'=>'مبلغ نهایی','payment_status'=>'پرداخت','satisfaction_score'=>'رضایت'],'fields'=>['customer_id'=>['مشتری','customer','required'],'therapist_id'=>['درمانگر','therapist','required'],'service_id'=>['خدمت','service','required'],'appointment_id'=>['نوبت مرتبط','appointment'],'massage_date'=>['تاریخ ماساژ','date','required'],'start_time'=>['شروع','time'],'end_time'=>['پایان','time'],'duration_minutes'=>['مدت','number'],'price'=>['قیمت','number','required'],'discount'=>['تخفیف','number'],'final_amount'=>['مبلغ نهایی','number','required'],'payment_method'=>['روش پرداخت','select',['cash'=>'نقدی','card'=>'کارتخوان','transfer'=>'انتقال بانکی','online'=>'آنلاین','other'=>'سایر']],'payment_status'=>['وضعیت پرداخت','select',['paid'=>'پرداخت شده','partial'=>'بخشی','unpaid'=>'پرداخت نشده']],'status'=>['وضعیت','select',['completed'=>'تکمیل شده','cancelled'=>'لغو شده']],'satisfaction_score'=>['امتیاز رضایت ۱ تا ۵','number'],'customer_feedback'=>['بازخورد مشتری','textarea'],'therapist_notes'=>['یادداشت درمانگر','textarea'],'recommended_next_visit_date'=>['مراجعه پیشنهادی بعدی','date'],'followup_status'=>['وضعیت پیگیری','text'],'additional_services'=>['خدمات اضافه','textarea'],'internal_notes'=>['یادداشت داخلی','textarea']]],
 'expenses'=>['title'=>'هزینه‌ها','table'=>'expenses','perm'=>'expenses','search'=>['title','category','description'],'columns'=>['expense_date'=>'تاریخ','title'=>'عنوان','category'=>'دسته','amount'=>'مبلغ','payment_method'=>'روش پرداخت'],'fields'=>['title'=>['عنوان','text','required'],'category'=>['دسته‌بندی','text','required'],'amount'=>['مبلغ','number','required'],'expense_date'=>['تاریخ','date','required'],'payment_method'=>['روش پرداخت','select',['cash'=>'نقدی','card'=>'کارتخوان','transfer'=>'انتقال بانکی','online'=>'آنلاین','other'=>'سایر']],'description'=>['شرح','textarea']]],
 'inventory'=>['title'=>'انبار و ملزومات','table'=>'inventory_items','perm'=>'inventory','search'=>['name','category'],'columns'=>['name'=>'کالا','category'=>'دسته','quantity'=>'موجودی','minimum_quantity'=>'حداقل','unit'=>'واحد','status'=>'وضعیت'],'fields'=>['name'=>['نام کالا','text','required'],'category'=>['دسته','text'],'quantity'=>['موجودی','number'],'minimum_quantity'=>['حداقل هشدار','number'],'unit'=>['واحد','text'],'status'=>['وضعیت','select',['active'=>'فعال','inactive'=>'غیرفعال']]]],
 'packages'=>['title'=>'پکیج‌ها و عضویت','table'=>'customer_packages','perm'=>'packages','search'=>['title','payment_status'],'columns'=>['customer_name'=>'مشتری','title'=>'عنوان','total_sessions'=>'کل','used_sessions'=>'استفاده شده','expires_at'=>'انقضا','payment_status'=>'پرداخت'],'fields'=>['customer_id'=>['مشتری','customer','required'],'title'=>['عنوان پکیج','text','required'],'total_sessions'=>['تعداد کل جلسات','number','required'],'used_sessions'=>['جلسات استفاده شده','number'],'price'=>['قیمت','number'],'payment_status'=>['وضعیت پرداخت','select',['paid'=>'پرداخت شده','partial'=>'بخشی','unpaid'=>'پرداخت نشده']],'starts_at'=>['شروع','date'],'expires_at'=>['انقضا','date'],'status'=>['وضعیت','select',['active'=>'فعال','expired'=>'منقضی','cancelled'=>'لغو شده']]]],
 'campaigns'=>['title'=>'کمپین‌های بازاریابی','table'=>'campaigns','perm'=>'campaigns','search'=>['name','segment','channel'],'columns'=>['name'=>'نام','segment'=>'گروه هدف','channel'=>'کانال','status'=>'وضعیت','starts_at'=>'شروع'],'fields'=>['name'=>['نام کمپین','text','required'],'segment'=>['گروه هدف','text'],'channel'=>['کانال','select',['sms'=>'پیامک','whatsapp'=>'واتساپ','email'=>'ایمیل','telegram'=>'تلگرام','phone'=>'تماس تلفنی']],'message'=>['پیام','textarea'],'starts_at'=>['شروع','date'],'ends_at'=>['پایان','date'],'status'=>['وضعیت','select',['draft'=>'پیش‌نویس','scheduled'=>'زمان‌بندی شده','sent'=>'ارسال شده','cancelled'=>'لغو شده']]]],
];
