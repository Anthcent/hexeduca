import { defineConfig } from '@playwright/test'

const isCI = ['1', 'true'].includes((process.env.CI || '').toLowerCase())
const externalBaseURL = process.env.PLAYWRIGHT_BASE_URL
const baseURL = externalBaseURL || 'http://localhost'

export default defineConfig({
    testDir: './tests/e2e',
    forbidOnly: isCI,
    failOnFlakyTests: isCI,
    retries: isCI ? 2 : 0,
    workers: isCI ? 1 : undefined,
    reporter: isCI
        ? [['line'], ['junit', { outputFile: 'test-results/junit.xml' }]]
        : 'line',
    use: {
        baseURL,
        trace: 'retain-on-failure',
    },
    ...(externalBaseURL
        ? {}
        : {
              webServer: {
                  command: 'docker compose up app',
                  url: baseURL,
                  reuseExistingServer: !isCI,
                  timeout: 120_000,
              },
          }),
})
