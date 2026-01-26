let deleteModal = null;
const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");

// ฟังก์ชันเดิม (เพิ่ม/แก้ไขพนักงาน)
function openAdd() {
  document.getElementById("empTitle").innerHTML =
    '<i class="bi bi-person-fill-add"></i> เพิ่มพนักงาน';
  document.getElementById("empTitle").style.color = "white"; // หรือ '#FFFFFF'
  document.getElementById("empAction").value = "add";
  document.getElementById("empId").value = "";
  document.querySelector('#empModal input[name="name"]').value = "";
  document.querySelector('#empModal select[name="department_id"]').value = "";
}

function openEdit(id, name, dept) {
  document.getElementById("empTitle").innerHTML =
    '<i class="bi bi-person-fill-gear"></i> แก้ไขพนักงาน';
  document.getElementById("empTitle").style.color = "white"; // หรือ '#FFFFFF'
  document.getElementById("empAction").value = "edit";
  document.getElementById("empId").value = id;
  document.querySelector('#empModal input[name="name"]').value = name;
  document.querySelector('#empModal select[name="department_id"]').value =
    dept || "";
  new bootstrap.Modal("#empModal").show();
}

// Toast
document.addEventListener("DOMContentLoaded", function () {
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    const d = JSON.parse(toastData);
    const t = document.createElement("div");
    t.className = `toast align-items-center text-bg-${d.type === "error" ? "danger" : "success"} border-0`;
    t.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${d.message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>`;
    document.querySelector(".toast-container").appendChild(t);
    new bootstrap.Toast(t, {
      delay: 2000,
    }).show();
  }
});

// จัดการปุ่มลบทั้งหมดด้วย SweetAlert2 (ใช้ data attributes)
document.addEventListener("click", function (e) {
  const btn = e.target.closest(".delete-btn");
  if (!btn) return;

  e.preventDefault();

  const type = btn.dataset.type;
  const id = btn.dataset.id;
  const name = btn.dataset.name;

  if (!type || !id || !name) {
    console.error("Missing delete data attributes");
    return;
  }

  let typeText = "";
  let warningText = "";

  if (type === "dept") {
    typeText = "แผนก";
    warningText = "หากมีพนักงานอยู่ในแผนกนี้ อาจทำให้ข้อมูลพนักงานไม่สมบูรณ์";
  } else if (type === "company") {
    typeText = "บริษัท";
    warningText = "การลบจะไม่กระทบข้อมูลอื่น ๆ";
  } else if (type === "emp") {
    typeText = "พนักงาน";
    warningText = "การลบนี้ไม่สามารถกู้คืนได้";
  } else {
    console.error("Unknown delete type:", type);
    return;
  }

  Swal.fire({
    title: `ยืนยันการลบ${typeText}`,
    html: `
            คุณต้องการลบ <strong>${name}</strong> หรือไม่?<br>
            <small class="text-muted">${warningText}</small>
        `,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#dc3545",
    cancelButtonColor: "#6c757d",
    confirmButtonText: '<i class="bi bi-trash-fill me-2"></i> ลบถาวร',
    cancelButtonText: '<i class="bi bi-x-circle me-1"></i> ยกเลิก',
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement("form");
      form.method = "POST";
      form.action = "manage_employees.php";

      // CSRF
      const csrf = document.createElement("input");
      csrf.type = "hidden";
      csrf.name = "csrf_token";
      csrf.value = window.csrfToken || "";
      form.appendChild(csrf);

      // ข้อมูลลบ
      const typeInput = document.createElement("input");
      typeInput.type = "hidden";
      typeInput.name = "delete_type";
      typeInput.value = type;
      form.appendChild(typeInput);

      const idInput = document.createElement("input");
      idInput.type = "hidden";
      idInput.name = "delete_id";
      idInput.value = id;
      form.appendChild(idInput);

      document.body.appendChild(form);

      // debug: ดูว่าสร้าง form ได้ไหม
      console.log("Submitting delete form:", {
        type: type,
        id: id,
        csrf: window.csrfToken ? "มีค่า" : "ไม่มีค่า",
      });

      form.addEventListener("submit", function () {
        setTimeout(() => window.location.reload(true), 500);
      });
      form.submit();
    }
  });
});
