<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Shop\Ai\ProposalService;
use RMS\Shop\Ai\Resources\AiResourceRegistry;
use RMS\Shop\Models\AiProposal;

/**
 * Agents propose changes to any registered resource. Nothing is applied until an admin approves.
 */
class AiProposalController extends BaseController
{
    /** Resources and the fields an agent may propose. */
    public function resources(): JsonResponse
    {
        $data = [];
        foreach (AiResourceRegistry::all() as $key => $resource) {
            $data[] = [
                'resource' => $key,
                'label' => $resource->label(),
                'read' => 'GET '.$resource->readPath(),
                'fields' => $resource->fields(),
            ];
        }

        return $this->apiSuccess($data, [
            'submit' => 'POST ai/proposals {resource, id, changes:{field:value}, note?, source?}',
        ]);
    }

    public function store(Request $request, ProposalService $service): JsonResponse
    {
        $data = $request->validate([
            'resource' => ['required', 'string', 'max:30'],
            'id' => ['required', 'integer'],
            'changes' => ['required', 'array', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:80'],
        ]);

        [$proposal, $errors, $status] = $service->submit(
            $request->attributes->get('shop_api_access'),
            $data['resource'],
            (int) $data['id'],
            $data['changes'],
            $data['note'] ?? null,
            $data['source'] ?? null
        );

        if (! $proposal) {
            return $this->apiError($errors, $status);
        }

        return $this->apiSuccess([
            'proposal_id' => $proposal->id,
            'status' => $proposal->status,
            'fields' => array_keys($proposal->changes),
            'review_at' => 'admin/shop/ai-proposals/'.$proposal->id,
        ], [], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));
        $rows = AiProposal::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('resource'), fn ($q) => $q->where('resource_type', (string) $request->query('resource')))
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->apiSuccess($rows->getCollection()->map(fn (AiProposal $p) => [
            'proposal_id' => $p->id,
            'resource' => $p->resource_type,
            'id' => $p->resource_id,
            'status' => $p->status,
            'fields' => array_keys((array) $p->changes),
            'created_at' => $p->created_at?->toIso8601String(),
            'applied_at' => $p->applied_at?->toIso8601String(),
        ])->values(), $this->paginationMeta($rows));
    }
}
