import { useState } from 'react'
import type { ConfigSchemaField } from '../../../api/types'
import { readSchedule, writeSchedule, type Recurrence } from '../../../lib/schedule'

const inputClass = 'w-full rounded-md border border-gray-300 px-2.5 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100'
const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']

export function ScheduleField({ field, value, onChange }: { field: ConfigSchemaField; value: string; onChange: (value: string) => void }) {
  const schedule = readSchedule(value ?? '')
  const [custom, setCustom] = useState(schedule.recurrence === 'custom')
  const recurrence = custom ? 'custom' : schedule.recurrence
  return <div className="space-y-3">
    <label className="block text-xs text-gray-500">Repeat
      <select aria-label="Repeat" className={`${inputClass} mt-1`} disabled={field.readonly} value={recurrence} onChange={(event) => {
        const next = event.target.value as Recurrence
        setCustom(next === 'custom')
        if (next !== 'custom') onChange(writeSchedule(next, schedule.time, schedule.day))
      }}>
        <option value="minute">Every minute</option><option value="hourly">Every hour</option>
        <option value="daily">Every day</option><option value="weekdays">Weekdays</option>
        <option value="weekly">Every week</option><option value="custom">Custom cron expression</option>
      </select>
    </label>
    {recurrence === 'custom' ? <input aria-label={field.label} className={`${inputClass} font-mono`} disabled={field.readonly} value={value ?? ''} onChange={(event) => onChange(event.target.value)} placeholder="0 8 * * *" /> : <>
      {recurrence === 'weekly' && <label className="block text-xs text-gray-500">Day
        <select aria-label="Day of week" className={`${inputClass} mt-1`} disabled={field.readonly} value={schedule.day} onChange={(event) => onChange(writeSchedule('weekly', schedule.time, event.target.value))}>{days.map((day, index) => <option key={day} value={index}>{day}</option>)}</select>
      </label>}
      {recurrence !== 'minute' && <label className="block text-xs text-gray-500">{recurrence === 'hourly' ? 'Minute of the hour' : 'At'}
        {recurrence === 'hourly'
          ? <input aria-label="Minute of the hour" type="number" min={0} max={59} className={`${inputClass} mt-1`} disabled={field.readonly} value={Number(schedule.time.slice(3))} onChange={(event) => { const minute = Number(event.target.value); if (Number.isInteger(minute) && minute >= 0 && minute <= 59) onChange(writeSchedule('hourly', `08:${minute}`, schedule.day)) }} />
          : <input aria-label="Run at" type="time" className={`${inputClass} mt-1`} disabled={field.readonly} value={schedule.time} onChange={(event) => { if (event.target.value) onChange(writeSchedule(recurrence, event.target.value, schedule.day)) }} />}
      </label>}
      <p className="text-xs text-gray-500">{recurrence === 'minute' ? 'Runs every minute.' : recurrence === 'hourly' ? `Runs hourly at minute ${Number(schedule.time.slice(3))}.` : `Runs ${recurrence === 'daily' ? 'every day' : recurrence === 'weekdays' ? 'Monday to Friday' : `every ${days[Number(schedule.day)]}`} at ${schedule.time}.`} Times use the timezone below.</p>
    </>}
  </div>
}

export function TimezoneField({ field, value, onChange }: { field: ConfigSchemaField; value: string; onChange: (value: string) => void }) {
  const zones = [...new Set(['UTC', value, ...Intl.supportedValuesOf('timeZone')].filter(Boolean))].sort()
  return <select aria-label={field.label} className={inputClass} disabled={field.readonly} value={value ?? ''} onChange={(event) => onChange(event.target.value)}>
    {!value && <option value="">Choose timezone</option>}
    {zones.map((zone) => <option key={zone} value={zone}>{zone.replaceAll('_', ' ')}</option>)}
  </select>
}
