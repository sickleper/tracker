<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $pageTitle ?? 'Work Order Tracker'; ?></title>
    
    <!-- Compiled Tailwind CSS -->
    <link rel="stylesheet" href="/dist/output.css?v=<?php echo time(); ?>">    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Essential Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    
    <!-- DataTables & Plugins -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>

    <!-- UI Enhancements & Charts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/style.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/plugins/monthSelect/index.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.21/lodash.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <!-- Maps (Leaflet) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    
    <!-- Google Maps (Fallback/Autocomplete) -->
    <?php $gmKey = $GLOBALS['googleMapsApiKey'] ?? $googleMapsApiKey ?? ''; ?>
    <script async src="https://maps.googleapis.com/maps/api/js?key=<?php echo $gmKey; ?>&libraries=places&loading=async"></script>

    <script>
        window.appUrl = <?php echo json_encode($appUrl ?? trackerAppUrl() ?: '/'); ?>;
        window.laravelApiUrl = <?php echo json_encode($laravelApiUrl ?? ($_ENV['LARAVEL_API_URL'] ?? '')); ?>;
        window.apiToken = '<?php echo htmlspecialchars(getTrackerApiToken() ?? '', ENT_QUOTES, 'UTF-8'); ?>';
        window.trackerTenantSlug = '<?php echo htmlspecialchars(trackerTenantSlug(), ENT_QUOTES, 'UTF-8'); ?>';
        window.calloutDays = '<?php echo $GLOBALS['callout_days'] ?? '4,5'; ?>'.split(',').map(Number);
        window.whatsappAdminNumber = <?php echo json_encode(trim($GLOBALS['whatsapp_admin_number'] ?? $_ENV['WHATSAPP_ADMIN_NUMBER'] ?? '')); ?>;
        window.whatsappAdminAlertsEnabled = <?php echo json_encode(($GLOBALS['whatsapp_admin_alerts_enabled'] ?? $_ENV['WHATSAPP_ADMIN_ALERTS_ENABLED'] ?? '1') === '1'); ?>;
        // Theme initialization - immediate to prevent flash
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        /* Dashboard specific styles */
        .card-item { transition: all 0.2s ease; }
        .card-item:active { transform: scale(0.98); }
        .chevron-rotate { transition: transform 0.3s ease; }
        .chevron-rotate.rotated { transform: rotate(180deg); }

        @keyframes pulse-green {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
        }
        .animate-pulse-green { animation: pulse-green 2s infinite; }

        /* Calendar Highlighting */
    .callout-day-highlight {
        background: rgba(79, 70, 229, 0.1) !important;
        border: 1px solid #4f46e5 !important;
        font-weight: 900 !important;
        color: #4f46e5 !important;
    }
    .dark .callout-day-highlight {
        background: rgba(129, 140, 248, 0.1) !important;
        border: 1px solid #818cf8 !important;
        color: #818cf8 !important;
    }
</style>
<?php
    if (!empty($pageCssFiles) && is_array($pageCssFiles)) {
        foreach ($pageCssFiles as $cssFile) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') . '">';
        }
    }
?>
</head>
<body class="bg-main-gradient min-h-screen transition-colors duration-300">
<?php if (trackerIsPrimaryApp() && !empty($_SESSION['impersonation_active'])): ?>
    <?php $tenantLoginUrl = rtrim(trackerAppUrl(), '/') . '/admin/tenant_login.php'; ?>
    <?php $tenantDiagnosticUrl = rtrim(trackerAppUrl(), '/') . '/admin/tenant_feature_diagnostic.php'; ?>
    <div class="bg-amber-500 text-slate-950 px-4 py-3 text-xs font-black uppercase tracking-widest no-print">
        <div class="max-w-full mx-auto flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span>Impersonating</span>
                <span class="px-2 py-1 rounded-full bg-white/70 text-[10px]"><?php echo htmlspecialchars($_SESSION['impersonated_user_name'] ?? $_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="text-[10px]"><?php echo htmlspecialchars($_SESSION['impersonated_user_email'] ?? $_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="opacity-70">in</span>
                <span class="px-2 py-1 rounded-full bg-white/70 text-[10px]"><?php echo htmlspecialchars($_SESSION['impersonated_tenant_name'] ?? $_SESSION['tenant_slug'] ?? 'Tenant', ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="opacity-70">as requested by</span>
                <span><?php echo htmlspecialchars($_SESSION['impersonated_by_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="<?php echo htmlspecialchars($tenantDiagnosticUrl, ENT_QUOTES, 'UTF-8'); ?>" class="px-4 py-2 rounded-xl bg-white/70 text-slate-950 hover:bg-white transition-all">
                    Tenant Diagnostic
                </a>
                <form method="post" action="<?php echo htmlspecialchars($tenantLoginUrl, ENT_QUOTES, 'UTF-8'); ?>" class="m-0">
                    <input type="hidden" name="action" value="restore">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-950 text-white hover:bg-black transition-all">Stop Impersonating</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
