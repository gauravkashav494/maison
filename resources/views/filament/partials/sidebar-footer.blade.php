{{-- Hidden while the desktop sidebar is collapsed to icons: the text has no room there. --}}
<div class="fi-sidebar-footer" x-data x-show="$store.sidebar.isOpen" x-cloak>© {{ date('Y') }} {{ filament()->getBrandName() }}</div>
