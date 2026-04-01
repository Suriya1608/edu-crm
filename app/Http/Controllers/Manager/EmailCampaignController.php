<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\Course;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmailCampaignController extends Controller
{
    public function index()
    {
        $campaigns = EmailCampaign::where('created_by', Auth::id())
            ->latest()
            ->paginate(20);

        return view('manager.email-campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        $templates = EmailTemplate::where('status', 'active')->get();
        $courses   = Course::active()->orderBy('sort_order')->orderBy('name')->pluck('name');
        $campaigns = Campaign::where('created_by', Auth::id())
            ->orderByDesc('id')
            ->get(['id', 'name']);

        return view('manager.email-campaigns.create', compact('templates', 'courses', 'campaigns'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string',
            'template_id'       => 'required|exists:email_templates,id',
            'course_filter'     => 'nullable|string|max:255',
            'scheduled_at'      => 'nullable|date|after:now',
            'recipient_emails'  => 'required|array|min:1',
            'recipient_emails.*'=> 'email',
            'recipient_names'   => 'nullable|array',
        ]);

        $template = EmailTemplate::where('status', 'active')->findOrFail($data['template_id']);

        $recipientEmails = array_values(array_unique($data['recipient_emails']));
        $recipientNames  = $data['recipient_names'] ?? [];

        $nameLookup = CampaignContact::whereIn('email', $recipientEmails)
            ->select('email', 'name')
            ->get()
            ->pluck('name', 'email')
            ->toArray();

        $isScheduled = !empty($data['scheduled_at']);

        $campaign = EmailCampaign::create([
            'name'             => $data['name'],
            'description'      => $data['description'] ?? null,
            'template_id'      => $template->id,
            'template_name'    => $template->name,
            'template_subject' => $template->subject,
            'template_body'    => $template->body,
            'course_filter'    => $data['course_filter'] ?? null,
            'scheduled_at'     => $isScheduled ? $data['scheduled_at'] : null,
            'status'           => $isScheduled ? 'scheduled' : 'sending',
            'created_by'       => Auth::id(),
            'recipients_count' => count($recipientEmails),
        ]);

        foreach ($recipientEmails as $i => $email) {
            EmailCampaignRecipient::create([
                'email_campaign_id' => $campaign->id,
                'email'             => $email,
                'name'              => $recipientNames[$i] ?? ($nameLookup[$email] ?? null),
                'tracking_token'    => Str::random(40),
                'status'            => 'pending',
            ]);
        }

        if (!$isScheduled) {
            SendEmailCampaignJob::dispatch($campaign->id);
        }

        return redirect()->route('manager.email-campaigns.show', $campaign)
            ->with('success', $isScheduled
                ? 'Email campaign scheduled for ' . \Carbon\Carbon::parse($data['scheduled_at'])->format('d M Y, h:i A') . '.'
                : 'Email campaign created and queued for sending.');
    }

    public function show(EmailCampaign $emailCampaign)
    {
        if ($emailCampaign->created_by !== Auth::id()) {
            abort(403);
        }

        $recipients = $emailCampaign->recipients()
            ->orderByRaw("FIELD(status,'sent','opened','failed','bounced','pending')")
            ->paginate(50);

        return view('manager.email-campaigns.show', [
            'campaign'   => $emailCampaign,
            'recipients' => $recipients,
        ]);
    }

    public function destroy(EmailCampaign $emailCampaign)
    {
        if ($emailCampaign->created_by !== Auth::id()) {
            abort(403);
        }

        $emailCampaign->delete();

        return redirect()->route('manager.email-campaigns.index')
            ->with('success', 'Email campaign deleted.');
    }

    // AJAX: distinct emails from Leads + Campaign Contacts with filters
    public function emailList(Request $request)
    {
        $source     = $request->get('source', 'all');      // all | leads | campaign_contacts
        $course     = $request->get('course');
        $campaignId = $request->get('campaign_id');

        $results = collect();

        // ── Leads ───────────────────────────────────────────────────────────────
        if ($source === 'all' || $source === 'leads') {
            $query = Lead::with('enrolledCourse')
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->where('assigned_by', Auth::id());

            if ($course && $course !== 'all') {
                $query->whereHas('enrolledCourse', fn($q) => $q->where('name', $course));
            }

            $query->get()->each(function ($lead) use (&$results) {
                $results->push([
                    'email'  => $lead->email,
                    'name'   => $lead->name,
                    'course' => $lead->course ?? '',   // getCourseAttribute accessor
                    'source' => 'Lead',
                ]);
            });
        }

        // ── Campaign Contacts ────────────────────────────────────────────────────
        if ($source === 'all' || $source === 'campaign_contacts') {
            $query = CampaignContact::whereNotNull('email')
                ->where('email', '!=', '');

            if ($course && $course !== 'all') {
                $query->where('course', $course);
            }

            if ($campaignId && $campaignId !== 'all') {
                $query->where('campaign_id', $campaignId);
            }

            $query->get()->each(function ($c) use (&$results) {
                $results->push([
                    'email'  => $c->email,
                    'name'   => $c->name,
                    'course' => $c->course ?? '',
                    'source' => 'Campaign',
                ]);
            });
        }

        return response()->json($results->unique('email')->values());
    }
}
