<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{{ ucfirst($kind) }} preview</title><style>
body{font:14px/1.6 Arial,sans-serif;color:#14233f;box-sizing:border-box;margin:0;padding:12mm}header{border-bottom:3px solid #005c5d;padding-bottom:18px}h1{color:#005c5d;margin:0}table{width:100%;border-collapse:collapse;margin:24px 0}td,th{text-align:left;padding:10px;border-bottom:1px solid #ddd}footer{margin-top:40px}.preview{color:#64748b}@page{size:A4;margin:8mm}@media print{body{padding:12mm}}
</style></head><body>@include('clinic.document-header')
<p class="preview">Document preview · Sample content, not a clinical or financial record</p>
<h2>{{ $number }}</h2><p>Date: {{ now()->format('M j, Y') }}</p><p>Patient: Sample patient</p>
@if($kind==='prescription')<table><tr><th>Medication</th><th>Directions</th></tr><tr><td>Medication name</td><td>Instructions supplied by the prescriber</td></tr></table>@if($documents['show_license'])<p>Doctor license: License number</p>@endif
@else<table><tr><th>Description</th><th>Amount</th></tr><tr><td>Sample consultation</td><td>{{ $billing['currency'] }} {{ number_format($billing['consultation_fee'],2) }}</td></tr></table>@endif
@if($documents['show_signature'])<p style="margin-top:50px">________________________<br>Signature / stamp</p>@endif
<footer>{{ $documents[$kind.'_footer'] ?? '' }}</footer></body></html>
