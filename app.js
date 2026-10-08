'use strict';
const form = document.querySelector('#registration-form');
const error = document.querySelector('#form-error');
const status = document.querySelector('#registration-status');
const submit = form.querySelector('button[type="submit"]');
const submitLabel = document.querySelector('#submit-label');
let csrf = '', sending = false;
const officialHost = ['cuidarmoveagente.com.br', 'www.cuidarmoveagente.com.br'].includes(location.hostname);
const localHost = ['127.0.0.1', 'localhost'].includes(location.hostname);
function digits(value) { return value.replace(/\D/g, ''); }
function validCPF(value) {
  const d = digits(value);
  if (d.length !== 11 || /^(\d)\1{10}$/.test(d)) return false;
  for (let n = 9; n < 11; n++) {
    let sum = 0;
    for (let i = 0; i < n; i++) sum += Number(d[i]) * (n + 1 - i);
    let k = (sum * 10) % 11;
    if (k === 10) k = 0;
    if (k !== Number(d[n])) return false;
  }
  return true;
}
function showError(message, field) {
  error.textContent = message; error.hidden = false;
  if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  return false;
}
function validate() {
  for (const field of form.querySelectorAll('[required]')) {
    if (!field.checkValidity()) return showError(field.type === 'checkbox' ? 'Confirme os itens obrigatórios para participar.' : 'Confira e preencha os campos obrigatórios.', field);
  }
  if (form.elements.name.value.trim().split(/\s+/).length < 2) return showError('Informe seu nome completo.', form.elements.name);
  if (!validCPF(form.elements.cpf.value)) return showError('Confira o CPF informado.', form.elements.cpf);
  const phone = digits(form.elements.phone.value);
  if (!/^[1-9]\d{9,10}$/.test(phone)) return showError('Informe um telefone com DDD.', form.elements.phone);
  return true;
}
async function requestSession() {
  submit.disabled = true;
  if (!officialHost && !localHost) {
    status.replaceChildren(document.createTextNode('O cadastro é realizado na página oficial: '));
    const link = document.createElement('a');
    link.href = 'https://cuidarmoveagente.com.br/#cadastro'; link.textContent = 'Ir para o cadastro ↗';
    status.append(link);
    return false;
  }
  try {
    const response = await fetch('api/inscricoes.php', { credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(15000) });
    const data = await response.json();
    if (!response.ok || !data.open || !data.csrf) throw new Error(data.message || 'As inscrições estão sendo preparadas. Tente novamente em breve.');
    csrf = data.csrf;
    status.textContent = 'Inscrições até 08/11/2026, às 23h59 · horário de Três Lagoas/MS.';
    submit.disabled = false;
    return true;
  } catch (e) {
    csrf = '';
    status.textContent = e.name === 'TimeoutError' || e instanceof TypeError || e instanceof SyntaxError ? 'Não foi possível conectar ao cadastro. Recarregue a página para tentar novamente.' : e.message;
    const retry = document.createElement('button');
    retry.type = 'button'; retry.className = 'inline-link'; retry.textContent = 'Tentar novamente';
    retry.addEventListener('click', requestSession, { once: true });
    status.append(document.createTextNode(' '), retry);
    return false;
  }
}
form.addEventListener('input', e => { e.target.removeAttribute('aria-invalid'); error.hidden = true; });
document.querySelector('#cpf').addEventListener('input', e => {
  const d = digits(e.target.value).slice(0, 11);
  e.target.value = d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
});
document.querySelector('#phone').addEventListener('input', e => {
  const d = digits(e.target.value).slice(0, 11);
  e.target.value = d.replace(/^(\d{2})(\d)/, '($1) $2').replace(/(\d{4,5})(\d{4})$/, '$1-$2');
});
form.addEventListener('submit', async e => {
  e.preventDefault();
  if (sending || !validate()) return;
  if (!csrf && !await requestSession()) return;
  sending = true; submit.disabled = true; submitLabel.textContent = 'Salvando cadastro…';
  const payload = Object.fromEntries(new FormData(form));
  payload.adult = document.querySelector('#adult').checked;
  payload.terms = document.querySelector('#terms').checked;
  try {
    const response = await fetch('api/inscricoes.php', {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify(payload), signal: AbortSignal.timeout(20000)
    });
    const data = await response.json();
    if (!response.ok || data.ok !== true) {
      if (response.status === 403) { csrf = ''; await requestSession(); }
      throw new Error(data.message || 'Não foi possível concluir. Confira os dados e tente novamente.');
    }
    form.reset(); form.hidden = true; status.hidden = true;
    document.querySelector('.form-intro').hidden = true;
    document.querySelector('#success').hidden = false;
    document.querySelector('#success').focus();
  } catch (e) {
    showError(e.name === 'TimeoutError' || e instanceof TypeError || e instanceof SyntaxError
      ? 'Não recebemos a confirmação do servidor. Tente enviar novamente: o mesmo CPF nunca gera uma segunda participação.'
      : e.message);
  } finally {
    sending = false; submit.disabled = !csrf; submitLabel.textContent = 'Cadastrar e participar';
  }
});
document.querySelector('#restart').addEventListener('click', () => {
  form.reset(); form.hidden = false; status.hidden = false;
  document.querySelector('.form-intro').hidden = false;
  document.querySelector('#success').hidden = true; error.hidden = true;
  requestSession(); form.elements.name.focus();
});
document.querySelectorAll('[data-dialog]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.dialog).showModal()));
document.querySelectorAll('dialog').forEach(dialog => {
  dialog.querySelector('.close-dialog').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', e => {
    if (e.target === dialog) { const r = dialog.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dialog.close(); }
  });
});
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
if (!reducedMotion.matches && 'IntersectionObserver' in window) {
  document.body.classList.add('motion-ready');
  const observer = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) { entry.target.classList.add('in-view'); observer.unobserve(entry.target); }
  }), { threshold: .08 });
  document.querySelectorAll('.reveal').forEach((el, i) => {
    el.style.setProperty('--reveal-delay', `${el.classList.contains('journey-item') ? (i % 3) * 80 : 0}ms`); observer.observe(el);
  });
  document.querySelector('.signup').animate([{ opacity: 0, transform: 'translateY(15px)' }, { opacity: 1, transform: 'translateY(0)' }], { duration: 900, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'both' });
}
window.addEventListener('pagehide', () => form.reset());
requestSession();
