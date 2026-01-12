<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'name',
        'organization',
        'phone1',
        'phone2',
        'user_id',
        'updated_by',
        'contact_book_id',
    ];

    /**
     * Get the user that saved this contact.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that last updated this contact.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the contact book this contact belongs to.
     */
    public function contactBook(): BelongsTo
    {
        return $this->belongsTo(ContactBook::class);
    }
}
