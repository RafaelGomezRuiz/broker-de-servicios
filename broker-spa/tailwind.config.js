import typography from '@tailwindcss/typography'

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,ts}'],
  theme: {
    extend: {
      colors: {
        // Paleta del portal: azul tinta institucional y un solo azul de acción.
        tinta: '#0F2A5C',
        accion: { DEFAULT: '#2251C5', oscuro: '#1A3F9C' },
        fondo: '#F5F7FA',
        linea: '#D6DCE6',
        texto: { DEFAULT: '#1B2333', suave: '#5A6578' },
        bien: '#1E7A4F',
        falla: '#B4232C',
        aviso: '#A6620A',
      },
      fontFamily: {
        sans: ['"IBM Plex Sans"', 'system-ui', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'ui-monospace', 'monospace'],
      },
      maxWidth: {
        lectura: '72ch',
      },
    },
  },
  plugins: [typography],
}
