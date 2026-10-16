import { describe, expect, it } from 'vitest'
import {
  isPageBuilderFullscreenPath,
  isPageBuilderNewTabPath,
  normalizeBuilderHref,
} from './page-builder-nav'

describe('page-builder-nav', () => {
  it('normalizes locale and bare builder paths', () => {
    expect(normalizeBuilderHref('/fa/dashboard/builder')).toBe('/dashboard/builder')
    expect(normalizeBuilderHref('builder/12')).toBe('/dashboard/builder/12')
    expect(normalizeBuilderHref('/theme-builder/header/3')).toBe('/dashboard/theme-builder/header/3')
  })

  it('marks صفحه‌ساز entry and editors for new tab', () => {
    expect(isPageBuilderNewTabPath('/dashboard/builder')).toBe(true)
    expect(isPageBuilderNewTabPath('/dashboard/builder/site')).toBe(true)
    expect(isPageBuilderNewTabPath('/dashboard/builder/new')).toBe(true)
    expect(isPageBuilderNewTabPath('/dashboard/builder/42')).toBe(true)
    expect(isPageBuilderNewTabPath('/dashboard/builder/chrome/header')).toBe(true)
    expect(isPageBuilderNewTabPath('/dashboard/theme-builder/header/9')).toBe(true)
    expect(isPageBuilderNewTabPath('/dashboard/builder/settings')).toBe(false)
    expect(isPageBuilderNewTabPath('/dashboard/builder/collections')).toBe(false)
    expect(isPageBuilderNewTabPath('/dashboard/theme-builder')).toBe(false)
    expect(isPageBuilderNewTabPath('/dashboard/marketing/pages')).toBe(false)
  })

  it('fullscreen only for editor surfaces', () => {
    expect(isPageBuilderFullscreenPath('/dashboard/builder')).toBe(false)
    expect(isPageBuilderFullscreenPath('/dashboard/builder/42')).toBe(true)
    expect(isPageBuilderFullscreenPath('/dashboard/builder/new')).toBe(true)
    expect(isPageBuilderFullscreenPath('/dashboard/builder/chrome/footer')).toBe(true)
    expect(isPageBuilderFullscreenPath('/dashboard/theme-builder/footer/2')).toBe(true)
  })
})
