export type Recurrence = 'minute' | 'hourly' | 'daily' | 'weekdays' | 'weekly' | 'custom'

// Recognize only expressions we can reproduce exactly. Other schedules stay custom.
export function readSchedule(cron: string): { recurrence: Recurrence; time: string; day: string } {
  const fallback = { recurrence: 'custom' as const, time: '08:00', day: '1' }
  if (cron === '* * * * *') return { ...fallback, recurrence: 'minute' }
  const parts = cron.split(' ')
  if (parts.length !== 5 || parts[2] !== '*' || parts[3] !== '*') return fallback
  const [minute, hour, , , day] = parts
  if (!/^(0|[1-9]\d?)$/.test(minute) || Number(minute) > 59) return fallback
  if (hour === '*' && day === '*') return { ...fallback, recurrence: 'hourly', time: `08:${minute.padStart(2, '0')}` }
  if (!/^(0|[1-9]\d?)$/.test(hour) || Number(hour) > 23) return fallback
  const time = `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`
  if (day === '*') return { ...fallback, recurrence: 'daily', time }
  if (day === '1-5') return { ...fallback, recurrence: 'weekdays', time }
  if (/^[0-6]$/.test(day)) return { recurrence: 'weekly', time, day }
  return fallback
}

export function writeSchedule(recurrence: Exclude<Recurrence, 'custom'>, time: string, day: string): string {
  const [hour, minute] = time.split(':').map(Number)
  if (recurrence === 'minute') return '* * * * *'
  if (recurrence === 'hourly') return `${minute} * * * *`
  return `${minute} ${hour} * * ${recurrence === 'weekdays' ? '1-5' : recurrence === 'weekly' ? day : '*'}`
}
