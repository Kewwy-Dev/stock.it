// สร้างหิมะตก
    const snowContainer = document.querySelector('.snowflakes');
    const numSnowflakes = window.innerWidth < 768 ? 60 : 100;

    for (let i = 0; i < numSnowflakes; i++) {
      const flake = document.createElement('div');
      flake.classList.add('snowflake');

      const size = 5 + Math.random() * 8;
      flake.style.width = `${size}px`;
      flake.style.height = `${size}px`;

      flake.style.left = `${Math.random() * 100}vw`;

      const duration = 5 + Math.random() * 5;
      flake.style.animationDuration = `${duration}s`;

      flake.style.animationDelay = `${Math.random() * duration}s`;

      const drift = (Math.random() - 0.5) * 180;
      flake.style.setProperty('--drift', `${drift}px`);

      flake.style.opacity = 0.1 + Math.random() * 0;

      snowContainer.appendChild(flake);
    }

    // Toggle Password
    const passwordRegister = document.getElementById('passwordRegister');
    const togglePasswordRegister = document.getElementById('togglePasswordRegister');

    passwordRegister.addEventListener('input', function () {
      if (this.value.length > 0) {
        togglePasswordRegister.classList.remove('d-none');
      } else {
        togglePasswordRegister.classList.add('d-none');
      }
    });

    togglePasswordRegister.addEventListener('click', function () {
      const type = passwordRegister.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordRegister.setAttribute('type', type);
      this.querySelector('i').classList.toggle('bi-eye');
      this.querySelector('i').classList.toggle('bi-eye-slash');
    });