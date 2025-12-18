<?php

namespace App\Events;

use App\Models\ScannerAccessLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScannerAccessEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ScannerAccessLog $accessLog;
    public array $logData;

    public function __construct(ScannerAccessLog $accessLog)
    {
        $this->accessLog = $accessLog;
        $this->logData = $this->formatLogData($accessLog);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('gym.' . $this->accessLog->gym_id . '.access-logs'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'scanner.access';
    }

    public function broadcastWith(): array
    {
        return [
            'log' => $this->logData,
        ];
    }

    private function formatLogData(ScannerAccessLog $log): array
    {
        $log->load(['scanner', 'member']);

        return [
            'id' => $log->id,
            'device_number' => $log->device_number,
            'scanner_name' => $log->scanner?->device_name ?? 'Scanner #' . $log->device_number,
            'scan_type' => $log->scan_type,
            'scan_type_label' => $log->scan_type_label,
            'access_granted' => $log->access_granted,
            'status_label' => $log->status_label,
            'denial_reason' => $log->denial_reason,
            'member_id' => $log->member_id,
            'member_name' => $log->member ? trim($log->member->first_name . ' ' . $log->member->last_name) : null,
            'member_number' => $log->member?->member_number,
            'nfc_card_id' => $log->metadata['nfc_card_id'] ?? null,
            'metadata' => $log->metadata,
            'created_at' => $log->created_at->toIso8601String(),
            'formatted_time' => $log->formatted_time,
            'time_ago' => $log->time_ago,
        ];
    }
}
