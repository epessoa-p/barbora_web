<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'full_name', 'document_number', 'phone', 'email',
        'birth_date', 'preferences', 'allergies', 'notes', 'active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** Búsqueda de recepción: por nombre, teléfono o documento. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('full_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('document_number', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%");
        });
    }

    /** Cumple años dentro de los próximos N días. */
    public function birthdayIsNear(int $days = 7): bool
    {
        if (! $this->birth_date) {
            return false;
        }

        $next = $this->birth_date->copy()->setYear(now()->year);

        if ($next->isPast()) {
            $next->addYear();
        }

        return $next->diffInDays(now()) <= $days;
    }
}
