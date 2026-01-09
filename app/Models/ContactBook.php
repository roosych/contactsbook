<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactBook extends Model
{
    protected $fillable = [
        'name',
        'department_ou',
        'distinguishedname_pattern',
        'description',
    ];

    /**
     * Get the contacts in this book.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get the users who have access to this book.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_contact_books')
                    ->withTimestamps();
    }
}
