/**
 * plugins/vuetify.ts
 *
 * Framework documentation: https://vuetifyjs.com`
 */

// Composables
import { createVuetify } from 'vuetify'
// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  theme: {
    defaultTheme: 'docuBrainDark',
    themes: {
      docuBrainDark: {
        dark: true,
        colors: {
          // === Superficies (60% + 30%) ===
          background: '#0D1219',        // fondo base — casi negro, con matiz azulado, NO negro puro
          surface: '#161D28',           // cards, paneles (Upload, Conversations, Chat)
          'surface-bright': '#1D2530',  // inputs, selects, elementos "un nivel más arriba"
          'surface-light': '#263140',   // bordes/dividers sutiles, hover states
          'surface-variant': '#1D2530',
          'on-surface-variant': '#9AA4B2',

          // === Acento principal (10%) ===
          primary: '#0EA5E9',           // azul-cian — botones, links, foco, marca
          'primary-darken-1': '#0284C7',
          'primary-lighten-1': '#38BDF8',

          secondary: '#64748B',         // gris azulado neutro para acciones secundarias
          'secondary-darken-1': '#475569',

          // === Colores semánticos ===
          success: '#22C55E',
          warning: '#F59E0B',
          error: '#EF4444',
          info: '#38BDF8',

          // === Texto ===
          'on-background': '#E6E8EB',
          'on-surface': '#E6E8EB',
          'on-primary': '#0D1219',       // texto oscuro sobre el botón cian (mejor contraste)
          'on-secondary': '#E6E8EB',
          'on-success': '#0D1219',
          'on-warning': '#0D1219',
          'on-error': '#0D1219',
          'on-info': '#0D1219',
        },
        variables: {
          'border-color': '#263140',
          'border-opacity': 1,
          'high-emphasis-opacity': 0.95,
          'medium-emphasis-opacity': 0.70,   // para texto secundario
          'disabled-opacity': 0.40,
          'idle-opacity': 0.10,
          'hover-opacity': 0.06,
          'focus-opacity': 0.14,
          'selected-opacity': 0.10,
          'activated-opacity': 0.14,
          'pressed-opacity': 0.16,
          'dragged-opacity': 0.10,
          'theme-kbd': '#161D28',
          'theme-on-kbd': '#E6E8EB',
          'theme-code': '#1D2530',
          'theme-on-code': '#E6E8EB',
        },
      },
    },
  },
})
