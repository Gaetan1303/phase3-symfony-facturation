import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  static values = { labels: Array, data: Array }

  connect() {
    this.canvas = this.element.querySelector('canvas')
    if (!this.canvas) return
    const ctx = this.canvas.getContext && this.canvas.getContext('2d')
    if (!ctx) return
    if (typeof Chart === 'undefined') return

    const labels = Array.isArray(this.labelsValue) ? this.labelsValue : []
    const numericData = (Array.isArray(this.dataValue) ? this.dataValue : []).map(v => Number(v) || 0)

    if (this.canvas._chartInstance) {
      try { this.canvas._chartInstance.destroy() } catch (e) { /* ignore */ }
    }

    this.canvas._chartInstance = new Chart(ctx, {
      type: 'bar',
      data: { labels: labels, datasets: [{ label: "Chiffre d'affaires", data: numericData, backgroundColor: 'rgba(37,99,235,0.8)', borderColor: 'rgba(37,99,235,1)', borderWidth: 1 }] },
      options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: function(value){ return value + ' €'; } } } }, plugins: { tooltip: { callbacks: { label: function(ctx){ return ctx.formattedValue + ' €'; } } } } }
    })
  }

  disconnect() {
    if (this.canvas && this.canvas._chartInstance) {
      try { this.canvas._chartInstance.destroy(); delete this.canvas._chartInstance } catch (e) { /* ignore */ }
    }
  }
}
