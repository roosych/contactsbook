<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacts Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        .search-loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4" style="max-width: 900px;">
        <!-- User Information -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1 fw-bold">{{ auth()->user()->name }}</h5>
                        <p class="mb-0 text-muted small">{{ auth()->user()->email }}</p>
                        @if(auth()->user()->isAdmin())
                            <small class="text-muted">Administrator</small>
                        @endif
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @if(auth()->user()->isAdmin())
                            <a href="{{ route('admin.contact-books-access.index') }}" class="btn btn-danger btn-sm">
                                Admin Panel
                            </a>
                        @endif
                        <form action="{{ route('logout') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- VCF File Upload Form -->
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h5 class="card-title mb-3 pb-2 border-bottom">Upload VCF File</h5>
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('cabinet.upload') }}" method="POST" enctype="multipart/form-data" id="uploadForm">
                @csrf
                <input type="hidden" name="is_personal" id="upload_is_personal" value="0">
                
                    <div class="d-flex gap-2 align-items-center">
                        <div class="border border-2 border-dashed rounded p-2 flex-grow-1 d-flex align-items-center" 
                             style="cursor: pointer; min-height: 48px;"
                             onclick="document.getElementById('vcf_file').click()" 
                             id="uploadArea">
                            <span class="me-2" style="font-size: 24px;">📄</span>
                            <div class="flex-grow-1">
                                <h6 class="mb-0 fw-semibold" id="uploadTitle" style="font-size: 14px;">Click to select file</h6>
                                <p class="text-muted mb-0 small" id="uploadSubtitle">or drag and drop file here (.vcf)</p>
                            </div>
                        </div>
                        
                        <button type="button" id="uploadBtn" class="btn btn-dark d-none" disabled style="white-space: nowrap;" onclick="showUploadConfirmModal()">
                            <span id="uploadBtnText">Upload</span>
                            <span id="uploadBtnSpinner" class="spinner-border spinner-border-sm d-none ms-2" role="status" aria-hidden="true"></span>
                        </button>
                </div>
                
                <input type="file" 
                       name="vcf_file" 
                       id="vcf_file" 
                       class="d-none" 
                       accept=".vcf"
                       required
                       onchange="handleFileSelect(this)">
                
                    <div id="fileName" class="mt-2 p-2 bg-light rounded small" style="display: none;">
                        <p id="fileNameText" class="mb-0 fw-medium"></p>
                    </div>
                </form>
            </div>
        </div>

        <!-- Contacts List -->
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <h5 class="mb-0 fw-bold">Contacts (<span id="contacts-count">{{ $contacts->total() }}</span>)</h5>
                        
                        <!-- Toggle between Personal and Group contacts -->
                        <div class="d-flex align-items-center gap-2" role="group" aria-label="Contact type toggle">
                            <form action="{{ route('cabinet.toggle-mode') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="personal" value="1">
                                <button type="submit" 
                                        class="btn btn-sm {{ ($isPersonalMode ?? true) ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Personal Contacts
                                </button>
                            </form>
                            <form action="{{ route('cabinet.toggle-mode') }}" method="POST" class="d-inline">
                                @csrf
                                <input type="hidden" name="personal" value="0">
                                <button type="submit" 
                                        class="btn btn-sm {{ !($isPersonalMode ?? true) ? 'btn-primary' : 'btn-outline-primary' }}">
                                    Group Contacts
                                </button>
                            </form>
                        </div>
                        
                        @if(!($isPersonalMode ?? true))
                            @if(isset($availableBooks) && $availableBooks->count() > 1)
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="bookDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                        @if(isset($selectedBook))
                                            {{ $selectedBook->name }}
                                        @else
                                            Select Book
                                        @endif
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="bookDropdown">
                                        @foreach($availableBooks as $book)
                                            <li>
                                                <a class="dropdown-item {{ isset($selectedBook) && $selectedBook->id === $book->id ? 'active' : '' }}" 
                                                   href="{{ route('cabinet.index', ['book_id' => $book->id]) }}">
                                                    {{ $book->name }}
                                                    @if($book->department_ou)
                                                        <small class="text-muted d-block">{{ $book->department_ou }}</small>
                                                    @endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @elseif(isset($selectedBook))
                                <span class="badge bg-secondary">{{ $selectedBook->name }}</span>
                            @endif
                        @endif
                </div>

                <div class="d-flex gap-2">
                    <button type="button" 
                            class="btn btn-sm btn-success" 
                            data-bs-toggle="modal" 
                            data-bs-target="#addContactModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
                        Add Contact
                    </button>
                    
                    @if($isPersonalMode ?? true)
                        <a href="{{ route('cabinet.export-personal') }}" 
                           class="btn btn-sm btn-outline-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
                                <path d="M.5 9.9a.5.5 0 0 1 .5.5h2a.5.5 0 0 1 0 1H3a1 1 0 0 0-1 1V14a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-1.6a1 1 0 0 0-1-1h-.5a.5.5 0 0 1 0-1h.5a.5.5 0 0 1 .5.5V14a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5v-1.6a.5.5 0 0 1 .5-.5h.5a.5.5 0 0 1 0-1H1a.5.5 0 0 1-.5-.5V10a.5.5 0 0 1 .5-.5h.5z"/>
                                <path d="M8 0a.5.5 0 0 1 .5.5v8.793l2.146-2.147a.5.5 0 0 1 .708.708l-3 3a.5.5 0 0 1-.708 0l-3-3a.5.5 0 1 1 .708-.708L7.5 9.293V.5A.5.5 0 0 1 8 0z"/>
                            </svg>
                            Export to VCF
                        </a>
                    @endif
                </div>
                </div>
                
                <div class="mb-3">
                    <div style="max-width: 300px; width: 100%;">
                        <input type="text"
                               id="search-input"
                               class="form-control form-control-sm"
                               placeholder="Search by name or number..."
                               autocomplete="off">
                    </div>
                </div>

                <div id="contacts-container">
                    @include('contacts-table', ['contacts' => $contacts, 'defaultBookId' => $defaultBookId ?? null, 'isAdmin' => $isAdmin ?? false])
                </div>
            </div>
        </div>
    </div>

    <!-- Add Contact Modal -->
    <div class="modal fade" id="addContactModal" tabindex="-1" aria-labelledby="addContactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addContactModalLabel">Add New Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addContactForm" action="{{ route('cabinet.contacts.store') }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="contact_name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="contact_name" 
                                   name="name" 
                                   value="{{ old('name') }}"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact_organization" class="form-label fw-semibold">Organization</label>
                            <input type="text" 
                                   class="form-control @error('organization') is-invalid @enderror" 
                                   id="contact_organization" 
                                   name="organization" 
                                   value="{{ old('organization') }}"
                                   placeholder="Enter organization name">
                            @error('organization')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact_phone1" class="form-label fw-semibold">Phone 1 <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('phone1') is-invalid @enderror" 
                                   id="contact_phone1" 
                                   name="phone1" 
                                   value="{{ old('phone1') }}"
                                   placeholder="+994XXXXXXXXX or +1234567890"
                                   required>
                            @error('phone1')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="contact_phone2" class="form-label fw-semibold">Phone 2</label>
                            <input type="text" 
                                   class="form-control @error('phone2') is-invalid @enderror" 
                                   id="contact_phone2" 
                                   name="phone2" 
                                   value="{{ old('phone2') }}"
                                   placeholder="+994XXXXXXXXX or +1234567890">
                            @error('phone2')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="contact_is_personal" 
                                       name="is_personal" 
                                       value="1"
                                       {{ old('is_personal') ? 'checked' : '' }}>
                                <label class="form-check-label" for="contact_is_personal">
                                    Personal Contact (only visible to you)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success" id="submitContactBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Add Contact
                    </button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script>
        // Open modal if there are validation errors
        @if($errors->any() && old('_token'))
            document.addEventListener('DOMContentLoaded', function() {
                const modal = new bootstrap.Modal(document.getElementById('addContactModal'));
                modal.show();
            });
        @endif

        // Функция для показа модалки подтверждения загрузки
        function showUploadConfirmModal() {
            const fileInput = document.getElementById('vcf_file');
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to upload');
                return;
            }
            
            // Показываем модалку
            const modal = new bootstrap.Modal(document.getElementById('uploadConfirmModal'));
            modal.show();
        }

        // Функция для подтверждения и начала загрузки
        function confirmUpload() {
            const fileInput = document.getElementById('vcf_file');
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to upload');
                return;
            }

            // Получаем выбранный тип книги
            const bookType = document.querySelector('input[name="upload_book_type"]:checked').value;
            document.getElementById('upload_is_personal').value = bookType === 'personal' ? '1' : '0';

            // Закрываем модалку
            const modal = bootstrap.Modal.getInstance(document.getElementById('uploadConfirmModal'));
            modal.hide();

            // Отправляем форму
            const uploadForm = document.getElementById('uploadForm');
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadBtnText = document.getElementById('uploadBtnText');
            const uploadBtnSpinner = document.getElementById('uploadBtnSpinner');

            // Disable button and show spinner
            uploadBtn.disabled = true;
            uploadBtnText.textContent = 'Uploading...';
            uploadBtnSpinner.classList.remove('d-none');

            // Submit form
            uploadForm.submit();
        }
        function handleFileSelect(input) {
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                document.getElementById('fileNameText').textContent = fileName;
                document.getElementById('fileName').style.display = 'block';
                
                // Update upload area
                const uploadArea = document.getElementById('uploadArea');
                const uploadTitle = document.getElementById('uploadTitle');
                const uploadSubtitle = document.getElementById('uploadSubtitle');
                uploadTitle.textContent = fileName;
                if (uploadSubtitle) {
                    uploadSubtitle.textContent = 'Ready to upload';
                }
                uploadArea.classList.add('bg-light', 'border-primary');
                
                // Show and enable upload button
                uploadBtn.classList.remove('d-none');
                uploadBtn.disabled = false;
            } else {
                // If file is not selected, hide and disable button
                uploadBtn.classList.add('d-none');
                uploadBtn.disabled = true;
            }
        }

        // Handle drag and drop
        const uploadArea = document.getElementById('uploadArea');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.add('bg-secondary', 'bg-opacity-10', 'border-primary');
        });

        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('bg-secondary', 'bg-opacity-10', 'border-primary');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            e.stopPropagation();
            uploadArea.classList.remove('bg-secondary', 'bg-opacity-10', 'border-primary');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.name.endsWith('.vcf')) {
                    const fileInput = document.getElementById('vcf_file');
                    // Create new FileList via DataTransfer
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;
                    handleFileSelect(fileInput);
                } else {
                    alert('Please select a file with .vcf extension');
                }
            }
        });

        // Contact search
        let searchTimeout;
        const searchInput = document.getElementById('search-input');
        const contactsContainer = document.getElementById('contacts-container');
        const contactsCount = document.getElementById('contacts-count');

        searchInput.addEventListener('input', function(e) {
            const searchValue = e.target.value.trim();

            // Clear previous timer
            clearTimeout(searchTimeout);

            // If less than 3 characters, show all contacts
            if (searchValue.length < 3) {
                if (searchValue.length === 0) {
                    // Reload page to show all contacts
                    window.location.href = '{{ route("cabinet.index") }}';
                }
                return;
            }

            // Set new timer for debounce (300ms)
            searchTimeout = setTimeout(() => {
                performSearch(searchValue);
            }, 300);
        });

        function performSearch(searchValue) {
            // Show loading indicator
            contactsContainer.classList.add('search-loading');

            // Get current book_id from URL (personal mode is stored in session, not in URL)
            const urlParams = new URLSearchParams(window.location.search);
            let currentBookId = urlParams.get('book_id');
            @if(isset($selectedBook) && $selectedBook)
                if (!currentBookId) {
                    currentBookId = '{{ $selectedBook->id }}';
                }
            @endif
            
            // Build URL with search and book_id parameters (personal mode is in session)
            let searchUrl = `{{ route('cabinet.index') }}?search=${encodeURIComponent(searchValue)}`;
            if (currentBookId) {
                searchUrl += `&book_id=${currentBookId}`;
            }
            
            // Execute AJAX request
            fetch(searchUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html',
                }
            })
            .then(response => response.text())
            .then(html => {
                // Update contacts container
                contactsContainer.innerHTML = html;
                contactsContainer.classList.remove('search-loading');

                // Update contacts counter from data attribute
                const containerDiv = contactsContainer.querySelector('[data-total-contacts]');
                if (containerDiv) {
                    const total = containerDiv.getAttribute('data-total-contacts');
                    contactsCount.textContent = total;
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                contactsContainer.classList.remove('search-loading');
            });
        }

        // Handle pagination clicks (event delegation)
        document.addEventListener('click', function(e) {
            if (e.target.closest('.pagination a')) {
                e.preventDefault();
                const url = e.target.closest('.pagination a').href;
                const searchValue = searchInput.value.trim();

                // Add search parameter to pagination URL (personal mode is in session)
                const urlObj = new URL(url);
                if (searchValue.length >= 3) {
                    urlObj.searchParams.set('search', searchValue);
                }

                contactsContainer.classList.add('search-loading');

                fetch(urlObj.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    }
                })
                .then(response => response.text())
                .then(html => {
                    contactsContainer.innerHTML = html;
                    contactsContainer.classList.remove('search-loading');

                    // Update contacts counter
                    const containerDiv = contactsContainer.querySelector('[data-total-contacts]');
                    if (containerDiv) {
                        const total = containerDiv.getAttribute('data-total-contacts');
                        contactsCount.textContent = total;
                    }

                    // Scroll to table start
                    contactsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                })
                .catch(error => {
                    console.error('Page load error:', error);
                    contactsContainer.classList.remove('search-loading');
                });
            }
        });

        // Handle contact deletion with confirmation
        document.addEventListener('submit', function(e) {
            const form = e.target.closest('.delete-contact-form');
            if (form) {
                e.preventDefault();
                
                const contactName = form.getAttribute('data-contact-name') || 'this contact';
                
                if (confirm(`Are you sure you want to delete "${contactName}"? This action cannot be undone.`)) {
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalHTML = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
                    
                    // Submit form
                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new FormData(form)
                    })
                    .then(response => {
                        if (response.redirected) {
                            // If redirected, follow the redirect
                            window.location.href = response.url;
                        } else if (response.ok) {
                            // Reload the page to show updated contacts
                            window.location.reload();
                        } else {
                            return response.text().then(text => {
                                throw new Error('Error deleting contact');
                            });
                        }
                    })
                    .catch(error => {
                        alert('Error: ' + error.message);
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHTML;
                    });
                }
            }
        });

        // Initialize input masks for phone numbers in modal
        const addContactModal = document.getElementById('addContactModal');
        if (addContactModal) {
            addContactModal.addEventListener('shown.bs.modal', function() {
                const phone1Input = document.getElementById('contact_phone1');
                const phone2Input = document.getElementById('contact_phone2');
                
                // Function to format phone input for international numbers
                function formatPhoneInput(input) {
                    if (!input) return;
                    
                    // Handle input event - allow international format
                    input.addEventListener('input', function(e) {
                        let value = this.value;
                        const caretPos = this.selectionStart;
                        
                        // Remove all characters except digits and +
                        let cleaned = value.replace(/[^\d+]/g, '');
                        
                        // Ensure it starts with +
                        if (!cleaned.startsWith('+')) {
                            // If user is typing digits, add + at the beginning
                            if (cleaned.length > 0) {
                                cleaned = '+' + cleaned;
                            } else {
                                cleaned = '+';
                            }
                        }
                        
                        // Limit to 16 characters total (1 for + and up to 15 digits for international format)
                        if (cleaned.length > 16) {
                            cleaned = cleaned.substring(0, 16);
                        }
                        
                        this.value = cleaned;
                        
                        // Restore cursor position
                        const newCaretPos = Math.min(caretPos, this.value.length);
                        this.setSelectionRange(newCaretPos, newCaretPos);
                    });
                    
                    // Prevent deletion of + at the beginning
                    input.addEventListener('keydown', function(e) {
                        const caretPos = this.selectionStart;
                        if (caretPos === 1 && (e.key === 'Backspace' || e.key === 'Delete')) {
                            e.preventDefault();
                            return false;
                        }
                    });
                    
                    // Handle paste - clean and format
                    input.addEventListener('paste', function(e) {
                        e.preventDefault();
                        const pasted = (e.clipboardData || window.clipboardData).getData('text');
                        
                        // Remove all characters except digits and +
                        let cleaned = pasted.replace(/[^\d+]/g, '');
                        
                        // Ensure it starts with +
                        if (!cleaned.startsWith('+')) {
                            if (cleaned.length > 0) {
                                cleaned = '+' + cleaned;
                            } else {
                                cleaned = '+';
                            }
                        }
                        
                        // Limit to 16 characters
                        if (cleaned.length > 16) {
                            cleaned = cleaned.substring(0, 16);
                        }
                        
                        this.value = cleaned;
                    });
                }
                
                // Apply formatting to both inputs
                formatPhoneInput(phone1Input);
                formatPhoneInput(phone2Input);
            });

            // Clear form when modal is hidden
            addContactModal.addEventListener('hidden.bs.modal', function() {
                const form = document.getElementById('addContactForm');
                if (form) {
                    form.reset();
                    // Remove validation classes
                    form.querySelectorAll('.is-invalid').forEach(el => {
                        el.classList.remove('is-invalid');
                    });
                    // Hide error alert if exists
                    const errorAlert = form.querySelector('.alert-danger');
                    if (errorAlert) {
                        errorAlert.remove();
                    }
                }
            });
        }

        // Handle add contact form submission
        const addContactForm = document.getElementById('addContactForm');
        if (addContactForm) {
            addContactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const submitBtn = document.getElementById('submitContactBtn');
                const spinner = submitBtn.querySelector('.spinner-border');
                const originalText = submitBtn.innerHTML;
                
                // Show loading state
                submitBtn.disabled = true;
                spinner.classList.remove('d-none');
                
                // Get form data
                const formData = new FormData(this);
                
                // Phone values will be normalized on server side
                // Just ensure phone2 is removed if empty
                const phone2Input = document.getElementById('contact_phone2');
                if (!phone2Input || !phone2Input.value.trim()) {
                    formData.delete('phone2');
                }
                
                // Submit form
                fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                    body: formData
                })
                .then(response => {
                    if (response.redirected) {
                        // Success - redirect
                        window.location.href = response.url;
                    } else if (response.ok) {
                        return response.text();
                    } else {
                        return response.text().then(html => {
                            // Parse HTML to extract errors
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const errorAlert = doc.querySelector('.alert-danger');
                            
                            if (errorAlert) {
                                // Show errors in modal
                                const modalBody = addContactForm.querySelector('.modal-body');
                                const existingAlert = modalBody.querySelector('.alert-danger');
                                if (existingAlert) {
                                    existingAlert.remove();
                                }
                                modalBody.insertBefore(errorAlert.cloneNode(true), modalBody.firstChild);
                                
                                // Mark invalid fields
                                const invalidFields = doc.querySelectorAll('.is-invalid');
                                invalidFields.forEach(invalidField => {
                                    const fieldName = invalidField.getAttribute('name') || invalidField.id;
                                    const formField = addContactForm.querySelector(`[name="${fieldName}"], #${fieldName}`);
                                    if (formField) {
                                        formField.classList.add('is-invalid');
                                        const feedback = invalidField.nextElementSibling;
                                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                                            if (!formField.nextElementSibling || !formField.nextElementSibling.classList.contains('invalid-feedback')) {
                                                formField.insertAdjacentElement('afterend', feedback.cloneNode(true));
                                            }
                                        }
                                    }
                                });
                            }
                            
                            throw new Error('Validation failed');
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    submitBtn.disabled = false;
                    spinner.classList.add('d-none');
                });
            });
        }
    </script>

    <!-- Upload Confirm Modal -->
    <div class="modal fade" id="uploadConfirmModal" tabindex="-1" aria-labelledby="uploadConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadConfirmModalLabel">Confirm Upload</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">In which book will the contacts be uploaded?</p>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="upload_book_type" 
                                   id="uploadBookPersonal" 
                                   value="personal" 
                                   checked>
                            <label class="form-check-label" for="uploadBookPersonal">
                                <strong>Personal Contacts</strong>
                                <span class="d-block text-muted small">Only visible to you</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" 
                                   type="radio" 
                                   name="upload_book_type" 
                                   id="uploadBookGroup" 
                                   value="group">
                            <label class="form-check-label" for="uploadBookGroup">
                                <strong>Group Contacts</strong>
                                <span class="d-block text-muted small">Visible to your department</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="confirmUpload()">Confirm and Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Move Contact Modal -->
    <div class="modal fade" id="moveContactModal" tabindex="-1" aria-labelledby="moveContactModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="moveContactModalLabel">Move Contact to Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="moveContactForm" method="POST" action="{{ route('cabinet.contacts.move') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert-info" id="moveContactAlert">
                            <strong>Contact with this number already exists in group book.</strong>
                            <p class="mb-0 mt-2">Please choose which name to use:</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Phone Number:</label>
                            <p class="mb-0" id="moveContactPhone"></p>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="radio" 
                                       name="name_choice" 
                                       id="nameChoiceGroup" 
                                       value="group" 
                                       checked>
                                <label class="form-check-label" for="nameChoiceGroup">
                                    <strong>Group Contact Name:</strong>
                                    <span id="groupContactName" class="d-block text-muted"></span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="radio" 
                                       name="name_choice" 
                                       id="nameChoicePersonal" 
                                       value="personal">
                                <label class="form-check-label" for="nameChoicePersonal">
                                    <strong>Personal Contact Name:</strong>
                                    <span id="personalContactName" class="d-block text-muted"></span>
                                </label>
                            </div>
                        </div>
                        
                        <input type="hidden" name="contact_id" id="moveContactId">
                        <input type="hidden" name="group_contact_id" id="moveGroupContactId">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Move Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function prepareMoveContact(contactId, contactName, phone1, phone2) {
            // Сохраняем данные контакта для использования в модалке
            document.getElementById('moveContactId').value = contactId;
            document.getElementById('personalContactName').textContent = contactName || '-';
            const phoneDisplay = phone1 || phone2 || '-';
            document.getElementById('moveContactPhone').textContent = phoneDisplay;
            
            // Устанавливаем action формы
            document.getElementById('moveContactForm').action = '{{ route('cabinet.contacts.move') }}';
            
            // Проверяем наличие дубликата
            fetch(`{{ route('cabinet.contacts.check-duplicate') }}?contact_id=${contactId}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.has_duplicate) {
                    // Показываем модалку с выбором имени
                    document.getElementById('moveGroupContactId').value = data.group_contact_id;
                    document.getElementById('groupContactName').textContent = data.group_contact_name || '-';
                    document.getElementById('nameChoiceGroup').checked = true;
                    document.getElementById('nameChoicePersonal').checked = false;
                    
                    // Показываем модалку
                    const modal = new bootstrap.Modal(document.getElementById('moveContactModal'));
                    modal.show();
                } else {
                    // Нет дубликата - сразу перемещаем
                    moveContactDirectly(contactId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error checking for duplicate contacts');
            });
        }

        function moveContactDirectly(contactId) {
            if (!confirm('Are you sure you want to move this contact to group book?')) {
                return;
            }

            const formData = new FormData();
            formData.append('_token', '{{ csrf_token() }}');
            formData.append('contact_id', contactId);
            
            fetch('{{ route('cabinet.contacts.move') }}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Перезагружаем страницу
                    window.location.reload();
                } else {
                    alert(data.message || 'Error moving contact');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error moving contact');
            });
        }

        // Обработка формы перемещения
        const moveContactForm = document.getElementById('moveContactForm');
        if (moveContactForm) {
            moveContactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Закрываем модалку и перезагружаем страницу
                        const modalElement = document.getElementById('moveContactModal');
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error moving contact');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error moving contact');
                });
            });
        }
    </script>
</body>
</html>
