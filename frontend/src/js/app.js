import { api, qs, setToken, getToken, API_BASE_URL } from './api.js';
import {
  escapeHtml, toast, badge, money, dateTime, statCard,
  showModal, closeModal, errorPanel, itemRowsHtml, bindItemRows, readItems
} from './ui.js';

const app = document.getElementById('app');

const state = {
  user: null,
  meta: null
};

const roleLabels = {
  ADMIN_MANAGER: 'Admin / Manager',
  WAREHOUSE: 'Warehouse Staff',
  EMPLOYEE: 'Employee'
};

const navByRole = {
  ADMIN_MANAGER: [
    ['dashboard','Dashboard'],
    ['products','Products'],
    ['suppliers','Suppliers'],
    ['inventory','Inventory'],
    ['requests','Requests'],
    ['reports','Reports'],
    ['users','Users'],
    ['transactions','Transactions']
  ],
  WAREHOUSE: [
    ['dashboard','Dashboard'],
    ['products','Products'],
    ['inventory','Inventory'],
    ['stock','Stock In / Out'],
    ['requests','Approved Requests'],
    ['transactions','Transactions']
  ],
  EMPLOYEE: [
    ['dashboard','Dashboard'],
    ['products','Stationery Catalog'],
    ['requests','My Requests'],
    ['new-request','Create Request']
  ]
};

function routeName() {
  return (location.hash.replace(/^#\/?/, '').split('?')[0] || 'dashboard').toLowerCase();
}

function routeQuery() {
  const raw = location.hash.split('?')[1] || '';
  return Object.fromEntries(new URLSearchParams(raw));
}

function navigate(name, params = {}) {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([k,v]) => {
    if (v !== '' && v !== null && v !== undefined) query.set(k, String(v));
  });
  location.hash = `#/${name}${query.toString() ? '?' + query.toString() : ''}`;
}

function titleFor(name) {
  const item = navByRole[state.user?.role_code]?.find(x => x[0] === name);
  if (item) return item[1];
  return name === 'new-request' ? 'Create Request' : 'OfficeStock';
}

function shell(content = '<div class="loading">Loading...</div>') {
  const route = routeName();
  const nav = (navByRole[state.user.role_code] || []).map(([key,label]) =>
    `<a href="#/${key}" class="${route===key?'active':''}">${escapeHtml(label)}</a>`
  ).join('');

  app.innerHTML = `
    <div class="app-shell">
      <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
          <div class="brand-mark">OS</div>
          <div><strong>OfficeStock</strong><small>${escapeHtml(roleLabels[state.user.role_code] || state.user.role_code)}</small></div>
        </div>
        <nav class="nav">${nav}</nav>
        <div class="sidebar-bottom">
          <div class="user-mini">
            <strong>${escapeHtml(state.user.full_name)}</strong>
            <span>${escapeHtml(state.user.email)}</span>
          </div>
          <button class="btn full" id="logoutBtn">Log out</button>
        </div>
      </aside>
      <main class="main">
        <header class="topbar">
          <div class="topbar-title">
            <button class="btn btn-sm mobile-menu" id="mobileMenuBtn">Menu</button>
            <div><h1>${escapeHtml(titleFor(route))}</h1><div class="subtitle">Production frontend → PHP API → online MySQL</div></div>
          </div>
          <span class="badge active">${escapeHtml(roleLabels[state.user.role_code] || '')}</span>
        </header>
        <div class="page" id="page">${content}</div>
      </main>
    </div>`;

  document.getElementById('logoutBtn')?.addEventListener('click', logout);
  document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('open');
  });
}

async function logout() {
  try { await api('/api/auth/logout', { method:'POST' }); } catch {}
  setToken(null);
  state.user = null;
  state.meta = null;
  renderLogin();
}

function renderLogin(message = '') {
  app.innerHTML = `
    <section class="login-page">
      <div class="login-hero">
        <div class="brand-mark">OS</div>
        <div class="eyebrow">HSB2006 BUSINESS WEB APPLICATION</div>
        <h1>OfficeStock</h1>
        <p>Manage office supplies from inventory receipt to internal request, approval, warehouse issue and automatic stock update.</p>
        <div class="workflow-strip">
          <span>Employee Request</span><span>Manager Approval</span><span>Warehouse Issue</span><span>Inventory Update</span>
        </div>
      </div>
      <div class="login-panel">
        <form class="login-card form-stack" id="loginForm">
          <div><h2>Welcome back</h2><p>Sign in with your assessment account.</p></div>
          ${message ? `<div class="error-panel">${escapeHtml(message)}</div>` : ''}
          <div class="field"><label>Email</label><input type="email" name="email" required autocomplete="username" placeholder="employee@officestock.demo"></div>
          <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password" placeholder="••••••••"></div>
          <button class="btn btn-primary full" type="submit">Sign in</button>
          <div class="demo-accounts">
            <strong>Assessment accounts</strong>
            <code>Manager: manager@officestock.demo / Manager@2026</code><br>
            <code>Warehouse: warehouse@officestock.demo / Warehouse@2026</code><br>
            <code>Employee: employee@officestock.demo / Employee@2026</code>
          </div>
          <small class="muted">API: ${escapeHtml(API_BASE_URL || '[not configured]')}</small>
        </form>
      </div>
    </section>`;

  document.getElementById('loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.currentTarget);
    const button = e.currentTarget.querySelector('button[type=submit]');
    button.disabled = true;
    button.textContent = 'Signing in...';
    try {
      const data = await api('/api/auth/login', {
        method:'POST',
        body:{ email:fd.get('email'), password:fd.get('password') }
      });
      setToken(data.token);
      state.user = data.user;
      state.meta = null;
      navigate('dashboard');
      await renderRoute();
    } catch (error) {
      renderLogin(error.message);
    }
  });
}

async function ensureMeta() {
  if (!state.meta) state.meta = await api('/api/meta');
  return state.meta;
}

function pageRoot() {
  return document.getElementById('page');
}

async function renderRoute() {
  if (!state.user) return renderLogin();

  shell();
  const name = routeName();
  try {
    if (name === 'dashboard') return await pageDashboard();
    if (name === 'products') return await pageProducts();
    if (name === 'suppliers' && state.user.role_code === 'ADMIN_MANAGER') return await pageSuppliers();
    if (name === 'inventory' && state.user.role_code !== 'EMPLOYEE') return await pageInventory();
    if (name === 'requests') return await pageRequests();
    if (name === 'new-request' && state.user.role_code === 'EMPLOYEE') return await pageNewRequest();
    if (name === 'stock' && state.user.role_code === 'WAREHOUSE') return await pageStock();
    if (name === 'reports' && state.user.role_code === 'ADMIN_MANAGER') return await pageReports();
    if (name === 'users' && state.user.role_code === 'ADMIN_MANAGER') return await pageUsers();
    if (name === 'transactions' && state.user.role_code !== 'EMPLOYEE') return await pageTransactions();
    navigate('dashboard');
  } catch (error) {
    if (error.status === 401) {
      setToken(null); state.user=null; state.meta=null; return renderLogin('Your session expired. Please sign in again.');
    }
    pageRoot().innerHTML = errorPanel(error);
  }
}

async function pageDashboard() {
  const data = await api('/api/dashboard');
  const m = data.metrics || {};
  const cards = [
    statCard('Total products', m.total_products ?? 0, 'Active products'),
    statCard('Total inventory', m.total_inventory ?? 0, 'Units in stock'),
    statCard('Low stock', m.low_stock ?? 0, 'At/below minimum')
  ];

  if (data.role === 'ADMIN_MANAGER') cards.push(statCard('Pending requests', m.pending_requests ?? 0, 'Waiting for review'));
  if (data.role === 'WAREHOUSE') cards.push(statCard('Approved requests', m.approved_requests ?? 0, 'Ready to issue'));
  if (data.role === 'EMPLOYEE') cards.push(statCard('My pending requests', m.my_pending ?? 0, 'Waiting for review'));

  let extra = '';
  if (data.pending_requests) extra += requestsMini(data.pending_requests, 'Pending requests');
  if (data.approved_requests) extra += requestsMini(data.approved_requests, 'Approved requests');
  if (data.recent_requests) extra += requestsMini(data.recent_requests, 'My recent requests');
  if (data.recent_transactions) extra += transactionsMini(data.recent_transactions);

  pageRoot().innerHTML = `
    <section class="hero">
      <div><div class="eyebrow">${escapeHtml(roleLabels[data.role])} PORTAL</div><h2>Operational overview</h2>
      <p>All metrics are loaded from the PHP API and production database. No dashboard data is hard-coded in the frontend.</p></div>
      <div class="actions">${data.role==='EMPLOYEE'?'<a class="btn" href="#/new-request">Create request</a>':''}</div>
    </section>
    <section class="stats-grid">${cards.join('')}</section>
    <section class="grid-2">${extra || '<div class="card"><div class="empty">No recent activity.</div></div>'}</section>`;
}

function requestsMini(rows, title) {
  return `<section class="card"><div class="card-head"><h2>${escapeHtml(title)}</h2><a href="#/requests">View all</a></div>
    <div class="request-list">${rows.length ? rows.map(r => requestCard(r, false)).join('') : '<div class="empty">No requests.</div>'}</div></section>`;
}

function transactionsMini(rows) {
  return `<section class="card"><div class="card-head"><h2>Recent transactions</h2><a href="#/transactions">View all</a></div>
    <div class="table-wrap"><table><thead><tr><th>Reference</th><th>Type</th><th>User</th><th>Time</th></tr></thead><tbody>
    ${rows.map(r=>`<tr><td>${escapeHtml(r.reference_code)}</td><td>${badge(r.type)}</td><td>${escapeHtml(r.created_by_name||'-')}</td><td>${dateTime(r.created_at)}</td></tr>`).join('')}
    </tbody></table></div></section>`;
}

async function pageProducts() {
  const q = routeQuery();
  const status = state.user.role_code === 'ADMIN_MANAGER' ? (q.status || 'ACTIVE') : 'ACTIVE';
  const rows = await api('/api/products' + qs({ q:q.q, status, sort:q.sort||'name', direction:q.direction||'ASC' }));
  const meta = await ensureMeta();

  pageRoot().innerHTML = `
    <section class="card">
      <form class="filters" id="productFilters">
        <input name="q" value="${escapeHtml(q.q||'')}" placeholder="Search SKU or product name">
        ${state.user.role_code==='ADMIN_MANAGER'?`<select name="status"><option value="ACTIVE" ${status==='ACTIVE'?'selected':''}>ACTIVE</option><option value="INACTIVE" ${status==='INACTIVE'?'selected':''}>INACTIVE</option></select>`:''}
        <select name="sort"><option value="name">Name</option><option value="sku" ${q.sort==='sku'?'selected':''}>SKU</option><option value="quantity" ${q.sort==='quantity'?'selected':''}>Quantity</option><option value="minimum" ${q.sort==='minimum'?'selected':''}>Minimum stock</option></select>
        <button class="btn">Search / Sort</button>
        ${state.user.role_code==='ADMIN_MANAGER'?'<button type="button" class="btn btn-primary" id="newProductBtn">Add product</button>':''}
      </form>
      <div class="table-wrap"><table><thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Supplier</th><th>Stock</th><th>Minimum</th><th>Status</th>${state.user.role_code==='ADMIN_MANAGER'?'<th>Actions</th>':''}</tr></thead>
      <tbody>${rows.length ? rows.map(r => `
        <tr><td>${escapeHtml(r.sku)}</td><td><strong>${escapeHtml(r.name)}</strong><br><span class="muted">${escapeHtml(r.unit)}</span></td>
        <td>${escapeHtml(r.category_name)}</td><td>${escapeHtml(r.supplier_name||'-')}</td>
        <td><strong>${r.quantity}</strong></td><td>${r.minimum_stock}</td><td>${r.is_low_stock?badge('LOW'):badge(r.status)}</td>
        ${state.user.role_code==='ADMIN_MANAGER'?`<td><div class="actions"><button class="btn btn-sm" data-edit-product="${r.id}">Edit</button>${r.status==='ACTIVE'?`<button class="btn btn-sm btn-danger" data-delete-product="${r.id}">Deactivate</button>`:`<button class="btn btn-sm btn-success" data-restore-product="${r.id}">Restore</button>`}</div></td>`:''}</tr>`).join('') : `<tr><td colspan="8" class="empty">No matching products.</td></tr>`}
      </tbody></table></div>
    </section>`;

  document.getElementById('productFilters').addEventListener('submit', e => {
    e.preventDefault(); const fd=new FormData(e.currentTarget); navigate('products', Object.fromEntries(fd));
  });

  if (state.user.role_code === 'ADMIN_MANAGER') {
    document.getElementById('newProductBtn').addEventListener('click', () => productModal(null, meta, rows));
    pageRoot().addEventListener('click', async e => {
      const edit=e.target.closest('[data-edit-product]');
      const del=e.target.closest('[data-delete-product]');
      const restore=e.target.closest('[data-restore-product]');
      if(edit) return productModal(rows.find(x=>Number(x.id)===Number(edit.dataset.editProduct)), meta, rows);
      if(del && confirm('Deactivate this product?')) {
        try { await api(`/api/products/${del.dataset.deleteProduct}`,{method:'DELETE'}); toast('Product deactivated.'); await pageProducts(); } catch(err){toast(err.message,'error');}
      }
      if(restore) {
        try { await api(`/api/products/${restore.dataset.restoreProduct}/restore`,{method:'POST'}); toast('Product restored.'); await pageProducts(); } catch(err){toast(err.message,'error');}
      }
    });
  }
}

function productModal(product, meta) {
  const categories=(meta.categories||[]).filter(x=>x.status==='ACTIVE');
  const suppliers=(meta.suppliers||[]).filter(x=>x.status==='ACTIVE');
  const modal=showModal(product?'Edit product':'Add product', `
    <form id="productForm" class="form-grid">
      <label class="field"><span>SKU</span><input name="sku" required maxlength="40" value="${escapeHtml(product?.sku||'')}"></label>
      <label class="field"><span>Name</span><input name="name" required maxlength="160" value="${escapeHtml(product?.name||'')}"></label>
      <label class="field"><span>Category</span><select name="category_id" required><option value="">Select</option>${categories.map(x=>`<option value="${x.id}" ${Number(x.id)===Number(product?.category_id)?'selected':''}>${escapeHtml(x.name)}</option>`).join('')}</select></label>
      <label class="field"><span>Supplier</span><select name="supplier_id"><option value="">None</option>${suppliers.map(x=>`<option value="${x.id}" ${Number(x.id)===Number(product?.supplier_id)?'selected':''}>${escapeHtml(x.name)}</option>`).join('')}</select></label>
      <label class="field"><span>Unit</span><input name="unit" required maxlength="40" value="${escapeHtml(product?.unit||'piece')}"></label>
      <label class="field"><span>Minimum stock</span><input name="minimum_stock" type="number" min="0" required value="${product?.minimum_stock??0}"></label>
      <label class="field"><span>Unit cost</span><input name="unit_cost" type="number" min="0" step="0.01" required value="${product?.unit_cost??0}"></label>
      <div class="span-2 actions"><button class="btn btn-primary" type="submit">Save product</button><button class="btn" type="button" data-close-modal>Cancel</button></div>
    </form>`);
  modal.querySelector('#productForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd=new FormData(e.currentTarget); const body=Object.fromEntries(fd);
    body.category_id=Number(body.category_id); body.supplier_id=body.supplier_id?Number(body.supplier_id):null;
    body.minimum_stock=Number(body.minimum_stock); body.unit_cost=Number(body.unit_cost);
    try {
      await api(product?`/api/products/${product.id}`:'/api/products',{method:product?'PUT':'POST',body});
      toast(product?'Product updated.':'Product created.'); closeModal(); await pageProducts();
    } catch(err){toast(err.message,'error');}
  });
}

async function pageSuppliers() {
  const q=routeQuery();
  const rows=await api('/api/suppliers'+qs({q:q.q,status:q.status||''}));
  pageRoot().innerHTML=`
    <section class="card">
      <form class="filters" id="supplierFilters"><input name="q" value="${escapeHtml(q.q||'')}" placeholder="Search name / email / phone">
      <select name="status"><option value="">All status</option><option value="ACTIVE" ${q.status==='ACTIVE'?'selected':''}>ACTIVE</option><option value="INACTIVE" ${q.status==='INACTIVE'?'selected':''}>INACTIVE</option></select>
      <button class="btn">Search</button><button class="btn btn-primary" type="button" id="newSupplierBtn">Add supplier</button></form>
      <div class="table-wrap"><table><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Status</th><th>Actions</th></tr></thead><tbody>
      ${rows.length?rows.map(r=>`<tr><td><strong>${escapeHtml(r.name)}</strong></td><td>${escapeHtml(r.phone||'-')}</td><td>${escapeHtml(r.email||'-')}</td><td>${escapeHtml(r.address||'-')}</td><td>${badge(r.status)}</td>
      <td><div class="actions"><button class="btn btn-sm" data-edit-supplier="${r.id}">Edit</button>${r.status==='ACTIVE'?`<button class="btn btn-sm btn-danger" data-delete-supplier="${r.id}">Deactivate</button>`:`<button class="btn btn-sm btn-success" data-restore-supplier="${r.id}">Restore</button>`}</div></td></tr>`).join(''):'<tr><td colspan="6" class="empty">No suppliers.</td></tr>'}
      </tbody></table></div></section>`;
  document.getElementById('supplierFilters').addEventListener('submit',e=>{e.preventDefault();navigate('suppliers',Object.fromEntries(new FormData(e.currentTarget)))});
  document.getElementById('newSupplierBtn').addEventListener('click',()=>supplierModal(null));
  pageRoot().addEventListener('click',async e=>{
    const edit=e.target.closest('[data-edit-supplier]'), del=e.target.closest('[data-delete-supplier]'), restore=e.target.closest('[data-restore-supplier]');
    if(edit) return supplierModal(rows.find(x=>Number(x.id)===Number(edit.dataset.editSupplier)));
    if(del && confirm('Deactivate this supplier?')){try{await api(`/api/suppliers/${del.dataset.deleteSupplier}`,{method:'DELETE'});toast('Supplier deactivated.');await pageSuppliers();}catch(err){toast(err.message,'error')}}
    if(restore){try{await api(`/api/suppliers/${restore.dataset.restoreSupplier}/restore`,{method:'POST'});toast('Supplier restored.');await pageSuppliers();}catch(err){toast(err.message,'error')}}
  });
}

function supplierModal(row) {
  const modal=showModal(row?'Edit supplier':'Add supplier',`
    <form id="supplierForm" class="form-grid">
      <label class="field span-2"><span>Name</span><input name="name" required maxlength="160" value="${escapeHtml(row?.name||'')}"></label>
      <label class="field"><span>Phone</span><input name="phone" maxlength="30" value="${escapeHtml(row?.phone||'')}"></label>
      <label class="field"><span>Email</span><input name="email" type="email" maxlength="160" value="${escapeHtml(row?.email||'')}"></label>
      <label class="field span-2"><span>Address</span><textarea name="address" maxlength="255">${escapeHtml(row?.address||'')}</textarea></label>
      <div class="span-2 actions"><button class="btn btn-primary">Save supplier</button><button class="btn" type="button" data-close-modal>Cancel</button></div>
    </form>`);
  modal.querySelector('#supplierForm').addEventListener('submit',async e=>{
    e.preventDefault(); const body=Object.fromEntries(new FormData(e.currentTarget));
    try{await api(row?`/api/suppliers/${row.id}`:'/api/suppliers',{method:row?'PUT':'POST',body});toast(row?'Supplier updated.':'Supplier created.');closeModal();await pageSuppliers();}catch(err){toast(err.message,'error')}
  });
}

async function pageInventory() {
  const q=routeQuery();
  const rows=await api('/api/inventory'+qs({q:q.q,stock_status:q.stock_status,sort:q.sort||'name',direction:q.direction||'ASC'}));
  pageRoot().innerHTML=`
    <section class="card"><form class="filters" id="inventoryFilters">
      <input name="q" value="${escapeHtml(q.q||'')}" placeholder="Search SKU / product">
      <select name="stock_status"><option value="">All stock</option><option value="low" ${q.stock_status==='low'?'selected':''}>Low stock</option><option value="ok" ${q.stock_status==='ok'?'selected':''}>Normal</option></select>
      <select name="sort"><option value="name">Name</option><option value="sku" ${q.sort==='sku'?'selected':''}>SKU</option><option value="quantity" ${q.sort==='quantity'?'selected':''}>Quantity</option><option value="minimum" ${q.sort==='minimum'?'selected':''}>Minimum</option></select>
      <button class="btn">Search / Filter</button></form>
      <div class="table-wrap"><table><thead><tr><th>SKU</th><th>Product</th><th>Category</th><th>Supplier</th><th>Current stock</th><th>Minimum</th><th>Stock status</th></tr></thead><tbody>
      ${rows.length?rows.map(r=>`<tr><td>${escapeHtml(r.sku)}</td><td><strong>${escapeHtml(r.name)}</strong><br><span class="muted">${escapeHtml(r.unit)}</span></td><td>${escapeHtml(r.category_name)}</td><td>${escapeHtml(r.supplier_name||'-')}</td><td><strong>${r.quantity}</strong></td><td>${r.minimum_stock}</td><td>${r.is_low_stock?badge('LOW'):badge('NORMAL')}</td></tr>`).join(''):'<tr><td colspan="7" class="empty">No matching inventory.</td></tr>'}
      </tbody></table></div></section>`;
  document.getElementById('inventoryFilters').addEventListener('submit',e=>{e.preventDefault();navigate('inventory',Object.fromEntries(new FormData(e.currentTarget)))});
}

function requestCard(r, actions = true) {
  let actionHtml='';
  if(actions && state.user.role_code==='EMPLOYEE' && r.status==='PENDING') actionHtml=`<button class="btn btn-sm btn-danger" data-cancel-request="${r.id}">Cancel</button>`;
  if(actions && state.user.role_code==='ADMIN_MANAGER' && r.status==='PENDING') actionHtml=`<button class="btn btn-sm btn-success" data-review-request="${r.id}" data-decision="approve">Approve</button><button class="btn btn-sm btn-danger" data-review-request="${r.id}" data-decision="reject">Reject</button>`;
  if(actions && state.user.role_code==='WAREHOUSE' && r.status==='APPROVED') actionHtml=`<button class="btn btn-sm btn-primary" data-issue-request="${r.id}">Issue request</button>`;
  return `<article class="request-card">
    <div class="request-top"><div><strong>${escapeHtml(r.request_code)}</strong><small>${escapeHtml(r.requester_name||state.user.full_name)} • ${escapeHtml(r.department_name||'-')} • ${dateTime(r.created_at)}</small></div>${badge(r.status)}</div>
    <p><strong>Reason:</strong> ${escapeHtml(r.reason)}</p>
    <ul>${(r.items||[]).map(i=>`<li>${escapeHtml(i.sku)} - ${escapeHtml(i.name)}: <strong>${i.quantity} ${escapeHtml(i.unit)}</strong> <span class="muted">(stock ${i.current_stock})</span></li>`).join('')}</ul>
    ${r.review_note?`<div class="note"><strong>Review note:</strong> ${escapeHtml(r.review_note)}</div>`:''}
    ${actionHtml?`<div class="actions" style="margin-top:12px">${actionHtml}</div>`:''}
  </article>`;
}

async function pageRequests() {
  const q=routeQuery();
  const defaultStatus = state.user.role_code==='WAREHOUSE' ? 'APPROVED' : '';
  const status=q.status ?? defaultStatus;
  const rows=await api('/api/requests'+qs({status}));
  const statuses=state.user.role_code==='WAREHOUSE'?['APPROVED','ISSUED']:['','PENDING','APPROVED','REJECTED','ISSUED','CANCELLED'];
  pageRoot().innerHTML=`
    <section class="card"><form class="filters" id="requestFilters"><select name="status">${statuses.map(s=>`<option value="${s}" ${status===s?'selected':''}>${s||'All status'}</option>`).join('')}</select><button class="btn">Filter</button>
    ${state.user.role_code==='EMPLOYEE'?'<a class="btn btn-primary" href="#/new-request">Create request</a>':''}</form>
    <div class="request-list">${rows.length?rows.map(r=>requestCard(r,true)).join(''):'<div class="empty">No requests in this status.</div>'}</div></section>`;
  document.getElementById('requestFilters').addEventListener('submit',e=>{e.preventDefault();navigate('requests',Object.fromEntries(new FormData(e.currentTarget)))});
  pageRoot().addEventListener('click',async e=>{
    const cancel=e.target.closest('[data-cancel-request]');
    const review=e.target.closest('[data-review-request]');
    const issue=e.target.closest('[data-issue-request]');
    if(cancel && confirm('Cancel this PENDING request?')){
      try{await api(`/api/requests/${cancel.dataset.cancelRequest}/cancel`,{method:'POST'});toast('Request cancelled.');await pageRequests();}catch(err){toast(err.message,'error')}
    }
    if(review){
      const decision=review.dataset.decision; let note='';
      if(decision==='reject'){note=prompt('Rejection reason (required):','')||''; if(!note) return;}
      else note=prompt('Approval note (optional):','')||'';
      try{await api(`/api/requests/${review.dataset.reviewRequest}/review`,{method:'POST',body:{decision,review_note:note}});toast(`Request ${decision}d.`);await pageRequests();}catch(err){toast(err.message,'error')}
    }
    if(issue && confirm('Issue this request now? Inventory will be deducted in one database transaction.')){
      try{const result=await api(`/api/requests/${issue.dataset.issueRequest}/issue`,{method:'POST'});toast(`Issued. Transaction ${result.reference_code}`);await pageRequests();}catch(err){toast(err.message,'error')}
    }
  });
}

async function pageNewRequest() {
  const products=await api('/api/products?status=ACTIVE&sort=name');
  pageRoot().innerHTML=`
    <section class="card"><div class="card-head"><h2>New stationery request</h2><span class="muted">Employee → Manager → Warehouse</span></div>
      <div class="card-body"><form id="newRequestForm" class="form-stack">
        <label class="field"><span>Reason</span><textarea name="reason" maxlength="255" required placeholder="Business reason for this request"></textarea></label>
        <div><strong>Request items</strong><p class="muted">Add one or more active products. Quantity must be greater than zero.</p>${itemRowsHtml(products,'requestItems')}</div>
        <div class="actions"><button class="btn btn-primary">Submit request</button><a class="btn" href="#/requests">Cancel</a></div>
      </form></div></section>`;
  const form=document.getElementById('newRequestForm'); bindItemRows(form,products);
  form.addEventListener('submit',async e=>{
    e.preventDefault(); const fd=new FormData(form);
    try{const result=await api('/api/requests',{method:'POST',body:{reason:fd.get('reason'),items:readItems(form)}});toast(`Request ${result.request_code} submitted.`);navigate('requests');}catch(err){toast(err.message,'error')}
  });
}

async function pageStock() {
  const [products,meta]=await Promise.all([api('/api/products?status=ACTIVE&sort=name'),ensureMeta()]);
  const suppliers=(meta.suppliers||[]).filter(x=>x.status==='ACTIVE');
  const deps=(meta.departments||[]).filter(x=>x.status==='ACTIVE');
  pageRoot().innerHTML=`
    <div class="grid-2">
      <section class="card"><div class="card-head"><h2>Stock In</h2></div><div class="card-body"><form id="stockInForm" class="form-stack">
      <label class="field"><span>Supplier</span><select name="supplier_id"><option value="">Optional</option>${suppliers.map(x=>`<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('')}</select></label>
      <div>${itemRowsHtml(products,'stockInItems')}</div><label class="field"><span>Note</span><textarea name="note" maxlength="255"></textarea></label><button class="btn btn-primary">Confirm Stock In</button></form></div></section>
      <section class="card"><div class="card-head"><h2>Direct Stock Out</h2></div><div class="card-body"><form id="stockOutForm" class="form-stack">
      <label class="field"><span>Destination department</span><select name="department_id" required><option value="">Select</option>${deps.map(x=>`<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('')}</select></label>
      <div>${itemRowsHtml(products,'stockOutItems')}</div><label class="field"><span>Note</span><textarea name="note" maxlength="255"></textarea></label><button class="btn btn-danger">Confirm Stock Out</button></form></div></section>
    </div>`;
  for(const [formId,path] of [['stockInForm','/api/inventory/stock-in'],['stockOutForm','/api/inventory/stock-out']]){
    const form=document.getElementById(formId); bindItemRows(form,products);
    form.addEventListener('submit',async e=>{
      e.preventDefault(); const fd=new FormData(form); const body={items:readItems(form),note:fd.get('note')};
      if(formId==='stockInForm') body.supplier_id=fd.get('supplier_id')?Number(fd.get('supplier_id')):null;
      else body.department_id=Number(fd.get('department_id'));
      try{const result=await api(path,{method:'POST',body});toast(`Transaction ${result.reference_code} completed.`);await pageStock();}catch(err){toast(err.message,'error')}
    });
  }
}

async function pageReports() {
  const q=routeQuery();
  const data=await api('/api/reports'+qs({type:q.type,from:q.from,to:q.to}));
  const summary=data.summary||[];
  const maxCount=Math.max(1,...summary.map(s=>Number(s.transaction_count||0)));
  const chart=summary.length?`<section class="card"><div class="card-head"><h2>Transaction volume</h2><span>Database report</span></div><div class="card-body report-bars">${summary.map(s=>`<div class="report-bar-row"><span>${escapeHtml(s.type)}</span><div class="report-bar-track"><div class="report-bar-fill" style="width:${Math.max(4,Number(s.transaction_count||0)/maxCount*100)}%"></div></div><strong>${s.transaction_count}</strong></div>`).join('')}</div></section>`:'';
  pageRoot().innerHTML=`
    <section class="stats-grid">${summary.map(s=>statCard(s.type,s.transaction_count,'Transactions')).join('')||statCard('Transactions',0,'No data')}</section>
    ${chart}
    <section class="card"><form class="filters" id="reportFilters"><select name="type"><option value="">All types</option>${['IN','OUT','REQUEST_ISSUE'].map(x=>`<option value="${x}" ${q.type===x?'selected':''}>${x}</option>`).join('')}</select>
    <input type="date" name="from" value="${escapeHtml(q.from||'')}"><input type="date" name="to" value="${escapeHtml(q.to||'')}"><button class="btn">Filter report</button></form>
    ${transactionTable(data.rows||[])}</section>`;
  document.getElementById('reportFilters').addEventListener('submit',e=>{e.preventDefault();navigate('reports',Object.fromEntries(new FormData(e.currentTarget)))});
}

async function pageTransactions() {
  const q=routeQuery();
  const rows=await api('/api/transactions'+qs({type:q.type,from:q.from,to:q.to}));
  pageRoot().innerHTML=`<section class="card"><form class="filters" id="txFilters"><select name="type"><option value="">All types</option>${['IN','OUT','REQUEST_ISSUE'].map(x=>`<option value="${x}" ${q.type===x?'selected':''}>${x}</option>`).join('')}</select>
  <input type="date" name="from" value="${escapeHtml(q.from||'')}"><input type="date" name="to" value="${escapeHtml(q.to||'')}"><button class="btn">Filter</button></form>${transactionTable(rows)}</section>`;
  document.getElementById('txFilters').addEventListener('submit',e=>{e.preventDefault();navigate('transactions',Object.fromEntries(new FormData(e.currentTarget)))});
}

function transactionTable(rows) {
  return `<div class="table-wrap"><table><thead><tr><th>Reference</th><th>Type</th><th>Product</th><th>Qty</th><th>Department</th><th>Created by</th><th>Time</th></tr></thead><tbody>
  ${rows.length?rows.map(r=>`<tr><td>${escapeHtml(r.reference_code)}</td><td>${badge(r.type)}</td><td>${escapeHtml(r.sku||'')} - ${escapeHtml(r.product_name||'')}</td><td>${r.quantity??''} ${escapeHtml(r.unit||'')}</td><td>${escapeHtml(r.department_name||'-')}</td><td>${escapeHtml(r.created_by_name||'-')}</td><td>${dateTime(r.created_at)}</td></tr>`).join(''):'<tr><td colspan="7" class="empty">No matching transactions.</td></tr>'}
  </tbody></table></div>`;
}

async function pageUsers() {
  const [rows,meta]=await Promise.all([api('/api/users'),ensureMeta()]);
  const roles=meta.roles||[], deps=(meta.departments||[]).filter(x=>x.status==='ACTIVE');
  pageRoot().innerHTML=`
    <div class="grid-2">
      <section class="card"><div class="card-head"><h2>Create user</h2></div><div class="card-body"><form id="userForm" class="form-stack">
      <label class="field"><span>Full name</span><input name="full_name" required maxlength="120"></label>
      <label class="field"><span>Email</span><input type="email" name="email" required maxlength="160"></label>
      <label class="field"><span>Role</span><select name="role_id" required><option value="">Select</option>${roles.map(x=>`<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('')}</select></label>
      <label class="field"><span>Department</span><select name="department_id"><option value="">Not applicable</option>${deps.map(x=>`<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('')}</select></label>
      <label class="field"><span>Password</span><input type="password" name="password" minlength="8" required></label><button class="btn btn-primary">Create user</button></form></div></section>
      <section class="card"><div class="card-head"><h2>User list</h2><span>${rows.length} users</span></div><div class="table-wrap"><table><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th></th></tr></thead><tbody>
      ${rows.map(r=>`<tr><td>${escapeHtml(r.full_name)}</td><td>${escapeHtml(r.email)}</td><td>${escapeHtml(r.role_name)}</td><td>${escapeHtml(r.department_name||'-')}</td><td>${badge(r.status)}</td><td>${Number(r.id)!==Number(state.user.id)?`<button class="btn btn-sm" data-toggle-user="${r.id}">${r.status==='ACTIVE'?'Lock':'Unlock'}</button>`:''}</td></tr>`).join('')}</tbody></table></div></section>
    </div>`;
  document.getElementById('userForm').addEventListener('submit',async e=>{
    e.preventDefault(); const fd=new FormData(e.currentTarget); const body=Object.fromEntries(fd);
    body.role_id=Number(body.role_id); body.department_id=body.department_id?Number(body.department_id):null;
    try{await api('/api/users',{method:'POST',body});toast('User created.');e.currentTarget.reset();await pageUsers();}catch(err){toast(err.message,'error')}
  });
  pageRoot().addEventListener('click',async e=>{
    const btn=e.target.closest('[data-toggle-user]'); if(!btn) return;
    try{await api(`/api/users/${btn.dataset.toggleUser}/status`,{method:'PATCH'});toast('User status updated.');await pageUsers();}catch(err){toast(err.message,'error')}
  });
}

window.addEventListener('hashchange', () => state.user && renderRoute());

async function boot() {
  if (!getToken()) return renderLogin();
  try {
    state.user=await api('/api/auth/me');
    if (!location.hash) navigate('dashboard');
    await renderRoute();
  } catch {
    setToken(null);
    renderLogin('Your saved session is no longer valid.');
  }
}

boot();
