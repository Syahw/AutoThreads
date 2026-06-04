/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
      },
      colors: {
        brand: {
          50: '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
          950: '#1e1b4b',
        },
        surface: {
          DEFAULT: '#ffffff',
          muted: '#f8fafc',
          sidebar: '#0f172a',
          'sidebar-hover': '#1e293b',
        },
      },
      boxShadow: {
        card: '0 1px 3px 0 rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.06)',
        'card-hover': '0 10px 40px -10px rgb(79 70 229 / 0.15), 0 4px 12px -4px rgb(15 23 42 / 0.08)',
        glow: '0 0 40px -8px rgb(99 102 241 / 0.35)',
      },
      backgroundImage: {
        'mesh': 'radial-gradient(at 40% 20%, rgb(99 102 241 / 0.08) 0px, transparent 50%), radial-gradient(at 80% 0%, rgb(139 92 246 / 0.06) 0px, transparent 50%), radial-gradient(at 0% 50%, rgb(59 130 246 / 0.05) 0px, transparent 50%)',
        'mesh-dark': 'radial-gradient(at 40% 20%, rgb(99 102 241 / 0.12) 0px, transparent 50%), radial-gradient(at 80% 0%, rgb(139 92 246 / 0.08) 0px, transparent 50%), radial-gradient(at 0% 80%, rgb(59 130 246 / 0.06) 0px, transparent 50%)',
        'sidebar-gradient': 'linear-gradient(180deg, #0f172a 0%, #020617 100%)',
        'brand-gradient': 'linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #a855f7 100%)',
      },
    },
  },
  plugins: [],
};
