@extends('layouts.app')

{{--
    Bulk order: add many products to the cart in one pass instead of one at a time.
    The form lives in partials/bulk-form (also on the homepage); behaviour lives in
    partials/bulk-order-script, shared by every template.
--}}

@section('content')
<x-page-head title="Bulk order" text="Search by name or code, set a quantity for each line, and add them all to your cart together." :breadcrumbs="['Bulk order' => null]" />

<section class="p-container py-4 lg:py-6">
    @include('partials.bulk-form')
</section>

@include('partials.bulk-order-script')
@endsection
