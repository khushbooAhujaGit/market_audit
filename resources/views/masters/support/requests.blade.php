@extends('template.layouts.simple.master')
@section('content')
    @php
        $statusLabels = ['open' => ['Open', 'bg-warning text-dark'], 'resolved' => ['Resolved', 'bg-success']];
        $filterUrl = fn($s) => route('support_requests.index') . ($s || $search !== '' ? '?' . http_build_query(array_filter(['status' => $s, 'search' => $search])) : '');
    @endphp
    <div class="page-body">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4>Support Requests</h4>
                            <p class="text-muted mb-0 mt-1" style="text-transform:none;">Messages sent from the public Support page
                                (<a href="{{ route('support.show') }}" target="_blank">{{ route('support.show') }}</a>). The sender gets a thank-you email with the reference number.</p>
                        </div>
                        <div class="card-body">
                            @if (session('message'))
                                <div class="alert alert-success">{{ session('message') }}</div>
                            @endif

                            <form method="GET" action="{{ route('support_requests.index') }}" class="d-flex flex-wrap align-items-center gap-2 mb-3">
                                <a class="btn btn-sm {{ !$status ? 'btn-primary' : 'btn-light' }}" href="{{ $filterUrl(null) }}">All ({{ $counts->sum() }})</a>
                                @foreach ($statusLabels as $key => [$label, $class])
                                    <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-light' }}" href="{{ $filterUrl($key) }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
                                @endforeach
                                <input type="hidden" name="status" value="{{ $status }}">
                                <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm ms-auto" style="max-width:280px" placeholder="Search name, email, phone or message">
                                <button class="btn btn-sm btn-primary" type="submit">Search</button>
                            </form>

                            <div class="table-responsive theme-scrollbar">
                                <table class="display" id="support_table" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th style="width:50px">Ref</th>
                                            <th style="width:120px">Received</th>
                                            <th style="width:110px">Name</th>
                                            <th style="min-width:230px">Contact</th>
                                            <th style="min-width:280px">Message</th>
                                            <th style="width:100px">Status</th>
                                            <th style="width:120px">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($requests as $item)
                                            @php [$label, $class] = $statusLabels[$item->status] ?? [$item->status, 'bg-secondary']; @endphp
                                            <tr>
                                                <td class="text-nowrap">#{{ $item->id }}</td>
                                                <td class="text-nowrap">{{ $item->created_at->setTimezone('Asia/Kolkata')->format('d-m-Y h:i A') }}</td>
                                                <td>{{ $item->name }}</td>
                                                <td style="overflow-wrap:anywhere;">
                                                    <div>{{ $item->email }}</div>
                                                    @if ($item->phone)<div class="small text-muted">{{ $item->phone }}</div>@endif
                                                    <div class="small {{ $item->thank_you_sent_at ? 'text-success' : 'text-muted' }}">
                                                        {{ $item->thank_you_sent_at ? 'Thank-you mail sent' : 'Thank-you mail not sent' }}</div>
                                                </td>
                                                <td style="max-width:420px; white-space:normal;">
                                                    <div style="white-space:pre-wrap;">{{ \Illuminate\Support\Str::limit($item->message, 140) }}</div>
                                                    @if (mb_strlen($item->message) > 140)
                                                        <a href="javascript:void(0)" class="small view-message" data-ref="#{{ $item->id }} · {{ $item->name }}"
                                                            data-message="{{ $item->message }}">Read full message</a>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge {{ $class }}">{{ $label }}</span>
                                                    @if ($item->status === 'resolved' && $item->resolved_at)
                                                        <div class="small text-muted mt-1">{{ $item->resolver->name ?? 'Deleted user' }},
                                                            {{ $item->resolved_at->setTimezone('Asia/Kolkata')->format('d-m-Y') }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-nowrap">
                                                    <form method="POST" action="{{ route('support_requests.status', $item->id) }}">
                                                        @csrf
                                                        <input type="hidden" name="status" value="{{ $item->status === 'open' ? 'resolved' : 'open' }}">
                                                        <button type="submit" class="btn btn-sm {{ $item->status === 'open' ? 'btn-success' : 'btn-outline-secondary' }}">
                                                            {{ $item->status === 'open' ? 'Mark resolved' : 'Reopen' }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-center text-muted py-4">No support requests{{ $status || $search !== '' ? ' match this filter' : ' yet' }}.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">{{ $requests->links() }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="support_message_modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="support_message_title">Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body"><div id="support_message_body" style="white-space:pre-wrap;"></div></div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        $(document).ready(function () {
            // Same table look as the other lists; paging and search stay on the server (the form above)
            if ($('#support_table tbody tr td').length > 1) {
                $('#support_table').DataTable({ paging: false, ordering: false, searching: false, info: false, autoWidth: false });
            }
        });

        $(document).on('click', '.view-message', function () {
            $('#support_message_title').text($(this).data('ref'));
            $('#support_message_body').text($(this).data('message'));
            $('#support_message_modal').modal('show');
        });
    </script>
@endsection
