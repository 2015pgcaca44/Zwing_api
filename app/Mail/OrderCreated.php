<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\User;

class OrderCreated extends Mailable
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
    public function __construct($user, $order, $carts, $payment_method, $path_of_pdf,$billLogo,$type)
    {
        $this->user = $user;
        $this->path_of_pdf = $path_of_pdf;
        $this->order = $order;
        $this->carts = $carts;
        $this->payment_method = $payment_method;
        $this->billLogo   = $billLogo;
        $this->type       = $type;
       
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        //return $this->view('view.name');
        if($this->type == 'account_deposite' || $this->type == 'adhoc_credit_note' || $this->type == 'refund_credit_note'){
             return $this->view('emails.orders.creditnote')
            ->attach($this->path_of_pdf, [
                //'as' => 'name.pdf',
                'mime' => 'application/pdf',
            ]); 
        }else if($this->order->v_id == 57){
            return $this->view('emails.orders.adhyam')
                ->attach($this->path_of_pdf, [
                    //'as' => 'name.pdf',
                    'mime' => 'application/pdf',
                ]); 
        }else{
             return $this->view('emails.orders.created')
            ->attach($this->path_of_pdf, [
                //'as' => 'name.pdf',
                'mime' => 'application/pdf',
            ]);
        }
    }
}
