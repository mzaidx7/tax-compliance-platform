<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Exports\AuthorizeExportDownload;
use App\Models\AuditLog;
use App\Models\User;
use App\Tenancy\FirmContext;
use App\Tenancy\TenantStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        AuditLog $exportAuditLog,
        AuthorizeExportDownload $authorizeExportDownload,
        TenantStorage $storage,
        RequirePassword $requirePassword,
        FirmContext $firmContext,
    ): Response {
        $actor = $request->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated member is required.');
        }

        if ($exportAuditLog->firm_id === $firmContext->firm()->id && $this->isClientMasterExport($exportAuditLog)) {
            return $requirePassword->handle(
                $request,
                fn (): StreamedResponse => $this->download($actor, $exportAuditLog, $authorizeExportDownload, $storage),
                'password.confirm',
                (string) config('auth.password_timeout'),
            );
        }

        return $this->download($actor, $exportAuditLog, $authorizeExportDownload, $storage);
    }

    private function isClientMasterExport(AuditLog $exportAuditLog): bool
    {
        $fileName = $exportAuditLog->after_values['file_name'] ?? null;

        return $exportAuditLog->action === 'firm.export.created'
            && is_string($fileName)
            && preg_match('/\Aclient-master-[0-9a-z]{26}\.csv\z/', $fileName) === 1;
    }

    private function download(
        User $actor,
        AuditLog $exportAuditLog,
        AuthorizeExportDownload $authorizeExportDownload,
        TenantStorage $storage,
    ): StreamedResponse {
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
