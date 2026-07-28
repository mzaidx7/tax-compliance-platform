<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters applied to the audit register.
 *
 * This is the single source of truth for register filtering. The read-only
 * viewer and the export action both apply it, so an exported file can never
 * contain a different set of records than the register showed.
 */
final readonly class AuditRegisterFilters
{
    public function __construct(
        public string $search = '',
        public string $action = '',
        public string $fromDate = '',
        public string $toDate = '',
    ) {}

    public static function fromStrings(
        string $search,
        string $action,
        string $fromDate,
        string $toDate,
    ): self {
        return new self(
            search: trim($search),
            action: trim($action),
            fromDate: trim($fromDate),
            toDate: trim($toDate),
        );
    }

    /**
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function apply(Builder $query): Builder
    {
        $search = $this->search;

        return $query
            ->when(
                $this->action !== '',
                fn (Builder $query): Builder => $query->where('action', $this->action),
            )
            ->when(
                $this->fromDate !== '',
                fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $this->fromDate),
            )
            ->when(
                $this->toDate !== '',
                fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $this->toDate),
            )
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(static function (Builder $query) use ($search): void {
                        $query
                            ->where('action', 'like', "%{$search}%")
                            ->orWhere('auditable_id', 'like', "%{$search}%")
                            ->orWhere('correlation_id', 'like', "%{$search}%")
                            ->orWhere('reason', 'like', "%{$search}%");
                    });
                },
            );
    }

    /**
     * Filter metadata retained with an export, without any record contents.
     *
     * @return array<string, string>
     */
    public function toAuditMetadata(): array
    {
        return [
            'search' => $this->search === '' ? 'none' : 'applied',
            'action' => $this->action === '' ? 'all' : $this->action,
            'from_date' => $this->fromDate === '' ? 'none' : $this->fromDate,
            'to_date' => $this->toDate === '' ? 'none' : $this->toDate,
        ];
    }
}
