import type { Page } from '@playwright/test'
export async function ministryFixture (
  page: Page,
  permissions = [
    'children.read',
    'children.manage',
    'children.safeguarding',
    'children.station',
    'children.supervise',
    'children.support',
    'people.read',
    'people.manage',
    'identity.manage',
  ],
) {
  const now = Date.now()
  const programmes = [
    {
      id: 'programme-1',
      name: 'Little Lights',
      currentPolicyId: 'policy-1',
      minStaff: 2,
      childrenPerStaff: 6,
      version: 1,
      createdAt: new Date(now).toISOString(),
    },
  ]
  const group = {
    id: 'class-1',
    programmeId: 'programme-1',
    name: 'Sunday Explorers',
    minAgeMonths: 60,
    maxAgeMonths: 144,
  }
  const room = {
    id: 'room-1',
    locationId: 'location-1',
    name: 'The Garden Room',
    capacity: 24,
  }
  const session = {
    session: {
      id: 'session-1',
      classId: group.id,
      roomId: room.id,
      startsAt: new Date(now - 300_000).toISOString(),
      endsAt: new Date(now + 3_600_000).toISOString(),
      seatLimit: 20,
      status: 'OPEN',
      version: 2,
    },
    className: group.name,
    roomName: room.name,
    timeZone: 'Africa/Johannesburg',
    effectiveCapacity: 12,
    occupancy: 0,
    staffingNeedsReview: false,
    staff: [
      {
        personId: 'worker-1',
        name: 'Naledi Mokoena',
        role: 'LEADER',
        present: true,
        issues: [],
      },
      {
        personId: 'worker-2',
        name: 'Thabo Dlamini',
        role: 'ASSISTANT',
        present: true,
        issues: [],
      },
    ],
  }
  const expected = [
    {
      childId: 'child-1',
      name: 'Lindiwe Mabasa',
      enrolmentId: 'enrolment-1',
      assignmentId: 'assignment-1',
    },
    {
      childId: 'child-2',
      name: 'Ethan Nkosi',
      enrolmentId: 'enrolment-2',
      assignmentId: 'assignment-2',
    },
  ]
  const attendance: Record<string, any>[] = []
  const requests: { method: string, path: string, body: any }[] = []
  const errors: string[] = []
  page.on('pageerror', error => errors.push(error.message))
  await page.addInitScript(() => {
    if (window.top !== window) return
    localStorage.setItem(
      'holyspace.java.auth.token',
      'children-ui-test-session',
    )
  })
  function dayResponse (query: URLSearchParams) {
    const mode = query.get('view') || 'EXPECTED'
    const raw
      = mode === 'EXPECTED'
        ? expected.map(child => ({
            childId: child.childId,
            name: child.name,
            attendance:
              attendance.find(
                value =>
                  value.childId === child.childId && !value.checkedOutAt,
              ) || null,
            ready: true,
            issues: [],
          }))
        : attendance
            .filter(value =>
              mode === 'HISTORY' ? !!value.checkedOutAt : !value.checkedOutAt,
            )
            .map(value => ({
              childId: value.childId,
              name:
                expected.find(child => child.childId === value.childId)
                  ?.name || 'Child',
              attendance: value,
              ready: true,
              issues:
                value.checkedOutAt || value.labelIssued
                  ? []
                  : ['Issue a pickup label before collection.'],
            }))
    const filtered = raw.filter(child =>
      child.name
        .toLowerCase()
        .includes((query.get('search') || '').toLowerCase()),
    )
    const offset = Number(query.get('offset') || 0)
    return {
      children: filtered.slice(offset, offset + 100),
      total: filtered.length,
      checkedAt: new Date().toISOString(),
    }
  }
  await page.route('**/api/**', async route => {
    const request = route.request()
    const method = request.method()
    const path = new URL(request.url()).pathname
    const body = request.postDataJSON()
    requests.push({ method, path, body })
    const send = (json: unknown, status = 200) =>
      route.fulfill({ status, json })
    const done = () => route.fulfill({ status: 204 })
    const readOnly: Record<string, unknown> = {
      '/api/children/sessions/session-1/closeout': {
        visits: attendance.length,
        children: new Set(attendance.map(value => value.childId)).size,
        awaitingCollection: attendance.filter(value => !value.checkedOutAt)
          .length,
        collected: attendance.filter(value => value.checkedOutAt).length,
        transferredOut: 0,
        labelsNeedingAttention: attendance.filter(
          value => !value.checkedOutAt && !value.labelIssued,
        ).length,
        failedHandoverAttempts: 0,
        supervisorCollections: 0,
        checkedAt: new Date().toISOString(),
      },
      '/api/children/station/sessions/session-1/day': dayResponse(
        new URL(request.url()).searchParams,
      ),
      '/api/people/metadata': {
        titles: [],
        membershipTypes: [],
        timeZone: 'Africa/Johannesburg',
        adultAge: 18,
      },
      '/api/people/child-1': {
        id: 'child-1',
        details: {
          firstName: 'Lindiwe',
          lastName: 'Mabasa',
          dateOfBirth: '2018-04-12',
          titleCode: null,
          gender: 'FEMALE',
          lifeStatus: 'LIVING',
          dateOfDeath: null,
        },
        ageCategory: 'CHILD',
        membershipType: 'ACTIVE_MEMBER',
        version: 0,
        archivedAt: null,
        contacts: [],
        addresses: [],
      },
      '/api/children/programmes/programme-1': programmes[0],
      '/api/children/programmes/programme-1/children/child-1/readiness': {
        ready: false,
        completed: 4,
        total: 7,
        on: '2026-10-26',
        checkedAt: new Date(now).toISOString(),
        timeZone: 'Africa/Johannesburg',
        enrolment: {
          id: 'enrolment-1',
          programmeId: 'programme-1',
          childId: 'child-1',
          validFrom: '2026-01-01',
          validUntil: '2027-01-01',
          endedAt: null,
          version: 0,
          createdAt: new Date(now).toISOString(),
        },
        steps: [
          'IDENTITY',
          'ENROLMENT',
          'CLASS',
          'POLICY',
          'GUARDIAN',
          'CONSENT',
          'COLLECTOR',
        ].map(code => ({
          code,
          complete: ['IDENTITY', 'ENROLMENT', 'CLASS', 'POLICY'].includes(code),
        })),
      },
      '/api/children/programmes/programme-1/children': {
        children: [
          {
            enrolmentId: 'enrolment-1',
            childId: 'child-1',
            name: 'Lindiwe Mabasa',
            validFrom: '2026-01-01',
            validUntil: '2027-01-01',
            status: 'ACTIVE',
            readiness: { ready: false, completed: 4, total: 7 },
            classes: [{ id: 'class-1', name: 'Sunday Explorers' }],
          },
        ],
        total: 1,
      },
      '/api/children/consent-policies/policy-1': {
        id: 'policy-1',
        programmeId: 'programme-1',
        label: 'Family consent · 2026',
        documentReference: 'https://example.org/consent',
        contentHash: 'a'.repeat(64),
        createdAt: new Date(now).toISOString(),
      },
      '/api/children/sessions/session-1/expected-children': expected,
      '/api/children/attendance/children/child-1': [],
    }
    if (method === 'GET' && path in readOnly) {
      return send(readOnly[path])
    }
    if (path === '/api/me') {
      return send({
        id: 'account-1',
        username: 'coordinator',
        displayName: 'Naledi Mokoena',
        status: 'active',
        roles: [],
        permissions,
      })
    }

    if (path === '/api/people') {
      return send([
        {
          id: 'child-1',
          details: {
            firstName: 'Lindiwe',
            lastName: 'Mabasa',
            dateOfBirth: '2018-04-12',
            titleCode: null,
            gender: 'FEMALE',
            lifeStatus: 'LIVING',
            dateOfDeath: null,
          },
          ageCategory: 'CHILD',
          membershipType: 'ACTIVE_MEMBER',
          version: 0,
          archivedAt: null,
        },
      ])
    }

    if (path === '/api/children/programmes') {
      if (method === 'POST') {
        const value = {
          id: 'programme-2',
          ...body,
          currentPolicyId: null,
          version: 0,
          createdAt: new Date().toISOString(),
        }
        programmes.push(value)
        return send({ id: value.id }, 201)
      }
      return send(programmes)
    }

    if (path === '/api/children/classes') {
      if (method === 'POST') {
        return send({ id: 'class-2' }, 201)
      }
      return send([group])
    }

    if (path === '/api/children/locations') {
      if (method === 'POST') {
        return send({ id: 'location-2' }, 201)
      }
      return send([
        {
          id: 'location-1',
          name: 'Main campus',
          timeZone: 'Africa/Johannesburg',
        },
      ])
    }
    if (path === '/api/children/rooms') {
      if (method === 'POST') {
        return send({ id: 'room-2' }, 201)
      }
      return send([room])
    }
    if (
      path === '/api/children/sessions'
      || path === '/api/children/station/sessions'
    ) {
      return send([session])
    }
    if (
      path === '/api/children/sessions/session-1'
      || path === '/api/children/station/sessions/session-1'
    ) {
      return send(session)
    }

    if (path === '/api/children/station/sessions/session-1/attendance') {
      const all
        = new URL(request.url()).searchParams.get('openOnly') === 'false'
      return send(
        attendance
          .filter(a => all || !a.checkedOutAt)
          .map(a => ({
            attendance: a,
            childName: expected.find(e => e.childId === a.childId)?.name,
          })),
      )
    }
    if (path === '/api/children/attendance' && method === 'POST') {
      const value = {
        id: 'attendance-1',
        childId: body.childId,
        sessionId: body.sessionId,
        arrivedAt: new Date().toISOString(),
        checkedOutAt: null,
        collectorId: null,
        authorizationId: null,
        identityVerified: false,
        exceptionUsed: false,
        labelVersion: 0,
        labelExpiresAt: null,
        labelIssued: false,
        version: 0,
      }
      attendance.push(value)
      session.occupancy = 1
      return send(value)
    }
    if (path === '/api/children/attendance/labels') {
      const record = attendance[0]!
      record.version++
      record.labelVersion++
      record.labelIssued = true
      return send({
        attendanceId: record.id,
        labelVersion: record.labelVersion,
        pickupCode: 'TEST-ONLY-PICKUP-CODE',
        expiresAt: new Date(now + 86_400_000).toISOString(),
        attendanceVersion: record.version,
      })
    }
    if (path === '/api/children/attendance/attendance-1') {
      return send(attendance[0])
    }
    if (path === '/api/children/attendance/attendance-1/collectors') {
      return send([
        {
          authorizationId: 'grant-1',
          collectorId: 'adult-1',
          name: 'Solly Mabasa',
          validUntil: new Date(now + 86_400_000).toISOString(),
        },
      ])
    }
    if (path === '/api/children/attendance/checkout') {
      const record = attendance[0]!
      if (body.pickupCode !== 'TEST-ONLY-PICKUP-CODE') {
        record.version++
        return send(
          {
            message:
              'Pickup code is invalid or expired. Reload attendance before retrying.',
          },
          400,
        )
      }
      record.version++
      record.checkedOutAt = new Date().toISOString()
      record.collectorId = body.collectorId
      record.identityVerified = body.identityVerified
      session.occupancy = 0
      return send(record)
    }
    if (path === '/api/children/enrolments') {
      if (method === 'POST') {
        return send({ id: 'enrolment-1' }, 201)
      }
      return send([
        {
          id: 'enrolment-1',
          programmeId: 'programme-1',
          childId: 'child-1',
          validFrom: '2026-01-01',
          validUntil: '2027-01-01',
          endedAt: null,
          version: 0,
          createdAt: new Date(now).toISOString(),
        },
      ])
    }
    if (path === '/api/children/class-assignments') {
      return send([
        {
          id: 'assignment-1',
          programmeId: 'programme-1',
          classId: group.id,
          childId: 'child-1',
          enrolmentId: 'enrolment-1',
          validFrom: '2026-01-01',
          validUntil: '2027-01-01',
          endedAt: null,
          version: 0,
        },
      ])
    }
    if (path === '/api/children/safeguarding/child-1') {
      return send({ authorities: [], consents: [], pickupAuthorizations: [] })
    }

    if (path === '/api/children/support/access') {
      return send({
        childId: 'child-1',
        allergies: 'Peanut allergy',
        careInstructions: 'Contact guardian',
        restrictedNotes: 'Restricted care detail',
        reviewOn: '2027-01-01',
        retainUntil: '2028-01-01',
        version: 0,
        updatedAt: new Date(now).toISOString(),
      })
    }
    if (path === '/api/children/sessions/status') {
      session.session.status = body.status
      session.session.version++
      return done()
    }
    errors.push(`Unexpected API: ${method} ${path}`)
    return send({ message: 'Unexpected API request' }, 500)
  })
  return { requests, errors, session, attendance }
}
