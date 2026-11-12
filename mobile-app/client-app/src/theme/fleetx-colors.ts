/**
 * FleetX System Color Palette
 * Defines colors for light and dark color schemes, customized to match
 * the premium visual identity of FleetX dashboard.
 */
export const Colors = {
  light: {
    text: '#0F172A',
    background: '#F8FAFC',
    backgroundElement: '#E2E8F0',
    backgroundSelected: '#CBD5E1',
    textSecondary: '#64748B',
    primary: '#1E75FF',
    glow: '#5C5CFF',
    border: '#CBD5E1',
    inputBg: '#F1F5F9',
  },
  dark: {
    text: '#FFFFFF',
    background: '#080B16', // Deep navy black backdrop
    backgroundElement: '#111A2E', // Slate card container background
    backgroundSelected: '#1E293B', // Selected state background
    textSecondary: '#94A3B8', // Slate muted secondary text
    primary: '#1E75FF', // Vibrant accent blue
    glow: '#5C5CFF', // Purple/blue glow framing accent
    border: '#1E293B', // Slate border color
    inputBg: '#131C2E', // Deep container background for text fields
  },
} as const;

export type ThemeColor = keyof typeof Colors.light & keyof typeof Colors.dark;
