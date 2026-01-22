<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#6AB023] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#5a9620] focus:bg-[#5a9620] active:bg-[#4a8610] focus:outline-none focus:ring-2 focus:ring-[#6AB023] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
