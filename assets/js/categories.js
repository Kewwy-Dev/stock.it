// assets/js/categories.js

let allCategories = [...categories]; // copy จาก window.categories ที่ส่งมาจาก PHP

function renderCategories() {
  const tbody = document.getElementById("categoriesTableBody");
  const noCatDiv = document.getElementById("noCategories");
  tbody.innerHTML = "";

  if (allCategories.length === 0) {
    noCatDiv.classList.remove("d-none");
    return;
  }

  noCatDiv.classList.add("d-none");

  allCategories.forEach((cat) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td class="ps-3">${cat.name}</td>
      <td class="text-center">${cat.item_count || 0}</td>
      <td class="text-end pe-3 d-flex">
        <button class="btn btn-sm btn-outline-primary me-1 rounded-pill px-2"
          onclick="openCategoryForm('edit', ${cat.id}, '${cat.name.replace(/'/g, "\\'")}')">
          <i class="bi bi-pencil"></i> แก้ไข
        </button>
        <button class="btn btn-sm btn-outline-danger rounded-pill px-3"
          onclick="confirmDeleteCategory(${cat.id}, '${cat.name.replace(/'/g, "\\'")}')">
          <i class="bi bi-trash"></i> ลบ
        </button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function openCategoryForm(mode, id = 0, name = "") {
  const modal = new bootstrap.Modal(
    document.getElementById("categoryFormModal"),
  );
  const title = document.getElementById("categoryFormTitle");
  const nameInput = document.getElementById("catFormName");
  const idInput = document.getElementById("catFormId");

  idInput.value = id;
  nameInput.value = name;
  title.innerHTML =
    mode === "add"
      ? '<i class="bi bi-tag me-2"></i>เพิ่มหมวดหมู่ใหม่'
      : '<i class="bi bi-pencil-square me-2"></i>แก้ไขหมวดหมู่';

  modal.show();
}

async function saveCategory() {
  const id = document.getElementById("catFormId").value;
  const name = document.getElementById("catFormName").value.trim();

  if (!name) {
    alert("กรุณากรอกชื่อหมวดหมู่");
    return;
  }

  try {
    const res = await fetch("save_categories.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `csrf_token=${encodeURIComponent(window.csrfToken)}&id=${id}&name=${encodeURIComponent(name)}`,
    });

    const data = await res.json();

    if (data.success) {
      if (data.new_id) {
        // เพิ่มใหม่
        allCategories.push({ id: data.new_id, name, item_count: 0 });
      } else {
        // แก้ไข
        const cat = allCategories.find((c) => c.id == id);
        if (cat) cat.name = name;
      }

      renderCategories();
      bootstrap.Modal.getInstance(
        document.getElementById("categoryFormModal"),
      ).hide();
      showToast(data.message, "success");
    } else {
      showToast(data.message, "danger");
    }
  } catch (err) {
    showToast("เกิดข้อผิดพลาดในการเชื่อมต่อ", "danger");
  }
}

// ฟังก์ชันยืนยันการลบด้วย SweetAlert2
function confirmDeleteCategory(id, name) {
  Swal.fire({
    title: "ยืนยันการลบหมวดหมู่",
    html: `คุณแน่ใจหรือไม่ว่าต้องการลบ<br><strong>"${name}"</strong> ?<br>
           <small class="text-danger">การกระทำนี้ไม่สามารถกู้คืนได้</small>`,
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#d33",
    cancelButtonColor: "#3085d6",
    confirmButtonText: '<i class="bi bi-trash me-1"></i> ลบถาวร',
    cancelButtonText: "ยกเลิก",
    reverseButtons: true,
  }).then((result) => {
    if (result.isConfirmed) {
      deleteCategory(id);
    }
  });
}

async function deleteCategory(id) {
  try {
    const res = await fetch("delete_category.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `csrf_token=${encodeURIComponent(window.csrfToken)}&id=${id}`,
    });

    const data = await res.json();

    if (data.success) {
      allCategories = allCategories.filter((c) => c.id != id);
      renderCategories();
      showToast("ลบหมวดหมู่สำเร็จ", "success");
    } else {
      showToast(data.message || "ไม่สามารถลบได้", "error");
    }
  } catch (err) {
    showToast("เกิดข้อผิดพลาด", "error");
  }
}

// เริ่มต้น
document.addEventListener("DOMContentLoaded", () => {
  renderCategories();

  document
    .getElementById("saveCategoryBtn")
    ?.addEventListener("click", saveCategory);
});
