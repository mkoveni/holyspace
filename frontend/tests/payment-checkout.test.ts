import { describe, expect, it } from 'vitest'
import { hostedCheckout } from '../src/payments/checkout'
describe('Hosted payment destinations', () => {
  it('uses provider-controlled POST for signed Payfast forms', () => {
    expect(
      hostedCheckout({
        id: 'one',
        url: 'https://sandbox.payfast.co.za/eng/process',
        fields: { amount: '100.00' },
      }),
    ).toEqual({ url: 'https://sandbox.payfast.co.za/eng/process', post: true })
  })
  it('uses a verified Yoco redirect without a payment form', () => {
    expect(
      hostedCheckout({
        id: 'one',
        url: 'https://c.yoco.com/checkout/one',
        fields: {},
      }),
    ).toEqual({ url: 'https://c.yoco.com/checkout/one', post: false })
  })
  it('rejects an unencrypted provider address', () => {
    const target = new URL('https://c.yoco.com/checkout/one')
    target.protocol = 'http:'
    expect(() => hostedCheckout({ id: 'one', url: target.href, fields: {} })).toThrow()
  })
  it.each([
    'https://c.yoco.com.evil.test/one',
    'https://evil.test@c.yoco.com/one',
    'https://c.yoco.com:444/one',
    'javascript:alert(1)',
    'https://www.payfast.co.za/other',
  ])('rejects an untrusted checkout address %s', url => {
    expect(() => hostedCheckout({ id: 'one', url, fields: {} })).toThrow()
  })
})
