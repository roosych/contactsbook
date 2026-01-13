<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactBook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCard;

class CabinetController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        // Проверяем режим из сессии, по умолчанию персональные контакты
        $isPersonalMode = session('personal_mode', true);
        
        // Если выбран режим личных контактов
        if ($isPersonalMode) {
            // Показываем только личные контакты текущего пользователя
            $query = Contact::query()
                ->where('user_id', $user->id)
                ->where('is_personal', true);
            
            $selectedBook = null;
            $availableBooks = collect();
        } else {
            // Режим групповых контактов (как раньше)
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
            
            // Фильтруем контакты по выбранной книге (только групповые контакты)
            $query = Contact::query();
            if ($selectedBook) {
                $query->where('contact_book_id', $selectedBook->id)
                      ->where('is_personal', false);
            } else {
                // Если нет книги, показываем только групповые контакты пользователя
                $query->where('user_id', $user->id)
                      ->where('is_personal', false);
            }
        }
        
        // Поиск по имени, организации или номеру телефона
        if ($request->has('search') && strlen($request->search) >= 3) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('organization', 'LIKE', "%{$search}%")
                  ->orWhere('phone1', 'LIKE', "%{$search}%")
                  ->orWhere('phone2', 'LIKE', "%{$search}%");
            });
        }
        
        $contacts = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Передаем ID дефолтной книги для проверки возможности редактирования
        $defaultBook = $user->getDefaultContactBook();
        $defaultBookId = $defaultBook ? $defaultBook->id : null;
        
        // Проверяем, является ли пользователь админом
        $isAdmin = $user->isAdmin();
        
        // Если это AJAX запрос, возвращаем только HTML таблицы
        if ($request->ajax()) {
            return view('contacts-table', compact('contacts', 'defaultBookId', 'isAdmin', 'isPersonalMode'))->render();
        }
        
        return view('index', compact('contacts', 'availableBooks', 'selectedBook', 'defaultBookId', 'isAdmin', 'isPersonalMode'));
    }

    /**
     * Переключает режим отображения контактов (персональные/групповые)
     */
    public function toggleMode(Request $request)
    {
        $isPersonal = $request->input('personal', true);
        session(['personal_mode' => (bool) $isPersonal]);
        
        return redirect()->route('cabinet.index');
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
            'is_personal' => 'nullable|boolean',
        ]);

        try {
            $file = $request->file('vcf_file');
            $originalName = $file->getClientOriginalName();
            $fileName = Str::random(40) . '_' . time() . '.vcf';
            
            // Сохраняем файл в storage/app/vcf
            $path = $file->storeAs('vcf', $fileName, 'local');
            
            $user = auth()->user();
            $isPersonal = $request->has('is_personal') && $request->is_personal;
            
            $contactBookId = null;
            if (!$isPersonal) {
                // Для групповых контактов нужна книга
                $contactBook = $user->getDefaultContactBook();
                
                if (!$contactBook) {
                    return redirect()->route('cabinet.index')->with('error', 
                        'Unable to determine contact book. Please contact the administrator.'
                    );
                }
                $contactBookId = $contactBook->id;
            }
            
            // Parse VCF file and save contacts
            $contactsCount = $this->parseVcfFile(Storage::path($path), auth()->id(), $contactBookId, $isPersonal);
            
            // Устанавливаем режим отображения в зависимости от типа загруженных контактов
            if ($isPersonal) {
                session(['personal_mode' => true]);
            } else {
                session(['personal_mode' => false]);
            }
            
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
     * @param int|null $contactBookId ID книги контактов (null для личных контактов)
     * @param bool $isPersonal Флаг личных контактов
     * @return int Количество обработанных контактов
     */
    private function parseVcfFile(string $filePath, int $userId, ?int $contactBookId, bool $isPersonal = false): int
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
                    
                    // Извлекаем организацию
                    $organization = null;
                    if (isset($vcard->ORG)) {
                        try {
                            $org = $vcard->ORG;
                            // В sabre/vobject ORG - это объект Property
                            // ORG структурированное поле: ORG:Company;Department;Unit
                            // Используем getParts() для получения структурированных частей
                            if (is_object($org) && method_exists($org, 'getParts')) {
                                $orgParts = $org->getParts();
                                // Берем первую часть (название компании)
                                if (!empty($orgParts) && !empty($orgParts[0])) {
                                    $organization = trim((string) $orgParts[0]);
                                }
                            }
                            // Если getParts() не сработал, пробуем getValue()
                            if (empty($organization) && is_object($org) && method_exists($org, 'getValue')) {
                                $orgValue = $org->getValue();
                                if (is_array($orgValue)) {
                                    $organization = !empty($orgValue[0]) ? trim((string) $orgValue[0]) : null;
                                } else {
                                    $organization = trim((string) $orgValue);
                                }
                            }
                            // Если все еще пусто, пробуем просто привести к строке
                            if (empty($organization)) {
                                $orgString = (string) $org;
                                // Если строка содержит точку с запятой, берем первую часть
                                if (strpos($orgString, ';') !== false) {
                                    $parts = explode(';', $orgString);
                                    $organization = trim($parts[0]);
                                } else {
                                    $organization = trim($orgString);
                                }
                            }
                            // Если организация пустая после trim, устанавливаем null
                            if (empty($organization)) {
                                $organization = null;
                            }
                        } catch (\Exception $e) {
                            // Если ошибка при извлечении организации, просто пропускаем
                            $organization = null;
                            \Log::warning('Ошибка при извлечении организации из VCF: ' . $e->getMessage());
                        }
                    }
                    
                    // Извлекаем телефоны
                    $phones = [];
                    if (isset($vcard->TEL)) {
                        foreach ($vcard->TEL as $tel) {
                            $phoneNumber = trim((string) $tel);
                            
                            // Удаляем префиксы типа tel:, TEL: и т.д.
                            $phoneNumber = preg_replace('/^tel:/i', '', $phoneNumber);
                            
                            // Очищаем номер от пробелов, дефисов, скобок, точек
                            // Но сохраняем + для международных номеров
                            $phoneNumber = preg_replace('/[\s\-\(\)\.]/', '', $phoneNumber);
                            
                            if (!empty($phoneNumber)) {
                                // Нормализуем номер:
                                // - Для Азербайджана: +994XXXXXXXXX
                                // - Для других стран: международный формат с +
                                $phoneNumber = $this->normalizePhoneNumber($phoneNumber);
                                
                                if (!empty($phoneNumber)) {
                                    $phones[] = $phoneNumber;
                                } else {
                                    // Логируем номера, которые не удалось нормализовать
                                    \Log::warning('Не удалось нормализовать номер из VCF: ' . trim((string) $tel));
                                }
                            }
                        }
                    }
                    
                    // Удаляем дубликаты номеров (если два номера одинаковые, оставляем один)
                    $phones = array_unique($phones);
                    $phones = array_values($phones); // Переиндексируем массив
                    
                    // Сохраняем контакт только если есть имя И хотя бы один телефон
                    // Если имя не указано ИЛИ не указаны номера - пропускаем контакт
                    if (empty($name) || empty($phones)) {
                        continue;
                    }
                    
                    // Проверяем дубликаты номеров для текущего пользователя
                    $phone1 = $phones[0] ?? null;
                    $phone2 = $phones[1] ?? null;
                    
                    // Проверяем phone1
                    if ($phone1) {
                        $duplicateQuery = Contact::where('user_id', $userId)
                            ->where('is_personal', $isPersonal)
                            ->where(function($query) use ($phone1) {
                                $query->where('phone1', $phone1)
                                      ->orWhere('phone2', $phone1);
                            });
                        
                        // Для групповых контактов проверяем также по книге
                        if (!$isPersonal && $contactBookId) {
                            $duplicateQuery->where('contact_book_id', $contactBookId);
                        }
                        // Для личных контактов contact_book_id должен быть null
                        if ($isPersonal) {
                            $duplicateQuery->whereNull('contact_book_id');
                        }
                        
                        $existingContact = $duplicateQuery->first();
                        
                        if ($existingContact) {
                            // Обновляем имя, если оно было пустым или если новое имя не пустое
                            if (empty($existingContact->name) && !empty($name)) {
                                $existingContact->update(['name' => $name]);
                            }
                            // Обновляем организацию, если она была пустой, а у нового контакта есть организация
                            if (empty($existingContact->organization) && !empty($organization)) {
                                $existingContact->update(['organization' => $organization]);
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
                        $duplicateQuery = Contact::where('user_id', $userId)
                            ->where('is_personal', $isPersonal)
                            ->where(function($query) use ($phone2) {
                                $query->where('phone1', $phone2)
                                      ->orWhere('phone2', $phone2);
                            });
                        
                        // Для групповых контактов проверяем также по книге
                        if (!$isPersonal && $contactBookId) {
                            $duplicateQuery->where('contact_book_id', $contactBookId);
                        }
                        // Для личных контактов contact_book_id должен быть null
                        if ($isPersonal) {
                            $duplicateQuery->whereNull('contact_book_id');
                        }
                        
                        $existingContact = $duplicateQuery->first();
                        
                        if ($existingContact) {
                            if (empty($existingContact->name) && !empty($name)) {
                                $existingContact->update(['name' => $name]);
                            }
                            // Обновляем организацию, если она была пустой, а у нового контакта есть организация
                            if (empty($existingContact->organization) && !empty($organization)) {
                                $existingContact->update(['organization' => $organization]);
                            }
                            continue;
                        }
                    }
                        
                    // Создаем новый контакт, если дубликатов нет
                    // Убеждаемся, что organization не попадает в phone1
                    $contactData = [
                        'name' => !empty($name) ? $name : null,
                        'organization' => $organization,
                        'phone1' => $phone1,
                        'phone2' => $phone2,
                        'user_id' => $userId,
                        'contact_book_id' => $isPersonal ? null : $contactBookId,
                        'is_personal' => $isPersonal,
                    ];
                    
                    // Проверка: если organization не пустая и похожа на телефонный номер, это ошибка
                    // (организация не должна быть телефонным номером)
                    if (!empty($organization)) {
                        // Проверяем, не является ли organization телефонным номером
                        $orgCleaned = preg_replace('/[^\d+]/', '', $organization);
                        // Если organization содержит только цифры и +, это может быть ошибка
                        if (preg_match('/^\+?\d{7,15}$/', $orgCleaned)) {
                            \Log::warning('Организация похожа на телефонный номер, пропускаем', [
                                'organization' => $organization,
                                'name' => $name
                            ]);
                            $contactData['organization'] = null;
                        }
                        // Если organization совпадает с phone1 или phone2, это ошибка
                        if ($organization === $phone1 || $organization === $phone2) {
                            \Log::error('Ошибка: организация совпадает с телефоном', [
                                'organization' => $organization,
                                'phone1' => $phone1,
                                'phone2' => $phone2,
                                'name' => $name
                            ]);
                            $contactData['organization'] = null;
                        }
                    }
                    
                    Contact::create($contactData);
                    
                    $contactsCount++;
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
     * Нормализует номер телефона
     * Для номеров Азербайджана (+994) - в формат +994XXXXXXXXX
     * Для номеров других стран - сохраняет международный формат с +
     *
     * @param string $phoneNumber Исходный номер телефона
     * @return string Нормализованный номер или пустая строка если не удалось нормализовать
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Удаляем все нецифровые символы кроме +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
        // ВАЖНО: Сначала проверяем номера с + (международные), чтобы не перепутать их с локальными
        
        // Если номер начинается с +994 (Азербайджан)
        if (str_starts_with($cleaned, '+994')) {
            $digits = substr($cleaned, 4); // Убираем +994
            // Берем первые 9 цифр (для мобильных номеров Азербайджана)
            if (preg_match('/^(\d{9})/', $digits, $matches)) {
                return '+994' . $matches[1];
            }
        }
        
        // Если номер начинается с + и имеет другой код страны (не 994)
        // ЭТО ДОЛЖНО БЫТЬ ПЕРЕД проверками локальных форматов!
        if (str_starts_with($cleaned, '+')) {
            // Убираем все символы кроме цифр и +
            $normalized = preg_replace('/[^\d+]/', '', $phoneNumber);
            // Проверяем, что есть хотя бы 7 цифр после + (минимальная длина международного номера)
            $digitsOnly = str_replace('+', '', $normalized);
            if (strlen($digitsOnly) >= 7 && strlen($digitsOnly) <= 15) {
                return $normalized;
            }
        }
        
        // Теперь проверяем локальные форматы Азербайджана (без +)
        
        // Если номер начинается с 994 (без +) - Азербайджан
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
        
        // Если номер состоит только из 9 цифр (без префикса) - считаем Азербайджан
        if (preg_match('/^(\d{9})$/', $cleaned, $matches)) {
            return '+994' . $matches[1];
        }
        
        // Если номер начинается без +, но содержит код страны
        // Пытаемся определить международный формат
        $digitsOnly = preg_replace('/\D/', '', $phoneNumber);
        
        // Если номер длинный (10+ цифр), вероятно это международный номер без +
        // Добавляем + в начало
        if (strlen($digitsOnly) >= 10 && strlen($digitsOnly) <= 15) {
            return '+' . $digitsOnly;
        }
        
        // Если номер средний длины (7-9 цифр), может быть локальным или международным
        // Для безопасности добавляем + только если номер явно не похож на локальный формат Азербайджана
        if (strlen($digitsOnly) >= 7 && strlen($digitsOnly) <= 9) {
            // Если не начинается с 0 и не 9 цифр - вероятно международный
            if (!str_starts_with($digitsOnly, '0') && strlen($digitsOnly) != 9) {
                return '+' . $digitsOnly;
            }
        }
        
        // Если ничего не подошло, возвращаем пустую строку
        return '';
    }

    /**
     * Создает новый контакт
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'organization' => 'nullable|string|max:255',
            'phone1' => 'required|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'is_personal' => 'nullable|boolean',
        ]);

        $isPersonal = $request->has('is_personal') && $request->is_personal;

        // Нормализуем номера телефонов
        $phone1 = $this->normalizePhoneNumber($validated['phone1']);
        if (empty($phone1)) {
            return back()->withErrors(['phone1' => 'Invalid phone number format. Please enter a valid international phone number (e.g., +994XXXXXXXXX or +1234567890)']);
        }

        $phone2 = null;
        if (!empty($validated['phone2'])) {
            $phone2 = $this->normalizePhoneNumber($validated['phone2']);
            if (empty($phone2)) {
                return back()->withErrors(['phone2' => 'Invalid phone number format. Please enter a valid international phone number (e.g., +994XXXXXXXXX or +1234567890)']);
            }
        }

        // Если два номера одинаковые, оставляем только один
        if ($phone2 && $phone1 === $phone2) {
            $phone2 = null;
        }

        // Если это личный контакт
        if ($isPersonal) {
            // Проверяем дубликаты только среди личных контактов пользователя
            $duplicateQuery = Contact::where('user_id', $user->id)
                ->where('is_personal', true)
                ->where(function($query) use ($phone1) {
                    $query->where('phone1', $phone1)
                          ->orWhere('phone2', $phone1);
                })
                ->first();

            if ($duplicateQuery) {
                return back()->withErrors(['phone1' => 'A personal contact with this number already exists']);
            }

            if ($phone2) {
                $duplicateQuery = Contact::where('user_id', $user->id)
                    ->where('is_personal', true)
                    ->where(function($query) use ($phone2) {
                        $query->where('phone1', $phone2)
                              ->orWhere('phone2', $phone2);
                    })
                    ->first();

                if ($duplicateQuery) {
                    return back()->withErrors(['phone2' => 'A personal contact with this number already exists']);
                }
            }

            // Создаем личный контакт
            Contact::create([
                'name' => $validated['name'],
                'organization' => $validated['organization'] ?? null,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'user_id' => $user->id,
                'contact_book_id' => null,
                'is_personal' => true,
            ]);

            session(['personal_mode' => true]);
            return redirect()->route('cabinet.index')->with('success', 'Personal contact created successfully');
        } else {
            // Групповой контакт (как раньше)
            $defaultBook = $user->getDefaultContactBook();
            
            if (!$defaultBook) {
                return back()->withErrors(['error' => 'Unable to determine contact book. Please contact the administrator.']);
            }

            $bookId = $defaultBook->id;

            // Проверяем дубликаты номеров в книге отдела
            $duplicateQuery = Contact::where('contact_book_id', $bookId)
                ->where('is_personal', false)
                ->where(function($query) use ($phone1) {
                    $query->where('phone1', $phone1)
                          ->orWhere('phone2', $phone1);
                })
                ->first();

            if ($duplicateQuery) {
                return back()->withErrors(['phone1' => 'A contact with this number already exists in this book']);
            }

            if ($phone2) {
                $duplicateQuery = Contact::where('contact_book_id', $bookId)
                    ->where('is_personal', false)
                    ->where(function($query) use ($phone2) {
                        $query->where('phone1', $phone2)
                              ->orWhere('phone2', $phone2);
                    })
                    ->first();

                if ($duplicateQuery) {
                    return back()->withErrors(['phone2' => 'A contact with this number already exists in this book']);
                }
            }

            // Создаем групповой контакт
            Contact::create([
                'name' => $validated['name'],
                'organization' => $validated['organization'] ?? null,
                'phone1' => $phone1,
                'phone2' => $phone2,
                'user_id' => $user->id,
                'contact_book_id' => $bookId,
                'is_personal' => false,
            ]);

            return redirect()->route('cabinet.index', ['book_id' => $bookId])->with('success', 'Contact created successfully');
        }
    }

    /**
     * Показывает форму редактирования контакта
     */
    public function edit(Contact $contact)
    {
        $user = auth()->user();
        
        // Для личных контактов: только владелец может редактировать
        if ($contact->is_personal) {
            if ($contact->user_id !== $user->id) {
                abort(403, 'You can only edit your own personal contacts');
            }
        } else {
            // Для групповых контактов: может редактировать только тот, кто создал контакт
            if ($contact->user_id !== $user->id) {
                abort(403, 'You can only edit contacts that you created');
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
        
        // Для личных контактов: только владелец может редактировать
        if ($contact->is_personal) {
            if ($contact->user_id !== $user->id) {
                abort(403, 'You can only edit your own personal contacts');
            }
        } else {
            // Для групповых контактов: может редактировать только тот, кто создал контакт
            if ($contact->user_id !== $user->id) {
                abort(403, 'You can only edit contacts that you created');
            }
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
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

        // Проверяем дубликаты в зависимости от типа контакта
        if ($contact->is_personal) {
            // Для личных контактов: проверяем только среди личных контактов пользователя
            if (!empty($validated['phone1'])) {
                $duplicateQuery = Contact::where('id', '!=', $contact->id)
                    ->where('user_id', $user->id)
                    ->where('is_personal', true)
                    ->where(function($query) use ($validated) {
                        $query->where('phone1', $validated['phone1'])
                              ->orWhere('phone2', $validated['phone1']);
                    });
                
                $duplicate = $duplicateQuery->first();
                
                if ($duplicate) {
                    return back()->withErrors(['phone1' => 'A personal contact with this number already exists']);
                }
            }

            if (!empty($validated['phone2'])) {
                $duplicateQuery = Contact::where('id', '!=', $contact->id)
                    ->where('user_id', $user->id)
                    ->where('is_personal', true)
                    ->where(function($query) use ($validated) {
                        $query->where('phone1', $validated['phone2'])
                              ->orWhere('phone2', $validated['phone2']);
                    });
                
                $duplicate = $duplicateQuery->first();
                
                if ($duplicate) {
                    return back()->withErrors(['phone2' => 'A personal contact with this number already exists']);
                }
            }
        } else {
            // Для групповых контактов: проверка как раньше
            $bookId = $contact->contact_book_id;

            // Check that numbers are not duplicated in other contacts in the same book
            if (!empty($validated['phone1'])) {
                $duplicateQuery = Contact::where('id', '!=', $contact->id)
                    ->where('is_personal', false)
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
                    ->where('is_personal', false)
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
        }

        // Add updated_by to track who last edited the contact
        $validated['updated_by'] = auth()->id();
        
        $contact->update($validated);

        // Перенаправляем обратно в соответствующий режим
        if ($contact->is_personal) {
            session(['personal_mode' => true]);
        } else {
            session(['personal_mode' => false]);
        }
        return redirect()->route('cabinet.index')->with('success', 'Contact updated successfully');
    }

    /**
     * Проверяет, имеет ли пользователь доступ к книге контактов
     */
    private function hasAccessToBook($user, $contactBookId): bool
    {
        if (!$contactBookId) {
            return false;
        }
        
        // Проверяем, является ли это дефолтной книгой пользователя
        $defaultBook = $user->getDefaultContactBook();
        if ($defaultBook && $defaultBook->id === $contactBookId) {
            return true;
        }
        
        // Проверяем, имеет ли пользователь доступ через user_contact_books
        return $user->contactBooks()->where('contact_books.id', $contactBookId)->exists();
    }

    /**
     * Проверяет, имеет ли пользователь право на удаление контактов в книге
     */
    private function canDeleteFromBook($user, $contactBookId): bool
    {
        if (!$contactBookId) {
            return false;
        }
        
        // Проверяем, имеет ли пользователь доступ через user_contact_books с флагом can_delete
        // Для дефолтной книги также нужно явно предоставить can_delete через админ-панель
        $pivot = $user->contactBooks()
            ->where('contact_books.id', $contactBookId)
            ->first();
        
        if ($pivot && $pivot->pivot->can_delete) {
            return true;
        }
        
        return false;
    }

    /**
     * Удаляет контакт
     */
    public function destroy(Contact $contact)
    {
        $user = auth()->user();
        
        // Для личных контактов: только владелец может удалять
        if ($contact->is_personal) {
            if ($contact->user_id !== $user->id) {
                abort(403, 'You can only delete your own personal contacts');
            }
        } else {
            // Для групповых контактов: может удалять только если:
            // 1. Пользователь имеет право на удаление в книге (can_delete = true в user_contact_books или дефолтная книга)
            // 2. ИЛИ пользователь - админ
            // Примечание: создатель контакта сам по себе НЕ может удалять свои контакты
            $canDelete = false;
            
            if ($user->isAdmin()) {
                $canDelete = true;
            } elseif ($this->canDeleteFromBook($user, $contact->contact_book_id)) {
                $canDelete = true;
            }
            
            if (!$canDelete) {
                abort(403, 'You do not have permission to delete this contact');
            }
        }

        $contact->delete();

        // Перенаправляем обратно в соответствующий режим
        if ($contact->is_personal) {
            session(['personal_mode' => true]);
        } else {
            session(['personal_mode' => false]);
        }
        return redirect()->route('cabinet.index')->with('success', 'Contact deleted successfully');
    }

    /**
     * Экспортирует личные контакты пользователя в VCF файл
     */
    public function exportPersonalContacts(Request $request)
    {
        $user = auth()->user();
        
        // Получаем все личные контакты пользователя
        $contacts = Contact::where('user_id', $user->id)
            ->where('is_personal', true)
            ->orderBy('name')
            ->get();
        
        if ($contacts->isEmpty()) {
            return redirect()->route('cabinet.index')->with('error', 'No personal contacts to export');
        }
        
        // Создаем VCF файл
        $vcfContent = '';
        
        foreach ($contacts as $contact) {
            $vcard = new VCard();
            
            // Добавляем имя
            if (!empty($contact->name)) {
                $vcard->add('FN', $contact->name);
                
                // Парсим имя на части для поля N (Family Name;Given Name;Additional Names;Honorific Prefixes;Honorific Suffixes)
                $nameParts = explode(' ', trim($contact->name), 2);
                if (count($nameParts) >= 2) {
                    // Если есть пробел, первая часть - имя, вторая - фамилия
                    $vcard->add('N', [$nameParts[1], $nameParts[0], '', '', '']);
                } else {
                    // Если нет пробела, все идет в фамилию
                    $vcard->add('N', [$nameParts[0], '', '', '', '']);
                }
            }
            
            // Добавляем организацию
            if (!empty($contact->organization)) {
                $vcard->add('ORG', $contact->organization);
            }
            
            // Добавляем телефоны
            if (!empty($contact->phone1)) {
                $vcard->add('TEL', $contact->phone1, ['TYPE' => 'CELL']);
            }
            
            if (!empty($contact->phone2)) {
                $vcard->add('TEL', $contact->phone2, ['TYPE' => 'CELL']);
            }
            
            $vcfContent .= $vcard->serialize() . "\n";
        }
        
        // Генерируем имя файла
        $fileName = 'personal_contacts_' . date('Y-m-d_His') . '.vcf';
        
        // Возвращаем файл для скачивания
        return response($vcfContent)
            ->header('Content-Type', 'text/vcard; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"')
            ->header('Content-Length', strlen($vcfContent));
    }

    /**
     * Проверяет наличие дубликата контакта в групповой книге
     */
    public function checkDuplicate(Request $request)
    {
        $user = auth()->user();
        $contactId = $request->get('contact_id');
        
        $contact = Contact::where('id', $contactId)
            ->where('user_id', $user->id)
            ->where('is_personal', true)
            ->first();
        
        if (!$contact) {
            return response()->json([
                'has_duplicate' => false,
                'message' => 'Contact not found'
            ], 404);
        }
        
        // Получаем дефолтную книгу пользователя
        $defaultBook = $user->getDefaultContactBook();
        
        if (!$defaultBook) {
            return response()->json([
                'has_duplicate' => false,
                'message' => 'No default book found'
            ]);
        }
        
        // Проверяем дубликаты по телефонным номерам
        $duplicateQuery = Contact::where('contact_book_id', $defaultBook->id)
            ->where('is_personal', false)
            ->where(function($query) use ($contact) {
                if ($contact->phone1) {
                    $query->where('phone1', $contact->phone1)
                          ->orWhere('phone2', $contact->phone1);
                }
                if ($contact->phone2) {
                    $query->orWhere('phone1', $contact->phone2)
                          ->orWhere('phone2', $contact->phone2);
                }
            })
            ->first();
        
        if ($duplicateQuery) {
            return response()->json([
                'has_duplicate' => true,
                'group_contact_id' => $duplicateQuery->id,
                'group_contact_name' => $duplicateQuery->name ?? '-',
            ]);
        }
        
        return response()->json([
            'has_duplicate' => false
        ]);
    }

    /**
     * Перемещает контакт из личной книги в групповую
     */
    public function moveToGroup(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'name_choice' => 'nullable|in:group,personal',
            'group_contact_id' => 'nullable|exists:contacts,id',
        ]);
        
        $contact = Contact::where('id', $validated['contact_id'])
            ->where('user_id', $user->id)
            ->where('is_personal', true)
            ->first();
        
        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found or you do not have permission'
            ], 404);
        }
        
        // Получаем дефолтную книгу пользователя
        $defaultBook = $user->getDefaultContactBook();
        
        if (!$defaultBook) {
            return response()->json([
                'success' => false,
                'message' => 'No default book found'
            ], 400);
        }
        
        // Если есть дубликат и выбран выбор имени
        if (!empty($validated['group_contact_id']) && !empty($validated['name_choice'])) {
            $groupContact = Contact::where('id', $validated['group_contact_id'])
                ->where('contact_book_id', $defaultBook->id)
                ->where('is_personal', false)
                ->first();
            
            if ($groupContact) {
                // Обновляем имя в групповом контакте в зависимости от выбора
                if ($validated['name_choice'] === 'personal' && !empty($contact->name)) {
                    $groupContact->update(['name' => $contact->name]);
                }
                
                // Обновляем организацию, если она пустая в групповом контакте
                if (empty($groupContact->organization) && !empty($contact->organization)) {
                    $groupContact->update(['organization' => $contact->organization]);
                }
                
                // Обновляем phone2, если он пустой в групповом контакте
                if (empty($groupContact->phone2) && !empty($contact->phone2)) {
                    $groupContact->update(['phone2' => $contact->phone2]);
                } elseif (empty($groupContact->phone2) && !empty($contact->phone1) && $groupContact->phone1 !== $contact->phone1) {
                    // Если phone2 пустой, но phone1 отличается, добавляем phone1 из личного контакта как phone2
                    $groupContact->update(['phone2' => $contact->phone1]);
                }
                
                // Личный контакт остается в личной книге (не удаляем)
                
                return response()->json([
                    'success' => true,
                    'message' => 'Contact copied to group book and merged with existing contact'
                ]);
            }
        }
        
        // Проверяем дубликаты перед созданием копии
        $duplicateQuery = Contact::where('contact_book_id', $defaultBook->id)
            ->where('is_personal', false)
            ->where(function($query) use ($contact) {
                if ($contact->phone1) {
                    $query->where('phone1', $contact->phone1)
                          ->orWhere('phone2', $contact->phone1);
                }
                if ($contact->phone2) {
                    $query->orWhere('phone1', $contact->phone2)
                          ->orWhere('phone2', $contact->phone2);
                }
            })
            ->first();
        
        if ($duplicateQuery) {
            return response()->json([
                'success' => false,
                'message' => 'Contact with this number already exists in group book'
            ], 400);
        }
        
        // Создаем копию контакта в групповой книге (личный контакт остается)
        Contact::create([
            'name' => $contact->name,
            'organization' => $contact->organization,
            'phone1' => $contact->phone1,
            'phone2' => $contact->phone2,
            'user_id' => $user->id,
            'contact_book_id' => $defaultBook->id,
            'is_personal' => false,
        ]);
        
        // Устанавливаем режим групповых контактов
        session(['personal_mode' => false]);
        
        return response()->json([
            'success' => true,
            'message' => 'Contact copied to group book successfully'
        ]);
    }
}
