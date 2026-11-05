<?php

namespace RMS\Shop\Ai;

use Illuminate\Support\Facades\DB;
use RMS\Shop\Ai\Resources\AiResource;
use RMS\Shop\Ai\Resources\AiResourceRegistry;
use RMS\Shop\Models\AiActivity;
use RMS\Shop\Models\AiProposal;
use RMS\Shop\Models\ShopApiAccess;

/**
 * Agent proposals for any registered resource: stored pending, applied only after an admin approves.
 */
class ProposalService
{
    /**
     * @param  array<string, mixed>  $changes
     * @return array{0: ?AiProposal, 1: array<string, array<int, string>>, 2: int}  [proposal, errors, http status]
     */
    public function submit(?ShopApiAccess $access, string $type, int $id, array $changes, ?string $note = null, ?string $source = null): array
    {
        $resource = AiResourceRegistry::get($type);
        if (! $resource) {
            return [null, ['resource' => ['منبع ناشناخته است. مجاز: '.implode(', ', array_keys(AiResourceRegistry::all()))]], 422];
        }

        $model = $resource->find($id);
        if (! $model) {
            return [null, ['id' => ['پیدا نشد.']], 404];
        }

        if ($changes === []) {
            return [null, ['changes' => ['حداقل یک فیلد بفرست.']], 422];
        }

        [$cleaned, $errors] = $resource->clean($model, $changes);
        if ($errors !== []) {
            return [null, $errors, 422];
        }

        // A value equal to the current one is no change; drop it.
        $current = $resource->current($model, array_keys($cleaned));
        foreach ($cleaned as $field => $value) {
            if (trim((string) ($current[$field] ?? '')) === $value) {
                unset($cleaned[$field]);
            }
        }
        if ($cleaned === []) {
            return [null, ['changes' => ['همهٔ مقدارها با مقدار فعلی یکی است.']], 422];
        }

        $before = $resource->current($model, array_keys($cleaned));

        $proposal = DB::transaction(function () use ($access, $type, $id, $cleaned, $before, $note, $source) {
            // One open proposal per resource: a newer one replaces the unreviewed older one.
            AiProposal::query()
                ->where('resource_type', $type)->where('resource_id', $id)
                ->where('status', AiProposal::STATUS_PENDING)
                ->update(['status' => AiProposal::STATUS_SUPERSEDED]);

            return AiProposal::query()->create([
                'resource_type' => $type,
                'resource_id' => $id,
                'status' => AiProposal::STATUS_PENDING,
                'access_id' => $access?->id,
                'actor' => $access?->name,
                'source' => 'agent: '.($source ?: 'api'),
                'note' => $note ? trim($note) : null,
                'changes' => $cleaned,
                'before' => $before,
            ]);
        });

        AiActivity::record(
            $access,
            null,
            'suggestion',
            'پیشنهاد تغییر '.$resource->label().': '.implode('، ', array_map(fn ($f) => $resource->fields()[$f]['label'] ?? $f, array_keys($cleaned))),
            ['proposal_id' => $proposal->id, 'note' => $proposal->note],
            $type,
            $id
        );

        return [$proposal, [], 201];
    }

    /**
     * Apply the chosen fields of a pending proposal. Values are checked again against the live record.
     *
     * @param  array<int, string>  $fields
     * @return array<string, array<int, string>>  errors; empty on success
     */
    public function apply(AiProposal $proposal, array $fields, ?int $adminId): array
    {
        if ($proposal->status !== AiProposal::STATUS_PENDING) {
            return ['status' => ['این پیشنهاد دیگر در انتظار نیست.']];
        }

        $resource = AiResourceRegistry::get($proposal->resource_type);
        $model = $resource?->find($proposal->resource_id);
        if (! $resource || ! $model) {
            return ['resource' => ['منبع پیدا نشد یا حذف شده است.']];
        }

        $chosen = array_intersect_key((array) $proposal->changes, array_flip($fields));
        if ($chosen === []) {
            return ['fields' => ['حداقل یک فیلد انتخاب کن.']];
        }

        [$cleaned, $errors] = $resource->clean($model, $chosen);
        if ($errors !== []) {
            $proposal->forceFill(['status' => AiProposal::STATUS_FAILED, 'error' => json_encode($errors, JSON_UNESCAPED_UNICODE)])->save();

            return $errors;
        }

        $before = $resource->current($model, array_keys($cleaned));
        DB::transaction(function () use ($resource, $model, $cleaned, $proposal, $adminId) {
            $resource->apply($model, $cleaned);
            $proposal->forceFill([
                'status' => AiProposal::STATUS_APPLIED,
                'applied_fields' => array_keys($cleaned),
                'reviewed_by' => $adminId,
                'applied_at' => now(),
            ])->save();
        });

        AiActivity::record(
            null,
            null,
            'apply',
            'تأیید و اعمال پیشنهاد: '.implode('، ', array_map(fn ($f) => $resource->fields()[$f]['label'] ?? $f, array_keys($cleaned))),
            ['proposal_id' => $proposal->id, 'before' => $before, 'after' => $cleaned, 'approved_by_admin' => $adminId],
            $proposal->resource_type,
            $proposal->resource_id
        );

        return [];
    }

    public function reject(AiProposal $proposal, ?int $adminId): void
    {
        if ($proposal->status !== AiProposal::STATUS_PENDING) {
            return;
        }
        $proposal->forceFill(['status' => AiProposal::STATUS_REJECTED, 'reviewed_by' => $adminId])->save();

        AiActivity::record(null, null, 'note', 'پیشنهاد رد شد', ['proposal_id' => $proposal->id], $proposal->resource_type, $proposal->resource_id);
    }
}
