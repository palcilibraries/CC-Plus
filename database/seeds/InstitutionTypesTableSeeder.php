<?php

use Illuminate\Database\Seeder;

class InstitutionTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

       // If consodb not set, seed the template database
        $_db = \Config::get('database.connections.consodb.database');
        if ($_db == 'db-name-isbad' || $_db == '') {
            $_db = \Config::get('database.connections.con_template.database');
        }
        $table = $_db . '.institutiontypes';

       // Make sure table is empty
        if (DB::table($table)->get()->count() == 0) {
            DB::table($table)->insert([
            ['id' => 1, 'name' => '(Not classified)'],
            ['id' => 2, 'name' => 'Associate\'s Colleges: High Transfer-High Traditional'],
            ['id' => 3, 'name' => 'Associate\'s Colleges: High Transfer-Mixed Traditional/Nontraditional'],
            ['id' => 4, 'name' => 'Associate\'s Colleges: High Transfer-High Nontraditional'],
            ['id' => 5, 'name' => 'Associate\'s Colleges: Mixed Transfer/Career & Technical-High Traditional'],
            ['id' => 6,
             'name' => 'Associate\'s Colleges: Mixed Transfer/Career & Technical-Mixed Traditional/Nontraditional'],
            ['id' => 7, 'name' => 'Associate\'s Colleges: Mixed Transfer/Career & Technical-High Nontraditional'],
            ['id' => 8, 'name' => 'Associate\'s Colleges: High Career & Technical-High Traditional'],
            ['id' => 9, 'name' => 'Associate\'s Colleges: High Career & Technical-Mixed Traditional/Nontraditional'],
            ['id' => 10, 'name' => 'Associate\'s Colleges: High Career & Technical-High Nontraditional'],
            ['id' => 11, 'name' => 'Special Focus Two-Year: Health Professions'],
            ['id' => 12, 'name' => 'Special Focus Two-Year: Technical Professions'],
            ['id' => 13, 'name' => 'Special Focus Two-Year: Arts & Design'],
            ['id' => 14, 'name' => 'Special Focus Two-Year: Other Fields'],
            ['id' => 15, 'name' => 'Baccalaureate/Associate\'s Colleges: Associate\'s Dominant'],
            ['id' => 16, 'name' => 'Doctoral Universities: Highest Research Activity'],
            ['id' => 17, 'name' => 'Doctoral Universities: Higher Research Activity'],
            ['id' => 18, 'name' => 'Doctoral Universities: Moderate Research Activity'],
            ['id' => 19, 'name' => 'Master\'s Colleges & Universities: Larger Programs'],
            ['id' => 20, 'name' => 'Master\'s Colleges & Universities: Medium Programs'],
            ['id' => 21, 'name' => 'Master\'s Colleges & Universities: Small Programs'],
            ['id' => 22, 'name' => 'Baccalaureate Colleges: Arts & Sciences Focus'],
            ['id' => 23, 'name' => 'Baccalaureate Colleges: Diverse Fields'],
            ['id' => 24, 'name' => 'Baccalaureate/Associate\'s Colleges: Mixed Baccalaureate/Associate\'s'],
            ['id' => 25, 'name' => 'Special Focus Four-Year: Faith-Related Institutions'],
            ['id' => 26, 'name' => 'Special Focus Four-Year: Medical Schools & Centers'],
            ['id' => 27, 'name' => 'Special Focus Four-Year: Other Health Professions Schools'],
            ['id' => 28, 'name' => 'Special Focus Four-Year: Engineering Schools'],
            ['id' => 29, 'name' => 'Special Focus Four-Year: Other Technology-Related Schools'],
            ['id' => 30, 'name' => 'Special Focus Four-Year: Business & Management Schools'],
            ['id' => 31, 'name' => 'Special Focus Four-Year: Arts, Music & Design Schools'],
            ['id' => 32, 'name' => 'Special Focus Four-Year: Law Schools'],
            ['id' => 33, 'name' => 'Special Focus Four-Year: Other Special Focus Institutions'],
            ['id' => 34, 'name' => 'Tribal Colleges']
            ]);
        }
    }
}
