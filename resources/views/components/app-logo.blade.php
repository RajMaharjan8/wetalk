{{-- Site logo: the admin-uploaded image (settings key "landing_logo") when set,
     otherwise the "RG" fallback badge. Used in every header/footer brand spot.

     Props:
       size  — badge/image box size classes (default h-8 w-8)
       text  — badge text classes (default text-sm)
       badge — extra classes for the fallback badge background/ring (default
               the solid indigo badge; pass the white-on-blur variant for the
               landing hero nav). --}}
@props([
    'size' => 'h-8 w-8',
    'text' => 'text-sm',
    'badge' => 'bg-indigo-600 text-white',
])

@php($logo = \App\Support\LandingContent::imageUrl('landing_logo'))

@if ($logo)
    <img src="{{ $logo }}" alt="{{ \App\Support\LandingContent::siteName() }}" class="{{ $size }} rounded-md object-contain">
@else
    <span {{ $attributes->merge(['class' => "flex {$size} items-center justify-center rounded-md font-bold {$text} {$badge}"]) }}>RG</span>
@endif
