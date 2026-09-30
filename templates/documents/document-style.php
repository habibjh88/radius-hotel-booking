<?php
/**
 * Shared print style for invoices and receipts. Plain CSS and tables only:
 * Pro renders the same HTML to PDF (dompdf), which has no flexbox or grid.
 *
 * Override from a theme at `radius-hotel-booking/documents/document-style.php`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'ABSPATH' ) || exit;
?>
<style>
	@page { size: A4; margin: 16mm 14mm; }
	* { box-sizing: border-box; }
	body { margin: 0; background: #f3f4f6; color: #111827; font-family: "DejaVu Sans", Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.5; }
	.sheet { max-width: 800px; margin: 24px auto; padding: 32px; background: #fff; border: 1px solid #e5e7eb; position: relative; }
	table { width: 100%; border-collapse: collapse; }
	td, th { vertical-align: top; }
	.logo { max-height: 60px; max-width: 200px; }
	.hotel-name { font-size: 16px; font-weight: bold; margin: 0 0 4px; }
	.muted { color: #6b7280; }
	.doc-title { font-size: 22px; font-weight: bold; letter-spacing: 0.04em; text-align: right; margin: 0; }
	.meta td { padding: 1px 0; }
	.meta td.label { color: #6b7280; padding-right: 12px; text-align: right; white-space: nowrap; }
	.meta td.value { text-align: right; font-weight: bold; }
	.box { margin-top: 24px; }
	.box-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; margin: 0 0 4px; }
	.lines { margin-top: 24px; }
	.lines th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; border-bottom: 2px solid #111827; padding: 6px 4px; }
	.lines td { border-bottom: 1px solid #e5e7eb; padding: 8px 4px; }
	.num, .lines th.num { text-align: right; white-space: nowrap; }
	.totals { width: 55%; margin: 16px 0 0 auto; }
	.totals td { padding: 4px; }
	.totals .grand td { border-top: 2px solid #111827; font-size: 14px; font-weight: bold; padding-top: 8px; }
	.note { margin-top: 24px; padding: 10px 12px; border: 1px solid #e5e7eb; background: #f9fafb; }
	.flag { display: inline-block; padding: 2px 8px; border: 1px solid #b91c1c; color: #b91c1c; font-weight: bold; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; }
	.stamp { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 72px; font-weight: bold; color: rgba(185, 28, 28, 0.15); transform: rotate(-20deg); pointer-events: none; }
	.footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #6b7280; white-space: pre-line; }
	.actions { max-width: 800px; margin: 16px auto 0; text-align: right; }
	.actions button { font: inherit; font-size: 14px; padding: 8px 16px; border: 0; border-radius: 6px; background: #1d4ed8; color: #fff; cursor: pointer; }
	@media print {
		body { background: #fff; }
		.sheet { margin: 0; padding: 0; border: 0; max-width: none; }
		.actions { display: none; }
	}
</style>
