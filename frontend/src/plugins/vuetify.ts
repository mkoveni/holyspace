/**
 * plugins/vuetify.ts
 *
 * Vuetify instance, theme palette and component defaults for the
 * Holyspace admin panel.
 *
 * Framework documentation: https://vuetifyjs.com
 */

// Composables
import { createVuetify } from 'vuetify'
// Styles
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

// Church Management Portal brand palette, extended with tonal surfaces so the
// UI reads as a cohesive, designed product rather than default Material colors.
const holyspaceLight = {
  dark: false,
  colors: {
    'background': '#F3F5FA',
    'surface': '#FFFFFF',
    'surface-bright': '#FFFFFF',
    'surface-light': '#EEF2F9',
    'surface-variant': '#E7ECF5',
    'on-surface-variant': '#5B6479',
    'primary': '#2c4d94',
    'primary-darken-1': '#1D3669',
    'primary-lighten-1': '#96cdf9',
    'secondary': '#252426',
    'secondary-darken-1': '#141314',
    'accent': '#fce9b9',
    'tertiary': '#96cdf9',
    'success': '#1E8E5A',
    'warning': '#C9770A',
    'error': '#D23F4D',
    'info': '#2c4d94',
  },
  variables: {
    'border-color': '#E4E8F1',
    'border-opacity': 1,
    'high-emphasis-opacity': 0.92,
    'medium-emphasis-opacity': 0.64,
  },
}

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  theme: {
    defaultTheme: 'holyspaceLight',
    themes: {
      holyspaceLight,
    },
  },
  defaults: {
    global: {
      ripple: false,
    },
    VCard: {
      rounded: 'md',
      elevation: 0,
      border: true,
    },
    VBtn: {
      rounded: 'sm',
      style: 'letter-spacing: 0; font-weight: 600; text-transform: none;',
    },
    VTextField: { variant: 'outlined', density: 'comfortable', rounded: 'sm', color: 'primary' },
    VSelect: { variant: 'outlined', density: 'comfortable', rounded: 'sm', color: 'primary' },
    VAutocomplete: { variant: 'outlined', density: 'comfortable', rounded: 'sm', color: 'primary' },
    VTextarea: { variant: 'outlined', density: 'comfortable', rounded: 'sm', color: 'primary' },
    VCombobox: { variant: 'outlined', density: 'comfortable', rounded: 'sm', color: 'primary' },
    VChip: { rounded: 'sm' },
    VDialog: { rounded: 'md' },
    VAlert: { rounded: 'sm' },
    VTooltip: { location: 'top' },
    VDataTable: { rounded: 'md' },
    VAppBar: { elevation: 0 },
  },
})
