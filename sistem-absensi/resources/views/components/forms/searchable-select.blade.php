@props(['name', 'label' => false, 'options', 'selected' => '', 'placeholder' => 'Semua', 'showPlaceholderOption' => true, 'required' => false, 'searchable' => true, 'notFoundText' => 'Data tidak ditemukan', 'searchPlaceholder' => 'Cari...'])

<div class="space-y-2 searchable-select-component" x-data="{
    open: false,
    search: '',
    selected: '{{ $selected }}',
    name: '{{ $name }}',
    options: {{ json_encode($options) }},
    placeholder: '{{ $placeholder }}',
    notFoundText: '{{ $notFoundText }}',
    searchPlaceholder: '{{ $searchPlaceholder }}',
    showPlaceholderOption: {{ $showPlaceholderOption ? 'true' : 'false' }},
    searchable: {{ $searchable ? 'true' : 'false' }},
    top: '0px',
    left: '0px',
    width: '0px',
    
    get selectedText() {
        if (this.selected === '' || this.selected === this.placeholder) return this.placeholder;
        const opt = this.options.find(o => o.value == this.selected);
        return opt ? opt.text : this.placeholder;
    },
    get filteredOptions() {
        if (this.search === '') return this.options;
        return this.options.filter(opt => opt.text.toLowerCase().includes(this.search.toLowerCase()));
    },
    selectOption(value) {
        this.selected = value;
        this.open = false;
        this.search = '';
    },
    selectFirst() {
        if (this.filteredOptions.length > 0) {
            this.selectOption(this.filteredOptions[0].value);
        } else if (this.search === '') {
            this.selectOption('');
        }
    },
    updatePosition() {
        if (!this.$refs.button) return;
        const rect = this.$refs.button.getBoundingClientRect();
        this.top = (rect.bottom + window.scrollY + 4) + 'px';
        this.left = (rect.left + window.scrollX) + 'px';
        this.width = rect.width + 'px';
    },
    init() {
        this.$watch('open', value => {
            if (value) {
                this.updatePosition();
                setTimeout(() => {
                    if (this.searchable && this.$refs.searchInput) this.$refs.searchInput.focus();
                }, 50);
            } else {
                this.search = '';
            }
        });
        window.addEventListener('resize', () => { if (this.open) this.updatePosition(); });
        window.addEventListener('scroll', () => { if (this.open) this.updatePosition(); }, true);
    }
}" @click.away="open = false" @reset-component="open = false; search = ''; selected = ''" @close-component="open = false">
    @if($label)
        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
            {{ str_replace(' *', '', $label) }} 
            @if($required || str_ends_with($label, '*'))
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif
    
    <div>
        <input type="hidden" name="{{ $name }}" :name="name" x-model="selected">
        
        <button type="button" x-ref="button" @click="open = !open" @keydown.escape.prevent="open = false"
            {{ $attributes->merge(['class' => 'flex w-full items-center justify-between rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 sm:px-4 sm:py-3 text-xs sm:text-sm text-slate-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary transition hover:bg-slate-50']) }}>
            <span x-text="selectedText" class="truncate" :class="{'text-slate-500': selected === '' || selected === placeholder}"></span>
            <svg class="h-4 w-4 sm:h-5 sm:w-5 text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <template x-teleport="body">
            <div x-show="open" x-cloak
                @click.away="open = false"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="transform opacity-0 scale-95"
                x-transition:enter-end="transform opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="transform opacity-100 scale-100"
                x-transition:leave-end="transform opacity-0 scale-95"
                :style="`top: ${top}; left: ${left}; width: ${width};`"
                class="absolute z-[9999] flex flex-col overflow-hidden rounded-2xl bg-white text-sm shadow-2xl ring-1 ring-slate-200 focus:outline-none">
                
                <template x-if="searchable">
                    <div class="bg-white px-3 py-3 z-10 border-b border-slate-100 shrink-0">
                        <input type="text" x-model="search" x-ref="searchInput" @keydown.enter.prevent="selectFirst()" @keydown.escape.prevent="open = false"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            :placeholder="searchPlaceholder">
                    </div>
                </template>
                
                <ul class="max-h-60 overflow-y-auto p-1.5 space-y-0.5">
                    <li x-show="showPlaceholderOption && search === ''" @click="selectOption('')" 
                        class="cursor-pointer select-none rounded-xl px-3 py-2.5 text-xs sm:text-sm text-slate-900 hover:bg-slate-100 transition"
                        :class="{'bg-primary/10 font-semibold text-primary': selected === '' || selected === placeholder}">
                        <span x-text="placeholder"></span>
                    </li>
                    <template x-for="option in filteredOptions" :key="option.value">
                        <li @click="selectOption(option.value)"
                            class="cursor-pointer select-none rounded-xl px-3 py-2.5 text-xs sm:text-sm text-slate-900 hover:bg-slate-100 transition"
                            :class="{'bg-primary/10 font-semibold text-primary': selected == option.value}">
                            <span x-text="option.text"></span>
                        </li>
                    </template>
                    <li x-show="filteredOptions.length === 0" class="px-3 py-2.5 text-xs sm:text-sm text-slate-500 text-center" x-text="notFoundText">
                    </li>
                </ul>
            </div>
        </template>
    </div>
</div>
