<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Mail\CampaignMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
            'name'             => 'required|string|max:255',
            'subject'          => 'required|string|max:255',
            'body'             => 'required|string',
            'blocks_json'      => 'nullable|string',
            'template_type'    => 'nullable|in:builder,simple',
            'status'           => 'required|in:active,inactive',
            'attachments_json' => 'nullable|string',
        ]);

        $data['body']          = $this->sanitizeBody($data['body']);
        $data['created_by']    = Auth::id();
        $data['template_type'] = 'simple';
        $data['blocks_json']   = null;
        $data['attachments']   = $this->decodeAttachments($data['attachments_json'] ?? null);

        unset($data['attachments_json']);

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
            'name'             => 'required|string|max:255',
            'subject'          => 'required|string|max:255',
            'body'             => 'required|string',
            'blocks_json'      => 'nullable|string',
            'template_type'    => 'nullable|in:builder,simple',
            'status'           => 'required|in:active,inactive',
            'attachments_json' => 'nullable|string',
        ]);

        $newAttachments  = $this->decodeAttachments($data['attachments_json'] ?? null);
        $this->pruneRemovedAttachments($emailTemplate->attachments ?? [], $newAttachments);

        $data['body']          = $this->sanitizeBody($data['body']);
        $data['template_type'] = 'simple';
        $data['blocks_json']   = null;
        $data['attachments']   = $newAttachments;

        unset($data['attachments_json']);

        $emailTemplate->update($data);

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Email template updated successfully.');
    }

    // ── Image Upload (AJAX) ───────────────────────────────────────────────────

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp|max:5120',
        ]);

        $path = $request->file('image')->store('email-assets', 'public');
        $url  = rtrim(config('app.url'), '/') . '/storage/' . $path;

        return response()->json([
            'url'  => $url,
            'data' => [['src' => $url]],
        ]);
    }

    // ── Attachment Upload (AJAX) ──────────────────────────────────────────────

    public function uploadAttachment(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,txt,csv,zip|max:51200',
        ]);

        $file         = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $path         = $file->store('email-attachments', 'public');

        return response()->json([
            'path' => $path,
            'name' => $originalName,
            'size' => $file->getSize(),
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
        // Delete attached files from storage
        foreach ($emailTemplate->attachments ?? [] as $att) {
            if (!empty($att['path'])) {
                Storage::disk('public')->delete($att['path']);
            }
        }

        $emailTemplate->delete();

        return redirect()->route('admin.email-templates.index')
            ->with('success', 'Email template deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sanitizeBody(string $body): string
    {
        $body = preg_replace('/<\?(?:php|=)?.*?\?>/is', '', $body);
        $body = preg_replace('/\{!!.*?!!\}/s', '', $body);
        return $body;
    }

    private function decodeAttachments(?string $json): array
    {
        if (empty($json)) {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function pruneRemovedAttachments(array $oldAttachments, array $newAttachments): void
    {
        $newPaths = array_column($newAttachments, 'path');
        foreach ($oldAttachments as $att) {
            if (!empty($att['path']) && !in_array($att['path'], $newPaths, true)) {
                Storage::disk('public')->delete($att['path']);
            }
        }
    }
}
