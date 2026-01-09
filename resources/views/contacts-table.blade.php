<div data-total-contacts="{{ $contacts->total() }}">
@if($contacts->count() > 0)
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone Number</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($contacts as $contact)
                    <tr>
                        <td>{{ $contact->name ?? '-' }}</td>
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
                            @php
                                // User can only edit contacts from their default book
                                $canEdit = false;
                                $defaultBookIdValue = $defaultBookId ?? null;
                                
                                if ($contact->contact_book_id && $defaultBookIdValue !== null) {
                                    $canEdit = $contact->contact_book_id == $defaultBookIdValue;
                                } elseif (!$contact->contact_book_id && $defaultBookIdValue === null) {
                                    // Old contacts without book can only be edited if user has no default book
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
                                <span class="text-muted small" title="Editing is only available for contacts from your department">
                                    —
                                </span>
                            @endif
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

