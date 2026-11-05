<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RMS\Shop\Ai\ProposalService;
use RMS\Shop\Ai\Resources\AiResourceRegistry;
use RMS\Shop\Models\AiProposal;

/**
 * Admin side of agent proposals: look at before/after, approve the fields you want, or reject.
 */
class AiProposalsController extends ShopAdminController
{
    use \RMS\Shop\Http\Controllers\Admin\Concerns\ResolvesAiRound;

    public function table(): string
    {
        return 'shop_ai_proposals';
    }

    public function modelName(): string
    {
        return AiProposal::class;
    }

    // Must match the core AdminController::index(Request) signature.
    public function index(Request $request)
    {
        $this->title('تأیید تغییرهای پیشنهادی AI');

        $ready = Schema::hasTable('shop_ai_proposals');
        $status = (string) $request->query('status', 'pending');
        $type = (string) $request->query('resource', '');

        $rows = collect();
        $counts = [];
        $round = $this->aiRound($request);
        $rid = $round['id'];
        if ($ready) {
            $counts = AiProposal::query()->when($rid, fn ($q) => $q->where('round_id', $rid))
                ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();
            $rows = AiProposal::query()
                ->when($rid, fn ($q) => $q->where('round_id', $rid))
                ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($type !== '', fn ($q) => $q->where('resource_type', $type))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString();
        }

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.proposals.index')
            ->withVariables([
                'ready' => $ready,
                'rows' => $rows,
                'counts' => $counts,
                'status' => $status,
                'type' => $type,
                'resources' => AiResourceRegistry::all(),
                'round' => $round,
            ]);

        return $this->view();
    }

    public function show(AiProposal $proposal)
    {
        $this->title('پیشنهاد #'.$proposal->id);

        $resource = AiResourceRegistry::get($proposal->resource_type);
        $model = $resource?->find($proposal->resource_id);
        $fields = array_keys((array) $proposal->changes);

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('ai.proposals.show')
            ->withVariables([
                'proposal' => $proposal,
                'resource' => $resource,
                'model' => $model,
                'modelTitle' => $resource && $model ? $resource->title($model) : null,
                'adminUrl' => $resource && $model ? $resource->adminUrl($model) : null,
                'live' => $resource && $model ? $resource->current($model, $fields) : [],
            ]);

        return $this->view();
    }

    public function apply(Request $request, AiProposal $proposal, ProposalService $service): RedirectResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string'],
        ]);

        $errors = $service->apply($proposal, $data['fields'], auth('admin')->id());
        if ($errors !== []) {
            return back()->withErrors(['proposal' => collect($errors)->flatten()->implode(' | ')]);
        }

        return redirect()->route('admin.shop.ai-proposals.index')->with('success', 'پیشنهاد #'.$proposal->id.' اعمال شد.');
    }

    public function reject(AiProposal $proposal, ProposalService $service): RedirectResponse
    {
        $service->reject($proposal, auth('admin')->id());

        return redirect()->route('admin.shop.ai-proposals.index')->with('success', 'پیشنهاد رد شد.');
    }
}
