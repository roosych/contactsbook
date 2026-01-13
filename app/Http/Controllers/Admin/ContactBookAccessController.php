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
            'can_delete' => 'nullable|array',
            'can_delete.*' => 'boolean',
        ]);

        $bookIds = $validated['contact_book_ids'] ?? [];
        $canDeleteFlags = $validated['can_delete'] ?? [];

        // Получаем дефолтную книгу пользователя и добавляем её в список, если она есть
        $defaultBook = $user->getDefaultContactBook();
        $defaultBookId = $defaultBook ? $defaultBook->id : null;
        
        if ($defaultBookId && !in_array($defaultBookId, $bookIds)) {
            $bookIds[] = $defaultBookId;
        }

        // Подготавливаем данные для синхронизации с учетом can_delete
        // Для дефолтной книги также нужно явно предоставить can_delete через админ-панель
        $syncData = [];
        foreach ($bookIds as $bookId) {
            $canDelete = isset($canDeleteFlags[$bookId]) && $canDeleteFlags[$bookId] ? true : false;
            
            $syncData[$bookId] = [
                'can_delete' => $canDelete,
            ];
        }

        // Синхронизируем доступ к книгам с флагами can_delete
        $user->contactBooks()->sync($syncData);

        return redirect()->route('admin.contact-books-access.index')
            ->with('success', 'Access for user "' . $user->name . '" to books has been updated successfully.');
    }
}
