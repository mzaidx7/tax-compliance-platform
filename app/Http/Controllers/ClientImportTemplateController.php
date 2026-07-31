<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ClientImportTemplateController
{
    public function __invoke(string $template): BinaryFileResponse
    {
        Gate::authorize('viewAny', Client::class);

        $file = match ($template) {
            'workbook' => 'TBT Client Master.xlsx',
            'clients' => 'Clients.csv',
            'people' => 'People.csv',
            default => abort(Response::HTTP_NOT_FOUND),
        };

        return response()->download(
            resource_path("import-templates/{$file}"),
            $file,
            ['Cache-Control' => 'private, max-age=300'],
        );
    }
}
