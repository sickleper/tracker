<div class="space-y-6">
    <section>
        <h4 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-2"><i class="fas fa-search text-blue-500"></i> Navigation & Filtering</h4>
        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
            <li><b>Client Tabs:</b> Filter the list by clicking client names at the top.</li>
            <li><b>Today / 3 Days:</b> Quick filters below the header to see recent job creation.</li>
            <li><b>Show Closed/Cancelled:</b> Reveal completed or cancelled jobs (hidden by default).</li>
            <li><b>Stats:</b> Click the pie chart icon to see a progress overview.</li>
        </ul>
    </section>
    <section>
        <h4 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-2"><i class="fab fa-google text-red-500"></i> Gmail & AI Automation</h4>
        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
            <li><b>Gmail Dashboard:</b> Access it via the red Google icon to see incoming job requests.</li>
            <li><b>AI Extraction:</b> The system automatically reads emails and PDFs to extract Site Addresses, Job Codes, and Contact Names.</li>
            <li><b>Importing:</b> One-click import pre-fills the new order form with all AI-extracted data.</li>
            <li><b>Sequential POs:</b> Clients like Aspen and DND automatically get the next available PO number suggested.</li>
        </ul>
    </section>
    <section>
        <h4 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-2"><i class="fas fa-edit text-orange-500"></i> Managing Work Orders</h4>
        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
            <li><b>Inline Editing:</b> Click and type in most table cells to auto-save changes instantly.</li>
            <li><b>WhatsApp Dispatches:</b> Selecting a user enables the WhatsApp button to send job details to workers.</li>
            <li><b>Invoice Status:</b> Tracks the lifecycle of a job's billing (Drafted, Sent, Paid).</li>
            <li><b>Xero Previews:</b> Clicking "Generate Xero" now shows a full preview of the description and contact details before creating the draft.</li>
            <li><b>Bulk Invoicing:</b> Select multiple jobs (checkboxes) to create a grouped invoice. 
                <ul class="pl-5 mt-1 space-y-1 list-circle">
                    <li>Only <b>Completed</b> or <b>Closed</b> jobs can be selected.</li>
                    <li>Review all itemized descriptions in the preview modal.</li>
                    <li>Set a <b>Custom Global Reference</b> (e.g., "Monthly Maintenance") for the entire batch.</li>
                </ul>
            </li>
            <li><b>Automatic Sync:</b> If an invoice is <b>Paid</b>, <b>Voided</b>, or <b>Deleted</b> in Xero, the tracker updates automatically via webhooks.</li>
        </ul>
    </section>
    <section>
        <h4 class="font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2 mb-2"><i class="fas fa-sync text-green-500"></i> Google Sheets Sync</h4>
        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-disc list-inside">
            <li><b>Auto-Sync:</b> Every table edit is pushed to the client's sheet in real-time.</li>
            <li><b>Full Refresh:</b> Use "Sync Database to Google Sheet" in Client Details to rebuild a client's entire sheet.</li>
        </ul>
    </section>
    <div class="bg-indigo-50 dark:bg-indigo-950/20 p-4 rounded-lg border border-indigo-100 dark:border-indigo-900/30">
        <p class="text-xs text-indigo-700 dark:text-indigo-400 font-medium italic">Tip: Use the History option in the action menu to see who changed what and when.</p>
    </div>
</div>
