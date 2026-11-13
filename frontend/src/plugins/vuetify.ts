/**
 * plugins/vuetify.ts
 *
 * Vuetify instance, theme palette and component defaults for the
 * Divine Community Space admin panel.
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
const divineCommunitySpaceLight = {
  dark: false,
  colors: {
    'background': '#F6F7FA',
    'surface': '#FFFFFF',
    'surface-bright': '#FFFFFF',
    'surface-light': '#FAFBFD',
    'surface-variant': '#E7ECF5',
    'on-surface-variant': '#5D6B80',
    'on-background': '#1B2A41',
    'on-surface': '#1B2A41',
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

const divineCommunitySpaceDark = {
  dark: true,
  colors: {
    'background': '#0F1623',
    'surface': '#172131',
    'surface-bright': '#1E2B42',
    'surface-light': '#1A2538',
    'surface-variant': '#28344A',
    'on-surface-variant': '#9AA8BD',
    'on-background': '#E7ECF5',
    'on-surface': '#E7ECF5',
    'primary': '#8FB0FF',
    'primary-darken-1': '#6E92EB',
    'primary-lighten-1': '#1F2D4A',
    'secondary': '#C5D0E4',
    'accent': '#D2BA84',
    'tertiary': '#96cdf9',
    'success': '#3DBA84',
    'warning': '#E8A043',
    'error': '#F06A77',
    'info': '#8FB0FF',
  },
  variables: {
    'border-color': '#28344A',
    'border-opacity': 1,
  },
}

// https://vuetifyjs.com/en/introduction/why-vuetify/#feature-guides
export default createVuetify({
  theme: {
    defaultTheme: 'divineCommunitySpaceLight',
    themes: {
      divineCommunitySpaceLight,
      divineCommunitySpaceDark,
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
