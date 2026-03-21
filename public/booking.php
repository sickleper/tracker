<?php
// Standalone Public Booking Form for Iframe Integration
require_once "../config.php";
require_once "../tracker_data.php"; // For makeApiCall (now updated for public endpoints)

// Fetch categories for the form via public endpoint
$categories = [];
$catRes = makeApiCall('/api/public/leads/categories');
if ($catRes && ($catRes['success'] ?? false)) $categories = $catRes['data'];

// Fetch callout days from settings API
$calloutDays = [4, 5]; // Default to Thu, Fri
$settingsRes = makeApiCall('/api/settings');
if ($settingsRes && ($settingsRes['success'] ?? false)) {
    // Robust search across all groups
    foreach ($settingsRes['data'] as $group => $items) {
        foreach ($items as $setting) {
            if ($setting['key'] === 'callout_days' && !empty($setting['value'])) {
                $calloutDays = array_map('intval', explode(',', $setting['value']));
                break 2; // Found it, exit both loops
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Consultation</title>
    <link rel="stylesheet" href="../dist/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.calloutDays = <?php echo json_encode($calloutDays); ?>;
    </script>
    <style>
        body { background: transparent; overflow-x: hidden; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        .flatpickr-calendar { box-shadow: none !important; border: none !important; width: 100% !important; }
        .flatpickr-innerContainer, .flatpickr-rContainer, .flatpickr-days, .dayContainer { width: 100% !important; min-width: 100% !important; }
        .flatpickr-day { max-width: none !important; flex-basis: 14.28% !important; border-radius: 12px !important; }
    </style>
</head>
<body class="p-2 md:p-4">
    <div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-12">
            
            <!-- Left: Form -->
            <div class="lg:col-span-7 p-6 md:p-8 space-y-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-xl font-black text-gray-900 italic uppercase tracking-wider flex items-center gap-3">
                        <i class="fas fa-calendar-check text-indigo-600"></i> Book Consultation
                    </h2>
                </div>

                <form id="publicBookingForm" class="space-y-5">
                    <input type="hidden" name="latlng" id="latlng">
                    <input type="hidden" name="booking_date" id="selected_date">
                    <input type="hidden" name="booking_time" id="selected_time">
                    <input type="hidden" name="last_name" value=""> <!-- Honeypot -->

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Your Name *</label>
                            <input type="text" name="client_name" required class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold" placeholder="John Doe">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Email Address *</label>
                            <input type="email" name="client_email" required class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold" placeholder="john@example.com">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Mobile Number *</label>
                            <input type="text" name="mobile" required class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold" placeholder="08x...">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Project Type</label>
                            <select name="category_id" required class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Eircode (Required for Address)</label>
                        <div class="flex gap-2">
                            <input type="text" id="eircodeLookup" class="flex-grow p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold uppercase" placeholder="D24...">
                            <button type="button" onclick="lookupEircode()" class="px-5 bg-indigo-600 text-white rounded-2xl hover:bg-indigo-700 transition-all shadow-md">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Full Address</label>
                        <input type="text" id="addressInput" name="address" required class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm font-bold">
                    </div>

                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1.5 ml-1">Briefly tell us what you need</label>
                        <textarea name="message" rows="3" class="w-full p-3.5 bg-gray-50 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all text-sm leading-relaxed" placeholder="Description of work..."></textarea>
                    </div>

                    <div id="selectedSlotDisplay" class="p-4 bg-indigo-50 rounded-2xl border-2 border-dashed border-indigo-200 text-center">
                        <p class="text-gray-400 font-bold italic text-xs">Select a date and time from the right.</p>
                    </div>

                    <button type="submit" class="w-full py-4 bg-gray-900 text-white rounded-2xl font-black uppercase tracking-widest hover:bg-gray-800 transition-all active:scale-[0.98] shadow-2xl flex items-center justify-center gap-3">
                        <i class="fas fa-check-circle text-emerald-500"></i> Confirm Booking
                    </button>
                </form>
            </div>

            <!-- Right: Scheduler -->
            <div class="lg:col-span-5 bg-gray-50 p-6 md:p-8 border-l border-gray-100 space-y-6">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-400 mb-4 flex items-center justify-between">
                        <span>1. Select Date</span>
                        <span class="text-[9px] bg-indigo-100 text-indigo-600 px-2 py-0.5 rounded-full">Dublin Area</span>
                    </h3>
                    <div id="calendar" class="bg-white p-4 rounded-2xl border border-gray-200 shadow-sm"></div>
                </div>

                <div id="slotsContainer">
                    <h3 class="text-xs font-black uppercase tracking-widest text-gray-400 mb-4">2. Available Times</h3>
                    <div id="slotsList" class="space-y-2 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
                        <div class="text-center py-8 text-gray-300 italic text-xs">Pick a date first</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        let currentLatlng = "";

        // Function to determine SweetAlert2 theme based on dark mode
        function getSwalTheme() {
            return document.documentElement.classList.contains('dark') ? 'dark' : 'default';
        }

        function lookupEircode() {
            const eircode = $('#eircodeLookup').val().trim();
            if (!eircode) return;

            const btn = $('button[onclick="lookupEircode()"]');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            $.getJSON(`../query_address.php?eircode=${eircode}`, function(data) {
                btn.prop('disabled', false).html('<i class="fas fa-search"></i>');
                if (data.address) $('#addressInput').val(data.address);
                if (data.coordinates) {
                    currentLatlng = `${data.coordinates.lat},${data.coordinates.lng}`;
                    $('#latlng').val(currentLatlng);
                    if ($('#selected_date').val()) fetchSlots($('#selected_date').val());
                }
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Address Identified', showConfirmButton: false, timer: 1500, theme: getSwalTheme() });
            });
        }

        function fetchSlots(date) {
            $('#selected_date').val(date);
            $('#slotsList').html('<div class="flex justify-center py-12"><i class="fas fa-circle-notch fa-spin text-2xl text-indigo-500"></i></div>');
            
            // Note: action 'get_slots' is handled by leads_handler.php which we updated to allow public access for 'get_public_slots'
            // We'll use get_public_slots to be safe
            const params = new URLSearchParams({ action: 'get_public_slots', date: date, latlng: currentLatlng });
            $.getJSON(`../leads/leads_handler.php?${params.toString()}`, function(res) {
                if (res.success && res.slots.length > 0) {
                    let html = '';
                    res.slots.forEach(slot => {
                        const recClass = slot.recommended ? 'border-emerald-500 bg-emerald-50 shadow-md ring-2 ring-emerald-100' : 'border-gray-100 bg-white hover:border-indigo-300';
                        html += `
                            <div onclick="selectSlot('${slot.time}')" class="slot-card p-3 rounded-xl border-2 transition-all cursor-pointer group ${recClass}">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-black text-gray-900 group-hover:text-indigo-600 transition-colors">${slot.time}</span>
                                    ${slot.recommended ? '<span class="text-[8px] font-black uppercase text-white bg-emerald-500 px-2 py-0.5 rounded-md">Best Route</span>' : ''}
                                </div>
                            </div>
                        `;
                    });
                    $('#slotsList').html(html);
                } else {
                    $('#slotsList').html(`<div class="text-center py-12 text-red-400 font-bold text-xs">${res.message || 'No slots available.'}</div>`);
                }
            });
        }

        function selectSlot(time) {
            $('.slot-card').removeClass('ring-4 ring-indigo-500 border-indigo-500 scale-[1.02]');
            $(event.currentTarget).addClass('ring-4 ring-indigo-500 border-indigo-500 scale-[1.02]');
            
            $('#selected_time').val(time);
            const date = moment($('#selected_date').val()).format('Do MMM YYYY');
            
            $('#selectedSlotDisplay').html(`
                <div class="flex items-center justify-center gap-3">
                    <span class="text-indigo-600 font-black text-lg">${time}</span>
                    <span class="w-1 h-1 rounded-full bg-gray-300"></span>
                    <span class="text-gray-900 font-bold text-xs">${date}</span>
                </div>
            `).removeClass('border-dashed border-indigo-200').addClass('border-solid border-indigo-500 bg-indigo-50/50');
        }

        $(document).ready(function() {
            flatpickr("#calendar", {
                inline: true,
                minDate: "today",
                dateFormat: "Y-m-d",
                disable: [
                    function(date) {
                        return !window.calloutDays.includes(date.getDay());
                    }
                ],
                onChange: function(selectedDates, dateStr) {
                    fetchSlots(dateStr);
                }
            });

            $('#publicBookingForm').on('submit', function(e) {
                e.preventDefault();
                if (!$('#selected_time').val()) {
                    Swal.fire({ title: 'Time Required', text: 'Please select an appointment time from the list.', icon: 'warning', theme: getSwalTheme() });
                    return;
                }

                const btn = $(this).find('button[type="submit"]');
                const originalHtml = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin"></i> Booking...');

                $.ajax({
                    url: '../leads/leads_handler.php',
                    method: 'POST',
                    data: $(this).serialize() + '&action=submit_public_booking',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Your consultation has been booked. We will contact you shortly.',
                                icon: 'success',
                                confirmButtonColor: '#111827',
                                theme: getSwalTheme()
                            }).then(() => location.reload());
                        } else {
                            Swal.fire({ icon: 'error', title: 'Error', text: res.message || 'Submission failed.', theme: getSwalTheme() });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Connection failed. Please try again.', theme: getSwalTheme() });
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        });
    </script>
</body>
</html>
