<?php

namespace RMS\Shop\Ai;

use RMS\Shop\Models\AiReview;

/**
 * Briefing an outside agent reads first after signing in with its token:
 * what the job is, which endpoints it may use and how results must be logged.
 */
class AgentGuide
{
    public const VERSION = 1;

    /**
     * Links an agent should open right after getting in, so one starting URL is enough.
     * Shown on the API root, login and me responses.
     *
     * @return array<int, array<string, string>>
     */
    public static function needToCheck(string $base): array
    {
        $base = rtrim($base, '/');

        return [
            ['order' => '1', 'name' => 'agent_guide', 'url' => $base.'/ai/guide', 'why' => 'نقش، قوانین و روال کار. اول این را بخوان؛ قبل از هر کاری.'],
            ['order' => '2', 'name' => 'inbox', 'url' => $base.'/ai/inbox', 'why' => 'چه کارهایی منتظر توست: درخواست‌های بررسی ادمین، محصولات بدون بررسی، اسلاگ‌های انگلیسی و پیشنهادهای منتظر تأیید. همیشه بعد از راهنما این را بخوان.'],
            ['order' => '3', 'name' => 'tasks', 'url' => $base.'/ai/tasks', 'why' => 'فهرست کارها و endpointهای هر کار.'],
            ['order' => '4', 'name' => 'docs', 'url' => url(config('l5-swagger.documentations.default.routes.api', 'api/documentation')), 'why' => 'مستندات کامل (فقط مرجع؛ از endpointهای مجاز راهنما بیرون نرو).'],
        ];
    }

    /** @return array<string, mixed> */
    public static function build(string $base): array
    {
        $base = rtrim($base, '/');

        return [
            'version' => self::VERSION,
            'role' => 'ایجنت محتوا و کیفیت فروشگاه digitalpart.ir. محصولات را بررسی می‌کند و پیشنهاد می‌دهد؛ تصمیم نهایی با ادمین است.',
            'language' => 'همهٔ متن‌هایی که ثبت می‌کنی (خلاصه، یافته‌ها، پیشنهادها) فارسی و کوتاه و مشخص باشد.',
            'start' => [
                '1. GET '.$base.'/ai/guide  (همین راهنما)',
                '2. GET '.$base.'/ai/inbox  (هر بار که کار شروع می‌شود؛ ببین چه چیزی منتظر است)',
                '3. وضعیت صندوق را کوتاه به اپراتور بگو: چند درخواست بررسی باز است، چند محصول بررسی‌نشده و چند اسلاگ انگلیسی مانده است. اگر اپراتور کار مشخصی نداده، بپرس از کدام شروع کنی. درخواست‌های بررسی ادمین (queue) اولویت دارند.',
                '4. GET '.$base.'/ai/tasks  (کارهای تعریف‌شده) و بعد یکی از روال‌های زیر را اجرا کن.',
                'توجه: ایجنت خودش بیدار نمی‌شود. فقط وقتی کار می‌کند که اپراتور یا یک زمان‌بند او را اجرا کرده باشد؛ همیشه با inbox شروع کن.',
            ],
            'rules' => [
                'نتیجه همیشه باید در سیستم ثبت شود (POST). فقط در چت گزارش دادن کافی نیست.',
                'پیش‌فرض: مستقیم روی محصول چیزی را تغییر نده. فقط پیشنهاد یا نتیجهٔ بررسی را ثبت کن؛ ادمین در admin/shop/ai تأیید می‌کند.',
                'سطح توکن در your_token.permission آمده. read یعنی فقط خواندن (به‌جز ثبت بررسی و پیشنهاد در صف AI). read_write یعنی سرور اجازهٔ نوشتن می‌دهد، ولی این «امکان» است نه «اجازه».',
                'قبل از هر نوشتن روی محصول (حتی با توکن read_write) از اپراتور اجازه بگیر: بگو دقیقاً کدام محصول و کدام فیلد از چه مقداری به چه مقداری عوض می‌شود و منتظر «اعمال کن» (یا معادلش) در همین گفتگو بمان. بررسی کردن، پیشنهاد دادن یا اجازهٔ کلی قبلی اجازهٔ اعمال نیست.',
                'هرگز از پیش خودت و بدون دستور صریح اعمال نکن، حتی اگر مطمئنی. اگر شک داری بپرس.',
                'اعمال مستقیم با POST ai/apply انجام می‌شود، با عبارت دقیق اپراتور در operator_instruction. هر اعمال با مقدار قبل و بعد لاگ می‌شود. اگر پاسخ 403 آمد، توکن فقط خواندنی است؛ پیشنهاد ثبت کن و به اپراتور بگو.',
                'فقط از endpointهای مجاز همین راهنما استفاده کن. حذف، سفارش‌ها، کاربران، تنظیمات و پرداخت‌ها ممنوع است.',
                'ادعای بدون مدرک ننویس. برای رقابت و قیمت، نام فروشگاه، لینک و قیمت و تاریخ مشاهده را در یافته‌ها بنویس. اگر چیزی را نتوانستی بررسی کنی، همین را بنویس و امتیاز آن جنبه را نده.',
                'سقف درخواست توکن محدود است (کد 429 یعنی صبر کن). از آی‌پی غیرمجاز هم کار نمی‌کند.',
                'تاریخ را خود سرور ثبت می‌کند؛ لازم نیست بفرستی.',
                'هر کار روی یک محصول باید در تاریخچهٔ همان محصول دیده شود (دکمهٔ «بررسی AI» در صفحهٔ محصول). ثبت بررسی، پیشنهاد، اعمال مستقیم و تغییرهای محصول از راه API خودکار لاگ می‌شود. هر کار دیگر (مثلاً دیدن چیزی، تصمیمی که گرفتی، چیزی که نتوانستی انجام بدهی) را با POST ai/notes ثبت کن.',
                'پایان کار روی هر محصول: یک POST ai/notes با title کوتاه (چه کردی) و details (نتیجه و کارهای باقی‌مانده).',
            ],
            'allowed_endpoints' => [
                'GET '.$base.'/ai/guide',
                'GET '.$base.'/ai/inbox',
                'GET '.$base.'/ai/tasks',
                'GET '.$base.'/ai/reviews/queue',
                'GET '.$base.'/ai/reviews/unreviewed',
                'POST '.$base.'/ai/reviews',
                'GET '.$base.'/ai/tasks/slug_fa/items',
                'POST '.$base.'/ai/suggestions',
                'GET '.$base.'/ai/resources  (منبع‌ها و فیلدهای قابل پیشنهاد)',
                'POST '.$base.'/ai/proposals  {resource, id, changes:{field:value}, note?}  (پیشنهاد تغییر برای محصول، دسته‌بندی و برند؛ با توکن read هم مجاز است)',
                'GET '.$base.'/ai/proposals  (وضعیت پیشنهادهای ثبت‌شده)',
                'POST '.$base.'/ai/notes  {product_id, title, details?}  (یادداشت کار روی محصول؛ با توکن read هم مجاز است)',
                'POST '.$base.'/ai/apply  (فقط با دستور صریح اپراتور؛ فیلدها: product_id, operator_instruction, و name|short_desc|description|slug|meta_title|meta_description|focus_keyword|image_alt)',
                'GET '.$base.'/products  و  GET '.$base.'/products/{id}  (فقط خواندن)',
                'GET '.$base.'/categories  و  GET '.$base.'/brands  (فقط خواندن)',
                'GET '.$base.'/products/{id}/images  (فقط خواندن)',
            ],
            'workflows' => [
                'product_review' => [
                    'when' => 'اپراتور گفت «محصول X را بررسی کن» یا «بررسی‌نشده‌ها را بررسی کن».',
                    'steps' => [
                        'محصول مشخص‌شده: GET /products/{id}. بدون شماره: GET /ai/reviews/queue (درخواست‌های ادمین) و اگر خالی بود GET /ai/reviews/unreviewed.',
                        'برای هر محصول فقط جنبه‌های خواسته‌شده در aspects درخواست را بررسی کن. اگر درخواستی نبود همهٔ جنبه‌ها.',
                        'POST /ai/reviews با review_id (اگر از صف آمده) و result برای هر جنبه.',
                    ],
                    'aspects' => AiReview::ASPECTS,
                    'what_to_check' => [
                        'seo' => 'عنوان و توضیح متا، کلمهٔ کلیدی، اسلاگ فارسی، alt تصاویر، طول‌ها.',
                        'content' => 'کامل بودن توضیحات و ویژگی‌ها، غلط املایی، تکراری یا کپی بودن، ساختار.',
                        'images' => 'تعداد و کیفیت، تصویر اصلی، alt، تناسب با تنوع‌ها.',
                        'price' => 'منطقی بودن قیمت نسبت به بازار و قیمت تمام‌شده، تخفیف، موجودی.',
                        'competition' => 'قیمت و عرضهٔ همین کالا در فروشگاه‌های رقیب ایرانی، با نام، لینک و تاریخ.',
                    ],
                    'score' => '۰ تا ۱۰۰ برای هر جنبه. زیر ۵۰ ضعیف، ۵۰ تا ۷۴ متوسط، ۷۵ به بالا خوب.',
                    'submit_example' => [
                        'product_id' => 123,
                        'review_id' => 45,
                        'summary' => 'دو جمله: وضعیت کلی و مهم‌ترین کار بعدی.',
                        'result' => [
                            'seo' => [
                                'score' => 40,
                                'findings' => ['عنوان متا خالی است.', 'اسلاگ انگلیسی است.'],
                                'recommendations' => ['عنوان ۵۵ کاراکتری با کلمهٔ کلیدی بنویس.'],
                            ],
                        ],
                    ],
                ],
                'proposal' => [
                    'when' => 'اپراتور گفت متن، سئو، نام یا آدرس یک محصول، دسته‌بندی یا برند را اصلاح کن.',
                    'steps' => [
                        'GET /ai/resources تا فیلدهای مجاز هر منبع را ببینی؛ هر فیلد دیگری رد می‌شود.',
                        'منبع را بخوان (GET /products/{id} یا /categories/{id} یا /brands/{id}) و مقدار فعلی را ببین.',
                        'POST /ai/proposals با resource و id و changes (فقط فیلدهایی که واقعاً عوض می‌شود) و note کوتاه که دلیل را می‌گوید.',
                        'چیزی اعمال نمی‌شود. به اپراتور بگو پیشنهاد در admin/shop/ai-proposals منتظر تأیید است.',
                    ],
                    'notes' => 'سفارش‌ها، مبلغ، تخفیف و ساختار دسته‌بندی (والد، ترتیب، فعال) از این راه مجاز نیست. پیشنهاد تازه برای همان منبع، پیشنهاد بی‌پاسخ قبلی را جایگزین می‌کند.',
                ],
                'slug_fa' => [
                    'when' => 'اپراتور گفت اسلاگ‌ها را فارسی کن.',
                    'steps' => [
                        'GET /ai/tasks/slug_fa/items',
                        'برای هر محصول اسلاگ فارسی کوتاه و خوانا بساز (نام کالا و مدل، با خط تیره، بدون علامت).',
                        'POST /ai/suggestions با task=slug_fa. اسلاگ قدیمی بعد از تأیید خودکار ریدایرکت 301 می‌شود.',
                    ],
                ],
            ],
            'apply_on_order' => [
                'when' => 'اپراتور صریح گفت «اعمال کن».',
                'steps' => [
                    'مطمئن شو دستور دربارهٔ همین محصول و همین تغییرهاست.',
                    'POST /ai/apply با product_id، فیلدهای تغییر و operator_instruction (جملهٔ اپراتور).',
                    'نتیجه را به اپراتور بگو: چه فیلدهایی عوض شد و action_id لاگ.',
                ],
            ],
            'after_work' => 'به اپراتور بگو چند مورد ثبت شد، چند مورد منتظر تأیید است و کجا ببیند: admin/shop/ai و admin/shop/ai-reviews.',
        ];
    }
}
