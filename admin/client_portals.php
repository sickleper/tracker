<?php
$pageTitle = "Client Portals";
require_once '../config.php';
require_once '../tracker_data.php';

if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$superAdminEmail = $GLOBALS['super_admin_email'] ?? 'websites.dublin@gmail.com';
if (($_SESSION['email'] ?? '') !== $superAdminEmail) {
    header('Location: ../index.php');
    exit();
}

include '../header.php';
include '../nav.php';
?>

<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h1 class="heading-brand">Client Work Order Portals</h1>
            <p class="text-gray-500 dark:text-gray-400 font-bold text-xs uppercase tracking-widest mt-1">Onboard each client by matching sender, subject work order, and PDF parsing rules.</p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-6 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-50 dark:hover:bg-slate-800 transition-all active:scale-95 shadow-xl flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back To Admin
            </a>
        </div>
    </div>

    <div class="mb-8 rounded-3xl border border-indigo-100 dark:border-indigo-900/50 bg-indigo-50 dark:bg-indigo-950/20 p-5">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600 dark:text-indigo-300">Rules-Driven Onboarding</div>
                <p class="mt-2 text-sm font-medium text-indigo-900 dark:text-indigo-100">Each portal now defines sender matching, subject work order extraction, and optional PDF parsing in one place.</p>
            </div>
        </div>
    </div>

    <div class="card-base border-none">
        <div class="section-header">
            <h3>
                <i class="fas fa-user-shield text-indigo-400 mr-2"></i> Client Portal Directory
            </h3>
            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Dedicated admin page for onboarding and editing work-order intake rules.</p>
        </div>
        <div class="table-container">
            <table class="w-full text-sm">
                <thead class="table-header-row">
                    <tr>
                        <th class="px-6 py-4 text-left">Client Name</th>
                        <th class="px-6 py-4 text-left">PO Prefix</th>
                        <th class="px-6 py-4 text-left">Intake Rules</th>
                        <th class="px-6 py-4 text-left">Dest. Email</th>
                        <th class="px-6 py-4 text-center">Form Enabled</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="clientPortalsBody" class="divide-y divide-gray-50 dark:divide-slate-800 bg-white dark:bg-slate-900/20"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="clientPortalModal" class="fixed inset-0 z-[100] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden flex flex-col border border-gray-100 dark:border-slate-800">
            <div class="bg-indigo-600 p-6 flex items-center justify-between text-white">
                <h3 class="font-black uppercase italic tracking-wider flex items-center gap-3 text-lg">
                    <i class="fas fa-user-shield text-indigo-200"></i> Client Portal Configuration
                </h3>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="openPortalHelpModal()" class="px-3 py-2 bg-white/10 hover:bg-white/20 rounded-xl transition-all text-[10px] font-black uppercase tracking-widest text-white">
                        <i class="fas fa-circle-question mr-1"></i> Help
                    </button>
                    <button type="button" onclick="closeClientPortalModal()" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
                </div>
            </div>
            <form id="clientPortalForm" class="p-8 space-y-8 overflow-y-auto max-h-[80vh] custom-scrollbar">
                <input type="hidden" name="client_id" id="portalClientId">

                <div class="p-4 bg-indigo-50 dark:bg-indigo-950/30 rounded-2xl border border-indigo-100 dark:border-indigo-900/50">
                    <label class="block text-[9px] font-black uppercase tracking-widest text-indigo-400 mb-1">Your Unique Portal URL:</label>
                    <div class="flex items-center justify-between gap-4">
                        <code id="portalUrlDisplay" class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 truncate"></code>
                        <button type="button" onclick="copyPortalUrl()" class="px-3 py-1.5 bg-white dark:bg-slate-800 border border-indigo-200 dark:border-indigo-900 text-[9px] font-black uppercase rounded-lg hover:bg-indigo-600 hover:text-white transition-all">Copy</button>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 p-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[9px] font-black uppercase tracking-[0.25em] text-slate-500 dark:text-slate-400">
                        <button type="button" class="wizard-step-indicator rounded-2xl bg-indigo-600 text-white px-3 py-3" data-step-target="1">1. Access</button>
                        <button type="button" class="wizard-step-indicator rounded-2xl bg-slate-200 dark:bg-slate-800 px-3 py-3" data-step-target="2">2. Intake</button>
                        <button type="button" class="wizard-step-indicator rounded-2xl bg-slate-200 dark:bg-slate-800 px-3 py-3" data-step-target="3">3. Extract</button>
                        <button type="button" class="wizard-step-indicator rounded-2xl bg-slate-200 dark:bg-slate-800 px-3 py-3" data-step-target="4">4. Review</button>
                    </div>
                </div>

                <div class="wizard-step-panel" data-wizard-step="1">
                    <div class="space-y-6">
                        <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 pb-2 border-b border-gray-100 dark:border-slate-800">1. Portal Access & Branding</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-slate-950 rounded-2xl border border-gray-200 dark:border-slate-800">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_wo_form_enabled" id="portalEnabled" value="1" class="sr-only peer">
                                    <div class="w-11 h-6 bg-gray-200 dark:bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                </label>
                                <span class="text-xs font-black uppercase tracking-widest text-gray-500">Enable Custom WO Form</span>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Company Logo URL</label>
                                <input type="text" name="logo_url" id="portalLogo" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wizard-step-panel hidden" data-wizard-step="2">
                    <div class="space-y-6">
                        <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500 pb-2 border-b border-gray-100 dark:border-slate-800">2. Choose Intake Mode</h4>
                        <div class="mb-6 p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-100 dark:border-indigo-500/20">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-200 mb-2 ml-1">Intake Type</label>
                            <select id="portalIntakeMode" class="w-full p-4 bg-white dark:bg-slate-950 border border-indigo-200 dark:border-indigo-900 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="email_with_subject_workorder">1. Email + Subject Work Order</option>
                                <option value="email_with_pdf_attachment">2. Email + PDF Attachment</option>
                            </select>
                            <p id="portalIntakeHint" class="text-[9px] text-indigo-700 dark:text-indigo-200 font-bold mt-2 ml-1 italic">Every client must provide a subject rule for the work order number. Choose whether PDFs also need to be parsed.</p>
                        </div>
                    </div>
                </div>

                <div class="wizard-step-panel hidden" data-wizard-step="3">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between gap-4 pb-2 border-b border-gray-100 dark:border-slate-800">
                            <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500">3. Configure Rules</h4>
                            <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Only the fields needed for this intake mode are shown</div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Match From Email / Domain</label>
                                <input type="text" name="match_from_email" id="portalMatchEmail" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. cilldarahousing.ie, invoices@client.com">
                            </div>
                            <div data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Saved Sample Sender</label>
                                <input type="text" name="sample_sender" id="portalSavedSampleSender" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. repairs@client.com">
                            </div>
                            <div data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Subject Work Order Regex</label>
                                <input type="text" name="workorder_pattern" id="portalWoPattern" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. /(WO\\d{4,}|SPO\\d{4,})/i">
                                <p class="text-[9px] text-gray-400 font-bold mt-2 ml-1 italic">Matched against the subject to find the work order number.</p>
                                <p id="portalRegexValidation" class="text-[9px] font-bold mt-2 ml-1 text-gray-400 italic">Regex syntax has not been checked yet.</p>
                            </div>
                            <div data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Saved Sample Subject</label>
                                <input type="text" name="sample_subject" id="portalSavedSampleSubject" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Work Order WO009991 Raised">
                            </div>
                            <div data-intake-field="email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">PDF Parser</label>
                                <select name="pdf_profile" id="portalPdfProfile" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                                    <option value="">No PDF parsing</option>
                                    <option value="markers">PDF markers only</option>
                                    <option value="joblogic">JobLogic field map</option>
                                </select>
                                <p class="text-[9px] text-gray-400 font-bold mt-2 ml-1 italic">Choose how attached PDFs should be read when the email has a work-order document.</p>
                            </div>
                            <div data-intake-field="email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">PDF Start Marker</label>
                                <input type="text" name="pdf_start_marker" id="portalPdfStart" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Order Date:">
                            </div>
                            <div data-intake-field="email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">PDF End Marker</label>
                                <input type="text" name="pdf_end_marker" id="portalPdfEnd" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g. Additional Instructions:">
                            </div>
                            <div data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">PO Prefix (e.g. ASPEN-)</label>
                                <input type="text" name="wo_prefix" id="portalPrefix" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Destination Email (for all portals)</label>
                                <input type="email" id="portalDestEmail" readonly class="w-full p-4 bg-gray-100 dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl text-sm font-bold text-gray-500 dark:text-gray-400 outline-none" title="This is configured globally in Global Settings">
                                <p class="text-[9px] text-indigo-500 font-bold mt-2 ml-1 italic">Managed in Global Settings under Workorder Extraction Email.</p>
                            </div>
                            <div class="md:col-span-2" data-intake-field="email_with_subject_workorder email_with_pdf_attachment">
                                <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400 mb-2 ml-1">Rule Change Note</label>
                                <textarea name="rule_note" id="portalRuleNote" rows="3" class="w-full p-4 bg-gray-50 dark:bg-slate-950 border border-gray-200 dark:border-slate-800 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Why was this rule changed?"></textarea>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20 p-5 space-y-5">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-700 dark:text-emerald-300">Live Rule Tester</div>
                                    <p class="mt-2 text-sm text-emerald-900 dark:text-emerald-100">Paste a sample sender and sample subject to verify that this client rule will match and extract the work order number.</p>
                                </div>
                                <button type="button" id="runPortalRuleTestBtn" class="rounded-2xl bg-emerald-600 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-700">
                                    Run Test
                                </button>
                                <button type="button" id="useSavedSamplesBtn" class="rounded-2xl bg-white dark:bg-slate-900 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900/40 hover:bg-emerald-100 dark:hover:bg-slate-800">
                                    Use Saved Samples
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300 mb-2 ml-1">Sample Sender</label>
                                    <input type="text" id="portalSampleSender" class="w-full p-4 bg-white dark:bg-slate-950 border border-emerald-200 dark:border-emerald-900/40 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. repairs@client.com">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300 mb-2 ml-1">Sample Subject</label>
                                    <input type="text" id="portalSampleSubject" class="w-full p-4 bg-white dark:bg-slate-950 border border-emerald-200 dark:border-emerald-900/40 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-emerald-500" placeholder="e.g. Work Order WO009991 Raised">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="rounded-2xl bg-white dark:bg-slate-950 border border-emerald-200 dark:border-emerald-900/40 p-4">
                                    <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Sender Match</div>
                                    <div id="portalSenderTestResult" class="mt-2 text-sm font-black text-slate-900 dark:text-white">Not tested</div>
                                </div>
                                <div class="rounded-2xl bg-white dark:bg-slate-950 border border-emerald-200 dark:border-emerald-900/40 p-4">
                                    <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Subject Regex</div>
                                    <div id="portalPatternTestResult" class="mt-2 text-sm font-black text-slate-900 dark:text-white">Not tested</div>
                                </div>
                                <div class="rounded-2xl bg-white dark:bg-slate-950 border border-emerald-200 dark:border-emerald-900/40 p-4">
                                    <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Extracted Work Order</div>
                                    <div id="portalExtractedPoResult" class="mt-2 text-sm font-black text-slate-900 dark:text-white">Not tested</div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-amber-200 dark:border-amber-900/40 bg-amber-50 dark:bg-amber-950/20 p-5 space-y-5 hidden" id="portalPdfTestPanel">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-[10px] font-black uppercase tracking-[0.25em] text-amber-700 dark:text-amber-300">PDF Test Preview</div>
                                    <p class="mt-2 text-sm text-amber-900 dark:text-amber-100">Upload a sample work-order PDF to preview the parsed text and any extracted JobLogic fields using the current wizard settings.</p>
                                </div>
                                <button type="button" id="runPortalPdfTestBtn" class="rounded-2xl bg-amber-600 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-amber-700">
                                    Run PDF Test
                                </button>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300 mb-2 ml-1">Sample PDF</label>
                                    <input type="file" id="portalSamplePdf" accept="application/pdf" class="w-full p-4 bg-white dark:bg-slate-950 border border-amber-200 dark:border-amber-900/40 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-amber-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300 mb-2 ml-1">Optional Work Order</label>
                                    <input type="text" id="portalPdfTestPo" class="w-full p-4 bg-white dark:bg-slate-950 border border-amber-200 dark:border-amber-900/40 rounded-2xl text-sm font-bold dark:text-white outline-none focus:ring-2 focus:ring-amber-500" placeholder="e.g. WO009991">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="rounded-2xl bg-white dark:bg-slate-950 border border-amber-200 dark:border-amber-900/40 p-4">
                                    <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Parsed Text Preview</div>
                                    <pre id="portalPdfTestText" class="mt-3 whitespace-pre-wrap text-xs text-slate-700 dark:text-slate-300 max-h-72 overflow-y-auto">No PDF test run yet.</pre>
                                </div>
                                <div class="rounded-2xl bg-white dark:bg-slate-950 border border-amber-200 dark:border-amber-900/40 p-4">
                                    <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Field Map Preview</div>
                                    <pre id="portalPdfTestFieldMap" class="mt-3 whitespace-pre-wrap text-xs text-slate-700 dark:text-slate-300 max-h-72 overflow-y-auto">No field map yet.</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wizard-step-panel hidden" data-wizard-step="4">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between gap-4 pb-2 border-b border-gray-100 dark:border-slate-800">
                            <h4 class="text-xs font-black uppercase tracking-widest text-indigo-500">4. Review & Save</h4>
                            <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Check the summary before you save</div>
                        </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 space-y-4">
                                <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Portal Summary</div>
                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Enabled</span><span id="reviewEnabled" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Brand logo</span><span id="reviewLogo" class="font-black text-slate-900 dark:text-white truncate max-w-[12rem]"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Intake mode</span><span id="reviewMode" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Strategy engine</span><span class="font-black text-slate-900 dark:text-white">Rules-driven</span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Subject regex</span><span id="reviewPattern" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Last updated by</span><span id="reviewRuleUpdatedBy" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Last updated at</span><span id="reviewRuleUpdatedAt" class="font-black text-slate-900 dark:text-white"></span></div>
                                </div>
                            </div>
                            <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-5 space-y-4">
                                <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">Extraction Rules</div>
                                <div class="space-y-3 text-sm">
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Match sender/domain</span><span id="reviewMatchEmail" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">PO prefix</span><span id="reviewPrefix" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">PDF parser</span><span id="reviewPdfProfile" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">PDF markers</span><span id="reviewPdfMarkers" class="font-black text-slate-900 dark:text-white"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Destination email</span><span id="reviewDestEmail" class="font-black text-slate-900 dark:text-white truncate max-w-[12rem]"></span></div>
                                    <div class="flex justify-between gap-4"><span class="text-slate-500 dark:text-slate-400">Rule note</span><span id="reviewRuleNote" class="font-black text-slate-900 dark:text-white text-right"></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-[9px] font-black uppercase tracking-[0.25em] text-slate-400">
                            <span id="wizardStepLabel">Step 1 of 4</span>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="wizardBackBtn" class="rounded-2xl bg-white dark:bg-slate-800 px-4 py-3 text-[10px] font-black uppercase tracking-widest text-slate-500 border border-slate-200 dark:border-slate-700 disabled:opacity-40" disabled>Back</button>
                            <button type="button" id="wizardNextBtn" class="rounded-2xl bg-indigo-600 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-indigo-700">Next</button>
                            <button type="submit" id="wizardSaveBtn" class="hidden rounded-2xl bg-emerald-600 px-5 py-3 text-[10px] font-black uppercase tracking-widest text-white hover:bg-emerald-700">Save Portal Settings</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="portalHelpModal" class="fixed inset-0 z-[110] hidden overflow-y-auto bg-black/60 backdrop-blur-md">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-3xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800">
            <div class="bg-slate-900 dark:bg-slate-950 p-6 flex items-center justify-between text-white">
                <div>
                    <h3 class="font-black uppercase tracking-widest text-lg">Wizard Help</h3>
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-300 mt-1">How to onboard a client work-order rule</p>
                </div>
                <button type="button" onclick="closePortalHelpModal()" class="w-8 h-8 flex items-center justify-center bg-white/10 hover:bg-white/20 rounded-full transition-all text-white">&times;</button>
            </div>
            <div class="p-8 space-y-6 max-h-[80vh] overflow-y-auto custom-scrollbar">
                <div class="rounded-3xl border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50 dark:bg-indigo-950/20 p-5">
                    <div class="text-[10px] font-black uppercase tracking-[0.25em] text-indigo-600 dark:text-indigo-300">Core Rule</div>
                    <p class="mt-2 text-sm font-medium text-indigo-900 dark:text-indigo-100">Every client must match a sender/domain and extract the work order number from the email subject. PDF rules are optional and only enrich the import data.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/30 p-5">
                        <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Step 1. Match Sender</div>
                        <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">Use the sender or domain that identifies the client.</p>
                        <div class="mt-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                            cilldarahousing.ie
                        </div>
                        <div class="mt-2 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                            repairs@icarehousing.ie
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/30 p-5">
                        <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">Step 2. Subject Regex</div>
                        <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">Match the work order number exactly as it appears in the subject.</p>
                        <div class="mt-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                            /(WO\d{4,}|SPO\d{4,})/i
                        </div>
                        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">Example subject: <span class="font-mono">Work Order WO009991 Raised</span></div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/30 p-5">
                    <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">When To Use PDF Parsing</div>
                    <div class="mt-4 space-y-4 text-sm text-slate-700 dark:text-slate-300">
                        <div>
                            <div class="font-black uppercase tracking-widest text-[10px] text-slate-500">Email + Subject Work Order</div>
                            <p class="mt-1">Use this when the subject gives the work order number and the email body has enough address/contact/description data.</p>
                        </div>
                        <div>
                            <div class="font-black uppercase tracking-widest text-[10px] text-slate-500">Email + PDF Attachment</div>
                            <p class="mt-1">Use this when the subject gives the work order number but the site/contact/details need to be read from an attached work-order PDF.</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/30 p-5">
                        <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">PDF Parser: JobLogic</div>
                        <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">Use when the PDF has labels like <span class="font-mono">Address:</span>, <span class="font-mono">Tenant:</span>, <span class="font-mono">Mobile:</span>, <span class="font-mono">Description</span>.</p>
                    </div>
                    <div class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/30 p-5">
                        <div class="text-[10px] font-black uppercase tracking-[0.25em] text-slate-500">PDF Parser: Markers</div>
                        <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">Use when you need to slice a predictable text block from the PDF.</p>
                        <div class="mt-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                            Start: Order Date:<br>
                            End: Additional Instructions:
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50 dark:bg-emerald-950/20 p-5">
                    <div class="text-[10px] font-black uppercase tracking-[0.25em] text-emerald-700 dark:text-emerald-300">Checklist Before Save</div>
                    <ul class="mt-3 space-y-2 text-sm text-emerald-900 dark:text-emerald-100">
                        <li>1. Sender/domain matches the real incoming email address.</li>
                        <li>2. Subject regex captures the work order number from the subject line.</li>
                        <li>3. PDF parser is only enabled if the client sends a work-order attachment.</li>
                        <li>4. Marker parser has both a start marker and an end marker.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        const text = String(value);
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function setPortalTestResult(elementId, text, tone) {
        const el = document.getElementById(elementId);
        if (!el) return;
        el.textContent = text;
        el.className = 'mt-2 text-sm font-black';
        if (tone === 'success') {
            el.classList.add('text-emerald-600', 'dark:text-emerald-400');
        } else if (tone === 'error') {
            el.classList.add('text-red-600', 'dark:text-red-400');
        } else if (tone === 'warning') {
            el.classList.add('text-amber-600', 'dark:text-amber-400');
        } else {
            el.classList.add('text-slate-900', 'dark:text-white');
        }
    }

    function portalMatchesAnyToken(email, rules) {
        const tokens = String(rules || '').split(/[\n,]+/).map(token => token.trim().toLowerCase()).filter(Boolean);
        const sampleEmail = String(email || '').trim().toLowerCase();
        if (!tokens.length || !sampleEmail) return false;
        return tokens.some(token => sampleEmail.includes(token));
    }

    function buildPortalRegex(pattern) {
        const value = String(pattern || '').trim();
        if (!value) {
            return { ok: false, reason: 'empty' };
        }

        try {
            const regexParts = value.match(/^\/([\s\S]*)\/([a-z]*)$/i);
            const regex = regexParts ? new RegExp(regexParts[1], regexParts[2]) : new RegExp(value);
            return { ok: true, regex };
        } catch (error) {
            return { ok: false, reason: 'invalid', error };
        }
    }

    function updatePortalRegexValidation() {
        const pattern = ($('#portalWoPattern').val() || '').trim();
        const message = $('#portalRegexValidation');
        const input = $('#portalWoPattern');

        input.removeClass('border-red-300 border-emerald-300 dark:border-red-700 dark:border-emerald-700');
        message.removeClass('text-red-600 text-emerald-600 text-amber-600 dark:text-red-400 dark:text-emerald-400 dark:text-amber-400');

        if (!pattern) {
            message.text('Subject regex is required.').addClass('text-amber-600 dark:text-amber-400');
            return;
        }

        const parsed = buildPortalRegex(pattern);
        if (parsed.ok) {
            message.text('Regex syntax is valid.').addClass('text-emerald-600 dark:text-emerald-400');
            input.addClass('border-emerald-300 dark:border-emerald-700');
        } else {
            message.text('Regex syntax is invalid.').addClass('text-red-600 dark:text-red-400');
            input.addClass('border-red-300 dark:border-red-700');
        }
    }

    function setPortalPdfTestResult(text, fieldMap) {
        $('#portalPdfTestText').text(text || 'No parsed text returned.');
        $('#portalPdfTestFieldMap').text(fieldMap || 'No field map returned.');
    }

    function getPortalHealth(client) {
        const isEnabled = !!client.is_wo_form_enabled;
        const hasSender = !!String(client.match_from_email || '').trim();
        const hasSubject = !!String(client.workorder_pattern || '').trim();
        const pdfProfile = String(client.pdf_profile || '').trim();
        const pdfStart = !!String(client.pdf_start_marker || '').trim();
        const pdfEnd = !!String(client.pdf_end_marker || '').trim();

        if (!isEnabled) {
            return { label: 'Portal Disabled', tone: 'slate' };
        }
        if (!hasSender) {
            return { label: 'Missing Sender Rule', tone: 'red' };
        }
        if (!hasSubject) {
            return { label: 'Missing Subject Rule', tone: 'red' };
        }
        if (pdfProfile === 'markers' && (!pdfStart || !pdfEnd)) {
            return { label: 'PDF Markers Incomplete', tone: 'amber' };
        }
        return { label: 'Ready', tone: 'emerald' };
    }

    function getPortalHealthBadge(client) {
        const health = getPortalHealth(client);
        const classMap = {
            emerald: 'bg-emerald-100 text-emerald-700',
            amber: 'bg-amber-100 text-amber-700',
            red: 'bg-red-100 text-red-700',
            slate: 'bg-slate-100 text-slate-600'
        };
        return `<span class="px-2 py-1 ${classMap[health.tone] || classMap.slate} text-[9px] font-black rounded-lg uppercase">${escapeHtml(health.label)}</span>`;
    }

    function runPortalRuleTest() {
        const senderRule = ($('#portalMatchEmail').val() || '').trim();
        const subjectPattern = ($('#portalWoPattern').val() || '').trim();
        const sampleSender = ($('#portalSampleSender').val() || '').trim();
        const sampleSubject = ($('#portalSampleSubject').val() || '').trim();

        const senderMatched = portalMatchesAnyToken(sampleSender, senderRule);
        setPortalTestResult(
            'portalSenderTestResult',
            senderRule ? (senderMatched ? 'Matched' : 'No match') : 'Add sender rule first',
            senderRule ? (senderMatched ? 'success' : 'error') : 'warning'
        );

        if (!subjectPattern) {
            setPortalTestResult('portalPatternTestResult', 'Add subject regex first', 'warning');
            setPortalTestResult('portalExtractedPoResult', 'No extraction', 'warning');
            return;
        }

        try {
            const parsed = buildPortalRegex(subjectPattern);
            if (!parsed.ok) {
                throw new Error('Invalid regex');
            }
            const regex = parsed.regex;
            const matches = sampleSubject ? sampleSubject.match(regex) : null;

            if (!sampleSubject) {
                setPortalTestResult('portalPatternTestResult', 'Add sample subject', 'warning');
                setPortalTestResult('portalExtractedPoResult', 'No extraction', 'warning');
                return;
            }

            if (matches && matches[0]) {
                setPortalTestResult('portalPatternTestResult', 'Matched', 'success');
                setPortalTestResult('portalExtractedPoResult', matches[0].trim().toUpperCase(), 'success');
            } else {
                setPortalTestResult('portalPatternTestResult', 'No match', 'error');
                setPortalTestResult('portalExtractedPoResult', 'No extraction', 'error');
            }
        } catch (error) {
            setPortalTestResult('portalPatternTestResult', 'Invalid regex', 'error');
            setPortalTestResult('portalExtractedPoResult', 'Fix regex syntax', 'error');
        }
    }

    window.getSwalTheme = function() {
        return $('html').hasClass('dark') ? 'dark' : 'default';
    };

    window.getSwalConfig = function() {
        const isDark = $('html').hasClass('dark');
        return {
            background: isDark ? '#0f172a' : '#ffffff',
            color: isDark ? '#f8fafc' : '#1e293b',
            confirmButtonColor: '#4f46e5',
            cancelButtonColor: '#ef4444',
            theme: getSwalTheme()
        };
    };

    window.closeClientPortalModal = function() {
        $('#clientPortalModal').addClass('hidden');
    };

    window.openPortalHelpModal = function() {
        $('#portalHelpModal').removeClass('hidden');
    };

    window.closePortalHelpModal = function() {
        $('#portalHelpModal').addClass('hidden');
    };

    window.loadClientPortals = function() {
        $('#clientPortalsBody').html('<tr><td colspan="6" class="p-0 border-none"><div class="flex flex-col items-center justify-center py-32 bg-white dark:bg-slate-900/20"><i class="fas fa-circle-notch fa-spin text-4xl text-indigo-500 mb-4"></i><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Loading Portals...</p></div></td></tr>');
        $.getJSON('../tracker_clients.php', function(res) {
            if (res.status !== 'success') {
                $('#clientPortalsBody').html('<tr><td colspan="6" class="p-12 text-center text-red-500 font-bold">Failed to load client portals.</td></tr>');
                return;
            }

            if (!Array.isArray(res.data) || res.data.length === 0) {
                $('#clientPortalsBody').html('<tr><td colspan="6" class="p-20 text-center text-gray-400 italic">No clients found.</td></tr>');
                return;
            }

            const promises = res.data.map(client =>
                $.getJSON(`../get_client_details.php?id=${client.id}`)
                    .then(cRes => ({ ok: true, client: cRes.client || client }))
                    .catch(() => ({ ok: false, client }))
            );

            Promise.all(promises).then(results => {
                $('#clientPortalsBody').empty();
                results.forEach(result => {
                    const c = result.client;
                    const statusBadge = c.is_wo_form_enabled
                        ? '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[9px] font-black rounded-lg uppercase">ENABLED</span>'
                        : '<span class="px-2 py-1 bg-gray-100 text-gray-400 text-[9px] font-black rounded-lg uppercase">DISABLED</span>';
                    const healthBadge = getPortalHealthBadge(c);
                    const intakeBadge = c.pdf_profile
                        ? '<span class="px-2 py-1 bg-amber-100 text-amber-700 text-[9px] font-black rounded-lg uppercase">EMAIL + PDF</span>'
                        : '<span class="px-2 py-1 bg-indigo-100 text-indigo-700 text-[9px] font-black rounded-lg uppercase">EMAIL + SUBJECT WO</span>';
                    const auditSummary = [
                        c.rule_updated_by_name ? `By: ${c.rule_updated_by_name}` : null,
                        c.rule_updated_at ? `At: ${c.rule_updated_at}` : null
                    ].filter(Boolean).join(' | ');
                    const ruleSummary = [
                        c.match_from_email ? `Email: ${c.match_from_email}` : null,
                        c.workorder_pattern ? `Subject WO: ${c.workorder_pattern}` : null,
                        c.pdf_profile ? `PDF: ${c.pdf_profile}` : null,
                        c.sample_subject ? `Sample: ${c.sample_subject}` : null
                    ].filter(Boolean).join(' | ');

                    const row = `
                        <tr class="table-row-hover" id="client-row-${c.id}">
                            <td class="px-6 py-4 font-bold text-gray-900 dark:text-gray-100">${escapeHtml(c.name)}</td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 font-mono text-xs">${escapeHtml(c.wo_prefix || '--')}</td>
                            <td class="px-6 py-4">${intakeBadge}<div class="mt-2">${healthBadge}</div><div class="text-[9px] text-gray-400 mt-2">${escapeHtml(ruleSummary || '--')}</div><div class="text-[9px] text-gray-400 mt-2">${escapeHtml(auditSummary || 'No audit data yet')}</div></td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">${escapeHtml(c.wo_destination_email || '--')}</td>
                            <td class="px-6 py-4 text-center">${statusBadge}</td>
                            <td class="px-6 py-4 text-right">
                                <button onclick="editClientPortal(${c.id})" class="text-indigo-600 dark:text-indigo-400 font-black uppercase text-[10px] tracking-widest hover:underline">Configure Portal</button>
                            </td>
                        </tr>`;
                    $('#clientPortalsBody').append(row);
                });
            }).catch(() => {
                $('#clientPortalsBody').html('<tr><td colspan="6" class="p-12 text-center text-red-500 font-bold">Failed to load client portals.</td></tr>');
            });
        }).fail(function() {
            $('#clientPortalsBody').html('<tr><td colspan="6" class="p-12 text-center text-red-500 font-bold">Failed to load client portals.</td></tr>');
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Portal Load Failed', text: 'Failed to load client portals.' });
        });
    };

    window.setPortalIntakeMode = function(mode) {
        const intakeMode = mode || 'email_with_subject_workorder';
        const hintMap = {
            email_with_subject_workorder: 'Match the sender, then extract the work order from the email subject.',
            email_with_pdf_attachment: 'Match the sender, extract the work order from the email subject, then parse the attached PDF.'
        };

        $('#portalIntakeMode').val(intakeMode);
        $('#portalIntakeHint').text(hintMap[intakeMode] || hintMap.email_with_subject_workorder);

        $('[data-intake-field]').each(function() {
            const modes = String($(this).data('intake-field') || '').split(/\s+/).filter(Boolean);
            const show = modes.includes(intakeMode);
            $(this).toggleClass('hidden', !show);
            $(this).find('input, select, textarea').prop('disabled', !show);
        });

        $('#portalPdfTestPanel').toggleClass('hidden', intakeMode !== 'email_with_pdf_attachment');
    };

    window.portalWizardStep = 1;
    window.portalRuleAudit = {
        updatedBy: '',
        updatedAt: ''
    };

    window.getPortalModeLabel = function(mode) {
        const labels = {
            email_with_subject_workorder: 'Email + Subject Work Order',
            email_with_pdf_attachment: 'Email + PDF Attachment'
        };
        return labels[mode] || 'Email + Subject Work Order';
    };

    window.getPdfProfileLabel = function(value) {
        const labels = {
            '': 'No PDF parsing',
            markers: 'Marker-based parser',
            joblogic: 'JobLogic field map'
        };
        return labels[value] || labels[''];
    };

    window.getPortalWizardValidation = function() {
        const intakeMode = $('#portalIntakeMode').val() || 'email_with_subject_workorder';
        const matchFromEmail = ($('#portalMatchEmail').val() || '').trim();
        const pattern = ($('#portalWoPattern').val() || '').trim();
        const pdfProfile = ($('#portalPdfProfile').val() || '').trim();
        const pdfStart = ($('#portalPdfStart').val() || '').trim();
        const pdfEnd = ($('#portalPdfEnd').val() || '').trim();
        const issues = [];

        if (!matchFromEmail) issues.push('Match From Email / Domain is required.');
        if (!pattern) {
            issues.push('Subject Work Order Regex is required for subject-based imports.');
        } else if (!buildPortalRegex(pattern).ok) {
            issues.push('Subject Work Order Regex has invalid syntax.');
        }
        if (intakeMode === 'email_with_pdf_attachment') {
            if (!pdfProfile) issues.push('PDF Parser is required when the client sends a PDF attachment.');
            if (pdfProfile === 'markers' && (!pdfStart || !pdfEnd)) {
                issues.push('PDF Start Marker and PDF End Marker are required for the marker-based parser.');
            }
        }

        return issues;
    };

    window.setPortalReviewSummary = function() {
        const enabled = $('#portalEnabled').is(':checked') ? 'Yes' : 'No';
        const logo = ($('#portalLogo').val() || '').trim() || 'Default logo';
        const intakeMode = $('#portalIntakeMode').val() || 'email_with_subject_workorder';
        const pattern = ($('#portalWoPattern').val() || '').trim() || 'Not set';
        const matchFromEmail = ($('#portalMatchEmail').val() || '').trim() || 'Not set';
        const prefix = ($('#portalPrefix').val() || '').trim() || 'Not set';
        const pdfProfile = $('#portalPdfProfile').val() || '';
        const pdfStart = ($('#portalPdfStart').val() || '').trim();
        const pdfEnd = ($('#portalPdfEnd').val() || '').trim();
        const pdfMarkers = (pdfStart || pdfEnd) ? [pdfStart || 'Start not set', pdfEnd || 'End not set'].join(' → ') : 'Not set';
        const destEmail = ($('#portalDestEmail').val() || '').trim() || 'Managed globally';
        const ruleNote = ($('#portalRuleNote').val() || '').trim() || 'Not set';

        $('#reviewEnabled').text(enabled);
        $('#reviewLogo').text(logo);
        $('#reviewMode').text(window.getPortalModeLabel(intakeMode));
        $('#reviewPattern').text(pattern);
        $('#reviewRuleUpdatedBy').text(window.portalRuleAudit.updatedBy || 'Will update on save');
        $('#reviewRuleUpdatedAt').text(window.portalRuleAudit.updatedAt || 'Will update on save');
        $('#reviewMatchEmail').text(matchFromEmail);
        $('#reviewPrefix').text(prefix);
        $('#reviewPdfProfile').text(window.getPdfProfileLabel(pdfProfile));
        $('#reviewPdfMarkers').text(pdfMarkers);
        $('#reviewDestEmail').text(destEmail);
        $('#reviewRuleNote').text(ruleNote);
    };

    window.setPortalWizardStep = function(step) {
        const maxStep = 4;
        const nextStep = Math.max(1, Math.min(maxStep, parseInt(step, 10) || 1));
        window.portalWizardStep = nextStep;

        $('[data-wizard-step]').each(function() {
            const stepNumber = parseInt($(this).data('wizard-step'), 10);
            $(this).toggleClass('hidden', stepNumber !== nextStep);
        });

        $('.wizard-step-indicator').each(function() {
            const target = parseInt($(this).data('step-target'), 10);
            const active = target === nextStep;
            $(this)
                .toggleClass('bg-indigo-600 text-white', active)
                .toggleClass('bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400', !active);
        });

        $('#wizardStepLabel').text(`Step ${nextStep} of ${maxStep}`);
        $('#wizardBackBtn').prop('disabled', nextStep === 1);
        $('#wizardNextBtn').toggleClass('hidden', nextStep === maxStep);
        $('#wizardSaveBtn').toggleClass('hidden', nextStep !== maxStep);

        if (nextStep === 3) window.setPortalIntakeMode($('#portalIntakeMode').val());
        if (nextStep === 4) window.setPortalReviewSummary();
    };

    window.getPortalIntakeMode = function(c) {
        if (!c) return 'email_with_subject_workorder';
        if (c.pdf_profile || c.pdf_start_marker || c.pdf_end_marker) return 'email_with_pdf_attachment';
        return 'email_with_subject_workorder';
    };

    window.editClientPortal = function(id) {
        $.getJSON(`../get_client_details.php?id=${id}`, function(res) {
            if (!res.success) return;
            const c = res.client;
            $('#portalClientId').val(c.id);
            $('#portalEnabled').prop('checked', !!c.is_wo_form_enabled);
            $('#portalLogo').val(c.logo_url || '');
            $('#portalPrefix').val(c.wo_prefix || '');
            $('#portalMatchEmail').val(c.match_from_email || '');
            $('#portalSavedSampleSender').val(c.sample_sender || '');
            $('#portalWoPattern').val(c.workorder_pattern || '');
            $('#portalSavedSampleSubject').val(c.sample_subject || '');
            $('#portalRuleNote').val(c.rule_note || '');
            $('#portalPdfStart').val(c.pdf_start_marker || '');
            $('#portalPdfEnd').val(c.pdf_end_marker || '');
            $('#portalPdfProfile').val(c.pdf_profile || '');
            window.portalRuleAudit = {
                updatedBy: c.rule_updated_by_name || '',
                updatedAt: c.rule_updated_at || ''
            };
            updatePortalRegexValidation();
            window.setPortalIntakeMode(window.getPortalIntakeMode(c));
            window.setPortalWizardStep(1);

            $.getJSON('../leads/leads_handler.php?action=get_global_settings', function(gsRes) {
                if (gsRes.success) {
                    const emailSetting = (gsRes.data.apis || []).find(s => s.key === 'workorder_extraction_email');
                    $('#portalDestEmail').val(emailSetting ? emailSetting.value : '');
                }
            });

            const cleanAppUrl = window.location.origin + window.location.pathname.replace('/admin/client_portals.php', '');
            $('#portalUrlDisplay').text(`${cleanAppUrl}/public/workorder_form.php?h=${c.hash}`);
            $('#clientPortalModal').removeClass('hidden');
        }).fail(function() {
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to load client portal details.' });
        });
    };

    $('#portalIntakeMode').on('change', function() {
        window.setPortalIntakeMode($(this).val());
    });

    $('#portalEnabled, #portalLogo, #portalMatchEmail, #portalSavedSampleSender, #portalWoPattern, #portalSavedSampleSubject, #portalRuleNote, #portalPdfProfile, #portalPdfStart, #portalPdfEnd, #portalPrefix').on('input change', function() {
        if (window.portalWizardStep === 4) window.setPortalReviewSummary();
    });

    $('#portalWoPattern').on('input change', function() {
        updatePortalRegexValidation();
    });

    $('#runPortalRuleTestBtn').on('click', function() {
        runPortalRuleTest();
    });

    $('#useSavedSamplesBtn').on('click', function() {
        $('#portalSampleSender').val($('#portalSavedSampleSender').val() || '');
        $('#portalSampleSubject').val($('#portalSavedSampleSubject').val() || '');
        runPortalRuleTest();
    });

    $('#runPortalPdfTestBtn').on('click', function() {
        const clientId = $('#portalClientId').val();
        const fileInput = document.getElementById('portalSamplePdf');
        const pdfFile = fileInput?.files?.[0];

        if (!clientId) {
            Swal.fire({ ...getSwalConfig(), icon: 'warning', title: 'Select Client', text: 'Open a client portal record before running the PDF test.' });
            return;
        }

        if (!pdfFile) {
            Swal.fire({ ...getSwalConfig(), icon: 'warning', title: 'Add PDF', text: 'Choose a sample PDF file first.' });
            return;
        }

        const formData = new FormData();
        formData.append('id', clientId);
        formData.append('pdf', pdfFile);
        formData.append('pdf_profile', $('#portalPdfProfile').val() || '');
        formData.append('pdf_start_marker', $('#portalPdfStart').val() || '');
        formData.append('pdf_end_marker', $('#portalPdfEnd').val() || '');
        formData.append('po', $('#portalPdfTestPo').val() || '');

        setPortalPdfTestResult('Running PDF test...', 'Running PDF test...');

        fetch('test_client_pdf_rule.php', {
            method: 'POST',
            body: formData
        })
            .then(async response => {
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'PDF test failed');
                }

                setPortalPdfTestResult(
                    data.refined_text || 'No parsed text returned.',
                    Object.keys(data.field_map || {}).length ? JSON.stringify(data.field_map, null, 2) : 'No field map returned.'
                );
            })
            .catch(error => {
                setPortalPdfTestResult('PDF test failed.', error.message || 'Unknown error');
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'PDF Test Failed', text: error.message || 'Failed to test PDF parsing.' });
            });
    });

    $('#portalSampleSender, #portalSampleSubject, #portalMatchEmail, #portalWoPattern').on('input', function() {
        if ($('#portalSampleSender').val() || $('#portalSampleSubject').val()) {
            runPortalRuleTest();
        }
    });

    $('#wizardBackBtn').on('click', function() {
        window.setPortalWizardStep(window.portalWizardStep - 1);
    });

    $('#wizardNextBtn').on('click', function() {
        const issues = window.getPortalWizardValidation();
        if (window.portalWizardStep === 3 && issues.length) {
            Swal.fire({ ...getSwalConfig(), icon: 'warning', title: 'Rules Incomplete', html: `<div class="text-left">${issues.map(issue => `<div>${escapeHtml(issue)}</div>`).join('')}</div>` });
            return;
        }
        if (window.portalWizardStep === 3) window.setPortalReviewSummary();
        window.setPortalWizardStep(window.portalWizardStep + 1);
    });

    $('.wizard-step-indicator').on('click', function() {
        window.setPortalWizardStep($(this).data('step-target'));
    });

    window.copyPortalUrl = function() {
        const url = $('#portalUrlDisplay').text();
        navigator.clipboard.writeText(url).then(() => {
            Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'URL Copied!', timer: 1000, showConfirmButton: false });
        }).catch(() => {
            Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Copy Failed', text: 'Failed to copy portal URL.' });
        });
    };

    $('#clientPortalForm').submit(function(e) {
        e.preventDefault();
        const issues = window.getPortalWizardValidation();
        if (issues.length) {
            window.setPortalWizardStep(3);
            Swal.fire({ ...getSwalConfig(), icon: 'warning', title: 'Rules Incomplete', html: `<div class="text-left">${issues.map(issue => `<div>${escapeHtml(issue)}</div>`).join('')}</div>` });
            return;
        }

        const intakeMode = $('#portalIntakeMode').val() || 'email_with_subject_workorder';
        const settingsData = {};
        $(this).serializeArray().forEach(item => settingsData[item.name] = item.value);
        settingsData['is_wo_form_enabled'] = $('#portalEnabled').is(':checked') ? 1 : 0;
        settingsData['strategy_key'] = '';
        settingsData['pdf_profile'] = intakeMode === 'email_with_pdf_attachment' ? ($('#portalPdfProfile').val() || '') : '';

        if (intakeMode === 'email_with_subject_workorder') {
            settingsData['pdf_start_marker'] = '';
            settingsData['pdf_end_marker'] = '';
            settingsData['pdf_profile'] = '';
        } else if (settingsData['pdf_profile'] !== 'markers') {
            settingsData['pdf_start_marker'] = '';
            settingsData['pdf_end_marker'] = '';
        }

        $.ajax({
            url: '../save_client_details.php',
            method: 'POST',
            data: { ...settingsData, id: $('#portalClientId').val() },
            success: function(res) {
                if (res.success) {
                    Swal.fire({ ...getSwalConfig(), icon: 'success', title: 'Portal Updated', timer: 1500, showConfirmButton: false });
                    closeClientPortalModal();
                    loadClientPortals();
                } else {
                    Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: res.message || 'Save failed' });
                }
            },
            error: function() {
                Swal.fire({ ...getSwalConfig(), icon: 'error', title: 'Error', text: 'Failed to save portal settings.' });
            }
        });
    });

    loadClientPortals();
    updatePortalRegexValidation();
});
</script>

<?php include '../footer.php'; ?>
