<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Contact</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4" style="max-width: 600px;">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3 pb-2 border-bottom fw-bold">Edit Contact</h5>
                
                <div class="d-flex gap-3 mb-3">
                    <div class="alert alert-light border-start border-primary border-3 flex-fill mb-0">
                        <small class="text-muted d-block mb-1">Contact uploaded by:</small>
                        <strong>{{ $contact->user->name ?? 'Unknown' }}</strong>
                        @if($contact->user->email)
                            <br><small class="text-muted">{{ $contact->user->email }}</small>
                        @endif
                        <br><small class="text-muted">{{ $contact->created_at->format('d.m.Y H:i') }}</small>
                    </div>
                    
                    <div class="alert alert-light border-start border-success border-3 flex-fill mb-0">
                        <small class="text-muted d-block mb-1">Last edited by:</small>
                        @if($contact->updatedBy)
                            <strong>{{ $contact->updatedBy->name ?? 'Unknown' }}</strong>
                            @if($contact->updatedBy->email)
                                <br><small class="text-muted">{{ $contact->updatedBy->email }}</small>
                            @endif
                            <br><small class="text-muted">{{ $contact->updated_at->format('d.m.Y H:i') }}</small>
                        @else
                            <strong>{{ $contact->user->name ?? 'Unknown' }}</strong>
                            @if($contact->user->email)
                                <br><small class="text-muted">{{ $contact->user->email }}</small>
                            @endif
                            <br><small class="text-muted">{{ $contact->created_at->format('d.m.Y H:i') }}</small>
                        @endif
                    </div>
                </div>
                
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('cabinet.contacts.update', $contact) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Name</label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $contact->name) }}"
                               placeholder="Enter name">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone1" class="form-label fw-semibold">Phone Number 1</label>
                        <input type="text" 
                               class="form-control @error('phone1') is-invalid @enderror" 
                               id="phone1" 
                               name="phone1" 
                               value="{{ old('phone1', $contact->phone1) }}"
                               placeholder="+994XXXXXXXXX">
                        @error('phone1')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="phone2" class="form-label fw-semibold">Phone Number 2</label>
                        <input type="text" 
                               class="form-control @error('phone2') is-invalid @enderror" 
                               id="phone2" 
                               name="phone2" 
                               value="{{ old('phone2', $contact->phone2) }}"
                               placeholder="+994XXXXXXXXX">
                        @error('phone2')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('cabinet.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dark">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Apply mask to phone fields: +994XXXXXXXXX (where X is a digit)
            // Mask format: +994 and 9 digits without spaces
            const phoneMaskOptions = {
                mask: '+994999999999',
                placeholder: '+994XXXXXXXXX',
                showMaskOnHover: false,
                showMaskOnFocus: true,
                clearMaskOnLostFocus: false,
                removeMaskOnSubmit: true,
                greedy: false,
                // Define custom character for digits
                definitions: {
                    '9': {
                        validator: '[0-9]',
                        cardinality: 1
                    }
                }
            };
            
            // Phone Number 1
            const phone1Input = document.getElementById('phone1');
            if (phone1Input) {
                Inputmask(phoneMaskOptions).mask(phone1Input);
            }
            
            // Phone Number 2
            const phone2Input = document.getElementById('phone2');
            if (phone2Input) {
                Inputmask(phoneMaskOptions).mask(phone2Input);
            }
        });
    </script>
</body>
</html>

