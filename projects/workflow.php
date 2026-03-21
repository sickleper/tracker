<?php
require_once "../config.php";

// SECURITY: Redirect to login if no valid session/cookie found
if (!isTrackerAuthenticated()) {
    header('Location: ../oauth2callback.php');
    exit();
}

$pageTitle = "System Workflow Diagram";
include_once "../header.php";
include_once "../nav.php";
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="mb-12 text-center">
        <h1 class="text-4xl font-black italic uppercase tracking-tighter text-gray-900 dark:text-white mb-4">Lifecycle of a Project</h1>
        <p class="text-gray-500 dark:text-slate-400 font-bold text-xs uppercase tracking-[0.3em]">From initial contact to final completion</p>
    </div>

    <!-- Diagram Container -->
    <div class="card-base border-none p-10 bg-white dark:bg-slate-900/50 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 right-0 p-4">
            <div class="px-3 py-1 bg-indigo-500/10 border border-indigo-500/20 rounded-full flex items-center gap-2">
                <i class="fas fa-project-diagram text-indigo-400 text-[10px]"></i>
                <span class="text-[9px] font-black uppercase tracking-widest text-indigo-400">Live Architecture Diagram</span>
            </div>
        </div>

        <div class="mermaid flex justify-center py-10">
            graph TD
                %% Styles
                classDef lead fill:#6366f1,stroke:#4f46e5,stroke-width:2px,color:#fff;
                classDef project fill:#10b981,stroke:#059669,stroke-width:2px,color:#fff;
                classDef storage fill:#f8fafc,stroke:#e2e8f0,stroke-width:1px,color:#64748b;
                classDef action fill:#f59e0b,stroke:#d97706,stroke-width:2px,color:#fff;
                classDef dark fill:#0f172a,stroke:#1e293b,stroke-width:1px,color:#94a3b8;

                %% Nodes
                Form((Website Contact Form)) --> |Auto-Import| Lead["<b>Lead Created</b><br/>leads table"]
                Email((Email Inbox Sync)) --> |IMAP Fetch| EmailLead["Email Lead Inbox<br/>email_leads table"]
                EmailLead --> |Manual Review| Lead
                Manual((Manual Entry)) --> |Admin Input| Lead
                
                Lead --> |Status: 1| Qualify{Qualify Lead}
                
                Qualify --> |Email/Follow-up| History["Communication History<br/>email_leads table"]
                
                Qualify --> |Lead Data| AI["<b>AI Proposal Assistant</b><br/>Gemini/GPT Analysis"]
                AI --> |Smart Draft| Proposal["Proposal Created<br/>proposals table"]
                class AI action;

                Brochure["<b>Category Brochure</b><br/>PDF Attachment"] -.-> Sent
                Proposal --> |Email Sent| Sent["<b>Proposal Sent</b><br/>Link shared with Lead"]
                class Brochure storage;

                Sent --> |Lead Reviews| Sign["<b>Proposal Signed</b><br/>Digital Signature Captured"]
                Sign --> |Auto-Convert| Convert("<b>Convert to Project</b>")
                class Convert action;

                subgraph "Conversion Process"
                    Convert --> |Step 1| Client["Create Client User<br/>users + client_details"]
                    Convert --> |Step 2| Project["Initialize Project<br/>projects table"]
                    Convert --> |Step 3| SyncMilestones["<b>Inject Category Milestones</b><br/>project_status_settings Template"]
                end

                Project --> Manage["<b>Project Workspace</b>"]
                Manage --> Team["Assign Team Members<br/>project_members"]
                Manage --> Milestones["Complete Milestones<br/>project_milestones"]
                
                Milestones --> |Toggle Status| Recalc["Recalculate Progress<br/>completion_percent"]
                
                Recalc --> |Percent = 100%| Done((Project Completed))

                %% Class Assignments
                class Lead,History lead;
                class Project,Manage,Done project;
                class Client,Proposal storage;
                class SyncMilestones,Recalc action;
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mt-12">
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fas fa-robot"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">AI Proposals</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">Gemini AI analyzes lead messages and history to generate professional proposal drafts and cost estimations automatically.</p>
        </div>
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fas fa-bolt"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">Automated Milestones</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">When a lead is converted, the system looks up the category's milestone template and injects all steps automatically.</p>
        </div>
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fas fa-sync"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">Dynamic Progress</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">Progress is calculated in real-time. Toggling a milestone triggers a recalculation of the project's total completion percentage.</p>
        </div>
        <div class="card-base border-none p-8">
            <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center rounded-xl mb-4">
                <i class="fas fa-users-cog"></i>
            </div>
            <h4 class="text-sm font-black uppercase tracking-widest text-gray-900 dark:text-white mb-2">Centralized Admin</h4>
            <p class="text-xs text-gray-500 dark:text-slate-400 leading-relaxed font-medium">Administrators can define global milestone templates per category in the System Administration panel.</p>
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

<?php include_once "../footer.php"; ?>
