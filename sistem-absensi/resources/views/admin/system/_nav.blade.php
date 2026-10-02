<div class="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-4 mb-6">
    <a href="{{ route('admin.system.categories') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('admin.system.categories') ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
        <i class="fa-solid fa-shapes"></i>
        Kategori Organisasi
    </a>

    <a href="{{ route('admin.system.features') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('admin.system.features') ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
        <i class="fa-solid fa-puzzle-piece"></i>
        Katalog Fitur
    </a>

    <a href="{{ route('admin.system.templates', 'admin.system.templates.*') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition {{ request()->routeIs('admin.system.templates*') ? 'bg-primary text-white shadow-sm' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50' }}">
        <i class="fa-solid fa-layer-group"></i>
        Template Kategori
    </a>

    <a href="{{ route('admin.organization.select') }}"
       class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 transition ml-auto">
        <i class="fa-solid fa-building-user"></i>
        Daftar Organisasi
    </a>
</div>
