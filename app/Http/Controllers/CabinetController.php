<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Sabre\VObject\Reader;

class CabinetController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Сначала получаем или создаем дефолтную книгу пользователя
        // (это автоматически создаст книгу для отдела, если её нет)
        $defaultBook = $user->getDefaultContactBook();
        
        // Получаем все доступные книги пользователя (включая дефолтную)
        $availableBooks = $user->accessibleContactBooks();
        
        // Получаем текущую выбранную книгу из запроса или сессии
        $selectedBookId = $request->get('book_id') ?? session('selected_book_id');
        
        // Если не выбрана книга, используем дефолтную
        if (!$selectedBookId) {
            $selectedBookId = $defaultBook ? $defaultBook->id : ($availableBooks->count() > 0 ? $availableBooks->first()->id : null);
        }
        
        // Получаем выбранную книгу
        $selectedBook = $availableBooks->firstWhere('id', $selectedBookId);
        
        // Если книга не найдена или пользователь не имеет к ней доступа, используем дефолтную
        if (!$selectedBook || !$availableBooks->contains('id', $selectedBookId)) {
            $selectedBook = $defaultBook;
            $selectedBookId = $selectedBook ? $selectedBook->id : null;
        }
        
        // Сохраняем выбранную книгу в сессию
        if ($selectedBookId) {
            session(['selected_book_id' => $selectedBookId]);
        }
        
        // Фильтруем контакты по выбранной книге
        $query = Contact::query();
        if ($selectedBook) {
            $query->where('contact_book_id', $selectedBook->id);
        } else {
            // Если нет книги, показываем только контакты пользователя
            $query->where('user_id', $user->id);
        }
        
        // Поиск по имени или номеру телефона
        if ($request->has('search') && strlen($request->search) >= 3) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('phone1', 'LIKE', "%{$search}%")
                  ->orWhere('phone2', 'LIKE', "%{$search}%");
            });
        }
        
        $contacts = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Передаем ID дефолтной книги для проверки возможности редактирования
        $defaultBookId = $defaultBook ? $defaultBook->id : null;
        
        // Если это AJAX запрос, возвращаем только HTML таблицы
        if ($request->ajax()) {
            return view('contacts-table', compact('contacts', 'defaultBookId'))->render();
        }
        
        return view('index', compact('contacts', 'availableBooks', 'selectedBook', 'defaultBookId'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'vcf_file' => [
                'required',
                'file',
                'max:10240', // Максимум 10MB
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    if ($extension !== 'vcf') {
                        $fail('The file must have a .vcf extension');
                    }
                },
            ],
        ]);

        try {
            $file = $request->file('vcf_file');
            $originalName = $file->getClientOriginalName();
            $fileName = Str::random(40) . '_' . time() . '.vcf';
            
            // Сохраняем файл в storage/app/vcf
            $path = $file->storeAs('vcf', $fileName, 'local');
            
            // Получаем текущую книгу пользователя
            $user = auth()->user();
            $contactBook = $user->getDefaultContactBook();
            
            if (!$contactBook) {
                return redirect()->route('cabinet.index')->with('error', 
                    'Unable to determine contact book. Please contact the administrator.'
                );
            }
            
            // Parse VCF file and save contacts
            $contactsCount = $this->parseVcfFile(Storage::path($path), auth()->id(), $contactBook->id);
            
            return redirect()->route('cabinet.index')->with('success', 
                'File "' . $originalName . '" uploaded successfully! Processed contacts: ' . $contactsCount
            );
        } catch (\Exception $e) {
            return redirect()->route('cabinet.index')->with('error', 
                'Error uploading file: ' . $e->getMessage()
            );
        }
    }

    /**
     * Парсит VCF файл и сохраняет контакты в базу данных
     *
     * @param string $filePath Путь к VCF файлу
     * @param int $userId ID пользователя, который загрузил файл
     * @param int $contactBookId ID книги контактов
     * @return int Количество обработанных контактов
     */
    private function parseVcfFile(string $filePath, int $userId, int $contactBookId): int
    {
        $contactsCount = 0;
        
        try {
            // Читаем содержимое файла
            $vcfContent = file_get_contents($filePath);
            
            // Нормализуем окончания строк (унифицируем в \n)
            $vcfContent = str_replace(["\r\n", "\r"], "\n", $vcfContent);
            
            // VCF файл может содержать несколько контактов
            // Разделяем на отдельные контакты по BEGIN:VCARD
            $vcfContacts = preg_split('/\n(?=BEGIN:VCARD)/i', $vcfContent);
            
            // If file didn't split, it's a single contact
            if (count($vcfContacts) === 1 && !str_contains($vcfContent, 'BEGIN:VCARD')) {
                throw new \Exception('Invalid VCF file format');
            }
            
            foreach ($vcfContacts as $vcfContact) {
                // Пропускаем пустые строки
                $vcfContact = trim($vcfContact);
                if (empty($vcfContact)) {
                    continue;
                }
                
                // Убеждаемся, что контакт начинается с BEGIN:VCARD
                if (!preg_match('/^BEGIN:VCARD/i', $vcfContact)) {
                    continue;
                }
                
                try {
                    // Парсим VCF контакт с помощью sabre/vobject
                    $vcard = Reader::read($vcfContact);
                    
                    // Извлекаем имя
                    $name = null;
                    if (isset($vcard->FN)) {
                        $name = trim((string) $vcard->FN);
                    } elseif (isset($vcard->N)) {
                        $n = $vcard->N;
                        $nameParts = [];
                        if (isset($n->familyName)) {
                            $nameParts[] = trim((string) $n->familyName);
                        }
                        if (isset($n->givenName)) {
                            $nameParts[] = trim((string) $n->givenName);
                        }
                        if (isset($n->additionalName)) {
                            $nameParts[] = trim((string) $n->additionalName);
                        }
                        $name = implode(' ', array_filter($nameParts));
                    }
                    
                    // Извлекаем телефоны
                    $phones = [];
                    if (isset($vcard->TEL)) {
                        foreach ($vcard->TEL as $tel) {
                            $phoneNumber = trim((string) $tel);
                            // Очищаем номер от пробелов, дефисов, скобок и других символов
                            $phoneNumber = preg_replace('/[\s\-\(\)\.]/', '', $phoneNumber);
                            // Удаляем префиксы типа tel:, TEL: и т.д.
                            $phoneNumber = preg_replace('/^tel:/i', '', $phoneNumber);
                            if (!empty($phoneNumber)) {
                                // Нормализуем номер в формат +994XXXXXXXXX
                                $phoneNumber = $this->normalizePhoneNumber($phoneNumber);
                                if (!empty($phoneNumber)) {
                                    $phones[] = $phoneNumber;
                                }
                            }
                        }
                    }
                    
                    // Удаляем дубликаты номеров (если два номера одинаковые, оставляем один)
                    $phones = array_unique($phones);
                    $phones = array_values($phones); // Переиндексируем массив
                    
                    // Сохраняем контакт только если есть имя или хотя бы один телефон
                    if (!empty($name) || !empty($phones)) {
                        // Проверяем дубликаты номеров для текущего пользователя
                        $phone1 = $phones[0] ?? null;
                        $phone2 = $phones[1] ?? null;
                        
                        // Проверяем phone1 (проверяем в рамках текущей книги)
                        if ($phone1) {
                            $existingContact = Contact::where('contact_book_id', $contactBookId)
                                ->where(function($query) use ($phone1) {
                                    $query->where('phone1', $phone1)
                                          ->orWhere('phone2', $phone1);
                                })
                                ->first();
                            
                            if ($existingContact) {
                                // Обновляем имя, если оно было пустым или если новое имя не пустое
                                if (empty($existingContact->name) && !empty($name)) {
                                    $existingContact->update(['name' => $name]);
                                }
                                // Если phone2 пустой, а у нового контакта есть phone2, обновляем
                                if (empty($existingContact->phone2) && $phone2) {
                                    $existingContact->update(['phone2' => $phone2]);
                                }
                                continue; // Пропускаем создание дубликата
                            }
                        }
                        
                        // Проверяем phone2, если phone1 не нашелся
                        if ($phone2 && !$phone1) {
                            $existingContact = Contact::where('contact_book_id', $contactBookId)
                                ->where(function($query) use ($phone2) {
                                    $query->where('phone1', $phone2)
                                          ->orWhere('phone2', $phone2);
                                })
                                ->first();
                            
                            if ($existingContact) {
                                if (empty($existingContact->name) && !empty($name)) {
                                    $existingContact->update(['name' => $name]);
                                }
                                continue;
                            }
                        }
                        
                        // Создаем новый контакт, если дубликатов нет
                        Contact::create([
                            'name' => !empty($name) ? $name : null,
                            'phone1' => $phone1,
                            'phone2' => $phone2,
                            'user_id' => $userId,
                            'contact_book_id' => $contactBookId,
                        ]);
                        
                        $contactsCount++;
                    }
                } catch (\Exception $e) {
                    // Пропускаем некорректные контакты и продолжаем обработку
                    \Log::warning('Ошибка при парсинге контакта из VCF: ' . $e->getMessage());
                    continue;
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Ошибка при парсинге VCF файла: ' . $e->getMessage());
        }
        
        return $contactsCount;
    }

    /**
     * Нормализует номер телефона в формат +994XXXXXXXXX
     *
     * @param string $phoneNumber Исходный номер телефона
     * @return string Нормализованный номер или пустая строка если не удалось нормализовать
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Удаляем все нецифровые символы кроме +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // Если номер начинается с +994
        if (str_starts_with($cleaned, '+994')) {
            $digits = substr($cleaned, 4); // Убираем +994
            // Берем первые 9 цифр (для мобильных номеров Азербайджана)
            if (preg_match('/^(\d{9})/', $digits, $matches)) {
                return '+994' . $matches[1];
            }
        }
        
        // Если номер начинается с 994 (без +)
        if (str_starts_with($cleaned, '994')) {
            $digits = substr($cleaned, 3);
            // Берем первые 9 цифр
            if (preg_match('/^(\d{9})/', $digits, $matches)) {
                return '+994' . $matches[1];
            }
        }
        
        // Если номер начинается с 0 (локальный формат Азербайджана: 0XX-XXX-XXXX)
        if (str_starts_with($cleaned, '0')) {
            $digits = substr($cleaned, 1); // Убираем 0
            // Берем первые 9 цифр (например, 055-455-5008 -> 554555008)
            if (preg_match('/^(\d{9})/', $digits, $matches)) {
                return '+994' . $matches[1];
            }
        }
        
        // Если номер состоит только из 9 цифр (без префикса)
        if (preg_match('/^(\d{9})$/', $cleaned, $matches)) {
            return '+994' . $matches[1];
        }
        
        // Если номер содержит 10 цифр и начинается не с 0, но может быть без префикса
        // Пытаемся извлечь последние 9 цифр
        if (preg_match('/(\d{9})$/', $cleaned, $matches)) {
            return '+994' . $matches[1];
        }
        
        // Если ничего не подошло, возвращаем пустую строку
        return '';
    }

    /**
     * Показывает форму редактирования контакта
     */
    public function edit(Contact $contact)
    {
        $user = auth()->user();
        
        // Получаем дефолтную книгу пользователя
        $defaultBook = $user->getDefaultContactBook();
        
        // Check that contact belongs to user's default book
        // User can only edit contacts from their default book
        if ($contact->contact_book_id) {
            if (!$defaultBook || $contact->contact_book_id !== $defaultBook->id) {
                abort(403, 'You can only edit contacts from your department');
            }
        } else {
            // If contact is not linked to a book, check that it belongs to the user
            // and that the user has no default book (old contacts)
            if ($contact->user_id !== $user->id) {
                abort(403, 'You do not have access to this contact');
            }
            // If user has a default book, old contacts cannot be edited
            if ($defaultBook) {
                abort(403, 'You can only edit contacts from your department');
            }
        }

        // Load relationships with user, updatedBy, and contactBook
        $contact->load('user', 'updatedBy', 'contactBook');

        return view('contact-edit', compact('contact'));
    }

    /**
     * Обновляет контакт
     */
    public function update(Request $request, Contact $contact)
    {
        $user = auth()->user();
        
        // Получаем дефолтную книгу пользователя
        $defaultBook = $user->getDefaultContactBook();
        
        // Check that contact belongs to user's default book
        // User can only edit contacts from their default book
        if ($contact->contact_book_id) {
            if (!$defaultBook || $contact->contact_book_id !== $defaultBook->id) {
                abort(403, 'You can only edit contacts from your department');
            }
        } else {
            // If contact is not linked to a book, check that it belongs to the user
            // and that the user has no default book (old contacts)
            if ($contact->user_id !== $user->id) {
                abort(403, 'You do not have access to this contact');
            }
            // If user has a default book, old contacts cannot be edited
            if ($defaultBook) {
                abort(403, 'You can only edit contacts from your department');
            }
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone1' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
        ]);

        // Нормализуем номера телефонов
        if (!empty($validated['phone1'])) {
            $validated['phone1'] = $this->normalizePhoneNumber($validated['phone1']);
        }
        if (!empty($validated['phone2'])) {
            $validated['phone2'] = $this->normalizePhoneNumber($validated['phone2']);
        }

        // Определяем ID книги для проверки дубликатов
        $bookId = $contact->contact_book_id;

        // Check that numbers are not duplicated in other contacts in the same book
        if (!empty($validated['phone1'])) {
            $duplicateQuery = Contact::where('id', '!=', $contact->id)
                ->where(function($query) use ($validated) {
                    $query->where('phone1', $validated['phone1'])
                          ->orWhere('phone2', $validated['phone1']);
                });
            
            if ($bookId) {
                $duplicateQuery->where('contact_book_id', $bookId);
            } else {
                $duplicateQuery->where('user_id', $user->id)->whereNull('contact_book_id');
            }
            
            $duplicate = $duplicateQuery->first();
            
            if ($duplicate) {
                return back()->withErrors(['phone1' => 'A contact with this number already exists in this book']);
            }
        }

        if (!empty($validated['phone2'])) {
            $duplicateQuery = Contact::where('id', '!=', $contact->id)
                ->where(function($query) use ($validated) {
                    $query->where('phone1', $validated['phone2'])
                          ->orWhere('phone2', $validated['phone2']);
                });
            
            if ($bookId) {
                $duplicateQuery->where('contact_book_id', $bookId);
            } else {
                $duplicateQuery->where('user_id', $user->id)->whereNull('contact_book_id');
            }
            
            $duplicate = $duplicateQuery->first();
            
            if ($duplicate) {
                return back()->withErrors(['phone2' => 'A contact with this number already exists in this book']);
            }
        }

        // Add updated_by to track who last edited the contact
        $validated['updated_by'] = auth()->id();
        
        $contact->update($validated);

        return redirect()->route('cabinet.index')->with('success', 'Contact updated successfully');
    }

    /**
     * Удаляет контакт
     */
    public function destroy(Contact $contact)
    {
        // Проверяем, что контакт принадлежит текущему пользователю
        if ($contact->user_id !== auth()->id()) {
            abort(403, 'You do not have access to this contact');
        }

        $contact->delete();

        return redirect()->route('cabinet.index')->with('success', 'Contact deleted successfully');
    }
}
