import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: [
                    '-apple-system',
                    'BlinkMacSystemFont',
                    '"Segoe UI"',
                    'Roboto',
                    '"Helvetica Neue"',
                    'Arial',
                    ...defaultTheme.fontFamily.sans,
                ],
                mono: [
                    'ui-monospace',
                    'SFMono-Regular',
                    'Consolas',
                    '"Liberation Mono"',
                    'Menlo',
                    ...defaultTheme.fontFamily.mono,
                ],
            },
            colors: {
                pt: {
                    carbon: '#141C2B',
                    graphite: '#222D40',
                    bg: '#F3F5F7',
                    surface: '#FFFFFF',
                    red: '#B24C4C',
                    green: '#3D8467',
                    amber: '#AD7A34',
                    muted: '#64748B',
                },
            },
        },
    },

    plugins: [forms],
};
