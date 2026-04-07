<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Mail\CampaignMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class EmailTemplateController extends Controller
{
    // ── List Templates ────────────────────────────────────────────────────────

    public function index()
    {
        $templates = EmailTemplate::with('creator')
            ->latest()
            ->paginate(20);

        return view('admin.email-templates.index', compact('templates'));
    }

    // ── Create Form ───────────────────────────────────────────────────────────

    public function create()
    {
        return view('admin.email-templates.create');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'blocks_json'   => 'nullable|string',
            'template_type' => 'nullable|in:builder,simple',
            'status'        => 'required|in:active,inactive',
        ]);

        $data['body']          = $this->sanitizeBody($data['body']);
        $data['created_by']    = Auth::id();
        $data['template_type'] = 'simple';
        $data['blocks_json']   = null;

        EmailTemplate::create($data);

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Email template created successfully.');
    }

    // ── Edit Form ─────────────────────────────────────────────────────────────

    public function edit(EmailTemplate $emailTemplate)
    {
        return view('admin.email-templates.edit', compact('emailTemplate'));
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'subject'       => 'required|string|max:255',
            'body'          => 'required|string',
            'blocks_json'   => 'nullable|string',
            'template_type' => 'nullable|in:builder,simple',
            'status'        => 'required|in:active,inactive',
        ]);

        $data['body']          = $this->sanitizeBody($data['body']);
        $data['template_type'] = 'simple';
        $data['blocks_json']   = null;

        $emailTemplate->update($data);

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Email template updated successfully.');
    }

    // ── Body Sanitisation ─────────────────────────────────────────────────────

    private function sanitizeBody(string $body): string
    {
        // Strip PHP open/close tags so no server-side code can be injected
        $body = preg_replace('/<\?(?:php|=)?.*?\?>/is', '', $body);
        // Strip Laravel raw-output directives {!! ... !!}
        $body = preg_replace('/\{!!.*?!!\}/s', '', $body);
        return $body;
    }

    // ── Image Upload (AJAX) ───────────────────────────────────────────────────

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $path = $request->file('image')->store('email-assets', 'public');
        $url  = rtrim(config('app.url'), '/') . '/storage/' . $path;

        // 'url'  → consumed by our custom eb-email-img fetch handler
        // 'data' → consumed by GrapesJS built-in asset manager ([{src}] format)
        return response()->json([
            'url'  => $url,
            'data' => [['src' => $url]],
        ]);
    }

    // ── Toggle Status ─────────────────────────────────────────────────────────

    public function toggleStatus(EmailTemplate $emailTemplate)
    {
        $emailTemplate->update([
            'status' => $emailTemplate->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Template status updated.');
    }

    // ── Send Test Email (AJAX) ────────────────────────────────────────────────

    public function sendTest(Request $request)
    {
        $request->validate([
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'body'    => 'required|string',
        ]);

        $appUrl = rtrim(config('app.url'), '/');
        $body   = preg_replace('/(<img\b[^>]*\bsrc=")\/(?!\/)/', '$1' . $appUrl . '/', $request->body);

        try {
            Mail::to($request->email)
                ->send(new CampaignMail($request->subject, $body, ''));

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // ── Destroy ───────────────────────────────────────────────────────────────

    public function destroy(EmailTemplate $emailTemplate)
    {
        $emailTemplate->delete();

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Email template deleted.');
    }
}
