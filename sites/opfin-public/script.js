const menuButton = document.querySelector('.menu-toggle');
const menu = document.querySelector('#primary-nav');
const menuLabel = menuButton?.querySelector('.sr-only');

function setMenu(open) {
  if (!menuButton || !menu) return;
  menuButton.setAttribute('aria-expanded', String(open));
  menu.classList.toggle('open', open);
  if (menuLabel) menuLabel.textContent = open ? 'Close menu' : 'Open menu';
}

if (menuButton && menu) {
  menuButton.addEventListener('click', () => {
    setMenu(menuButton.getAttribute('aria-expanded') !== 'true');
  });

  menu.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setMenu(false));
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
      setMenu(false);
      menuButton.focus();
    }
  });
}

const year = document.querySelector('#year');
if (year) year.textContent = String(new Date().getFullYear());
