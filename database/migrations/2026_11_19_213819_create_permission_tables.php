
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelMorphKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

        throw_if(
            empty($tableNames),
            'Permission tables configuration is missing.'
        );

        throw_if(
            ! $teams || empty($teamForeignKey),
            'Permission Teams must be enabled before migration.'
        );

        /*
         * Permissions
         *
         * Permissions are shared by guard.
         * Tenant and Platform permissions remain separated
         * through their respective guards.
         */
        Schema::create($tableNames['permissions'], function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('guard_name');

            $table->timestamps();

            $table->unique(
                ['name', 'guard_name'],
                'permissions_name_guard_name_unique'
            );
        });

        /*
         * Roles
         *
         * tenant_id is UUID:
         * - Tenant roles use the actual tenant UUID.
         * - Platform roles use the reserved Platform UUID.
         */
        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teamForeignKey) {
            $table->id();

            $table->string($teamForeignKey, 36);

            $table->string('name');
            $table->string('guard_name');

            $table->timestamps();

            $table->index(
                $teamForeignKey,
                'roles_tenant_id_index'
            );

            $table->unique(
                [$teamForeignKey, 'name', 'guard_name'],
                'roles_tenant_name_guard_unique'
            );
        });

        /*
         * Model Has Permissions
         */
        Schema::create(
            $tableNames['model_has_permissions'],
            function (Blueprint $table) use (
                $tableNames,
                $pivotPermission,
                $modelMorphKey,
                $teamForeignKey
            ) {
                $table->unsignedBigInteger($pivotPermission);

                $table->string($teamForeignKey, 36);

                $table->string('model_type');
                $table->unsignedBigInteger($modelMorphKey);

                $table->index(
                    [$modelMorphKey, 'model_type'],
                    'model_has_permissions_model_id_model_type_index'
                );

                $table->index(
                    $teamForeignKey,
                    'model_has_permissions_tenant_id_index'
                );

                $table->foreign($pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->cascadeOnDelete();

                $table->primary(
                    [
                        $teamForeignKey,
                        $pivotPermission,
                        $modelMorphKey,
                        'model_type',
                    ],
                    'model_has_permissions_primary'
                );
            }
        );

        /*
         * Model Has Roles
         */
        Schema::create(
            $tableNames['model_has_roles'],
            function (Blueprint $table) use (
                $tableNames,
                $pivotRole,
                $modelMorphKey,
                $teamForeignKey
            ) {
                $table->unsignedBigInteger($pivotRole);

                $table->string($teamForeignKey, 36);

                $table->string('model_type');
                $table->unsignedBigInteger($modelMorphKey);

                $table->index(
                    [$modelMorphKey, 'model_type'],
                    'model_has_roles_model_id_model_type_index'
                );

                $table->index(
                    $teamForeignKey,
                    'model_has_roles_tenant_id_index'
                );

                $table->foreign($pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->cascadeOnDelete();

                $table->primary(
                    [
                        $teamForeignKey,
                        $pivotRole,
                        $modelMorphKey,
                        'model_type',
                    ],
                    'model_has_roles_primary'
                );
            }
        );

        /*
         * Role Has Permissions
         */
        Schema::create(
            $tableNames['role_has_permissions'],
            function (Blueprint $table) use (
                $tableNames,
                $pivotRole,
                $pivotPermission
            ) {
                $table->unsignedBigInteger($pivotPermission);
                $table->unsignedBigInteger($pivotRole);

                $table->foreign($pivotPermission)
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->cascadeOnDelete();

                $table->foreign($pivotRole)
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->cascadeOnDelete();

                $table->primary(
                    [$pivotPermission, $pivotRole],
                    'role_has_permissions_primary'
                );
            }
        );

        app('cache')
            ->store(
                config('permission.cache.store') !== 'default'
                    ? config('permission.cache.store')
                    : null
            )
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(
            empty($tableNames),
            'Permission tables configuration is missing.'
        );

        Schema::dropIfExists($tableNames['role_has_permissions']);
        Schema::dropIfExists($tableNames['model_has_roles']);
        Schema::dropIfExists($tableNames['model_has_permissions']);
        Schema::dropIfExists($tableNames['roles']);
        Schema::dropIfExists($tableNames['permissions']);
    }
};
