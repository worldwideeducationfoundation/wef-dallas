/* Our Team page: the bio modal and the photo-format fallback.
   Was two inline <script> blocks and onclick="" attributes in our-team.php;
   the Content-Security-Policy no longer runs inline script, so it lives here.
   Loaded with defer, so the DOM is complete when this runs. */

document.addEventListener("DOMContentLoaded", function() {
  const teamImages = document.querySelectorAll('.team-card-image img');

  function handleImageError(img) {
    const currentSrc = img.getAttribute('src');
    if (currentSrc) {
      if (currentSrc.endsWith('.jpg')) {
        img.setAttribute('src', currentSrc.replace('.jpg', '.png'));
      } else if (currentSrc.endsWith('.png')) {
        img.setAttribute('src', currentSrc.replace('.png', '.jpeg'));
      } else if (currentSrc.endsWith('.jpeg')) {
        img.setAttribute('src', currentSrc.replace('.jpeg', '.webp'));
      } else {
        img.removeAttribute('src'); // Trigger CSS fallback
      }
    }
  }

  teamImages.forEach(img => {
    if (img.complete && img.naturalHeight === 0) {
      handleImageError(img);
    }
    img.addEventListener('error', function() {
      handleImageError(this);
    });
  });
});

const modalOverlay = document.getElementById('team-modal-overlay');
const modalImg = document.getElementById('team-modal-img');
const modalName = document.getElementById('team-modal-name');
const modalRole = document.getElementById('team-modal-role');
const modalBio = document.getElementById('team-modal-bio');
const modalCloseBtn = document.getElementById('team-modal-close');

function openModal(cardId) {
  const card = document.getElementById(cardId);
  if (!card) return;

  const imgEl = card.querySelector('img');
  const nameEl = card.querySelector('h3');
  const roleEl = card.querySelector('.team-role');
  const bioEl = card.querySelector('.team-bio');

  if (imgEl) modalImg.src = imgEl.src;
  if (nameEl) modalName.textContent = nameEl.textContent;
  if (roleEl) modalRole.textContent = roleEl.textContent;
  if (bioEl) {
    modalBio.innerHTML = bioEl.innerHTML;
    modalBio.style.display = 'block';
  } else {
    modalBio.innerHTML = '';
  }

  modalOverlay.classList.add('active');
  document.body.style.overflow = 'hidden';

  if (window.location.hash !== '#' + cardId) {
    history.pushState({
      modalOpen: true
    }, "", '#' + cardId);
  }
}

function closeModal() {
  modalOverlay.classList.remove('active');
  document.body.style.overflow = '';
  if (window.location.hash) {
    history.pushState("", document.title, window.location.pathname + window.location.search);
  }
}

function toggleBio(button) {
  const card = button.closest('.team-card');
  openModal(card.id);
}

document.querySelectorAll('.team-read-more').forEach((button) => {
  button.addEventListener('click', () => toggleBio(button));
});
document.querySelectorAll('.team-modal-back').forEach((button) => {
  button.addEventListener('click', closeModal);
});

// Phones: designations are cut to two lines with an ellipsis, so the
// whole card opens the full details, not just the Read More button.
const phoneWidth = window.matchMedia('(max-width: 768px)');
document.querySelectorAll('.team-card').forEach((card) => {
  card.addEventListener('click', (e) => {
    if (!phoneWidth.matches || e.target.closest('.team-read-more')) return;
    openModal(card.id);
  });
});

if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
if (modalOverlay) {
  modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) closeModal();
  });
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('active')) {
    closeModal();
  }
});

window.addEventListener('popstate', function() {
  if (window.location.hash) {
    const targetCard = document.getElementById(window.location.hash.substring(1));
    if (targetCard && targetCard.classList.contains('team-card')) {
      openModal(targetCard.id);
    }
  } else {
    closeModal();
  }
});

window.addEventListener('load', function() {
  if (window.location.hash) {
    const targetCard = document.getElementById(window.location.hash.substring(1));
    if (targetCard && targetCard.classList.contains('team-card')) {
      openModal(targetCard.id);
    }
  }
});
