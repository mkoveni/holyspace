import type { Page } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { expect, test } from '@playwright/test'
const passwordFile = process.env.CHILDREN_E2E_PASSWORD_FILE
test.use({ trace: 'off' })
async function choose (page: Page, label: string, name: string) {
  const input = page.getByLabel(label, { exact: true })
  await input.fill(name)
  await page.getByRole('option', { name, exact: true }).click()
}
async function select (page: Page, label: string, name: string) {
  await page.getByLabel(label, { exact: true }).press('ArrowDown')
  await page.getByRole('option', { name, exact: true }).click()
}
test('sets up a programme and completes family consent, session planning and a safe collection on Java', async ({
  page,
  request,
}) => {
  test.skip(
    !passwordFile,
    'Provide an isolated Java administrator password file.',
  )
  test.setTimeout(120_000)
  page.on('response', response => {
    if (response.status() >= 400) {
      console.info('HTTP', response.status(), new URL(response.url()).pathname)
    }
  })
  const response = await request.post('/api/login_check', {
    data: {
      username: 'children-admin',
      password: readFileSync(passwordFile!, 'utf8').trim(),
    },
  })
  expect(response.status()).toBe(200)
  const token = (await response.json()).token
  const headers = { Authorization: `Bearer ${token}` }
  expect((await request.get('/api/me', { headers })).status()).toBe(200)
  const suffix = Date.now().toString().slice(-7)
  async function person (firstName: string, dob: string) {
    const response = await request.post('/api/people', {
      headers,
      data: { person: { firstName, lastName: 'Mabasa', dateOfBirth: dob } },
    })
    expect(response.status()).toBe(201)
    return (await response.json()).id
  }
  const child = await person(`Lindiwe${suffix}`, '2018-04-12')
  await person(`Solly${suffix}`, '1980-02-12')
  const worker = await person(`Naledi${suffix}`, '1985-03-16')
  await page.addInitScript(
    value => localStorage.setItem('divinecommunityspace.java.auth.token', value),
    token,
  )
  await page.goto('/children')
  await page
    .getByRole('button', { name: 'Add programme', exact: true })
    .first()
    .click()
  await page
    .getByLabel('Programme name *', { exact: true })
    .fill(`Little Lights ${suffix}`)
  await page.getByLabel('Minimum staff present *', { exact: true }).fill('1')
  await page
    .getByLabel('Children per staff member *', { exact: true })
    .fill('6')
  await page
    .getByRole('button', { name: 'Save & continue', exact: true })
    .click()
  await page
    .getByRole('link', { name: `Open Little Lights ${suffix}`, exact: true })
    .click()
  await page.waitForURL('**/children/programmes/*')
  const programme = new URL(page.url()).pathname.split('/').at(-1)!
  await page.getByRole('button', { name: 'Add class', exact: true }).click()
  await page
    .getByLabel('Class name *', { exact: true })
    .fill(`Explorers ${suffix}`)
  await page.getByLabel('From age (months) *', { exact: true }).fill('0')
  await page.getByLabel('Until age (months) *', { exact: true }).fill('144')
  await page
    .getByRole('button', { name: 'Save & continue', exact: true })
    .click()
  await expect(
    page.getByText(`Explorers ${suffix}`, { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Publish consent policy', exact: true })
    .click()
  await page
    .getByLabel('Policy version name *', { exact: true })
    .fill(`Annual consent ${suffix}`)
  await page
    .getByLabel('Policy document link or reference *', { exact: true })
    .fill('https://example.org/annual-consent')
  await page.getByLabel('Policy document *', { exact: true }).setInputFiles({
    name: 'family-consent.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from(
      'Family consent for ministry activities and safe collection.',
    ),
  })
  await page
    .getByRole('button', { name: 'Publish policy', exact: true })
    .click()
  await expect(
    page.getByRole('heading', {
      name: `Annual consent ${suffix}`,
      exact: true,
    }),
  ).toBeVisible()
  await page.goto(`/children/participants/${child}?programme=${programme}`)
  await page
    .getByRole('button', { name: 'Enrol in programme', exact: true })
    .click()
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Welcome to a programme', exact: true }),
  ).toHaveCount(0)
  await page
    .getByRole('button', { name: 'Place in class', exact: true })
    .click()
  await select(page, 'Class *', `Explorers ${suffix}`)
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Find their class', exact: true }),
  ).toHaveCount(0)
  await page
    .getByRole('button', { name: 'Verify guardian', exact: true })
    .click()
  await choose(page, 'Guardian *', `Solly${suffix} Mabasa`)
  await page
    .getByLabel('Evidence link or reference *', { exact: true })
    .fill('Verified guardian evidence')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Verify a guardian', exact: true }),
  ).toHaveCount(0)
  await page
    .getByRole('button', { name: 'Record consent', exact: true })
    .click()
  await expect(
    page.getByText(`Annual consent ${suffix}`, { exact: true }),
  ).toBeVisible()
  await page
    .getByLabel('Evidence link or reference *', { exact: true })
    .fill('Signed family consent')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Record family consent', exact: true }),
  ).toHaveCount(0)
  await page
    .getByRole('button', { name: 'Authorize collector', exact: true })
    .click()
  await choose(page, 'Authorized collector *', `Solly${suffix} Mabasa`)
  await page
    .getByLabel('Evidence link or reference *', { exact: true })
    .fill('Signed collection permission')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'Authorize a collector', exact: true }),
  ).toHaveCount(0)
  await page.screenshot({
    path: 'test-results/children-real-participant.png',
    fullPage: true,
    animations: 'disabled',
  })
  // Set up the location and clearance using the Java contract; the session itself is planned through the screen.
  async function create (path: string, data: unknown) {
    const response = await request.post('/api/children' + path, {
      headers,
      data,
    })
    expect(response.status()).toBe(201)
    return (await response.json()).id
  }
  const location = await create('/locations', {
    name: `Campus ${suffix}`,
    timeZone: 'Africa/Johannesburg',
  })
  await create('/rooms', {
    locationId: location,
    name: `Garden ${suffix}`,
    capacity: 20,
  })
  const accountResponse = await request.post('/api/users', {
    headers,
    data: {
      username: `team${suffix}`,
      email: `team${suffix}@example.org`,
      displayName: `Team ${suffix}`,
      password: readFileSync(passwordFile!, 'utf8').trim(),
    },
  })
  expect(accountResponse.status()).toBe(201)
  const workerAccount = (await accountResponse.json()).id
  const until = new Date(Date.now() + 30 * 86_400_000).toISOString()
  expect(
    (
      await request.post('/api/children/workers', {
        headers,
        data: {
          personId: worker,
          accountId: workerAccount,
          screenedUntil: until,
          trainedUntil: until,
          evidenceReference: 'Verified training and screening',
        },
      })
    ).status(),
  ).toBe(204)
  await page.goto(`/children/sessions?programme=${programme}`)
  await page
    .getByRole('button', { name: 'Plan session', exact: true })
    .first()
    .click()
  // The class and room are carried through automatically for this first programme/location.
  await expect(
    page.getByRole('dialog').getByText(`Explorers ${suffix}`, { exact: true }),
  ).toBeVisible()
  await select(page, 'Location *', `Campus ${suffix}`)
  await expect(page.getByRole('dialog').getByText(`Garden ${suffix}`, { exact: true })).toBeVisible()
  await page
    .getByRole('button', { name: 'Create session', exact: true })
    .click()
  await expect(
    page.getByRole('heading', { name: 'Plan a little belonging', exact: true }),
  ).toHaveCount(0)
  await page
    .locator('article')
    .filter({
      has: page.getByRole('heading', {
        name: `Explorers ${suffix}`,
        exact: true,
      }),
    })
    .getByRole('link', { name: 'Open session', exact: true })
    .click()
  await page.waitForURL('**/children/sessions/*')
  const session = new URL(page.url()).pathname.split('/').at(-1)!
  await page.getByRole('button', { name: 'Assign staff', exact: true }).click()
  await choose(page, 'Team member *', `Naledi${suffix} Mabasa`)
  await select(page, 'Role *', 'Leader')
  await page.getByLabel('This team member is present', { exact: true }).check()
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'A team prepared to care', exact: true }),
  ).toHaveCount(0)
  await page
    .getByRole('button', { name: 'Session status', exact: true })
    .click()
  await page.getByLabel('Reason *', { exact: true }).fill('Team and room ready')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', {
      name: 'Set the tone for this session',
      exact: true,
    }),
  ).toHaveCount(0)
  const row = page
    .getByRole('row')
    .filter({ hasText: `Lindiwe${suffix} Mabasa` })
  await row.getByRole('button', { name: 'Check in', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Issue a pickup label', exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Create label', exact: true }).click()
  const code = (await page
    .locator('.children-label code')
    .textContent())!.trim()
  expect(code).toHaveLength(22)
  await page.getByRole('button', { name: 'Done', exact: true }).click()
  await row.getByRole('button', { name: 'Collect', exact: true }).click()
  await select(page, 'Authorized collector *', `Solly${suffix} Mabasa`)
  await page.getByLabel('Pickup code *', { exact: true }).fill(code)
  await page
    .getByLabel('I have checked this collector’s identity in person')
    .check()
  await page
    .getByRole('button', { name: 'Confirm collection', exact: true })
    .click()
  await expect(
    page.getByRole('heading', { name: 'A safe handover', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByText('Ready for the first hello', { exact: true }),
  ).toBeVisible()
  const roster = await (
    await request.get(
      `/api/children/station/sessions/${session}/attendance?openOnly=false`,
      { headers },
    )
  ).json()
  expect(roster[0].attendance.checkedOutAt).toBeTruthy()
  expect(roster[0].attendance.identityVerified).toBe(true)
})

test('creates welcoming spaces and records team clearance through the forms', async ({
  page,
  request,
}) => {
  test.skip(
    !passwordFile,
    'Provide an isolated Java administrator password file.',
  )
  const password = readFileSync(passwordFile!, 'utf8').trim()
  const login = await request.post('/api/login_check', {
    data: { username: 'children-admin', password },
  })
  expect(login.status()).toBe(200)
  const token = (await login.json()).token
  const headers = { Authorization: `Bearer ${token}` }
  const suffix = Date.now().toString().slice(-7)
  const person = await request.post('/api/people', {
    headers,
    data: {
      person: {
        firstName: `Care${suffix}`,
        lastName: 'Mokoena',
        dateOfBirth: '1984-05-12',
      },
    },
  })
  expect(person.status()).toBe(201)
  const account = await request.post('/api/users', {
    headers,
    data: {
      username: `care${suffix}`,
      email: `care${suffix}@example.org`,
      displayName: `Care ${suffix}`,
      password,
    },
  })
  expect(account.status()).toBe(201)
  await page.addInitScript(
    value => localStorage.setItem('divinecommunityspace.java.auth.token', value),
    token,
  )
  await page.goto('/children/settings')
  await page.getByRole('button', { name: 'Add location', exact: true }).click()
  await page
    .getByLabel('Location name *', { exact: true })
    .fill(`East campus ${suffix}`)
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', { name: 'A welcoming location', exact: true }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'Add room', exact: true }).click()
  await select(page, 'Location *', `East campus ${suffix}`)
  await page
    .getByLabel('Room name *', { exact: true })
    .fill(`Little Garden ${suffix}`)
  await page.getByLabel('Room capacity *', { exact: true }).fill('18')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('heading', {
      name: 'Make room for little people',
      exact: true,
    }),
  ).toHaveCount(0)
  await select(page, 'Location', `East campus ${suffix}`)
  await expect(
    page.getByText(`Little Garden ${suffix}`, { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Manage team clearance', exact: true })
    .click()
  await choose(page, 'Team member *', `Care${suffix} Mokoena`)
  await page
    .getByLabel('Linked user account *', { exact: true })
    .fill(`care${suffix}`)
  await page
    .getByRole('option', {
      name: `Care ${suffix} · care${suffix}`,
      exact: true,
    })
    .click()
  await page
    .getByLabel('Assessment evidence reference *', { exact: true })
    .fill('Verified assessment and training')
  await page.getByRole('button', { name: 'Save changes', exact: true }).click()
  await expect(
    page.getByRole('dialog').getByRole('heading', { name: 'A trusted team', exact: true }),
  ).toHaveCount(0)
  const workerId = (await person.json()).id
  const worker = await request.get(`/api/children/workers/${workerId}`, {
    headers,
  })
  expect(worker.status()).toBe(200)
  expect((await worker.json()).evidenceReference).toBe(
    'Verified assessment and training',
  )
  await page.screenshot({
    path: 'test-results/children-real-spaces.png',
    fullPage: true,
    animations: 'disabled',
  })
})
