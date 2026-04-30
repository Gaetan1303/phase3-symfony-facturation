import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect() {
    this.sidebar = this.element
    this.overlay = document.getElementById('sidebar-overlay')
  }

  open(e) {
    if (e && e.preventDefault) e.preventDefault()
    this.sidebar.classList.remove('-translate-x-full')
    if (this.overlay) this.overlay.classList.remove('hidden')
  }

  close(e) {
    if (e && e.preventDefault) e.preventDefault()
    this.sidebar.classList.add('-translate-x-full')
    if (this.overlay) this.overlay.classList.add('hidden')
  }

  toggle(e) {
    if (this.sidebar.classList.contains('-translate-x-full')) {
      this.open(e)
    } else {
      this.close(e)
    }
  }

  disconnect() {
    // nothing global attached; safe to leave
  }
}
