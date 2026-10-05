const button = document.querySelector('.menu-toggle');
const menu = document.querySelector('#mobile-menu');
function closeMenu() { if (button && menu) { menu.hidden = true; button.setAttribute('aria-expanded', 'false'); } }
button?.addEventListener('click', () => { menu.hidden = !menu.hidden; button.setAttribute('aria-expanded', String(!menu.hidden)); });
menu?.addEventListener('click', event => { if (event.target.closest('a')) closeMenu(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeMenu(); document.querySelectorAll('details[open]').forEach(item => item.removeAttribute('open')); button?.focus(); } });
