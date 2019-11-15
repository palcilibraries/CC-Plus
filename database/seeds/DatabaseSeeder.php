<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
          ReportsTableSeeder::class,
          ReportFieldsTableSeeder::class,
          InstitutionTypesTableSeeder::class,
          InstitutionsTableSeeder::class,
          RolesTableSeeder::class,
          AccessMethodsTableSeeder::class,
          AccessTypesTableSeeder::class,
          DataTypesTableSeeder::class,
        ]);
    }
}
