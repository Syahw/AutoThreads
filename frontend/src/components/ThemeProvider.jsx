import { useEffect } from 'react';
import { applyTheme } from '../lib/theme';
import { useThemeStore } from '../stores/themeStore';

/**
 * Keeps <html> class in sync with theme store (survives re-renders and navigation).
 */
export default function ThemeProvider({ children }) {
  const mode = useThemeStore((s) => s.mode);

  useEffect(() => {
    useThemeStore.getState().init();
  }, []);

  useEffect(() => {
    applyTheme(mode);
  }, [mode]);

  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const onChange = () => {
      if (useThemeStore.getState().mode === 'system') {
        applyTheme('system');
      }
    };
    media.addEventListener('change', onChange);
    return () => media.removeEventListener('change', onChange);
  }, []);

  return children;
}
