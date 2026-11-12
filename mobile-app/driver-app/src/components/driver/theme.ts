/**
 * FLeetX Driver visual tokens — product family with Client, execution-first layout.
 * Presentation only. Do not encode business rules here.
 */

export const DriverTheme = {
  colors: {
    primary: '#1E75FF',
    primaryDark: '#1558C7',
    navy: '#080B16',
    navyElevated: '#0E1320',
    background: '#080B16',
    surface: '#0E1320',
    surfaceElevated: '#111A2E',
    textPrimary: '#F8FAFC',
    textSecondary: '#94A3B8',
    textMuted: '#64748B',
    textOnNavy: '#FFFFFF',
    textOnPrimary: '#FFFFFF',
    border: 'rgba(255, 255, 255, 0.1)',
    success: '#10B981',
    successSoft: 'rgba(16, 185, 129, 0.15)',
    warning: '#F59E0B',
    warningSoft: 'rgba(245, 158, 11, 0.15)',
    danger: '#EF4444',
    dangerSoft: 'rgba(239, 68, 68, 0.15)',
    info: '#1E75FF',
    infoSoft: 'rgba(30, 117, 255, 0.15)',
    overlay: 'rgba(8, 11, 22, 0.75)',
  },
  spacing: {
    xs: 6,
    sm: 10,
    md: 16,
    lg: 20,
    xl: 24,
    xxl: 36,
  },
  radius: {
    small: 8,
    medium: 12,
    large: 16,
    pill: 999,
  },
  typography: {
    screenTitle: { fontSize: 22, fontWeight: '700' as const, lineHeight: 28 },
    sectionTitle: { fontSize: 13, fontWeight: '700' as const, lineHeight: 18, letterSpacing: 0.6 },
    cardTitle: { fontSize: 18, fontWeight: '700' as const, lineHeight: 24 },
    body: { fontSize: 15, fontWeight: '400' as const, lineHeight: 22 },
    bodyMedium: { fontSize: 15, fontWeight: '600' as const, lineHeight: 22 },
    caption: { fontSize: 13, fontWeight: '500' as const, lineHeight: 18 },
    button: { fontSize: 16, fontWeight: '700' as const, lineHeight: 22 },
    metric: { fontSize: 20, fontWeight: '700' as const, lineHeight: 26 },
  },
  control: {
    buttonHeight: 52,
    iconButton: 44,
    tabBarHeight: 64,
  },
  elevation: {
    card: {
      shadowColor: '#0F172A',
      shadowOffset: { width: 0, height: 4 },
      shadowOpacity: 0.06,
      shadowRadius: 12,
      elevation: 3,
    },
    header: {
      shadowColor: '#080B16',
      shadowOffset: { width: 0, height: 2 },
      shadowOpacity: 0.18,
      shadowRadius: 8,
      elevation: 4,
    },
  },
} as const;

export type DriverThemeType = typeof DriverTheme;
