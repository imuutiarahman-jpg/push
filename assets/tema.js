// Tema UPT AIK UMGO — palet mengikuti warna logo (teal tua + emas).
// Dimuat tepat setelah Tailwind CDN; seluruh class emerald/amber otomatis memakai warna logo.
tailwind.config = {
  theme: {
    extend: {
      colors: {
        // Teal logo (ganti emerald)
        emerald: {
          50: '#f0f7f8',
          100: '#dcebec',
          200: '#bcd8dc',
          300: '#8fbcc3',
          400: '#5d9aa5',
          500: '#387d89',
          600: '#146573',
          700: '#0f5461',
          800: '#0b3d4a',
          900: '#082e38',
          950: '#041e25'
        },
        // Emas logo (ganti amber)
        amber: {
          50: '#fbf8ec',
          100: '#f6eecb',
          200: '#eddf9c',
          300: '#e2cb6b',
          400: '#d4af37',
          500: '#c9a227',
          600: '#a4821f',
          700: '#83671e',
          800: '#6b541f',
          900: '#5c471e'
        }
      }
    }
  }
};
