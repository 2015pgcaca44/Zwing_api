<?php

use Illuminate\Database\Seeder;
use App\EmailScheduler;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call('UsersTableSeeder');
        $emailList = ['shubhammaurya021@gmail.com', 'shubham.m@gsl.in'];
        EmailScheduler::create([ 'v_id' => 92, 'type' => 'INTEGRATION_SYNC', 'email_list' => json_encode($emailList), 'status' => '1' ]);
    }
}
