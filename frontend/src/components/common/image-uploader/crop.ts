export interface ImageSize { width: number, height: number }
export interface CropSettings { zoom: number, x: number, y: number }
export const imagePresets = {
  person: { width: 250, height: 250 },
  family: { width: 1200, height: 800 },
  // Event and community covers are shown 16:9 in the member app; the uploader saves PNG, so 720p stays well under 5 MB.
  event: { width: 1280, height: 720 },
  community: { width: 1280, height: 720 },
  form: { width: 1200, height: 300 },
} as const

export function validateSize (size: ImageSize): void {
  if (![size.width, size.height].every(value => Number.isInteger(value) && value > 0 && value <= 4096) || size.width * size.height > 16_000_000) {
    throw new Error('Choose output dimensions between 1 and 4,096 pixels, up to 16 million pixels.')
  }
}

/** The source rectangle always stays inside the original image and fills the output. */
export function cropRectangle (source: ImageSize, target: ImageSize, crop: CropSettings) {
  validateSize(target)
  if (!(source.width > 0 && source.height > 0)) {
    throw new Error('This image has no usable dimensions.')
  }
  const zoom = Math.min(4, Math.max(1, crop.zoom))
  const scale = Math.max(target.width / source.width, target.height / source.height) * zoom
  const width = target.width / scale
  const height = target.height / scale
  return {
    x: (source.width - width) * Math.min(100, Math.max(0, crop.x)) / 100,
    y: (source.height - height) * Math.min(100, Math.max(0, crop.y)) / 100,
    width,
    height,
  }
}
