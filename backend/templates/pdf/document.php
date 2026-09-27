<?php

declare(strict_types=1);

/**
 * Variables: $document, $company, $client, $lines, $logoDataUri,
 *            $depositAmountCents, $remainingDueCents
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

if (!function_exists('nf_qty_unit')) {
    function nf_qty_unit($qty, string $unit): string
    {
        return nf_qty($qty) . ' ' . $unit;
    }
}

$isQuote = ($document['doc_type'] ?? '') === 'quote';
$number = $document['number'] ?? 'Brouillon';
$issueDate = nf_date($document['issue_date'] ?? $document['sent_at'] ?? null)
    ?: nf_date(substr((string) ($document['created_at'] ?? ''), 0, 10));
$depositAmountCents = (int) ($depositAmountCents ?? $document['deposit_ttc_cents'] ?? 0);
$vatRateBp = (int) ($document['vat_rate_bp'] ?? 2000);
$deductionCents = (int) ($document['deduction_ttc_cents'] ?? 0);
$deductionLabel = trim((string) ($document['deduction_label'] ?? ''));
$hasSite = trim((string) ($document['site_address_line1'] ?? '')) !== ''
    || trim((string) ($document['site_city'] ?? '')) !== '';
$object = trim((string) ($document['object'] ?? ''));
$paymentTerms = trim((string) ($company['payment_terms'] ?? ''));
$quoteValidity = trim((string) ($company['legal_quote_validity'] ?? ''));

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
  @page { margin: 36px 42px 70px 42px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
  .top { width: 100%; margin-bottom: 18px; }
  .top td { vertical-align: top; }
  .logo { max-height: 64px; max-width: 160px; margin-bottom: 6px; }
  .company-block { line-height: 1.35; }
  .company-block strong { font-size: 12px; }
  .client-block { text-align: right; line-height: 1.35; }
  .client-block strong { font-size: 12px; }
  .doc-meta { margin: 14px 0 10px; }
  .doc-meta .label { font-size: 13px; font-weight: bold; }
  .doc-meta .sub { color: #444; margin-top: 2px; }
  .site { margin: 8px 0 14px; line-height: 1.35; }
  .site .title { font-weight: bold; margin-bottom: 2px; }
  table.lines { width: 100%; border-collapse: collapse; margin-top: 6px; }
  table.lines th {
    background: #f3f3f3;
    border-bottom: 1px solid #888;
    padding: 5px 4px;
    text-align: left;
    font-size: 9px;
    text-transform: none;
  }
  table.lines td { border-bottom: 1px solid #ddd; padding: 5px 4px; vertical-align: top; }
  table.lines .num { text-align: right; white-space: nowrap; }
  table.lines .center { text-align: center; white-space: nowrap; }
  .totals-wrap { width: 100%; margin-top: 12px; }
  .totals-wrap td { vertical-align: top; }
  .totals { width: 230px; margin-left: auto; }
  .totals td { padding: 3px 4px; }
  .totals .grand { font-weight: bold; font-size: 11px; border-top: 1px solid #333; }
  .totals .deduction { color: #333; }
  .totals .remaining { font-weight: bold; }
  .deposit { margin-top: 10px; font-size: 10px; }
  .payment { margin-top: 8px; }
  .notes { margin-top: 14px; }
  .legal { margin-top: 18px; font-size: 8px; color: #555; line-height: 1.35; }
  .signature-page { page-break-before: always; padding-top: 80px; }
  .signature-box { width: 100%; margin-top: 40px; }
  .signature-box td { width: 50%; vertical-align: top; padding: 12px; }
  .signature-box .frame {
    border: 1px solid #999;
    min-height: 140px;
    padding: 14px;
  }
  .signature-box h4 { margin: 0 0 10px; font-size: 11px; }
</style>
</head>
<body>

<table class="top">
  <tr>
    <td width="52%">
      <?php if (!empty($logoDataUri)): ?>
        <img class="logo" src="<?= htmlspecialchars($logoDataUri, ENT_QUOTES) ?>" alt="Logo">
      <?php endif; ?>
      <div class="company-block">
        <strong><?= htmlspecialchars((string) ($company['name'] ?? ''), ENT_QUOTES) ?></strong><br>
        <?php if (!empty($company['address_line1'])): ?>
          <?= htmlspecialchars((string) $company['address_line1'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?php if (!empty($company['address_line2'])): ?>
          <?= htmlspecialchars((string) $company['address_line2'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?php
          $companyCity = trim(($company['postal_code'] ?? '') . ' ' . ($company['city'] ?? ''));
          if ($companyCity !== ''):
        ?>
          <?= htmlspecialchars($companyCity, ENT_QUOTES) ?><br>
        <?php endif; ?>
        France<br>
        <?php if (!empty($company['phone'])): ?>
          Tél : <?= htmlspecialchars((string) $company['phone'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?php if (!empty($company['email'])): ?>
          <?= htmlspecialchars((string) $company['email'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?php if (!empty($company['website'])): ?>
          <?= htmlspecialchars((string) $company['website'], ENT_QUOTES) ?>
        <?php endif; ?>
      </div>
    </td>
    <td width="48%">
      <div class="client-block">
        <strong><?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES) ?></strong><br>
        <?php if (!empty($client['address_line1'])): ?>
          <?= htmlspecialchars((string) $client['address_line1'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?php if (!empty($client['address_line2'])): ?>
          <?= htmlspecialchars((string) $client['address_line2'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?php
          $clientCity = trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? ''));
          if ($clientCity !== ''):
        ?>
          <?= htmlspecialchars($clientCity, ENT_QUOTES) ?><br>
        <?php endif; ?>
        France<br>
        <?php if ($clientContact !== []): ?>
          <?= htmlspecialchars(implode(' — ', $clientContact), ENT_QUOTES) ?>
        <?php endif; ?>
      </div>
    </td>
  </tr>
</table>

<div class="doc-meta">
  <div class="label">
    <?= $isQuote ? 'Devis' : 'Facture' ?> n° <?= htmlspecialchars((string) $number, ENT_QUOTES) ?>
  </div>
  <?php if ($isQuote && $quoteValidity !== ''): ?>
    <div class="sub"><?= htmlspecialchars($quoteValidity, ENT_QUOTES) ?></div>
  <?php endif; ?>
  <?php if ($issueDate !== ''): ?>
    <div class="sub">En date du <?= htmlspecialchars($issueDate, ENT_QUOTES) ?></div>
  <?php endif; ?>
</div>

<?php if ($object !== '' || $hasSite): ?>
  <div class="site">
    <?php if ($object !== ''): ?>
      <div class="title"><?= htmlspecialchars($object, ENT_QUOTES) ?></div>
    <?php endif; ?>
    <?php if ($hasSite): ?>
      <div><strong>Adresse du projet :</strong></div>
      <?php if (!empty($document['site_address_line1'])): ?>
        <?= htmlspecialchars((string) $document['site_address_line1'], ENT_QUOTES) ?><br>
      <?php endif; ?>
      <?php if (!empty($document['site_address_line2'])): ?>
        <?= htmlspecialchars((string) $document['site_address_line2'], ENT_QUOTES) ?><br>
      <?php endif; ?>
      <?php
        $siteCity = trim(($document['site_postal_code'] ?? '') . ' ' . ($document['site_city'] ?? ''));
        if ($siteCity !== ''):
      ?>
        <?= htmlspecialchars($siteCity, ENT_QUOTES) ?><br>
      <?php endif; ?>
      France
    <?php endif; ?>
  </div>
<?php endif; ?>

<table class="lines">
  <thead>
    <tr>
      <th style="width:6%;">N°</th>
      <th>Désignation</th>
      <th class="center" style="width:12%;">Qté</th>
      <th class="num" style="width:16%;">PU HT</th>
      <th class="num" style="width:16%;">Total HT</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($lines as $i => $line): ?>
      <tr>
        <td class="center"><?= $i + 1 ?></td>
        <td><?= nl2br(htmlspecialchars((string) $line['label'], ENT_QUOTES)) ?></td>
        <td class="center"><?= htmlspecialchars(nf_qty_unit($line['quantity'], (string) $line['unit']), ENT_QUOTES) ?></td>
        <td class="num"><?= nf_money((int) $line['unit_price_ht_cents']) ?></td>
        <td class="num"><?= nf_money((int) $line['line_ht_cents']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals-wrap">
  <tr>
    <td width="50%">
      <?php if ($isQuote && $depositAmountCents > 0): ?>
        <div class="deposit">
          Acompte à la signature de <?= nf_money((int) $depositAmountCents) ?>.
        </div>
      <?php endif; ?>
      <?php if (!$isQuote && $paymentTerms !== ''): ?>
        <div class="payment"><?= nl2br(htmlspecialchars($paymentTerms, ENT_QUOTES)) ?></div>
      <?php endif; ?>
      <?php if (!empty($document['notes'])): ?>
        <div class="notes">
          <?= nl2br(htmlspecialchars((string) $document['notes'], ENT_QUOTES)) ?>
        </div>
      <?php endif; ?>
    </td>
    <td width="50%">
      <table class="totals">
        <tr>
          <td>Total HT</td>
          <td class="num"><?= nf_money((int) $document['total_ht_cents']) ?></td>
        </tr>
        <?php if ((int) ($document['total_vat_cents'] ?? 0) > 0): ?>
          <tr>
            <td>TVA à <?= nf_vat_label($vatRateBp) ?></td>
            <td class="num"><?= nf_money((int) $document['total_vat_cents']) ?></td>
          </tr>
        <?php elseif (empty($company['vat_exempt'])): ?>
          <tr>
            <td>TVA à <?= nf_vat_label($vatRateBp) ?></td>
            <td class="num"><?= nf_money(0) ?></td>
          </tr>
        <?php endif; ?>
        <tr class="grand">
          <td>Total TTC</td>
          <td class="num"><?= nf_money((int) $document['total_ttc_cents']) ?></td>
        </tr>
        <?php if (!$isQuote && $deductionCents > 0): ?>
          <tr class="deduction">
            <td><?= htmlspecialchars($deductionLabel !== '' ? $deductionLabel : 'Acompte', ENT_QUOTES) ?></td>
            <td class="num">− <?= nf_money($deductionCents) ?></td>
          </tr>
          <tr class="remaining">
            <td>Reste à payer</td>
            <td class="num"><?= nf_money((int) $remainingDueCents) ?></td>
          </tr>
        <?php endif; ?>
      </table>
    </td>
  </tr>
</table>

<div class="legal">
  <?php if (!empty($company['legal_late_penalties'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_late_penalties'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if (!empty($company['legal_recovery_fee'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_recovery_fee'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if (!empty($company['legal_extra'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_extra'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if (!empty($company['vat_exempt'])): ?>
    <p><em>TVA non applicable — art. 293 B du CGI</em></p>
  <?php endif; ?>
</div>

<?php if ($isQuote): ?>
<div class="signature-page">
  <p><strong><?= htmlspecialchars((string) $number, ENT_QUOTES) ?></strong></p>
  <table class="signature-box">
    <tr>
      <td>
        <div class="frame">
          <h4>Le client</h4>
          <p>Mention datée et signée :<br>
          « Devis reçu avant l’exécution des travaux.<br>
          Bon pour travaux. »</p>
        </div>
      </td>
      <td>
        <div class="frame">
          <h4><?= htmlspecialchars((string) ($company['name'] ?? 'Nuances Façade'), ENT_QUOTES) ?></h4>
        </div>
      </td>
    </tr>
  </table>
</div>
<?php endif; ?>

</body>
</html>
