<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-3.5 py-2 bg-pt-red border border-transparent rounded text-xs font-medium text-white tracking-wide hover:bg-[#b82525] active:bg-[#9e1f1f] focus:outline-none focus:ring-1 focus:ring-pt-red disabled:opacity-50 transition-colors duration-150']) }}>
    {{ $slot }}
</button>
