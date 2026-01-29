<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}
if ($_SESSION['user_role'] !== 'admin') {
  header('Location: newuser.php');
  exit;
}

// ใน index.php (ส่วนบน)

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$items = $pdo->query("
  SELECT 
    i.id, i.name, i.image, i.stock, i.is_favorite,
    i.category_id,
    c.name AS category_name
  FROM items i
  LEFT JOIN categories c ON i.category_id = c.id
  ORDER BY i.is_favorite DESC, i.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$json_items = json_encode($items, JSON_UNESCAPED_UNICODE);
$categories = $pdo->query("
    SELECT 
        c.id, 
        c.name, 
        COUNT(i.id) AS item_count 
    FROM categories c 
    LEFT JOIN items i ON c.id = i.category_id 
    GROUP BY c.id 
    ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// $items = $pdo->query("SELECT id, name, image, stock, is_favorite FROM items ORDER BY is_favorite DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
// $json_items = json_encode($items, JSON_UNESCAPED_UNICODE);

$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$companies   = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$employees   = $pdo->query("SELECT e.id, e.name, e.department_id, d.name AS dept_name 
                           FROM employees e LEFT JOIN departments d ON e.department_id = d.id 
                           ORDER BY d.name, e.name")->fetchAll();
$json_employees = json_encode($employees, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="th" <?php if (isset($_SESSION['toast'])): ?>data-toast='<?= json_encode($_SESSION['toast']) ?>' <?php unset($_SESSION['toast']);
                                                                                                          endif; ?>>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stock-IT</title>
  <link rel="icon" type="image/png" href="uploads/Stock-IT.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset_url('assets/css/index.css') ?>">
  <link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/light.css" />
  <style>

  </style>
</head>

<body class="bg-light">
  <?php include 'navbar.php'; ?>

  <div class="container mt-3 mt-md-4 mt-lg-5">
    <!-- Header Section: Filters + Title -->
    <div class="mb-3 mb-md-4">
      <!-- Mobile: filters ซ้อนกัน -->
      <div class="d-block d-md-none">
        <div class="d-flex flex-column gap-2 mb-3">
          <!-- Category -->
          <div class="dropdown">
            <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
              type="button" id="categoryDropdown" data-bs-toggle="dropdown">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-grid-fill text-primary"></i>
                <span id="categoryLabel">หมวดหมู่อุปกรณ์</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <!-- Dropdown-menu หมวดหมู่ -->
            <ul class="dropdown-menu shadow animate-slide" aria-labelledby="categoryDropdown">
              <li><a class="dropdown-item category-select" href="#" data-value="">— ทุกหมวดหมู่ —</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <?php foreach ($categories as $cat): ?>
                <li><a class="dropdown-item category-select" href="#" data-value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="dropdown">
            <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
              type="button" id="itemDropdown" data-bs-toggle="dropdown">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-search text-primary"></i>
                <span id="itemFilterLabel">ค้นหาอุปกรณ์</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <!-- Dropdown-menu อุปกรณ์ -->
            <ul class="dropdown-menu dropdown-menu-end shadow animate-slide" aria-labelledby="itemDropdown">
              <li><a class="dropdown-item item-filter-select" href="#" data-value="">— ทุกอุปกรณ์ —</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>

              <div style="overflow-x: hidden; overflow-y: auto; max-height: 45vh;">
                <?php foreach ($items as $i): ?>
                  <li><a class="dropdown-item item-filter-select" href="#" data-value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?></a></li>
                <?php endforeach; ?>
              </div>

              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item item-filter-select text-warning fw-medium" href="#" data-value="low">⚠️ อุปกรณ์เหลือน้อย</a></li>
              <li><a class="dropdown-item item-filter-select text-danger fw-medium" href="#" data-value="zero">🚫 อุปกรณ์หมด</a></li>
            </ul>
            <input type="hidden" id="itemFilterValue" value="">
          </div>
        </div>
        <h2 class="text-center mb-0 fs-4 fw-bold text-primary">
          <i class="bi bi-box-seam me-2"></i>รายการอุปกรณ์ IT
        </h2>
      </div>

      <!-- Tablet & Desktop: filters + title ใน row เดียว -->
      <div class="row align-items-center g-3 d-none d-md-flex">
        <div class="col-md-4 col-lg-3">
          <div class="dropdown">
            <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
              type="button" id="categoryDropdown" data-bs-toggle="dropdown">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-grid-fill text-primary"></i>
                <span id="categoryLabel">หมวดหมู่อุปกรณ์</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <!-- dropdown-menu หมวดหมู่ -->
            <ul class="dropdown-menu shadow animate-slide" aria-labelledby="categoryDropdown">
              <li><a class="dropdown-item category-select" href="#" data-value="">— ทุกหมวดหมู่ —</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <?php foreach ($categories as $cat): ?>
                <li><a class="dropdown-item category-select" href="#" data-value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <!-- Title Ipad/Mobile -->
        <div class="col-md-4 col-lg-6 text-center">
          <h2 class="mb-0 fs-3 fs-lg-2 fw-bold text-primary">
            <i class="bi bi-box-seam me-2"></i>รายการอุปกรณ์ IT
          </h2>
        </div>

        <div class="col-md-4 col-lg-3">
          <div class="dropdown">
            <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
              type="button" id="itemDropdown" data-bs-toggle="dropdown">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-search text-primary"></i>
                <span id="itemFilterLabel">ค้นหาอุปกรณ์</span>
              </div>
              <i class="bi bi-chevron-down small text-muted"></i>
            </button>
            <!-- dropdown-menu อุปกรณ์ -->
            <ul class="dropdown-menu dropdown-menu-end shadow animate-slide" aria-labelledby="itemDropdown">
              <li><a class="dropdown-item item-filter-select" href="#" data-value="">— ทุกอุปกรณ์ —</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>

              <div style="overflow-x: hidden; overflow-y: auto; max-height: 45vh;">
                <?php foreach ($items as $i): ?>
                  <li><a class="dropdown-item item-filter-select" href="#" data-value="<?= $i['id'] ?>"><?= htmlspecialchars($i['name']) ?></a></li>
                <?php endforeach; ?>
              </div>

              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item item-filter-select text-warning fw-medium" href="#" data-value="low">⚠️ อุปกรณ์เหลือน้อย</a></li>
              <li><a class="dropdown-item item-filter-select text-danger fw-medium" href="#" data-value="zero">🚫 อุปกรณ์หมด</a></li>
            </ul>
            <input type="hidden" id="itemFilterValue" value="">
          </div>
        </div>
      </div>
    </div>

    <!-- Item Grid (ปรับ class ใน JS เป็น col-6 col-md-6 col-lg-3 เพื่อ 2 col บน mobile/tablet, 4 col บน desktop) -->
    <div id="itemGrid" class="row g-3 g-md-4"></div>

    <div id="noResults" class="text-center py-5 d-none">
      <i class="bi bi-search fs-1 text-muted"></i>
      <p class="mt-3 text-muted">ไม่พบรายการ</p>
    </div>
  </div>

  <div id="itemGrid" class="row g-3 g-sm-4 g-md-3 g-lg-4"></div>
  <div id="noResults" class="text-center mt-5 d-none">
    <i class="bi bi-search fs-1 text-muted"></i>
    <p class="mt-3 fs-5 text-muted">ไม่พบรายการอุปกรณ์ที่ค้นหา</p>
  </div>
  </div>

  <!-- ปุ่มเพิ่มอุปกรณ์ -->
  <button class="additem_button fab" data-bs-toggle="toolip" title="เพิ่มอุปกรณ์" onclick="openAddModal()"><i class="bi bi-boxes fs-4"></i></button>
  <!-- ปุ่มเปิด modal จัดการหมวดหมู่ -->
  <button id="#myButton" class="categories_button ctg btn btn-outline-primary mb-3 d-flex align-items-center gap-2"
    data-bs-toggle="modal" data-bs-target="#manageCategoriesModal" data-bs-toggle="toolip" title="จัดการหมวดหมู่">
    <i class="bi bi-grid-fill fs-4"></i>
  </button>

  <!-- ============================================= -->
  <!-- Modal หลัก : จัดการหมวดหมู่                  -->
  <!-- ============================================= -->
  <div class="modal fade manage-categories-modal" id="manageCategoriesModal" tabindex="-1" aria-labelledby="manageCategoriesLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
      <div class="modal-content border-0 shadow-lg rounded-4">
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title d-flex align-items-center gap-2" id="manageCategoriesLabel">
            <i class="bi bi-grid-fill"></i> จัดการหมวดหมู่อุปกรณ์
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body p-4">
          <!-- ปุ่มเพิ่มหมวดหมู่ -->
          <div class="d-flex justify-content-end mb-4">
            <button type="button" class="btn btn-primary rounded-pill px-4 d-flex align-items-center gap-2 shadow-sm"
              onclick="openCategoryForm('add', 0, '')">
              <i class="bi bi-plus-lg"></i> เพิ่มหมวดหมู่
            </button>
          </div>

          <!-- ตารางหมวดหมู่ (responsive) -->
          <div class="table-responsive rounded-3 border bg-white">
            <table class="table table-hover align-middle mb-0" id="categoriesTable">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">ชื่อหมวดหมู่</th>
                  <th class="text-center" style="width:140px;">จำนวนอุปกรณ์</th>
                  <th class="text-center pe-3" style="width:160px;">จัดการ</th>
                </tr>
              </thead>
              <tbody id="categoriesTableBody"></tbody>
            </table>
          </div>

          <!-- ถ้าไม่มีข้อมูล -->
          <div id="noCategories" class="text-center py-5 text-muted d-none">
            <i class="bi bi-tags display-4 opacity-50"></i>
            <p class="mt-3">ยังไม่มีหมวดหมู่อุปกรณ์</p>
          </div>
        </div>

        <!-- <div class="modal-footer bg-light border-0 rounded-bottom-4">
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">ปิด</button>
        </div> -->
      </div>
    </div>
  </div>

  <!-- ============================================= -->
  <!-- Modal ย่อย : เพิ่ม / แก้ไข หมวดหมู่           -->
  <!-- ขนาดเล็กลงมาก → modal-dialog-centered         -->
  <!-- ============================================= -->
  <div class="modal fade" id="categoryFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-4 border-0 shadow">
        <div class="modal-header bg-primary text-white rounded-top-4">
          <h5 class="modal-title" id="categoryFormTitle">
            <i class="bi bi-tag me-2"></i><span>เพิ่มหมวดหมู่ใหม่</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body px-4 py-4">
          <input type="hidden" id="catFormId" value="0">
          <div class="mb-3">
            <label class="form-label fw-medium">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
            <input type="text" class="form-control form-control-lg rounded-3" id="catFormName"
              placeholder="เช่น เครื่องสำรองไฟ, สาย LAN, ..." required autofocus>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 rounded-bottom-4 px-4 pb-4">
          <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">ยกเลิก</button>
          <button type="button" class="btn btn-primary px-4 rounded" id="saveCategoryBtn">
            <i class="bi bi-check-lg me-1"></i> บันทึก
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal เพิ่ม/แก้ไขอุปกรณ์ -->
  <div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalTitle">เพิ่มอุปกรณ์</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="itemForm" action="save_item.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="itemId" name="id">
            <input type="hidden" id="oldImage" name="old_image">

            <div class="mb-3">
              <label class="form-label">ชื่ออุปกรณ์ <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="name" required>
            </div>

            <div class="mb-3">
              <label class="form-label">หมวดหมู่</label>
              <div class="dropdown">
                <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                  type="button" id="itemCategoryDropdown" data-bs-toggle="dropdown">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-grid-fill text-primary"></i>
                    <span id="itemCategoryLabel">— ไม่ระบุ —</span>
                  </div>
                  <i class="bi bi-chevron-down small text-muted"></i>
                </button>
                <ul class="dropdown-menu shadow animate-slide w-100" aria-labelledby="itemCategoryDropdown">
                  <li><a class="dropdown-item category-select-modal" href="#" data-value="">— ไม่ระบุ —</a></li>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                  <?php foreach ($categories as $cat): ?>
                    <li><a class="dropdown-item category-select-modal" href="#" data-value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                  <?php endforeach; ?>
                </ul>
                <input type="hidden" name="category_id" id="itemCategoryValue" value="">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">รูปภาพ (≤5MB)</label>
              <input type="file" class="form-control" name="image" id="imageInput" accept="image/*">
              <div id="imagePreview" class="mt-2 text-center"></div>
            </div>

            <!-- Checkbox ลบพื้นหลัง -->
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="removeBgCheckbox" name="remove_bg" value="1">
              <label class="form-check-label" for="removeBgCheckbox">
                ลบพื้นหลังอัตโนมัติ (ฟรี 50 ครั้ง/เดือน)
              </label>
            </div>

            <div class="mb-3">
              <label class="form-label">จำนวน</label>
              <input type="number" class="form-control" name="stock" min="0" value="0">
            </div>

            <div class="text-end">
              <button type="button" class="btn btn-secondary me-1" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-primary" id="submitItemBtn">
                <i class="bi bi-floppy me-1"></i>บันทึก
              </button>
            </div>
            <button type="button" class="btn btn-secondary ms-2" id="processingBtn" style="display:none;" disabled>
              <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
              กำลังลบพื้นหลัง...
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal เพิ่ม/เบิก -->
  <div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="transTitle">เพิ่มสต็อก</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form action="save_transaction.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="item_id" name="item_id">
            <input type="hidden" id="type" name="type">
            <div class="mb-3">
              <label class="form-label text-danger">จำนวน *</label>
              <input type="number" class="form-control" name="quantity" min="1" required>
            </div>
            <div class="mb-3">
              <label class="form-label">วันที่</label>
              <input type="date" class="form-control" name="transaction_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">หมายเหตุ</label>
              <textarea class="form-control" name="memo" rows="2"></textarea>
            </div>
            <div id="outSection" style="display:none;">
              <div class="mb-3">
                <label class="form-label">บริษัท</label>
                <div class="dropdown">
                  <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                    type="button" id="companyDropdown" data-bs-toggle="dropdown">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-building text-primary"></i>
                      <span id="companyLabel">— ไม่ระบุ —</span>
                    </div>
                    <i class="bi bi-chevron-down small text-muted"></i>
                  </button>
                  <ul class="dropdown-menu shadow animate-slide w-100" aria-labelledby="companyDropdown">
                    <li><a class="dropdown-item company-select-modal" href="#" data-value="">— ไม่ระบุ —</a></li>
                    <li>
                      <hr class="dropdown-divider">
                    </li>
                    <?php foreach ($companies as $c): ?>
                      <li><a class="dropdown-item company-select-modal" href="#" data-value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                  <input type="hidden" id="company_id" name="company_id" value="">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">แผนก</label>
                <div class="dropdown">
                  <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                    type="button" id="departmentDropdown" data-bs-toggle="dropdown">
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-diagram-3-fill text-primary"></i>
                      <span id="departmentLabel">— เลือกแผนก —</span>
                    </div>
                    <i class="bi bi-chevron-down small text-muted"></i>
                  </button>
                  <ul class="dropdown-menu shadow animate-slide w-100" aria-labelledby="departmentDropdown">
                    <li><a class="dropdown-item department-select-modal" href="#" data-value="">— เลือกแผนก —</a></li>
                    <li>
                      <hr class="dropdown-divider">
                    </li>
                    <?php foreach ($departments as $d): ?>
                      <li><a class="dropdown-item department-select-modal" href="#" data-value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></a></li>
                    <?php endforeach; ?>
                  </ul>
                  <input type="hidden" id="department_id" name="department_id" value="">
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">ผู้เบิก</label>
                <div class="dropdown">
                  <button class="btn btn-white border d-flex align-items-center justify-content-between gap-2 custom-filter-btn w-100"
                    type="button" id="employeeDropdown" data-bs-toggle="dropdown" disabled>
                    <div class="d-flex align-items-center gap-2">
                      <i class="bi bi-person-badge-fill text-primary"></i>
                      <span id="employeeLabel">— เลือกผู้เบิก —</span>
                    </div>
                    <i class="bi bi-chevron-down small text-muted"></i>
                  </button>
                  <ul class="dropdown-menu shadow animate-slide w-100" aria-labelledby="employeeDropdown" id="employeeMenu">
                    <li><span class="dropdown-item text-muted">— เลือกแผนกก่อน —</span></li>
                  </ul>
                  <input type="hidden" id="employee_id" name="employee_id" value="">
                </div>
              </div>
            </div>
            <div class="text-end">
              <button type="button" class="btn btn-secondary me-1" data-bs-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>บันทึก</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- CDN -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/@popperjs/core@2"></script>
  <script src="https://unpkg.com/tippy.js@6"></script>

  <!-- ส่งข้อมูลจาก PHP ไปยัง JS -->
  <script>
    window.stockItems = <?= $json_items ?>;
    window.stockEmployees = <?= $json_employees ?>;
    window.csrfToken = "<?= $_SESSION['csrf_token'] ?>";
    let categories = <?= json_encode($categories ?? [], JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <!-- โหลดไฟล์ JS แยก -->
  <script src="<?= asset_url('assets/js/toast.js') ?>"></script>
  <script src="<?= asset_url('assets/js/index.js') ?>"></script>
  <script src="<?= asset_url('assets/js/categories.js') ?>"></script>
</body>

</html>