// assets/js/toast.js
const toastStyle = document.createElement("style");
toastStyle.innerHTML = `
  .colored-toast.swal2-icon-success { background-color: #ffffff !important; }
  .colored-toast.swal2-icon-warning { background-color: #ffffff !important; }
  .colored-toast.swal2-icon-error   { background-color: #ffffff !important; }
  .colored-toast.swal2-icon-info    { background-color: #ffffff !important; }
  .colored-toast .swal2-title,
  .colored-toast .swal2-html-container { color: #5d5d5d !important; font-weight: 400 !important; }
`;
document.head.appendChild(toastStyle);

window.Toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
  customClass: { popup: 'colored-toast' }
});

// อ่านจาก data-toast (สำหรับ PHP → HTML)
document.addEventListener("DOMContentLoaded", () => {
  const toastData = document.documentElement.dataset.toast;
  if (toastData) {
    try {
      const d = JSON.parse(toastData);
      const iconType = d.type === 'error' ? 'error' : 
                       d.type === 'warning' ? 'warning' : 
                       d.type === 'info' ? 'info' : 'success';
      
      window.Toast.fire({
        icon: iconType,
        title: d.message,
        background: d.type === 'error' ? "#f27474" :
                    d.type === 'warning' ? "#f8bb86" :
                    d.type === 'info' ? "#3fc3ee" : "#a5dc86"
      });
    } catch (e) {
      console.error("Toast parse error:", e);
    }
  }
});
window.showToast = function(message, type = 'success') {
  const icon = type === 'error' ? 'error' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'success';
  Toast.fire({ icon, title: message });
};