@extends('layout.main_tamplate')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card card-dark">
                            <!-- /.card-header -->
                            <div class="card-header">
                                <h3 class="card-title">EDIT &raquo; {{ $noo->nama_outlet }}</h3>

                            </div>
                            <div class="card-body">
                                <form action="/noo/{{ $noo->id }}" method="POST">
                                    @csrf
                                    <div class="mb-3 col-lg-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="custom-select col-lg-12" name="status" id="status" required>
                                            @if ($noo->limit)
                                                <option value="PENDING">PENDING</option>
                                                <option value="CONFIRMED">CONFIRMED</option>
                                            @else
                                                <option value="PENDING">PENDING</option>
                                            @endif

                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-3">Update</button>
                                </form>

                            </div>
                        </div>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
        </section>
    </section>
    <!-- /.content -->
@endsection
