# RMS Shop Package Configuration

# ============================================
# Routes Configuration
# ============================================
# Admin panel routes
SHOP_ADMIN_PREFIX=admin/shop
SHOP_ADMIN_ROUTE_NAME=admin.shop.

# Public shop routes
SHOP_PREFIX=shop
SHOP_ROUTE_NAME=shop.

# ============================================
# Order Configuration
# ============================================
SHOP_ACTIVE_ORDER_STATUSES=pending,preparing,paid

# ============================================
# Cart Configuration
# ============================================
# Soft reservation TTL in seconds (30 minutes default)
SHOP_CART_RES_TTL=1800

# ============================================
# Category Configuration
# ============================================
SHOP_DEFAULT_CATEGORY_NAME=دسته‌بندی نشده
SHOP_DEFAULT_CATEGORY_SLUG=uncategorized
SHOP_CATEGORY_CACHE_TTL=300

# ============================================
# Media Configuration
# ============================================
# Image driver: avif, imagick, gd
SHOP_IMAGE_DRIVER=avif
SHOP_IMAGE_QUALITY=85
SHOP_IMAGE_MAX_SIZE=5120

# Video configuration
SHOP_VIDEO_ENABLED=true
SHOP_VIDEO_DRIVER=hls
SHOP_VIDEO_MAX_SIZE=102400

# FFmpeg paths (leave as 'ffmpeg' if in system PATH)
FFMPEG_PATH=ffmpeg
FFPROBE_PATH=ffprobe

# For Windows with custom path:
# FFMPEG_PATH=C:\ffmpeg\bin\ffmpeg.exe
# FFPROBE_PATH=C:\ffmpeg\bin\ffprobe.exe

# ============================================
# Queue Configuration
# ============================================
# Separate queues for different processing tasks
# This allows running dedicated workers for each queue

# Queue for AVIF image conversion
SHOP_QUEUE_AVIF=shop-avif

# Queue for video processing (HLS conversion)
SHOP_QUEUE_MEDIA=shop-media

# Default queue for other shop tasks
SHOP_QUEUE_DEFAULT=default

# ============================================
# Queue Worker Commands
# ============================================
# Run these commands in separate terminals for optimal performance:

# 1. AVIF conversion worker (high priority)
#    php artisan queue:work --queue=shop-avif --tries=3 --timeout=300

# 2. Video processing worker (CPU intensive)
#    php artisan queue:work --queue=shop-media --tries=2 --timeout=600

# 3. Default worker for other tasks
#    php artisan queue:work --queue=default --tries=3 --timeout=60

# For production with Supervisor:
# [program:shop-avif-worker]
# process_name=%(program_name)s_%(process_num)02d
# command=php /path/to/artisan queue:work --queue=shop-avif --tries=3 --timeout=300
# autostart=true
# autorestart=true
# numprocs=2
# redirect_stderr=true
# stdout_logfile=/path/to/storage/logs/shop-avif-worker.log

# [program:shop-media-worker]
# process_name=%(program_name)s_%(process_num)02d
# command=php /path/to/artisan queue:work --queue=shop-media --tries=2 --timeout=600
# autostart=true
# autorestart=true
# numprocs=1
# redirect_stderr=true
# stdout_logfile=/path/to/storage/logs/shop-media-worker.log

