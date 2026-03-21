<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/tracker_data.php';

if (!isTrackerAuthenticated()) {
    http_response_code(401);
    exit('<div class="p-8 text-center text-red-500 font-black uppercase tracking-widest">Unauthorized</div>');
}

$taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : null;
$taskPoNumber = '';
$isWeatherDependentInitial = 0; 
$existingNextVisit = null; 

if ($taskId) {
    $response = makeApiCall("/api/tasks/{$taskId}");
    if ($response && ($response['success'] ?? false) && isset($response['task'])) {
        $task = $response['task'];
        $taskPoNumber = htmlspecialchars(($task['po_number'] ?? '') . ' - ' . ($task['heading'] ?? ''));
        $isWeatherDependentInitial = (int)($task['is_weather_dependent'] ?? 0);
        if (!empty($task['next_visit']) && $task['next_visit'] !== '0000-00-00') {
            $existingNextVisit = $task['next_visit'];
        }
    }
}
?>

<div class="p-6">
    <!-- Job Info -->
    <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-950/20 rounded-2xl border border-indigo-100 dark:border-indigo-900/30 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <span class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.2em]">Currently Scheduling</span>
            <h2 class="text-indigo-900 dark:text-indigo-300 font-black text-lg leading-tight mt-1"><?php echo $taskPoNumber ?: 'New Job'; ?></h2>
        </div>
        <div class="flex items-center gap-3 bg-white dark:bg-slate-900 p-3 rounded-xl shadow-sm border border-gray-100 dark:border-slate-800">
            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Weather Dependent</span>
            <label class="inline-flex items-center cursor-pointer">
                <input type="checkbox" id="weatherDependentToggle" class="sr-only peer" <?php echo $isWeatherDependentInitial ? 'checked' : ''; ?> onchange="updateWeatherDependency(<?php echo $taskId; ?>)">
                <div class="relative w-11 h-6 bg-gray-200 dark:bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
        </div>
    </div>

    <!-- Existing Next Visit Warning -->
    <?php if ($existingNextVisit): ?>
    <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-950/20 rounded-2xl border border-amber-200 dark:border-amber-900/30 flex items-center gap-4 text-amber-800 dark:text-amber-400">
        <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/50 rounded-full flex items-center justify-center text-amber-600">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <p class="text-[10px] font-black uppercase tracking-widest opacity-60">Currently Booked For</p>
            <p class="text-sm font-black italic underline"><?php echo date('D, d M Y', strtotime($existingNextVisit)); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Loading State -->
    <div id="weather-loading" class="flex flex-col items-center justify-center py-20">
        <div class="w-12 h-12 border-4 border-indigo-600/20 border-t-indigo-600 rounded-full animate-spin mb-4"></div>
        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest animate-pulse">Fetching Real-Time Forecast...</p>
    </div>

    <!-- Forecast Container -->
    <div id="weather-forecast-grid" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Days will be injected here -->
    </div>
</div>

<script>
    // Component-level state to avoid globals
    (function() {
        const taskId = <?php echo json_encode($taskId); ?>;
        
        async function fetchForecast() {
            try {
                const response = await fetch(window.appUrl + 'fetch_weather_forecast.php');
                const result = await response.json();

                if (result.success && result.data) {
                    const daysMap = {};
                    result.data.slots.forEach(slot => {
                        if (!daysMap[slot.date]) {
                            const d = new Date(slot.date);
                            daysMap[slot.date] = {
                                date: slot.date,
                                day_name: d.toLocaleDateString('en-GB', { weekday: 'short' }),
                                date_formatted: d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }),
                                temp: slot.temperature,
                                rain_prob: slot.precipitation_probability,
                                wind_speed: slot.wind_speed,
                                description: 'Suitable Work Window',
                                icon: '01d', 
                                score: slot.score
                            };
                        }
                    });
                    renderForecast(Object.values(daysMap));
                } else {
                    document.getElementById('weather-loading').innerHTML = `<p class="text-red-500 font-black uppercase tracking-widest text-[10px]">${result.message || 'Error loading forecast'}</p>`;
                }
            } catch (error) {
                document.getElementById('weather-loading').innerHTML = `<p class="text-red-500 font-black uppercase tracking-widest text-[10px]">Failed to load weather data.</p>`;
            }
        }

        function renderForecast(days) {
            const container = document.getElementById('weather-forecast-grid');
            const loading = document.getElementById('weather-loading');
            if (!container) return;
            container.innerHTML = '';

            days.forEach((day) => {
                const isSuitable = day.rain_prob < 30 && day.wind_speed < 25;
                const card = document.createElement('div');
                card.className = `p-6 rounded-3xl border transition-all cursor-pointer group hover:shadow-xl active:scale-95 ${isSuitable ? 'border-emerald-100 bg-emerald-50/30 dark:border-emerald-900/30 dark:bg-emerald-900/10 hover:border-emerald-500' : 'border-gray-100 bg-white dark:bg-slate-900 dark:border-slate-800 hover:border-indigo-400'}`;
                
                card.onclick = () => {
                    if (!isSuitable) {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "The weather forecast for this day isn't ideal for outdoor work.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#fbbf24',
                            confirmButtonText: 'Yes, book it anyway'
                        }).then((result) => {
                            if (result.isConfirmed) performUpdate(day.date);
                        });
                    } else {
                        performUpdate(day.date);
                    }
                };

                card.innerHTML = `
                    <div class="text-center">
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1">${day.day_name}</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white italic tracking-tighter">${day.date_formatted}</p>
                        
                        <div class="my-4 flex items-center justify-center gap-2">
                            <span class="text-3xl font-black text-gray-800 dark:text-gray-200 italic tracking-tighter">${Math.round(day.temp)}°</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-[8px] mb-4">
                            <div class="bg-white/80 dark:bg-black/20 p-2 rounded-xl border border-gray-100 dark:border-slate-800">
                                <span class="block text-gray-400 font-black uppercase tracking-widest mb-1">Rain</span>
                                <span class="font-black ${day.rain_prob > 30 ? 'text-red-500' : 'text-blue-600'}">${day.rain_prob}%</span>
                            </div>
                            <div class="bg-white/80 dark:bg-black/20 p-2 rounded-xl border border-gray-100 dark:border-slate-800">
                                <span class="block text-gray-400 font-black uppercase tracking-widest mb-1">Wind</span>
                                <span class="font-black ${day.wind_speed > 25 ? 'text-red-500' : 'text-gray-700 dark:text-gray-300'}">${Math.round(day.wind_speed)} km/h</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-gray-100 dark:border-slate-800">
                            ${isSuitable ? 
                                '<span class="text-[8px] font-black text-emerald-600 bg-emerald-100 dark:bg-emerald-900/30 px-3 py-1 rounded-full uppercase tracking-widest"><i class="fas fa-check mr-1"></i> Recommended</span>' : 
                                '<span class="text-[8px] font-black text-gray-400 bg-gray-100 dark:bg-slate-800 px-3 py-1 rounded-full uppercase tracking-widest">Not Ideal</span>'
                            }
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });

            loading.classList.add('hidden');
            container.classList.remove('hidden');
        }

        async function performUpdate(date) {
            if (!taskId) {
                // Handle creation mode if applicable
                if (typeof onDateSelected === 'function') onDateSelected(date);
                closeWeatherForecastModal();
                return;
            }

            showSaving();
            try {
                const response = await fetch(window.appUrl + 'tracker_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'update',
                        id: taskId,
                        field: 'nextVisit',
                        value: date
                    })
                });

                const data = await response.json();
                hideSaving();
                if (data.success) {
                    Toast.fire({ icon: 'success', title: 'Schedule Updated' });
                    // Refresh parent if needed
                    const taskRow = document.querySelector(`tr[data-id="${taskId}"]`);
                    if (taskRow) {
                        const nextVisitInput = taskRow.querySelector('input[onchange*="nextVisit"]');
                        if (nextVisitInput) nextVisitInput.value = date;
                    }
                    closeWeatherForecastModal();
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                hideSaving();
                Swal.fire('Error', 'Failed to update schedule', 'error');
            }
        }

        // Start fetching immediately
        fetchForecast();
    })();

    async function updateWeatherDependency(taskId) {
        if (!taskId) return;
        const isDependent = document.getElementById('weatherDependentToggle').checked ? 1 : 0;
        
        try {
            const response = await fetch(window.appUrl + 'update_weather_dependency.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ task_id: taskId, is_weather_dependent: isDependent })
            });
            const data = await response.json();
            if (data.success) {
                // Update the icon in the main view
                const btn = document.querySelector(`button[onclick*="openWeatherForecastModal(${taskId})"]`);
                if (btn) {
                    if (isDependent) {
                        btn.classList.add('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100');
                        btn.classList.remove('text-gray-400', 'hover:text-gray-600');
                    } else {
                        btn.classList.remove('text-yellow-500', 'hover:text-yellow-700', 'bg-yellow-100');
                        btn.classList.add('text-gray-400', 'hover:text-gray-600');
                    }
                }
                Toast.fire({ icon: 'success', title: 'Dependency Updated' });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            console.error('Error updating weather dependency:', error);
        }
    }
</script>
