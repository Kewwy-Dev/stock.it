// assets/js/index.js

let items = [];
let employees = [];

document.addEventListener('DOMContentLoaded', () => {
  // รับข้อมูลจาก PHP
  items     = window.stockItems    || [];
  employees = window.stockEmployees || [];

  console.log('Index page loaded - จำนวนอุปกรณ์:', items.length);

  const grid      = document.getElementById('itemGrid');
  const filter    = document.getElementById('itemFilter');
  const noResults = document.getElementById('noResults');

  // ฟังก์ชัน render การ์ดอุปกรณ์ (เหมือนเดิม)
  function render(data) {
    grid.innerHTML = '';
    if (!data.length) {
      noResults.classList.remove('d-none');
      return;
    }
    noResults.classList.add('d-none');

    data.forEach(i => {
      const col = document.createElement('div');
      col.className = 'col-md-6 col-lg-3';
      col.innerHTML = `
        <div class="item-card position-relative">
          <button class="close-btn" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                  data-id="${i.id}" data-name="${esc(i.name)}">
            <i class="bi bi-trash3-fill"></i>
          </button>
          
          <!-- ดาวปักหมุด -->
          <button class="btn-favorite position-absolute top-0 start-0 m-2 p-1 rounded-circle border-0"
                  data-id="${i.id}" data-favorite="${i.is_favorite}" title="ปักดาวอุปกรณ์นี้">
            <i class="bi bi-star${i.is_favorite ? '-fill text-warning' : ' text-secondary'} fs-4"></i>
          </button>

          ${i.image 
            ? `<img src="uploads/${i.image}" class="item-img w-100" alt="${esc(i.name)}">` 
            : `<div class="item-img-placeholder"><i class="bi bi-box"></i></div>`}
          
          <div class="p-3">
            <h5 class="card-title">${esc(i.name)}</h5>
            <p class="text-success fw-bold">คงเหลือ: ${i.stock} ชิ้น</p>
            <div class="d-flex gap-2">
              <button class="btn btn-success flex-fill" 
                      onclick="openTrans(${i.id},'IN','${esc(i.name)}')">+ เพิ่ม</button>
              <button class="btn btn-danger flex-fill" 
                      onclick="openTrans(${i.id},'OUT','${esc(i.name)}')">- เบิก</button>
            </div>
          </div>
        </div>`;
      grid.appendChild(col);
    });
  }

  // ฟังก์ชัน escape HTML
  function esc(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
  }

  // ตัวกรองอุปกรณ์
  if (filter) {
    filter.addEventListener('change', () => {
      const val = filter.value;
      let filtered;

      if (val === 'low') {
        filtered = items.filter(x => x.stock < 5 && x.stock > 0);
      } else if (val === 'zero') {
        filtered = items.filter(x => x.stock === 0);
      } else if (val) {
        filtered = items.filter(x => String(x.id) === val);
      } else {
        filtered = [...items];
      }

      render(filtered);
    });
  }

  // Preview รูปภาพเมื่อเลือกไฟล์
  const imageInput = document.querySelector('input[name="image"]');
  if (imageInput) {
    imageInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      const preview = document.getElementById('imagePreview');
      if (!preview) return;

      preview.innerHTML = '';
      if (file) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        img.className = 'img-fluid mt-2 rounded';
        img.style.maxHeight = '200px';
        preview.appendChild(img);
      }
    });
  }

  // เปิด modal เพิ่มอุปกรณ์
  window.openAddModal = function() {
    document.getElementById('modalTitle').textContent = 'เพิ่มอุปกรณ์';
    const form = document.querySelector('#addItemModal form');
    if (form) form.reset();
    
    const preview = document.getElementById('imagePreview');
    if (preview) preview.innerHTML = '';

    document.getElementById('itemId').value = '';
    document.getElementById('oldImage').value = '';

    new bootstrap.Modal('#addItemModal').show();
  };

  // เปิด modal เพิ่ม/เบิก
  window.openTrans = function(id, type, itemName) {
    document.getElementById('transTitle').textContent = 
      (type === 'IN' ? 'เพิ่มสต็อก' : 'เบิกใช้') + ' - ' + itemName;

    document.getElementById('item_id').value = id;
    document.getElementById('type').value = type;
    
    const outSection = document.getElementById('outSection');
    outSection.style.display = type === 'OUT' ? 'block' : 'none';

    const empSelect = document.getElementById('employeeSelect');
    empSelect.disabled = true;
    empSelect.innerHTML = '<option value="">— เลือกผู้เบิก —</option>';

    document.getElementById('departmentSelect').value = '';

    new bootstrap.Modal('#transactionModal').show();
  };

  // เมื่อเลือกแผนก → โหลดรายชื่อพนักงาน
  const deptSelect = document.getElementById('departmentSelect');
  if (deptSelect) {
    deptSelect.addEventListener('change', function() {
      const deptId = this.value;
      document.getElementById('department_id').value = deptId;

      const empSelect = document.getElementById('employeeSelect');
      empSelect.innerHTML = '<option value="">— เลือกผู้เบิก —</option>';

      const filteredEmps = employees.filter(e => String(e.department_id) === deptId);
      
      filteredEmps.forEach(e => {
        const opt = document.createElement('option');
        opt.value = e.id;
        opt.textContent = e.name;
        empSelect.appendChild(opt);
      });

      empSelect.disabled = false;
    });
  }

  // Modal ลบอุปกรณ์
  const deleteModal = document.getElementById('deleteModal');
  if (deleteModal) {
    deleteModal.addEventListener('show.bs.modal', e => {
      const btn = e.relatedTarget;
      document.getElementById('deleteName').textContent = btn.dataset.name;
      document.getElementById('delete-id').value = btn.dataset.id;
    });
  }

  // Toast Notification
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    try {
      const d = JSON.parse(toastData);
      const t = document.createElement('div');
      t.className = `toast align-items-center text-bg-${d.type === 'error' ? 'danger' : 'success'} border-0`;
      t.innerHTML = `
        <div class="d-flex">
          <div class="toast-body">${d.message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
      document.querySelector('.toast-container')?.appendChild(t);
      new bootstrap.Toast(t, { delay: 4000 }).show();
    } catch (e) {
      console.error('Toast parse error:', e);
    }
  }

  // Toggle Favorite (ใช้ event delegation)
  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('.btn-favorite');
    if (!btn) return;

    e.preventDefault();

    const itemId = btn.dataset.id;
    const current = parseInt(btn.dataset.favorite);
    const newFav = current === 1 ? 0 : 1;

    try {
      const response = await fetch('toggle_favorite.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${itemId}&is_favorite=${newFav}&csrf_token=${window.csrfToken}`
      });

      if (!response.ok) throw new Error('อัพเดทไม่สำเร็จ');

      // อัพเดท UI ปุ่ม
      const icon = btn.querySelector('i');
      if (newFav === 1) {
        icon.classList.remove('bi-star', 'text-secondary');
        icon.classList.add('bi-star-fill', 'text-warning');
      } else {
        icon.classList.remove('bi-star-fill', 'text-warning');
        icon.classList.add('bi-star', 'text-secondary');
      }
      btn.dataset.favorite = newFav;

      // อัพเดท array และ render ใหม่
      items = items.map(item => 
        item.id == itemId ? { ...item, is_favorite: newFav } : item
      );

      // เรียงลำดับใหม่: favorite ก่อน + ชื่อตามตัวอักษร
      items.sort((a, b) => {
        if (a.is_favorite !== b.is_favorite) return b.is_favorite - a.is_favorite;
        return a.name.localeCompare(b.name);
      });

      render(items);

    } catch (err) {
      console.error(err);
      alert('เกิดข้อผิดพลาดในการปักดาว กรุณาลองใหม่');
    }
  });

  // Render ครั้งแรก
  render(items);
});