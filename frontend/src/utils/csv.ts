export interface CsvColumn<T> {
  header: string
  value: (row: T) => string | number | null | undefined
}

/** Cells starting with = + - @ are read as formulas by spreadsheets, so they are prefixed with a quote. */
function cell (raw: string | number | null | undefined): string {
  let text = raw === null || raw === undefined ? '' : String(raw)
  if (/^[=+\-@\t\r]/.test(text)) {
    text = `'${text}`
  }
  return /[",\n\r]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text
}

export function toCsv<T> (rows: T[], columns: CsvColumn<T>[]): string {
  const lines = [columns.map(column => cell(column.header)).join(',')]
  for (const row of rows) {
    lines.push(columns.map(column => cell(column.value(row))).join(','))
  }
  return lines.join('\r\n')
}

export function downloadCsv (filename: string, csv: string) {
  // The byte-order mark makes Excel read UTF-8 names correctly.
  const url = URL.createObjectURL(new Blob([String.fromCodePoint(0xFE_FF) + csv], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.append(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
