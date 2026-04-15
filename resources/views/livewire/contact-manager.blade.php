@section('title', 'Contactos')
@section('page-title', 'Gestión de Contactos')
@section('page-subtitle', 'Enriquece los números telefónicos con información de identidad')

<div class="space-y-4">

    @if(session('success'))
    <div class="flex items-center gap-2 px-4 py-3 rounded-lg text-sm text-green-300 border border-green-800" style="background-color:#14532d40;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    <!-- Toolbar -->
    <div class="flex items-center justify-between">
        <div class="relative w-72">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text"
                   wire:model.live.debounce.300ms="search"
                   placeholder="Buscar número, nombre o alias..."
                   class="w-full pl-8 pr-3 py-2 rounded-lg text-sm text-white border border-slate-600 focus:outline-none focus:border-blue-500"
                   style="background-color:#1e293b;">
        </div>
        <p class="text-xs text-slate-400">{{ $contacts->total() }} contactos únicos</p>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700 text-xs text-slate-400 uppercase tracking-wider">
                    <th class="text-left px-4 py-3">Imagen</th>
                    <th class="text-left px-4 py-3">Número</th>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Alias</th>
                    <th class="text-left px-4 py-3">Llamadas</th>
                    <th class="text-left px-4 py-3">Notas</th>
                    <th class="text-right px-4 py-3">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($contacts as $contact)
                <tr class="hover:bg-slate-700/30 transition-colors">
                    <td class="px-4 py-3">
                        @if($contact->image_path)
                            <img src="{{ asset('storage/'.$contact->image_path) }}" class="w-8 h-8 rounded-full object-cover">
                        @else
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs text-slate-500" style="background-color:#334155;">
                                <i class="fas fa-user"></i>
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-slate-200">{{ $contact->phone_number }}</td>
                    <td class="px-4 py-3 text-white">{{ $contact->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-yellow-400">{{ $contact->alias ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium" style="background-color:#3b82f620; color:#60a5fa;">
                            {{ $callCounts[$contact->phone_number] ?? 0 }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs max-w-xs truncate">{{ $contact->notes ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <button wire:click="openModal({{ $contact->id }})"
                                class="text-xs px-3 py-1 rounded-lg text-white transition-colors hover:opacity-90"
                                style="background-color:#3b82f6;">
                            <i class="fas fa-edit mr-1"></i> Editar
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                        <i class="fas fa-search text-3xl mb-2 block"></i>
                        Sin resultados
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div>{{ $contacts->links() }}</div>

    <!-- Edit Modal -->
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color:rgba(0,0,0,.7);">
        <div class="w-full max-w-md rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-white font-semibold">Editar Contacto</h3>
                <button wire:click="closeModal" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
            </div>

            <form wire:submit.prevent="save" class="space-y-4">
                <!-- Phone (read-only) -->
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Número</label>
                    <div class="px-3 py-2 rounded-lg text-sm font-mono text-slate-300 border border-slate-700" style="background-color:#0f172a;">
                        {{ $editPhone }}
                    </div>
                </div>

                <!-- Name -->
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Nombre</label>
                    <input type="text" wire:model="editName" placeholder="Nombre real..."
                           class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 focus:outline-none focus:border-blue-500"
                           style="background-color:#0f172a;">
                    @error('editName') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Alias -->
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Alias</label>
                    <input type="text" wire:model="editAlias" placeholder="Apodo, rol..."
                           class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 focus:outline-none focus:border-blue-500"
                           style="background-color:#0f172a;">
                </div>

                <!-- Notes -->
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Notas</label>
                    <textarea wire:model="editNotes" rows="3" placeholder="Observaciones forenses..."
                              class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 focus:outline-none focus:border-blue-500 resize-none"
                              style="background-color:#0f172a;"></textarea>
                </div>

                <!-- Image -->
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Imagen</label>
                    <div class="flex items-center gap-3">
                        @if($existingImage && !$editImage)
                        <img src="{{ asset('storage/'.$existingImage) }}" class="w-12 h-12 rounded-full object-cover">
                        @endif
                        @if($editImage)
                        <img src="{{ $editImage->temporaryUrl() }}" class="w-12 h-12 rounded-full object-cover border-2 border-blue-500">
                        @endif
                        <label class="cursor-pointer text-xs px-3 py-1.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 transition-colors">
                            <i class="fas fa-image mr-1"></i> {{ $existingImage ? 'Cambiar' : 'Subir' }} foto
                            <input type="file" wire:model="editImage" accept="image/*" class="hidden">
                        </label>
                        <div wire:loading wire:target="editImage" class="text-xs text-slate-400">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                    </div>
                    @error('editImage') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="button" wire:click="closeModal"
                            class="flex-1 text-sm py-2 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-700 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="flex-1 text-sm py-2 rounded-lg text-white font-medium transition-all hover:opacity-90"
                            style="background-color:#3b82f6;">
                        <span wire:loading.remove wire:target="save"><i class="fas fa-save mr-1"></i> Guardar</span>
                        <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin mr-1"></i> Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</div>
