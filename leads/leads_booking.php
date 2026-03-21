<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

require_once "../tracker_data.php";

$pageTitle = "Schedule Console";
include_once "../header.php";
include_once "../nav.php";

// Fetch categories for the form
$categories = [];
$catRes = makeApiCall('/api/leads/categories');
if ($catRes && ($catRes['success'] ?? false)) $categories = $catRes['data'];

// Using $GLOBALS['callout_days'] which was already populated by tracker_data.php
$calloutDaysArr = explode(',', ($GLOBALS['callout_days'] ?? '4,5'));
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    
    <!-- Header Section -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="heading-brand">
                <span>📅</span> Schedule Console
            </h1>
            <p class="text-gray-500 dark:text-gray-400 text-xs font-bold uppercase tracking-widest mt-1">Book consultations with route-optimized logic</p>
        </div>
    </div>

    <!-- View Navigation -->
    <div class="mb-8 border-b border-gray-200 dark:border-slate-800">
        <nav class="flex flex-wrap -mb-px space-x-2 sm:space-x-8">
            <a href="leads.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-list-ul"></i> Active Database
            </a>
            <a href="leads_callout_map.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-map-marked-alt"></i> Callout Map
            </a>
            <a href="leads_booking.php" class="border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2">
                <i class="fas fa-calendar-alt"></i> Scheduler
            </a>
            <a href="leads_visualize.php" class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-black text-xs uppercase tracking-widest flex items-center gap-2 transition-colors">
                <i class="fas fa-chart-pie"></i> Visualizer
            </a>
        </nav>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left: Booking Form -->
        <div class="lg:col-span-7">
            <div class="card-base">
                <div class="bg-gray-900 p-6 flex justify-between items-center text-white">
                    <h2 class="text-xl font-black italic uppercase tracking-wider flex items-center gap-3">
                        <i class="fas fa-file-invoice text-indigo-400"></i> Request Details
                    </h2>
                    <div class="flex gap-2 bg-white/10 p-1 rounded-xl">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="booking_type_toggle" value="survey" checked class="sr-only peer" onchange="toggleBookingType('survey')">
                            <div class="px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-400 peer-checked:bg-indigo-600 peer-checked:text-white transition-all">Survey</div>
                        </label>
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="radio" name="booking_type_toggle" value="enquiry" class="sr-only peer" onchange="toggleBookingType('enquiry')">
                            <div class="px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest text-gray-400 peer-checked:bg-indigo-600 peer-checked:text-white transition-all">Enquiry</div>
                        </label>
                    </div>
                </div>

                <form id="mainBookingForm" class="p-8 space-y-6">
                    <input type="hidden" name="booking_type" id="booking_type" value="survey">
                    <input type="hidden" name="latlng" id="latlng">
                    <input type="hidden" name="booking_date" id="selected_date">
                    <input type="hidden" name="booking_time" id="selected_time">
                    <input type="hidden" name="last_name" value=""> <!-- Honeypot -->

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Client Name *</label>
                            <input type="text" name="client_name" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Email Address *</label>
                            <input type="email" name="client_email" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Mobile Number *</label>
                            <input type="text" name="mobile" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Project Category</label>
                            <select name="category_id" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold dark:text-white">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['category_name'] ?? $cat['name'] ?? 'General'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Eircode (Auto-fills Address & Map)</label>
                        <div class="flex gap-2">
                            <input type="text" id="eircodeLookup" class="flex-grow p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-black uppercase dark:text-white" placeholder="D24...">
                            <button type="button" onclick="lookupEircode()" class="px-6 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 transition-all shadow-lg active:scale-95">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Site Address</label>
                        <input type="text" id="addressInput" name="address" required class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-medium dark:text-gray-300">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Requirements / Message</label>
                        <textarea name="message" rows="4" class="w-full p-4 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-800 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm leading-relaxed dark:text-gray-300" placeholder="Briefly describe the work required..."></textarea>
                    </div>

                    <div id="surveySection" class="pt-4 border-t border-gray-100 dark:border-slate-800">
                        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i class="fas fa-clock text-indigo-500"></i> Selected Appointment
                        </h3>
                        <div id="selectedSlotDisplay" class="p-8 bg-indigo-50 dark:bg-indigo-900/20 rounded-3xl border-2 border-dashed border-indigo-200 dark:border-indigo-900/50 text-center transition-all">
                            <p class="text-gray-400 dark:text-gray-500 font-bold italic text-sm">Please select a suitable slot from the calendar on the right.</p>
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="w-full py-5 bg-gray-900 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-indigo-600 transition-all active:scale-[0.98] shadow-2xl flex items-center justify-center gap-3">
                            <i class="fas fa-check-circle text-emerald-400"></i> Confirm & Book Lead
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Availability Calendar -->
        <div class="lg:col-span-5 space-y-8">
            <div class="card-base p-6">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-4 flex items-center justify-between">
                    <span>Select Visit Date</span>
                    <?php 
                        $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                        $activeDayNames = array_map(function($d) use ($dayNames) { return $dayNames[intval($d)]; }, $calloutDaysArr);
                        $displayText = implode('/', $activeDayNames) . ' Only';
                    ?>
                    <span id="callout-days-display" class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-full"><?php echo $displayText; ?></span>
                </h3>
                <div id="calendar" class="dark:bg-slate-900 rounded-xl overflow-hidden"></div>
            </div>

            <div id="slotsContainer" class="card-base min-h-[300px]">
                <div class="bg-gray-100 dark:bg-slate-800 p-4 border-b border-gray-200 dark:border-slate-700">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Available Time Slots</h3>
                </div>
                <div id="slotsList" class="p-6 space-y-3">
                    <div class="text-center py-12 text-gray-300 dark:text-gray-600 italic text-sm">Select a date to view slots</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let calendar = null;
    let currentLatlng = "";

    // Function to determine SweetAlert2 theme based on dark mode
    function getSwalTheme() {
        return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
    }

    function toggleBookingType(type) {
        $('#booking_type').val(type);
        const section = $('#surveySection, #slotsContainer');
        if (type === 'enquiry') {
            section.addClass('opacity-30 pointer-events-none grayscale');
            $('#selected_date').val('');
            $('#selected_time').val('');
            $('#selectedSlotDisplay')
                .html('<p class="text-gray-400 dark:text-gray-500 font-bold italic text-sm">Scheduling is disabled for enquiry mode.</p>')
                .removeClass('border-solid border-indigo-500 bg-white dark:bg-slate-900 shadow-xl')
                .addClass('border-dashed border-indigo-200 dark:border-indigo-900/50');
            $('#slotsList').html('<div class="text-center py-12 text-gray-300 dark:text-gray-600 italic text-sm">Scheduling disabled for enquiry mode</div>');
            $('.slot-card').removeClass('ring-4 ring-indigo-500 border-indigo-500 scale-[1.02]');
        } else {
            section.removeClass('opacity-30 pointer-events-none grayscale');
            $('#selectedSlotDisplay')
                .html('<p class="text-gray-400 dark:text-gray-500 font-bold italic text-sm">Please select a suitable slot from the calendar on the right.</p>')
                .removeClass('border-solid border-indigo-500 bg-white dark:bg-slate-900 shadow-xl')
                .addClass('border-dashed border-indigo-200 dark:border-indigo-900/50');
            $('#slotsList').html('<div class="text-center py-12 text-gray-300 dark:text-gray-600 italic text-sm">Select a date to view slots</div>');
        }
    }

    function lookupEircode() {
        const eircode = $('#eircodeLookup').val().trim();
        if (!eircode) return;
        const btn = $('button[onclick="lookupEircode()"]');
        const orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.getJSON(`../query_address.php?eircode=${eircode}`, function(data) {
            if (data.address) $('#addressInput').val(data.address);
            if (data.coordinates) {
                currentLatlng = `${data.coordinates.lat},${data.coordinates.lng}`;
                $('#latlng').val(currentLatlng);
                if ($('#selected_date').val()) fetchSlots($('#selected_date').val());
            }
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Address Found', showConfirmButton: false, timer: 1500, theme: getSwalTheme() });
        }).fail(function(xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Lookup Failed',
                text: xhr.responseJSON?.message || 'Could not look up that Eircode.',
                theme: getSwalTheme()
            });
        }).always(function() {
            btn.prop('disabled', false).html(orig);
        });
    }

    function fetchSlots(date) {
        $('#selected_date').val(date);
        $('#slotsList').html('<div class="flex justify-center py-12"><i class="fas fa-circle-notch fa-spin text-2xl text-indigo-500"></i></div>');
        
        const params = new URLSearchParams({ action: 'get_slots', date: date, latlng: currentLatlng, show_all: 0 });
        $.getJSON(`leads_handler.php?${params.toString()}`, function(res) {
            if (res.success && res.slots.length > 0) {
                let html = '';
                res.slots.forEach(slot => {
                    const recClass = slot.recommended ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-950/20 shadow-md ring-2 ring-emerald-100 dark:ring-emerald-900/30' : 'border-gray-100 dark:border-slate-800 bg-gray-50 dark:bg-slate-900 hover:border-indigo-300';
                    html += `
                        <div onclick="selectSlot(this, '${slot.time}')" class="slot-card p-4 rounded-2xl border-2 transition-all cursor-pointer group ${recClass}">
                            <div class="flex items-center justify-between">
                                <span class="text-lg font-black text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">${slot.time}</span>
                                ${slot.recommended ? '<span class="text-[9px] font-black uppercase text-white bg-emerald-500 px-2 py-1 rounded-lg">Recommended Route</span>' : ''}
                            </div>
                            ${slot.reason ? `<p class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-1 italic">${slot.reason}</p>` : ''}
                        </div>
                    `;
                });
                $('#slotsList').html(html);
            } else {
                $('#slotsList').html(`<div class="text-center py-12 text-red-400 font-bold text-sm">${res.message || 'No slots available for this date.'}</div>`);
            }
        });
    }

    function selectSlot(element, time) {
        $('.slot-card').removeClass('ring-4 ring-indigo-500 border-indigo-500 scale-[1.02]');
        $(element).addClass('ring-4 ring-indigo-500 border-indigo-500 scale-[1.02]');
        
        $('#selected_time').val(time);
        const date = moment($('#selected_date').val()).format('dddd, Do MMMM YYYY');
        
        $('#selectedSlotDisplay').html(`
            <div class="flex flex-col items-center">
                <span class="text-indigo-600 dark:text-indigo-400 font-black text-3xl italic uppercase tracking-tighter">${time}</span>
                <span class="text-gray-900 dark:text-white font-bold text-sm mt-1">${date}</span>
            </div>
        `).removeClass('border-dashed border-indigo-200 dark:border-indigo-900/50').addClass('border-solid border-indigo-500 bg-white dark:bg-slate-900 shadow-xl');
    }

    $(document).ready(function() {
        calendar = flatpickr("#calendar", {
            inline: true,
            minDate: "today",
            dateFormat: "Y-m-d",
            disable: [ (date) => !window.calloutDays.includes(date.getDay()) ],
            onChange: (selectedDates, dateStr) => fetchSlots(dateStr),
            locale: { firstDayOfWeek: 1 }
        });

        $('#mainBookingForm').on('submit', function(e) {
            e.preventDefault();
            const btn = $(this).find('button[type="submit"]');
            const originalHtml = btn.html();

            if ($('#booking_type').val() === 'survey' && !$('#selected_time').val()) {
                Swal.fire({ title: 'Time Required', text: 'Please select an appointment slot from the calendar.', icon: 'warning', theme: getSwalTheme() });
                return;
            }

            btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Booking...');

            $.ajax({
                url: 'leads_handler.php',
                method: 'POST',
                data: $(this).serialize() + '&action=submit_booking',
                success: function(res) {
                    if (res.success) {
                        Swal.fire({ title: 'Booked!', text: 'The lead and appointment have been saved.', icon: 'success', confirmButtonColor: '#4f46e5', theme: getSwalTheme() }).then(() => location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message, theme: getSwalTheme() });
                    }
                },
                error: (xhr) => Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'Submission failed.', theme: getSwalTheme() }),
                complete: () => btn.prop('disabled', false).html(originalHtml)
            });
        });
    });
</script>

<style>
    .flatpickr-calendar { box-shadow: none !important; border: none !important; width: 100% !important; background: transparent !important; }
    .flatpickr-innerContainer, .flatpickr-rContainer, .flatpickr-days, .dayContainer { width: 100% !important; min-width: 100% !important; }
    .flatpickr-day { max-width: none !important; flex-basis: 14.28% !important; border-radius: 12px !important; font-weight: 700 !important; }
</style>

<?php include_once "../footer.php"; ?>
