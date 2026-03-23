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
        $cleanBarcode = trim($barcode);
        $cleanNotes = $this->normalizeNullableText($notes);
        $cleanDeviceIdentifier = $this->normalizeNullableText($deviceIdentifier);

        return DB::transaction(function () use (
            $tenantId,
            $userId,
            $cleanBarcode,
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

            $equipment = DB::table('equipments')
                ->where('tenant_id', $tenantId)
                ->where('barcode', $cleanBarcode)
                ->lockForUpdate()
                ->first();

            $payload = $this->encodePayload([
                'requested_status_id' => $toStatusId,
                'requested_sector_id' => $sectorId,
                'event_source' => $eventSource,
            ]);

            if (! $equipment) {
                DB::table('barcode_reads')->insert([
                    'tenant_id' => $tenantId,
                    'equipment_id' => null,
                    'barcode_value' => $cleanBarcode,
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

            DB::table('barcode_reads')->insert([
                'tenant_id' => $tenantId,
                'equipment_id' => (int) $equipment->id,
                'barcode_value' => $cleanBarcode,
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
                'notes' => $cleanNotes,
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

    private function encodePayload(array $payload): ?string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }
}
