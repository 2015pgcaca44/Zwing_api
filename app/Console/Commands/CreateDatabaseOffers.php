<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Http\Controllers\Spar\DatabaseOfferController;

class CreateDatabaseOffers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'database:createoffers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creating spar available offers ';

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
        $page = 1;
        $request = new \Illuminate\Http\Request();
        $request->replace(['page' => $page]);

        $databaseOfferC = new DatabaseOfferController;
        $databaseOfferC->create_offers($request);
    }
}
