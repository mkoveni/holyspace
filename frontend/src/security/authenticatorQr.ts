import QRCode from 'qrcode'

/** Encode the existing provisioning URI locally; never send authenticator secrets to an image service. */
export async function authenticatorQr (uri:string): Promise<string> {
  const parsed = new URL(uri)
  if (uri.length > 4096 || parsed.protocol !== 'otpauth:' || parsed.hostname !== 'totp' || !parsed.searchParams.get('secret')) {
    throw new Error('Invalid authenticator setup URI.')
  }
  return QRCode.toDataURL(uri, {
    errorCorrectionLevel: 'M',
    width: 320,
    margin: 4,
    color: {dark:'#000000',light:'#ffffff'},
  })
}
