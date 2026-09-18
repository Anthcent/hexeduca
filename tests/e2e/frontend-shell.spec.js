import { expect, test } from '@playwright/test'

test('base shell renders with Tailwind styles and no initial console errors', async ({ page }) => {
    const consoleErrors = []
    page.on('console', message => {
        if (message.type() === 'error') consoleErrors.push(message.text())
    })
    page.on('pageerror', error => consoleErrors.push(error.message))

    await page.goto('/')

    const main = page.getByRole('main')

    await expect(main).toBeVisible()
    await expect(main.getByText('Educativo foundation scaffold')).toBeVisible()
    await expect(main.locator('div').first()).toHaveCSS('background-color', 'rgb(255, 255, 255)')
    expect(consoleErrors).toEqual([])
})
