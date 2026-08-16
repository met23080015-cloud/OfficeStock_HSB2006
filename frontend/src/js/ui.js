export function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

export function toast(message, type = 'success') {
  const region = document.getElementById('toast-region');
  const node = document.createElement('div');
  node.className = `toast ${type}`;
  node.textContent = message;
  region.appendChild(node);
  setTimeout(() => node.remove(), 4300);
}

export function badge(status) {
  const text = String(status || '').toUpperCase();
  const cls = text.toLowerCase().replaceAll('_', '-');
  return `<span class="badge ${cls}">${escapeHtml(text)}</span>`;
}

export function money(value) {
  const n = Number(value || 0);
  return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(n);
}

export function dateTime(value) {
  if (!value) return '-';
  const d = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
  if (Number.isNaN(d.getTime())) return escapeHtml(value);
  return d.toLocaleString();
}

export function statCard(label, value, hint = '') {
  return `<article class="stat-card"><small>${escapeHtml(label)}</small><strong>${escapeHtml(value)}</strong><div class="hint">${escapeHtml(hint)}</div></article>`;
}

export function showModal(title, content) {
  closeModal();
  const backdrop = document.createElement('div');
  backdrop.className = 'modal-backdrop';
  backdrop.id = 'modal-root';
  backdrop.innerHTML = `
    <section class="modal" role="dialog" aria-modal="true">
      <div class="modal-head"><h2>${escapeHtml(title)}</h2><button class="icon-btn" data-close-modal aria-label="Close">×</button></div>
      <div class="modal-body">${content}</div>
    </section>`;
  backdrop.addEventListener('click', e => {
    if (e.target === backdrop || e.target.closest('[data-close-modal]')) closeModal();
  });
  document.body.appendChild(backdrop);
  return backdrop;
}

export function closeModal() {
  document.getElementById('modal-root')?.remove();
}

export function errorPanel(error) {
  return `<div class="error-panel"><strong>Unable to load this section.</strong><div>${escapeHtml(error?.message || error)}</div></div>`;
}

export function itemRowsHtml(products, id = 'itemRows') {
  const options = products.map(p => `<option value="${p.id}">${escapeHtml(p.sku)} - ${escapeHtml(p.name)} (stock ${p.quantity})</option>`).join('');
  return `
    <div id="${id}" class="items-box">
      <div class="item-row">
        <label class="field"><span>Product</span><select data-item-product required><option value="">Select product</option>${options}</select></label>
        <label class="field"><span>Quantity</span><input data-item-quantity type="number" min="1" value="1" required></label>
        <button type="button" class="btn btn-sm remove-item">Remove</button>
      </div>
    </div>
    <button type="button" class="btn btn-sm" data-add-item>Add another item</button>`;
}

export function bindItemRows(root, products) {
  const options = products.map(p => `<option value="${p.id}">${escapeHtml(p.sku)} - ${escapeHtml(p.name)} (stock ${p.quantity})</option>`).join('');
  root.querySelector('[data-add-item]')?.addEventListener('click', () => {
    const box = root.querySelector('.items-box');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
      <label class="field"><span>Product</span><select data-item-product required><option value="">Select product</option>${options}</select></label>
      <label class="field"><span>Quantity</span><input data-item-quantity type="number" min="1" value="1" required></label>
      <button type="button" class="btn btn-sm remove-item">Remove</button>`;
    box.appendChild(div);
  });
  root.addEventListener('click', e => {
    if (e.target.closest('.remove-item')) {
      const rows = root.querySelectorAll('.item-row');
      if (rows.length > 1) e.target.closest('.item-row').remove();
    }
  });
}

export function readItems(root) {
  const rows = [...root.querySelectorAll('.item-row')];
  return rows.map(row => ({
    product_id: Number(row.querySelector('[data-item-product]').value),
    quantity: Number(row.querySelector('[data-item-quantity]').value)
  }));
}
