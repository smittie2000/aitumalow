import assert from 'node:assert/strict'
import test from 'node:test'
import { readSchedule, writeSchedule } from '../src/lib/schedule.ts'

test('schedule controls preserve supported native cron expressions', () => {
  for (const cron of ['* * * * *', '15 * * * *', '0 8 * * *', '45 17 * * 1-5', '30 9 * * 0']) {
    const schedule = readSchedule(cron)
    assert.notEqual(schedule.recurrence, 'custom')
    if (schedule.recurrence !== 'custom') assert.equal(writeSchedule(schedule.recurrence, schedule.time, schedule.day), cron)
  }
})

test('complex or noncanonical schedules stay custom instead of being rewritten', () => {
  for (const cron of ['*/15 * * * *', '0 8 1 * *', '0 8 * * MON-FRI', '00 08 * * *', '0 25 * * *', '60 8 * * *', '0 8 * * 1,3', '']) {
    assert.equal(readSchedule(cron).recurrence, 'custom', cron)
  }
})
