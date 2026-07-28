<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Documents\AuthorizeDocumentDownload;
use App\Models\DocumentEvidence;
use App\Models\User;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocumentEvidenceDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        DocumentEvidence $documentEvidence,
        AuthorizeDocumentDownload $authorizeDownload,
        TenantStorage $storage,
    ): StreamedResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated member is required.');
        }

        $evidence = $authorizeDownload->handle($actor, $documentEvidence);

        return response()->streamDownload(
            function () use ($storage, $evidence): void {
                $stream = $storage->readStream($evidence->logical_path);

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            $evidence->original_name,
            [
                'Content-Type' => $evidence->detected_mime_type,
                'Content-Length' => (string) $evidence->bytes,
                'Content-Disposition' => 'attachment',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
