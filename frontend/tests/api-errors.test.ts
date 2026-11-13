import { AxiosError, CanceledError, type InternalAxiosRequestConfig } from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { classifyError, isConflict } from '@/composables/apiErrors'
import { useAction } from '@/composables/useAction'
import { useUiStore } from '@/stores/ui'

const config = { headers: {} } as InternalAxiosRequestConfig

function failure (status: number, data: unknown = {}) {
  return new AxiosError('Request failed', undefined, config, undefined, {
    config,
    data,
    headers: {},
    status,
    statusText: '',
  })
}

describe('classifyError', () => {
  it('classifies by status and prefers the server message', () => {
    expect(classifyError(failure(409, { message: 'Version is stale.' }))).toEqual({
      kind: 'conflict',
      status: 409,
      message: 'Version is stale.',
    })
    expect(classifyError(failure(404)).kind).toBe('not-found')
    expect(classifyError(failure(422)).kind).toBe('validation')
    expect(classifyError(failure(503)).kind).toBe('server')
  })

  it('gives plain wording when the server sends none', () => {
    expect(classifyError(failure(409)).message).toContain('changed by someone else')
    expect(classifyError(failure(429)).message).toContain('Too many attempts')
    expect(classifyError(failure(403)).message).toContain('do not have permission')
    expect(classifyError(failure(500)).message).toContain('could not complete')
  })

  it('reports an unreachable server as a network error', () => {
    const offline = new AxiosError('Network Error', 'ERR_NETWORK', config)
    expect(classifyError(offline)).toMatchObject({ kind: 'network', status: null })
  })

  it('flattens validation violations', () => {
    const info = classifyError(failure(422, { violations: [{ propertyPath: 'name', title: 'Required.' }] }))
    expect(info.message).toBe('name: Required.')
  })

  it('recognises cancellation and plain errors', () => {
    expect(classifyError(new CanceledError('Session changed.')).kind).toBe('cancelled')
    expect(classifyError(new Error('boom')).message).toBe('boom')
    expect(classifyError('x', 'Fallback').message).toBe('Fallback')
    expect(isConflict(failure(409))).toBe(true)
    expect(isConflict(failure(400))).toBe(false)
  })
})

describe('useAction error handling', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows the server message instead of the Axios status text', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'notifyError')
    const ok = await useAction().run(async () => {
      throw failure(422, { message: 'Name is required.' })
    })
    expect(ok).toBe(false)
    expect(spy).toHaveBeenCalledWith('Name is required.')
  })

  it('runs onConflict on a 409 and still reports the failure', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'notifyError')
    const reload = vi.fn()
    const ok = await useAction().run(async () => {
      throw failure(409)
    }, { onConflict: reload })
    expect(ok).toBe(false)
    expect(reload).toHaveBeenCalledOnce()
    expect(spy).toHaveBeenCalledOnce()
  })

  it('does not run onConflict for other failures and stays silent on cancellation', async () => {
    const ui = useUiStore()
    const spy = vi.spyOn(ui, 'notifyError')
    const reload = vi.fn()
    await useAction().run(async () => {
      throw failure(500)
    }, { onConflict: reload })
    expect(reload).not.toHaveBeenCalled()
    spy.mockClear()
    await useAction().run(async () => {
      throw new CanceledError('Session changed.')
    })
    expect(spy).not.toHaveBeenCalled()
  })

  it('swallows a failing onConflict reload', async () => {
    const ok = await useAction().run(async () => {
      throw failure(409)
    }, {
      onConflict: () => {
        throw new Error('reload failed')
      },
    })
    expect(ok).toBe(false)
  })
})
