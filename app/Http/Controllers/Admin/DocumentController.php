<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index()
    {
        $documents = Document::with('uploader')->latest()->get();
        return view('admin.documents.index', compact('documents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file'  => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png|max:20480',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'public');

        Document::create([
            'title'       => $request->title,
            'file_path'   => $path,
            'file_name'   => $file->getClientOriginalName(),
            'file_type'   => $file->getClientMimeType(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function destroy(Document $document)
    {
        Storage::disk('public')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function download(Document $document)
    {
        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    public function view(Document $document)
    {
        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('public')->response($document->file_path, $document->file_name, [
            'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
        ]);
    }

    /**
     * JSON list — accessible to all authenticated roles for the quick-access popup.
     */
    public function list()
    {
        $documents = Document::latest()->get();

        return response()->json([
            'ok'        => true,
            'documents' => $documents->map(fn($d) => [
                'id'                  => $d->id,
                'title'               => $d->title,
                'file_name'           => $d->file_name,
                'file_size_formatted' => $d->file_size_formatted,
                'icon'                => $d->icon,
                'created_at'          => $d->created_at->format('d M Y'),
                'download_url'        => route('documents.download', $d->id),
                'view_url'            => route('documents.view', $d->id),
            ]),
        ]);
    }
}
