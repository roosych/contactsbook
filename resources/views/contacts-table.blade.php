<div data-total-contacts="{{ $contacts->total() }}">
@if($contacts->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Organization</th>
                    <th>Phone Number</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contacts as $contact)
                    <tr>
                        <td>{{ $contact->name ?? '-' }}</td>
                        <td>{{ $contact->organization ?? '-' }}</td>
                        <td>
                            @if($contact->phone1)
                                <div>
                                    <a href="tel:{{ $contact->phone1 }}" class="text-decoration-none">{{ $contact->phone1 }}</a>
                                </div>
                            @endif
                            @if($contact->phone2)
                                <div class="mt-1">
                                    <a href="tel:{{ $contact->phone2 }}" class="text-decoration-none">{{ $contact->phone2 }}</a>
                                </div>
                            @endif
                            @if(!$contact->phone1 && !$contact->phone2)
                                -
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex align-items-center justify-content-end gap-2">
                                @php
                                    // Логика прав на редактирование:
                                    // Личные контакты: может редактировать только владелец
                                    // Групповые контакты: может редактировать только тот, кто создал контакт
                                    $canEdit = false;
                                    $isAdminValue = $isAdmin ?? false;
                                    
                                    if ($contact->is_personal) {
                                        $canEdit = $contact->user_id === auth()->id();
                                    } else {
                                        // Для групповых контактов: только создатель может редактировать
                                        $canEdit = $contact->user_id === auth()->id();
                                    }
                                @endphp
                                @if($canEdit)
                                    <a href="{{ route('cabinet.contacts.edit', $contact) }}" 
                                       class="btn btn-sm btn-link text-decoration-none" 
                                       title="Edit"
                                       style="padding: 4px 8px;">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil" viewBox="0 0 16 16">
                                            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168l10-10zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207 11.207 2.5zm1.586 3L10.5 9.207 2.793 1.5 4.5 1.207 12.793 9.5zm-9.5 4.5L1.5 12.207 4.793 15.5 6.5 13.793l-1.207-1.207z"/>
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-muted small" title="You can only edit contacts that you created">
                                        —
                                    </span>
                                @endif
                                
                                @if($contact->is_personal && $contact->user_id === auth()->id())
                                    <button type="button" 
                                            class="btn btn-sm btn-link text-decoration-none" 
                                            title="Move to Group"
                                            style="padding: 4px 8px;"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#moveContactModal_{{ $contact->id }}"
                                            onclick="prepareMoveContact({{ $contact->id }}, '{{ addslashes($contact->name ?? '') }}', '{{ $contact->phone1 ?? '' }}', '{{ $contact->phone2 ?? '' }}')">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right-circle" viewBox="0 0 16 16">
                                            <path fill-rule="evenodd" d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8zm15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM4.5 7.5a.5.5 0 0 0 0 1h5.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3a.5.5 0 0 0 0-.708l-3-3a.5.5 0 1 0-.708.708L10.293 7.5H4.5z"/>
                                        </svg>
                                    </button>
                                @endif
                                
                                @php
                                    // Логика прав на удаление:
                                    // Личные контакты: может удалять только владелец
                                    // Групповые контакты: может удалять только если:
                                    //   1. Пользователь имеет доступ к книге (пермишн) - доступ через user_contact_books или дефолтная книга
                                    //   2. ИЛИ пользователь - админ
                                    // Примечание: создатель контакта сам по себе НЕ может удалять свои контакты
                                    $canDelete = false;
                                    $currentUser = auth()->user();
                                    
                                    if ($contact->is_personal) {
                                        $canDelete = $contact->user_id === $currentUser->id;
                                    } else {
                                        // Для групповых контактов проверяем право на удаление или админ права
                                        if ($isAdminValue) {
                                            $canDelete = true;
                                        } elseif ($contact->contact_book_id) {
                                            // Проверяем право на удаление через user_contact_books с флагом can_delete
                                            // Для дефолтной книги также нужно явно предоставить can_delete через админ-панель
                                            $pivot = $currentUser->contactBooks()
                                                ->where('contact_books.id', $contact->contact_book_id)
                                                ->first();
                                            
                                            if ($pivot && $pivot->pivot->can_delete) {
                                                $canDelete = true;
                                            }
                                        }
                                    }
                                @endphp
                                
                                @if($canDelete)
                                    <form action="{{ route('cabinet.contacts.destroy', $contact) }}" 
                                          method="POST" 
                                          class="d-inline delete-contact-form"
                                          data-contact-name="{{ $contact->name ?? 'Contact' }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="btn btn-sm btn-link text-decoration-none text-danger" 
                                                title="Delete"
                                                style="padding: 4px 8px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4H3.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <div class="mt-3 d-flex justify-content-center">
        {{ $contacts->links('pagination::bootstrap-5') }}
    </div>
@else
    <p class="text-muted text-center py-4">No contacts found.</p>
@endif
</div>

