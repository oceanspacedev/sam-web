import preset from '../../../../vendor/filament/filament/tailwind.config.preset'
import tailwindScrollbar from 'tailwind-scrollbar'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    plugins: [tailwindScrollbar],
}
