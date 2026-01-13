// assets/js/history.js - UI แบบเดิม + clearFilter ใช้งานได้

let data = [];
let currentPage = 1;
const rowsPerPage = 10;
let hiddenFrom, hiddenTo, fp;

document.addEventListener('DOMContentLoaded', () => {
  console.log('History JS loaded');
  
  data = window.historyTransactions || [];
  console.log('Transactions loaded:', data.length, 'items');

  initHiddenInputs();
  initFlatpickr();
  initEventListeners();
  render(data); // แสดงข้อมูลเริ่มต้น
  initToast();
});

function initHiddenInputs() {
  hiddenFrom = document.getElementById('filterDateFrom');
  if (!hiddenFrom) {
    hiddenFrom = document.createElement('input');
    hiddenFrom.type = 'hidden';
    hiddenFrom.id = 'filterDateFrom';
    document.body.appendChild(hiddenFrom);
  }

  hiddenTo = document.getElementById('filterDateTo');
  if (!hiddenTo) {
    hiddenTo = document.createElement('input');
    hiddenTo.type = 'hidden';
    hiddenTo.id = 'filterDateTo';
    document.body.appendChild(hiddenTo);
  }
}

function initFlatpickr() {
  fp = flatpickr("#filterDateRange", {
    mode: "range",
    dateFormat: "Y-m-d",
    locale: "th",
    conjunction: " ถึง ",
    allowInput: true,
    clickOpens: true,
    onChange: updateFilter,
    onClose: updateFilter
  });
}

function initEventListeners() {
  // Filter selects
  ['filterItem', 'filterType', 'filterCompany'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', () => {
        currentPage = 1;
        render(getFilteredData());
      });
    }
  });

  // Clear Filter Button
  const clearBtn = document.getElementById('clearFilterBtn');
  if (clearBtn) {
    clearBtn.addEventListener('click', clearFilter);
  }

  // Export Button
  const exportBtn = document.getElementById('exportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', exportCSV);
  }

  // Delete Modal
  const delModal = document.getElementById('delModal');
  if (delModal) {
    delModal.addEventListener('show.bs.modal', e => {
      const btn = e.relatedTarget;
      document.getElementById('delId').value = btn.dataset.id;
      document.getElementById('delInfo').textContent = btn.dataset.info;
    });
  }
}

function clearFilter() {
  console.log('🔄 Clear Filter clicked');

  ['filterItem', 'filterType', 'filterCompany'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });

  const dateRange = document.getElementById('filterDateRange');
  if (dateRange) dateRange.value = '';

  if (hiddenFrom) hiddenFrom.value = '';
  if (hiddenTo) hiddenTo.value = '';
  if (fp) fp.clear();

  currentPage = 1;
  render(data);

  console.log('✅ Filter cleared');
}

function updateFilter() {
  const selected = fp.selectedDates;
  if (selected.length === 2) {
    hiddenFrom.value = selected[0].toISOString().slice(0, 10);
    hiddenTo.value = selected[1].toISOString().slice(0, 10);
  } else {
    hiddenFrom.value = '';
    hiddenTo.value = '';
  }
  currentPage = 1;
  render(getFilteredData());
}

function getFilteredData() {
  let filtered = [...data];

  const itemId = document.getElementById('filterItem')?.value || '';
  const type = document.getElementById('filterType')?.value || '';
  const companyId = document.getElementById('filterCompany')?.value || '';
  const dateFrom = hiddenFrom?.value || '';
  const dateTo = hiddenTo?.value || '';

  if (itemId) filtered = filtered.filter(r => String(r.item_id) === itemId);
  if (type) filtered = filtered.filter(r => r.type === type);
  if (companyId) filtered = filtered.filter(r => String(r.company_id) === companyId);
  if (dateFrom) filtered = filtered.filter(r => r.transaction_date >= dateFrom);
  if (dateTo) filtered = filtered.filter(r => r.transaction_date <= dateTo);

  return filtered;
}

function render(filtered = data) {
  const tbody = document.getElementById('historyBody');
  const noData = document.getElementById('noData');
  const summary = document.getElementById('filterSummary');
  const countEl = document.getElementById('resultCount');
  const detailsEl = document.getElementById('filterDetails');

  if (!tbody) {
    console.error('ไม่พบตาราง #historyBody');
    return;
  }

  tbody.innerHTML = '';

  const totalItems = filtered.length;
  if (countEl) countEl.textContent = totalItems.toLocaleString('th-TH');

  // สรุปตัวกรอง (แบบเดิม)
  let filters = [];
  const itemSelect = document.getElementById('filterItem');
  const typeSelect = document.getElementById('filterType');
  const companySelect = document.getElementById('filterCompany');

  if (itemSelect?.value) {
    filters.push(`อุปกรณ์: <span class="badge bg-primary">${esc(itemSelect.selectedOptions[0]?.text || '')}</span>`);
  }
  if (typeSelect?.value) {
    const typeText = typeSelect.value === 'IN' ? 'เพิ่มสต็อก' : 'เบิก';
    filters.push(`รายการ: <span class="badge bg-info">${typeText}</span>`);
  }
  if (companySelect?.value) {
    filters.push(`บริษัท: <span class="badge bg-secondary">${esc(companySelect.selectedOptions[0]?.text || '')}</span>`);
  }
  if (hiddenFrom?.value && hiddenTo?.value) {
    filters.push(`ช่วงวันที่: <span class="badge bg-success">${formatDate(hiddenFrom.value)} - ${formatDate(hiddenTo.value)}</span>`);
  }

  if (filters.length > 0 && detailsEl && summary) {
    detailsEl.innerHTML = ' (' + filters.join(' | ') + ')';
    summary.classList.remove('d-none');
  } else if (detailsEl && summary) {
    detailsEl.innerHTML = ' (แสดงข้อมูลทั้งหมด)';
    summary.classList.remove('d-none');
  }

  if (totalItems === 0) {
    noData?.classList.remove('d-none');
    summary?.classList.add('d-none');
  } else {
    noData?.classList.add('d-none');
  }

  const totalPages = Math.ceil(totalItems / rowsPerPage);
  if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;

  const start = (currentPage - 1) * rowsPerPage;
  const end = Math.min(start + rowsPerPage, totalItems);
  const pageData = filtered.slice(start, end);

  pageData.forEach(r => {
    const tr = document.createElement('tr');
    const typeBadge = r.type === 'IN'
      ? '<span class="badge badge-in">เพิ่ม</span>'
      : '<span class="badge badge-out">เบิก</span>';

    const emp = r.emp_name
      ? `${esc(r.emp_name)}${r.dept_name ? ' (' + esc(r.dept_name) + ')' : ''}`
      : '-';

    tr.innerHTML = `
      <td>${formatDate(r.transaction_date)}</td>
      <td><strong>${esc(r.item_name || '-')}</strong></td>
      <td>${typeBadge}</td>
      <td class="text-center">${formatNum(r.quantity)}</td>
      <td class="text-center">${formatNum(r.stock)}</td>
      <td>${emp}</td>
      <td>${r.company_name ? esc(r.company_name) : '-'}</td>
      <td>${r.memo ? esc(r.memo) : '-'}</td>
      <td>
        <button class="btn btn-sm btn-outline-danger" 
                data-bs-toggle="modal" data-bs-target="#delModal"
                data-id="${r.id}" 
                data-info="${esc(r.item_name || '-')} - ${formatDate(r.transaction_date)}">
          <i class="bi bi-trash3 me-1"></i>ลบ
        </button>
      </td>`;
    tbody.appendChild(tr);
  });

  renderPagination(totalItems, totalPages);
}

function renderPagination(totalItems, totalPages) {
  const container = document.getElementById('paginationContainer');
  if (!container || totalPages <= 1) return;

  container.innerHTML = '';

  const createPageItem = (text, pageNum, disabled = false, active = false) => {
    const li = document.createElement('li');
    li.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
    const a = document.createElement('a');
    a.className = 'page-link';
    a.href = '#';
    a.textContent = text;
    a.dataset.page = pageNum;
    
    if (!disabled && !active) {
      a.addEventListener('click', e => {
        e.preventDefault();
        currentPage = Number(pageNum);
        render(getFilteredData());
      });
    } else {
      a.addEventListener('click', e => e.preventDefault());
    }
    li.appendChild(a);
    return li;
  };

  container.appendChild(createPageItem('ก่อนหน้า', currentPage - 1, currentPage === 1));
  
  const pageWindow = 2;
  let startPage = Math.max(1, currentPage - pageWindow);
  let endPage = Math.min(totalPages, currentPage + pageWindow);

  if (startPage > 1) {
    container.appendChild(createPageItem('1', 1));
    if (startPage > 2) container.appendChild(createPageItem('...', 0, true));
  }
  
  for (let i = startPage; i <= endPage; i++) {
    container.appendChild(createPageItem(i, i, false, i === currentPage));
  }
  
  if (endPage < totalPages) {
    if (endPage < totalPages - 1) container.appendChild(createPageItem('...', 0, true));
    container.appendChild(createPageItem(totalPages, totalPages));
  }
  
  container.appendChild(createPageItem('ถัดไป', currentPage + 1, currentPage === totalPages));
}

// Utility Functions
function esc(t) {
  const d = document.createElement('div');
  d.textContent = t ?? '-';
  return d.innerHTML;
}

function formatDate(d) {
  if (!d) return '-';
  const [y, m, day] = d.split('-');
  return `${day}/${m}/${y}`;
}

function formatNum(n) {
  return new Intl.NumberFormat('th-TH').format(Number(n) || 0);
}

function exportCSV() {
  const filtered = getFilteredData();
  if (filtered.length === 0) {
    alert('ไม่มีข้อมูลตามตัวกรองที่เลือกเพื่อส่งออก');
    return;
  }

  let csv = '\uFEFFวันที่,ชื่ออุปกรณ์,รายการ,จำนวน,คงเหลือ,ชื่อผู้เบิก/แผนก,บริษัท,หมายเหตุ\n';

  filtered.forEach(r => {
    const typeText = r.type === 'IN' ? 'เพิ่มสต็อก' : 'เบิกออก';
    const emp = r.emp_name ? r.emp_name + (r.dept_name ? ' (' + r.dept_name + ')' : '') : '-';
    csv += [
      formatDate(r.transaction_date),
      r.item_name || '-',
      typeText,
      r.quantity,
      r.stock,
      emp,
      r.company_name || '-',
      r.memo || '-'
    ].join(',') + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  const url = URL.createObjectURL(blob);
  link.setAttribute('href', url);
  link.setAttribute('download', 'ประวัติการเบิก-รับเข้าอุปกรณ์_' + new Date().toISOString().slice(0,10) + '.csv');
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function initToast() {
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    try {
      const d = JSON.parse(toastData);
      const t = document.createElement('div');
      t.className = `toast align-items-center text-bg-${d.type==='error'?'danger':'success'} border-0`;
      t.innerHTML = `<div class="d-flex"><div class="toast-body">${d.message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
      document.querySelector('.toast-container')?.appendChild(t);
      new bootstrap.Toast(t, {delay:4000}).show();
    } catch (e) {
      console.error('Toast parse error:', e);
    }
  }
}