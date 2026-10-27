{{-- The signed-in account, read from the panel's own guard (admin or client). --}}
@php($u = filament()->auth()->user())
@if($u)
<span class="fi-admin-user"><span class="fi-admin-user-name">{{ $u->name }}</span><span class="fi-admin-user-role">{{ $u->role_label ?? 'Staff' }} · {{ $u->email }}</span></span>
@endif
