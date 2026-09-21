@extends('layouts.app', ['appBar' => ['title' => 'My bookings', 'back' => true]])

@section('content')
<x-account-shell title="My bookings">
    @if($bookings->isEmpty())
        <x-empty-state icon="calendar" title="No bookings yet" text="Book a plumber online and your requests will show up here."><a href="{{ route('booking.create') }}" class="btn btn-primary">Book a plumber</a></x-empty-state>
    @else
        <div class="space-y-2.5">@foreach($bookings as $b)<x-booking-row :booking="$b" />@endforeach</div>
    @endif
</x-account-shell>
@endsection