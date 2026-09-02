<x-filament-panels::page>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-10 flex justify-end">
            <x-filament::button
                type="submit"
                icon="heroicon-m-check"
                class="px-6 py-3"
            >
                Save Changes
            </x-filament::button>
        </div>
    </form>

</x-filament-panels::page>
