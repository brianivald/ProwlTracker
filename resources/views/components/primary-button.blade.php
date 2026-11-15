<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-3.5 py-2 bg-pt-carbon border border-transparent rounded text-xs font-medium text-white tracking-wide hover:bg-pt-graphite focus:bg-pt-graphite active:bg-pt-carbon focus:outline-none focus:ring-1 focus:ring-pt-graphite disabled:opacity-50 transition-colors duration-150']) }}>
    {{ $slot }}
</button>
