<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','meeting_id',
        'number','token','status','valid_from','valid_to','currency',
        'opened_at','expires_at',
        'commission_percent','stratton_raise_percent',
        'subtotal_net','total_vat','total_gross','total_discount',
        'meta','notes',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'opened_at' => 'datetime',
        'expires_at' => 'date',
        'commission_percent' => 'decimal:4',
        'stratton_raise_percent' => 'decimal:4',
        'subtotal_net' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_gross' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'meta' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OfferItem::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(OfferVerification::class);
    }

    public function payrollCalculations(): HasMany
    {
        return $this->hasMany(PayrollCalculation::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Offer $offer) {
            if (!empty($offer->number)) {
                return;
            }

            $year = Carbon::now()->format('Y');
            $companyId = $offer->company_id;
            $nip = '';

            if ($companyId) {
                $company = Company::query()->select('nip')->find($companyId);
                if ($company && $company->nip) {
                    $nip = preg_replace('/\D+/', '', $company->nip);
                }
            }

            if ($nip === '') {
                $nip = '0000000000';
            }

            $sequence = self::query()
                ->where('company_id', $companyId)
                ->whereYear('created_at', $year)
                ->count() + 1;

            $offer->number = sprintf('SP/%s/%s/%03d', $year, $nip, $sequence);
        });
    }
}
