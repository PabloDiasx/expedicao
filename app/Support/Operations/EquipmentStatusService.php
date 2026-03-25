<?php

namespace App\Support\Operations;

use App\Enums\ReadResult;
use App\Enums\TransitionResult;
use App\Support\Concerns\NormalizesText;
use Illuminate\Support\Facades\DB;

class EquipmentStatusService
{
    use NormalizesText;
    /**
     * @var array<int, array<int, string>>
     */
    private array $serialPrefixCache = [];

    /**
     * @return array{
     *     result: string,
     *     equipment_id: int|null,
     *     serial_number: string|null,
     *     status_name: string,
     *     status_code: string
     * }
     */
    public function applyBarcodeTransition(
        int $tenantId,
        ?int $userId,
        string $barcode,
        int $toStatusId,
        ?int $sectorId,
        ?string $deviceIdentifier,
        ?string $notes,
        string $eventSource = 'manual'
    ): array {
        $rawScannerCode = trim($barcode);
        $normalizedScannerCode = $this->normalizeScannerCode($rawScannerCode);
        $cleanNotes = $this->normalizeNullableText($notes);
        $cleanDeviceIdentifier = $this->normalizeNullableText($deviceIdentifier);

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $rawScannerCode,
            $normalizedScannerCode,
            $toStatusId,
            $sectorId,
            $cleanDeviceIdentifier,
            $cleanNotes,
            $eventSource
        ): array {
            $now = now();
            $status = DB::table('statuses')
                ->select('id', 'code', 'name')
                ->where('id', $toStatusId)
                ->first();

            if (! $status) {
                return [
                    'result' => TransitionResult::InvalidStatus->value,
                    'equipment_id' => null,
                    'serial_number' => null,
                    'status_name' => '',
                    'status_code' => '',
                ];
            }

            $lookupMode = 'barcode';
            $lookupValue = $normalizedScannerCode;
            $convertedSerial = null;

            $equipment = $this->findEquipmentByBarcode($tenantId, $rawScannerCode, $normalizedScannerCode);

            if (! $equipment) {
                $equipment = $this->findEquipmentBySerial($tenantId, $rawScannerCode, $normalizedScannerCode);
                if ($equipment) {
                    $lookupMode = 'serial';
                    $lookupValue = (string) $equipment->serial_number;
                }
            }

            if (! $equipment) {
                $candidateSerials = $this->extractSerialCandidatesFromScannerCode($tenantId, $normalizedScannerCode);
                if ($candidateSerials !== []) {
                    $lookupMode = 'converted_serial';
                    $lookupValue = $candidateSerials[0];
                    $convertedSerial = $candidateSerials[0];

                    foreach ($candidateSerials as $candidateSerial) {
                        $equipment = $this->findEquipmentBySerial($tenantId, $candidateSerial, $candidateSerial);
                        if ($equipment) {
                            $lookupValue = $candidateSerial;
                            $convertedSerial = $candidateSerial;
                            break;
                        }
                    }
                }
            }

            $payload = $this->encodePayload([
                'requested_status_id' => $toStatusId,
                'requested_sector_id' => $sectorId,
                'event_source' => $eventSource,
                'scanner_input' => $rawScannerCode,
                'lookup_mode' => $lookupMode,
                'lookup_value' => $lookupValue,
                'converted_serial' => $convertedSerial,
            ]);

            if (! $equipment) {
                DB::table('barcode_reads')->insert([
                    'tenant_id' => $tenantId,
                    'equipment_id' => null,
                    'barcode_value' => $rawScannerCode,
                    'sector_id' => $sectorId,
                    'user_id' => $userId,
                    'device_identifier' => $cleanDeviceIdentifier,
                    'read_result' => ReadResult::NotFound->value,
                    'payload' => $payload,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [
                    'result' => TransitionResult::NotFound->value,
                    'equipment_id' => null,
                    'serial_number' => null,
                    'status_name' => (string) $status->name,
                    'status_code' => (string) $status->code,
                ];
            }

            $fromStatusId = $equipment->current_status_id ? (int) $equipment->current_status_id : null;
            $fromSectorId = $equipment->current_sector_id ? (int) $equipment->current_sector_id : null;
            $isSameTransition = $fromStatusId === $toStatusId && $fromSectorId === $sectorId;
            $finalNotes = $this->buildTransitionNotes(
                notes: $cleanNotes,
                rawScannerCode: $rawScannerCode,
                convertedSerial: $convertedSerial
            );

            DB::table('barcode_reads')->insert([
                'tenant_id' => $tenantId,
                'equipment_id' => (int) $equipment->id,
                'barcode_value' => $rawScannerCode,
                'sector_id' => $sectorId,
                'user_id' => $userId,
                'device_identifier' => $cleanDeviceIdentifier,
                'read_result' => $isSameTransition ? ReadResult::NoChange->value : ReadResult::Matched->value,
                'payload' => $payload,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($isSameTransition) {
                return [
                    'result' => TransitionResult::NoChange->value,
                    'equipment_id' => (int) $equipment->id,
                    'serial_number' => (string) $equipment->serial_number,
                    'status_name' => (string) $status->name,
                    'status_code' => (string) $status->code,
                ];
            }

            $updates = [
                'current_status_id' => $toStatusId,
                'current_sector_id' => $sectorId,
                'updated_at' => $now,
            ];

            if ((string) $status->code === 'produzindo' && ! $equipment->manufactured_at) {
                $updates['manufactured_at'] = $now->toDateString();
            }

            if ((string) $status->code === 'montado' && ! $equipment->assembled_at) {
                $updates['assembled_at'] = $now->toDateString();
            }

            DB::table('equipments')
                ->where('id', $equipment->id)
                ->where('tenant_id', $tenantId)
                ->update($updates);

            DB::table('status_histories')->insert([
                'tenant_id' => $tenantId,
                'equipment_id' => (int) $equipment->id,
                'from_status_id' => $fromStatusId,
                'to_status_id' => $toStatusId,
                'sector_id' => $sectorId,
                'user_id' => $userId,
                'event_source' => $eventSource,
                'notes' => $finalNotes,
                'changed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'result' => TransitionResult::Updated->value,
                'equipment_id' => (int) $equipment->id,
                'serial_number' => (string) $equipment->serial_number,
                'status_name' => (string) $status->name,
                'status_code' => (string) $status->code,
            ];
        });
    }

    /**
     * Convert a scanner barcode to the best serial candidate.
     * Returns the first matching serial or null if no conversion applies.
     */
    public function convertScannerCode(int $tenantId, string $scannerCode): ?string
    {
        $normalized = $this->normalizeScannerCode(trim($scannerCode));
        if ($normalized === '') {
            return null;
        }

        $candidates = $this->extractSerialCandidatesFromScannerCode($tenantId, $normalized);

        return $candidates !== [] ? $candidates[0] : null;
    }

    private function findEquipmentByBarcode(int $tenantId, string $rawScannerCode, string $normalizedScannerCode): ?object
    {
        return DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($rawScannerCode, $normalizedScannerCode): void {
                $query->where('barcode', $rawScannerCode);
                if ($normalizedScannerCode !== $rawScannerCode) {
                    $query->orWhere('barcode', $normalizedScannerCode);
                }
            })
            ->lockForUpdate()
            ->first();
    }

    private function findEquipmentBySerial(int $tenantId, string $rawSerial, string $normalizedSerial): ?object
    {
        return DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($rawSerial, $normalizedSerial): void {
                $query->where('serial_number', $rawSerial);
                if ($normalizedSerial !== $rawSerial) {
                    $query->orWhere('serial_number', $normalizedSerial);
                }
            })
            ->lockForUpdate()
            ->first();
    }

    /** Known model prefixes sorted longest-first for greedy match. */
    private const KNOWN_MODELS = [
        'V2CROSS', 'V5XT', 'V5PT',
        'V8X', 'V5P', 'V5X', 'V4N', 'V2R', 'V12',
        'V1', 'V2', 'V4', 'V5', 'V8',
    ];

    /**
     * Extract model prefix from a barcode string.
     * Tries known models first, then regex fallback.
     */
    private function extractModelFromBarcode(string $barcode): ?string
    {
        $upper = mb_strtoupper($barcode);
        foreach (self::KNOWN_MODELS as $model) {
            if (str_starts_with($upper, $model)) {
                return $model;
            }
        }
        // Fallback: V + digits + optional trailing letters (e.g. V8X, V4N)
        if (preg_match('/^(V[0-9]{1,2}[A-Z]*)/', $upper, $m) === 1) {
            return $m[1];
        }
        // Generic fallback
        if (preg_match('/^([A-Z]+[0-9]+[A-Z]*)/', $upper, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function extractSerialCandidatesFromScannerCode(int $tenantId, string $scannerCode): array
    {
        $candidates = [];

        if ($scannerCode === '') {
            return [];
        }

        // Already in final format: MODEL.YY.SERIAL
        if (preg_match('/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/', $scannerCode) === 1) {
            $candidates[$scannerCode] = true;
        }

        // Dashed format: {MODEL}{STUFF}-{YY}.{SERIAL}
        if (preg_match('/-([0-9]{2})\.([0-9]{1,8})$/', $scannerCode, $tailMatches) === 1) {
            $year = $tailMatches[1];
            $serialRaw = $tailMatches[2];
            $serialTrimmed = ltrim($serialRaw, '0');
            $serialTrimmed = $serialTrimmed === '' ? '0' : $serialTrimmed;

            $model = $this->extractModelFromBarcode($scannerCode);
            if ($model !== null) {
                $candidates[$model.'.'.$year.'.'.$serialTrimmed] = true;
                if ($serialRaw !== $serialTrimmed) {
                    $candidates[$model.'.'.$year.'.'.$serialRaw] = true;
                }
            }

            // Also try prefixes from existing equipment
            foreach ($this->loadSerialPrefixesForTenant($tenantId) as $prefix) {
                if (str_starts_with($scannerCode, $prefix)) {
                    $candidates[$prefix.'.'.$year.'.'.$serialTrimmed] = true;
                }
            }
        }

        // Compact format: {MODEL}{LETTERS}{YY}{SERIAL} (no dashes)
        if ($candidates === []) {
            $model = $this->extractModelFromBarcode($scannerCode);
            if ($model !== null) {
                $rest = substr($scannerCode, strlen($model));
                if (preg_match('/[A-Z]+([0-9]{2})([0-9]{2,8})$/', $rest, $compactMatches) === 1) {
                    $year = $compactMatches[1];
                    $serial = ltrim($compactMatches[2], '0');
                    $serial = $serial === '' ? '0' : $serial;
                    $candidates[$model.'.'.$year.'.'.$serial] = true;
                }
            }
        }

        return array_values(array_keys($candidates));
    }

    /**
     * @return array<int, string>
     */
    private function loadSerialPrefixesForTenant(int $tenantId): array
    {
        if (isset($this->serialPrefixCache[$tenantId])) {
            return $this->serialPrefixCache[$tenantId];
        }

        $prefixes = DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->where('serial_number', 'like', '%.%.%')
            ->distinct()
            ->limit(5000)
            ->pluck('serial_number')
            ->map(static function ($value): string {
                $serial = trim((string) $value);
                $dotPos = strpos($serial, '.');

                return $dotPos !== false ? substr($serial, 0, $dotPos) : $serial;
            })
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        usort($prefixes, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        $this->serialPrefixCache[$tenantId] = $prefixes;

        return $prefixes;
    }

    private function buildTransitionNotes(?string $notes, string $rawScannerCode, ?string $convertedSerial): ?string
    {
        if ($convertedSerial === null) {
            return $notes;
        }

        $conversionNote = sprintf(
            'Codigo lido: %s | Serial convertido: %s',
            $rawScannerCode,
            $convertedSerial
        );

        if ($notes === null) {
            return $conversionNote;
        }

        return $notes.' | '.$conversionNote;
    }

    private function encodePayload(array $payload): ?string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }
}
