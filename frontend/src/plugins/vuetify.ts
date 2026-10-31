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
    'background': '#F5F6F8',
    'surface': '#FFFFFF',
    'surface-bright': '#FFFFFF',
    'surface-light': '#FAFBFD',
    'surface-variant': '#E7ECF5',
    'on-surface-variant': '#69798E',
    'on-background': '#26364C',
    'on-surface': '#26364C',
    'primary': '#2c4d94',
    'primary-darken-1': '#1D3669',
    'primary-lighten-1': '#EEF2FA',
    'secondary': '#26364C',
    'secondary-darken-1': '#141314',
    'accent': '#fce9b9',
    'tertiary': '#96cdf9',
    'success': '#1E8E5A',
    'warning': '#C9770A',
    'error': '#D23F4D',
    'info': '#2c4d94',
  },
  variables: {
    'border-color': '#E5E9EF',
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
      rounded: 'xl',
      elevation: 0,
      border: true,
    },
    VBtn: {
      rounded: 'lg',
      style: 'letter-spacing: 0; font-weight: 600; text-transform: none;',
    },
    VTextField: { variant: 'outlined', density: 'comfortable', rounded: 'lg', color: 'primary' },
    VSelect: { variant: 'outlined', density: 'comfortable', rounded: 'lg', color: 'primary' },
    VAutocomplete: { variant: 'outlined', density: 'comfortable', rounded: 'lg', color: 'primary' },
    VTextarea: { variant: 'outlined', density: 'comfortable', rounded: 'lg', color: 'primary' },
    VCombobox: { variant: 'outlined', density: 'comfortable', rounded: 'lg', color: 'primary' },
    VChip: { rounded: 'sm', variant: 'tonal' },
    VDialog: { rounded: 'xl' },
    VAlert: { rounded: 'lg' },
    VTooltip: { location: 'top' },
    VDataTable: { rounded: 'lg', density: 'comfortable' },
    VAppBar: { elevation: 0 },
  },
})
