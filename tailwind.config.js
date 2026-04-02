import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    DEFAULT: '#F68F1F',
                    50: '#fff6e5',
                    100: '#ffebc2',
                    200: '#ffd88e',
                    300: '#ffbe50',
                    400: '#ffa112',
                    500: '#F68F1F',
                    600: '#e57200',
                    700: '#bf5500',
                    800: '#994200',
                    900: '#7a3604',
                }
            }
        },
    },

    plugins: [forms, typography],
};
