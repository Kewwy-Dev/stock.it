const snowContainer = document.querySelector(".snowflakes");
const numSnowflakes = window.innerWidth < 768 ? 60 : 100;

// สร้างหิมะเพียงครั้งเดียวตอนโหลดหน้า
for (let i = 0; i < numSnowflakes; i++) {
  const flake = document.createElement("div");
  flake.classList.add("snowflake");

  const size = 5 + Math.random() * 8;
  flake.style.width = `${size}px`;
  flake.style.height = `${size}px`;

  flake.style.left = `${Math.random() * 100}vw`;

  const duration = 5 + Math.random() * 5;
  flake.style.animationDuration = `${duration}s`;

  // delay สุ่มเพื่อให้เริ่มตกไม่พร้อมกัน
  flake.style.animationDelay = `${Math.random() * duration}s`; // ใช้ duration เพื่อให้ loop ต่อเนื่องดี

  const drift = (Math.random() - 0.5) * 180;
  flake.style.setProperty("--drift", `${drift}px`);

  flake.style.opacity = 0.1 + Math.random() * 0;

  snowContainer.appendChild(flake);
}

    // Progress Animation
    const progressBar = document.getElementById("progressBar");
    const progressText = document.getElementById("progressText");
    let currentProgress = 0;
    const duration = 1000;
    const startTime = Date.now();

    function updateProgress() {
    const elapsed = Date.now() - startTime;
    const progress = Math.min((elapsed / duration) * 100, 100);

    progressBar.style.width = `${progress}%`;

    const displayProgress = Math.floor(progress);
    progressText.textContent = `${displayProgress}%`;

    const textPosition = Math.min(progress, 95);
    progressText.style.left = `${textPosition}%`;

    if (progress < 100) {
        requestAnimationFrame(updateProgress);
    } else {
        progressText.textContent = "100%";
        progressText.style.left = "50%";
        setTimeout(hideLoader, 500); //ระยะเวลาเปลี่ยนหน้าหลังโหลดเสร็จ
    }
    }

requestAnimationFrame(updateProgress);

// เปลี่ยนหน้าโดยไม่กระทบหิมะ
function hideLoader() {
  document.querySelector(".loader-container").style.opacity = "0";
  setTimeout(() => {
    window.location.href = window.redirectUrl;
  }, 500);
}
