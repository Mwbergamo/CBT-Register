@props(['label'])

<label {{ $attributes->merge(['class' => 'block']) }}>
    <span class="block text-xs font-medium text-slate-500 mb-1">{{ $label }}</span>
    {{ $slot }}
</label>
