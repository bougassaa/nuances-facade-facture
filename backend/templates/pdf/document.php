<?php

declare(strict_types=1);

/**
 * Variables: $document, $company, $client, $lines, $logoDataUri
 */

function nf_money(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' €';
}

function nf_date(?string $ymd): string
{
    if ($ymd === null || $ymd === '') {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($ymd, 0, 10));
    return $dt ? $dt->format('d/m/Y') : $ymd;
}

function nf_qty($qty): string
{
    $f = (float) $qty;
    if (abs($f - round($f)) < 0.00001) {
        return (string) (int) round($f);
    }
    return rtrim(rtrim(number_format($f, 4, ',', ' '), '0'), ',');
}

function nf_vat_label(int $bp): string
{
    $pct = $bp / 100;
    if (abs($pct - round($pct)) < 0.001) {
        return (string) (int) round($pct) . ' %';
    }
    return number_format($pct, 1, ',', '') . ' %';
}

$isQuote = ($document['doc_type'] ?? '') === 'quote';
$title = $isQuote ? 'DEVIS' : 'FACTURE';
$number = $document['number'] ?? 'Brouillon';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
  .header { width: 100%; margin-bottom: 24px; }
  .header td { vertical-align: top; }
  .logo { max-height: 70px; max-width: 180px; }
  .title { font-size: 22px; font-weight: bold; text-align: right; }
  .meta { text-align: right; margin-top: 8px; }
  .box { border: 1px solid #ccc; padding: 10px; margin-bottom: 16px; }
  .box h3 { margin: 0 0 6px; font-size: 12px; color: #555; }
  table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
  table.lines th { background: #f0f0f0; border-bottom: 1px solid #999; padding: 6px; text-align: left; font-size: 10px; }
  table.lines td { border-bottom: 1px solid #ddd; padding: 6px; vertical-align: top; }
  table.lines .num { text-align: right; white-space: nowrap; }
  .totals { width: 240px; margin-left: auto; margin-top: 12px; }
  .totals td { padding: 4px 6px; }
  .totals .grand { font-weight: bold; font-size: 13px; border-top: 1px solid #333; }
  .legal { margin-top: 28px; font-size: 9px; color: #555; line-height: 1.4; }
  .notes { margin-top: 16px; }
</style>
</head>
<body>

<table class="header">
  <tr>
    <td width="55%">
      <?php if (!empty($logoDataUri)): ?>
        <img class="logo" src="<?= htmlspecialchars($logoDataUri, ENT_QUOTES) ?>" alt="Logo">
      <?php endif; ?>
      <div style="margin-top:8px;">
        <strong><?= htmlspecialchars((string) ($company['name'] ?? ''), ENT_QUOTES) ?></strong><br>
        <?= htmlspecialchars((string) ($company['address_line1'] ?? ''), ENT_QUOTES) ?><br>
        <?php if (!empty($company['address_line2'])): ?>
          <?= htmlspecialchars((string) $company['address_line2'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?= htmlspecialchars(trim(($company['postal_code'] ?? '') . ' ' . ($company['city'] ?? '')), ENT_QUOTES) ?><br>
        <?php if (!empty($company['phone'])): ?>Tél. <?= htmlspecialchars((string) $company['phone'], ENT_QUOTES) ?><br><?php endif; ?>
        <?php if (!empty($company['email'])): ?><?= htmlspecialchars((string) $company['email'], ENT_QUOTES) ?><br><?php endif; ?>
        <?php if (!empty($company['siret'])): ?>SIRET <?= htmlspecialchars((string) $company['siret'], ENT_QUOTES) ?><br><?php endif; ?>
        <?php if (!empty($company['vat_number'])): ?>N° TVA <?= htmlspecialchars((string) $company['vat_number'], ENT_QUOTES) ?><br><?php endif; ?>
        <?php if (!empty($company['vat_exempt'])): ?><em>TVA non applicable — art. 293 B du CGI</em><?php endif; ?>
      </div>
    </td>
    <td width="45%">
      <div class="title"><?= $title ?></div>
      <div class="meta">
        N° <?= htmlspecialchars((string) $number, ENT_QUOTES) ?><br>
        Date : <?= nf_date($document['issue_date'] ?? $document['sent_at'] ?? null) ?: nf_date(substr((string) ($document['created_at'] ?? ''), 0, 10)) ?><br>
        <?php if ($isQuote && !empty($document['valid_until'])): ?>
          Valable jusqu’au : <?= nf_date((string) $document['valid_until']) ?><br>
        <?php endif; ?>
      </div>
      <div class="box" style="margin-top:16px;">
        <h3>Client</h3>
        <strong><?= htmlspecialchars((string) ($client['name'] ?? ''), ENT_QUOTES) ?></strong><br>
        <?= htmlspecialchars((string) ($client['address_line1'] ?? ''), ENT_QUOTES) ?><br>
        <?php if (!empty($client['address_line2'])): ?>
          <?= htmlspecialchars((string) $client['address_line2'], ENT_QUOTES) ?><br>
        <?php endif; ?>
        <?= htmlspecialchars(trim(($client['postal_code'] ?? '') . ' ' . ($client['city'] ?? '')), ENT_QUOTES) ?><br>
        <?php if (!empty($client['vat_number'])): ?>N° TVA <?= htmlspecialchars((string) $client['vat_number'], ENT_QUOTES) ?><?php endif; ?>
      </div>
    </td>
  </tr>
</table>

<?php if (!empty($document['object'])): ?>
  <p><strong>Objet :</strong> <?= htmlspecialchars((string) $document['object'], ENT_QUOTES) ?></p>
<?php endif; ?>

<table class="lines">
  <thead>
    <tr>
      <th>Désignation</th>
      <th class="num">Qté</th>
      <th>Unité</th>
      <th class="num">P.U. HT</th>
      <th class="num">TVA</th>
      <th class="num">Total HT</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($lines as $line): ?>
      <tr>
        <td><?= nl2br(htmlspecialchars((string) $line['label'], ENT_QUOTES)) ?></td>
        <td class="num"><?= nf_qty($line['quantity']) ?></td>
        <td><?= htmlspecialchars((string) $line['unit'], ENT_QUOTES) ?></td>
        <td class="num"><?= nf_money((int) $line['unit_price_ht_cents']) ?></td>
        <td class="num"><?= nf_vat_label((int) $line['vat_rate_bp']) ?></td>
        <td class="num"><?= nf_money((int) $line['line_ht_cents']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals">
  <tr>
    <td>Total HT</td>
    <td class="num"><?= nf_money((int) $document['total_ht_cents']) ?></td>
  </tr>
  <tr>
    <td>TVA</td>
    <td class="num"><?= nf_money((int) $document['total_vat_cents']) ?></td>
  </tr>
  <tr class="grand">
    <td>Total TTC</td>
    <td class="num"><?= nf_money((int) $document['total_ttc_cents']) ?></td>
  </tr>
</table>

<?php if (!empty($document['notes'])): ?>
  <div class="notes">
    <strong>Notes</strong><br>
    <?= nl2br(htmlspecialchars((string) $document['notes'], ENT_QUOTES)) ?>
  </div>
<?php endif; ?>

<?php if (!empty($company['iban'])): ?>
  <div class="notes">
    <strong>Coordonnées bancaires</strong><br>
    IBAN : <?= htmlspecialchars((string) $company['iban'], ENT_QUOTES) ?>
    <?php if (!empty($company['bic'])): ?> — BIC : <?= htmlspecialchars((string) $company['bic'], ENT_QUOTES) ?><?php endif; ?>
  </div>
<?php endif; ?>

<div class="legal">
  <?php if (!empty($company['legal_decennale'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_decennale'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if (!empty($company['legal_late_penalties'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_late_penalties'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if (!empty($company['legal_recovery_fee'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_recovery_fee'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if ($isQuote && !empty($company['legal_quote_validity'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_quote_validity'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
  <?php if (!empty($company['legal_extra'])): ?>
    <p><?= nl2br(htmlspecialchars((string) $company['legal_extra'], ENT_QUOTES)) ?></p>
  <?php endif; ?>
</div>

</body>
</html>
