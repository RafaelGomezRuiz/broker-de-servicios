import queEs from './que-es-el-broker.md?raw'
import postman from './probar-con-postman.md?raw'
import laravel from './integrar-laravel.md?raw'
import adjuntos from './cco-adjuntos-y-lote.md?raw'
import estados from './estados-y-errores.md?raw'

export interface Guia {
  slug: string
  titulo: string
  resumen: string
  contenido: string
}

/** Guías escritas a mano (Markdown). La referencia de cada endpoint sale del contrato OpenAPI. */
export const guias: Guia[] = [
  {
    slug: 'que-es-el-broker',
    titulo: 'Qué es el broker y cómo pedir una llave',
    resumen: 'Para qué sirve, qué cambia en tu sistema y cómo se obtiene el acceso.',
    contenido: queEs,
  },
  {
    slug: 'probar-con-postman',
    titulo: 'Probar con Postman',
    resumen: 'Importar el contrato, configurar la llave y enviar el primer correo.',
    contenido: postman,
  },
  {
    slug: 'integrar-laravel',
    titulo: 'Integrar un sistema Laravel',
    resumen: 'Variables, llamada al broker y manejo de errores desde PHP.',
    contenido: laravel,
  },
  {
    slug: 'cco-adjuntos-y-lote',
    titulo: 'Copia oculta, adjuntos y envíos masivos',
    resumen: 'CCO, varios destinatarios, archivos y lotes de hasta 200 correos.',
    contenido: adjuntos,
  },
  {
    slug: 'estados-y-errores',
    titulo: 'Límites, estados y errores',
    resumen: 'Qué significa cada estado, cuándo se reintenta y qué hacer ante cada error.',
    contenido: estados,
  },
]

export const buscarGuia = (slug: string) => guias.find((g) => g.slug === slug)
