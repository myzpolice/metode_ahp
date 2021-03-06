@extends('layouts.app')
@section('title')
{{ $title }}
@stop
@section('breadcrumb')
{!! Breadcrumbs::render('criteria') !!}
@stop
@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Dashboard</div>

                <div class="panel-body">
                    <table class="table table-bordered table-hover table-striped table-condensed">
                      <tr>
                        <th>ID</th>
                        <th>Criteria</th>
                        <th>Action</th>
                      </tr>
                      <?php if(count($criteria) == 0){ ?>
                      <tr>
                        <td colspan="3">Tidak ada Data</td>
                      </tr>
                      <?php } else { ?>
                      @foreach($criteria as $criterias)
                      <tr>

                        <td>{{ $criterias->id }}</td>
                        <td>{{ $criterias->criteria }}</td>
                        <td>
                          {!! link_to_route('criteria.edit', 'Edit', array($criterias->id), array('class' => 'btn btn-info')) !!}

                          <button class="btn btn-danger delete" data-id="{{ $criterias  ->id }}" data-token="{{ csrf_token() }}">Delete</button>
                        </td>
                      </tr>
                      @endforeach
                      <?php } ?>
                    </table>
                    <a class="btn btn-info" href="{{ route('criteria.create') }}">Create New Criteria</a>
                </div>
            </div>
        </div>
    </div>
</div>
@stop
@section('script')
<script>

  $('.delete').on('click', function(){
    var id = $(this).data('id');
    var token = $(this).data('token');
    $.ajax({
      url: 'criteria/' + id,
      type: 'POST',
      data: {'_method': 'DELETE', '_token':token},
      success: function(){
        console.log('Success Delete!');
        window.location.href = "{{ url('criteria') }}";
      }
    });
  });

</script>
@endsection
