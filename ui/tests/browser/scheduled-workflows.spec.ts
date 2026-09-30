import { test, expect } from '@playwright/test'

// The same authoring path works for unrelated Laravel host capabilities.
const scenarios = [
  { name: 'Follow up on open tickets', query: 'Find open tickets', action: 'Remind ticket owner', field: 'Reminder message', message: 'Please follow up on this open ticket.', records: ['Printer offline', 'Invoice question'], status: 'closed', output: 'Captured email', path: '/testing/inbox' },
  { name: 'Confirm bookings every morning', query: 'Find upcoming unconfirmed bookings', action: 'Call client with AI agent', field: 'Agent instructions', message: 'Confirm the booking with the client. Record their response; escalate changes to the team.', records: ['Alice appointment', 'Bob appointment'], status: 'confirmed', output: 'Captured call', path: '/testing/scenarios' },
]

for (const scenario of scenarios) {
  test(`${scenario.name}: author, publish, execute, and handle no matching records`, async ({ page }, testInfo) => {
    const errors: string[] = []
    page.on('pageerror', (error) => errors.push(error.message))

    await test.step('Author with 17 visible interactions; no graph setup, expressions, or canvas selectors', async () => {
      await page.goto('/')
      await page.getByRole('button', { name: 'New Workflow', exact: true }).click()
      await page.getByRole('dialog', { name: 'New Workflow' }).getByRole('textbox', { name: 'Name', exact: true }).fill(scenario.name)
      await page.getByRole('button', { name: 'Create', exact: true }).click()
      await page.getByRole('textbox', { name: 'Search triggers' }).fill('Schedule')
      await page.getByRole('button', { name: 'Schedule', exact: true }).click()
      await page.getByRole('combobox', { name: 'Repeat', exact: true }).selectOption('daily')
      await page.getByLabel('Run at', { exact: true }).fill('08:00')
      await page.getByRole('combobox', { name: 'Timezone', exact: true }).selectOption('Africa/Johannesburg')
      await page.screenshot({ path: testInfo.outputPath('schedule-settings.png'), fullPage: true })
      await page.getByRole('button', { name: 'Save and add next step', exact: true }).click()
      await page.getByRole('textbox', { name: 'Search actions' }).fill(scenario.query)
      await page.getByRole('button', { name: scenario.query, exact: true }).click()
      await page.getByRole('button', { name: 'Add next step', exact: true }).click()
      await page.getByRole('textbox', { name: 'Search actions' }).fill(scenario.action)
      await page.getByRole('button', { name: scenario.action, exact: true }).click()
      await page.getByRole('textbox', { name: scenario.field, exact: true }).fill(scenario.message)
      await page.getByRole('button', { name: 'Save settings', exact: true }).click()
      await page.getByRole('button', { name: 'Publish', exact: true }).click()
      await expect(page.getByText('Active', { exact: true })).toBeVisible()
      await page.screenshot({ path: testInfo.outputPath('scheduled-workflow.png'), fullPage: true })
    })
    const editorUrl = page.url()
    const workflowId = new URL(editorUrl).pathname.split('/').at(-1)

    await test.step('Verify native schedule persistence and replay its actual cron occurrence', async () => {
      await page.reload()
      await expect(page.getByText('Active', { exact: true })).toBeVisible()
      await page.goto('/testing/scenarios')
      const schedule = page.getByRole('article', { name: `Schedule aitumalow.workflow.${workflowId}`, exact: true })
      await expect(schedule).toContainText('0 8 * * *')
      await expect(schedule).toContainText('Africa/Johannesburg')
      await expect(schedule).toContainText('08:00')
      await schedule.getByRole('button', { name: 'Replay next unplayed occurrence', exact: true }).click()
      await expect.poll(async () => {
        await page.goto(scenario.path)
        return page.getByRole('article', { name: scenario.output, exact: true }).filter({ hasText: scenario.message }).count()
      }).toBe(2)
      const results = page.getByRole('article', { name: scenario.output, exact: true }).filter({ hasText: scenario.message })
      for (const record of scenario.records) await expect(results.filter({ hasText: record })).toHaveCount(1)
      await expect(results).not.toContainText(['Resolved ticket', 'Confirmed appointment'])
      await page.screenshot({ path: testInfo.outputPath('captured-results.png'), fullPage: true })
      await page.goto(editorUrl)
      await page.getByRole('button', { name: 'Run history', exact: true }).click()
      await expect(page.getByRole('complementary', { name: 'Run history' }).getByText('completed', { exact: true })).toHaveCount(1)
    })

    await test.step('After all matching records close, the next occurrence completes without side effects', async () => {
      await page.goto('/testing/scenarios')
      for (const recordName of scenario.records) {
        const record = page.getByRole('article', { name: recordName, exact: true })
        await record.getByLabel('Status', { exact: true }).selectOption(scenario.status)
        await record.getByRole('button', { name: 'Save record', exact: true }).click()
      }
      const schedule = page.getByRole('article', { name: `Schedule aitumalow.workflow.${workflowId}`, exact: true })
      await schedule.getByRole('button', { name: 'Replay next unplayed occurrence', exact: true }).click()
      await page.goto(editorUrl)
      await page.getByRole('button', { name: 'Run history', exact: true }).click()
      await expect(page.getByRole('complementary', { name: 'Run history' }).getByText('completed', { exact: true })).toHaveCount(2)
      await page.goto(scenario.path)
      await expect(page.getByRole('article', { name: scenario.output, exact: true }).filter({ hasText: scenario.message })).toHaveCount(2)
      expect(errors).toEqual([])
    })
  })
}
