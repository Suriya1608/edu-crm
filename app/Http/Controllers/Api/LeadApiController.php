<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Notifications\LeadAssignmentNotification;
use App\Services\ManagerLeadAllocator;
use App\Services\LeadDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadApiController extends Controller
{
    public function __construct(private ManagerLeadAllocator $managerLeadAllocator)
    {
    }

    /**
     * GET /api/leads
     * Returns leads scoped to the authenticated user's role.
     * Supports ?status=, ?search=, ?per_page= (max 100).
     */
    public function index(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Lead::with(['assignedUser:id,name', 'followups' => fn($q) => $q->latest()->limit(1)])
            ->select('id', 'lead_code', 'name', 'phone', 'email', 'course_id', 'source', 'status',
                     'assigned_to', 'assigned_by', 'is_duplicate', 'created_at');

        match ($user->role) {
            'admin'      => null, // no scope — sees all
            'manager'    => $query->where('assigned_by', $user->id),
            'telecaller' => $query->where('assigned_to', $user->id),
            default      => $query->whereRaw('0 = 1'), // unknown role sees nothing
        };

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('lead_code', 'like', "%$s%")
                ->orWhere('name', 'like', "%$s%")
                ->orWhere('phone', 'like', "%$s%")
            );
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $leads   = $query->latest()->paginate($perPage);

        return response()->json($leads);
    }

    /**
     * GET /api/leads/{id}
     * Returns a single lead. Enforces role-based ownership.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $lead = Lead::with(['assignedUser:id,name', 'followups'])->findOrFail($id);

        $allowed = match ($user->role) {
            'admin'      => true,
            'manager'    => $lead->assigned_by === $user->id,
            'telecaller' => $lead->assigned_to === $user->id,
            default      => false,
        };

        if (! $allowed) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($lead);
    }

    public function store(Request $request)
    {
        if ($request->header('X-API-KEY') !== env('LEAD_API_KEY')) {
            return response()->json([
                'error' => 'Unauthorized Access',
            ], 403);
        }

        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|string',
            'email' => 'nullable|email',
            'course' => 'nullable|string',
            'source' => 'nullable|string',
        ]);

        $exists = Lead::where('phone', $request->phone)->first();
        if ($exists) {
            return response()->json([
                'message' => 'Lead already exists',
            ]);
        }

        $managerId = $this->managerLeadAllocator->resolveManagerIdForIncomingLead();

        $courseId = $request->filled('course')
            ? Course::where('name', trim($request->course))->value('id')
            : null;

        $lead = Lead::create([
            'lead_code' => $this->generateLeadCode(),
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'course_id' => $courseId,
            'source' => $request->source ?? 'landing_page',
            'assigned_by' => $managerId,
            'status' => LeadDefaults::defaultStatus(),
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => null,
            'type' => 'note',
            'description' => 'Lead captured from API',
            'meta_data' => null,
            'activity_time' => now(),
        ]);

        if ($managerId) {
            $manager = \App\Models\User::find($managerId);
            if ($manager) {
                $manager->notify(new LeadAssignmentNotification(
                    title: 'New Lead Assigned',
                    message: 'Lead ' . $lead->lead_code . ' auto-assigned to you.',
                    link: route('manager.leads.show', encrypt($lead->id)),
                    meta: ['type' => 'lead_assignment', 'lead_id' => $lead->id]
                ));
            }

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => null,
                'type' => 'assignment',
                'description' => "Auto-assigned to manager #{$managerId}",
                'meta_data' => ['manager_id' => $managerId],
                'activity_time' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Lead stored successfully',
        ]);
    }

    private function generateLeadCode(): string
    {
        $prefix = 'SMIT';

        $lastLead = Lead::latest('id')->first();
        $nextNumber = $lastLead ? $lastLead->id + 1 : 1;
        $formattedNumber = str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

        return $prefix . '-' . $formattedNumber;
    }
}
