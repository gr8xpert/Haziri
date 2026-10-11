<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Punch;
use App\Services\Settings\TenantSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use TanemRahman\ZktecoAdms\Models\ZktecoAttphoto;

/**
 * "Keep photos" (photo_retention_days): deletes kiosk / phone punch photos and machine attendance photos older than
 * that many days for the current tenant. The punch itself stays; only its photo goes. 0 or empty keeps photos forever.
 * Scheduled through `tenants:run`, so it runs once per tenant.
 */
#[Signature('hazri:photos:prune')]
#[Description('Delete punch photos older than the company\'s "Keep photos" days (current tenant)')]
final class PrunePunchPhotos extends Command
{
    public function handle(TenantSettings $settings): int
    {
        $days = (int) $settings->get('photo_retention_days');
        if ($days <= 0) {
            $this->info('Photos are kept forever.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $disk = Storage::disk('local');
        $removed = 0;

        Punch::withTrashed()->withoutGlobalScopes()->whereNotNull('photo_path')->where('punched_at', '<', $cutoff)
            ->chunkById(500, function ($punches) use ($disk, &$removed): void {
                foreach ($punches as $punch) {
                    $disk->delete($punch->photo_path);
                    Punch::withTrashed()->withoutGlobalScopes()->whereKey($punch->id)->update(['photo_path' => null]);
                    $removed++;
                }
            });

        ZktecoAttphoto::query()->where(fn ($q) => $q->where('captured_at', '<', $cutoff)
            ->orWhere(fn ($q) => $q->whereNull('captured_at')->where('created_at', '<', $cutoff)))
            ->chunkById(500, function ($photos) use (&$removed): void {
                foreach ($photos as $photo) {
                    Storage::disk($photo->disk ?: 'local')->delete($photo->path);
                    $photo->delete();
                    $removed++;
                }
            });

        $this->info("{$removed} photo(s) older than {$days} days deleted.");

        return self::SUCCESS;
    }
}
