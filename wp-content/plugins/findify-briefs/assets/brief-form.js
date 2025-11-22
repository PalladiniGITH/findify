(() => {
  const config = window.FindifyBrief || {};
  const storageKey = 'findifyBriefData';

  function formatCurrency(value) {
    if (value === '' || value === null || typeof value === 'undefined' || Number.isNaN(Number(value))) {
      return '0,00';
    }
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })
      .format(Number(value))
      .replace('R$', '')
      .trim();
  }

  function buildWhatsAppMessage(data, whatsappNumber) {
    const budgetValue = data.budget ? `R$ ${formatCurrency(parseFloat(String(data.budget).replace(',', '.')) || 0)}` : 'Não informado';
    const lines = [
      'Olá, equipe Findify! Gostaria de solicitar uma curadoria personalizada.',
      data.nome ? `Meu nome: ${data.nome}.` : 'Meu nome não foi informado.',
      data.email ? `Contato por e-mail: ${data.email}.` : 'E-mail não informado.',
      `O que desejo comprar: ${data.product || 'Não informado'}.`,
      `Orçamento: ${budgetValue}.`,
      `Estilo ou uso pretendido: ${data.style || 'Não informado'}.`,
      `Marca preferida: ${data.brand || 'Sem preferência'}.`,
      `Quantidade de opções desejada: ${data.options || '3'}.`,
      'Obrigado!'
    ];

    const encoded = encodeURIComponent(lines.join('\n'));
    return `https://wa.me/${whatsappNumber}?text=${encoded}`;
  }

  function showToast(toastEl, message) {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.classList.add('is-visible');
    setTimeout(() => toastEl.classList.remove('is-visible'), 3200);
  }

  function displayFeedback(target, message, data, whatsappLink) {
    if (!target) return;
    const budgetValue = data.budget ? `R$ ${formatCurrency(parseFloat(String(data.budget).replace(',', '.')) || 0)}` : 'Não informado';
    const summary = [
      `Nome: ${data.nome || 'Não informado'}`,
      `E-mail: ${data.email || 'Não informado'}`,
      `O que deseja comprar: ${data.product || 'Não informado'}`,
      `Orçamento: ${budgetValue}`,
      `Estilo ou uso pretendido: ${data.style || 'Não informado'}`,
      `Marca preferida: ${data.brand || 'Sem preferência'}`,
      `Quantidade de opções: ${data.options || '3'}`,
    ].join('\n');

    target.innerHTML = `
      <div class="findify-brief__card">
        <p class="findify-brief__status">${message}</p>
        <pre class="findify-brief__summary">${summary}</pre>
        <a class="findify-brief__link" href="${whatsappLink}" target="_blank" rel="noopener">Enviar pelo WhatsApp</a>
      </div>
    `;
  }

  function persistData(data) {
    if (!window.localStorage) return;
    try {
      localStorage.setItem(storageKey, JSON.stringify(data));
    } catch (error) {
      // Ignore persistence errors in browsers with restricted storage.
      console.warn('Não foi possível salvar o brief localmente.', error);
    }
  }

  function restoreData() {
    if (!window.localStorage) return null;
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (error) {
      console.warn('Não foi possível restaurar o brief salvo.', error);
      return null;
    }
  }

  function initForm(form) {
    const budgetInput = form.querySelector('[name="orcamento"]');
    const budgetDisplay = form.querySelector('[data-budget-display]');
    const feedback = form.querySelector('[data-feedback]');
    const toast = form.querySelector('[data-toast]');
    const whatsappButton = form.querySelector('[data-whatsapp-button]');
    const submitButton = form.querySelector('[data-submit]');
    const actionInput = form.querySelector('input[name="action"]');
    const nonceInput = form.querySelector('[data-nonce]');
    const ajaxUrl = config.ajaxUrl || form.getAttribute('action') || window.location.href;
    const whatsappNumber = config.whatsappNumber || '5541996860137';

    if (actionInput) {
      actionInput.value = config.action || actionInput.value || 'findify_submit_brief';
    }

    if (nonceInput && config.nonce) {
      nonceInput.value = config.nonce;
    }

    function getFormData() {
      const data = {
        nome: form.querySelector('[name="nome"]')?.value.trim() || '',
        email: form.querySelector('[name="email"]')?.value.trim() || '',
        product: form.querySelector('[name="projeto"]')?.value.trim() || '',
        budget: budgetInput?.value.trim() || '',
        style: form.querySelector('[name="mensagem"]')?.value.trim() || '',
        brand: form.querySelector('[name="brand"]')?.value.trim() || '',
        options: form.querySelector('[name="options"]')?.value || '3',
      };
      return data;
    }

    function updateBudgetDisplay() {
      if (!budgetInput || !budgetDisplay) return;
      const current = budgetInput.value ? parseFloat(String(budgetInput.value).replace(',', '.')) : 0;
      const sanitized = Number.isNaN(current) ? 0 : current;
      budgetDisplay.textContent = `R$ ${formatCurrency(sanitized)}`;
    }

    function setSubmitting(isSubmitting) {
      if (!submitButton) return;
      submitButton.disabled = Boolean(isSubmitting);
      submitButton.textContent = isSubmitting ? 'Enviando...' : 'Enviar brief';
    }

    function setInitialFeedback() {
      if (!feedback) return;
      feedback.textContent = 'Preencha o brief para receber uma curadoria personalizada da Findify.';
    }

    const saved = restoreData();
    if (saved) {
      const nomeField = form.querySelector('[name="nome"]');
      if (nomeField && saved.nome) nomeField.value = saved.nome;

      const emailField = form.querySelector('[name="email"]');
      if (emailField && saved.email) emailField.value = saved.email;

      const productField = form.querySelector('[name="projeto"]');
      if (productField && saved.product) productField.value = saved.product;

      if (budgetInput && saved.budget) budgetInput.value = saved.budget;

      const styleField = form.querySelector('[name="mensagem"]');
      if (styleField && saved.style) styleField.value = saved.style;

      const brandField = form.querySelector('[name="brand"]');
      if (brandField && saved.brand) brandField.value = saved.brand;

      const optionsField = form.querySelector('[name="options"]');
      if (optionsField && saved.options) optionsField.value = saved.options;

      updateBudgetDisplay();
    } else {
      setInitialFeedback();
      updateBudgetDisplay();
    }

    form.addEventListener('input', (event) => {
      if (event.target === budgetInput) {
        updateBudgetDisplay();
      }
      persistData(getFormData());
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const data = getFormData();
      const whatsappLink = buildWhatsAppMessage(data, whatsappNumber);

      displayFeedback(feedback, 'Enviando seu brief para nossa equipe...', data, whatsappLink);
      persistData(data);
      setSubmitting(true);
      showToast(toast, 'Enviando seu brief...');

      const formData = new FormData(form);
      formData.set('action', actionInput?.value || config.action || 'findify_submit_brief');
      formData.set('nonce', nonceInput?.value || config.nonce || '');

      try {
        const response = await fetch(ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData,
        });

        const payload = await response.json().catch(() => null);

        if (!response.ok || !payload || payload.success !== true) {
          const errorMessage = payload?.data?.message || payload?.message || config.errorMessage || 'Não foi possível enviar seu brief.';
          throw new Error(errorMessage);
        }

        const successMessage = payload.data?.message || payload.message || config.successMessage || 'Brief enviado!';

        showToast(toast, successMessage);
        displayFeedback(feedback, successMessage, data, whatsappLink);

        try {
          localStorage.removeItem(storageKey);
        } catch (error) {
          console.warn('Não foi possível limpar os dados salvos do brief.', error);
        }

        form.reset();
        updateBudgetDisplay();
      } catch (error) {
        const message = error instanceof Error ? error.message : config.errorMessage || 'Erro ao enviar seu brief. Tente novamente.';
        showToast(toast, 'Falha ao enviar. Tente novamente.');
        displayFeedback(feedback, message, data, whatsappLink);
      } finally {
        setSubmitting(false);
      }
    });

    whatsappButton?.addEventListener('click', () => {
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      const data = getFormData();
      const whatsappLink = buildWhatsAppMessage(data, whatsappNumber);
      window.open(whatsappLink, '_blank');
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-findify-brief-form]').forEach((form) => initForm(form));
  });
})();
