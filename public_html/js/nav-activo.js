document.addEventListener('DOMContentLoaded', function () {
  const urlActual = window.location.pathname;
  const enlacesNav = document.querySelectorAll('a.nav-link');

  enlacesNav.forEach(function (enlace) {
    const href = new URL(enlace.href).pathname;
    if (href === urlActual) {
      enlace.classList.add('active');
    }
  });
});