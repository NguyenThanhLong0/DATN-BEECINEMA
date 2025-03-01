<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket_Combo extends Model
{
    use HasFactory;
    protected $table = "ticket_combos";
    protected $fillable = [
        'ticket_id',
        'combo_id',
        'price',
        'quantity'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function combo()
    {
        return $this->belongsTo(Combo::class);
    }
}
