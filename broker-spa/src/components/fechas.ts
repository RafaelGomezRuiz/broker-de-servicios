const formato = new Intl.DateTimeFormat('es-DO', {
  dateStyle: 'medium',
  timeStyle: 'short',
  timeZone: 'America/Santo_Domingo',
})

const relativo = new Intl.RelativeTimeFormat('es', { numeric: 'auto' })

/** «23 oct 2026, 10:15». */
export function fecha(iso: string | null | undefined): string {
  return iso ? formato.format(new Date(iso)) : '—'
}

/** «hace 5 minutos», «ayer»… Para fechas recientes. */
export function haceCuanto(iso: string | null | undefined): string {
  if (!iso) return 'nunca'
  const segundos = Math.round((new Date(iso).getTime() - Date.now()) / 1000)
  const pasos: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  for (const [unidad, tamano] of pasos) {
    if (Math.abs(segundos) >= tamano) return relativo.format(Math.round(segundos / tamano), unidad)
  }
  return 'hace un momento'
}
