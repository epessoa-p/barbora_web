<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCategory extends Model
{
    use HasFactory, SoftDeletes, BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'description', 'color', 'sort_order', 'active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
