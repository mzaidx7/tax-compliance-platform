<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Exports\AuthorizeExportDownload;
use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        AuditLog $exportAuditLog,
        AuthorizeExportDownload $authorizeExportDownload,
        TenantStorage $storage,
    ): StreamedResponse {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated member is required.');
        }

        $artifact = $authorizeExportDownload->handle($actor, $exportAuditLog);

        return response()->streamDownload(
            function () use ($storage, $artifact): void {
                $stream = $storage->readStream($artifact->logicalPath);

                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            },
            $artifact->fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Length' => (string) $artifact->bytes,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
