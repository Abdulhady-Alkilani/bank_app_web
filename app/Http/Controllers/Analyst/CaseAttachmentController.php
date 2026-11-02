<?php

namespace App\Http\Controllers\Analyst;

use App\Http\Controllers\Controller;
use App\Models\CaseAttachment;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseAttachmentController extends Controller
{
    public function download(Request $request, CaseAttachment $attachment, AuditLogger $audit): StreamedResponse
    {
        Gate::authorize('view', $attachment->case);

        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        $audit->log('case.attachment_downloaded', $attachment->case, [], ['attachment_id' => $attachment->id], $request->user());

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }
}
