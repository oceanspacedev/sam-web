import tailwindScrollbar from 'tailwind-scrollbar'

export default {
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    500: 'var(--color-primary-500)',
                },
            },
        },
    },
    plugins: [tailwindScrollbar],
}
