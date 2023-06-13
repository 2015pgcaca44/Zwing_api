<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\EmailScheduler;
use Illuminate\Support\Facades\Mail;
use App\Mail\IntegrationSyncStatus;

class SendEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send e-mails to a user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Mail::to('shubhammaurya021@gmail.com')->send(new IntegrationSyncStatus(108));
        $list = EmailScheduler::where('status', '1')->get();
        foreach ($list as $key => $value) {
            $toEmailList = json_decode($value->email_list);
            foreach ($toEmailList as $to) {
                Mail::to($to)->queue(new IntegrationSyncStatus($value->v_id));
            }
            if( count(Mail::failures()) > 0 ) {
              echo "There was one or more failures. They were: <br />";
            } else {
                echo "Email Send";
            }
        }
    }
}
