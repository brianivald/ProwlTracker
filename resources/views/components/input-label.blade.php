@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-xs uppercase tracking-wider text-pt-muted']) }}>
    {{ $value ?? $slot }}
</label>
