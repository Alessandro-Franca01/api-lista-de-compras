<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListShopping extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'items' // Armazenará um array de itens (usando JSON no banco)
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'items' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // TODO: In second version change this relation for many to many
    public function sharedWith()
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }
}
