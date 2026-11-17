import { describe, expect, it } from 'vitest'
import DateObject from 'react-date-object'
import persian from 'react-date-object/calendars/persian'
import persian_fa from 'react-date-object/locales/persian_fa'
import { toGregorianIsoDate } from './date-picker-jalali'

describe('toGregorianIsoDate', () => {
  it('emits Latin Gregorian digits even when the picker used persian_fa', () => {
    const picked = new DateObject({
      calendar: persian,
      locale: persian_fa,
      year: 1403,
      month: 8,
      day: 17,
    })
    const iso = toGregorianIsoDate(picked)
    expect(iso).toMatch(/^\d{4}-\d{2}-\d{2}$/)
    expect(iso).toBe('2024-11-07')
    expect(/[۰-۹]/.test(iso)).toBe(false)
  })
})
