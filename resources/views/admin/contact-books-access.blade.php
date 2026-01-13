<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Contact Books Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .books-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 8px;
        }
        .form-check.checked {
            background-color: #e7f3ff;
            border-color: #0d6efd;
        }
        .form-check.default-book {
            background-color: #f0f0f0;
            opacity: 0.9;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4" style="max-width: 1200px;">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0 fw-bold border-bottom pb-2">Manage Contact Books Access</h5>
                    <a href="{{ route('cabinet.index') }}" class="btn btn-secondary btn-sm">Back</a>
                </div>

                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if($users->count() > 0)
                    @foreach($users as $user)
                        <div class="card mb-4">
                            <div class="card-body p-4">
                                <div class="mb-4">
                                    <h6 class="mb-2 fw-bold">{{ $user->name }}</h6>
                                    <div class="small text-muted">
                                        @if($user->email)
                                            <span>{{ $user->email }}</span>
                                        @endif
                                        @if($user->getDepartmentOu())
                                            <span class="badge bg-secondary ms-2">{{ $user->getDepartmentOu() }}</span>
                                        @endif
                                        @if($user->role === 'admin')
                                            <span class="badge bg-danger ms-2">Admin</span>
                                        @endif
                                    </div>
                                </div>

                                <form action="{{ route('admin.contact-books-access.update', $user) }}" method="POST">
                                    @csrf
                                    @method('PUT')

                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <label class="form-label fw-bold mb-0 small">Books:</label>
                                            <button type="submit" class="btn btn-dark btn-sm">Save</button>
                                        </div>
                                        <div class="border rounded p-3" style="max-height: 150px; overflow-y: auto;">
                                            <div class="books-list">
                                                @if($contactBooks->count() > 0)
                                                    @foreach($contactBooks as $book)
                                                        @php
                                                            $isChecked = $user->contactBooks->contains('id', $book->id);
                                                            $defaultBook = $user->getDefaultContactBook();
                                                            $isDefaultBook = $defaultBook && $defaultBook->id === $book->id;
                                                            if ($isDefaultBook) {
                                                                $isChecked = true;
                                                            }
                                                            
                                                            // Получаем значение can_delete из pivot
                                                            $canDelete = false;
                                                            if ($isChecked) {
                                                                $pivot = $user->contactBooks()->where('contact_books.id', $book->id)->first();
                                                                if ($pivot && $pivot->pivot->can_delete) {
                                                                    $canDelete = true;
                                                                }
                                                            }
                                                        @endphp
                                                        <div class="form-check border rounded p-3 {{ $isChecked ? 'checked' : '' }} {{ $isDefaultBook ? 'default-book' : '' }}">
                                                            <div class="d-flex align-items-start gap-2">
                                                                <input class="form-check-input mt-1" 
                                                                       type="checkbox" 
                                                                       name="contact_book_ids[]" 
                                                                       value="{{ $book->id }}" 
                                                                       id="book_{{ $user->id }}_{{ $book->id }}"
                                                                       {{ $isChecked ? 'checked' : '' }}
                                                                       {{ $isDefaultBook ? 'disabled' : '' }}
                                                                       onchange="toggleCanDelete({{ $user->id }}, {{ $book->id }}, this.checked)">
                                                                <div class="flex-grow-1">
                                                                    <label class="form-check-label small d-block" for="book_{{ $user->id }}_{{ $book->id }}">
                                                                        <span>{{ $book->name }}</span>
                                                                        @if($book->department_ou)
                                                                            <span class="text-muted">({{ $book->department_ou }})</span>
                                                                        @endif
                                                                        @if($isDefaultBook)
                                                                            <span class="badge bg-secondary ms-1" style="font-size: 9px;">Default</span>
                                                                        @endif
                                                                    </label>
                                                                    @if($isChecked || $isDefaultBook)
                                                                        <div class="mt-2" id="can_delete_container_{{ $user->id }}_{{ $book->id }}" style="display: {{ ($isChecked || $isDefaultBook) ? 'block' : 'none' }};">
                                                                            <div class="form-check form-check-sm">
                                                                                <input class="form-check-input" 
                                                                                       type="checkbox" 
                                                                                       name="can_delete[{{ $book->id }}]" 
                                                                                       value="1" 
                                                                                       id="can_delete_{{ $user->id }}_{{ $book->id }}"
                                                                                       {{ $canDelete ? 'checked' : '' }}>
                                                                                <label class="form-check-label small text-muted" for="can_delete_{{ $user->id }}_{{ $book->id }}">
                                                                                    Can delete contacts
                                                                                </label>
                                                                            </div>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <p class="text-muted small mb-0">No contact books available</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted text-center py-4">No users in the system</p>
                @endif
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCanDelete(userId, bookId, isChecked) {
            const canDeleteCheckbox = document.getElementById('can_delete_' + userId + '_' + bookId);
            if (!canDeleteCheckbox) return;
            
            const canDeleteContainer = canDeleteCheckbox.closest('.mt-2');
            if (!canDeleteContainer) return;
            
            if (isChecked) {
                canDeleteContainer.style.display = 'block';
            } else {
                canDeleteContainer.style.display = 'none';
                canDeleteCheckbox.checked = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name^="contact_book_ids"]');
            
            checkboxes.forEach(function(checkbox) {
                updateCheckboxState(checkbox);
                
                if (!checkbox.disabled) {
                    checkbox.addEventListener('change', function() {
                        updateCheckboxState(this);
                        const bookId = this.value;
                        const userId = this.id.split('_')[1];
                        toggleCanDelete(userId, bookId, this.checked);
                    });
                }
            });
            
            function updateCheckboxState(checkbox) {
                const formCheck = checkbox.closest('.form-check');
                if (checkbox.checked) {
                    formCheck.classList.add('checked');
                } else {
                    formCheck.classList.remove('checked');
                }
            }

            // Инициализируем видимость can_delete для уже отмеченных книг
            checkboxes.forEach(function(checkbox) {
                if (checkbox.checked) {
                    const bookId = checkbox.value;
                    const userId = checkbox.id.split('_')[1];
                    toggleCanDelete(userId, bookId, true);
                }
            });

            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const userCard = form.closest('.card');
                    const defaultCheckbox = userCard.querySelector('input[name^="contact_book_ids"]:disabled');
                    if (defaultCheckbox && defaultCheckbox.checked) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'contact_book_ids[]';
                        hiddenInput.value = defaultCheckbox.value;
                        form.appendChild(hiddenInput);
                    }
                });
            });
        });
    </script>
</body>
</html>
