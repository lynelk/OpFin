<!doctype html>
<html lang="en-GB">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>OpFin Financial Intelligence report</title>
<style>body{font:15px/1.5 system-ui,sans-serif;max-width:1000px;margin:2rem auto;padding:1rem;color:#172333}table{border-collapse:collapse;width:100%;margin:1rem 0}td,th{border-bottom:1px solid #ccd4df;padding:.5rem;text-align:left}h1{font-size:1.8rem}code{overflow-wrap:anywhere;font-size:.8rem}.notice{padding:1rem;border:1px solid #ccd4df}@media print{body{margin:0}h2{break-after:avoid}tr{break-inside:avoid}}</style></head>
<body><h1>{{ $report['title'] }}</h1><p>{{ $report['space_name'] }} · As of {{ $report['as_of'] }}</p>
<p class="notice">{{ $report['disclaimer'] }}</p>
@foreach($report['analysis']['currency_metrics'] as $currency => $metrics)
<h2>{{ $currency }} portfolio</h2><table><thead><tr><th scope="col">Measure</th><th scope="col">Value</th></tr></thead><tbody>
@foreach($metrics as $metric => $value)<tr><th scope="row">{{ str_replace('_', ' ', $metric) }}</th><td>{{ $value === null ? 'Not available' : number_format($value, 0, '.', ',') }}</td></tr>@endforeach
</tbody></table>@endforeach
<h2>Definitions and limitations</h2><dl>@foreach($report['analysis']['definitions'] as $key => $definition)<dt><strong>{{ str_replace('_', ' ', $key) }}</strong></dt><dd>{{ $definition }}</dd>@endforeach</dl>
<p>Amounts are integer minor currency units. Ratios ending in bps use 100 basis points per percentage point. No cross-currency total is implied.</p>
<p>Generated {{ $report['generated_at'] }} · Report {{ $report_id }}</p><p>Content fingerprint: <code>{{ $content_hash }}</code></p><p>Source fingerprint: <code>{{ $report['source_hash'] }}</code></p></body></html>
