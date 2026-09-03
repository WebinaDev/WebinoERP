'use client';

import { useEffect } from 'react';

interface ThemeConfig {
  theme: {
    primary: string;
    radius: string;
  };
  branding: {
    name: string;
    logo: string | null;
  };
}

export function ThemeProvider({ config }: { config: ThemeConfig }) {
  useEffect(() => {
    if (config) {
      // Convert hex to HSL for CSS variables
      const primary = hexToHsl(config.theme.primary);
      
      document.documentElement.style.setProperty('--primary', primary);
      document.documentElement.style.setProperty('--radius', config.theme.radius);
    }
  }, [config]);

  return null;
}

function hexToHsl(hex: string): string {
  const r = parseInt(hex.slice(1, 3), 16) / 255;
  const g = parseInt(hex.slice(3, 5), 16) / 255;
  const b = parseInt(hex.slice(5, 7), 16) / 255;

  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h: number, s: number, l = (max + min) / 2;

  if (max === min) {
    h = s = 0;
  } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
      case g: h = ((b - r) / d + 2) / 6; break;
      case b: h = ((r - g) / d + 4) / 6; break;
      default: h = 0;
    }
  }

  return `${Math.round(h * 360)} ${Math.round(s * 100)}% ${Math.round(l * 100)}%`;
}

