<?php
/**
 * Plugin Name: Findify Brief Form
 * Description: Captura briefs do landing page, salva no banco do WordPress e responde via AJAX para continuar o atendimento no WhatsApp.
 * Version: 1.0.0
 * Author: Findify
 */

if (!defined('ABSPATH')) {
    exit;
}

function findify_brief_activation(): void
{
    global $wpdb;

    $table = $wpdb->prefix . 'findify_briefs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      nome VARCHAR(190) NOT NULL,
      email VARCHAR(190) NOT NULL,
      product TEXT NOT NULL,
      budget DECIMAL(12,2) NULL,
      style TEXT NOT NULL,
      brand VARCHAR(190) NULL,
      options SMALLINT UNSIGNED NOT NULL DEFAULT 3,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY  (id),
      KEY email (email)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'findify_brief_activation');

function findify_brief_enqueue_assets(): void
{
    $asset_url = plugin_dir_url(__FILE__) . 'assets/';

    wp_enqueue_style(
        'findify-brief-style',
        $asset_url . 'brief-form.css',
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'findify-brief-script',
        $asset_url . 'brief-form.js',
        [],
        '1.0.0',
        true
    );

    wp_localize_script(
        'findify-brief-script',
        'FindifyBrief',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('findify_brief_nonce'),
            'action' => 'findify_submit_brief',
            'whatsappNumber' => '5541996860137',
            'successMessage' => __('Brief recebido! Em até 24h retornamos com sua curadoria.', 'findify'),
            'errorMessage' => __('Não foi possível enviar seu brief agora. Tente novamente.', 'findify'),
        ]
    );
}
add_action('wp_enqueue_scripts', 'findify_brief_enqueue_assets');

function findify_brief_render_shortcode(): string
{
    ob_start();
    ?>
    <section class="findify-brief" aria-labelledby="findify-brief-title">
        <div class="findify-brief__header">
            <p class="findify-brief__eyebrow">MVP Findify</p>
            <h2 id="findify-brief-title">Briefing rápido</h2>
            <p>Compartilhe suas preferências e deixe o resto com a nossa curadoria.</p>
        </div>

        <form data-findify-brief-form class="findify-brief__form" method="post" novalidate>
            <input type="hidden" name="action" value="findify_submit_brief" />
            <input type="hidden" name="nonce" value="" data-nonce />

            <div class="findify-brief__grid">
                <label class="findify-brief__field">
                    <span>Seu nome</span>
                    <input type="text" name="nome" required placeholder="Como devemos te chamar?" />
                </label>
                <label class="findify-brief__field">
                    <span>E-mail</span>
                    <input type="email" name="email" required placeholder="seu@email.com" />
                </label>
            </div>

            <label class="findify-brief__field">
                <span>O que você quer comprar?</span>
                <input type="text" name="projeto" required placeholder="Ex.: perfume, notebook, tênis..." />
            </label>

            <label class="findify-brief__field">
                <span>Qual é o seu orçamento?</span>
                <div class="findify-brief__money">
                    <span>R$</span>
                    <input type="number" name="orcamento" min="0" step="0.01" placeholder="0,00" />
                </div>
                <small>Valor formatado: <strong data-budget-display>R$ 0,00</strong></small>
            </label>

            <label class="findify-brief__field">
                <span>Estilo ou uso pretendido?</span>
                <textarea name="mensagem" rows="3" required placeholder="Conte em poucas palavras como imagina usar ou o estilo preferido."></textarea>
            </label>

            <label class="findify-brief__field">
                <span>Já tem alguma marca em mente?</span>
                <input type="text" name="brand" placeholder="Se não tiver preferência, deixe em branco." />
            </label>

            <label class="findify-brief__field">
                <span>Quer que mostremos até quantas opções?</span>
                <select name="options">
                    <option value="3" selected>3</option>
                    <option value="5">5</option>
                    <option value="10">10</option>
                </select>
            </label>

            <div class="findify-brief__actions">
                <button type="submit" class="findify-brief__primary" data-submit>Enviar brief</button>
                <button type="button" class="findify-brief__secondary" data-whatsapp-button>Chamar no WhatsApp</button>
            </div>
        </form>

        <div class="findify-brief__feedback" data-feedback aria-live="polite"></div>
        <div class="findify-brief__toast" data-toast role="status" aria-live="assertive"></div>
    </section>
    <?php
    return trim((string) ob_get_clean());
}
add_shortcode('findify_brief_form', 'findify_brief_render_shortcode');

function findify_brief_handle_submission(): void
{
    $nonce = $_POST['nonce'] ?? '';
    if (!wp_verify_nonce($nonce, 'findify_brief_nonce')) {
        wp_send_json_error(['message' => __('Falha na validação. Recarregue a página e tente novamente.', 'findify')], 400);
    }

    $input = [
        'nome'    => sanitize_text_field(wp_unslash($_POST['nome'] ?? '')),
        'email'   => sanitize_email(wp_unslash($_POST['email'] ?? '')),
        'product' => sanitize_text_field(wp_unslash($_POST['projeto'] ?? '')),
        'budget'  => sanitize_text_field(wp_unslash($_POST['orcamento'] ?? '')),
        'style'   => sanitize_textarea_field(wp_unslash($_POST['mensagem'] ?? '')),
        'brand'   => sanitize_text_field(wp_unslash($_POST['brand'] ?? '')),
        'options' => sanitize_text_field(wp_unslash($_POST['options'] ?? '3')),
    ];

    if ($input['nome'] === '' || $input['email'] === '' || $input['product'] === '' || $input['style'] === '') {
        wp_send_json_error(['message' => __('Preencha nome, e-mail, o que deseja comprar e estilo/uso pretendido.', 'findify')], 400);
    }

    if (!is_email($input['email'])) {
        wp_send_json_error(['message' => __('Informe um e-mail válido para continuar.', 'findify')], 400);
    }

    $budgetValue = null;
    if ($input['budget'] !== '') {
        $normalizedBudget = str_replace(',', '.', $input['budget']);
        $budgetValue = is_numeric($normalizedBudget) ? (float) $normalizedBudget : null;
    }

    $optionsCount = ctype_digit($input['options']) ? (int) $input['options'] : 3;
    if (!in_array($optionsCount, [3, 5, 10], true)) {
        $optionsCount = 3;
    }

    global $wpdb;

    $budgetPlaceholder = $budgetValue === null ? 'NULL' : '%f';
    $table = $wpdb->prefix . 'findify_briefs';

    $createdAt = current_time('mysql');
    $args = [
        $input['nome'],
        $input['email'],
        $input['product'],
    ];

    if ($budgetValue !== null) {
        $args[] = $budgetValue;
    }

    $args[] = $input['style'];
    $args[] = $input['brand'] === '' ? null : $input['brand'];
    $args[] = $optionsCount;
    $args[] = $createdAt;

    $sql = $wpdb->prepare(
        "INSERT INTO {$table} (nome, email, product, budget, style, brand, options, created_at) VALUES (%s, %s, %s, {$budgetPlaceholder}, %s, %s, %d, %s)",
        $args
    );

    $result = $wpdb->query($sql);

    if ($result === false) {
        wp_send_json_error(['message' => __('Erro ao salvar seu brief. Tente novamente em instantes.', 'findify')], 500);
    }

    wp_send_json_success([
        'message' => __('Brief recebido! Em até 24h retornamos com sua curadoria.', 'findify'),
    ]);
}
add_action('wp_ajax_findify_submit_brief', 'findify_brief_handle_submission');
add_action('wp_ajax_nopriv_findify_submit_brief', 'findify_brief_handle_submission');
