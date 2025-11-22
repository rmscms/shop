<div class="modal fade" id="systemRequirementsModal" tabindex="-1" aria-labelledby="systemRequirementsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="systemRequirementsModalLabel">
                    <i class="ph-info-circle me-2"></i>
                    مستندات و نیازمندی‌های سیستم - پکیج Shop
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Overall Status -->
                <div class="alert alert-{{ $overallStatus['status_color'] }} border-start border-{{ $overallStatus['status_color'] }} border-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-fill">
                            <h6 class="alert-heading mb-1">
                                <i class="ph-shield-check me-1"></i>
                                {{ $overallStatus['status_text'] }}
                            </h6>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-{{ $overallStatus['status_color'] }}" 
                                     role="progressbar" 
                                     style="width: {{ $overallStatus['percentage'] }}%"
                                     aria-valuenow="{{ $overallStatus['percentage'] }}" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="d-block mt-2 text-muted">
                                {{ $overallStatus['passed'] }} از {{ $overallStatus['total'] }} مورد بررسی موفق ({{ $overallStatus['percentage'] }}%)
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-3" id="requirementsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="ph-info me-1"></i>
                            خلاصه
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="php-tab" data-bs-toggle="tab" data-bs-target="#php" type="button" role="tab">
                            <i class="ph-code me-1"></i>
                            PHP
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="extensions-tab" data-bs-toggle="tab" data-bs-target="#extensions" type="button" role="tab">
                            <i class="ph-puzzle-piece me-1"></i>
                            افزونه‌ها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="avif-tab" data-bs-toggle="tab" data-bs-target="#avif" type="button" role="tab">
                            <i class="ph-image me-1"></i>
                            AVIF
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="video-tab" data-bs-toggle="tab" data-bs-target="#video" type="button" role="tab">
                            <i class="ph-video-camera me-1"></i>
                            ویدیو
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="directories-tab" data-bs-toggle="tab" data-bs-target="#directories" type="button" role="tab">
                            <i class="ph-folder-open me-1"></i>
                            پوشه‌ها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes" type="button" role="tab">
                            <i class="ph-signpost me-1"></i>
                            مسیرها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="queues-tab" data-bs-toggle="tab" data-bs-target="#queues" type="button" role="tab">
                            <i class="ph-queue me-1"></i>
                            صف‌ها
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="api-tab" data-bs-toggle="tab" data-bs-target="#api" type="button" role="tab">
                            <i class="ph-plug me-1"></i>
                            API و تست
                        </button>
                    </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="plugins-tab" data-bs-toggle="tab" data-bs-target="#plugins" type="button" role="tab">
                                <i class="ph-puzzle-piece me-1"></i>
                                پلاگین‌ها
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="sidebar-tab" data-bs-toggle="tab" data-bs-target="#sidebar" type="button" role="tab">
                                <i class="ph-sidebar me-1"></i>
                                Sidebar
                            </button>
                        </li>
                </ul>

                <div class="tab-content" id="requirementsTabsContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-list-checks me-1"></i>
                                    خلاصه نیازمندی‌ها
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <h6 class="text-success">
                                            <i class="ph-check-circle me-1"></i>
                                            موارد نصب شده
                                        </h6>
                                        <ul class="list-unstyled mb-0">
                                            @if($requirements['php']['status'])
                                                <li class="mb-2">
                                                    <i class="ph-check text-success me-1"></i>
                                                    PHP {{ $requirements['php']['current'] }}
                                                </li>
                                            @endif
                                            
                                            @foreach($requirements['extensions'] as $ext => $info)
                                                @if($info['status'])
                                                    <li class="mb-2">
                                                        <i class="ph-check text-success me-1"></i>
                                                        {{ $info['name'] }}
                                                    </li>
                                                @endif
                                            @endforeach
                                            
                                            @if($requirements['imagemagick']['status'])
                                                <li class="mb-2">
                                                    <i class="ph-check text-success me-1"></i>
                                                    ImageMagick
                                                    @if($requirements['imagemagick']['avif_support'])
                                                        <span class="badge bg-success">AVIF ✓</span>
                                                    @endif
                                                </li>
                                            @endif
                                            
                                            @if($requirements['ffmpeg']['status'])
                                                <li class="mb-2">
                                                    <i class="ph-check text-success me-1"></i>
                                                    FFmpeg {{ $requirements['ffmpeg']['version'] }}
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        @if(!empty($overallStatus['critical_issues']) || !empty($overallStatus['warnings']))
                                            @if(!empty($overallStatus['critical_issues']))
                                                <h6 class="text-danger">
                                                    <i class="ph-warning-circle me-1"></i>
                                                    مشکلات حیاتی
                                                </h6>
                                                <ul class="list-unstyled mb-3">
                                                    @foreach($overallStatus['critical_issues'] as $issue)
                                                        <li class="mb-2">
                                                            <i class="ph-x text-danger me-1"></i>
                                                            {{ $issue }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            
                                            @if(!empty($overallStatus['warnings']))
                                                <h6 class="text-warning">
                                                    <i class="ph-warning me-1"></i>
                                                    هشدارها (اختیاری)
                                                </h6>
                                                <ul class="list-unstyled mb-0">
                                                    @foreach($overallStatus['warnings'] as $warning)
                                                        <li class="mb-2">
                                                            <i class="ph-warning text-warning me-1"></i>
                                                            {{ $warning }}
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        @else
                                            <div class="text-center py-4">
                                                <i class="ph-check-circle display-4 text-success mb-3 d-block"></i>
                                                <h5 class="text-success">همه چیز آماده است!</h5>
                                                <p class="text-muted">تمام نیازمندی‌ها برآورده شده است</p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PHP Tab -->
                    <div class="tab-pane fade" id="php" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-code me-1"></i>
                                    نسخه PHP
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-{{ $requirements['php']['status'] ? 'success' : 'danger' }}">
                                    <h6 class="alert-heading">
                                        <i class="ph-{{ $requirements['php']['status'] ? 'check-circle' : 'x-circle' }} me-1"></i>
                                        {{ $requirements['php']['message'] }}
                                    </h6>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>نسخه فعلی:</strong> {{ $requirements['php']['current'] }}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>نسخه مورد نیاز:</strong> {{ $requirements['php']['required'] }}
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-info me-1"></i>
                                            توضیحات
                                        </h6>
                                        <p class="mb-2">این پکیج برای اجرا به PHP نسخه 8.2 یا بالاتر نیاز دارد.</p>
                                        <p class="mb-0"><strong>دلایل:</strong></p>
                                        <ul class="mb-0">
                                            <li>پشتیبانی از Type Hints پیشرفته</li>
                                            <li>بهبود Performance</li>
                                            <li>امنیت بیشتر</li>
                                            <li>سازگاری با Laravel 11+</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extensions Tab -->
                    <div class="tab-pane fade" id="extensions" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-puzzle-piece me-1"></i>
                                    افزونه‌های PHP
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="40">وضعیت</th>
                                                <th>نام افزونه</th>
                                                <th>توضیحات</th>
                                                <th width="100">الزامی</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($requirements['extensions'] as $ext => $info)
                                                <tr class="{{ $info['status'] ? 'table-success' : ($info['required'] ? 'table-danger' : 'table-warning') }}">
                                                    <td class="text-center">
                                                        <i class="ph-{{ $info['status'] ? 'check-circle text-success' : 'x-circle text-' . ($info['required'] ? 'danger' : 'warning') }}"></i>
                                                    </td>
                                                    <td>
                                                        <strong>{{ $info['name'] }}</strong>
                                                        <br>
                                                        <small class="text-muted">php-{{ $ext }}</small>
                                                    </td>
                                                    <td>{{ $info['description'] }}</td>
                                                    <td>
                                                        @if($info['required'])
                                                            <span class="badge bg-danger">الزامی</span>
                                                        @else
                                                            <span class="badge bg-secondary">اختیاری</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="alert alert-info mt-3">
                                    <h6 class="alert-heading">
                                        <i class="ph-lightbulb me-1"></i>
                                        نحوه نصب
                                    </h6>
                                <p class="mb-2"><strong>Ubuntu/Debian:</strong></p>
                                <pre><code class="language-bash">sudo apt-get install php8.2-gd php8.2-imagick php8.2-mbstring php8.2-redis</code></pre>
                                
                                <p class="mb-2 mt-3"><strong>Windows (Laragon/XAMPP):</strong></p>
                                <p class="mb-0">در فایل <code>php.ini</code> خطوط زیر را Uncomment کنید:</p>
                                <pre><code class="language-ini">extension=gd
extension=imagick
extension=mbstring
extension=fileinfo</code></pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AVIF Tab -->
                    <div class="tab-pane fade" id="avif" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-image me-1"></i>
                                    پشتیبانی AVIF
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-{{ $requirements['imagemagick']['avif_support'] ? 'success' : 'danger' }}">
                                    <h6 class="alert-heading">
                                        <i class="ph-{{ $requirements['imagemagick']['avif_support'] ? 'check-circle' : 'x-circle' }} me-1"></i>
                                        {{ $requirements['imagemagick']['message'] }}
                                    </h6>
                                    @if($requirements['imagemagick']['status'])
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>نسخه ImageMagick:</strong> {{ $requirements['imagemagick']['version'] ?? 'نامشخص' }}
                                            </div>
                                            <div class="col-md-6">
                                                <strong>پشتیبانی AVIF:</strong> 
                                                @if($requirements['imagemagick']['avif_support'])
                                                    <span class="badge bg-success">فعال</span>
                                                @else
                                                    <span class="badge bg-danger">غیرفعال</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-info me-1"></i>
                                            درباره AVIF
                                        </h6>
                                        <p class="mb-2">AVIF فرمت تصویری مدرن است که:</p>
                                        <ul class="mb-3">
                                            <li>کیفیت بالاتر با حجم کمتر (تا 50% کوچکتر از JPEG)</li>
                                            <li>پشتیبانی از شفافیت (مانند PNG)</li>
                                            <li>پشتیبانی از HDR</li>
                                            <li>سرعت بارگذاری بیشتر</li>
                                        </ul>
                                        
                                        <h6 class="mt-3">
                                            <i class="ph-gear me-1"></i>
                                            نیازمندی‌ها
                                        </h6>
                                        <ul class="mb-3">
                                            <li><strong>ImageMagick:</strong> نسخه 7.0.0 یا بالاتر با libheif</li>
                                            <li><strong>PHP Extension:</strong> php-imagick نسخه 3.5.0 یا بالاتر</li>
                                            <li><strong>libheif:</strong> کتابخانه برای encode/decode AVIF</li>
                                        </ul>

                                        @if(!$requirements['imagemagick']['avif_support'])
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading">
                                                    <i class="ph-wrench me-1"></i>
                                                    نحوه فعال‌سازی AVIF
                                                </h6>
                                                
                                                <p class="mb-2"><strong>Ubuntu 22.04+:</strong></p>
                                                <pre><code class="language-bash"># نصب ImageMagick با پشتیبانی AVIF
sudo apt-get update
sudo apt-get install libheif-dev libheif1
sudo apt-get install imagemagick libmagickwand-dev
sudo pecl install imagick</code></pre>

                                                <p class="mb-2 mt-3"><strong>بررسی پشتیبانی AVIF:</strong></p>
                                                <pre><code class="language-bash">convert -list format | grep AVIF</code></pre>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if($requirements['imagemagick']['status'])
                                    <div class="card bg-light mt-3">
                                        <div class="card-body">
                                            <h6>
                                                <i class="ph-gear me-1"></i>
                                                عملکرد در پکیج
                                            </h6>
                                            <p class="mb-0">پکیج Shop از Job <code>ConvertImageToAvif</code> برای تبدیل خودکار تصاویر استفاده می‌کند:</p>
                                            <ul class="mb-0 mt-2">
                                                <li>هنگام آپلود تصویر، فایل اصلی در <code>/orig</code> ذخیره می‌شود</li>
                                                <li>یک Job برای تبدیل به AVIF در صف قرار می‌گیرد</li>
                                                <li>تصویر AVIF با کیفیت 85% ساخته می‌شود</li>
                                                <li>مرورگرهای قدیمی از تصویر اصلی استفاده می‌کنند</li>
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Video Tab -->
                    <div class="tab-pane fade" id="video" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-video-camera me-1"></i>
                                    پشتیبانی از ویدیو
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-{{ $requirements['ffmpeg']['status'] ? 'success' : 'warning' }}">
                                    <h6 class="alert-heading">
                                        <i class="ph-{{ $requirements['ffmpeg']['status'] ? 'check-circle' : 'warning' }} me-1"></i>
                                        {{ $requirements['ffmpeg']['message'] }}
                                    </h6>
                                    @if($requirements['ffmpeg']['status'])
                                        <hr>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p class="mb-0">
                                                    <strong>نسخه:</strong> {{ $requirements['ffmpeg']['version'] }}
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <p class="mb-0">
                                                    <strong>مسیر FFmpeg:</strong> 
                                                    <code>{{ $requirements['ffmpeg']['ffmpeg_path'] }}</code>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="row mt-2">
                                            <div class="col-md-12">
                                                <p class="mb-0">
                                                    <strong>مسیر FFprobe:</strong> 
                                                    <code>{{ $requirements['ffmpeg']['ffprobe_path'] }}</code>
                                                    @if($requirements['ffmpeg']['ffprobe_status'])
                                                        <span class="badge bg-success ms-2">فعال</span>
                                                    @else
                                                        <span class="badge bg-danger ms-2">یافت نشد</span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-info me-1"></i>
                                            درباره FFmpeg
                                        </h6>
                                        <p class="mb-2">FFmpeg ابزار قدرتمند برای پردازش ویدیو است که برای:</p>
                                        <ul class="mb-3">
                                            <li>تبدیل ویدیوها به فرمت HLS</li>
                                            <li>استخراج thumbnail از ویدیو</li>
                                            <li>فشرده‌سازی ویدیو</li>
                                            <li>تغییر resolution و bitrate</li>
                                        </ul>

                                        @if(!$requirements['ffmpeg']['status'])
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading">
                                                    <i class="ph-wrench me-1"></i>
                                                    نحوه نصب FFmpeg
                                                </h6>
                                                
                                                <p class="mb-2"><strong>Ubuntu/Debian:</strong></p>
                                                <pre><code class="language-bash">sudo apt-get update
sudo apt-get install ffmpeg</code></pre>

                                                <p class="mb-2 mt-3"><strong>Windows:</strong></p>
                                                <ol class="mb-2">
                                                    <li>دانلود از <a href="https://ffmpeg.org/download.html" target="_blank">ffmpeg.org</a></li>
                                                    <li>استخراج در <code>C:\ffmpeg</code></li>
                                                    <li>اضافه کردن <code>C:\ffmpeg\bin</code> به PATH</li>
                                                </ol>

                                                <p class="mb-2 mt-3"><strong>بررسی نصب:</strong></p>
                                                <pre><code class="language-bash">ffmpeg -version</code></pre>
                                                
                                                <p class="mb-2 mt-3"><strong>تنظیم مسیر FFmpeg در <code>.env</code>:</strong></p>
                                                <p class="mb-2">اگر FFmpeg در PATH سیستم شما نیست، باید مسیر کامل آن را مشخص کنید:</p>
                                                
                                                <p class="mb-1"><strong>Linux/Mac:</strong></p>
                                                <pre><code class="language-bash">FFMPEG_PATH=/usr/bin/ffmpeg
FFPROBE_PATH=/usr/bin/ffprobe</code></pre>
                                                
                                                <p class="mb-1 mt-2"><strong>Windows:</strong></p>
                                                <pre><code class="language-bash">FFMPEG_PATH=C:\ffmpeg\bin\ffmpeg.exe
FFPROBE_PATH=C:\ffmpeg\bin\ffprobe.exe</code></pre>
                                                
                                                <p class="mb-1 mt-2"><strong>اگر در PATH است (توصیه می‌شود):</strong></p>
                                                <pre><code class="language-bash">FFMPEG_PATH=ffmpeg
FFPROBE_PATH=ffprobe</code></pre>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-play-circle me-1"></i>
                                            پشتیبانی HLS
                                        </h6>
                                        <p class="mb-2">پکیج Shop از پروتکل HLS برای پخش ویدیو استفاده می‌کند:</p>
                                        <ul class="mb-2">
                                            <li>Adaptive Bitrate Streaming</li>
                                            <li>پخش روان بدون بافر شدن</li>
                                            <li>پشتیبانی از تمام مرورگرها</li>
                                            <li>امنیت بیشتر (چانک شده)</li>
                                        </ul>
                                        <p class="mb-0 text-muted">
                                            <i class="ph-info me-1"></i>
                                            اگر FFmpeg نصب نباشد، ویدیوها به صورت مستقیم پخش می‌شوند
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Directories Tab -->
                    <div class="tab-pane fade" id="directories" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-folder-open me-1"></i>
                                    پوشه‌های مورد نیاز
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="40">وضعیت</th>
                                                <th>مسیر</th>
                                                <th>توضیحات</th>
                                                <th width="150">دسترسی</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($requirements['directories'] as $dir => $info)
                                                <tr class="{{ $info['status'] ? 'table-success' : 'table-danger' }}">
                                                    <td class="text-center">
                                                        <i class="ph-{{ $info['status'] ? 'check-circle text-success' : 'x-circle text-danger' }}"></i>
                                                    </td>
                                                    <td>
                                                        <code>{{ $dir }}</code>
                                                        <br>
                                                        <small class="text-muted">{{ $info['path'] }}</small>
                                                    </td>
                                                    <td>{{ $info['description'] }}</td>
                                                    <td>
                                                        @if($info['exists'])
                                                            <span class="badge bg-success">موجود</span>
                                                        @else
                                                            <span class="badge bg-danger">ناموجود</span>
                                                        @endif
                                                        
                                                        @if($info['exists'])
                                                            @if($info['writable'])
                                                                <span class="badge bg-success">قابل نوشتن</span>
                                                            @else
                                                                <span class="badge bg-danger">فقط خواندنی</span>
                                                            @endif
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <div class="alert alert-info mt-3">
                                    <h6 class="alert-heading">
                                        <i class="ph-wrench me-1"></i>
                                        رفع مشکلات دسترسی
                                    </h6>
                                    
                                    <p class="mb-2"><strong>Linux/Ubuntu:</strong></p>
                                    <pre><code class="language-bash"># ایجاد پوشه‌ها
php artisan storage:link

# تنظیم دسترسی
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage public/uploads</code></pre>

                                    <p class="mb-2 mt-3"><strong>Windows:</strong></p>
                                    <p class="mb-0">مطمئن شوید پوشه‌ها برای IIS_IUSRS یا Everyone قابل نوشتن هستند</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Routes Tab -->
                    <div class="tab-pane fade" id="routes" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-signpost me-1"></i>
                                    سفارشی‌سازی مسیرها
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">
                                        <i class="ph-info me-1"></i>
                                        مسیرهای پیش‌فرض
                                    </h6>
                                    <p class="mb-2">پکیج Shop مسیرهای زیر را ثبت می‌کند:</p>
                                    <ul class="mb-0">
                                        <li><code>/admin/shop/*</code> - پنل مدیریت</li>
                                        <li><code>/shop/*</code> - فروشگاه عمومی</li>
                                    </ul>
                                </div>

                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-code me-1"></i>
                                            Override کردن مسیرها
                                        </h6>
                                        <p class="mb-2">برای تغییر مسیرها، فایل <code>config/shop.php</code> را ویرایش کنید:</p>
                                        
                                        @php
                                        $phpCode = <<<'CODE'
<?php

return [
    'routes' => [
        'admin' => [
            'prefix' => 'admin/shop',    // تغییر prefix پنل ادمین
            'name' => 'admin.shop.',      // تغییر نام route
            'middleware' => ['web', 'auth:admin'],
        ],
        'shop' => [
            'prefix' => 'shop',           // تغییر prefix فروشگاه
            'name' => 'shop.',            // تغییر نام route
            'middleware' => ['web'],
        ],
    ],
];
CODE;
                                        @endphp
                                        <pre><code class="language-php">{{ $phpCode }}</code></pre>

                                        <p class="mb-2 mt-3"><strong>مثال: تغییر prefix به /store:</strong></p>
                                        <pre><code class="language-php">'shop' => [
    'prefix' => 'store',  // حالا مسیرها: /store/product/{slug}
    'name' => 'store.',   // حالا route names: store.product.show
],</code></pre>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-arrows-clockwise me-1"></i>
                                            Override کردن کنترلرها
                                        </h6>
                                        <p class="mb-2">برای override کردن یک کنترلر:</p>
                                        
                                        <p class="mb-2 mt-3"><strong>1. ایجاد کنترلر جدید:</strong></p>
                                        <pre><code class="language-php">&lt;?php
namespace App\Http\Controllers\Admin\Shop;

use RMS\Shop\Http\Controllers\Admin\ProductsController as BaseProductsController;

class ProductsController extends BaseProductsController
{
    // Override methods here
}</code></pre>

                                        <p class="mb-2 mt-3"><strong>2. ثبت route جدید در <code>routes/admin.php</code>:</strong></p>
                                        <pre><code class="language-bash">{{ "Route::resource('shop/products', App\Http\Controllers\Admin\Shop\ProductsController::class);" }}</code></pre>

                                        <div class="alert alert-warning mt-3 mb-0">
                                            <i class="ph-warning me-1"></i>
                                            <strong>نکته:</strong> Route جدید باید قبل از routes پکیج تعریف شود تا اولویت داشته باشد
                                        </div>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-eye me-1"></i>
                                            Override کردن Views
                                        </h6>
                                        <p class="mb-2">برای سفارشی‌سازی ظاهر:</p>
                                        
                                        <p class="mb-2 mt-3"><strong>1. Publish کردن views:</strong></p>
                                        <pre><code class="language-bash">php artisan vendor:publish --tag=shop-views</code></pre>

                                        <p class="mb-2 mt-3"><strong>2. ویرایش فایل‌ها در:</strong></p>
                                        <pre><code class="language-bash">resources/views/vendor/shop/</code></pre>

                                        <p class="mb-0 text-muted">
                                            <i class="ph-info me-1"></i>
                                            Laravel به صورت خودکار views در مسیر vendor/shop را قبل از views پکیج بررسی می‌کند
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queues Tab -->
                    <div class="tab-pane fade" id="queues" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-queue me-1"></i>
                                    مدیریت صف‌های پردازش
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">
                                        <i class="ph-info me-1"></i>
                                        چرا صف‌های جداگانه؟
                                    </h6>
                                    <p class="mb-2">برای عملکرد بهتر، پکیج Shop از 2 صف مجزا استفاده می‌کند:</p>
                                    <ul class="mb-0">
                                        <li><strong>shop-avif:</strong> تبدیل تصاویر به AVIF (سریع، اولویت بالا)</li>
                                        <li><strong>shop-media:</strong> پردازش ویدیو و تبدیل به HLS (کند، CPU intensive)</li>
                                    </ul>
                                </div>

                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-gear me-1"></i>
                                            تنظیمات صف‌ها در <code>.env</code>
                                        </h6>
                                        <pre><code class="language-bash"># Queue Configuration
SHOP_QUEUE_AVIF=shop-avif
SHOP_QUEUE_MEDIA=shop-media
SHOP_QUEUE_DEFAULT=default</code></pre>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-terminal me-1"></i>
                                            اجرای Workers در Development
                                        </h6>
                                        <p class="mb-2">برای تست، این دستورات را در ترمینال‌های جداگانه اجرا کنید:</p>
                                        
                                        <p class="mb-2 mt-3"><strong>1. Worker برای AVIF (اولویت بالا):</strong></p>
                                        <pre><code class="language-bash">php artisan queue:work --queue=shop-avif --tries=3 --timeout=300</code></pre>
                                        <small class="text-muted d-block mt-1">
                                            <i class="ph-info me-1"></i>
                                            3 تلاش مجدد، 5 دقیقه timeout
                                        </small>

                                        <p class="mb-2 mt-3"><strong>2. Worker برای Video (CPU intensive):</strong></p>
                                        <pre><code class="language-bash">php artisan queue:work --queue=shop-media --tries=2 --timeout=600</code></pre>
                                        <small class="text-muted d-block mt-1">
                                            <i class="ph-info me-1"></i>
                                            2 تلاش مجدد، 10 دقیقه timeout (برای ویدیوهای بزرگ)
                                        </small>

                                        <p class="mb-2 mt-3"><strong>3. Worker برای سایر کارها:</strong></p>
                                        <pre><code class="language-bash">php artisan queue:work --queue=default --tries=3 --timeout=60</code></pre>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-server me-1"></i>
                                            تنظیمات Production با Supervisor
                                        </h6>
                                        <p class="mb-2">برای production، از Supervisor استفاده کنید:</p>
                                        
                                        <p class="mb-2 mt-3"><strong>1. نصب Supervisor:</strong></p>
                                        <pre><code class="language-bash">sudo apt-get install supervisor</code></pre>

                                        <p class="mb-2 mt-3"><strong>2. ایجاد فایل کانفیگ <code>/etc/supervisor/conf.d/shop-workers.conf</code>:</strong></p>
                                        <pre class="language-bash" style="max-height: 400px; overflow-y: auto;"><code>[program:shop-avif-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=shop-avif --tries=3 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/shop-avif-worker.log
stopwaitsecs=3600

[program:shop-media-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=shop-media --tries=2 --timeout=600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/shop-media-worker.log
stopwaitsecs=3600

[program:shop-default-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --queue=default --tries=3 --timeout=60
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/shop-default-worker.log
stopwaitsecs=3600</code></pre>

                                        <p class="mb-2 mt-3"><strong>3. فعال‌سازی و اجرای Workers:</strong></p>
                                        <pre><code class="language-bash">sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start shop-avif-worker:*
sudo supervisorctl start shop-media-worker:*
sudo supervisorctl start shop-default-worker:*</code></pre>

                                        <p class="mb-2 mt-3"><strong>4. بررسی وضعیت:</strong></p>
                                        <pre><code class="language-bash">sudo supervisorctl status</code></pre>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-chart-line me-1"></i>
                                            مانیتورینگ صف‌ها
                                        </h6>
                                        <p class="mb-2">برای مشاهده وضعیت صف‌ها:</p>
                                        
                                        <p class="mb-2 mt-3"><strong>1. نمایش تعداد Job‌های در انتظار:</strong></p>
                                        <pre><code class="language-bash">{{ "php artisan queue:monitor shop-avif,shop-media,default" }}</code></pre>

                                        <p class="mb-2 mt-3"><strong>2. لیست Job‌های Failed:</strong></p>
                                        <pre><code class="language-bash">php artisan queue:failed</code></pre>

                                        <p class="mb-2 mt-3"><strong>3. اجرای مجدد Job‌های Failed:</strong></p>
                                        <pre><code class="language-bash"># اجرای مجدد یک Job
php artisan queue:retry {id}

# اجرای مجدد همه
php artisan queue:retry all</code></pre>

                                        <p class="mb-2 mt-3"><strong>4. پاک کردن Job‌های Failed:</strong></p>
                                        <pre><code class="language-bash">php artisan queue:flush</code></pre>
                                    </div>
                                </div>

                                <div class="alert alert-warning mt-3">
                                    <h6 class="alert-heading">
                                        <i class="ph-warning me-1"></i>
                                        نکات مهم
                                    </h6>
                                    <ul class="mb-0">
                                        <li><strong>AVIF Worker:</strong> 2 process توصیه می‌شود (سریع هستند)</li>
                                        <li><strong>Media Worker:</strong> 1 process کافی است (CPU intensive)</li>
                                        <li><strong>Timeout:</strong> برای ویدیوهای بزرگ، timeout را افزایش دهید</li>
                                        <li><strong>Memory:</strong> Worker های media به حافظه بیشتری نیاز دارند</li>
                                        <li><strong>Log Files:</strong> فایل‌های لاگ را به صورت دوره‌ای پاک کنید</li>
                                    </ul>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-database me-1"></i>
                                            تنظیمات Queue Driver
                                        </h6>
                                        <p class="mb-2">در فایل <code>.env</code> تنظیم کنید:</p>
                                        
                                        <p class="mb-2 mt-3"><strong>Redis (توصیه می‌شود):</strong></p>
                                        <pre><code class="language-bash">QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379</code></pre>

                                        <p class="mb-2 mt-3"><strong>Database (برای شروع):</strong></p>
                                        <pre><code class="language-bash">QUEUE_CONNECTION=database
# سپس اجرا کنید:
# php artisan queue:table
# php artisan migrate</code></pre>

                                        <div class="alert alert-info mt-3 mb-0">
                                            <i class="ph-lightbulb me-1"></i>
                                            <strong>توصیه:</strong> Redis سریع‌تر و کارآمدتر است، مخصوصاً برای پردازش تصاویر و ویدیوها
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- API & Tests Tab -->
                    <div class="tab-pane fade" id="api" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-plug me-1"></i>
                                    پیش‌نیازهای API پنل و تست‌ها
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-primary">
                                    <h6 class="alert-heading mb-2">
                                        <i class="ph-arrows-in-line-horizontal me-1"></i>
                                        معماری front و API
                                    </h6>
                                    <p class="mb-2">
                                        نسخه‌ی کاربری باید فقط از طریق API داده بگیرد. اگر front و API را هم‌زمان روی PHP built-in server اجرا کنید (یک پورت واحد)،
                                        request‌های داخلی گیر می‌کنند و <code>cURL error 28</code> می‌گیرید.
                                    </p>
                                    <ul class="mb-3">
                                        <li><strong>بهترین حالت:</strong> API روی پورت/هاست جدا (مثلاً <code>127.0.0.1:8001</code>) و front روی <code>127.0.0.1:8000</code>.</li>
                                        <li>در فایل <code>.env</code> پروژه‌ی front مقدار <code>SHOP_PANEL_API_URL</code> را روی آدرس API تنظیم کنید.</li>
                                        <li>پکیج Shop (در <code>config/shop.php</code>) فیلد <code>frontend_api.base_url</code> را همین مقدار می‌خواند.</li>
                                        <li>اگر از Nginx/Apache استفاده می‌کنید، هر سرویس را پشت VirtualHost خودش قرار دهید یا از Proxy پاس بدهید.</li>
                                    </ul>
                                    <p class="mb-0">نمونه تنظیمات:</p>
                                    <pre class="bg-dark text-white p-3 rounded mt-2">
APP_URL=http://127.0.0.1:8000
SHOP_PANEL_API_URL=http://127.0.0.1:8001/api/v1/panel
                                    </pre>
                                </div>

                                <div class="alert alert-info">
                                    <h6 class="alert-heading mb-2">
                                        <i class="ph-info me-1"></i>
                                        وابستگی‌های جدید
                                    </h6>
                                    <ul class="mb-0">
                                        <li>در ریشه پروژه (`rms2`) پکیج <code>laravel/sanctum ^4.2</code> باید نصب شود تا احراز هویت Panel API فعال شود.</li>
                                        <li>تنظیم <code>config/shop/panel_api.php</code> → مقدار <code>auth_guard</code> به صورت پیش‌فرض <code>sanctum</code> است و در صورت نیاز قابل تغییر است.</li>
                                        <li>برای محیط‌های تازه، دستور <code>php artisan migrate --path=vendor/laravel/sanctum/database/migrations</code> را اجرا کنید تا جدول‌های توکن ساخته شوند.</li>
                                    </ul>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6 class="mb-3">
                                            <i class="ph-lock me-1"></i>
                                            چک‌لیست فعال‌سازی لاگین Panel API
                                        </h6>
                                        <ol class="mb-0">
                                            <li class="mb-3">
                                                <strong>پکیج Sanctum</strong>  
                                                <pre><code class="language-bash">composer update laravel/sanctum</code></pre>
                                                <small class="text-muted">در پروژه اصلی (و هر consumer مانند <code>shop-test</code>) اجرا شود.</small>
                                            </li>
                                            <li class="mb-3">
                                                <strong>مایگریشن جدول توکن‌ها</strong>  
                                                <pre><code class="language-bash">php artisan migrate --path=vendor/laravel/sanctum/database/migrations</code></pre>
                                                <small class="text-muted">وجود جدول <code>personal_access_tokens</code> برای صدور توکن ضروری است.</small>
                                            </li>
                                            <li class="mb-3">
                                                <strong>مدل کاربر</strong>  
                                                <pre><code class="language-php">use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
}</code></pre>
                                                <small class="text-muted">همین مدل در <code>config/shop/panel_api.php → auth.user_model</code> استفاده می‌شود.</small>
                                            </li>
                                            <li class="mb-3">
                                                <strong>پیکربندی درایور</strong>  
                                                <pre><code class="language-php">'auth' => [
    'driver' => env('SHOP_PANEL_AUTH_DRIVER', 'email'),
    'device_name' => env('SHOP_PANEL_DEVICE_NAME', 'shop-panel'),
    'drivers' => [
        'email' => RMS\Shop\Support\PanelApi\Auth\EmailPasswordDriver::class,
        // 'otp' => App\PanelAuth\Drivers\OtpDriver::class,
    ],
];</code></pre>
                                                <small class="text-muted">با اضافه‌کردن Driver جدید می‌توانید OTP، موبایل یا 2FA را سفارشی کنید.</small>
                                            </li>
                                            <li class="mb-0">
                                                <strong>نمونه تنظیمات .env</strong>  
                                                <pre><code class="language-bash">SHOP_PANEL_AUTH_DRIVER=email
SHOP_PANEL_DEVICE_NAME=shop-web
SHOP_PANEL_USER_MODEL=App\Models\User</code></pre>
                                            </li>
                                        </ol>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6 class="mb-2">
                                            <i class="ph-list-checks me-1"></i>
                                            چک‌لیست سلامت API
                                        </h6>
                                        <ol class="mb-0">
                                            <li class="mb-2">
                                                <strong>اجرای تست‌های فیچر:</strong>
                                                <pre><code class="language-bash">php artisan test --filter=ShopPanelApiTest</code></pre>
                                                <small class="text-muted">این تست مسیرهای محصولات، سبد، لاگین، آدرس و سفارش‌ها را پوشش می‌دهد.</small>
                                            </li>
                                            <li class="mb-2">
                                                <strong>بازسازی Swagger:</strong>
                                                <pre><code class="language-bash">php artisan l5-swagger:generate
php artisan l5-swagger:publish</code></pre>
                                                <small class="text-muted">خروجی در <code>/api/documentation</code> و فایل JSON مشترک استفاده می‌شود.</small>
                                            </li>
                                            <li class="mb-0">
                                                <strong>رونوشت مستندات:</strong>
                                                <span class="text-muted">تغییرات و وابستگی‌ها در فایل <code>packages/rms/shop/docs/api-panel-roadmap.md</code> نگهداری می‌شوند؛ در صورت اعمال آپدیت حتماً این فایل را بروز کنید.</span>
                                            </li>
                                        </ol>
                                    </div>
                                </div>

                                <div class="alert alert-success mt-3 mb-0">
                                    <h6 class="alert-heading mb-1">
                                        <i class="ph-shield-check me-1"></i>
                                        وضعیت فعلی
                                    </h6>
                                    <p class="mb-0">در این نسخه، تمام تست‌های API پاس شده و Swagger آخرین وضعیت را نشان می‌دهد. هر بار پس از تغییر سرویس‌های API، این چک‌لیست را تکرار کنید.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Plugins Tab -->
                    <div class="tab-pane fade" id="plugins" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="ph-puzzle-piece me-1"></i>
                                    تنظیمات پلاگین‌های Shop
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <h6 class="alert-heading">
                                        <i class="ph-warning me-1"></i>
                                        مهم: ثبت پلاگین‌ها در RMS Core
                                    </h6>
                                    <p class="mb-2">
                                        پلاگین‌های Shop باید در فایل <code>config/plugins.php</code> پروژه شما ثبت شوند تا بتوانید از آن‌ها در کنترلرها استفاده کنید.
                                    </p>
                                    <p class="mb-0">
                                        <strong>⚠️ اگر فایل <code>config/plugins.php</code> در پروژه شما وجود ندارد، ابتدا آن را publish کنید (مرحله 1 در پایین).</strong>
                                    </p>
                                </div>

                                <h6 class="mt-3 mb-3">
                                    <i class="ph-copy me-1"></i>
                                    پلاگین‌های موجود در Shop Package
                                </h6>

                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-2">پس از publish کردن پلاگین‌ها، کدهای زیر را به فایل <code>config/plugins.php</code> اضافه کنید:</p>
                                        
                                        <div class="alert alert-info">
                                            <i class="ph-info me-1"></i>
                                            برای کپی کردن، روی دکمه Copy کلیک کنید
                                        </div>

                                        <div class="position-relative">
                                            <button type="button" class="btn btn-sm btn-primary position-absolute top-0 end-0 m-2" onclick="copyPluginsConfig(event)" style="z-index: 10;">
                                                <i class="ph-copy me-1"></i>
                                                کپی کد
                                            </button>
                                            <pre id="pluginsConfig"><code class="language-php">    // Shop Package Plugins
    'ckeditor' => [
        'css' => [],
        'js' => [
            'ckeditor.js',
            'ckeditor-init.js'
        ],
        'plugin_path' => 'ckeditor',
        'enabled' => true,
        'load_order' => 4,
        'description' => 'CKEditor 5 for rich text editing in Shop package'
    ],

    'fancytree' => [
        'css' => [
            'fancytree.css',
        ],
        'js' => [
            'fancytree_all.min.js',
            'fancytree_childcounter.js',
            'fancytree.dnd5.js',
            'rms-fancytree.js',
        ],
        'dependencies' => ['jquery'],
        'load_order' => 4,
        'plugin_path' => 'fancytree',
        'enabled' => true,
        'description' => 'Tree component for category management in Shop'
    ],

    'splide' => [
        'css' => [
            'splide.min.css'
        ],
        'js' => [
            'splide.min.js'
        ],
        'dependencies' => [],
        'load_order' => 4,
        'plugin_path' => 'splide',
        'enabled' => true,
        'description' => 'Splide slider for product galleries (panel)'
    ],

    'hls' => [
        'css' => [],
        'js' => [
            'hls.min.js'
        ],
        'dependencies' => [],
        'load_order' => 3,
        'plugin_path' => 'hls',
        'enabled' => true,
        'description' => 'HLS.js for video playback in Shop'
    ],

    'prism' => [
        'css' => [
            'prism.css'
        ],
        'js' => [
            'prism.js'
        ],
        'dependencies' => [],
        'load_order' => 5,
        'plugin_path' => 'prism',
        'enabled' => true,
        'description' => 'Prism.js for syntax highlighting in documentation'
    ],</code></pre>
                                        </div>
                                    </div>
                                </div>

                                <div class="card bg-light mt-3">
                                    <div class="card-body">
                                        <h6>
                                            <i class="ph-list-numbers me-1"></i>
                                            مراحل نصب پلاگین‌ها
                                        </h6>
                                        
                                        <ol class="mb-0">
                                            <li class="mb-2">
                                                <strong>اطمینان از وجود فایل <code>config/plugins.php</code>:</strong>
                                                <pre><code class="language-bash">php artisan vendor:publish --tag=cms-plugins-config</code></pre>
                                                <small class="text-muted d-block mt-1">این دستور فایل config/plugins.php را از RMS Core کپی می‌کند (فقط یکبار لازم است)</small>
                                            </li>
                                            
                                            <li class="mb-2">
                                                <strong>Publish کردن پلاگین‌های Admin:</strong>
                                                <pre><code class="language-bash">php artisan vendor:publish --tag=shop-plugins-admin</code></pre>
                                                <small class="text-muted d-block mt-1">این دستور پلاگین‌های CKEditor، Fancytree و Prism را در <code>public/admin/plugins/</code> کپی می‌کند</small>
                                            </li>
                                            
                                            <li class="mb-2">
                                                <strong>Publish کردن پلاگین‌های Panel:</strong>
                                                <pre><code class="language-bash">php artisan vendor:publish --tag=shop-plugins-panel</code></pre>
                                                <small class="text-muted d-block mt-1">این دستور پلاگین‌های Splide و HLS را در <code>public/panel/plugins/</code> کپی می‌کند</small>
                                            </li>
                                            
                                            <li class="mb-2">
                                                <strong>باز کردن فایل <code>config/plugins.php</code></strong>
                                                <small class="text-muted d-block mt-1">فایل config/plugins.php را در پروژه خود باز کنید</small>
                                            </li>
                                            
                                            <li class="mb-2">
                                                <strong>اضافه کردن کدهای پلاگین:</strong>
                                                <small class="text-muted d-block mt-1">کدهای بالا را در آرایه اصلی فایل config/plugins.php قرار دهید (قبل از آخرین ]; )</small>
                                            </li>
                                            
                                            <li class="mb-0">
                                                <strong>استفاده در کنترلرها:</strong>
                                                <pre><code class="language-php">// در کنترلر Shop:
$this->view->withPlugins(['ckeditor', 'fancytree']);</code></pre>
                                            </li>
                                        </ol>
                                    </div>
                                </div>

                                <div class="alert alert-success mt-3 mb-0">
                                    <h6 class="alert-heading">
                                        <i class="ph-check-circle me-1"></i>
                                        نکات مهم
                                    </h6>
                                    <ul class="mb-0">
                                        <li>پلاگین‌ها فقط یکبار نیاز به publish و ثبت دارند</li>
                                        <li>پس از اضافه کردن به config/plugins.php، نیازی به cache clear نیست</li>
                                        <li>می‌توانید هر پلاگینی را با تغییر <code>'enabled' => false</code> غیرفعال کنید</li>
                                        <li>ترتیب بارگذاری با <code>load_order</code> مشخص می‌شود (عدد کمتر = زودتر)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Tab -->
                        <div class="tab-pane fade" id="sidebar" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="ph-sidebar me-1"></i>
                                        اضافه کردن بخش Shop به Sidebar
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <h6 class="alert-heading">
                                            <i class="ph-info me-1"></i>
                                            راهنما
                                        </h6>
                                        <p class="mb-0">
                                            برای اضافه کردن منوی Shop به Sidebar پنل ادمین، کد زیر را در فایل <code>resources/views/vendor/cms/admin/layout/sidebar.blade.php</code> پروژه خود قرار دهید.
                                        </p>
                                    </div>

                                    <div class="card bg-light mt-3">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0">
                                                <i class="ph-code-block me-1"></i>
                                                کد Sidebar Shop
                                            </h6>
                                            <button type="button" class="btn btn-primary btn-sm" onclick="copySidebarCode(event)">
                                                <i class="ph-copy me-1"></i>
                                                کپی کد
                                            </button>
                                        </div>
                                        @php
                                        $sidebarCode = <<<'SIDEBAR'
{{-- Shop Module --}}
<x-cms::menu-header title="فروشگاه"/>

<x-cms::submenu-item
    title="فروشگاه"
    icon="ph-shopping-cart"
    :children="[
        [
            'title' => 'داشبورد فروشگاه',
            'url' => route('admin.shop.dashboard'),
            'icon' => 'ph-storefront',
        ],
        [
            'title' => 'محصولات',
            'url' => route('admin.shop.products.index'),
            'icon' => 'ph-package',
        ],
        [
            'title' => 'دسته‌بندی‌ها',
            'url' => route('admin.shop.categories.index'),
            'icon' => 'ph-folders',
        ],
        [
            'title' => 'سفارش‌ها',
            'url' => route('admin.shop.orders.index'),
            'icon' => 'ph-shopping-bag',
        ],
        [
            'title' => 'سبدهای خرید',
            'url' => route('admin.shop.carts.index'),
            'icon' => 'ph-shopping-cart-simple',
        ],
        [
            'title' => 'ارزها',
            'url' => route('admin.shop.currencies.index'),
            'icon' => 'ph-coins',
        ],
        [
            'title' => 'نرخ ارز',
            'url' => route('admin.shop.currency-rates.index'),
            'icon' => 'ph-arrows-left-right',
        ],
        [
            'title' => 'دسته‌بندی ویژگی‌ها',
            'url' => route('admin.shop.product-feature-categories.index'),
            'icon' => 'ph-list-bullets',
            'iconColor' => 'primary',
        ],
        [
            'title' => 'آمار خرید',
            'url' => route('admin.shop.product-purchase-stats.index'),
            'icon' => 'ph-chart-line',
        ],
        [
            'title' => 'امتیاز کاربران',
            'url' => route('admin.shop.user-points.index'),
            'icon' => 'ph-star',
        ],
        [
            'title' => 'تنظیمات شاپ',
            'url' => route('admin.shop.settings.edit'),
            'icon' => 'ph-gear',
        ],
    ]"
/>
SIDEBAR;
                                        @endphp
                                        <div class="card-body p-0">
                                            <pre id="sidebarCode" class="mb-0"><code class="language-markup">{{ $sidebarCode }}</code></pre>
                                        </div>
                                    </div>

                                    <div class="card bg-light mt-3">
                                        <div class="card-body">
                                            <h6>
                                                <i class="ph-list-numbers me-1"></i>
                                                مراحل اضافه کردن
                                            </h6>
                                            
                                            <ol class="mb-0">
                                                <li class="mb-2">
                                                    <strong>Publish کردن Sidebar (اگر نکرده‌اید):</strong>
                                                    <pre><code class="language-bash">php artisan vendor:publish --tag=cms-admin-views</code></pre>
                                                    <small class="text-muted d-block mt-1">این دستور فایل sidebar.blade.php را در <code>resources/views/vendor/cms/admin/layout/</code> کپی می‌کند</small>
                                                </li>
                                                
                                                <li class="mb-2">
                                                    <strong>باز کردن فایل Sidebar:</strong>
                                                    <small class="text-muted d-block mt-1">فایل <code>resources/views/vendor/cms/admin/layout/sidebar.blade.php</code> را باز کنید</small>
                                                </li>
                                                
                                                <li class="mb-2">
                                                    <strong>پیدا کردن محل مناسب:</strong>
                                                    <small class="text-muted d-block mt-1">کد Shop را می‌توانید در هر جایی از Sidebar قرار دهید، مثلاً قبل از بخش "System Management"</small>
                                                </li>
                                                
                                                <li class="mb-0">
                                                    <strong>Paste کردن کد:</strong>
                                                    <small class="text-muted d-block mt-1">کد کپی شده را در محل مناسب Paste کنید و فایل را ذخیره کنید</small>
                                                </li>
                                            </ol>
                                        </div>
                                    </div>

                                    <div class="alert alert-warning mt-3 mb-0">
                                        <h6 class="alert-heading">
                                            <i class="ph-warning me-1"></i>
                                            نکات مهم
                                        </h6>
                                        <ul class="mb-0">
                                            <li>مطمئن شوید که route names با route های تعریف شده در پکیج Shop مطابقت دارند</li>
                                            <li>اگر prefix route ها را تغییر داده‌اید، باید در <code>urlPattern</code> ها نیز تغییر دهید</li>
                                            <li>می‌توانید آیتم‌های اضافی یا کمتری اضافه/حذف کنید بسته به نیاز</li>
                                            <li>برای Badge سفارش‌ها، می‌توانید logic خودتان را پیاده‌سازی کنید</li>
                                            <li>پس از تغییرات، نیازی به cache clear نیست</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ph-x me-1"></i>
                    بستن
                </button>
                <button type="button" class="btn btn-primary" onclick="window.location.reload()">
                    <i class="ph-arrows-clockwise me-1"></i>
                    بررسی مجدد
                </button>
            </div>
        </div>
    </div>
</div>

    <script>
    function copyPluginsConfig(event) {
        const codeElement = document.querySelector('#pluginsConfig code');
        const textToCopy = codeElement.textContent;
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            // Show success message
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="ph-check me-1"></i> کپی شد!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        }).catch(err => {
            console.error('خطا در کپی کردن:', err);
            alert('خطا در کپی کردن. لطفاً به صورت دستی کپی کنید.');
        });
    }

    function copySidebarCode(event) {
        const codeElement = document.querySelector('#sidebarCode code');
        const textToCopy = codeElement.textContent;
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            // Show success message
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="ph-check me-1"></i> کپی شد!';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-success');
            
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('btn-success');
                btn.classList.add('btn-primary');
            }, 2000);
        }).catch(err => {
            console.error('خطا در کپی کردن:', err);
            alert('خطا در کپی کردن. لطفاً به صورت دستی کپی کنید.');
        });
    }
    </script>







