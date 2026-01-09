<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactBook;
use App\Models\User;
use Illuminate\Http\Request;

class ContactBookAccessController extends Controller
{
    /**
     * Показывает страницу управления доступом к книгам
     */
    public function index()
    {
        $users = User::with('contactBooks')->orderBy('name')->get();
        $contactBooks = ContactBook::orderBy('name')->get();

        return view('admin.contact-books-access', compact('users', 'contactBooks'));
    }

    /**
     * Обновляет доступ пользователя к книгам
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'contact_book_ids' => 'nullable|array',
            'contact_book_ids.*' => 'exists:contact_books,id',
        ]);

        $bookIds = $validated['contact_book_ids'] ?? [];

        // Получаем дефолтную книгу пользователя и добавляем её в список, если она есть
        $defaultBook = $user->getDefaultContactBook();
        if ($defaultBook && !in_array($defaultBook->id, $bookIds)) {
            $bookIds[] = $defaultBook->id;
        }

        // Синхронизируем доступ к книгам
        $user->contactBooks()->sync($bookIds);

        return redirect()->route('admin.contact-books-access.index')
            ->with('success', 'Access for user "' . $user->name . '" to books has been updated successfully.');
    }
}
