/* ─── Cart State ─── */
  let cart = [];

  function addToCart(name, price, category) {
    const existing = cart.find(i => i.name === name && i.category === category);
    if (existing) {
      existing.qty++;
    } else {
      cart.push({ name, price, category, qty: 1 });
    }
    renderCart();
    if (!document.getElementById('cartDrawer').classList.contains('open')) toggleCart();
  }

  function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
  }

  function changeQty(idx, delta) {
    cart[idx].qty += delta;
    if (cart[idx].qty <= 0) cart.splice(idx, 1);
    renderCart();
  }

  function renderCart() {
    const body      = document.getElementById('cartBody');
    const emptyMsg  = document.getElementById('cartEmptyMsg');
    const foot      = document.getElementById('cartFoot');
    const badge     = document.getElementById('cartBadge');
    const countHead = document.getElementById('cartCountHead');
    const total     = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const totalQty  = cart.reduce((s, i) => s + i.qty, 0);

    countHead.textContent = totalQty;
    badge.textContent     = totalQty;
    badge.classList.toggle('show', totalQty > 0);
    document.getElementById('cartTotalDisplay').textContent =
      total.toLocaleString('fr-FR') + ' F CFA';

    /* clear existing items but keep emptyMsg */
    body.querySelectorAll('.cart-item').forEach(el => el.remove());

    if (cart.length === 0) {
      emptyMsg.style.display = '';
      foot.style.display = 'none';
      return;
    }

    emptyMsg.style.display = 'none';
    foot.style.display = '';

    cart.forEach((item, idx) => {
      const div = document.createElement('div');
      div.className = 'cart-item';
      div.innerHTML = `
        <div class="cart-item-info">
          <div class="cart-item-name">${item.name}</div>
          <div class="cart-item-cat">${item.category}</div>
          <div class="cart-item-price">${(item.price * item.qty).toLocaleString('fr-FR')} F CFA</div>
        </div>
        <div class="cart-item-qty">
          <button class="qty-btn" onclick="changeQty(${idx}, -1)">&#8722;</button>
          <span class="qty-val">${item.qty}</span>
          <button class="qty-btn" onclick="changeQty(${idx}, +1)">&#43;</button>
        </div>
        <button class="cart-item-del" onclick="removeFromCart(${idx})" title="Supprimer">
          <i class="fas fa-trash-alt"></i>
        </button>`;
      body.appendChild(div);
    });
  }

  function toggleCart() {
    document.getElementById('cartDrawer').classList.toggle('open');
    document.getElementById('cartOverlay').classList.toggle('open');
  }

  function sendToWhatsApp() {
    const name  = document.getElementById('customerName').value.trim();
    const phone = document.getElementById('customerPhone').value.trim();
    if (!name)  { alert('Veuillez entrer votre nom.'); return; }
    if (!phone) { alert('Veuillez entrer votre numéro de téléphone.'); return; }
    if (cart.length === 0) { alert('Votre panier est vide.'); return; }

    const total = cart.reduce((s, i) => s + i.price * i.qty, 0);
    const lines = cart.map(i =>
      `• ${i.name} (${i.category}) x${i.qty} — ${(i.price * i.qty).toLocaleString('fr-FR')} F CFA`
    ).join('\n');

    const msg =
`🛒 *Nouvelle commande – Zouzoukou Mem's*

👤 *Client :* ${name}
📞 *Téléphone :* ${phone}

📦 *Articles commandés :*
${lines}

💰 *Total estimé :* ${total.toLocaleString('fr-FR')} F CFA

_Message envoyé depuis le site Zouzoukou Mem's_`;

    window.open('https://wa.me/2250747081151?text=' + encodeURIComponent(msg), '_blank');
  }

  /* Navigation mobile */
  function toggleNav() {
    const links = document.getElementById('navLinks');
    const ham   = document.getElementById('hamburger');
    links.classList.toggle('open');
    ham.classList.toggle('open');
  }

  document.querySelectorAll('.nav-links a').forEach(a =>
    a.addEventListener('click', () => {
      document.getElementById('navLinks').classList.remove('open');
      document.getElementById('hamburger').classList.remove('open');
    })
  );

  /* Tabs */
  const tabMap = { 'instants': 'Instants', 'pro': 'Pro', 'ivoire': 'Ivoire' };
  document.querySelectorAll('.tab').forEach(btn => {
    btn.addEventListener('click', function () {
      const key = this.dataset.tab;
      document.querySelectorAll('.tab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      this.classList.add('active');
      document.getElementById('panel-' + key).classList.add('active');
    });
  });

  /* Scroll fade-in */
  const observer = new IntersectionObserver(entries => {
    entries.forEach((e, i) => {
      if (e.isIntersecting) {
        setTimeout(() => e.target.classList.add('in'), i * 90);
        observer.unobserve(e.target);
      }
    });
  }, { threshold: 0.12 });

  document.querySelectorAll('.fade').forEach(el => observer.observe(el));

  /* Navbar shadow on scroll */
  window.addEventListener('scroll', () => {
    document.getElementById('navbar').style.boxShadow =
      window.scrollY > 40
        ? '0 4px 32px rgba(45,18,8,.16)'
        : '0 2px 20px rgba(45,18,8,.07)';
  });
