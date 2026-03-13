<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OSPP Conformance Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; color: #1a1a2e; font-size: 12px; line-height: 1.5; }
        .header { background: #1a1a2e; color: #fff; padding: 30px 40px; }
        .header h1 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .header p { font-size: 11px; opacity: 0.8; }
        .content { padding: 30px 40px; }
        .info-row { display: flex; margin-bottom: 20px; }
        .info-block { flex: 1; }
        .info-block label { font-size: 10px; text-transform: uppercase; color: #666; display: block; margin-bottom: 2px; }
        .info-block span { font-size: 13px; font-weight: 600; }
        .score-box { text-align: center; background: #f8f9fa; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .score-big { font-size: 48px; font-weight: 700; color: #1a1a2e; }
        .score-label { font-size: 12px; color: #666; margin-top: 4px; }
        .score-detail { font-size: 11px; color: #888; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th { background: #f1f3f5; text-align: left; padding: 8px 12px; font-size: 10px; text-transform: uppercase; color: #666; border-bottom: 2px solid #dee2e6; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; font-size: 11px; }
        .status-passed { color: #2e7d32; font-weight: 600; }
        .status-failed { color: #c62828; font-weight: 600; }
        .status-partial { color: #ef6c00; font-weight: 600; }
        .status-not_tested { color: #999; }
        .section-title { font-size: 14px; font-weight: 700; margin: 25px 0 10px; padding-bottom: 5px; border-bottom: 2px solid #1a1a2e; }
        .footer { text-align: center; padding: 20px 40px; font-size: 9px; color: #999; border-top: 1px solid #eee; margin-top: 30px; }
        .bar { height: 8px; border-radius: 4px; background: #e9ecef; margin-top: 4px; }
        .bar-fill { height: 100%; border-radius: 4px; background: #2e7d32; }
        .category-row td { vertical-align: middle; }
    </style>
</head>
<body>

<div class="header">
    <h1>OSPP Conformance Report</h1>
    <p>CSMS Sandbox &mdash; Protocol Version {{ $report->protocolVersion }}</p>
</div>

<div class="content">
    <table style="margin-bottom: 20px;">
        <tr>
            <td style="border: none; padding: 0; width: 25%;">
                <label style="font-size: 10px; text-transform: uppercase; color: #666;">Tenant</label><br>
                <strong>{{ $tenant->name }}</strong>
            </td>
            <td style="border: none; padding: 0; width: 25%;">
                <label style="font-size: 10px; text-transform: uppercase; color: #666;">Email</label><br>
                <strong>{{ $tenant->email }}</strong>
            </td>
            <td style="border: none; padding: 0; width: 25%;">
                <label style="font-size: 10px; text-transform: uppercase; color: #666;">Protocol</label><br>
                <strong>{{ $report->protocolVersion }}</strong>
            </td>
            <td style="border: none; padding: 0; width: 25%;">
                <label style="font-size: 10px; text-transform: uppercase; color: #666;">Generated</label><br>
                <strong>{{ $generatedAt->format('Y-m-d H:i') }} UTC</strong>
            </td>
        </tr>
    </table>

    <div class="score-box">
        <div class="score-big">{{ $report->percentage }}%</div>
        <div class="score-label">Overall Conformance Score</div>
        <div class="score-detail">
            {{ $report->passed }} passed &middot;
            {{ $report->failed }} failed &middot;
            {{ $report->partial }} partial &middot;
            {{ $report->notTested }} not tested &middot;
            {{ $report->totalTested }} / {{ $report->totalTested + $report->notTested }} tested
        </div>
    </div>

    <div class="section-title">Category Breakdown</div>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Passed</th>
                <th>Total Tested</th>
                <th>Score</th>
                <th style="width: 30%;">Progress</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->categories as $name => $cat)
            <tr class="category-row">
                <td><strong>{{ ucfirst(str_replace('_', ' ', $name)) }}</strong></td>
                <td>{{ $cat['passed'] }}</td>
                <td>{{ $cat['total'] }}</td>
                <td>{{ $cat['percentage'] }}%</td>
                <td>
                    <div class="bar">
                        <div class="bar-fill" style="width: {{ $cat['percentage'] }}%;"></div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="section-title">Per-Action Results</div>
    <table>
        <thead>
            <tr>
                <th>Action</th>
                <th>Status</th>
                <th>Last Tested</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->results as $result)
            <tr>
                <td>{{ $result['action'] }}</td>
                <td class="status-{{ $result['status'] }}">{{ ucfirst($result['status'] === 'not_tested' ? 'Not Tested' : $result['status']) }}</td>
                <td>{{ $result['last_tested_at'] ? \Carbon\Carbon::parse($result['last_tested_at'])->format('Y-m-d H:i') : '—' }}</td>
                <td>
                    @if(!empty($result['error_details']))
                        @foreach(array_slice($result['error_details'], 0, 2) as $err)
                            <span style="color: #c62828;">{{ $err['message'] ?? $err['path'] ?? '' }}</span><br>
                        @endforeach
                    @elseif(!empty($result['behavior_checks']))
                        @foreach(collect($result['behavior_checks'])->where('passed', false)->take(2) as $check)
                            <span style="color: #ef6c00;">{{ $check['rule'] }}: {{ $check['detail'] ?? 'failed' }}</span><br>
                        @endforeach
                    @else
                        &mdash;
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="footer">
    Generated by CSMS Sandbox &mdash; csms-sandbox.ospp-standard.org
</div>

</body>
</html>
