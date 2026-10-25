@props(['tag' => 'form'])
@if($tag === 'div')
<div {{ $attributes->class(['seekers-filter-form', 'directory-toolbar']) }}>{{ $slot }}</div>
@else
<form {{ $attributes->class(['seekers-filter-form', 'directory-toolbar']) }}>{{ $slot }}</form>
@endif
