<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id','name','description',
        'qty','unit','unit_price','discount_percent','vat_rate',
        'line_net','line_vat','line_gross',
        'sort_order','meta',
    ];

    protected $casts = [
        'qty' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'line_net' => 'decimal:2',
        'line_vat' => 'decimal:2',
        'line_gross' => 'decimal:2',
        'sort_order' => 'integer',
        'meta' => 'array',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
