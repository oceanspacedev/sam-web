@extends('layout.main_tamplate')

@section('content')
    <section class="content-header">
        <!-- Main content -->
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-dark">
                            <h3 class="card-title">Plan Visit</h3>
                            <button type="button" data-toggle="modal" data-target="#exportdate"
                                class="badge bg-success mx-3 elevation-0">EXPORT</button>
                            <a href="/planvisit/export/template"><button class="badge bg-warning mx-3 elevation-0">TEMPLATE IMPORT</button></a>
                            <a href="#"><button class="badge bg-danger mx-3 elevation-0" data-toggle="modal"
                                data-target="#importPlanVisit">IMPORT</button>
                            </a>
                            <a href="#"><button class="badge bg-info mx-3 elevation-0" data-toggle="modal"
                                data-target="#addPlanVisit">ADD</button>
                            </a>
                            {{-- ACTIVATE DELETE BULK BUTTON --}}
                            {{-- <a href="#"><button class="badge bg-danger mx-3 elevation-0" data-toggle="modal"
                                data-target="#bulkDelete">DELETE BULK</button></a> --}}
                            <div class="card-tools">
                                <div class="input-group input-group-sm" style="width: 150px;">
                                    <form action="/planvisit" class="d-inline-flex">
                                        <input type="text" name="search" class="form-control float-right"
                                            placeholder="Cari">
                                        <div class="input-group-append">
                                            <button type="submit" class="btn btn-default">
                                                <i class="fas fa-search"></i>
                                            </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        @if ($message = Session::get('success'))
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                        @if ($message = Session::get('error'))
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>{{ $message }}</strong>
                            </div>
                        @endif
                    <!-- /.card-header -->
                    <div class="card-body table-responsive p-0" style="height: 500px;">
                        <table class="table table-head-fixed text-nowrap">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama</th>
                                    <th>Outlet</th>
                                    <th>Kode Outlet</th>
                                    <th>Tanggal Visit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($planVisits as $planVisit)
                                    <tr>
                                        <td>{{ $loop->iteration ?? null }}</td>
                                        <td>{{ $planVisit->user->nama_lengkap ?? null }}</td>
                                        <td>{{ $planVisit->outlet->nama_outlet ?? null }}</td>
                                        <td>{{ $planVisit->outlet->kode_outlet ?? null }}</td>
                                        <td>
                                            @if ($planVisit->schedule_scope === 'weekly' && $planVisit->period_start && $planVisit->period_end)
                                                {{ \Carbon\Carbon::parse($planVisit->period_start)->format('d M Y') }} -
                                                {{ \Carbon\Carbon::parse($planVisit->period_end)->format('d M Y') }}
                                            @else
                                                {{ $planVisit->period_start
                                                    ? \Carbon\Carbon::parse($planVisit->period_start)->format('d M Y')
                                                    : date('d M Y', strtotime($planVisit->tanggal_visit)) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- /.card-body -->
                </div>
                <!-- /.card -->
            </div>
        </div>
        </div><!-- /.container-fluid -->

    </section>

     <!-- Modal -->
     <div class="modal fade" id="exportdate" tabindex="-1" role="dialog" aria-labelledby="exportdateLabel"
     aria-hidden="true">
     <div class="modal-dialog" role="document">
         <div class="modal-content">
             <form method="GET" action="/planvisit/export">
                 <div class="modal-header">
                     <h5 class="modal-title" id="exportdateLabel">Export</h5>
                     <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                         <span aria-hidden="true">&times;</span>
                     </button>
                 </div>
                 <div class="modal-body">
                     <div class="form-group">
                         <label for="tanggal1">Dari</label>
                         <input class="form-control" type="date" id="tanggal1" name="tanggal1" placeholder="Tanggal" required>
                     </div>
                     <div class="form-group">
                         <label for="tanggal2">Sampai</label>
                         <input class="form-control" type="date" id="tanggal2" name="tanggal2" placeholder="Tanggal" required>
                     </div>
                 </div>

                 <div class="modal-footer">
                     <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                     <button type="submit" class="btn btn-primary">Export</button>
                 </div>
             </form>

         </div>
     </div>
 </div>
    <!-- /.content -->
    <!-- /.content-wrapper -->
    <!-- Modal BULK DELETE -->
    <form action="planvisit" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="bulkDelete" aria-labelledby="bulkDeleteLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bulkDeleteLabel">Bulk Delete Plan Visit</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="divisi" class="form-label">Pilih Divisi</label>
                            <select class="custom-select" name="divisi_id" id="divisi_id"
                                required>
                                <option value="">--Choose Divisi--</option>
                                    @foreach ($divisis as $divisi)
                                        <option value="{{ $divisi->id }}">{{ $divisi->name }}</option>
                                    @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label for="bulkDelete" class="form-label">Pilih Range Tanggal</label>
                            <input type="text" class="form-control float-right" value="" name="bulkDelete"
                                id="tanggalBulkDelete" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">DELETE</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Modal ADD-->
    <form action="/planvisit/store" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="addPlanVisit" tabindex="-1" aria-labelledby="addPlanVisitLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPlanVisitLabel">Add Plan Visit</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12">
                            <label for="nama" class="form-label">User</label>
                            <select class="form-control" id="user_id" name="user_id" required>
                                <option selected disabled>-- Pilih User --</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">
                                            {{ $user->nama_lengkap }}</option>
                                    @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label for="outlet" class="form-label">Outlet</label>
                            <select class="form-control" id="outlet_id" name="outlet_id" required>
                                <option selected disabled>-- Pilih Outlet --</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}">
                                            {{ $outlet->nama_outlet . ' - ' . $outlet->divisi->name }}</option>
                                    @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <label for="tanggal_visit" class="form-label">Pilih Tanggal Visit</label>
                            <input type="text" class="form-control float-right" value="" name="tanggal_visit"
                                id="tanggalVisit" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">TAMBAH</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Modal IMPORT-->
    <form action="/planvisit/import" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal fade" id="importPlanVisit" tabindex="-1" aria-labelledby="importPlanVisitLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="importPlanVisitLabel">Import Plan Visit</h5>
                    </div>
                    <div class="modal-body">
                        <div class="col-12 mt-3">
                            <label for="formFile" class="form-label">Pilih File</label>
                            <input class="form-control" type="file" id="formFile" name="file">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">IMPORT</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
