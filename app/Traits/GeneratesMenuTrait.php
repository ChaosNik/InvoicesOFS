<?php

namespace App\Traits;

trait GeneratesMenuTrait
{
    public function generateMenu($key, $user, $abilities = null, ?bool $isOwner = null)
    {
        $new_items = [];
        $isOwner = $isOwner ?? $user->isOwner();
        $abilityNames = collect($abilities ?? $user->getAbilities())
            ->pluck('name')
            ->filter()
            ->values()
            ->all();
        $hasAllAbilities = in_array('*', $abilityNames, true);

        $menu = \Menu::get($key);
        $items = $menu ? $menu->items->toArray() : [];

        foreach ($items as $data) {
            if ($this->canAccessMenuItem($data, $isOwner, $abilityNames, $hasAllAbilities)) {
                $new_items[] = [
                    'title' => $data->title,
                    'link' => $data->link->path['url'],
                    'icon' => $data->data['icon'],
                    'name' => $data->data['name'],
                    'group' => $data->data['group'],
                ];
            }
        }

        return $new_items;
    }

    private function canAccessMenuItem($data, bool $isOwner, array $abilityNames, bool $hasAllAbilities): bool
    {
        if ($isOwner) {
            return true;
        }

        if ($data->data['owner_only']) {
            return false;
        }

        if (empty($data->data['ability'])) {
            return true;
        }

        return $hasAllAbilities || in_array($data->data['ability'], $abilityNames, true);
    }
}
