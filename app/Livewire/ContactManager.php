<?php

namespace App\Livewire;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ContactManager extends Component
{
    use WithFileUploads, WithPagination;

    public string $search = '';
    public bool $showModal = false;

    // Modal form fields
    public ?int $editingId = null;
    public string $editPhone = '';
    public string $editName = '';
    public string $editAlias = '';
    public string $editNotes = '';
    public $editImage = null;
    public ?string $existingImage = null;

    protected $rules = [
        'editName'  => 'nullable|string|max:120',
        'editAlias' => 'nullable|string|max:80',
        'editNotes' => 'nullable|string|max:1000',
        'editImage' => 'nullable|image|max:2048',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(int $id): void
    {
        $contact = PhoneContact::findOrFail($id);
        $this->editingId    = $contact->id;
        $this->editPhone    = $contact->phone_number;
        $this->editName     = $contact->name ?? '';
        $this->editAlias    = $contact->alias ?? '';
        $this->editNotes    = $contact->notes ?? '';
        $this->editImage    = null;
        $this->existingImage = $contact->image_path;
        $this->showModal    = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['editingId', 'editPhone', 'editName', 'editAlias', 'editNotes', 'editImage', 'existingImage']);
    }

    public function save(): void
    {
        $this->validate();

        $contact = PhoneContact::findOrFail($this->editingId);

        $data = [
            'name'  => $this->editName ?: null,
            'alias' => $this->editAlias ?: null,
            'notes' => $this->editNotes ?: null,
        ];

        if ($this->editImage) {
            // Delete old image
            if ($contact->image_path && \Storage::disk('public')->exists($contact->image_path)) {
                \Storage::disk('public')->delete($contact->image_path);
            }
            $data['image_path'] = $this->editImage->store('contacts', 'public');
        }

        $contact->update($data);

        $this->closeModal();
        session()->flash('success', "Contacto {$contact->phone_number} actualizado.");
    }

    public function render()
    {
        $contacts = PhoneContact::query()
            ->when($this->search, function ($q) {
                $q->where('phone_number', 'like', "%{$this->search}%")
                  ->orWhere('name', 'like', "%{$this->search}%")
                  ->orWhere('alias', 'like', "%{$this->search}%");
            })
            ->orderByRaw("CASE WHEN name IS NOT NULL THEN 0 ELSE 1 END")
            ->orderBy('phone_number')
            ->paginate(20);

        // Attach call counts
        $numbers = $contacts->pluck('phone_number');
        $callCounts = CdrRecord::selectRaw('number_a as phone, COUNT(*) as cnt')
            ->whereIn('number_a', $numbers)
            ->groupBy('number_a')
            ->pluck('cnt', 'phone');

        return view('livewire.contact-manager', compact('contacts', 'callCounts'))
            ->extends('layouts.app')
            ->section('content');
    }
}
