{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: signers in players' recent pkms reports that aren't trusted yet (AnticheatToolsController@trustSigner). --}}
@php
    $signerSuggestions = App\Libraries\TomYumPkmsReports::signerSuggestions($trustedSigners);
@endphp
<p class="tomyum-panel__hint" style="margin-top: 14px">
    <strong>Seen in players' reports, not trusted:</strong> signers from the last 500 pkms reports' <code>[not in allowlist: …]</code>.
    Trust only a real vendor (audio, overlay, antivirus, peripheral software); never one that ships a cheat.
</p>
@if (count($signerSuggestions) === 0)
    <p class="tomyum-panel__hint">None: every signer seen recently is trusted.</p>
@else
    <table class="tomyum-panel__table">
        <thead>
            <tr>
                <th>signer CN</th>
                <th>latest module</th>
                <th style="width: 80px">players</th>
                <th style="width: 80px">reports</th>
                <th style="width: 150px">last seen</th>
                <th style="width: 90px"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($signerSuggestions as $suggestion)
                <tr>
                    <td><strong>{{ $suggestion['signer'] }}</strong></td>
                    <td><code>{{ $suggestion['module'] ?? '?' }}</code></td>
                    <td>{{ $suggestion['players'] }}</td>
                    <td>{{ $suggestion['reports'] }}</td>
                    <td>{!! timeago(parse_time_to_carbon($suggestion['last_seen'])) !!}</td>
                    <td>
                        <form method="POST" action="{{ route('panel.admin.anticheat.trust-signer') }}" onsubmit="return confirm('Trust {{ addslashes($suggestion['signer']) }}? Its modules stop flagging pkms reports.')">
                            @csrf
                            <input type="hidden" name="signer" value="{{ $suggestion['signer'] }}">
                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Trust</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
