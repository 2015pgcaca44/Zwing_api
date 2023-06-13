<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Vmart\FetchController;

class PushAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:invoice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push Invoice to client database';

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
        $controller = new FetchController(); // make sure to import the controller
        $controller->apicall();
        
    }
}
