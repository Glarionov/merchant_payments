<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    public $fillable = [
        'merchant_id',
        'payment_id',
        'amount',
        'amount_paid',
        'status',
    ];

}
