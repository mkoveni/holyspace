import { describe, expect, it } from 'vitest'
import { cropRectangle, imagePresets, validateSize } from '../src/components/common/image-uploader/crop'

describe('image crop geometry', () => {
  it('crops a landscape image into an exact person square without distortion', () => {
    expect(cropRectangle({ width: 1600, height: 900 }, imagePresets.person, { zoom: 1, x: 50, y: 50 })).toEqual({ x: 350, y: 0, width: 900, height: 900 })
  })
  it('crops a portrait image into a 16:9 event cover', () => {
    const crop = cropRectangle({ width: 900, height: 1600 }, imagePresets.event, { zoom: 1, x: 50, y: 50 })
    expect(crop).toEqual({ x: 0, y: 546.875, width: 900, height: 506.25 })
    expect(crop.width / crop.height).toBeCloseTo(16 / 9)
  })
  it('crops community covers to the same 16:9 shape as event covers', () => {
    expect(imagePresets.community).toEqual(imagePresets.event)
  })
  it('keeps zoom and edge positions inside the original image', () => {
    for (const x of [-100, 0, 50, 100, 200]) {
      for (const y of [-100, 0, 50, 100, 200]) {
        const crop = cropRectangle({ width: 640, height: 480 }, imagePresets.event, { zoom: 4, x, y })
        expect(crop.x).toBeGreaterThanOrEqual(0)
        expect(crop.y).toBeGreaterThanOrEqual(0)
        expect(crop.x + crop.width).toBeLessThanOrEqual(640)
        expect(crop.y + crop.height).toBeLessThanOrEqual(480)
        expect(crop.width / crop.height).toBeCloseTo(16 / 9)
      }
    }
  })
  it('supports smaller sources and clamps zoom to prevent blank output', () => {
    expect(cropRectangle({ width: 100, height: 100 }, imagePresets.person, { zoom: 0.5, x: 50, y: 50 })).toEqual({ x: 0, y: 0, width: 100, height: 100 })
  })
  it('rejects invalid dimensions and excessive canvas allocations', () => {
    for (const size of [{ width: 0, height: 250 }, { width: 1.5, height: 250 }, { width: 5000, height: 250 }, { width: 4096, height: 4096 }]) {
      expect(() => validateSize(size)).toThrow()
    }
  })
})
