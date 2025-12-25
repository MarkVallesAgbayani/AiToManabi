/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // Japanese-inspired color palette
        sakura: {
          50: '#FFF5F7',
          100: '#FFE6EA',
          200: '#FFB3C0',
          300: '#FF8096',
          400: '#FF4D6C',
          500: '#FF1A42',
          600: '#CC1535',
          700: '#991028',
          800: '#660A1B',
          900: '#33050E',
        },
        matcha: {
          50: '#F4F7ED',
          100: '#E9EFDB',
          200: '#D3DFB7',
          300: '#BECF93',
          400: '#A8BF6F',
          500: '#92AF4B',
          600: '#758C3C',
          700: '#58692D',
          800: '#3A461E',
          900: '#1D230F',
        },
        indigo: {
          50: '#EEF2FF',
          100: '#E0E7FF',
          200: '#C7D2FE',
          300: '#A5B4FC',
          400: '#818CF8',
          500: '#6366F1',
          600: '#4F46E5',
          700: '#4338CA',
          800: '#3730A3',
          900: '#312E81',
        },
      },
      fontFamily: {
        japanese: ['Noto Sans JP', 'sans-serif'],
      },
    },
  },
  plugins: [],
} 