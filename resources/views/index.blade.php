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
                        
                        <button type="submit" id="uploadBtn" class="btn btn-dark d-none" disabled style="white-space: nowrap;">
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
                    </div>
                    
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle upload form submission
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadBtnText = document.getElementById('uploadBtnText');
            const uploadBtnSpinner = document.getElementById('uploadBtnSpinner');

            // Check if file is selected
            const fileInput = document.getElementById('vcf_file');
            if (!fileInput.files || !fileInput.files[0]) {
                e.preventDefault();
                alert('Please select a file to upload');
                return;
            }

            // Disable button and show spinner
            uploadBtn.disabled = true;
            uploadBtnText.textContent = 'Uploading...';
            uploadBtnSpinner.classList.remove('d-none');
        });
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

            // Get current book_id from URL or use selectedBook
            const urlParams = new URLSearchParams(window.location.search);
            let currentBookId = urlParams.get('book_id');
            @if(isset($selectedBook) && $selectedBook)
                if (!currentBookId) {
                    currentBookId = '{{ $selectedBook->id }}';
                }
            @endif
            
            // Build URL with search and book_id parameters
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

                // Add search parameter to pagination URL
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
    </script>
</body>
</html>
