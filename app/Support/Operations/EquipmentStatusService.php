<?php

namespace App\Support\Operations;

use Illuminate\Support\Facades\DB;

class EquipmentStatusService
{
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
                    'result' => 'invalid_status',
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
                $candidateSerial = $this->extractSerialFromScannerCode($normalizedScannerCode);
                if ($candidateSerial !== null) {
                    $equipment = $this->findEquipmentBySerial($tenantId, $candidateSerial, $candidateSerial);
                    if ($equipment) {
                        $lookupMode = 'converted_serial';
                        $lookupValue = $candidateSerial;
                        $convertedSerial = $candidateSerial;
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
                    'read_result' => 'not_found',
                    'payload' => $payload,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return [
                    'result' => 'not_found',
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
                'read_result' => $isSameTransition ? 'no_change' : 'matched',
                'payload' => $payload,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($isSameTransition) {
                return [
                    'result' => 'no_change',
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
                'result' => 'updated',
                'equipment_id' => (int) $equipment->id,
                'serial_number' => (string) $equipment->serial_number,
                'status_name' => (string) $status->name,
                'status_code' => (string) $status->code,
            ];
        });
    }

    private function normalizeNullableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeScannerCode(string $value): string
    {
        $normalized = mb_strtoupper(trim($value));
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        return $normalized;
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

    private function extractSerialFromScannerCode(string $scannerCode): ?string
    {
        if ($scannerCode === '') {
            return null;
        }

        if (preg_match('/^[A-Z0-9]+\.[0-9]{2}\.[0-9]+$/', $scannerCode) === 1) {
            return $scannerCode;
        }

        if (preg_match('/^([A-Z]+[0-9]+).*-([0-9]{2})\.([0-9]{1,8})$/', $scannerCode, $matches) === 1) {
            $model = $matches[1];
            $year = $matches[2];
            $serial = ltrim($matches[3], '0');
            $serial = $serial === '' ? '0' : $serial;

            return $model.'.'.$year.'.'.$serial;
        }

        if (preg_match('/^([A-Z]+[0-9]+)[A-Z]{1,6}([0-9]{2})([0-9]{2,8})$/', $scannerCode, $matches) === 1) {
            $model = $matches[1];
            $year = $matches[2];
            $serial = ltrim($matches[3], '0');
            $serial = $serial === '' ? '0' : $serial;

            return $model.'.'.$year.'.'.$serial;
        }

        return null;
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
