<?php

namespace RMS\Shop\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use RMS\Shop\Models\AiRound;

/** Which AI work round an admin page is showing: ?round=<id>, "all", or the current one. */
trait ResolvesAiRound
{
    /**
     * @return array{rounds: \Illuminate\Support\Collection, current: ?AiRound, selected: ?AiRound, id: ?int, all: bool, archived: bool}
     */
    protected function aiRound(Request $request): array
    {
        if (! AiRound::available()) {
            return ['rounds' => collect(), 'current' => null, 'selected' => null, 'id' => null, 'all' => false, 'archived' => false];
        }

        $rounds = AiRound::query()->orderByDesc('id')->get();
        $current = AiRound::current();
        $param = (string) $request->query('round', '');
        $all = $param === 'all';
        $selected = $all ? null : ($rounds->firstWhere('id', (int) $param) ?? $current);

        return [
            'rounds' => $rounds,
            'current' => $current,
            'selected' => $selected,
            'id' => $selected?->id,
            'all' => $all,
            'archived' => $selected !== null && ! $selected->isCurrent(),
        ];
    }
}
