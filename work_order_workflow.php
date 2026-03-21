<?php
require_once "config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: oauth2callback.php');
    exit();
}

$pageTitle = "Work Order System Workflow";
include_once "header.php";
include_once "nav.php";
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-12 text-center">
        <h1 class="text-4xl font-black italic uppercase tracking-tighter text-gray-900 dark:text-white mb-4">Work Order Lifecycle</h1>
        <p class="text-gray-500 dark:text-slate-400 font-bold text-xs uppercase tracking-[0.3em]">Data flow from Request to Invoice</p>
    </div>

    <!-- Diagram Container -->
    <div class="card-base border-none p-10 bg-white dark:bg-slate-900/50 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <div class="px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 rounded-full flex items-center gap-2">
                <i class="fas fa-project-diagram text-indigo-400 text-[10px]"></i>
                <span class="text-[9px] font-black uppercase tracking-widest text-indigo-400">Tracker Architecture Diagram</span>
            </div>
        </div>

        <div class="mermaid flex justify-center py-10">
            graph TD
                %% Styles
                classDef source fill:#f8fafc,stroke:#e2e8f0,stroke-width:1px,color:#64748b;
                classDef ai fill:#6366f1,stroke:#4f46e5,stroke-width:2px,color:#fff;
                classDef tracker fill:#10b981,stroke:#059669,stroke-width:2px,color:#fff;
                classDef finance fill:#f59e0b,stroke:#d97706,stroke-width:2px,color:#fff;
                classDef external fill:#ef4444,stroke:#dc2626,stroke-width:2px,color:#fff;

                %% Sources
                Gmail((Gmail Inbox)) --> |IMAP Fetch| AI_Parser["<b>Google AI (Gemini) Classifier</b><br/>Filtering & Multi-type Extraction"]
                Portal((Client Portal)) --> |Direct Input| WO_Queue["Work Order Queue (Identified)"]
                Manual((Manual Entry)) --> |Admin Input| WO_Queue

                AI_Parser --> |Smart Classification| WO_Queue
                class AI_Parser ai;

                %% Tracker Processing
                WO_Queue --> |One-Click Import| Tracker["<b>Work Order Tracker</b><br/>MySQL Storage"]
                class Tracker tracker;

                Tracker --> |Task Status| Status{Status Update}
                Status --> |Pending/Active| Field["<b>Field Operations</b><br/>Technician Mobile View"]
                Status --> |Complete| Ready[Ready for Invoicing]
                
                %% Worker Loop
                Field --> |<b>Mark Complete</b>| Tracker
                
                Field --> |Assign with Lat/Lng| WhatsApp["<b>WhatsApp Dispatch</b><br/>Includes Eircode & Location"]
                Tracker -.-> |<b>Real-time Sync</b>| Sheets["<b>Google Sheets API</b><br/>Live Client Tracking Sheet"]
                class WhatsApp,Sheets,Field external;

                %% Integration
                Ready --> |Sync Request| Xero_API["<b>Xero API Integration</b>"]
                class Xero_API finance;

                Xero_API --> |Create| Invoice["Invoice in Xero<br/>(Draft/Authorized)"]

                %% Webhook Loop
                Invoice -.-> |<b>Real-time Webhook Update</b>| Tracker

                Invoice --> |Payment| Done((Paid & Closed))

                %% Dependencies
                Tracker -.-> |Sync| Sheets["<b>Google Sheets API</b><br/>Client Tracking Sheet"]
                class WhatsApp,Sheets external;
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fab fa-google"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">AI Gmail Import</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">Automatically scans incoming Gmail messages for work order details, using AI to extract PO numbers, addresses, and task descriptions without manual typing.</p>
        </div>
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fas fa-file-excel"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">Sheets Sync</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">The tracker remains synced with Google Sheets, allowing legacy workflows to coexist with the new database-driven system while ensuring data integrity.</p>
        </div>
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">Xero Integration</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">Convert completed work orders directly into Xero draft invoices with a single click, eliminating billing errors and speeding up the payment cycle.</p>
        </div>
    </div>
    <!-- Detailed Pipeline -->
    <div class="mt-16 space-y-12">
        <div class="text-center">
            <h2 class="text-2xl font-black uppercase italic tracking-tighter text-gray-900 dark:text-white">Detailed Step-by-Step Pipeline</h2>
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-2">The journey of a work order from capture to cash</p>
        </div>

        <div class="grid grid-cols-1 gap-4">
            <!-- Step 1 -->
            <div class="flex flex-col md:flex-row gap-6 items-start p-8 bg-white dark:bg-slate-900 rounded-[2.5rem] border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black italic text-xl shrink-0 shadow-lg shadow-indigo-200 dark:shadow-none">01</div>
                <div class="space-y-3">
                    <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                        Inbox Filtering & AI Classification
                        <span class="px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-[8px] font-black rounded uppercase tracking-widest">Smart Capture</span>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 leading-relaxed">The system automatically filters the Gmail inbox to identify potential Work Orders from various clients. <b>Google AI (Gemini)</b> then classifies the request type and extracts all critical details: PO Number, <b>Eircode</b>, Site Address, and Job Description, handling multiple document formats seamlessly.</p>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="flex flex-col md:flex-row gap-6 items-start p-8 bg-white dark:bg-slate-900 rounded-[2.5rem] border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black italic text-xl shrink-0 shadow-lg shadow-indigo-200 dark:shadow-none">02</div>
                <div class="space-y-3">
                    <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                        One-Click Work Order Import
                        <span class="px-2 py-0.5 bg-green-50 dark:bg-green-900/30 text-green-600 dark:text-green-400 text-[8px] font-black rounded uppercase tracking-widest">Efficiency</span>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 leading-relaxed">Administrators review the list of identified orders. With a <b>single click ("Import")</b>, the validated data is instantly committed to the database, synced to the client's Google Sheet, and moved into the active tracker for assignment.</p>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="flex flex-col md:flex-row gap-6 items-start p-8 bg-white dark:bg-slate-900 rounded-[2.5rem] border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black italic text-xl shrink-0 shadow-lg shadow-indigo-200 dark:shadow-none">03</div>
                <div class="space-y-3">
                    <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                        Dispatch & Worker Feedback
                        <span class="px-2 py-0.5 bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-[8px] font-black rounded uppercase tracking-widest">Live Updates</span>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 leading-relaxed">Admin dispatches the job via <b>WhatsApp</b> with Eircode and GPS links. Workers access their mobile-view to <b>update statuses and mark jobs as Complete</b> in real-time. Every worker action instantly updates the central tracker and triggers a <b>Live Google Sheet Sync</b>, providing clients with immediate visibility into job progress.</p>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="flex flex-col md:flex-row gap-6 items-start p-8 bg-white dark:bg-slate-900 rounded-[2.5rem] border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black italic text-xl shrink-0 shadow-lg shadow-indigo-200 dark:shadow-none">04</div>
                <div class="space-y-3">
                    <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                        Financial Conversion (Xero Sync)
                        <span class="px-2 py-0.5 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 text-[8px] font-black rounded uppercase tracking-widest">Financial</span>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 leading-relaxed">Once a job is marked "Completed," the admin clicks <b>"Generate Xero Invoice."</b> The system maps the PO number and site details into a Xero Draft Invoice. The Work Order status in the tracker is updated to <span class="text-indigo-500 font-bold">"Drafted"</span>.</p>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="flex flex-col md:flex-row gap-6 items-start p-8 bg-white dark:bg-slate-900 rounded-[2.5rem] border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all">
                <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center font-black italic text-xl shrink-0 shadow-lg shadow-indigo-200 dark:shadow-none">05</div>
                <div class="space-y-3">
                    <h3 class="text-lg font-black uppercase tracking-tight text-gray-900 dark:text-white flex items-center gap-3">
                        Automated Reconciliation
                        <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-[8px] font-black rounded uppercase tracking-widest">Webhook</span>
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-slate-400 leading-relaxed">Xero sends a <b>Real-time Webhook</b> notification when the invoice is authorized or paid. The system's background worker (ProcessXeroWebhookJob) receives this, finds the matching Work Order, and automatically updates its status to <span class="text-emerald-500 font-bold">"Paid"</span> or <span class="text-blue-500 font-bold">"Sent"</span>.</p>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Mermaid JS -->
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>
    mermaid.initialize({
        startOnLoad: true,
        theme: $('html').hasClass('dark') ? 'dark' : 'neutral',
        themeVariables: {
            fontFamily: 'Inter, sans-serif',
            fontSize: '12px',
            primaryColor: '#6366f1',
            primaryTextColor: '#fff',
            primaryBorderColor: '#4f46e5',
            lineColor: '#6366f1'
        }
    });
</script>

<?php include_once "footer.php"; ?>
