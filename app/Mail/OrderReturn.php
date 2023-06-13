<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\User;

class OrderReturn extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $user;
    public $path_of_pdf;
    public $order;
    public $carts;
    public $payment_method;
    public function __construct($user, $order, $carts, $payment_method, $path_of_pdf)
    {
        $this->user = $user;
        $this->path_of_pdf = $path_of_pdf;
        $this->order = $order;
        $this->carts = $carts;
        $this->payment_method = $payment_method;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        //return $this->view('view.name');
        return $this->view('emails.orders.return')
                    ->attach($this->path_of_pdf, [
                        //'as' => 'name.pdf',
                        'mime' => 'application/pdf',
                    ]);
    }
}
