
<?php

use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return [

    'models' => [

        'permission' => Permission::class,

       'role' => \App\Modules\Users\Models\Role::class,

        /*
         * Teams model.
         * We use tenant_id as the team identifier.
         */
        'team' => null,

        'default_model' => null,
    ],

    'table_names' => [

        'roles' => 'roles',

        'permissions' => 'permissions',

        'model_has_permissions' => 'model_has_permissions',

        'model_has_roles' => 'model_has_roles',

        'role_has_permissions' => 'role_has_permissions',
    ],

    /*
     * Custom column names.
     */
    'column_names' => [

        'role_pivot_key' => 'role_id',

        'permission_pivot_key' => 'permission_id',

        'model_morph_key' => 'model_id',

        'team_foreign_key' => 'tenant_id',
    ],

    /*
     * Teams Feature.
     */
    'teams' => true,

    /*
     * Resolve the active team ID.
     */
    'team_resolver' => \App\Modules\Users\Support\HumexaTeamResolver::class,

    /*
     * Register permission checks on Laravel Gate.
     */
    'register_permission_check_method' => true,

    /*
     * Reset permissions on Octane operations.
     */
    'register_octane_reset_listener' => false,

    /*
     * Package events.
     */
    'events_enabled' => false,

    /*
     * Passport Client Credentials Grant.
     */
    'use_passport_client_credentials' => false,

    /*
     * Security: hide permission names in exceptions.
     */
    'display_permission_in_exception' => false,

    'display_role_in_exception' => false,

    /*
     * Wildcard permissions.
     */
    'enable_wildcard_permission' => false,

    /*
     * Cache configuration.
     */
    'cache' => [

        'expiration_time' => DateInterval::createFromDateString('24 hours'),

        'key' => 'spatie.permission.cache',

        'store' => 'default',
    ],

];
