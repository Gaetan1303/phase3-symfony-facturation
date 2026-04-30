import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  connect() {
    this.container = this.element.querySelector('#lines');
    if (!this.container) return;

    this.prototype = this.container.dataset.prototype || '';
    this.index = this.container.querySelectorAll('tr').length || 0;

    this.initAddCard();
    this.attachExistingRows();
    this.updateTotals();
  }

  parseNumber(v){
    v = v || '0';
    v = String(v).replace(',', '.').replace(/[^0-9.\-]/g,'');
    return parseFloat(v) || 0;
  }

  updateTotals(){
    const rows = Array.from(this.container.querySelectorAll('tr'));
    let total = 0;
    rows.forEach(row => {
      const qtyInput = row.querySelector('input[name$="[quantity]"]');
      const priceInput = row.querySelector('input[name$="[unitPrice]"]');

      const qtyTextFallback = row.querySelector('td:nth-child(2)')?.textContent;
      const priceTextFallback = row.querySelector('td:nth-child(3)')?.textContent;

      const qty = this.parseNumber(qtyInput?.value ?? qtyTextFallback);
      const price = this.parseNumber(priceInput?.value ?? priceTextFallback);
      const cell = row.querySelector('.line-total');
      const lineTotal = qty * price;
      if(cell) cell.textContent = lineTotal.toFixed(2) + ' €';
      total += lineTotal;
    });
    const invoiceTotalEl = document.getElementById('invoice-total');
    if (invoiceTotalEl) invoiceTotalEl.textContent = total.toFixed(2) + ' €';
    const apGlobalEl = document.getElementById('ap_global_total');
    if (apGlobalEl) apGlobalEl.textContent = total.toFixed(2) + ' €';
  }

  initAddCard(){
    // populate visual product select from prototype
    try{
      const parser = new DOMParser();
      const doc = parser.parseFromString(this.prototype, 'text/html');
      const protoSelect = doc.querySelector('select');
      const protoTextarea = doc.querySelector('textarea');
      const cardEl = document.getElementById('ap_product');
      if (protoSelect && cardEl && cardEl.tagName.toLowerCase() === 'select') {
        cardEl.innerHTML = protoSelect.innerHTML;
      } else if (protoSelect && cardEl && cardEl.tagName.toLowerCase() !== 'select') {
        const sel = document.createElement('select');
        sel.id = 'ap_product';
        sel.className = cardEl.className;
        sel.innerHTML = protoSelect.innerHTML;
        cardEl.parentNode.replaceChild(sel, cardEl);
      } else if (protoTextarea && cardEl) {
        // keep textarea
      } else if (cardEl) {
        cardEl.value = '';
      }
    } catch (e) {
      // ignore
    }

    const addBtn = document.getElementById('ap_add');
    if (addBtn) addBtn.addEventListener('click', (ev) => { ev.preventDefault(); this.addFromCard(); });

    const apQty = document.getElementById('ap_qty');
    const apPrice = document.getElementById('ap_price');
    const apLineTotal = document.getElementById('ap_line_total');
    if (apQty) apQty.addEventListener('input', () => this.updateAddCardPreview());
    if (apPrice) apPrice.addEventListener('input', () => this.updateAddCardPreview());
    this.updateAddCardPreview();

    const apProduct = document.getElementById('ap_product');
    if (apProduct) apProduct.addEventListener('input', () => this.updateApBadge());
    this.updateApBadge();
  }

  updateAddCardPreview(){
    const apQty = document.getElementById('ap_qty');
    const apPrice = document.getElementById('ap_price');
    const apLineTotal = document.getElementById('ap_line_total');
    if (!apLineTotal) return;
    const q = this.parseNumber(apQty?.value);
    const p = this.parseNumber(apPrice?.value);
    const span = apLineTotal.querySelector('span');
    if (span) span.textContent = (q * p).toFixed(2) + ' €';
  }

  updateApBadge(){
    const apProduct = document.getElementById('ap_product');
    const apBadge = document.getElementById('ap_badge');
    if (!apBadge || !apProduct) return;
    const v = (apProduct.value || '').trim();
    const initial = v ? v.charAt(0).toUpperCase() : '?';
    apBadge.textContent = initial;
  }

  addFromCard(){
    const prodValue = document.getElementById('ap_product')?.value || '';
    const qtyValue = document.getElementById('ap_qty')?.value || '1';
    const priceValue = document.getElementById('ap_price')?.value || '';

    const html = this.prototype.replace(/__name__/g, this.index);
    const tpl = document.createElement('template');
    tpl.innerHTML = html.trim();

    const protoDoc = tpl.content;
    const prodFieldProto = protoDoc.querySelector('select, textarea, input');
    const qtyProto = protoDoc.querySelector('input[name$="[quantity]"]');
    const priceProto = protoDoc.querySelector('input[name$="[unitPrice]"]');

    const tr = document.createElement('tr');
    const td = document.createElement('td');
    td.colSpan = 5;
    td.style.padding = '0';

    const card = document.createElement('div');
    card.style.boxSizing = 'border-box';
    card.style.display = 'flex';
    card.style.alignItems = 'center';
    card.style.gap = '16px';
    card.style.background = '#F9FAFB';
    card.style.border = '1px solid #E5E7EB';
    card.style.borderRadius = '10px';
    card.style.padding = '17px';

    const prodArea = document.createElement('div');
    prodArea.style.flex = '1';
    prodArea.style.display = 'flex';
    prodArea.style.alignItems = 'center';
    prodArea.style.gap = '12px';

    const badge = document.createElement('div');
    badge.className = 'w-10 h-10 rounded-full flex items-center justify-center font-medium';
    badge.style.background = '#DBEAFE';
    badge.style.color = '#1447E6';
    badge.textContent = (prodValue.trim().charAt(0) || '?').toUpperCase();
    prodArea.appendChild(badge);

    if (prodFieldProto) {
      const prodField = prodFieldProto.cloneNode(true);
      prodField.value = prodValue;
      prodField.classList.add('rounded-[8px]', 'border', 'border-[#D1D5DC]', 'p-2');
      prodField.style.width = '100%';
      prodArea.appendChild(prodField);
    } else {
      const span = document.createElement('div');
      span.textContent = prodValue;
      prodArea.appendChild(span);
    }

    const qtyArea = document.createElement('div');
    qtyArea.style.width = '96px';
    qtyArea.style.flex = 'none';
    if (qtyProto) {
      const q = qtyProto.cloneNode(true);
      q.value = qtyValue;
      q.classList.add('rounded-[8px]', 'border', 'border-[#D1D5DC]', 'p-2');
      q.style.width = '96px';
      qtyArea.appendChild(q);
    } else {
      const q = document.createElement('input'); q.type='number'; q.value = qtyValue; qtyArea.appendChild(q);
    }

    const priceArea = document.createElement('div');
    priceArea.style.width = '128px';
    priceArea.style.flex = 'none';
    if (priceProto) {
      const p = priceProto.cloneNode(true);
      p.value = priceValue;
      p.classList.add('rounded-[8px]', 'border', 'border-[#D1D5DC]', 'p-2');
      p.style.width = '128px';
      priceArea.appendChild(p);
    } else {
      const p = document.createElement('input'); p.type='text'; p.value = priceValue; priceArea.appendChild(p);
    }

    const totalArea = document.createElement('div');
    totalArea.style.width = '128px';
    totalArea.style.textAlign = 'right';
    totalArea.className = 'line-total';
    totalArea.textContent = (parseFloat(qtyValue || 0) * parseFloat(priceValue || 0)).toFixed(2) + ' €';

    const actionsArea = document.createElement('div');
    actionsArea.style.width = '48px';
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button'; removeBtn.className = 'text-sm text-red-600'; removeBtn.textContent = '🗑';
    removeBtn.addEventListener('click', () => { tr.remove(); this.updateTotals(); });
    actionsArea.appendChild(removeBtn);

    card.appendChild(prodArea);
    card.appendChild(qtyArea);
    card.appendChild(priceArea);
    card.appendChild(totalArea);
    card.appendChild(actionsArea);

    td.appendChild(card);
    tr.appendChild(td);

    tr.querySelectorAll('input, select, textarea').forEach(i => {
      i.addEventListener('input', () => this.updateTotals());
    });

    this.container.appendChild(tr);
    this.index++;
    this.updateTotals();
  }

  attachExistingRows(){
    this.container.querySelectorAll('tr').forEach(row => {
      row.style.boxSizing = 'border-box';
      row.style.background = '#FFFFFF';
      row.style.border = 'none';
      row.style.padding = '8px 0';
      row.querySelectorAll('input, select, textarea').forEach(i => {
        i.classList.add('rounded-[8px]');
        i.classList.add('border');
        i.classList.add('border-[#D1D5DC]');
        i.classList.add('p-2');
        i.addEventListener('input', () => this.updateTotals());
      });
      const prodCell = row.querySelector('td') || null;
      if (prodCell && !prodCell.querySelector('.w-10')) {
        const text = (prodCell.textContent || '').trim();
        const initial = text ? text.charAt(0).toUpperCase() : '?';
        const badge = document.createElement('div');
        badge.className = 'w-10 h-10 rounded-full flex items-center justify-center font-medium mr-3';
        badge.style.background = '#DBEAFE';
        badge.style.color = '#1447E6';
        badge.textContent = initial;
        prodCell.insertBefore(badge, prodCell.firstChild);
      }
    });
  }
}
