import type { Checkout } from './api'
export function hostedCheckout (checkout: Checkout): {
  url: string
  post: boolean
} {
  const url = new URL(checkout.url)
  if (
    url.protocol !== 'https:'
    || url.username
    || url.password
    || (url.port && url.port !== '443')
    || url.hostname !== 'c.yoco.com'
  ) {
    throw new Error('The provider checkout address could not be verified.')
  }
  return { url: url.href, post: false }
}
