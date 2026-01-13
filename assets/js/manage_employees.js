let deleteModal = null;
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

    // ฟังก์ชันเปิด Modal ลบ (ใช้ร่วมกันทุกอย่าง)
    function openDelete(type, id, name) {
      let typeText = '';
      let warning = '';
      let url = '';

      if (type === 'dept') {
        typeText = 'แผนก';
        warning = 'หากมีพนักงานอยู่ในแผนกนี้ อาจทำให้ข้อมูลพนักงานไม่สมบูรณ์';
        url = `?del_dept=${id}`;
      } else if (type === 'company') {
        typeText = 'บริษัท';
        warning = 'การลบจะไม่กระทบข้อมูลอื่น ๆ';
        url = `?del_company=${id}`;
      } else if (type === 'emp') {
        typeText = 'พนักงาน';
        warning = 'การลบนี้ไม่สามารถกู้คืนได้';
        url = `?delete_emp=${id}`;
      }

      // ตั้งค่าข้อความใน Modal
      document.getElementById('deleteModalLabel').textContent = `ยืนยันการลบ${typeText}`;
      document.getElementById('deleteItemType').textContent = typeText + ':';
      document.getElementById('deleteItemName').textContent = name;

      // ตั้ง URL ลบจริง
      confirmDeleteBtn.href = url;

      // เปิด Modal
      if (!deleteModal) {
        deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
      }
      deleteModal.show();
    }

    // ฟังก์ชันเดิม (เพิ่ม/แก้ไขพนักงาน)
    function openAdd() {
      document.getElementById('empTitle').innerHTML = '<i class="bi bi-person-fill-add"></i> เพิ่มพนักงาน';
      document.getElementById('empTitle').style.color = 'white'; // หรือ '#FFFFFF'
      document.getElementById('empAction').value = 'add';
      document.getElementById('empId').value = '';
      document.querySelector('#empModal input[name="name"]').value = '';
      document.querySelector('#empModal select[name="department_id"]').value = '';
    }

    function openEdit(id, name, dept) {
      document.getElementById('empTitle').innerHTML = '<i class="bi bi-person-fill-gear"></i> แก้ไขพนักงาน';
      document.getElementById('empTitle').style.color = 'white'; // หรือ '#FFFFFF'
      document.getElementById('empAction').value = 'edit';
      document.getElementById('empId').value = id;
      document.querySelector('#empModal input[name="name"]').value = name;
      document.querySelector('#empModal select[name="department_id"]').value = dept || '';
      new bootstrap.Modal('#empModal').show();
    }

    // Toast
    const toastData = document.documentElement.dataset.toast;
    if (toastData) {
      const d = JSON.parse(toastData);
      const t = document.createElement('div');
      t.className = `toast align-items-center text-bg-${d.type === 'error' ? 'danger' : 'success'} border-0`;
      t.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${d.message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
      document.querySelector('.toast-container').appendChild(t);
      new bootstrap.Toast(t, {
        delay: 2000
      }).show();
    }