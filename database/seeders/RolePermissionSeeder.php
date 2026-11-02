<?php

namespace Database\Seeders;

use App\Support\RbacMatrix;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        RbacMatrix::seedPermissions();

        foreach (array_keys(RbacMatrix::roles()) as $slug) {
            RbacMatrix::ensureRole($slug);
        }
    }
}
