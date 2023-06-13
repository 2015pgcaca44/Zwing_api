<?php

namespace App\Events;

class Loyalty extends Event
{

	public $loyaltyPrams;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($loyaltyPrams)
    {
    	$this->loyaltyPrams = $loyaltyPrams;
    }
}
