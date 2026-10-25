@props(['tableClass' => 'directory-data-table', 'enhanced' => false, 'label' => null])
<div class="seekers-table-wrap directory-table-wrap data-table-scroll" @if($label) role="region" aria-label="{{ $label }}" tabindex="0" @endif>
    <table {{ $attributes->class([$tableClass]) }} data-directory-table @if($enhanced) data-enhanced-table @endif>
        {{ $slot }}
    </table>
</div>
