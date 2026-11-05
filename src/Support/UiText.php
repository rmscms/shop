<?php

namespace RMS\Shop\Support;

class UiText
{
    /** Plain one-line text for a logged value; feature lists ([{group, features}]) become "group: name=value، …". */
    public static function plain(mixed $value): string
    {
        if (! is_array($value)) {
            return trim(strip_tags((string) $value));
        }

        $parts = [];
        foreach ($value as $group) {
            if (! is_array($group) || ! isset($group['features'])) {
                continue;
            }
            $items = array_map(fn ($f) => ($f['name'] ?? '').' = '.($f['value'] ?? ''), (array) $group['features']);
            $parts[] = (($group['group'] ?? '') !== '' ? $group['group'].': ' : '').implode('، ', $items);
        }

        return implode(' | ', $parts);
    }
}
