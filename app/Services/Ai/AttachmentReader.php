<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\LeaveRequest;
use Illuminate\Support\Facades\Storage;

/**
 * Spec §12.5: read a medical certificate (image or PDF) on a leave request and suggest the dates, doctor and clinic
 * for HR. It only suggests — nothing is approved or changed automatically.
 */
final class AttachmentReader
{
    public function __construct(private readonly AiGateway $ai) {}

    /** @return array{ok: bool, data?: array<string, mixed>, error?: string} */
    public function read(LeaveRequest $request, int $userId): array
    {
        $path = (string) $request->attachment_path;
        if (! str_starts_with($path, 'leave-attachments/') || str_contains($path, '..') || ! Storage::disk('local')->exists($path)) {
            return ['ok' => false, 'error' => __('No readable attachment.')];
        }
        $bytes = Storage::disk('local')->get($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $block = match ($mime) {
            'application/pdf' => ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($bytes)]],
            'image/jpeg', 'image/png', 'image/webp', 'image/gif' => ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mime, 'data' => base64_encode($bytes)]],
            default => null,
        };
        if ($block === null) {
            return ['ok' => false, 'error' => __('Only images and PDFs can be read.')];
        }

        $nullableString = ['type' => ['string', 'null']];
        $result = $this->ai->ask('attachment', "leave request {$request->id} attachment",
            'Extract facts from this medical certificate / leave document. Use null for anything not clearly written. Dates as YYYY-MM-DD.',
            [$block, ['type' => 'text', 'text' => 'Extract the rest period, doctor and clinic.']],
            ['type' => 'object', 'additionalProperties' => false,
                'required' => ['from_date', 'to_date', 'doctor', 'clinic', 'summary'],
                'properties' => ['from_date' => $nullableString, 'to_date' => $nullableString, 'doctor' => $nullableString,
                    'clinic' => $nullableString, 'summary' => $nullableString]],
            'low', $userId, 1500);

        if (! $result->ok) {
            return ['ok' => false, 'error' => $result->error];
        }
        $data = $result->data;
        $data['matches_request'] = ($data['from_date'] ?? null) === $request->from_date->format('Y-m-d')
            && ($data['to_date'] ?? null) === $request->to_date->format('Y-m-d');

        return ['ok' => true, 'data' => $data];
    }
}
