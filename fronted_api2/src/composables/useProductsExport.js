import { jsPDF } from 'jspdf'
import autoTable from 'jspdf-autotable'
import api from '@/service/api'

// La API pagina (máximo 50 por página), así que recorremos todas las páginas
export const fetchAllProducts = async () => {
  const todos = []
  let page = 1
  let ultima
  do {
    const { data } = await api.get('/products', { params: { page, per_page: 50 } })
    todos.push(...data.data)
    ultima = data.last_page
    page++
  } while (page <= ultima)
  return todos
}

const descargar = (blob, nombre) => {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = nombre
  document.body.appendChild(a)
  a.click()
  a.remove()
  URL.revokeObjectURL(url)
}

// Evita que Excel interprete un texto escrito por un usuario como fórmula (=, +, -, @)
const seguro = (valor) => {
  const texto = String(valor ?? '')
  return /^[=+\-@\t\r]/.test(texto) ? `'${texto}` : texto
}

const ENCABEZADOS = ['#', 'Nombre', 'Descripción', 'Precio', 'Stock']

const fechaHoy = () => new Date().toISOString().slice(0, 10)

// ---------------------------------------------------------------------
// CSV
// ---------------------------------------------------------------------
export const exportCsv = (productos) => {
  // Texto entre comillas (las comillas internas se duplican). Los saltos de línea y
  // espacios repetidos de una descripción se colapsan: así cada producto ocupa una sola fila.
  const texto = (v) => `"${seguro(String(v ?? '').replace(/\s+/g, ' ').trim()).replace(/"/g, '""')}"`

  // Ordenados por id: el archivo siempre sale en el mismo orden
  const ordenados = [...productos].sort((a, b) => a.id - b.id)

  const filas = ordenados.map((p, i) =>
    [
      i + 1,
      texto(p.name),
      texto(p.description),
      Number(p.price).toFixed(2), // número sin comillas: Excel lo reconoce como número
      Number(p.stock),
    ].join(','),
  )

  const contenido = [
    'sep=,', // le indica a Excel el separador, sin importar la configuración regional
    ENCABEZADOS.map(texto).join(','),
    ...filas,
  ].join('\r\n')

  // El BOM (\uFEFF) hace que Excel lea bien las tildes y la ñ
  descargar(
    new Blob(['\uFEFF' + contenido], { type: 'text/csv;charset=utf-8;' }),
    `productos_${fechaHoy()}.csv`,
  )
}

// ---------------------------------------------------------------------
// PDF
// ---------------------------------------------------------------------
// Construye el documento; las tres funciones de abajo lo reutilizan
const crearPdf = (productos) => {
  const ordenados = [...productos].sort((a, b) => a.id - b.id)
  const doc = new jsPDF()

  doc.setFontSize(16)
  doc.text('Listado de productos', 14, 16)
  doc.setFontSize(10)
  doc.setTextColor(100)
  doc.text(
    `Generado el ${new Date().toLocaleDateString('es-SV')} - ${ordenados.length} productos`,
    14,
    22,
  )
  doc.setTextColor(0)

  autoTable(doc, {
    startY: 28,
    head: [ENCABEZADOS],
    body: ordenados.map((p, i) => [
      i + 1,
      p.name,
      String(p.description ?? '').replace(/\s+/g, ' ').trim(),
      `$${Number(p.price).toFixed(2)}`,
      p.stock,
    ]),
    styles: { fontSize: 9, cellPadding: 2 },
    headStyles: { fillColor: [16, 185, 129], halign: 'center' },
    columnStyles: {
      0: { halign: 'center', cellWidth: 14 },
      3: { halign: 'right', cellWidth: 26 },
      4: { halign: 'center', cellWidth: 20 },
    },
  })

  // Pie de página con numeración
  const paginas = doc.getNumberOfPages()
  const ancho = doc.internal.pageSize.getWidth()
  const alto = doc.internal.pageSize.getHeight()
  for (let i = 1; i <= paginas; i++) {
    doc.setPage(i)
    doc.setFontSize(8)
    doc.setTextColor(120)
    doc.text(`Página ${i} de ${paginas}`, ancho - 14, alto - 8, { align: 'right' })
  }

  return doc
}

// Descarga directa
export const exportPdf = (productos) => {
  crearPdf(productos).save(`productos_${fechaHoy()}.pdf`)
}

// URL temporal para mostrarlo dentro de un <iframe>.
// Quien la use debe liberarla con URL.revokeObjectURL(url) cuando ya no la necesite.
export const pdfComoUrl = (productos) => URL.createObjectURL(crearPdf(productos).output('blob'))
