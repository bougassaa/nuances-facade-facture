<?php

declare(strict_types=1);

/**
 * Variables: $document, $company, $client, $lines, $logoDataUri, $signatureDataUri,
 *            $depositAmountCents, $remainingDueCents
 *
 * Mise en page calquée sur les devis / factures de l’ancien logiciel de l’artisan.
 * Helvetica en police principale ; DejaVu Sans prend le relais (caractère par caractère)
 * pour les glyphes absents de Helvetica.
 */

if (!function_exists('nf_money')) {
    function nf_money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', ' ') . ' €';
    }
}

if (!function_exists('nf_date')) {
    function nf_date(?string $ymd): string
    {
        if ($ymd === null || $ymd === '') {
            return '';
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($ymd, 0, 10));
        return $dt ? $dt->format('d/m/Y') : $ymd;
    }
}

if (!function_exists('nf_qty')) {
    function nf_qty($qty): string
    {
        $f = (float) $qty;
        if (abs($f - round($f)) < 0.00001) {
            return (string) (int) round($f);
        }
        return rtrim(rtrim(number_format($f, 4, ',', ' '), '0'), ',');
    }
}

if (!function_exists('nf_vat_label')) {
    function nf_vat_label(int $bp): string
    {
        $pct = $bp / 100;
        if (abs($pct - round($pct)) < 0.001) {
            return (string) (int) round($pct) . ' %';
        }
        return number_format($pct, 1, ',', '') . ' %';
    }
}

if (!function_exists('nf_e')) {
    function nf_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES);
    }
}

$isQuote = ($document['doc_type'] ?? '') === 'quote';
$number = $document['number'] ?? 'Brouillon';
$issueDate = nf_date($document['issue_date'] ?? $document['sent_at'] ?? null)
    ?: nf_date(substr((string) ($document['created_at'] ?? ''), 0, 10));
$depositAmountCents = (int) ($depositAmountCents ?? $document['deposit_ttc_cents'] ?? 0);
$depositPercent = (int) ($document['deposit_percent'] ?? 0);
$validityMonths = (int) ($company['quote_validity_months'] ?? 0);
$vatExempt = !empty($company['vat_exempt']);
$vatRateBp = (int) ($document['vat_rate_bp'] ?? 2000);
$deductionCents = (int) ($document['deduction_ttc_cents'] ?? 0);
$deductionLabel = trim((string) ($document['deduction_label'] ?? ''));
$object = trim((string) ($document['object'] ?? ''));
$signatureDataUri = $signatureDataUri ?? null;
$paymentTerms = trim((string) ($company['payment_terms'] ?? ''));
$companyName = (string) ($company['name'] ?? '');

$siteLines = array_values(array_filter([
    trim((string) ($document['site_address_line1'] ?? '')),
    trim((string) ($document['site_address_line2'] ?? '')),
    trim(($document['site_postal_code'] ?? '') . ' ' . ($document['site_city'] ?? '')),
], static fn (string $l): bool => $l !== ''));
$hasSite = $siteLines !== [];

$clientContact = [];
if (!empty($client['email'])) {
    $clientContact[] = (string) $client['email'];
}
if (!empty($client['phone'])) {
    $clientContact[] = (string) $client['phone'];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 44px 38px 118px 38px; }
  body {
    font-family: Helvetica, "DejaVu Sans", sans-serif;
    font-size: 12px;
    line-height: 1.4;
    color: #333;
  }
  table { border-collapse: collapse; }
  td, th { vertical-align: top; }

  /* En-tête : entreprise (nom, adresse, logo) à gauche, client dans un bloc gris à droite */
  .header { width: 100%; }
  .company-cell { width: 54%; padding: 0; }
  .company-name { font-size: 34px; font-weight: bold; line-height: 1.1; color: #333; margin-bottom: 12px; }
  .company-sub { width: 100%; }
  .company-address { padding: 0; line-height: 1.5; }
  .company-address .contact { margin-top: 10px; }
  .company-logo { width: 52%; padding: 0 0 0 6px; text-align: left; }
  .logo { max-width: 140px; max-height: 128px; margin-top: -14px; }
  .client-cell { width: 46%; padding: 95px 0 0 0; }
  .client-box {
    background: #efefef;
    border-radius: 4px;
    padding: 12px 18px 14px 18px;
    line-height: 1.55;
    font-size: 13px;
  }
  .client-box .client-name { margin-bottom: 4px; }
  .client-box .contact { font-size: 11px; margin-top: 4px; }

  /* Numéro, validité, adresse du projet, date */
  .meta { width: 100%; margin-top: 30px; }
  .meta-left { width: 54%; padding: 0; }
  .meta-right { width: 46%; padding: 0 0 0 18px; }
  .doc-title { font-size: 17px; font-weight: bold; line-height: 1.2; }
  .validity { margin: 2px 0 14px 0; }
  .project-label { font-style: italic; margin-top: 2px; }
  .project-address {
    width: 69%;
    background: #efefef;
    border-radius: 4px;
    padding: 8px 18px 10px 18px;
    margin-top: 6px;
    line-height: 1.55;
  }
  .issue-date { padding-top: 2px; }

  /* Objet du document */
  .object { font-size: 22px; font-style: italic; margin: 34px 0 30px 40px; line-height: 1.2; }

  /* Lignes */
  table.lines { width: 100%; border: 1px solid #dcdcdc; }
  table.lines th {
    background: #8f8f8f;
    color: #fff;
    font-weight: bold;
    padding: 9px 7px;
    text-align: left;
    white-space: nowrap;
  }
  table.lines td { padding: 3px 7px; border-left: 1px solid #dcdcdc; }
  table.lines td.num { border-left: none; }
  table.lines tr.spacer td { padding: 0; height: 14px; }
  table.lines tr.spacer-end td { padding: 0; height: 22px; }
  table.lines .c-num { width: 4%; }
  table.lines .c-qty { width: 9%; text-align: right; }
  table.lines .c-price { width: 10%; text-align: right; }
  table.lines .c-vat { width: 7%; text-align: right; }
  table.lines .c-total { width: 11%; text-align: right; }
  table.lines td.qty,
  table.lines td.money,
  table.lines td.vat { text-align: right; white-space: nowrap; }

  /* Mentions + totaux */
  .summary { width: 100%; margin-top: 36px; page-break-inside: avoid; }
  .summary-left { width: 50%; padding: 6px 24px 0 0; }
  .summary-right { width: 50%; padding: 0 0 0 6px; }
  .summary-left p { margin: 0 0 8px 0; }
  table.totals { width: 100%; border-bottom: 1px solid #cfcfcf; }
  table.totals td { padding: 4px 9px; border-top: 1px solid #cfcfcf; white-space: nowrap; }
  table.totals td.amount { text-align: right; }
  table.totals tr.strong td { font-weight: bold; }
  table.totals tr.band td {
    background: #8f8f8f;
    color: #fff;
    font-weight: bold;
    font-size: 14px;
    border-top: 1px solid #8f8f8f;
  }

  .legal { margin-top: 26px; font-size: 9px; color: #666; line-height: 1.4; }
  .legal p { margin: 0 0 4px 0; }

  /* Page signature des devis */
  .signature-page { page-break-before: always; padding-top: 0; }
  table.signature-box { width: 100%; }
  table.signature-box td { vertical-align: bottom; padding: 0; }
  table.signature-box .sig-side { width: 5%; }
  table.signature-box .sig-cell { width: 40%; }
  table.signature-box .sig-gap { width: 10%; }
  .sig-label { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
  .sig-frame { background: #efefef; border-radius: 4px; }
  .sig-client { height: 100px; padding: 2px 16px; font-size: 12px; line-height: 1.2; }
  .sig-company { height: 76px; text-align: center; }
  .signature-img { max-width: 120px; max-height: 72px; margin-top: 2px; }
</style>
</head>
<body>

<table class="header">
  <tr>
    <td class="company-cell">
      <div class="company-name"><?= nf_e($companyName) ?></div>
      <table class="company-sub">
        <tr>
          <td class="company-address">
            <?php if (!empty($company['address_line1'])): ?>
              <?= nf_e($company['address_line1']) ?><br>
            <?php endif; ?>
            <?php if (!empty($company['address_line2'])): ?>
              <?= nf_e($company['address_line2']) ?><br>
            <?php endif; ?>
            <?php $companyCity = trim(($company['postal_code'] ?? '') . ' ' . ($company['city'] ?? '')); ?>
            <?php if ($companyCity !== ''): ?>
              <?= nf_e($companyCity) ?><br>
            <?php endif; ?>
            France
            <div class="contact">
              <?php if (!empty($company['phone'])): ?>
                Tél : <?= nf_e($company['phone']) ?><br>
              <?php endif; ?>
              <?php if (!empty($company['email'])): ?>
                <?= nf_e($company['email']) ?><br>
              <?php endif; ?>
              <?php if (!empty($company['website'])): ?>
                <?= nf_e($company['website']) ?>
              <?php endif; ?>
            </div>
          </td>
          <?php if (!empty($logoDataUri)): ?>
            <td class="company-logo">
              <img class="logo" src="<?= nf_e($logoDataUri) ?>" alt="Logo">
            </td>
          <?php endif; ?>
        </tr>
      </table>
    </td>
    <td class="client-cell">
      <div class="client-box">
        <div class="client-name"><?= nf_e($client['name'] ?? '') ?></div>
        <?php if (!empty($client['address_line1'])): ?>
          <?= nf_e($client['address_line1']) ?><br>
        <?php endif; ?>
        <?php if (!empty($client['address_line2'])): ?>
          <?= nf_e($client['address_line2']) ?><br>
        <?php endif; ?>
        <?php $clientCity = trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? '')); ?>
        <?php if ($clientCity !== ''): ?>
          <?= nf_e($clientCity) ?><br>
        <?php endif; ?>
        France
        <?php if ($clientContact !== []): ?>
          <div class="contact"><?= nf_e(implode(' — ', $clientContact)) ?></div>
        <?php endif; ?>
      </div>
    </td>
  </tr>
</table>

<table class="meta">
  <tr>
    <td class="meta-left">
      <div class="doc-title"><?= $isQuote ? 'Devis' : 'Facture' ?> n° <?= nf_e($number) ?></div>
      <?php if ($isQuote && $validityMonths > 0): ?>
        <div class="validity">Valable <?= $validityMonths ?> mois</div>
      <?php endif; ?>
      <?php if ($hasSite): ?>
        <div class="project-label">Adresse du projet :</div>
        <div class="project-address">
          <?php foreach ($siteLines as $siteLine): ?>
            <?= nf_e($siteLine) ?><br>
          <?php endforeach; ?>
          France
        </div>
      <?php endif; ?>
    </td>
    <td class="meta-right">
      <?php if ($issueDate !== ''): ?>
        <div class="issue-date">En date du <?= nf_e($issueDate) ?></div>
      <?php endif; ?>
    </td>
  </tr>
</table>

<?php if ($object !== ''): ?>
  <div class="object"><?= nf_e($object) ?></div>
<?php endif; ?>

<table class="lines">
  <thead>
    <tr>
      <th class="c-num">N°</th>
      <th class="c-label">Désignation</th>
      <th class="c-qty">Qté</th>
      <th class="c-price">PU HT</th>
      <?php if (!$vatExempt): ?>
        <th class="c-vat">TVA</th>
      <?php endif; ?>
      <th class="c-total">Total HT</th>
    </tr>
  </thead>
  <tbody>
    <?php $columnCount = $vatExempt ? 5 : 6; ?>
    <tr class="spacer"><?= str_repeat('<td></td>', $columnCount) ?></tr>
    <?php foreach ($lines as $i => $line): ?>
      <tr>
        <td class="num"><?= $i + 1 ?></td>
        <td class="label"><?= nl2br(nf_e($line['label'])) ?></td>
        <td class="qty"><?= nf_e(nf_qty($line['quantity'])) ?>&nbsp;&nbsp;<?= nf_e($line['unit']) ?></td>
        <td class="money"><?= nf_money((int) $line['unit_price_ht_cents']) ?></td>
        <?php if (!$vatExempt): ?>
          <td class="vat"><?= nf_vat_label($vatRateBp) ?></td>
        <?php endif; ?>
        <td class="money"><?= nf_money((int) $line['line_ht_cents']) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr class="spacer-end"><?= str_repeat('<td></td>', $columnCount) ?></tr>
  </tbody>
</table>

<table class="summary">
  <tr>
    <td class="summary-left">
      <?php if ($isQuote && $depositAmountCents > 0): ?>
        <p class="deposit">Acompte à la signature de <?= $depositPercent ?> %, soit <?= nf_money($depositAmountCents) ?>.</p>
      <?php endif; ?>
      <?php if (!$isQuote && $paymentTerms !== ''): ?>
        <p class="payment"><?= nl2br(nf_e($paymentTerms)) ?></p>
      <?php endif; ?>
      <?php if (!empty($document['notes'])): ?>
        <p class="notes"><?= nl2br(nf_e($document['notes'])) ?></p>
      <?php endif; ?>
    </td>
    <td class="summary-right">
      <table class="totals">
        <tr class="strong">
          <td>Total HT</td>
          <td class="amount"><?= nf_money((int) $document['total_ht_cents']) ?></td>
        </tr>
        <?php if ((int) ($document['total_vat_cents'] ?? 0) > 0 || !$vatExempt): ?>
          <tr>
            <td>TVA à <?= nf_vat_label($vatRateBp) ?></td>
            <td class="amount"><?= nf_money((int) ($document['total_vat_cents'] ?? 0)) ?></td>
          </tr>
        <?php endif; ?>
        <tr class="band">
          <td>Total TTC</td>
          <td class="amount"><?= nf_money((int) $document['total_ttc_cents']) ?></td>
        </tr>
        <?php if (!$isQuote && $deductionCents > 0): ?>
          <tr>
            <td><?= nf_e($deductionLabel !== '' ? $deductionLabel : 'Acompte') ?></td>
            <td class="amount">- <?= nf_money($deductionCents) ?></td>
          </tr>
          <tr class="band">
            <td>Reste à payer</td>
            <td class="amount"><?= nf_money((int) $remainingDueCents) ?></td>
          </tr>
        <?php endif; ?>
      </table>
    </td>
  </tr>
</table>

<?php
  $legal = array_values(array_filter([
      trim((string) ($company['legal_late_penalties'] ?? '')),
      trim((string) ($company['legal_recovery_fee'] ?? '')),
      trim((string) ($company['legal_extra'] ?? '')),
  ], static fn (string $t): bool => $t !== ''));
?>
<?php if ($legal !== [] || $vatExempt): ?>
  <div class="legal">
    <?php foreach ($legal as $text): ?>
      <p><?= nl2br(nf_e($text)) ?></p>
    <?php endforeach; ?>
    <?php if ($vatExempt): ?>
      <p><em>TVA non applicable — art. 293 B du CGI</em></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($isQuote): ?>
<div class="signature-page">
  <table class="signature-box">
    <tr>
      <td class="sig-side"></td>
      <td class="sig-cell">
        <div class="sig-label">Le client</div>
        <div class="sig-frame sig-client">
          Mention datée et signée :<br>
          <em>« Devis reçu avant l’exécution des travaux.<br>Bon pour travaux. »</em>
        </div>
      </td>
      <td class="sig-gap"></td>
      <td class="sig-cell">
        <div class="sig-label"><?= nf_e($companyName !== '' ? $companyName : 'Nuances Façade') ?></div>
        <div class="sig-frame sig-company">
          <?php if (!empty($signatureDataUri)): ?>
            <img class="signature-img" src="<?= nf_e($signatureDataUri) ?>" alt="Signature">
          <?php endif; ?>
        </div>
      </td>
      <td class="sig-side"></td>
    </tr>
  </table>
</div>
<?php endif; ?>

</body>
</html>
