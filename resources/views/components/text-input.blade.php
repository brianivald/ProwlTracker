@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-slate-300 bg-white text-pt-carbon text-sm focus:border-pt-graphite focus:ring-1 focus:ring-pt-graphite rounded transition-colors']) }}>
