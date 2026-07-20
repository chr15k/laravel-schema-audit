<?php

namespace Workbench\ValueObjectsbase\Seeders;

use Illuminate\ValueObjectsbase\Console\Seeds\WithoutModelEvents;
use Illuminate\ValueObjectsbase\Seeder;
use Workbench\ValueObjectsbase\Factories\UserFactory;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UserFactory::new()->times(10)->create();

        UserFactory::new()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
