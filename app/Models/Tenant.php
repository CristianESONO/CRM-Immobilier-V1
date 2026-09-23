<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'domain',
        'plan',
        'max_users',
        'max_properties',
        'feature_flags',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'feature_flags' => 'array',
        'max_users' => 'integer',
        'max_properties' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }
}
