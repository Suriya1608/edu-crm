<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignContact;
use App\Models\User;
use Illuminate\Http\Request;

class CampaignPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $campaigns   = Campaign::orderBy('name')->get();
        $managers    = User::where('role', 'manager')->orderBy('name')->get();
        $telecallers = User::where('role', 'telecaller')->orderBy('name')->get();

        $query = Campaign::query();

        if ($request->filled('manager')) {
            $query->where('created_by', $request->manager);
        }

        if ($request->filled('campaign')) {
            $query->where('id', $request->campaign);
        }

        $selectedCampaigns = $query->with('contacts')->get();

        $stats = [
            'total_contacts'    => 0,
            'assigned'          => 0,
            'calls_completed'   => 0,
            'whatsapp_sent'     => 0,
            'interested'        => 0,
            'not_interested'    => 0,
            'followups_pending' => 0,
            'converted'         => 0,
        ];

        $perCampaign = [];

        foreach ($selectedCampaigns as $camp) {
            $contactQuery = $camp->contacts();

            if ($request->filled('telecaller')) {
                $contactQuery = $camp->contacts()->where('assigned_to', $request->telecaller);
            }

            if ($request->filled('date_from')) {
                $contactQuery->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $contactQuery->whereDate('created_at', '<=', $request->date_to);
            }

            $allContacts = $contactQuery->get();

            $campStats = [
                'name'              => $camp->name,
                'status'            => $camp->status,
                'manager'           => $camp->createdBy?->name ?? '—',
                'total_contacts'    => $allContacts->count(),
                'assigned'          => $allContacts->whereNotNull('assigned_to')->count(),
                'calls_completed'   => $allContacts->where('call_count', '>', 0)->count(),
                'whatsapp_sent'     => $camp->contacts()
                    ->whereHas('activities', fn($q) => $q->where('type', 'whatsapp'))
                    ->count(),
                'interested'        => $allContacts->where('status', 'interested')->count(),
                'not_interested'    => $allContacts->where('status', 'not_interested')->count(),
                'followups_pending' => $allContacts->whereIn('status', ['callback'])->whereNotNull('next_followup')->count(),
                'converted'         => $allContacts->where('status', 'converted')->count(),
            ];

            $perCampaign[] = $campStats;

            $stats['total_contacts']    += $campStats['total_contacts'];
            $stats['assigned']          += $campStats['assigned'];
            $stats['calls_completed']   += $campStats['calls_completed'];
            $stats['whatsapp_sent']     += $campStats['whatsapp_sent'];
            $stats['interested']        += $campStats['interested'];
            $stats['not_interested']    += $campStats['not_interested'];
            $stats['followups_pending'] += $campStats['followups_pending'];
            $stats['converted']         += $campStats['converted'];
        }

        return view('admin.campaigns.performance', compact(
            'campaigns', 'managers', 'telecallers', 'stats', 'perCampaign'
        ));
    }

    public function contacts(Request $request)
    {
        $query = CampaignContact::with('campaign', 'assignedUser');

        if ($request->filled('campaign')) {
            $query->where('campaign_id', $request->campaign);
        }
        if ($request->filled('telecaller')) {
            $query->where('assigned_to', $request->telecaller);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        $contacts    = $query->latest()->paginate(25)->withQueryString();
        $campaigns   = Campaign::orderBy('name')->get();
        $telecallers = User::where('role', 'telecaller')->orderBy('name')->get();

        return view('admin.campaigns.contacts', compact('contacts', 'campaigns', 'telecallers'));
    }
}
