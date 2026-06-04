import { create } from 'zustand';
import { applyTheme, readStoredTheme, resolveTheme, writeStoredTheme } from '../lib/theme';

export const useThemeStore = create((set, get) => ({
  mode: readStoredTheme(),

  setMode: (mode) => {
    writeStoredTheme(mode);
    set({ mode });
    applyTheme(mode);
  },

  toggle: () => {
    const resolved = resolveTheme(get().mode);
    get().setMode(resolved === 'dark' ? 'light' : 'dark');
  },

  init: () => {
    const mode = readStoredTheme();
    set({ mode });
    applyTheme(mode);
  },
}));
