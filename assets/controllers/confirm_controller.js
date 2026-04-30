import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
  connect() {
    this.modal = document.getElementById('confirm-modal')
    this.messageEl = document.getElementById('confirm-modal-message')
    this.confirmButton = document.getElementById('confirm-modal-confirm')
    this.currentForm = null
    if (!this.modal) return
    this._onKey = this._onKey.bind(this)
  }

  open(e) {
    e.preventDefault()
    const el = e.currentTarget
    const msg = el.getAttribute('data-confirm-message') || 'Êtes-vous sûr ?'
    // find target form: data-confirm-target-form selector or closest form
    const selector = el.getAttribute('data-confirm-target')
    this.currentForm = selector ? document.querySelector(selector) : el.closest('form')
    this.messageEl.textContent = msg
    this.modal.classList.remove('hidden')
    document.documentElement.classList.add('overflow-hidden')
    document.addEventListener('keydown', this._onKey)
  }

  confirm(e) {
    e.preventDefault()
    if (this.currentForm) {
      // submit the form normally
      this.currentForm.submit()
    }
    this._hide()
  }

  cancel(e) {
    if (e) e.preventDefault()
    this._hide()
  }

  _hide() {
    if (!this.modal) return
    this.modal.classList.add('hidden')
    document.documentElement.classList.remove('overflow-hidden')
    document.removeEventListener('keydown', this._onKey)
    this.currentForm = null
  }

  _onKey(e) {
    if (e.key === 'Escape') this._hide()
  }
}
