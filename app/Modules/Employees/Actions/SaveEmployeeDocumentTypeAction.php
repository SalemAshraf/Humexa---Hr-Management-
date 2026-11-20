<?php

declare(strict_types=1);

namespace App\\Modules\\Employees\\Actions;

use App\\Modules\\Employees\\Models\\EmployeeDocumentType;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;

class SaveEmployeeDocumentTypeAction
{
    public function execute(string $tenantId, array $data, ?EmployeeDocumentType $type = null): EmployeeDocumentType
    {
        return DB::transaction(function () use ($tenantId, $data, $type) {
            DB::table('tenants')->where('id', $tenantId)->lockForUpdate()->first();

            if ($type !== null) {
                $type = EmployeeDocumentType::query()
                    ->where('tenant_id', $tenantId)
                    ->whereKey($type->getKey())
                    ->firstOrFail();
            }

            $attributes = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ];

            if (array_key_exists('is_active', $data)) {
                $attributes['is_active'] = (bool) $data['is_active'];
            } elseif ($type === null) {
                $attributes['is_active'] = true;
            }

            if ($type === null || $type->name !== $data['name']) {
                $attributes['slug'] = $this->uniqueSlug($tenantId, $data['name']);
            }

            if ($type === null) {
                return EmployeeDocumentType::query()->forceCreate([
                    ...$attributes,
                    'tenant_id' => $tenantId,
                ]);
            }

            $type->forceFill($attributes)->save();

            return $type->refresh();
        }, 3);
    }

    private function uniqueSlug(string $tenantId, string $name): string
    {
        $base = Str::slug($name) ?: 'document-type';
        $slug = $base;
        $suffix = 2;

        while (EmployeeDocumentType::withTrashed()
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
