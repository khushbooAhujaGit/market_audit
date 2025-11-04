@extends('template.layouts.simple.master')
@section('content')

    <div class="page-body">
        <h4 class="mb-3">Project Template Values</h4>

        <form method="GET" action="{{route('get_all_template_data')}}" class="mb-4">
            <input type="hidden" name="project_template_id" value="{{ request('project_template_id') }}">
            <div class="row">
                <div class="col-md-3">
                    <select name="search_key" id="search_key" class="form-control" onchange="searchData()" >
                        @foreach ($headIds as $id)
                            <option value="{{ $id }}" {{ request('search_key') == $id ? 'selected' : '' }}>
                                {{ $headMappings[$id] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search_value" class="form-control"
                           placeholder="Search multiple values (comma separated)"
                           value="{{ request('search_value') }}" onkeyup="searchData()"  >
{{--                    <input type="text" name="search_value" id="search_value" class="form-control" placeholder="Search value..."--}}
{{--                           value="{{ request('search_value') }}" onkeyup="searchData()" >--}}
                </div>
                <div class="col-md-3">
                    <select name="sort_by" class="form-control">
                        <option value="">Sort By</option>
                        @foreach ($headIds as $id)
                            <option value="{{ $id }}" {{ request('sort_by') == $id ? 'selected' : '' }}>
                                {{ $headMappings[$id] ?? 'Head ' . $id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="sort_order" class="form-control">
                        <option value="asc" {{ request('sort_order') == 'asc' ? 'selected' : '' }}>ASC</option>
                        <option value="desc" {{ request('sort_order') == 'desc' ? 'selected' : '' }}>DESC</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary w-100">Go</button>
                </div>
            </div>
        </form>

        <div id="results">
            <table class="table table-bordered" id="template_table">
                <thead>
                <tr>
                    <th>Row ID</th>
                    @foreach ($headIds as $id)
                        <th>{{ $headMappings[$id] ?? 'Head ' . $id }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($data as $row)
                    <tr>
                        <td>{{ $row->id }}</td>
                        @foreach ($headIds as $id)
                            <td>{{ $row->{'head_'.$id} }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="d-flex justify-content-center">
                {{ $data->appends(request()->query())->links() }}
            </div>
        </div>

    </div>
@endsection
@section('scripts')
    <script>
        const templates_table = $("#template_table").DataTable({
            paging: false
        });
        function searchData(){
            let search_key = $('#search_key').val();
            let search_value = $('#search_value').val();

            $.ajax({
                url: "{{ route('get_all_template_data') }}",
                method: "GET",
                data: {
                    search_key: search_key,
                    search_value: search_value,
                    project_template_id: '{{ request('project_template_id') }}'
                },
                success: function (res) {
                    let html = '<table class="table table-bordered"><thead><tr><th>Row ID</th>';

                    res.headIds.forEach(id => {
                        html += `<th>${res.headMappings[id] ?? 'Head ' + id}</th>`;
                    });

                    html += '</tr></thead><tbody>';

                    res.data.forEach(row => {
                        html += '<tr>';
                        html += `<td>${row.id}</td>`;
                        res.headIds.forEach(id => {
                            html += `<td>${row['head_' + id] ?? ''}</td>`;
                        });
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    html += `<div class="d-flex justify-content-center">${res.pagination}</div>`;

                    $('#results').html(html);
                }
            });
        }
    </script>
@endsection
