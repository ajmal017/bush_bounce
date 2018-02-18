<?php

use Illuminate\Database\Seeder;
// no Illuminate\Database\Eloquent\Model
use App\Record;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // https://stackoverflow.com/questions/32795443/what-does-modelunguard-do-in-the-database-seeder-file-from-laravel-5

        // $this->call(UsersTableSeeder::class);
        $this->call('RecordsTableSeeder');



    }
}
