<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\HasLdapUser;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class User extends Authenticatable implements LdapAuthenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, AuthenticatesWithLdap, HasLdapUser;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'guid',
        'domain',
        'distinguishedname',
        'department',
        'position',
        'mailnickname',
        'status',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Extract department OU from distinguishedname.
     */
    public function getDepartmentOu(): ?string
    {
        if (!$this->distinguishedname) {
            return null;
        }

        // Извлекаем OU из distinguishedname
        // Пример: "CN=Ruslan Kandiba,OU=Users,OU=IT_Department,..." -> IT_Department
        preg_match_all('/OU=([^,]+)/', $this->distinguishedname, $allMatches);
        if (isset($allMatches[1][1])) {
            return $allMatches[1][1];
        }

        return null;
    }

    /**
     * Get or create the user's default contact book (based on department).
     */
    public function getDefaultContactBook()
    {
        $departmentOu = $this->getDepartmentOu();
        if (!$departmentOu) {
            return null;
        }

        $book = ContactBook::where('department_ou', $departmentOu)->first();
        
        // Если книги нет, создаем её
        if (!$book) {
            $book = ContactBook::create([
                'name' => $departmentOu . ' Contacts',
                'department_ou' => $departmentOu,
                'distinguishedname_pattern' => $this->distinguishedname,
                'description' => 'Contact book for ' . $departmentOu . ' department',
            ]);
        }

        return $book;
    }

    /**
     * Get all contact books the user has access to.
     */
    public function accessibleContactBooks()
    {
        // Книга по умолчанию (на основе отдела)
        $defaultBook = $this->getDefaultContactBook();
        $bookIds = $defaultBook ? [$defaultBook->id] : [];

        // Добавляем книги, к которым у пользователя есть доступ через таблицу user_contact_books
        // Получаем все книги и извлекаем ID из коллекции, чтобы избежать неоднозначности в SQL
        $accessibleBooks = $this->contactBooks()->get();
        $accessibleIds = $accessibleBooks->pluck('id')->toArray();
        $bookIds = array_merge($bookIds, $accessibleIds);
        $bookIds = array_unique($bookIds);

        if (empty($bookIds)) {
            return collect();
        }

        return ContactBook::whereIn('id', $bookIds)->get();
    }

    /**
     * Get contact books the user has been granted access to (many-to-many).
     */
    public function contactBooks(): BelongsToMany
    {
        return $this->belongsToMany(ContactBook::class, 'user_contact_books')
                    ->withPivot('can_delete')
                    ->withTimestamps();
    }

    /**
     * Get contacts created by this user.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
