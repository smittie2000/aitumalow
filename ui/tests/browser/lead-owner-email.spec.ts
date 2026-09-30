import { test, expect } from '@playwright/test'

test('an operator builds lead-status email automation entirely through the interface', async ({ page }, testInfo) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))

  await test.step('Create a workflow and choose the lead-status trigger', async () => {
    await page.goto('/')
    await page.getByRole('button', { name: 'New Workflow', exact: true }).click()
    await page.getByRole('dialog', { name: 'New Workflow' }).getByRole('textbox', { name: 'Name', exact: true }).fill('Email owner when lead status changes')
    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await page.getByRole('textbox', { name: 'Search triggers' }).fill('lead status')
    await page.getByRole('button', { name: /Lead status changed/ }).click()
  })
  const editorUrl = page.url()

  await test.step('Add email-owner action, configure the message, and publish', async () => {
    await page.getByRole('button', { name: 'Add next step', exact: true }).click()
    await page.getByRole('textbox', { name: 'Search actions' }).fill('email lead owner')
    await page.getByRole('button', { name: /Email lead owner/ }).click()
    await page.getByRole('textbox', { name: 'Email subject', exact: true }).fill('Lead status updated')
    await page.getByRole('textbox', { name: 'Message', exact: true }).fill('Please review your lead.')
    await page.getByRole('button', { name: 'Save settings', exact: true }).click()
    await page.getByRole('button', { name: 'Publish', exact: true }).click()
    await expect(page.getByText('Active', { exact: true })).toBeVisible()
    await page.screenshot({ path: testInfo.outputPath('published-workflow.png'), fullPage: true })
    await page.reload()
    await expect(page.getByText('Active', { exact: true })).toBeVisible()
  })

  await test.step('Changing Alice’s lead status sends email to Alice', async () => {
    await page.goto('/testing/leads')
    const alice = page.getByRole('article', { name: "Alice's lead" })
    await alice.getByLabel('Status', { exact: true }).selectOption('qualified')
    await alice.getByRole('button', { name: 'Save lead' }).click()
    await expect.poll(async () => {
      await page.goto('/testing/inbox')
      return page.getByRole('article', { name: 'Captured email', exact: true }).count()
    }).toBe(1)
    const email = page.getByRole('article', { name: 'Captured email', exact: true })
    await expect(email).toContainText('alice@example.test')
    await expect(email).toContainText('Lead status updated')
    await expect(email).toContainText('Please review your lead.')
    await expect(email).toContainText('new → qualified')
    await expect(email).not.toContainText('bob@example.test')
    await page.screenshot({ path: testInfo.outputPath('captured-email.png'), fullPage: true })
  })

  await test.step('A name-only change does not trigger email; another owner gets their own email', async () => {
    await page.goto('/testing/leads')
    const alice = page.getByRole('article', { name: "Alice's lead" })
    await alice.getByLabel('Lead name', { exact: true }).fill('Alice renamed lead')
    await alice.getByRole('button', { name: 'Save lead' }).click()
    const bob = page.getByRole('article', { name: "Bob's lead" })
    await bob.getByLabel('Status', { exact: true }).selectOption('won')
    await bob.getByRole('button', { name: 'Save lead' }).click()
    await expect.poll(async () => {
      await page.goto('/testing/inbox')
      return page.getByRole('article', { name: 'Captured email', exact: true }).count()
    }).toBe(2)
    await expect(page.getByRole('article', { name: 'Captured email', exact: true }).filter({ hasText: 'bob@example.test' })).toContainText('new → won')
  })

  await test.step('Inspect completed runs and disable the automation through the UI', async () => {
    await page.goto(editorUrl)
    await page.getByRole('button', { name: 'Run history', exact: true }).click()
    await expect(page.getByRole('complementary', { name: 'Run history', exact: true }).getByText('completed', { exact: true })).toHaveCount(2)
    await page.getByRole('button', { name: 'Deactivate', exact: true }).click()
    await page.goto('/testing/leads')
    const bob = page.getByRole('article', { name: "Bob's lead" })
    await bob.getByLabel('Status', { exact: true }).selectOption('qualified')
    await bob.getByRole('button', { name: 'Save lead' }).click()
    await page.goto('/testing/inbox')
    await expect(page.getByRole('article', { name: 'Captured email', exact: true })).toHaveCount(2)
    // Leave the UI-authored example active for manual inspection after the test.
    await page.goto(editorUrl)
    await page.getByRole('button', { name: 'Publish', exact: true }).click()
    await expect(page.getByText('Active', { exact: true })).toBeVisible()
    expect(errors).toEqual([])
  })
})

test('creation errors remain visible and an operator can correct the name and retry', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('button', { name: 'New Workflow', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'New Workflow' })
  await dialog.getByRole('textbox', { name: 'Name', exact: true }).fill('x'.repeat(256))
  await dialog.getByRole('button', { name: 'Create', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('255')
  await dialog.getByRole('textbox', { name: 'Name', exact: true }).fill('Corrected workflow')
  await dialog.getByRole('button', { name: 'Create', exact: true }).click()
  await expect(page.getByRole('button', { name: 'Add trigger', exact: true })).toBeVisible()
  await expect(dialog).not.toBeVisible()
})
