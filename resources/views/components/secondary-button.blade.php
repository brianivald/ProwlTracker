<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-3.5 py-2 bg-white border border-slate-300 rounded text-xs font-medium text-pt-carbon tracking-wide hover:bg-slate-50 focus:outline-none focus:ring-1 focus:ring-slate-400 disabled:opacity-50 transition-colors duration-150']) }}>
    {{ $slot }}
</button>
