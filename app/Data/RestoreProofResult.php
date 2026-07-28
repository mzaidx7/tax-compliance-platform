<?php

declare(strict_types=1);

namespace App\Data;

final readonly class RestoreProofResult
{
    /**
     * @param  array<string, int>  $recordCounts
     */
    public function __construct(
        public string $proofId,
        public string $schemaVersion,
        public int $migrationCount,
        public array $recordCounts,
        public string $databaseChecksum,
        public string $privateFileChecksum,
        public string $releaseChecksum,
        public string $frameworkVersion,
        public bool $databaseIntegrityValid,
        public bool $foreignKeysValid,
        public bool $authenticationValid,
        public bool $tenantIsolationValid,
        public bool $operationsTablesPresent,
        public bool $artifactsCleaned,
        public int $durationMilliseconds,
    ) {}

    public function withArtifactsCleaned(): self
    {
        return new self(
            proofId: $this->proofId,
            schemaVersion: $this->schemaVersion,
            migrationCount: $this->migrationCount,
            recordCounts: $this->recordCounts,
            databaseChecksum: $this->databaseChecksum,
            privateFileChecksum: $this->privateFileChecksum,
            releaseChecksum: $this->releaseChecksum,
            frameworkVersion: $this->frameworkVersion,
            databaseIntegrityValid: $this->databaseIntegrityValid,
            foreignKeysValid: $this->foreignKeysValid,
            authenticationValid: $this->authenticationValid,
            tenantIsolationValid: $this->tenantIsolationValid,
            operationsTablesPresent: $this->operationsTablesPresent,
            artifactsCleaned: true,
            durationMilliseconds: $this->durationMilliseconds,
        );
    }

    public function passed(): bool
    {
        return $this->databaseIntegrityValid
            && $this->foreignKeysValid
            && $this->authenticationValid
            && $this->tenantIsolationValid
            && $this->operationsTablesPresent
            && $this->artifactsCleaned;
    }
}
