<?php

namespace App\Jobs;

use App\Http\Controllers\Vmart\DataPushApiController;

class ItemFetch extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        echo 'Item';
        $controller = new DataPushApiController();
        $controller->InvItemNewSync();
        $controller->InvItemUpdateSync();

        $controller->InvArticleNewSync();
        $controller->InvArticleNewSync();
        $controller->InvArticleUpdateSync();
        $controller->InvGroupSync();

        $controller->InvHsnsadetNewSync();
        $controller->InvHsnsadetUpdateSync();
        $controller->InvHsnsacmainNewSync();
        $controller->InvHsnsacmainUpdateSync();
        $controller->InvHsnsaclabNewSync();
        $controller->PromoAssortmentSync();
        $controller->PromoAssortmentExcludeSync();
        $controller->PromoAssortmentIncludeSync();

        $controller->PromoBuyNewSync();
        $controller->PromoBuyUpdateSync();
        $controller->PromoMasterNewSync();
        $controller->PromoMasterUpdateSync();

        $controller->PromoSlabNewSync();
        $controller->PromoSlabUpdateSync();
        $controller->PsitePromoAssignNewSync();
        $controller->PsitePromoAssignUpdateSync();
        echo 'Not Working';
    }
}
